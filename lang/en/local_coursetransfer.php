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
 * Strings for component 'assign', language 'en'
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Course Transfer';
$string['pluginname_header_general'] = 'General';
$string['setting_destiny_restore_course_max_size'] = 'Maximum course size to be restored';
$string['setting_destiny_restore_course_max_size_desc'] = 'Limit of the size of the backup copy (MBZ file)of the origin course to be restored in MB.';
$string['setting_destiny_sites'] = 'Destiny sites';
$string['setting_destiny_sites_desc'] = 'List of destination sites, to which the backup copies of the courses can be delivered. On the same line, host and token separated by semicolons. Sites separated by line break.';
$string['setting_origin_sites'] = 'Origin sites';
$string['setting_origin_sites_desc'] = 'List of source sites, from which backup copies of the courses may be requested. On the same line, host and token separated by semicolons. Sites separated by line break.';
$string['setting_origin_field_search_user'] = 'Origin user field';
$string['setting_origin_field_search_user_desc'] = 'Field to be used to search for a user in the origin site.';
$string['origin_restore_course'] = 'Restore Remote Course';
$string['origin_course_id_require'] = "Origin Course ID must be specified";
$string['origin_category_id_require'] = "Origin Category ID must be specified";
$string['site_url_required'] = "Site URL is required";
$string['site_url_invalid'] = "Site URL is not valid";
$string['categoryid_require'] = "Category ID must be specified";
$string['courseid_require'] = "Course ID must be specified";
$string['requestid_require'] = "Request ID must be specified";
$string['origin_course_id_integer'] = "Origin Course ID must be an integer";
$string['destiny_course_integer'] = "Destiny Course ID must be an integer";
$string['destiny_category_id_integer'] = "Destiny Category ID must be an integer";
$string['origin_enrolusers_boolean'] = "Enrol Users must be a boolean";
$string['destiny_remove_activities_boolean'] = "Remove Activities must be a boolean";
$string['destiny_merge_activities_boolean'] = "Merge Activities must be a boolean";
$string['destiny_remove_enrols_boolean'] = "Remove Enrols must be a boolean";
$string['destiny_remove_groups_booelan'] = "Remove Groups must be a boolean";
$string['origin_remove_course_boolean'] = "Remove Course must be a boolean";
$string['origin_schedule_datetime_integer'] = "Schedule Datetime must be an integer";
$string['destiny_not_remove_activities_invalid'] = "Remove Activities must be a string separated by commas";
$string['origin_category_id_integer'] = "Origin Category ID must be an integer";
$string['categoryid_integer'] = "Category ID must be an integer";
$string['courseid_integer'] = "Course ID must be an integer";
$string['requestid_integer'] = "Request ID must be an integer";
$string['status_integer'] = "Status must be an integer";
$string['from_integer'] = "from must be an integer";
$string['to_integer'] = "to must be an integer";
$string['userid_integer'] = "User ID must be an integer";
$string['origin_site'] = "Select origin site";
$string['origin_site_help'] = "The site of origin, is where the course you want to restore is located";
$string['request_id'] = "Request ID";
$string['siteurl'] = "Origin Site";
$string['origin_course_id'] = "Origin Course ID";
$string['status'] = "Status";
$string['origin_activities'] = "Origin Activities";
$string['configuration'] = "Config";
$string['error'] = "Errors";
$string['userid'] = "User ID";
$string['backupsize'] = "Size (MB)";
$string['timemodified'] = "Time Modified";
$string['timecreated'] = "Time Created";
$string['user_not_found'] = "User not founded in origin/destiny Moodle";
$string['user_does_not_have_courses'] = "User does not have courses in origin/destiny Moodle";
$string['field_not_valid'] = "Field is not valid. Please, check your plugin settings page";
$string['steps_buttons_next'] = 'Next';
$string['steps_buttons_back'] = 'Back';
$string['steps_buttons_cancel'] = 'Cancel';
$string['steps_restore_title'] = "Restore Remote Course";
$string['steps_restore_title_cat'] = "Restore Remote Courses in Category";
$string['step1_restore_desc'] = "To restore a course from another platform, you must first select the site where the original course is located";
$string['step1_restore_desc_cat'] = "To restore courses from another platform, you must first select the site where the original courses are located";
$string['step2_restore_desc'] = "Select the course to restore";
$string['step2_restore_desc_cat'] = "Select the category to restore";
$string['step2_course_list'] = "Origin course list";
$string['step2_course_id'] = "Course ID";
$string['step2_course_name'] = "Course Name";
$string['step2_course_shortname'] = "Shortname";
$string['step2_course_idnumber'] = "Course ID Number";
$string['step2_course_categoryid'] = "Category ID";
$string['step2_course_categoryname'] = "Category Name";
$string['step3_restore_desc'] = "Selected course details";
$string['step3_sections_title'] = "Sections";
$string['step4_restore_desc'] = "Selected course configuration";
$string['step4_config_title'] = "Configuration";
$string['step5_restore_origin_site'] = "Origin Site";
$string['step5_restore_selected_course'] = "Selected Course";
$string['step5_sections_title'] = "Sections selected";
$string['step5_configuration_title'] = "Configuration selected";
$string['config_destiny_merge_activities'] = "Merge the backup course into this course";
$string['config_destiny_remove_enrols'] = "Delete enrollments in destiny course";
$string['config_destiny_remove_groups'] = "Delete groups in destiny course";
$string['config_destiny_remove_activities'] = "Delete the contents of this course and then restore";
$string['course_details_shortname'] = "Short Name";
$string['course_details_course_id'] = "Course ID Number";
$string['course_details_category_name'] = "Category Name";
$string['course_details_category_id'] = "Category ID";
$string['course_details_backup_size'] = "Backup Estimated Size";
$string['course_sections_title'] = "Sections";
$string['sections_table_id'] = "Section ID";
$string['sections_table_number'] = "Section Number";
$string['sections_table_name'] = "Section Name";
$string['activities_table_name'] = "Activity Name";
$string['activities_table_type'] = "Activity Type";
$string['list_course_restoration'] = "List of course restorations";
$string['list_course_restoration_cat'] = "List of category restoration";
$string['list_desc_restoration'] = "Click here to restore a course from another platform. <br> Next it will show you what to do in each step";
$string['list_desc_restoration_cat'] = "Click here to restore a category from another platform. Below is a step by step to do it correctly.";
$string['status_error'] = "Error";
$string['status_not_started'] = "Not started";
$string['status_in_progress'] = "In progress";
$string['status_in_backup'] = "In backup";
$string['status_incompleted'] = "Incompleted";
$string['status_download'] = "Download";
$string['status_completed'] = "Completed";
$string['error_validate_site'] = "The selected site is invalid";
$string['error_not_controlled'] = "The site is not available at this time. try again later";
$string['site_not_found'] = "The selected site is not among those available";
$string['origin_restore_category'] = "Origin Restore Category";
$string['step2_category_id'] = "ID";
$string['step2_category_name'] = "Name";
$string['step2_category_idnumber'] = "ID Number";
$string['step2_category_parentname'] = "Parent Category";
$string['step2_category_totalcourses'] = "Total courses";
$string['step2_categories_list'] = "Origin categories list";
$string['step3_restore_desc_cat'] = "Select the courses you want to restore from the chosen category";
$string['step3_category_list'] = "List of courses from the origin category:";
$string['category_details_name'] = "Category Name";
$string['category_details_category_id'] = "Category ID";
$string['category_details_parent_name'] = "Category Parent";
$string['step4_restore_desc_cat'] = "Configuration of the restoration of the category";
$string['step4_restore_origin_site'] = "Origen site";
$string['step4_restore_selected_category'] = "Category selected";
$string['step4_courses_title_desc'] = "Courses selected";
$string['execute_restore'] = "Execute restore";
$string['origin_category_id'] = "Origin Category ID";
$string['origin_category_courses'] = "Courses to restore";
$string['destiny_course_id_integer'] = "Destiny Course ID is integer";
$string['destiny_category_id_require'] = "Destiny Category ID is required";
$string['destiny_category_id_integer'] = "Destiny Category ID is integer";
$string['not_activities'] = "No Activities found";
$string['not_courses'] = "No courses found";
$string['request_not_found'] = "Request not found";
$string['deleteindestiny'] = "Remove in destiny";
$string['config_destiny_merge_activities_desc'] = "The activities of the source course will be merged into the destination course";
$string['config_destiny_remove_activities_desc'] = "All content in the destination course will be deleted and restored with the source course";
$string['config_destiny_remove_groups_desc'] = "Existing destination course groups will be deleted";
$string['config_destiny_remove_enrols_desc'] = "Existing target course enrollments will be deleted";

