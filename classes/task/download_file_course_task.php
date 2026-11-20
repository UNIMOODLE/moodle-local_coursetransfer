<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

// Project implemented by the "Recovery, Transformation and Resilience Plan.
// Funded by the European Union - Next GenerationEU".
//
// Produced by the UNIMOODLE University Group: Universities of
// Valladolid, Complutense de Madrid, UPV/EHU, León, Salamanca,
// Illes Balears, Valencia, Rey Juan Carlos, La Laguna, Zaragoza, Málaga,
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos.

/**
 * download_file_course_task
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer\task;

use context_course;
use core_php_time_limit;
use curl;
use dml_exception;
use local_coursetransfer\api\request;
use local_coursetransfer\coursetransfer_request;
use local_coursetransfer\coursetransfer_restore;
use local_coursetransfer\coursetransfer_sites;
use moodle_exception;
use stdClass;

/**
 * download_file_course_task
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class download_file_course_task extends \core\task\adhoc_task {
    // Use the logging trait to get some nice, juicy, logging.
    use \core\task\logging_trait;

    /** @var int Seconds below LOW_SPEED_LIMIT bytes/s before a stalled transfer is aborted. */
    const LOW_SPEED_TIME = 300;

    /** @var int Bytes per second under which the transfer counts as stalled. */
    const LOW_SPEED_LIMIT = 1024;

    /**
     * Execute.
     *
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function execute() {

        $this->log_start("Download File Backup Course Remote and Restore Starting...");
        $requestid = (int)($this->get_custom_data()->requestid ?? 0);
        $request = coursetransfer_request::get($requestid);
        if (!$request) {
            $this->log_finish('Request ' . $requestid . ' not found: nothing to download.');
            return;
        }
        // Only a request waiting for its backup is downloaded: a cron retry must not store a second
        // file nor queue a second restore (LCT-051).
        $waiting = [coursetransfer_request::STATUS_BACKUP, coursetransfer_request::STATUS_DOWNLOAD];
        if (!in_array((int)$request->status, $waiting, true)) {
            $this->log_finish('Request ' . $requestid . ' is not waiting for its download (status ' . $request->status . ').');
            return;
        }

        // Large backups need time and memory; remove the limits for this task and
        // record any uncatchable fatal (timeout/OOM) into the request log.
        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);
        coursetransfer_request::register_fatal_shutdown($requestid, '13099');

        $tmpfile = '';
        try {
            // Tasks queued by 2.1 carry the token in the URL: the URL is used without it (LCT-011).
            $fileurl = strtok((string)$this->get_custom_data()->fileurl, '?');
            $legacytoken = '';
            $query = (string)parse_url((string)$this->get_custom_data()->fileurl, PHP_URL_QUERY);
            if ($query !== '') {
                parse_str($query, $params);
                $legacytoken = (string)($params['token'] ?? '');
            }
            if ($legacytoken !== '') {
                // Deprecated in 2.2, removed in 2.3 (Decision 4): queued by 2.1. The token itself is never logged.
                $this->log('DEPRECATED: download task queued by 2.1 with the token in its URL; it is no longer used.');
            }
            $token = $this->get_origin_token($request, $legacytoken);

            // Mark as "downloading" so the request shows progress while the file is fetched.
            coursetransfer_request::update_fields($requestid, [
                'status' => coursetransfer_request::STATUS_DOWNLOAD,
                'downloaded' => 0,
                'fileurl' => $fileurl,
            ]);

            $maxmb = (int)get_config('local_coursetransfer', 'target_restore_course_max_size');
            $maxbytes = $maxmb > 0 ? $maxmb * 1000000 : 0;
            // Stream the download to a temporary file on disk instead of holding the
            // whole .mbz in memory (LCT-011 / fatal 13099 OOM).
            $tmpfile = make_request_directory() . '/local_coursetransfer_' . $requestid . '.mbz';
            $res = $this->fetch($fileurl, $token, $tmpfile, $maxbytes);

            // 1. Transport / HTTP error.
            if ($res['result'] !== true || $res['errno'] || $res['httpcode'] !== 200) {
                // 63 = CURLE_FILESIZE_EXCEEDED.
                $code = (int)$res['errno'] === 63 ? '13005' : '13001';
                if (in_array((int)$res['errno'], request::TLS_ERRNOS, true)) {
                    $this->set_request_error($requestid, $code, request::transport_error(
                        (int)$res['errno'],
                        (string)$res['error'],
                        (string)$request->siteurl
                    ));
                    return;
                }
                $this->set_request_error($requestid, $code, 'HTTP ' . $res['httpcode'] . ': ' .
                    (is_string($res['result']) && $res['result'] !== '' ? $res['result'] :
                    ($res['error'] !== '' ? $res['error'] : 'request failed in file download')));
                return;
            }

            // 2. The body is a Moodle web service error (JSON) instead of the MBZ.
            // A valid .mbz is gzip-compressed: it starts with the magic bytes 0x1f 0x8b.
            // Read only the first bytes from disk so a huge valid file is never loaded.
            $size = (int)filesize($tmpfile);
            $fh = fopen($tmpfile, 'rb');
            $magic = $fh ? fread($fh, 2) : '';
            if ($fh) {
                fclose($fh);
            }
            if ($magic !== "\x1f\x8b") {
                // The error payload is small: read the first 4 KB to extract the message.
                $body = (string) @file_get_contents($tmpfile, false, null, 0, 4096);
                $error = json_decode($body);
                if ($error && !empty($error->errorcode)) {
                    // For example: "sitepolicynotagreed: No ha aceptado la política del sitio [debuginfo]".
                    $msg = $error->errorcode . ': ' . ($error->error ?? '');
                    if (!empty($error->debuginfo)) {
                        $msg .= ' [' . $error->debuginfo . ']';
                    }
                    $this->set_request_error($requestid, '13002', $msg);
                } else {
                    $this->set_request_error(
                        $requestid,
                        '13003',
                        'Downloaded file is not a valid MBZ backup (' . $size . ' bytes)'
                    );
                }
                return;
            }

            // 3. Size checks: the limit of this site, and the size the origin announced.
            if ($maxbytes > 0 && $size > $maxbytes) {
                $this->set_request_error($requestid, '13005', get_string('backupsize_larger', 'local_coursetransfer') .
                    ': (' . ($size / 1000000) . ' MB) > ' . $maxmb . ' MB');
                return;
            }
            $expected = (int)($request->origin_backup_size ?? 0);
            if ($expected > 0 && $size !== $expected) {
                $this->set_request_error($requestid, '13004', 'Downloaded backup is ' . $size .
                    ' bytes, the origin announced ' . $expected . ' bytes');
                return;
            }

            // 4. Valid backup: store it from the file on disk and queue the restore.
            $this->log('Backup File Dowload Success!');
            $context = context_course::instance($request->target_course_id);
            $filename = 'local_coursetransfer_' . $request->origin_course_id . '_' . time() . '.mbz';
            $fileinfo = [
                    'contextid' => $context->id,
                    'component' => 'backup',
                    'filearea' => 'course',
                    'itemid' => 0,
                    'filepath' => '/',
                    'filename' => $filename,
            ];
            $file = get_file_storage()->create_file_from_pathname($fileinfo, $tmpfile);
            $this->log('Backup File Dowload in Moodle Success!');
            coursetransfer_request::update_fields($requestid, [
                'status' => coursetransfer_request::STATUS_DOWNLOADED,
                'downloaded' => $size,
            ]);
            coursetransfer_restore::create_task_restore_course($request, $file);
        } catch (\Throwable $e) {
            $this->set_request_error($requestid, '13000', $e->getMessage());
        } finally {
            // The request directory is only removed at shutdown: a long cron run would keep every copy.
            if ($tmpfile !== '' && file_exists($tmpfile)) {
                @unlink($tmpfile);
            }
            $this->log_finish("Download File Backup Course Remote and Restore Finishing...");
        }
    }

    /**
     * Token of the request's origin, as registered on this site now.
     *
     * @param stdClass $request
     * @param string $legacytoken Token carried by a URL queued by 2.1, used if the origin is no longer registered.
     * @return string
     * @throws moodle_exception
     */
    protected function get_origin_token(stdClass $request, string $legacytoken): string {
        try {
            return (string)coursetransfer_sites::get_by_host('origin', (string)$request->siteurl)->token;
        } catch (moodle_exception $e) {
            if ($legacytoken !== '') {
                return $legacytoken;
            }
            throw $e;
        }
    }

    /**
     * cURL options of the transfer.
     *
     * @param int $maxbytes Size limit in bytes (0 = none).
     * @return array
     */
    public function get_curl_options(int $maxbytes): array {
        $timeout = (int)get_config('local_coursetransfer', 'request_timeout');
        $options = [
            // The token goes to the origin: verify who it is (sslverifypeer), and never follow a redirect elsewhere.
            'CURLOPT_FOLLOWLOCATION' => 0,
            'CURLOPT_CONNECTTIMEOUT' => $timeout > 0 ? $timeout : 20,
            // No total timeout (backups can be large): abort only a stalled transfer.
            'CURLOPT_LOW_SPEED_LIMIT' => self::LOW_SPEED_LIMIT,
            'CURLOPT_LOW_SPEED_TIME' => self::LOW_SPEED_TIME,
        ] + request::ssl_options();
        if ($maxbytes > 0) {
            $options['CURLOPT_MAXFILESIZE'] = $maxbytes;
        }
        return $options;
    }

    /**
     * Download the backup to a file, sending the token in the body of a POST and not in the URL.
     *
     * @param string $url Web service file URL of the origin, without token.
     * @param string $token
     * @param string $tmpfile
     * @param int $maxbytes
     * @return array ['result' => true|string, 'httpcode' => int, 'errno' => int, 'error' => string]
     */
    protected function fetch(string $url, string $token, string $tmpfile, int $maxbytes): array {
        // Same cURL security policy as the web service calls (api\request::req()).
        $ignoresecurity = (bool)get_config('local_coursetransfer', 'ignorecurlsecurity');
        $curl = new curl(['ignoresecurity' => $ignoresecurity]);
        // A throttled progress callback bumps timemodified (heartbeat) so a large or
        // stalled download is observable in the logs (LCT-022).
        $reqid = (int)$this->get_custom_data()->requestid;
        $lastheartbeat = 0;
        $curl->setopt($this->get_curl_options($maxbytes) + [
            'CURLOPT_NOPROGRESS' => 0,
            'CURLOPT_PROGRESSFUNCTION' => function ($res, $dltotal, $dlnow, $ultotal, $ulnow) use ($reqid, &$lastheartbeat) {
                global $DB;
                $now = time();
                if ($dlnow > 0 && ($now - $lastheartbeat) >= 5) {
                    $lastheartbeat = $now;
                    $DB->set_field('local_coursetransfer_request', 'downloaded', (int)$dlnow, ['id' => $reqid]);
                    $DB->set_field('local_coursetransfer_request', 'timemodified', $now, ['id' => $reqid]);
                    $totalmb = $dltotal > 0 ? ' / ' . round($dltotal / 1048576, 1) . ' MB' : '';
                    mtrace('  ... downloading ' . round($dlnow / 1048576, 1) . ' MB' . $totalmb);
                }
                return 0;
            },
        ]);
        $fh = fopen($tmpfile, 'w');
        if (!$fh) {
            return ['result' => 'Cannot write ' . $tmpfile, 'httpcode' => 0, 'errno' => 0, 'error' => ''];
        }
        // The core webservice/pluginfile.php reads the token with required_param(), which accepts POST (4.5 to 5.3).
        $result = $curl->post($url, 'token=' . rawurlencode($token), ['CURLOPT_FILE' => $fh]);
        fclose($fh);
        $info = $curl->get_info();
        return [
            'result' => $result === true || $result === '' ? true : $result,
            'httpcode' => (int)($info['http_code'] ?? 0),
            'errno' => (int)$curl->get_errno(),
            'error' => (string)$curl->error,
        ];
    }

    /**
     * Mark the request as errored, log the message and persist it.
     *
     * @param int $requestid
     * @param string $code Error code.
     * @param string $message Error message.
     * @throws dml_exception
     * @throws moodle_exception
     */
    private function set_request_error(int $requestid, string $code, string $message): void {
        $this->log($message);
        coursetransfer_request::update_fields($requestid, [
            'status' => coursetransfer_request::STATUS_ERROR,
            'error_code' => $code,
            'error_message' => $message,
        ]);
    }
}
