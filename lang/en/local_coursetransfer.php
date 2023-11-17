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
 * Strings for component 'assign', language 'en'
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Course Transfer';
$string['pluginname_header_general'] = 'General';
$string['setting_destiny_restore_course_max_size'] = 'Maximum course size to be restored (MB)';
$string['setting_destiny_restore_course_max_size_desc'] = 'Limit of the size of the backup copy (MBZ file)of the origin course to be restored in MB.';
$string['setting_destiny_sites'] = 'Destination sites';
$string['setting_destiny_sites_link'] = 'Destination sites Management';
$string['setting_destiny_sites_desc'] = 'List of destination sites, to which the backup copies of the courses can be delivered. Perform a test to verify that everything is configured correctly, both at destination and origin.';
$string['setting_origin_sites'] = 'Origin sites';
$string['setting_origin_sites_link'] = 'Origin sites Management';
$string['setting_origin_sites_desc'] = 'List of origen sites, from which backup copies of the courses may be requested. Perform a test to verify that everything is configured correctly, both at destination and origin.';
$string['setting_origin_field_search_user'] = 'Origin user field';
$string['setting_origin_field_search_user_desc'] = 'Field to be used to search for a user in the origin site.';
$string['origin_restore_course'] = 'Restore Remote Course';
$string['origin_course_id_require'] = "Origin Course ID must be specified: --origin_course_id=12";
$string['origin_category_id_require'] = "Origin Category ID must be specified: --origin_category_id=12";
$string['site_url_required'] = "Site URL is required: --site_url=https://origen.dominio";
$string['site_url_invalid'] = "Site URL is not valid: --site_url=https://origen.dominio";
$string['categoryid_require'] = "Category ID must be specified";
$string['courseid_require'] = "Course ID must be specified";
$string['requestid_require'] = "Request ID must be specified: --requestid=3";
$string['destiny_target_is_incorrect'] = "Destination Target is incorrect, must have values 2,3 or 4: --destiny_target=2";
$string['origin_course_id_integer'] = "Origin Course ID must be an integer";
$string['destiny_course_id_is_required'] = "Destination Course ID is required";
$string['destiny_course_id_isnot_correct'] = "For destiny_target=2 there must be no target course, a new course will be created";
$string['destiny_category_id_integer'] = "Destination Category ID must be an integer: --destiny_category_id=101";
$string['origin_enrolusers_boolean'] = "Origin Enrol Users must be a boolean: --origin_enrolusers=true";
$string['destiny_remove_enrols_boolean'] = "Destination Remove Enrols must be a boolean: --destiny_remove_enrols=false";
$string['destiny_remove_groups_booelan'] = "Destination Remove Groups must be a boolean: --destiny_remove_groups=false";
$string['origin_remove_course_boolean'] = "Origin Remove Course must be a boolean: --origin_remove_course=false";
$string['origin_schedule_datetime_integer'] = "Origin Schedule Datetime must be an integer: --origin_schedule_datetime=1679404952";
$string['destiny_not_remove_activities_invalid'] = "Destination Not Remove Activities must be a string separated by commas array type: --destiny_not_remove_activities=[3,234,234]";
$string['origin_category_id_integer'] = "Origin Category ID must be an integer: -origin_category_id=12";
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
$string['destiny_course_id'] = "Destination Course ID";
$string['status'] = "Status";
$string['origin_activities'] = "Origin Activities";
$string['configuration'] = "Config";
$string['error'] = "Errors";
$string['userid'] = "User ID";
$string['backupsize'] = "Size (MB)";
$string['timemodified'] = "Time Modified";
$string['timecreated'] = "Time Created";
$string['user_not_found'] = "User not founded in origin/destination Moodle";
$string['user_does_not_have_courses'] = "User does not have courses in origin Moodle";
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
$string['step4_restore_desc'] = "Select settings for restore";
$string['step4_config_title'] = "Configuration";
$string['step5_restore_origin_site'] = "Origin Site";
$string['step5_restore_selected_course'] = "Selected Course";
$string['step5_sections_title'] = "Sections selected";
$string['step5_configuration_title'] = "Configuration selected";
$string['config_destiny_merge_activities'] = "Merge the backup course into this course";
$string['config_destiny_remove_enrols'] = "Delete current roles and enrolments";
$string['config_destiny_remove_groups'] = "Delete current groups and groupings";
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
$string['status_in_progress'] = "In progress...";
$string['status_in_backup'] = "In backup...";
$string['status_incompleted'] = "Incompleted";
$string['status_download'] = "Download...";
$string['status_downloaded'] = "Downloaded";
$string['status_restore'] = "Restore...";
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
$string['step2_category_totalsubcategories'] = "Total subcategories";
$string['step2_category_totalcourseschild'] = "Total courses in subcategories";
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
$string['execute_remove'] = "Execute remove";
$string['origin_category_id'] = "Origin Category ID";
$string['origin_category_courses'] = "Courses to restore";
$string['destiny_course_id_integer'] = "Destination Course ID is integer";
$string['destiny_category_id_require'] = "Destination Category ID is required";
$string['destiny_category_id_integer'] = "Destination Category ID is integer";
$string['not_activities'] = "No Activities found";
$string['not_courses'] = "No courses found";
$string['request_not_found'] = "Request not found";
$string['deleteindestiny'] = "Remove in destination";
$string['config_destiny_merge_activities_desc'] = "The activities of the origen course will be merged into the destination course";
$string['config_destiny_remove_activities_desc'] = "All content in the destination course will be deleted and restored with the origen course";
$string['config_destiny_remove_groups_desc'] = "Existing destination course groups and groupings will be deleted";
$string['config_destiny_remove_enrols_desc'] = "Existing target course roles and enrollments will be deleted";
$string['summary'] = "Summary";
$string['config'] = "Configuration";
$string['refresh'] = "Refresh";
$string['index_title'] = "Data for integration with other Moodle";
$string['token_user_ws'] = "Token for other platforms";
$string['user_ws'] = "Service Web User";
$string['sections_table_select_all'] = "Select all";
$string['restore_page'] = "Restore remote courses or categories";
$string['restore_page_desc'] = "Select whether you want to restore a category or a set of courses.";
$string['remove_page'] = "Deletion of remote platform courses";
$string['remove_page_desc'] = "Select if you want to delete a category or a set of courses.";
$string['token_not_found'] = "The token could not be retrieved.";
$string['click_refresh'] = "Click the 'Refresh' button to recalculate the settings.";
$string['restoretnewcourse'] = "Restoration performed in a new course";
$string['course_categories'] = "Restore courses or category";
$string['course_categories_help'] = "Select courses if you want to restore a list of courses, select category if you want to restore an entire category or a list of courses in that category";
$string['origin_restore_courses_title'] = "Restore Origen Courses";
$string['origin_restore_courses_desc'] = 'Select the courses you want to restore from the origen site and link the corresponding destination. You can select in destination, new course, and a new course will be created in destination. Later you can select category.';
$string['origin_restore_courses_list'] = "Origen Site Course List";
$string['step2_course_destiny'] = "Destination Course";
$string['origin_restore_step4_desc'] = "Review the selected courses, their destination, and settings before running the remote restore. Remember to select the destination category, if the course to be created is new";
$string['origin_restore_category_title'] = "Restore Origen Category";
$string['origin_restore_category_desc'] = "Select the category you want to restore from the origen site";
$string['step4_destiny_title'] = "Destination category";
$string['step4_destiny_desc'] = "Select the destination category of the restore";
$string['origin_restore_category_step3_desc'] = "Select the destination category and settings to apply to the restore";
$string['origin_restore_category_step4_desc'] = "Review selected settings before running restores";
$string['course_categories_remove'] = "Delete courses or category";
$string['course_categories_remove_help'] = "Select if you want to delete a list of courses or an entire category";
$string['remove_course_page'] = "Delete remote courses";
$string['remove_page_course_desc'] = "Select the remote site courses you want to delete";
$string['origin_remove_step3_desc'] = "Review the courses selected to delete on the site of origin.";
$string['origin_remove_courses_list'] = "Courses to be deleted at the site of origin.";
$string['origin_remove_step3_cat_desc'] = "Review the data of the remote category to delete";
$string['origin_remove_category_step3'] = "Category to delete";
$string['remove_category_page'] = "Delete remote category";
$string['logs_page'] = "Logs executions";
$string['course_completed_sections'] = "Restoration of the complete course";
$string['restore_origin_data'] = "Origin course configuration";
$string['restore_origin_cat_data'] = "Origin category and origin courses configuration";
$string['restore_origin_user_data'] = "Restore course with origin user data";
$string['restore_origin_user_data_desc'] = "The course will be restored with the user data that exists in the origin course.";
$string['detail'] = "Detail";
$string['type'] = "Type";
$string['direction'] = "Direction";
$string['restore_course'] = "Restore Course";
$string['restore_category'] = "Restore Category";
$string['remove_course'] = "Remove Course";
$string['remove_category'] = "Remove Category";
$string['request'] = "Request";
$string['response'] = "Response";
$string['view_logs'] = "View logs";
$string['destiny_site'] = "Destination Site";
$string['id'] = "ID";
$string['host_url'] = "Host URL";
$string['host_token'] = "Host Token";
$string['test'] = "Test";
$string['actions'] = "Actions";
$string['log_page'] = "Log Detail";
$string['log_page_general_data'] = "General data";
$string['log_page_url'] = "URL";
$string['log_page_user'] = "User";
$string['log_page_status'] = "Status";
$string['log_page_exec_date'] = "Execution date";
$string['log_page_destiny_target'] = "Destini target";
$string['log_page_petition_type'] = "Request type";
$string['log_page_direction'] = "Direction";
$string['log_page_destiny_request'] = "Destiny request";
$string['log_page_request_category'] = "Request category";
$string['log_page_destiny_data'] = "Destiny data";
$string['log_page_course_id'] = "Course ID";
$string['log_page_category_id'] = "Category ID";
$string['log_page_remove_enrols'] = "Remove enrols";
$string['log_page_remove_groups'] = "Remove groups";
$string['log_page_origin_course_data'] = "Origin course data";
$string['log_page_course_fullname'] = "Course name";
$string['log_page_course_shortname'] = "Course shortname";
$string['log_page_course_idnumber'] = "Course ID Number";
$string['log_page_origin_category_data'] = "Origin category data";
$string['log_page_category_name'] = "Category name";
$string['log_page_category_idnumber'] = "Category ID Number";
$string['log_page_category_requests'] = "Category requests";
$string['log_page_config'] = "Configuration";
$string['log_page_user_data'] = "User data";
$string['log_page_remove_course'] = "Remove course";
$string['log_page_remove_category'] = "Remove category";
$string['log_page_remove_exec_time'] = "Execution time";
$string['log_page_origin_activities'] = "Origin activities";
$string['log_page_origin_backup_size'] = "Backup Size (Mb)";
$string['log_page_origin_backup_url'] = "Backup File URL";
$string['log_page_fileurl'] = "File URL";
$string['log_page_error'] = "Errors";
$string['log_page_error_code'] = "Error code";
$string['log_page_error_msg'] = "Error message";
$string['create_site'] = "Create Site";
$string['back_config'] = "Back to configuration";
$string['host_url_desc'] = "Add Host URL";
$string['host_token_desc'] = "Add Host Token";
$string['delete_site'] = "Delete site";
$string['delete_site_question'] = "Are you sure to delete this site?";
$string['edit_site'] = "Edit site";
$string['view_error'] = "View error";
$string['backupsize_larger'] = "The backup size is greater than allowed";
$string['restore_origin_remove'] = "Delete origin course";
$string['restore_origin_remove_desc'] = "The origin course will be deleted once restored";
$string['restore_origin_cat_remove'] = "Delete origin category";
$string['restore_origin_cat_remove_desc'] = "The origin category will be deleted once fully restored";
$string['coursetransfer:origin_restore'] = "Restore remote courses or categories";
$string['coursetransfer:origin_restore_course'] = "Restore origen platform course";
$string['coursetransfer:origin_remove_course'] = "Remove origin platform course";
$string['coursetransfer:origin_restore_course_users'] = "Restore course with origin user data";
$string['coursetransfer:origin_view_courses'] = "View origin platform courses";
$string['coursetransfer:destiny_restore_enrol_remove'] = "Delete current roles and enrolments";
$string['coursetransfer:destiny_restore_groups_remove'] = "Delete current groups and groupings";
$string['coursetransfer:destiny_restore_content_remove'] = "Delete the contents of this course and then restore";
$string['coursetransfer:destiny_restore_merge'] = "Merge the backup course into this course";
$string['forbidden'] = "Forbidden";
$string['you_have_not_permission'] = "You do not have permissions to view this page";
$string['createnewcategory'] = "In new category...";
$string['origin_schedule'] = "Deferred execution";
$string['origin_schedule_desc'] = "If the task is executed on a delayed basis, select the execution date";
$string['origin_schedule_datetime'] = "Execution date";
$string['in_new_course'] = "In new course";
$string['remove_content'] = "Remove content in destination";
$string['merge_content'] = "Merge content in destination";
