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
$string['setting_destiny_sites_desc'] = 'Listado de sitios destino, a los que se les podrá entregar las copias de seguridad de los cursos. En la misma linea, host y token separados por punto y coma. Sitios separados por salto de línea.';
$string['setting_origin_sites'] = 'Sitios origen';
$string['setting_origin_sites_desc'] = 'Listado de sitios origen, a los que se les podrá pedir copias de seguridad de los cursos. En la misma línea, host y token separados por punto y coma. Sitios separados por salto de línea.';
$string['setting_origin_field_search_user'] = 'Campo usuario origen';
$string['setting_origin_field_search_user_desc'] = 'Campo a utilizar para la búsqueda de un usuario en el sitio de origen.';
$string['origin_restore_course'] = 'Restaurar curso en remoto';
$string['origin_course_id_require'] = "Origin Course ID es obligatorio";
$string['site_url_required'] = "Site URL es obligatorio";
$string['site_url_invalid'] = "Site URL es inválido";
$string['origin_category_id_require'] = "Origin Category ID es obligatorio";
$string['categoryid_require'] = "Category ID es obligatorio";
$string['courseid_require'] = "Course ID es obligatorio";
$string['requestid_require'] = "Request ID es obligatorio";
$string['destiny_target_is_incorrect'] = "Destiny Target es incorrecto, debe tener valores 2,3 o 4";
$string['origin_course_id_integer'] = "Origin Course ID tiene que ser entero";
$string['destiny_course_id_is_required'] = "Destiny Course ID es obligatorio";
$string['destiny_course_id_isnot_correct'] = "Para destiny_target=2 no debe tener curso de destino, se creará nuevo curso";
$string['destiny_category_id_integer'] = "Destiny Category ID tiene que ser entero";
$string['origin_enrolusers_boolean'] = "Origin Enrol Users tiene que ser boolean";
$string['destiny_remove_enrols_boolean'] = "Destiny Remove Enrols tiene que ser boolean";
$string['destiny_remove_groups_booelan'] = "Destiny Remove Groups tiene que ser boolean";
$string['origin_remove_course_boolean'] = "Origin Remove Course tiene que ser boolean";
$string['origin_schedule_datetime_integer'] = "Origin Schedule Datetime tiene que ser entero";
$string['destiny_not_remove_activities_invalid'] = "Destiny Not Remove Activities tiene que ser una cadena de cmid separados por coma";
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
$string['user_not_found'] = "Usuario no encontrado en Moodle de Origen/Destino";
$string['user_does_not_have_courses'] = "El usuario no tiene curso en el Moodle de Origen/Destino";
$string['field_not_valid'] = "El campo no es válido. Por favor, revisa la configuración del plugin";
$string['steps_buttons_next'] = 'Siguiente';
$string['steps_buttons_back'] = 'Atrás';
$string['steps_buttons_cancel'] = 'Cancelar';
$string['steps_restore_title'] = "Restaurar curso de Origen";
$string['steps_restore_title_cat'] = "Restaurar cursos de Origen en categoría";
$string['step1_restore_desc'] = "Para restaurar un curso desde otra plataforma, primero debe seleccionar el sitio donde se encuentra el curso original";
$string['step1_restore_desc_cat'] = "To restore categories from another platform, you must first select the site where the original category is located";
$string['step2_restore_desc'] = "Seleccione el curso para restaurar";
$string['step2_restore_desc_cat'] = "Seleccione la categoría para restaurar";
$string['step2_course_list'] = "Lista de cursos de origen";
$string['step2_course_id'] = "ID Curso";
$string['step2_course_name'] = "Nombre Curso";
$string['step2_course_shortname'] = "Nombre Corto Curso";
$string['step2_course_idnumber'] = "ID Number Curso";
$string['step2_course_categoryid'] = "ID Categoría";
$string['step2_course_categoryname'] = "Nombre Categoría";
$string['step3_restore_desc'] = "Seleccionar detalles del curso";
$string['step3_sections_title'] = "Secciones";
$string['step4_restore_desc'] = "Configuración del curso seleccionado";
$string['step4_config_title'] = "Configuración";
$string['step5_restore_origin_site'] = "Sitio de origen";
$string['step5_restore_selected_course'] = "Curso Seleccionado";
$string['step5_sections_title'] = "Secciones seleccionadas";
$string['step5_configuration_title'] = "Configuración seleccionada";
$string['config_destiny_merge_activities'] = "Fusionar la copia de seguridad con este curso";
$string['config_destiny_remove_enrols'] = "Eliminar roles y matrículaciones en el curso de destino";
$string['config_destiny_remove_groups'] = "Eliminar grupos y agrupamientos en el curso de destino";
$string['config_destiny_remove_activities'] = "Borrar el contenido del curso actual y después restaurar";
$string['course_details_shortname'] = "Nombre corto";
$string['course_details_course_id'] = "ID Number";
$string['course_details_category_name'] = "Nombre Categoría";
$string['course_details_category_id'] = "ID Categoría";
$string['course_details_backup_size'] = "Tamaño estimado de la copia de seguridad";
$string['course_sections_title'] = "Secciones";
$string['sections_table_id'] = "ID Sección";
$string['sections_table_number'] = "Número Sección";
$string['sections_table_name'] = "Nombre Sección";
$string['activities_table_name'] = "Nombre Actividad";
$string['activities_table_type'] = "Tipo Actividad";
$string['list_course_restoration'] = "Listado de restauraciones del curso";
$string['list_course_restoration_cat'] = "Listado de restauraciones de la categoría";
$string['list_desc_restoration'] = "Haz click aquí para restaurar un curso desde otra plataforma. A continuación se mostrará un paso a paso para realizarlo correctamente";
$string['list_desc_restoration_cat'] = "Haz click aquí para restaurar una categoría desde otra plataforma. A continuación se mostrará un paso a paso para realizarlo correctamente.";
$string['status_error'] = "Error";
$string['status_not_started'] = "Sin empezar";
$string['status_in_progress'] = "En progreso...";
$string['status_in_backup'] = "Copía Seguridad";
$string['status_incompleted'] = "Sin completar";
$string['status_download'] = "Descargando...";
$string['status_downloaded'] = "Descargada";
$string['status_restore'] = "Restaurando...";
$string['status_completed'] = "Completada";
$string['error_validate_site'] = "El sitio seleccionado es invalido";
$string['error_not_controlled'] = "El sitio no está disponible en este momento. Inténtelo más tarde";
$string['site_not_found'] = "El sitio seleccionado no se encuentra entre los disponibles";
$string['origin_restore_category'] = "Restaurar categoría remota";
$string['step2_category_id'] = "ID Categoría";
$string['step2_category_name'] = "Nombre";
$string['step2_category_idnumber'] = "ID Number";
$string['step2_category_parentname'] = "Categoría Padre";
$string['step2_category_totalcourses'] = "Número de Cursos";
$string['step2_category_totalsubcategories'] = "Número de Subcategorías";
$string['step2_category_totalcourseschild'] = "Número de Cursos Subcategorías";
$string['step2_categories_list'] = "Lista de categorías de origen";
$string['step3_restore_desc_cat'] = "Seleccione los cursos que quiere restaurar de la categoría elegida";
$string['step3_category_list'] = "Lista de cursos de la categoría de origen: ";
$string['category_details_name'] = "Nombre de la Categoría";
$string['category_details_category_id'] = "ID de la Categoría";
$string['category_details_parent_name'] = "Categoría Padre";
$string['step4_restore_desc_cat'] = "Configuración de la restauración de la categoría";
$string['step4_restore_origin_site'] = "Sitio de origen";
$string['step4_restore_selected_category'] = "Categoría seleccionada";
$string['step4_courses_title_desc'] = "Cursos seleccionados";
$string['execute_restore'] = "Ejecutar restauración";
$string['origin_category_id'] = "ID Categoría Origen";
$string['origin_category_courses'] = "Cursos a restaurar";
$string['destiny_course_id_integer'] = "ID Curso de Destino debe ser entero";
$string['destiny_category_id_require'] = "ID Categoría de Destino es obligatoria";
$string['destiny_category_id_integer'] = "ID Categoría de Destino debe ser entero";
$string['not_activities'] = "No se han encontrado actividades";
$string['not_courses'] = "No se han encontrado cursos";
$string['request_not_found'] = "No se ha encontrado la petición";
$string['deleteindestiny'] = "Borrar en destino";
$string['deleteindestiny'] = "Borrar en destino";
$string['config_destiny_merge_activities_desc'] = "Las actividades del curso de origen se fusionaran en el curso de destino";
$string['config_destiny_remove_activities_desc'] = "Todo el contenido del curso de destino se borrará y se restaurará con el curso de origen";
$string['config_destiny_remove_groups_desc'] = "Se borrarán los grupos y agrupamientos actuales del curso de destino ya existentes";
$string['config_destiny_remove_enrols_desc'] = "Se borrarán las matriculaciones y roles actuales del curso de destino ya existentes";
$string['summary'] = "Resumen";
$string['config'] = "Configuración";
$string['refresh'] = "Refrescar";
$string['index_title'] = "Datos para la integración con otros Moodle";
$string['token_user_ws'] = "Token para otras plataformas";
$string['user_ws'] = "Usuario Servicio Web";
$string['sections_table_select_all'] = "Marcar todo";
$string['restore_page'] = "Restaurar cursos o categorías remotas";
$string['restore_page_desc'] = "Seleccione si quiere restaurar una categoría o un conjunto de cursos.";
$string['remove_page'] = "Eliminación de cursos de plataforma remota";
$string['remove_page_desc'] = "Seleccione si quiere eliminar una categoría o un conjunto de cursos.";
$string['token_not_found'] = "El token no ha podido recuperarse.";
$string['click_refresh'] = "Haz clic en el botón 'Refrescar' para recalcular la configuración.";
$string['restoretnewcourse'] = "Restauración realizada en un curso nuevo";
$string['course_categories'] = "Restaurar cursos o categoría";
$string['course_categories_help'] = "Seleccione cursos si quieres restaurar un listado de cursos, seleccione categoría si quiere restaurar una categoría completa o un listado de cursos de esa categoría";
$string['origin_restore_courses_title'] = "Restaurar cursos de origen";
$string['origin_restore_courses_desc'] = "Seleccione los cursos que quiere restaurar del sitio de origen y vincule el destino correspondiente";
$string['origin_restore_courses_list'] = "Lista de cursos del sitio de origen";
$string['step2_course_destiny'] = "Curso de Destino";
$string['origin_restore_step4_desc'] = "Revisa los cursos seleccionados, su destino y la configuración antes de ejecutar la restauración remota";
$string['origin_restore_category_title'] = "Restaurar categoría de origen";
$string['origin_restore_category_desc'] = "Seleccione la categoría que quiere restaurar del sitio de origen";
$string['step4_destiny_title'] = "Categoría de destino";
$string['step4_destiny_desc'] = "Seleccione la categoría de destino de la restauración";
