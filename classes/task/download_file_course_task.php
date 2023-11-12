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

/**
 * This file defines an adhoc task to create a backup of the curse.
 *
 * @package    mod_forum
 * @copyright  2023 3iPunt <https://www.tresipunt.com/>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_coursetransfer\task;

use context_course;
use dml_exception;
use local_coursetransfer\coursetransfer;
use local_coursetransfer\coursetransfer_request;
use moodle_exception;

/**
 * Create Backup Course Task
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
        $request = $this->get_custom_data()->request;

        try {

            $filecontent = file_get_contents($fileurle);

            $this->log('Backup File Dowload Success!');

            $fs = get_file_storage();

            $context = context_course::instance($request->destiny_course_id);
            $filename = 'local_coursetransfer_' . $request->origin_course_id . '_' . time() . '.mbz';

            $fileinfo = array(
                    'contextid' => $context->id,
                    'component' => 'backup',
                    'filearea' => 'course',
                    'itemid' => 0,
                    'filepath' => '/',
                    'filename' => $filename);

            $file = $fs->create_file_from_string($fileinfo, $filecontent);

            $this->log('Backup File Dowload in Moodle Success!');

            $request->status = coursetransfer_request::STATUS_DOWNLOADED;
            coursetransfer_request::insert_or_update($request, $request->id);

            coursetransfer::create_task_restore_course($request, $file);

            $this->log('Restore in Moodle Success!');
            $request->status = coursetransfer_request::STATUS_COMPLETED;
            coursetransfer_request::insert_or_update($request, $request->id);

            if (!is_null($request->request_category_id)) {
                coursetransfer_request::update_status_request_cat($request->request_category_id);
                $this->log('Update Status Category Request');
            }

            if ($request->origin_remove_course) {
                $this->log('Origin Course Removing...');
                try {
                    $site = coursetransfer::get_site_by_url($request->siteurl);
                    $resrem = coursetransfer::remove_course($site, $request->origin_course_id);
                    if (isset($resrem['data']['requestid'])) {
                        $requesremtid = $resrem['data']['requestid'];
                        $requestrem = coursetransfer_request::get($requesremtid);
                        if (!$resrem['success']) {
                            $errors = $resrem['errors'];
                            $requestrem->status = coursetransfer_request::STATUS_ERROR;
                            $requestrem->error_code = $errors[0]->code;
                            $requestrem->error_message = $errors[0]->msg;
                            coursetransfer_request::insert_or_update($requestrem, $requesremtid);
                            $this->log('Origin Course Removed not working. Error: ' . json_encode($errors));
                        } else {
                            $requestrem->status = coursetransfer_request::STATUS_COMPLETED;
                            coursetransfer_request::insert_or_update($requestrem, $requesremtid);
                            $this->log('Origin Course Removed!');
                        }
                    } else {
                        $this->log('Origin Course Removed not working. ERROR NOT CONTROLLED');
                    }
                } catch (moodle_exception $e) {
                    $this->log('Origin Course Removed not working. Error: ' . $e->getMessage());
                }
            }

        } catch (\Exception $e) {
            $this->log($e->getMessage());
            $request->status = coursetransfer_request::STATUS_ERROR;
            $request->error_code = '200200';
            $request->error_message = $e->getMessage();
            coursetransfer_request::insert_or_update($request, $request->id);
        }

        $this->log_finish("Download File Backup Course Remote and Restore Finishing...");
    }

}
