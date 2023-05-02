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
 * Strings for component 'assign', language 'es'
 *
 * @package    local_coursetransfer
 * @copyright  2023 3iPunt {@link https://tresipunt.com/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Restaurar cursos remotos';
$string['pluginname_header_general'] = 'General';
$string['setting_destiny_restore_course_max_size'] = 'Tamaño máximo del curso a restaurar';
$string['setting_destiny_restore_course_max_size_desc'] = 'Límite en el tamaño de la copia de seguridad (archivo MBZ) del curso origen a restaurar en MB.';
$string['setting_destiny_sites'] = 'Sitios destino';
$string['setting_destiny_sites_desc'] = 'Listado de sitios destino, a los que se les podrá entregar las copias de seguridad de los cursos. Separados por salto de línea.';
$string['setting_origin_sites'] = 'Sitios origen';
$string['setting_origin_sites_desc'] = 'Listado de sitios origen, a los que se les podrá pedir copias de seguridad de los cursos. Separados por salto de línea.';
$string['setting_origin_field_search_user'] = 'Campo usuario origen';
$string['setting_origin_field_search_user_desc'] = 'Campo a utilizar para la búsqueda de un usuario en el sitio de origen.';
$string['origin_restore_course'] = 'Restaurar curso en remoto';
$string['origin_course_id_require'] = "Origin Course ID es obligatorio";
$string['origin_category_id_require'] = "Origin Category ID es obligatorio";
$string['categoryid_require'] = "Category ID es obligatorio";
$string['courseid_require'] = "Course ID es obligatorio";
$string['requestid_require'] = "Request ID es obligatorio";
$string['origin_course_id_integer'] = "Origin Course ID tiene que ser entero";
$string['destiny_course_integer'] = "Destiny Course ID tiene que ser entero";
$string['destiny_category_id_integer'] = "Destiny Category ID tiene que ser entero";
$string['origin_enrolusers_boolean'] = "Enrol Users tiene que ser boolean";
$string['destiny_remove_activities_boolean'] = "Remove Activities tiene que ser boolean";
$string['destiny_merge_activities_boolean'] = "Merge Activities tiene que ser boolean";
$string['destiny_remove_enrols_boolean'] = "Remove Enrols tiene que ser boolean";
$string['destiny_remove_groups_booelan'] = "Remove Groups tiene que ser boolean";
$string['origin_remove_course_boolean'] = "Remove Course tiene que ser boolean";
$string['origin_schedule_datetime_integer'] = "Schedule Datetime tiene que ser entero";
$string['destiny_not_remove_activities_string'] = "Remove Activities tiene que ser un string";
$string['origin_category_id_integer'] = "Origin Category ID tiene que ser entero";
$string['categoryid_integer'] = "Category ID tiene que ser entero";
$string['courseid_integer'] = "Course ID tiene que ser entero";
$string['requestid_integer'] = "Request ID tiene que ser entero";
$string['status_integer'] = "Status tiene que ser entero";
$string['from_integer'] = "from tiene que ser un timestamp (entero)";
$string['to_integer'] = "to tiene que ser un timestamp (entero)";
$string['userid_integer'] = "User ID tiene que ser entero";
$string['origin_site'] = "Seleccione el sitio de origen";
$string['origin_site_help'] = "El sitio de origen, es donde se encuentra el curso que se quiere restaurar";
$string['request_id'] = "ID Petición";
$string['siteurl'] = "Sitio Origen";
$string['origin_course_id'] = "ID del curso de origen";
$string['status'] = "Estado";
$string['origin_activities'] = "Actividades de Origen";
$string['configuration'] = "Configuración";
$string['error'] = "Errors";
$string['userid'] = "ID Usurio";
$string['backupsize'] = "Tamaño (MB)";
$string['timemodified'] = "Fecha de modificación";
$string['timecreated'] = "Fecha de creación";
$string['user_not_found'] = "Usuario no encontrado en Moodle de Origen";
$string['user_does_not_have_courses'] = "El usuario no tiene curso en el Moodle de Origen";
$string['field_not_valid'] = "El campo no es válido. Por favor, revisa la configuración del plugin";
$string['steps_buttons_next'] = 'Siguiente';
$string['steps_buttons_back'] = 'Atrás';
$string['steps_buttons_cancel'] = 'Cancelar';
$string['steps_restore_title'] = "Restaurar curso de Origen";
$string['step1_restore_desc'] = "To restore a course from another platform, you must first select the site where the original course is located";
$string['step2_restore_desc'] = "Select the course to restore";
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
$string['config_destiny_merge_activities'] = "Merge with destiny course";
$string['config_destiny_remove_enrols'] = "Delete enrollments in destiny course";
$string['config_destiny_remove_groups'] = "Delete groups in destiny course";
$string['config_destiny_remove_activities'] = "Delete activities in destiny course";
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
$string['list_course_restoration'] = "Listado de restauraciones del curso";
$string['list_desc_restoration'] = "Haz click aquí para restaurar un curso desde otra plataforma. A continuación se mostrará un paso a paso para realizarlo correctamente";
$string['status_error'] = "Error";
$string['status_not_started'] = "Sin empezar";
$string['status_in_progress'] = "En progreso";
$string['status_incompleted'] = "Sin completar";
$string['status_download'] = "Descargada";
$string['status_completed'] = "Completada";
$string['error_validate_site'] = "El sitio seleccionado es invalido";
$string['error_not_controlled'] = "El sitio no está disponible en este momento. Inténtelo más tarde";
$string['site_not_found'] = "El sitio seleccionado no se encuentra entre los disponibles";
