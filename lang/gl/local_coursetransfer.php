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
 * Strings for component 'coursetransfer', language 'gl'
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = "Restaurar cursos remotos";
$string['pluginname_header_general'] = "Xeral";
$string['setting_target_restore_course_max_size'] = "Tamaño máximo do curso para restaurar (MB)";
$string['setting_target_restore_course_max_size_desc'] = "Limite o tamaño da copia de seguridade (ficheiro MBZ) do curso de orixe que se restaurará en MB.";
$string['setting_target_sites'] = "Sitios de destino";
$string['setting_target_sites_link'] = "Xestión do sitio de destino";
$string['setting_target_sites_desc'] = "Lista de sitios de destino, aos que se poden entregar copias de seguridade dos cursos. Realiza unha proba para comprobar que todo está configurado correctamente, tanto no destino como na orixe.";
$string['setting_origin_sites'] = "Sitios de orixe";
$string['setting_origin_sites_link'] = "Xestión do sitio de orixe";
$string['setting_origin_sites_desc'] = "Lista de sitios de orixe, dos que se poden solicitar copias de seguridade dos cursos. Realiza unha proba para comprobar que todo está configurado correctamente, tanto no destino como na orixe.";
$string['setting_origin_field_search_user'] = "Campo de usuario de orixe";
$string['setting_origin_field_search_user_desc'] = "Campo que se utilizará para buscar un usuario no sitio de orixe.";
$string['origin_restore_course'] = "Restaurar curso remoto";
$string['origin_course_id_require'] = "O ID do curso de orixe é necesario: --origin_course_id=12";
$string['site_url_required'] = "O URL do sitio é necesario: --site_url=https://origin.domain";
$string['site_url_invalid'] = "O URL do sitio non é válido: --site_url=https://origin.domain";
$string['origin_category_id_require'] = "Requírese o ID da categoría de orixe: --origin_category_id=12";
$string['categoryid_require'] = "Requírese o ID de categoría";
$string['courseid_require'] = "O ID do curso é necesario";
$string['requestid_require'] = "O ID de solicitude é necesario: --requestid=3";
$string['target_target_is_incorrect'] = "Target Target é incorrecto, debería ter valores 2,3 ou 4: --target_target=2";
$string['origin_course_id_integer'] = "O ID do curso de orixe debe ser enteiro: --origin_course_id=12";
$string['target_course_id_is_required'] = "O ID do curso de Target é necesario: --target_course_id=12";
$string['target_course_id_isnot_correct'] = "Para target_target=2 non debes ter un curso de destino, crearase un novo curso";
$string['target_category_id_integer'] = "O ID da categoría Target debe ser enteiro: --target_category_id=101";
$string['origin_enrolusers_boolean'] = "Os usuarios de rexistro de orixe deben ser booleanos: --origin_enrolusers=true";
$string['target_remove_enrols_boolean'] = "Os rexistros de eliminación de Target deben ser booleanos: --target_remove_enrols=false";
$string['target_remove_groups_booelan'] = "Os grupos de eliminación de Target deben ser booleanos: --target_remove_groups=false";
$string['origin_remove_course_boolean'] = "O curso de eliminación de orixe ten que ser booleano: --origin_remove_course=false";
$string['origin_schedule_datetime_integer'] = "A data hora da programación de orixe debe ser enteira: --origin_schedule_datetime=1679404952";
$string['target_not_remove_activities_invalid'] = "As actividades de Target Not Remove deben ser unha cadea cmid separada por comas de tipo matriz: --target_not_remove_activities=[3,234,234]";
$string['origin_category_id_integer'] = "O ID da categoría de orixe debe ser enteiro: -origin_category_id=12";
$string['categoryid_integer'] = "O ID de categoría debe ser enteiro";
$string['courseid_integer'] = "O ID do curso debe ser enteiro";
$string['requestid_integer'] = "O ID de solicitude debe ser enteiro";
$string['status_integer'] = "O estado debe ser enteiro";
$string['from_integer'] = "from ten que ser unha marca de tempo (enteiro)";
$string['to_integer'] = "to ten que ser unha marca de tempo (enteiro)";
$string['userid_integer'] = "O ID de usuario debe ser enteiro";
$string['origin_site'] = "Seleccione o sitio de orixe";
$string['origin_site_help'] = "O sitio de orixe é onde se atopa o curso que queres restaurar.";
$string['request_id'] = "Solicitar ID";
$string['siteurl'] = "Sitio de orixe";
$string['origin_course_id'] = "ID do curso de orixe";
$string['target_course_id'] = "ID do curso obxectivo";
$string['status'] = "Estado";
$string['origin_activities'] = "Actividades de orixe";
$string['configuration'] = "Configuración";
$string['error'] = "Erros";
$string['userid'] = "ID do usuario";
$string['backupsize'] = "Tamaño (MB)";
$string['timemodified'] = "Data de modificación";
$string['timecreated'] = "Data de creación";
$string['user_not_found'] = "Non se atopou o usuario no Moodle de orixe/destino";
$string['user_does_not_have_courses'] = "O usuario non ten cursos no Moodle Fonte";
$string['field_not_valid'] = "O campo non é válido. Comprobe a configuración do complemento";
$string['steps_buttons_next'] = "Seguindo";
$string['steps_buttons_back'] = "De volta";
$string['steps_buttons_cancel'] = "Cancelar";
$string['steps_restore_title'] = "Curso de restauración da orixe";
$string['steps_restore_title_cat'] = "Cursos de restauración de orixe na categoría";
$string['step1_restore_desc'] = "Para restaurar un curso desde outra plataforma, primeiro debes seleccionar o sitio onde se atopa o curso orixinal";
$string['step1_restore_desc_cat'] = "Para restaurar categorías doutra plataforma, primeiro debes seleccionar o sitio onde se atopa a categoría orixinal";
$string['step2_restore_desc'] = "Seleccione o curso a restaurar";
$string['step2_restore_desc_cat'] = "Seleccione a categoría para restaurar";
$string['step2_course_list'] = "Lista de cursos de orixe";
$string['step2_course_id'] = "ID do curso";
$string['step2_course_name'] = "Nome do curso";
$string['step2_course_shortname'] = "Curso de nomes curtos";
$string['step2_course_idnumber'] = "Curso de Número de DNI";
$string['step2_course_categoryid'] = "ID de categoría";
$string['step2_course_categoryname'] = "Nome Categoría";
$string['step3_restore_desc'] = "Seleccione os detalles do curso";
$string['step3_sections_title'] = "Seccións";
$string['step4_restore_desc'] = "Seleccione a configuración para restaurar";
$string['step4_config_title'] = "Configuración";
$string['step5_restore_origin_site'] = "Sitio de orixe";
$string['step5_restore_selected_course'] = "Curso seleccionado";
$string['step5_sections_title'] = "Seccións seleccionadas";
$string['step5_configuration_title'] = "Configuración seleccionada";
$string['config_target_merge_activities'] = "Combina a copia de seguranza con este curso";
$string['config_target_remove_enrols'] = "Eliminar roles e matrículas no curso de destino";
$string['config_target_remove_groups'] = "Eliminar grupos e agrupacións no curso de destino";
$string['config_target_remove_activities'] = "Elimina o contido actual do curso e despois restaura";
$string['course_details_shortname'] = "Nome curto";
$string['course_details_course_id'] = "Número de identificación";
$string['course_details_category_name'] = "Nome Categoría";
$string['course_details_category_id'] = "ID de categoría";
$string['course_details_backup_size'] = "Tamaño estimado (MB)";
$string['course_sections_title'] = "Seccións";
$string['sections_table_id'] = "ID de sección";
$string['sections_table_number'] = "Número de sección";
$string['sections_table_name'] = "Nome da sección";
$string['activities_table_name'] = "Nome da actividade";
$string['activities_table_type'] = "Tipo de actividade";
$string['list_course_restoration'] = "Lista de restauracións do curso";
$string['list_course_restoration_cat'] = "Listaxe de restauracións da categoría";
$string['list_desc_restoration'] = "Fai clic aquí para restaurar un curso desde outra plataforma. A continuación mostrarase un paso a paso para facelo correctamente";
$string['list_desc_restoration_cat'] = "Fai clic aquí para restaurar unha categoría desde outra plataforma. A continuación mostrarase un paso a paso para facelo correctamente.";
$string['status_error'] = "Erro";
$string['status_not_started'] = "Sen comezar";
$string['status_in_progress'] = "En progreso...";
$string['status_in_backup'] = "Copia de seguranza";
$string['status_incompleted'] = "Sen cubrir";
$string['status_download'] = "Descargando...";
$string['status_downloaded'] = "Descargado";
$string['status_restore'] = "Restaurando...";
$string['status_completed'] = "Completado";
$string['error_validate_site'] = "O sitio seleccionado non é válido";
$string['error_not_controlled'] = "O sitio non está dispoñible neste momento. Téntao de novo máis tarde";
$string['site_not_found'] = "O sitio seleccionado non está entre os dispoñibles";
$string['origin_restore_category'] = "Restaurar categoría remota";
$string['step2_category_id'] = "ID de categoría";
$string['step2_category_name'] = "Nome";
$string['step2_category_idnumber'] = "Número de identificación";
$string['step2_category_parentname'] = "Categoría parental";
$string['step2_category_totalcourses'] = "Número de Cursos";
$string['step2_category_totalsubcategories'] = "Número de subcategorías";
$string['step2_category_totalcourseschild'] = "Número de subcategorías de cursos";
$string['step2_categories_list'] = "Lista de categorías de fontes";
$string['step3_restore_desc_cat'] = "Seleccione os cursos que quere restaurar da categoría escollida";
$string['step3_category_list'] = "Lista de cursos da categoría de orixe:";
$string['category_details_name'] = "Nome da categoría";
$string['category_details_category_id'] = "ID de categoría";
$string['category_details_parent_name'] = "Categoría parental";
$string['step4_restore_desc_cat'] = "Configuración de restauración de categorías";
$string['step4_restore_origin_site'] = "Sitio de orixe";
$string['step4_restore_selected_category'] = "Categoría seleccionada";
$string['step4_courses_title_desc'] = "Cursos seleccionados";
$string['execute_restore'] = "Executa a restauración";
$string['execute_remove'] = "Executar a eliminación";
$string['origin_category_id'] = "DNI Categoría Orixe";
$string['origin_category_courses'] = "Cursos para restaurar";
$string['target_course_id_integer'] = "O ID do curso de destino debe ser enteiro";
$string['target_category_id_require'] = "É necesario o ID da categoría de destino";
$string['target_category_id_integer'] = "O ID da categoría de destino debe ser enteiro";
$string['not_activities'] = "Non se atoparon actividades";
$string['not_courses'] = "Non se atoparon cursos";
$string['request_not_found'] = "Non se atopou a solicitude";
$string['deleteintarget'] = "Eliminar no destino";
$string['config_target_merge_activities_desc'] = "As actividades do curso de orixe fusionaranse co curso de destino";
$string['config_target_remove_activities_desc'] = "Todo o contido do curso de destino eliminarase e restaurarase co curso de orixe";
$string['config_target_remove_groups_desc'] = "Eliminaranse todos os grupos e agrupacións existentes do curso de destino.";
$string['config_target_remove_enrols_desc'] = "Eliminaranse as inscricións e funcións actuais para o curso de destino que xa existan.";
$string['summary'] = "Resumo";
$string['config'] = "Configuración";
$string['refresh'] = "Actualizar";
$string['index_title'] = "Datos para a integración con outros Moodle";
$string['token_user_ws'] = "Token para outras plataformas";
$string['user_ws'] = "Usuario do servizo web";
$string['sections_table_select_all'] = "Marcar todo";
$string['restore_page'] = "Restaurar cursos ou categorías remotos";
$string['restore_page_desc'] = "Seleccione se quere restaurar unha categoría ou un conxunto de cursos.";
$string['remove_page'] = "Eliminando cursos de plataforma remota";
$string['remove_page_desc'] = "Seleccione se desexa eliminar unha categoría ou un conxunto de cursos.";
$string['token_not_found'] = "Non se puido recuperar o token.";
$string['click_refresh'] = 'Fai clic no botón "Actualizar" para volver calcular a configuración.';
$string['restoretnewcourse'] = "Restauración realizada nun novo curso";
$string['course_categories'] = "Restaurar cursos ou categoría";
$string['course_categories_help'] = "Seleccione cursos se quere restaurar unha lista de cursos, seleccione unha categoría se quere restaurar unha categoría enteira ou unha lista de cursos desa categoría";
$string['origin_restore_courses_title'] = "Restaurar cursos de orixe";
$string['origin_restore_courses_desc'] = "Seleccione os cursos que quere restaurar desde o sitio de orixe e ligue o destino correspondente. Podes seleccionar no destino, novo curso e crearase un novo curso no destino. Máis tarde podes seleccionar a categoría.";
$string['origin_restore_courses_list'] = "Lista de cursos do sitio de orixe";
$string['step2_course_target'] = "Curso Destino";
$string['origin_restore_step4_desc'] = "Revisa os cursos seleccionados, o seu destino e a configuración antes de executar a restauración remota. Lembra seleccionar a categoría de destino, se o curso a crear é novo";
$string['origin_restore_category_title'] = "Restaurar a categoría de orixe";
$string['origin_restore_category_desc'] = "Seleccione a categoría que quere restaurar desde o sitio de orixe";
$string['step4_target_title'] = "Categoría obxectivo";
$string['step4_target_desc'] = "Seleccione a categoría de destino de restauración";
$string['origin_restore_category_step3_desc'] = "Seleccione a categoría de destino e a configuración a aplicar na restauración";
$string['origin_restore_category_step4_desc'] = "Revisa a configuración seleccionada antes de realizar as restauracións";
$string['course_categories_remove'] = "Eliminar cursos ou categorías";
$string['course_categories_remove_help'] = "Seleccione se desexa eliminar unha lista de cursos ou unha categoría enteira";
$string['remove_course_page'] = "Eliminar cursos remotos";
$string['remove_page_course_desc'] = "Selecciona os cursos do sitio remoto que queres eliminar";
$string['origin_remove_step3_desc'] = "Revisa os cursos seleccionados para eliminar no sitio de orixe.";
$string['origin_remove_courses_list'] = "Cursos para eliminar no sitio de orixe.";
$string['origin_remove_step3_cat_desc'] = "Comprobe os datos da categoría remota a eliminar";
$string['origin_remove_category_step3'] = "Categoría para eliminar";
$string['remove_category_page'] = "Eliminar categoría remota";
$string['logs_page'] = "Rexistros de execución";
$string['course_completed_sections'] = "Restauración do curso completo";
$string['restore_origin_data'] = "Configuración do curso de orixe";
$string['restore_origin_cat_data'] = "Configuración de categorías e cursos de orixe";
$string['restore_origin_user_data'] = "Restaurar o curso cos datos do usuario de orixe";
$string['restore_origin_user_data_desc'] = "O curso restaurarase cos datos de usuario existentes no curso de orixe";
$string['detail'] = "Detalle";
$string['type'] = "Mozo";
$string['direction'] = "Enderezo";
$string['restore_course'] = "Curso de Restauración";
$string['restore_category'] = "Categoría Restauración";
$string['remove_course'] = "Eliminación do curso";
$string['remove_category'] = "Eliminación de categoría";
$string['request'] = "Petición";
$string['response'] = "Resposta";
$string['view_logs'] = "Ver rexistros";
$string['target_site'] = "Sitio de destino";
$string['id'] = "ID";
$string['host_url'] = "URL do host";
$string['host_token'] = "Token de host";
$string['test'] = "Proba";
$string['actions'] = "Accións";
$string['log_page'] = "Detalle do rexistro";
$string['log_page_general_data'] = "Datos xerais";
$string['log_page_url'] = "URL";
$string['log_page_user'] = "Usuario";
$string['log_page_status'] = "Estado";
$string['log_page_exec_date'] = "Data de execución";
$string['log_page_target_target'] = "Obxectivo de destino";
$string['log_page_petition_type'] = "Tipo de solicitude";
$string['log_page_direction'] = "Enderezo";
$string['log_page_target_request'] = "Solicitude de destino";
$string['log_page_request_category'] = "Categoría de solicitude";
$string['log_page_target_data'] = "Datos de destino";
$string['log_page_course_id'] = "ID do curso";
$string['log_page_category_id'] = "ID de categoría";
$string['log_page_remove_enrols'] = "Eliminar rexistros";
$string['log_page_remove_groups'] = "Eliminar grupos";
$string['log_page_origin_course_data'] = "Datos do curso de orixe";
$string['log_page_course_fullname'] = "Nome do curso";
$string['log_page_course_shortname'] = "Nome curto Curso";
$string['log_page_course_idnumber'] = "Curso de Número de DNI";
$string['log_page_origin_category_data'] = "Datos da categoría de orixe";
$string['log_page_category_name'] = "Nome da categoría";
$string['log_page_category_idnumber'] = "DNI Caregoria";
$string['log_page_category_requests'] = "Solicitudes de categoría";
$string['log_page_config'] = "Configuración";
$string['log_page_user_data'] = "Datos do usuario";
$string['log_page_remove_course'] = "Eliminar o curso";
$string['log_page_remove_category'] = "Eliminar o curso";
$string['log_page_remove_exec_time'] = "Tempo de execución";
$string['log_page_origin_activities'] = "Actividades de orixe";
$string['log_page_origin_backup_size'] = "Tamaño da copia de seguranza (Mb)";
$string['log_page_origin_backup_size_estimated'] = "Tamaño estimado (Mb)";
$string['log_page_origin_backup_url'] = "URL do ficheiro de copia de seguridade";
$string['log_page_fileurl'] = "URL do ficheiro";
$string['log_page_error'] = "Erros";
$string['log_page_error_code'] = "Código de erro";
$string['log_page_error_msg'] = "Mensaxe de erro";
$string['create_site'] = "Crear un sitio";
$string['back_config'] = "Volver á configuración";
$string['host_url_desc'] = "Engade o URL do host";
$string['host_token_desc'] = "Engade o token de host";
$string['delete_site'] = "Eliminar sitio";
$string['delete_site_question'] = "Estás seguro de eliminar este sitio?";
$string['edit_site'] = "Editar sitio";
$string['view_error'] = "Ver erro";
$string['backupsize_larger'] = "O tamaño da copia de seguridade é superior ao permitido";
$string['restore_origin_remove'] = "Eliminar o curso de orixe";
$string['restore_origin_remove_desc'] = "O curso de orixe eliminarase unha vez restaurado";
$string['restore_origin_cat_remove'] = "Eliminar a categoría de orixe";
$string['restore_origin_cat_remove_desc'] = "A categoría de orixe eliminarase unha vez restaurada por completo";
$string['coursetransfer:origin_restore'] = "Restaurar cursos ou categorías remotos";
$string['coursetransfer:origin_restore_course'] = "Curso de restauración da plataforma de orixe";
$string['coursetransfer:origin_remove_course'] = "Eliminar o curso da plataforma de orixe";
$string['coursetransfer:origin_remove_category'] = "Eliminar a categoría da plataforma de orixe";
$string['coursetransfer:origin_restore_course_users'] = "Restaurar o curso cos datos do usuario de orixe";
$string['coursetransfer:origin_view_courses'] = "Consulta os cursos da plataforma fonte";
$string['coursetransfer:target_restore_enrol_remove'] = "Eliminar roles e matrículas no curso de destino";
$string['coursetransfer:target_restore_groups_remove'] = "Eliminar grupos e agrupacións no curso de destino";
$string['coursetransfer:target_restore_content_remove'] = "Elimina o contido actual do curso e despois restaura";
$string['coursetransfer:target_restore_merge'] = "Combina a copia de seguranza con este curso";
$string['forbidden'] = "Prohibido";
$string['you_have_not_permission'] = "Non tes permisos para ver esta páxina";
$string['createnewcategory'] = "En nova categoría...";
$string['origin_schedule'] = "Execución aprazada";
$string['origin_schedule_desc'] = "Se a tarefa se executa con demora, seleccione a data de execución";
$string['origin_schedule_datetime'] = "Data de execución";
$string['in_new_course'] = "En Curso Novo";
$string['remove_content'] = "Eliminar contido de destino";
$string['merge_content'] = "Combina o contido co destino";
$string['messageprovider:restore_course_completed'] = "Restauración do curso remoto completada";
$string['messageprovider:restore_category_completed'] = "Restauración da categoría remota completada";
$string['messageprovider:remove_course_completed'] = "Eliminar curso remoto completado";
$string['messageprovider:remove_category_completed'] = "Eliminar categoría remota completado";
$string['notification_restore_course_completed'] = "Completaches con éxito a restauración do curso remoto no teu destino: {$a}";
$string['notification_restore_category_completed'] = "Completaches con éxito a restauración da categoría remota no teu destino: {$a}";
$string['notification_remove_course_completed'] = "Completaches correctamente a eliminación do curso remoto: {$a}";
$string['notification_remove_category_completed'] = "Completaches correctamente a eliminación da categoría remota: {$a}";
$string['view_detail'] = "Ver detalle:";
$string['remove_config'] = "Borrar configuración";
$string['site_exist'] = 'O sitio xa existe';
$string['host_token_empty'] = 'O host ou o token está baleiro';
$string['courses_not_selected'] = 'Non hai cursos seleccionados';
$string['request_timeout'] = 'Timeout';
$string['request_timeout_desc'] = 'Tempo de espera da solicitude CURL en segundos entre a orixe e o destino';
$string['clean_adhoc_failed_task'] = 'Tarefa que limpa as tarefas ad-hoc erradas deste compoñente';
$string['remove_course_cleanup'] = 'Eliminación definitiva da curso';
$string['remove_course_cleanup_desc'] = 'Se está activo, o curso eliminarase definitivamente sen ter en conta a papeleira';
$string['remove_cat_cleanup'] = 'Eliminación definitiva da categoría';
$string['remove_cat_cleanup_desc'] = 'Se está activa, a categoría eliminarase permanentemente sen ter en conta a papeleira';
$string['in_target_adding_not_remove_enrols'] = 'Target enrols cannot be deleted when the target is a content merge (--target_target=4)';
$string['in_target_adding_not_remove_groups'] = 'Groups cannot be deleted in target, when the target is a content merge (--target_target=4)';
