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
 * Strings for component 'course transfer', language 'es'
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Restaurar cursos remotos';
$string['pluginname_header_general'] = 'General';
$string['setting_target_restore_course_max_size'] = 'Tamaño máximo del curso a restaurar (MB)';
$string['setting_target_restore_course_max_size_desc'] = 'Límite en el tamaño de la copia de seguridad (archivo MBZ) del curso origen a restaurar en MB.';
$string['setting_target_sites'] = 'Sitios destino';
$string['setting_target_sites_link'] = 'Gestión de Sitios destino';
$string['setting_target_sites_desc'] = 'Listado de sitios destino, a los que se les podrá entregar las copias de seguridad de los cursos. Realiza una prueba para comprobar que todo está configurado correctamente, tanto en destino como origen.';
$string['setting_origin_sites'] = 'Sitios origen';
$string['setting_origin_sites_link'] = 'Gestión de Sitios origen';
$string['setting_origin_sites_desc'] = 'Listado de sitios origen, a los que se les podrá pedir copias de seguridad de los cursos. Realiza una prueba para comprobar que todo está configurado correctamente, tanto en destino como origen.';
$string['setting_origin_field_search_user'] = 'Campo usuario origen';
$string['setting_origin_field_search_user_desc'] = 'Campo a utilizar para la búsqueda de un usuario en el sitio de origen.';
$string['origin_restore_course'] = 'Restaurar curso en remoto';
$string['origin_course_id_require'] = "Origin Course ID es obligatorio: --origin_course_id=12";
$string['site_url_required'] = "Site URL es obligatorio: --site_url=https://origen.dominio";
$string['site_url_invalid'] = "Site URL es inválido: --site_url=https://origen.dominio";
$string['origin_category_id_require'] = "Origin Category ID es obligatorio: --origin_category_id=12";
$string['categoryid_require'] = "Category ID es obligatorio";
$string['courseid_require'] = "Course ID es obligatorio";
$string['requestid_require'] = "Request ID es obligatorio: --requestid=3";
$string['target_target_is_incorrect'] = "Target Target es incorrecto, debe tener valores 2,3 o 4: --target_target=2";
$string['origin_course_id_integer'] = "Origin Course ID tiene que ser entero: --origin_course_id=12";
$string['target_course_id_is_required'] = "Target Course ID es obligatorio: --target_course_id=12";
$string['target_course_id_isnot_correct'] = "Para target_target=2 no debe tener curso de destino, se creará nuevo curso";
$string['target_category_id_integer'] = "Target Category ID tiene que ser entero: --target_category_id=101";
$string['origin_enrolusers_boolean'] = "Origin Enrol Users tiene que ser boolean: --origin_enrolusers=true";
$string['target_remove_enrols_boolean'] = "Target Remove Enrols tiene que ser boolean: --target_remove_enrols=false";
$string['target_remove_groups_booelan'] = "Target Remove Groups tiene que ser boolean: --target_remove_groups=false";
$string['origin_remove_course_boolean'] = "Origin Remove Course tiene que ser boolean: --origin_remove_course=false";
$string['origin_schedule_datetime_integer'] = "Origin Schedule Datetime tiene que ser entero: --origin_schedule_datetime=1679404952";
$string['target_not_remove_activities_invalid'] = "Target Not Remove Activities tiene que ser una cadena de cmid separados por coma de tipo array: --target_not_remove_activities=[3,234,234]";
$string['origin_category_id_integer'] = "Origin Category ID tiene que ser entero: -origin_category_id=12";
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
$string['target_course_id'] = "ID del curso de destino";
$string['status'] = "Estado";
$string['origin_activities'] = "Actividades de Origen";
$string['configuration'] = "Configuración";
$string['error'] = "Errors";
$string['userid'] = "ID Usurio";
$string['backupsize'] = "Tamaño (MB)";
$string['timemodified'] = "Fecha de modificación";
$string['timecreated'] = "Fecha de creación";
$string['user_not_found'] = "Usuario no encontrado en Moodle de Origen/Destino";
$string['user_does_not_have_courses'] = "El usuario no tiene cursos en el Moodle de Origen";
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
$string['step4_restore_desc'] = "Seleccione las configuraciones para la restauración";
$string['step4_config_title'] = "Configuración";
$string['step5_restore_origin_site'] = "Sitio de origen";
$string['step5_restore_selected_course'] = "Curso Seleccionado";
$string['step5_sections_title'] = "Secciones seleccionadas";
$string['step5_configuration_title'] = "Configuración seleccionada";
$string['config_target_merge_activities'] = "Fusionar la copia de seguridad con este curso";
$string['config_target_remove_enrols'] = "Eliminar roles y matrículaciones en el curso de destino";
$string['config_target_remove_groups'] = "Eliminar grupos y agrupamientos en el curso de destino";
$string['config_target_remove_activities'] = "Borrar el contenido del curso actual y después restaurar";
$string['course_details_shortname'] = "Nombre corto";
$string['course_details_course_id'] = "ID Number";
$string['course_details_category_name'] = "Nombre Categoría";
$string['course_details_category_id'] = "ID Categoría";
$string['course_details_backup_size'] = "Tamaño estimado (MB)";
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
$string['execute_remove'] = "Ejecutar borrado";
$string['origin_category_id'] = "ID Categoría Origen";
$string['origin_category_courses'] = "Cursos a restaurar";
$string['target_course_id_integer'] = "ID Curso de Destino debe ser entero";
$string['target_category_id_require'] = "ID Categoría de Destino es obligatoria";
$string['target_category_id_integer'] = "ID Categoría de Destino debe ser entero";
$string['not_activities'] = "No se han encontrado actividades";
$string['not_courses'] = "No se han encontrado cursos";
$string['request_not_found'] = "No se ha encontrado la petición";
$string['deleteintarget'] = "Borrar en destino";
$string['deleteintarget'] = "Borrar en destino";
$string['config_target_merge_activities_desc'] = "Las actividades del curso de origen se fusionaran en el curso de destino";
$string['config_target_remove_activities_desc'] = "Todo el contenido del curso de destino se borrará y se restaurará con el curso de origen";
$string['config_target_remove_groups_desc'] = "Se borrarán los grupos y agrupamientos actuales del curso de destino ya existentes";
$string['config_target_remove_enrols_desc'] = "Se borrarán las matriculaciones y roles actuales del curso de destino ya existentes";
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
$string['origin_restore_courses_desc'] = "Seleccione los cursos que quiere restaurar del sitio de origen y vincule el destino correspondiente. Puede seleccionar en destino, nuevo curso, y así se creará un nuevo curso en destino. Más adelante podrá seleccionar categoría.";
$string['origin_restore_courses_list'] = "Lista de cursos del sitio de origen";
$string['step2_course_target'] = "Curso de Destino";
$string['origin_restore_step4_desc'] = "Revisa los cursos seleccionados, su destino y la configuración antes de ejecutar la restauración remota. Recuerde seleccionar la categoría de destino, si el curso a crear es nuevo";
$string['origin_restore_category_title'] = "Restaurar categoría de origen";
$string['origin_restore_category_desc'] = "Seleccione la categoría que quiere restaurar del sitio de origen";
$string['step4_target_title'] = "Categoría de destino";
$string['step4_target_desc'] = "Seleccione la categoría de destino de la restauración";
$string['origin_restore_category_step3_desc'] = "Seleccione la categoría de destino y la configuración a aplicar en la restauración";
$string['origin_restore_category_step4_desc'] = "Revisa las configuraciones seleccionadas antes de ejectuar las restauraciones";
$string['course_categories_remove'] = "Borrar cursos o categoría";
$string['course_categories_remove_help'] = "Selecciona si quieres borrar un listado de cursos o una categoría completa";
$string['remove_course_page'] = "Borrar cursos remotos";
$string['remove_page_course_desc'] = "Selecciona los cursos del sitio remoto que quieres borrar";
$string['origin_remove_step3_desc'] = "Revisa los cursos seleccionados para borrar en el sitio de origen.";
$string['origin_remove_courses_list'] = "Cursos a borrar en el sitio de origen.";
$string['origin_remove_step3_cat_desc'] = "Revisa los datos de la categoría remota a borrar";
$string['origin_remove_category_step3'] = "Categoría a borrar";
$string['remove_category_page'] = "Borrar categoría remota";
$string['logs_page'] = "Registros ejecuciones";
$string['course_completed_sections'] = "Restauración del curso completo";
$string['restore_origin_data'] = "Configuración del curso de origen";
$string['restore_origin_cat_data'] = "Configuración de la categoría y cursos de origen";
$string['restore_origin_user_data'] = "Restaurar curso con datos de usuarios de origen";
$string['restore_origin_user_data_desc'] = "El curso se restaurará con los datos de usuarios que existen en el curso de origen";
$string['detail'] = "Detalle";
$string['type'] = "Tipo";
$string['direction'] = "Dirección";
$string['direction'] = "Dirección";
$string['restore_course'] = "Restauración de Curso";
$string['restore_category'] = "Restauración de Categoría";
$string['remove_course'] = "Borrado de curso";
$string['remove_category'] = "Borrado de categoría";
$string['request'] = "Petición";
$string['response'] = "Respuesta";
$string['view_logs'] = "Ver logs";
$string['target_site'] = "Sitio destino";
$string['id'] = "ID";
$string['host_url'] = "Host URL";
$string['host_token'] = "Host Token";
$string['test'] = "Prueba";
$string['actions'] = "Acciones";
$string['log_page'] = "Detalle del Log";
$string['log_page_general_data'] = "Datos generales";
$string['log_page_url'] = "URL";
$string['log_page_user'] = "Usuario";
$string['log_page_status'] = "Estado";
$string['log_page_exec_date'] = "Fecha ejecución";
$string['log_page_target_target'] = "Target de destino";
$string['log_page_petition_type'] = "Tipo de petición";
$string['log_page_direction'] = "Dirección";
$string['log_page_target_request'] = "Petición de Destino";
$string['log_page_request_category'] = "Petición Categoría";
$string['log_page_target_data'] = "Datos destino";
$string['log_page_course_id'] = "ID Curso";
$string['log_page_category_id'] = "ID Categoría";
$string['log_page_remove_enrols'] = "Borrar matriculaciones";
$string['log_page_remove_groups'] = "Borrar grupos";
$string['log_page_origin_course_data'] = "Datos curso origen";
$string['log_page_course_fullname'] = "Nombre del curso";
$string['log_page_course_shortname'] = "Nombre corto Curso";
$string['log_page_course_idnumber'] = "ID Number Curso";
$string['log_page_origin_category_data'] = "Datos categoría Origen";
$string['log_page_category_name'] = "Nombre categoría";
$string['log_page_category_idnumber'] = "ID Number Caregoria";
$string['log_page_category_requests'] = "Peticiones de Categoría";
$string['log_page_config'] = "Configuración";
$string['log_page_user_data'] = "Datos de Usuario";
$string['log_page_remove_course'] = "Borrar el curso";
$string['log_page_remove_category'] = "Borrar el curso";
$string['log_page_remove_exec_time'] = "Tiempo de ejecución";
$string['log_page_origin_activities'] = "Actividades de origen";
$string['log_page_origin_backup_size'] = "Tamaño del Backup (Mb)";
$string['log_page_origin_backup_size_estimated'] = "Tamaño Estimado (Mb)";
$string['log_page_origin_backup_url'] = "URL del archivo de backup";
$string['log_page_fileurl'] = "URL Archivo";
$string['log_page_error'] = "Errores";
$string['log_page_error_code'] = "Código de Error";
$string['log_page_error_msg'] = "Mensaje de Error";
$string['create_site'] = "Crear un sitio";
$string['back_config'] = "Volver a configuración";
$string['host_url_desc'] = "Añada la URL del host";
$string['host_token_desc'] = "Añada el Token del host";
$string['delete_site'] = "Borrar sitio";
$string['delete_site_question'] = "¿Estas seguro de borrar este sitio?";
$string['edit_site'] = "Editar sitio";
$string['view_error'] = "Ver error";
$string['backupsize_larger'] = "El tamaño del backup es mayor al permitido";
$string['restore_origin_remove'] = "Eliminar el curso de origen";
$string['restore_origin_remove_desc'] = "El curso de origen será eliminado una vez restaurado";
$string['restore_origin_cat_remove'] = "Eliminar la categoría de origen";
$string['restore_origin_cat_remove_desc'] = "La categoría de origen será eliminada una vez restaurada completamente";
$string['coursetransfer:origin_restore'] = "Restaurar cursos o categorías remotas";
$string['coursetransfer:origin_restore_course'] = "Restaurar curso de plataforma origen";
$string['coursetransfer:origin_remove_course'] = "Borrar curso de plataforma origen";
$string['coursetransfer:origin_remove_category'] = "Borrar categoría de plataforma origen";
$string['coursetransfer:origin_restore_course_users'] = "Restaurar curso con datos de usuarios de origen";
$string['coursetransfer:origin_view_courses'] = "Ver cursos de plataforma origen";
$string['coursetransfer:target_restore_enrol_remove'] = "Eliminar roles y matrículaciones en el curso de destino";
$string['coursetransfer:target_restore_groups_remove'] = "Eliminar grupos y agrupamientos en el curso de destino";
$string['coursetransfer:target_restore_content_remove'] = "Borrar el contenido del curso actual y después restaurar";
$string['coursetransfer:target_restore_merge'] = "Fusionar la copia de seguridad con este curso";
$string['forbidden'] = "Prohibido";
$string['you_have_not_permission'] = "Usted no tiene permisos para ver esta página";
$string['createnewcategory'] = "En nueva categoría...";
$string['origin_schedule'] = "Ejecución en diferido";
$string['origin_schedule_desc'] = "Si la tarea se ejecuta en diferido, seleccione la fecha de ejecución";
$string['origin_schedule_datetime'] = "Fecha de ejecución";
$string['in_new_course'] = "En Nuevo Curso";
$string['remove_content'] = "Borrar contenido de destino";
$string['merge_content'] = "Fusionar contenido en destino";
$string['messageprovider:restore_course_completed'] = "Restauración Curso Remoto Completada";
$string['messageprovider:restore_category_completed'] = "Restauración Categoría Remota Completada";
$string['messageprovider:remove_course_completed'] = "Borrado Curso Remoto Completado";
$string['messageprovider:remove_category_completed'] = "Borrado Categoría Remota Completado";
$string['notification_restore_course_completed'] = 'Ha finalizado con éxito la restauración del curso remoto en su destino: {$a}';
$string['notification_restore_category_completed'] = 'Ha finalizado con éxito la restauración de la categoría remota en su destino: {$a}';
$string['notification_remove_course_completed'] = 'Ha finalizado con éxito el borrado del curso remoto: {$a}';
$string['notification_remove_category_completed'] = 'Ha finalizado con éxito el borrado de la categoría remota: {$a}';
$string['view_detail'] = 'Ver detalle:';
$string['remove_config'] = 'Configuración de Borrado';
$string['site_exist'] = 'El sitio ya existe';
$string['host_token_empty'] = 'El host o el token están vacíos';
$string['courses_not_selected'] = 'No hay cursos seleccionados';
$string['request_timeout'] = 'Timeout';
$string['request_timeout_desc'] = 'Tiempo en segundos de espera petición CURL entre origen y destino';
$string['clean_adhoc_failed_task'] = 'Tarea que limpia las tareas adhoc que han fallado de este componente';
$string['remove_course_cleanup'] = 'Borrado definitivo curso';
$string['remove_course_cleanup_desc'] = 'Si está activo, se borrará definitivamente el curso sin tener en cuenta la papelera de reciclaje';
$string['remove_cat_cleanup'] = 'Borrado definitivo categoría';
$string['remove_cat_cleanup_desc'] = 'Si está activo, se borrará definitivamente la categoría sin tener en cuenta la papelera de reciclaje';
$string['in_target_adding_not_remove_enrols'] = 'No se pueden borrar matriculaciones en destino, cuando el target es una fusión de contenido (--target_target=4)';
$string['in_target_adding_not_remove_groups'] = 'No se pueden borrar grupos en destino, cuando el target es una fusión de contenido (--target_target=4)';
$string['search_with_name'] = 'Introduzca el nombre del curso a buscar...';
$string['in_exists_course'] = 'En un curso que ya existe';
$string['select_category_target'] = 'Seleccione la categoría donde se restaurará el curso nuevo';
$string['select_destination'] = 'Seleccione el destino';
$string['search_course_destination'] = 'Busca el curso de destino';
$string['not_course_found'] = 'No se ha encontrado ningún curso...';
$string['coursetransfer:view_logs'] = 'Ver registros';
