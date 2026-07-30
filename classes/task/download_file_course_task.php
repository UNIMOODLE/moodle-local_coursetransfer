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
use local_coursetransfer\coursetransfer_request;
use local_coursetransfer\coursetransfer_restore;
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

    /**
     * Execute.
     *
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function execute() {

        $this->log_start("Download File Backup Course Remote and Restore Starting...");
        $fileurle = $this->get_custom_data()->fileurl;
        $requestid = $this->get_custom_data()->requestid;
        $request = coursetransfer_request::get($requestid);

        // Large backups need time and memory; remove the limits for this task and
        // record any uncatchable fatal (timeout/OOM) into the request log.
        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);
        coursetransfer_request::register_fatal_shutdown((int)$requestid, '13099');

        try {
            // Mark as "downloading" so the request shows progress while the file is fetched.
            $request->status = coursetransfer_request::STATUS_DOWNLOAD;
            $request->downloaded = 0;
            coursetransfer_request::insert_or_update($request, $request->id);

            // Download with Moodle's cURL client so the HTTP response can be inspected.
            // A throttled progress callback bumps timemodified (heartbeat) so a large or
            // stalled download is observable in the logs (LCT-022): if "Downloading" stays
            // but timemodified stops advancing, the download is stuck.
            $curl = new curl();
            $reqid = (int)$request->id;
            $lastheartbeat = 0;
            $curl->setopt([
                    'CURLOPT_NOPROGRESS' => 0,
                    'CURLOPT_PROGRESSFUNCTION' =>
                            function($res, $dltotal, $dlnow, $ultotal, $ulnow) use ($reqid, &$lastheartbeat) {
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
            // Stream the download to a temporary file on disk instead of holding the
            // whole .mbz in memory: a large backup (>1.5 GB) exhausts the PHP memory
            // limit otherwise (LCT-011 / fatal 13099 OOM). download_one() writes the
            // body directly to the file via CURLOPT_FILE. The temp dir is auto-cleaned.
            $tmpfile = make_request_directory() . '/local_coursetransfer_' . $reqid . '.mbz';
            $result = $curl->download_one($fileurle, null, ['filepath' => $tmpfile]);
            $info = $curl->get_info();
            $httpcode = (int)($info['http_code'] ?? 0);

            // 1. Transport / HTTP error. download_one() returns true on success or an
            // error string, and removes the temp file on failure.
            if ($result !== true || $curl->get_errno() || $httpcode !== 200) {
                $this->set_request_error($request, '13001',
                        'HTTP ' . $httpcode . ': ' .
                        (is_string($result) && $result !== '' ? $result :
                                ($curl->error !== '' ? $curl->error : 'request failed in file download')));
                $this->log_finish("Download File Backup Course Remote and Restore Finishing...");
                return;
            }

            // 2. The body is a Moodle web service error (JSON) instead of the MBZ.
            // A valid .mbz is gzip-compressed: it starts with the magic bytes 0x1f 0x8b.
            // Read only the first bytes from disk so a huge valid file is never loaded.
            $fh = fopen($tmpfile, 'rb');
            $magic = $fh ? fread($fh, 2) : '';
            if ($fh) {
                fclose($fh);
            }
            if ($magic !== "\x1f\x8b") {
                $size = filesize($tmpfile);
                // The error payload is small: read the first 4 KB to extract the message.
                $body = (string) @file_get_contents($tmpfile, false, null, 0, 4096);
                $error = json_decode($body);
                if ($error && !empty($error->errorcode)) {
                    // e.g. "sitepolicynotagreed: No ha aceptado la política del sitio [debuginfo]".
                    $msg = $error->errorcode . ': ' . ($error->error ?? '');
                    if (!empty($error->debuginfo)) {
                        $msg .= ' [' . $error->debuginfo . ']';
                    }
                    $this->set_request_error($request, '13002', $msg);
                } else {
                    $this->set_request_error($request, '13003',
                            'Downloaded file is not a valid MBZ backup (' . $size . ' bytes)');
                }
                $this->log_finish("Download File Backup Course Remote and Restore Finishing...");
                return;
            }

            // 3. Valid backup: store it from the file on disk (streamed copy, no full
            // in-memory load) and queue the restore.
            $fs = get_file_storage();
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
            $file = $fs->create_file_from_pathname($fileinfo, $tmpfile);
            $this->log('Backup File Dowload in Moodle Success!');
            $request->status = coursetransfer_request::STATUS_DOWNLOADED;
            $request->downloaded = filesize($tmpfile);
            coursetransfer_request::insert_or_update($request, $request->id);
            coursetransfer_restore::create_task_restore_course($request, $file);
        } catch (\Exception $e) {
            $this->set_request_error($request, '13000', $e->getMessage());
        }
        $this->log_finish("Download File Backup Course Remote and Restore Finishing...");
    }

    /**
     * Mark the request as errored, log the message and persist it.
     *
     * @param stdClass $request Request record.
     * @param string $code Error code.
     * @param string $message Error message.
     * @throws dml_exception
     * @throws moodle_exception
     */
    private function set_request_error(stdClass $request, string $code, string $message): void {
        $this->log($message);
        $request->status = coursetransfer_request::STATUS_ERROR;
        $request->error_code = $code;
        $request->error_message = $message;
        coursetransfer_request::insert_or_update($request, $request->id);
    }
}
