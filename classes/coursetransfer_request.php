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
 * Coursetransfer Request.
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetransfer;

use coding_exception;
use core_course_category;
use core_shutdown_manager;
use dml_exception;
use local_coursetransfer\models\configuration_category;
use local_coursetransfer\models\configuration_course;
use moodle_exception;
use stdClass;

/**
 * coursetransfer_request
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursetransfer_request {

    /** @var string Table */
    const TABLE = 'local_coursetransfer_request';

    /** @var int Type Course */
    const TYPE_COURSE = 0;

    /** @var int Type Category */
    const TYPE_CATEGORY = 1;

    /** @var int Type Remove Course */
    const TYPE_REMOVE_COURSE = 2;

    /** @var int Type Remove Category */
    const TYPE_REMOVE_CATEGORY = 3;

    /** @var int Direction Request */
    const DIRECTION_REQUEST = 0;

    /** @var int Direction Response */
    const DIRECTION_RESPONSE = 1;

    /** @var int Status Error */
    const STATUS_ERROR = 0;

    /** @var int Status not started */
    const STATUS_NOT_STARTED = 1;

    /** @var int Status in progress */
    const STATUS_IN_PROGRESS = 10;

    /** @var int Status Backup */
    const STATUS_BACKUP = 30;

    /** @var int Status Download */
    const STATUS_DOWNLOAD = 50;

    /** @var int Status Downloaded */
    const STATUS_DOWNLOADED = 70;

    /** @var int Status Restore */
    const STATUS_RESTORE = 80;

    /** @var int Status Incompleted */
    const STATUS_INCOMPLETED = 90;

    /** @var int Status Completed */
    const STATUS_COMPLETED = 100;

    /**
     * Get.
     *
     * @param int $requestid
     * @return false|mixed|stdClass
     * @throws dml_exception
     */
    public static function get(int $requestid): mixed {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $requestid]);
    }

    /**
     * Get by Target Course Id.
     *
     * @param int $courseid
     * @return array
     * @throws dml_exception
     */
    public static function get_by_target_course_id(int $courseid): array {
        global $DB;
        return $DB->get_records(self::TABLE,
                ['target_course_id' => $courseid, 'type' => self::TYPE_COURSE, 'direction' => self::DIRECTION_REQUEST]);
    }

    /**
     * Get by Target Category Id.
     *
     * @param int $catid
     * @return array
     * @throws dml_exception
     */
    public static function get_by_target_category_id(int $catid): array {
        global $DB;
        return $DB->get_records(self::TABLE,
                ['target_category_id' => $catid, 'type' => self::TYPE_CATEGORY, 'direction' => self::DIRECTION_REQUEST]);
    }

    /**
     * Get by Origin Course Id.
     *
     * @param int $courseid
     * @return array
     * @throws dml_exception
     */
    public static function get_by_origin_course_id(int $courseid): array {
        global $DB;
        return $DB->get_records(self::TABLE,
                ['origin_course_id' => $courseid, 'type' => self::TYPE_COURSE, 'direction' => self::DIRECTION_RESPONSE]);
    }

    /**
     * Get by Origin Category Id.
     *
     * @param int $catid
     * @return array
     * @throws dml_exception
     */
    public static function get_by_origin_category_id(int $catid): array {
        global $DB;
        return $DB->get_records(self::TABLE,
                ['origin_category_id' => $catid, 'type' => self::TYPE_CATEGORY, 'direction' => self::DIRECTION_RESPONSE]);
    }

    /**
     * Filters
     *
     * @param array $filters
     * @return array
     * @throws dml_exception
     */
    public static function filters(array $filters): array {
        global $DB;
        $where = '';
        if (isset($filters['type'])) {
            if (empty($where)) {
                $where .= 'WHERE type = ' . $filters['type'];
            } else {
                $where .= ' AND type = ' . $filters['type'];
            }
        }
        if (isset($filters['direction'])) {
            if (empty($where)) {
                $where .= 'WHERE direction = ' . $filters['direction'];
            } else {
                $where .= ' AND direction = ' . $filters['direction'];
            }
        }
        if (isset($filters['status'])) {
            if (empty($where)) {
                $where .= 'WHERE status = ' . $filters['status'];
            } else {
                $where .= ' AND status = ' . $filters['status'];
            }
        }
        if (isset($filters['userid'])) {
            if (empty($where)) {
                $where .= 'WHERE userid = ' . $filters['userid'];
            } else {
                $where .= ' AND userid = ' . $filters['userid'];
            }
        }
        if (isset($filters['from'])) {
            if (empty($where)) {
                $where .= 'WHERE timemodified >= ' . $filters['from'];
            } else {
                $where .= ' AND timemodified >= ' . $filters['from'];
            }
        }
        if (isset($filters['to'])) {
            if (empty($where)) {
                $where .= 'WHERE timemodified <= ' . $filters['to'];
            } else {
                $where .= ' AND timemodified <= ' . $filters['to'];
            }
        }

        $sql = 'SELECT *
                FROM {' . self::TABLE . '}
                ' . $where . '
                LIMIT 201';

        return $DB->get_records_sql($sql);
    }

    /**
     * Get the plugin's adhoc tasks (this site) whose customdata references the given request.
     *
     * Single source of truth used both by the tracking page and by the retry
     * anti-duplicate check. Filters with LIKE and then confirms the exact
     * requestid via json_decode (so 12 does not match 123).
     *
     * @param int $requestid
     * @return array task_adhoc records keyed by id.
     * @throws dml_exception
     */
    public static function get_related_adhoc_tasks(int $requestid): array {
        global $DB;
        $candidates = $DB->get_records_select('task_adhoc',
                'component = :component AND ' . $DB->sql_like('customdata', ':needle'),
                ['component' => 'local_coursetransfer', 'needle' => '%"requestid":' . $requestid . '%'],
                'nextruntime ASC');
        $tasks = [];
        foreach ($candidates as $task) {
            $data = json_decode($task->customdata);
            if (isset($data->requestid) && (int)$data->requestid === $requestid) {
                $tasks[$task->id] = $task;
            }
        }
        return $tasks;
    }

    /**
     * Build the WHERE clause + params for the logs listing/export, with optional filters.
     *
     * Single source of truth for the logs table and the export endpoint. Uses
     * placeholders (no concatenation). Bare column names (single table).
     *
     * @param int $type Request type.
     * @param int $direction Request direction.
     * @param int|string|null $status Status code, or null/'' for all.
     * @param int|null $datefrom Lower bound for timemodified (timestamp).
     * @param int|null $dateto Upper bound for timemodified (timestamp).
     * @param int|null $sizeminbytes Lower bound for origin_backup_size (bytes).
     * @param int|null $sizemaxbytes Upper bound for origin_backup_size (bytes).
     * @return array [string $where, array $params]
     */
    public static function get_logs_filter_sql(int $type, int $direction, int|string $status = null, int $datefrom = null,
                                               int $dateto = null, int $sizeminbytes = null,
                                               int $sizemaxbytes = null, $origincourseid = null,
                                               $targetcourseid = null): array {
        $where = 'direction = :direction AND type = :type';
        $params = ['direction' => $direction, 'type' => $type];
        if (is_numeric($status)) {
            $where .= ' AND status = :status';
            $params['status'] = (int)$status;
        }
        if (!empty($origincourseid)) {
            $where .= ' AND origin_course_id = :origincourseid';
            $params['origincourseid'] = (int)$origincourseid;
        }
        if (!empty($targetcourseid)) {
            $where .= ' AND target_course_id = :targetcourseid';
            $params['targetcourseid'] = (int)$targetcourseid;
        }
        if (!empty($datefrom)) {
            $where .= ' AND timemodified >= :datefrom';
            $params['datefrom'] = (int)$datefrom;
        }
        if (!empty($dateto)) {
            $where .= ' AND timemodified <= :dateto';
            $params['dateto'] = (int)$dateto;
        }
        if (!empty($sizeminbytes)) {
            $where .= ' AND origin_backup_size >= :sizemin';
            $params['sizemin'] = (int)$sizeminbytes;
        }
        if (!empty($sizemaxbytes)) {
            $where .= ' AND origin_backup_size <= :sizemax';
            $params['sizemax'] = (int)$sizemaxbytes;
        }
        return [$where, $params];
    }

    /**
     * WHERE + params for the unified executions log (all types and
     * directions in one query). Placeholders only. Columns are prefixed
     * with "r." so the caller can join {course} for the local target name.
     *
     * Accepted filters: statusgroup ('prog'|'wait'|'done'|'err'),
     * type (0..3), dir ('in'|'out'), site (siteurl), q (course search),
     * from / to (timestamps on timemodified).
     *
     * @param array $filters
     * @return array [string $where, array $params]
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function get_executions_filter_sql(array $filters): array {
        global $DB;
        $where = ['1 = 1'];
        $params = [];

        $groups = [
            'prog' => [self::STATUS_IN_PROGRESS, self::STATUS_BACKUP, self::STATUS_DOWNLOAD,
                    self::STATUS_DOWNLOADED, self::STATUS_RESTORE],
            'wait' => [self::STATUS_NOT_STARTED],
            'done' => [self::STATUS_COMPLETED],
            'err' => [self::STATUS_ERROR, self::STATUS_INCOMPLETED],
        ];
        if (!empty($filters['statusgroup']) && isset($groups[$filters['statusgroup']])) {
            [$insql, $inparams] = $DB->get_in_or_equal($groups[$filters['statusgroup']], SQL_PARAMS_NAMED, 'st');
            $where[] = "r.status $insql";
            $params += $inparams;
        }
        if (isset($filters['type']) && is_numeric($filters['type']) && (int)$filters['type'] >= 0) {
            $where[] = 'r.type = :ftype';
            $params['ftype'] = (int)$filters['type'];
        }
        // Direction of the exchange from this site's point of view:
        // "in" (I pull) = restores initiated here or removes requested by a peer;
        // "out" (I serve/act outwards) = the inverse combinations.
        if (!empty($filters['dir']) && in_array($filters['dir'], ['in', 'out'], true)) {
            $restores = '(r.type = ' . self::TYPE_COURSE . ' OR r.type = ' . self::TYPE_CATEGORY . ')';
            if ($filters['dir'] === 'in') {
                $where[] = "(($restores AND r.direction = " . self::DIRECTION_REQUEST . ')'
                        . ' OR (NOT ' . $restores . ' AND r.direction = ' . self::DIRECTION_RESPONSE . '))';
            } else {
                $where[] = "(($restores AND r.direction = " . self::DIRECTION_RESPONSE . ')'
                        . ' OR (NOT ' . $restores . ' AND r.direction = ' . self::DIRECTION_REQUEST . '))';
            }
        }
        if (!empty($filters['site'])) {
            $compare = $DB->sql_compare_text('r.siteurl', 255);
            $where[] = "$compare = " . $DB->sql_compare_text(':fsite', 255);
            $params['fsite'] = $filters['site'];
        }
        if (!empty($filters['q'])) {
            $like1 = $DB->sql_like('r.origin_course_fullname', ':fq1', false, false);
            $like2 = $DB->sql_like('r.origin_category_name', ':fq2', false, false);
            $like3 = $DB->sql_like('c.fullname', ':fq3', false, false);
            $where[] = "($like1 OR $like2 OR $like3)";
            $needle = '%' . $DB->sql_like_escape($filters['q']) . '%';
            $params['fq1'] = $needle;
            $params['fq2'] = $needle;
            $params['fq3'] = $needle;
        }
        if (!empty($filters['from'])) {
            $where[] = 'r.timemodified >= :ffrom';
            $params['ffrom'] = (int)$filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'r.timemodified <= :fto';
            $params['fto'] = (int)$filters['to'];
        }
        return [implode(' AND ', $where), $params];
    }

    /**
     * Unified executions page: one slice of the request log across all
     * types and directions, newest first. Includes the LOCAL target
     * course fullname when it exists (targetcoursename).
     *
     * @param array $filters see get_executions_filter_sql()
     * @param int $page zero-based page
     * @param int $perpage
     * @return stdClass[]
     * @throws dml_exception|coding_exception
     */
    public static function get_executions(array $filters, int $page, int $perpage): array {
        global $DB;
        [$where, $params] = self::get_executions_filter_sql($filters);
        $sql = 'SELECT r.*, c.fullname AS targetcoursename
                  FROM {' . self::TABLE . '} r
             LEFT JOIN {course} c ON c.id = r.target_course_id
                 WHERE ' . $where . '
              ORDER BY r.timemodified DESC, r.id DESC';
        return $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
    }

    /**
     * Total rows for the unified executions page.
     *
     * @param array $filters see get_executions_filter_sql()
     * @return int
     * @throws dml_exception|coding_exception
     */
    public static function count_executions(array $filters): int {
        global $DB;
        [$where, $params] = self::get_executions_filter_sql($filters);
        $sql = 'SELECT COUNT(1)
                  FROM {' . self::TABLE . '} r
             LEFT JOIN {course} c ON c.id = r.target_course_id
                 WHERE ' . $where;
        return (int)$DB->count_records_sql($sql, $params);
    }

    /**
     * Requests currently moving (or waiting for cron), newest first.
     *
     * @param int $limit
     * @return stdClass[]
     * @throws dml_exception|coding_exception
     */
    public static function get_active_executions(int $limit = 20): array {
        global $DB;
        $active = [self::STATUS_NOT_STARTED, self::STATUS_IN_PROGRESS, self::STATUS_BACKUP,
                self::STATUS_DOWNLOAD, self::STATUS_DOWNLOADED, self::STATUS_RESTORE];
        [$insql, $params] = $DB->get_in_or_equal($active, SQL_PARAMS_NAMED, 'ac');
        $sql = 'SELECT r.*, c.fullname AS targetcoursename
                  FROM {' . self::TABLE . '} r
             LEFT JOIN {course} c ON c.id = r.target_course_id
                 WHERE r.status ' . $insql . '
              ORDER BY r.timemodified DESC, r.id DESC';
        return $DB->get_records_sql($sql, $params, 0, $limit);
    }

    /**
     * Distinct site URLs present in the request log (for the site filter).
     *
     * @return string[]
     * @throws dml_exception
     */
    public static function get_execution_sites(): array {
        global $DB;
        $compare = $DB->sql_compare_text('siteurl', 255);
        $records = $DB->get_records_sql(
                "SELECT DISTINCT $compare AS siteurl FROM {" . self::TABLE . '}');
        $sites = [];
        foreach ($records as $record) {
            if (!empty($record->siteurl)) {
                $sites[] = $record->siteurl;
            }
        }
        sort($sites);
        return $sites;
    }

    /**
     * Register a shutdown handler that records an uncatchable fatal error
     * (e.g. execution timeout or out-of-memory) into the request log, so large
     * downloads/restores that die do not leave the request stuck and silent.
     *
     * @param int $requestid
     * @param string $errorcode Error code to store if a fatal happens.
     */
    public static function register_fatal_shutdown(int $requestid, string $errorcode): void {
        core_shutdown_manager::register_function(function() use ($requestid, $errorcode) {
            global $DB;
            $err = error_get_last();
            $fatalmask = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;
            if (!$err || !((int)$err['type'] & $fatalmask)) {
                return;
            }
            $request = $DB->get_record(self::TABLE, ['id' => $requestid]);
            if (!$request || in_array((int)$request->status, [self::STATUS_COMPLETED, self::STATUS_ERROR], true)) {
                return;
            }
            $request->status = self::STATUS_ERROR;
            $request->error_code = $errorcode;
            $request->error_message = 'Fatal: ' . $err['message'];
            $request->timemodified = time();
            $DB->update_record(self::TABLE, $request);
        });
    }

    /**
     * Update status request category.
     *
     * @param int $requestid
     * @return stdClass
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function update_status_request_cat(int $requestid): stdClass {
        global $DB;

        $reqcat = $DB->get_record(self::TABLE, ['id' => $requestid]);
        $courses = $DB->get_records(self::TABLE,
                ['request_category_id' => $requestid, 'type' => self::TYPE_COURSE, 'direction' => self::DIRECTION_REQUEST]);

        $completed = 1;
        foreach ($courses as $course) {
            if ($course->status < self::STATUS_COMPLETED) {
                $completed = 0;
                break;
            }
        }
        $reqcat->status = $completed === 1 ? self::STATUS_COMPLETED : self::STATUS_INCOMPLETED;
        self::insert_or_update($reqcat, $requestid);
        return $reqcat;
    }

    /**
     * Fire the request_completed event for a request row.
     *
     * Decouples the plugin from any notification policy: observers (e.g.
     * local_coursetransfermanager) decide whether to notify. The plugin itself
     * no longer sends messages/emails.
     *
     * @param stdClass $request A local_coursetransfer_request row.
     */
    public static function trigger_request_completed(stdClass $request): void {
        \local_coursetransfer\event\request_completed::create([
            'context' => \context_system::instance(),
            'objectid' => (int)$request->id,
            'userid' => (int)$request->userid,
            'other' => [
                'type' => (int)$request->type,
                'direction' => (int)$request->direction,
            ],
        ])->trigger();
    }

    /**
     * Insert or update row in table.
     *
     * @param stdClass $object
     * @param int|null $id
     * @return bool|int
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function insert_or_update(stdClass $object, int $id = null): bool|int
    {
        global $DB;
        if (!array_key_exists($object->status, coursetransfer::STATUS)) {
            throw new moodle_exception('STATUS IS NOT VALID');
        }
        if (!in_array($object->type, [0, 1, 2, 3])) {
            throw new moodle_exception('TYPE IS NOT VALID');
        }
        if (!in_array($object->direction, [0, 1])) {
            throw new moodle_exception('DIRECTION IS NOT VALID');
        }
        // Every write goes through here, so this is the single place that guarantees siteurl is
        // stored the same way it is looked up: without its trailing slash.
        if (isset($object->siteurl)) {
            $object->siteurl = coursetransfer_sites::clean_host($object->siteurl);
        }
        $object->timemodified = time();
        if (is_null($id)) {
            $object->timecreated = time();
            return $DB->insert_record(self::TABLE, $object);
        } else {
            $object->id = $id;
            return $DB->update_record(self::TABLE, $object);
        }
    }

    /**
     * Set Request Restore Course.
     *
     * @param stdClass $user
     * @param stdClass $site
     * @param int $targetcourseid
     * @param int $origincourseid
     * @param configuration_course $configuration $configuration
     * @param array $sections
     * @param int|null $requestcatid
     * @return stdClass
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function set_request_restore_course(stdClass $user,
            stdClass $site, int $targetcourseid, int $origincourseid, configuration_course $configuration,
            array $sections, int $requestcatid = null): stdClass {
        global $USER;
        $userid = is_null($user) ? $USER->id : $user->id;
        $object = new stdClass();
        $object->type = self::TYPE_COURSE;
        $object->siteurl = $site->host;
        $object->direction = self::DIRECTION_REQUEST;
        $object->target_course_id = $targetcourseid;
        $object->origin_course_id = $origincourseid;
        $object->origin_enrolusers = $configuration->originenrolusers;
        $object->request_category_id = $requestcatid;
        $object->origin_activities = json_encode($sections);
        $object->target_remove_enrols = $configuration->targetremoveenrols;
        $object->target_remove_groups = $configuration->targetremovegroups;
        $object->origin_remove_course = $configuration->originremovecourse;
        $object->target_notremove_activities = $configuration->targetnotremoveactivities;
        $object->origin_backup_size_estimated = 0;
        $object->origin_schedule_datetime = $configuration->nextruntime;
        $object->target_target = $configuration->targettarget;
        $object->status = self::STATUS_NOT_STARTED;
        $object->userid = $userid;
        $object->id = self::insert_or_update($object);
        return $object;
    }

    /**
     * Set Request Restore Course.
     *
     * @param stdClass $user
     * @param int $targetrequestid
     * @param stdClass $targetsite
     * @param int $targetcourseid
     * @param stdClass $origincourse
     * @param configuration_course $configuration $configuration
     * @param array $sections
     * @param int|null $requestcatid
     * @return stdClass
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function set_request_restore_course_response(stdClass $user, int $targetrequestid,
            stdClass $targetsite, int $targetcourseid, stdClass $origincourse, configuration_course $configuration,
            array $sections, int $requestcatid = null): stdClass {
        global $USER;
        $user = is_null($user) ? $USER : $user;
        $origincat = core_course_category::get($origincourse->category, MUST_EXIST);

        $object = new stdClass();
        $object->type = self::TYPE_COURSE;
        $object->siteurl = $targetsite->host;
        $object->direction = self::DIRECTION_RESPONSE;
        $object->target_request_id = $targetrequestid;
        $object->request_category_id = $requestcatid;
        $object->origin_course_id = $origincourse->id;
        $object->origin_course_fullname = $origincourse->fullname;
        $object->origin_course_shortname = $origincourse->shortname;
        $object->origin_category_id = $origincourse->category;
        $object->origin_category_idnumber = $origincourse->idnumber;
        $object->origin_category_name = $origincat->name;
        $object->origin_enrolusers = $configuration->originenrolusers;
        $object->origin_remove_course = $configuration->originremovecourse;
        $object->origin_remove_category = null;
        $object->origin_schedule_datetime = $configuration->nextruntime;
        $object->origin_remove_activities = 0;
        $object->origin_activities = json_encode($sections);
        $object->origin_category_requests = null;
        $object->origin_backup_size = null;
        $object->origin_backup_size_estimated = null;
        $object->origin_backup_url = null;
        $object->target_course_id = $targetcourseid;
        $object->target_category_id = null;
        $object->target_remove_enrols = $configuration->targetremoveenrols;
        $object->target_remove_groups = $configuration->targetremovegroups;
        $object->target_target = $configuration->targettarget;
        $object->error_code = null;
        $object->error_message = null;
        $object->userid = $user->id;
        $object->status = self::STATUS_NOT_STARTED;
        $object->id = self::insert_or_update($object);
        return $object;
    }

    /**
     * Set Request Object Restore Category.
     *
     * @param stdClass $site
     * @param int $targetcategoryid
     * @param int $origincategoryid
     * @param string $origincategoryname
     * @param configuration_category $configuration $configuration
     * @param stdClass $user
     * @return stdClass
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function set_request_restore_category(
            stdClass $site, int $targetcategoryid, int $origincategoryid, string $origincategoryname,
            configuration_category $configuration, stdClass $user): stdClass {
        global $USER;
        $userid = is_null($user) ? $USER->id : $user->id;
        $object = new stdClass();
        $object->type = self::TYPE_CATEGORY;;
        $object->siteurl = $site->host;
        $object->direction = self::DIRECTION_REQUEST;
        $object->target_category_id = $targetcategoryid;
        $object->origin_category_id = $origincategoryid;
        $object->origin_category_name = $origincategoryname;
        $object->origin_category_requests = json_encode([]);
        $object->origin_activities = json_encode([]);
        $object->origin_enrolusers = $configuration->originenrolusers;
        $object->target_remove_enrols = $configuration->targetremoveenrols;
        $object->target_remove_groups = $configuration->targetremovegroups;
        $object->origin_remove_category = $configuration->originremovecategory;
        $object->origin_schedule_datetime = $configuration->nextruntime;
        $object->target_target = $configuration->targettarget;
        $object->status = self::STATUS_NOT_STARTED;
        $object->userid = $userid;
        $object->id = self::insert_or_update($object);
        return $object;
    }

    /**
     * Get Status Category Request.
     *
     * @param int $requestid
     * @return int
     * @throws dml_exception
     */
    public static function get_status_category_request(int $requestid): int {
        $request = self::get($requestid);
        $status = self::STATUS_NOT_STARTED;
        $requests = json_decode($request->origin_category_requests);
        $total = count($requests);
        foreach ($requests as $req) {
            $courserequest = self::get($req);
            if ((int)$courserequest->status > self::STATUS_NOT_STARTED) {
                $status = self::STATUS_IN_PROGRESS;
            }
            if ((int)$courserequest->status === self::STATUS_COMPLETED) {
                $total--;
            }
        }
        if ($total === 0) {
            $status = self::STATUS_COMPLETED;
        }
        return $status;
    }

    /**
     * Set Request Remove Course.
     *
     * @param stdClass $site
     * @param int $origincourseid
     * @param stdClass|null $user
     * @param null $nextruntime
     * @return stdClass
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function set_request_remove_course(
            stdClass $site, int $origincourseid, stdClass $user = null, $nextruntime = null): stdClass {
        global $USER;
        $userid = is_null($user) ? $USER->id : $user->id;
        $object = new stdClass();
        $object->type = self::TYPE_REMOVE_COURSE;
        $object->siteurl = $site->host;
        $object->direction = self::DIRECTION_REQUEST;
        $object->origin_course_id = $origincourseid;
        $object->origin_schedule_datetime = $nextruntime;
        $object->status = self::STATUS_NOT_STARTED;
        $object->userid = $userid;
        $object->id = self::insert_or_update($object);
        return $object;
    }

    /**
     * Set Request Remove Category.
     *
     * @param stdClass $site
     * @param int $origincatid
     * @param stdClass|null $user
     * @param int $nextruntime
     * @return stdClass
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function set_request_remove_category(
            stdClass $site, int $origincatid, stdClass $user = null, $nextruntime = null): stdClass {
        global $USER;
        $userid = is_null($user) ? $USER->id : $user->id;
        $object = new stdClass();
        $object->type = self::TYPE_REMOVE_CATEGORY;
        $object->siteurl = $site->host;
        $object->direction = self::DIRECTION_REQUEST;
        $object->origin_category_id = $origincatid;
        $object->origin_schedule_datetime = $nextruntime;
        $object->status = self::STATUS_NOT_STARTED;
        $object->userid = $userid;
        $object->id = self::insert_or_update($object);
        return $object;
    }
}
