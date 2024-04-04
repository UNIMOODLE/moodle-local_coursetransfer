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
 * Strings for component 'course transfer', language 'cat'
 *
 * @package    local_coursetransfer
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     3IPUNT <contacte@tresipunt.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = "Restaurar cursos remots";
$string['pluginname_header_general'] = "General";
$string['setting_target_restore_course_max_size'] = "Grandària màxima del curs a restaurar (MB)";
$string['setting_target_restore_course_max_size_desc'] = "Límit a la mida de la còpia de seguretat (arxiu MBZ) del curs origen a restaurar a MB.";
$string['setting_target_sites'] = "Llocs destinació";
$string['setting_target_sites_link'] = "Gestió de Llocs destinació";
$string['setting_target_sites_desc'] = "Llistat de llocs destí, als quals se'ls podrà lliurar les còpies de seguretat dels cursos. Realitza una prova per comprovar que tot està configurat correctament, tant a destinació com a origen.";
$string['setting_origin_sites'] = "Llocs origen";
$string['setting_origin_sites_link'] = "Gestió de Llocs origen";
$string['setting_origin_sites_desc'] = "Llistat de llocs origen, als quals se'ls podrà demanar còpies de seguretat dels cursos. Realitza una prova per comprovar que tot està configurat correctament, tant a destinació com a origen.";
$string['setting_origin_field_search_user'] = "Camp usuari origen";
$string['setting_origin_field_search_user_desc'] = "Camp a utilitzar per cercar un usuari al lloc d'origen.";
$string['origin_restore_course'] = "Restaurar curs en remot";
$string['origin_course_id_require'] = "Origin Course ID és obligatori: --origin_course_id=12";
$string['site_url_required'] = "Site URL és obligatori: --site_url=https://origen.domini";
$string['site_url_invalid'] = "Site URL no és vàlid: --site_url=https://origen.domini";
$string['origin_category_id_require'] = "Origin Category ID és obligatori: --origin_category_id=12";
$string['categoryid_require'] = "Category ID és obligatori";
$string['courseid_require'] = "Course ID és obligatori";
$string['requestid_require'] = "Request ID és obligatori: --requestid=3";
$string['target_target_is_incorrect'] = "Destiny Target és incorrecte, ha de tenir valors 2,3 o 4: --target_target=2";
$string['origin_course_id_integer'] = "Origin Course ID ha de ser sencer: --origin_course_id=12";
$string['target_course_id_is_required'] = "Destiny Course ID és obligatori: --target_course_id=12";
$string['target_course_id_isnot_correct'] = "Per a target_target=2 no heu de tenir curs de destinació, es crearà nou curs";
$string['target_category_id_integer'] = "Destiny Category ID ha de ser sencer: --target_category_id=101";
$string['origin_enrolusers_boolean'] = "Origin Enrol Users ha de ser boolean: --origin_enrolusers=true";
$string['target_remove_enrols_boolean'] = "Destiny Remove Enrols ha de ser boolean: --target_remove_enrols=false";
$string['target_remove_groups_booelan'] = "Destiny Remove Groups ha de ser boolean: --target_remove_groups=false";
$string['origin_remove_course_boolean'] = "Origin Remove Course ha de ser boolean: --origin_remove_course=false";
$string['origin_schedule_datetime_integer'] = "Origin Schedule Datetime ha de ser sencer: --origin_schedule_datetime=1679404952";
$string['target_not_remove_activities_invalid'] = "Destiny Not Remove Activities ha de ser una cadena de cmid separats per coma de tipus array: --target_not_remove_activities=[3,234,234]";
$string['origin_category_id_integer'] = "Origin Category ID ha de ser sencer: -origin_category_id=12";
$string['categoryid_integer'] = "Category ID ha de ser sencer";
$string['courseid_integer'] = "Course ID ha de ser sencer";
$string['requestid_integer'] = "Request ID ha de ser sencer";
$string['status_integer'] = "Status ha de ser sencer";
$string['from_integer'] = "from ha de ser un timestamp (sencer)";
$string['to_integer'] = "to ha de ser un timestamp (sencer)";
$string['userid_integer'] = "User ID ha de ser sencer";
$string['origin_site'] = "Seleccioneu el lloc d'origen";
$string['origin_site_help'] = "El lloc d'origen és on es troba el curs que es vol restaurar";
$string['request_id'] = "ID Petició";
$string['siteurl'] = "Lloc Origen";
$string['origin_course_id'] = "ID del curs d'origen";
$string['target_course_id'] = "ID del curs de destinació";
$string['status'] = "Estat";
$string['origin_activities'] = "Activitats d'origen";
$string['configuration'] = "Configuració";
$string['error'] = "Errors";
$string['userid'] = "ID Usuri";
$string['backupsize'] = "Grandària (MB)";
$string['timemodified'] = "Data de modificació";
$string['timecreated'] = "Data de creació";
$string['user_not_found'] = "Usuari no trobat a Moodle d'Origen/Destinació";
$string['user_does_not_have_courses'] = "L'usuari no té cursos al Moodle d'Origen";
$string['field_not_valid'] = "El camp no és vàlid. Si us plau, revisa la configuració del plugin";
$string['steps_buttons_next'] = "Següent";
$string['steps_buttons_back'] = "Enrere";
$string['steps_buttons_cancel'] = "Cancel·la";
$string['steps_restore_title'] = "Restaurar curs d'origen";
$string['steps_restore_title_cat'] = "Restaurar cursos d'origen en categoria";
$string['step1_restore_desc'] = "Per restaurar un curs des d'una altra plataforma, primer heu de seleccionar el lloc on es troba el curs original";
$string['step1_restore_desc_cat'] = "Per a la resta de categories de l'another platform, cal que seleccioneu el lloc on aquest category original està situat";
$string['step2_restore_desc'] = "Seleccioneu el curs per restaurar";
$string['step2_restore_desc_cat'] = "Seleccioneu la categoria per restaurar";
$string['step2_course_list'] = "Llista de cursos d'origen";
$string['step2_course_id'] = "ID Curs";
$string['step2_course_name'] = "Nom Curs";
$string['step2_course_shortname'] = "Nom Curt Curs";
$string['step2_course_idnumber'] = "ID Number Curs";
$string['step2_course_categoryid'] = "ID Categoria";
$string['step2_course_categoryname'] = "Nom Categoria";
$string['step3_restore_desc'] = "Seleccionar detalls del curs";
$string['step3_sections_title'] = "Seccions";
$string['step4_restore_desc'] = "Seleccioneu les configuracions per a la restauració";
$string['step4_config_title'] = "Configuració";
$string['step5_restore_origin_site'] = "Lloc d'origen";
$string['step5_restore_selected_course'] = "Curs Seleccionat";
$string['step5_sections_title'] = "Seccions seleccionades";
$string['step5_configuration_title'] = "Configuració seleccionada";
$string['config_target_merge_activities'] = "Fusionar la còpia de seguretat amb aquest curs";
$string['config_target_remove_enrols'] = "Eliminar rols i matriculacions al curs de destinació";
$string['config_target_remove_groups'] = "Eliminar grups i agrupaments al curs de destinació";
$string['config_target_remove_activities'] = "Esborrar el contingut del curs actual i després restaurar";
$string['course_details_shortname'] = "Nom curt";
$string['course_details_course_id'] = "ID Number";
$string['course_details_category_name'] = "Nom Categoria";
$string['course_details_category_id'] = "ID Categoria";
$string['course_details_backup_size'] = "Mida estimada (MB)";
$string['course_sections_title'] = "Seccions";
$string['sections_table_id'] = "ID Secció";
$string['sections_table_number'] = "Número Secció";
$string['sections_table_name'] = "Nom Secció";
$string['activities_table_name'] = "Nom Activitat";
$string['activities_table_type'] = "Tipus Activitat";
$string['list_course_restoration'] = "Llistat de restauracions del curs";
$string['list_course_restoration_cat'] = "Llistat de restauracions de la categoria";
$string['list_desc_restoration'] = "Fes clic aquí per restaurar un curs des d'una altra plataforma. A continuació es mostrarà un pas a pas per fer-lo correctament";
$string['list_desc_restoration_cat'] = "Fes clic aquí per restaurar una categoria des d'una altra plataforma. A continuació es mostrarà un pas a pas per fer-lo correctament.";
$string['status_error'] = "Error";
$string['status_not_started'] = "Sense començar";
$string['status_in_progress'] = "En progrés...";
$string['status_in_backup'] = "Copia Seguretat";
$string['status_incompleted'] = "Sense completar";
$string['status_download'] = "Descarregant...";
$string['status_downloaded'] = "Descarregada";
$string['status_restore'] = "Restaurant...";
$string['status_completed'] = "Completada";
$string['error_validate_site'] = "El lloc seleccionat és invàlid";
$string['error_not_controlled'] = "El lloc no està disponible en aquest moment. Intenteu-ho més tard";
$string['site_not_found'] = "El lloc seleccionat no es troba entre els disponibles";
$string['origin_restore_category'] = "Restaurar categoria remota";
$string['step2_category_id'] = "ID Categoria";
$string['step2_category_name'] = "Nom";
$string['step2_category_idnumber'] = "ID Number";
$string['step2_category_parentname'] = "Categoria Pare";
$string['step2_category_totalcourses'] = "Número de Cursos";
$string['step2_category_totalsubcategories'] = "Nombre de subcategories";
$string['step2_category_totalcourseschild'] = "Número de Cursos Subcategories";
$string['step2_categories_list'] = "Llista de categories d'origen";
$string['step3_restore_desc_cat'] = "Seleccioneu els cursos que voleu restaurar de la categoria escollida";
$string['step3_category_list'] = "Llista de cursos de la categoria d'origen:";
$string['category_details_name'] = "Nom de la Categoria";
$string['category_details_category_id'] = "ID de la Categoria";
$string['category_details_parent_name'] = "Categoria Pare";
$string['step4_restore_desc_cat'] = "Configuració de la restauració de la categoria";
$string['step4_restore_origin_site'] = "Lloc d'origen";
$string['step4_restore_selected_category'] = "Categoria seleccionada";
$string['step4_courses_title_desc'] = "Cursos seleccionats";
$string['execute_restore'] = "Executar restauració";
$string['execute_remove'] = "Executar esborrat";
$string['origin_category_id'] = "ID Categoria Origen";
$string['origin_category_courses'] = "Cursos a restaurar";
$string['target_course_id_integer'] = "ID Curs de Destinació ha de ser sencer";
$string['target_category_id_require'] = "ID Categoria de Destinació és obligatòria";
$string['target_category_id_integer'] = "ID Categoria de Destinació ha de ser sencer";
$string['not_activities'] = "No s'han trobat activitats";
$string['not_courses'] = "No s'han trobat cursos";
$string['request_not_found'] = "No s'ha trobat la petició";
$string['deleteintarget'] = "Esborrar en destinació";
$string['config_target_merge_activities_desc'] = "Les activitats del curs d'origen es fusionaran al curs de destinació";
$string['config_target_remove_activities_desc'] = "Tot el contingut del curs de destinació s'esborrarà i es restaurarà amb el curs d'origen";
$string['config_target_remove_groups_desc'] = "S'esborraran els grups i agrupaments actuals del curs de destinació ja existents";
$string['config_target_remove_enrols_desc'] = "S'esborraran les matriculacions i rols actuals del curs de destinació ja existents";
$string['summary'] = "Resum";
$string['config'] = "Configuració";
$string['refresh'] = "Refrescar";
$string['index_title'] = "Dades per a la integració amb altres Moodle";
$string['token_user_ws'] = "Token per a altres plataformes";
$string['user_ws'] = "Usuari Servei Web";
$string['sections_table_select_all'] = "Marqueu-ho tot";
$string['restore_page'] = "Restaurar cursos o categories remotes";
$string['restore_page_desc'] = "Seleccioneu si voleu restaurar una categoria o un conjunt de cursos.";
$string['remove_page'] = "Eliminació de cursos de plataforma remota";
$string['remove_page_desc'] = "Seleccioneu si voleu suprimir una categoria o un conjunt de cursos.";
$string['token_not_found'] = "El token no s'ha pogut recuperar.";
$string['click_refresh'] = "Fes clic al botó 'Refrescar' per recalcular la configuració.";
$string['restoretnewcourse'] = "Restauració realitzada en un curs nou";
$string['course_categories'] = "Restaurar cursos o categoria";
$string['course_categories_help'] = "Seleccioneu cursos si voleu restaurar un llistat de cursos, seleccioneu categoria si voleu restaurar una categoria completa o un llistat de cursos d'aquesta categoria";
$string['origin_restore_courses_title'] = "Restaurar cursos d'origen";
$string['origin_restore_courses_desc'] = "Seleccioneu els cursos que voleu restaurar del lloc d'origen i vinculeu la destinació corresponent. Podeu seleccionar a destinació, nou curs, i així es crearà un nou curs a destinació. Més endavant podreu seleccionar categoria.";
$string['origin_restore_courses_list'] = "Llista de cursos del lloc d'origen";
$string['step2_course_target'] = "Curs de Destinació";
$string['origin_restore_step4_desc'] = "Revisa els cursos seleccionats, la destinació i la configuració abans d'executar la restauració remota. Recordeu seleccionar la categoria de destinació, si el curs a crear és nou";
$string['origin_restore_category_title'] = "Restaurar categoria d'origen";
$string['origin_restore_category_desc'] = "Seleccioneu la categoria que voleu restaurar del lloc d'origen";
$string['step4_target_title'] = "Categoria de destinació";
$string['step4_target_desc'] = "Seleccioneu la categoria de destinació de la restauració";
$string['origin_restore_category_step3_desc'] = "Seleccioneu la categoria de destinació i la configuració a aplicar a la restauració";
$string['origin_restore_category_step4_desc'] = "Revisa les configuracions seleccionades abans d'executar les restauracions";
$string['course_categories_remove'] = "Esborrar cursos o categoria";
$string['course_categories_remove_help'] = "Selecciona si vols esborrar un llistat de cursos o una categoria completa";
$string['remove_course_page'] = "Esborrar cursos remots";
$string['remove_page_course_desc'] = "Selecciona els cursos del lloc remot que vols esborrar";
$string['origin_remove_step3_desc'] = "Reviseu els cursos seleccionats per esborrar al lloc d'origen.";
$string['origin_remove_courses_list'] = "Cursos a esborrar al lloc dorigen.";
$string['origin_remove_step3_cat_desc'] = "Revisa les dades de la categoria remota a esborrar";
$string['origin_remove_category_step3'] = "Categoria a esborrar";
$string['remove_category_page'] = "Esborrar categoria remota";
$string['logs_page'] = "Registres execucions";
$string['course_completed_sections'] = "Restauració del curs complet";
$string['restore_origin_data'] = "Configuració del curs dorigen";
$string['restore_origin_cat_data'] = "Configuració de la categoria i cursos dorigen";
$string['restore_origin_user_data'] = "Restaurar el curs amb dades d'usuaris d'origen";
$string['restore_origin_user_data_desc'] = "El curs es restaurarà amb les dades d'usuaris que hi ha al curs d'origen";
$string['detail'] = "Detall";
$string['type'] = "Tipus";
$string['direction'] = "Direcció";
$string['restore_course'] = "Restauració de Curs";
$string['restore_category'] = "Restauració de Categoria";
$string['remove_course'] = "Esborrat de curs";
$string['remove_category'] = "Esborrat de categoria";
$string['request'] = "Petició";
$string['response'] = "Resposta";
$string['view_logs'] = "Veure logs";
$string['target_site'] = "Lloc destinació";
$string['id'] = "ID";
$string['host_url'] = "Host URL";
$string['host_token'] = "Host Token";
$string['test'] = "Prova";
$string['actions'] = "Accions";
$string['log_page'] = "Detall del Log";
$string['log_page_general_data'] = "Dades generals";
$string['log_page_url'] = "URL";
$string['log_page_user'] = "Usuari";
$string['log_page_status'] = "Estat";
$string['log_page_exec_date'] = "Data execució";
$string['log_page_target_target'] = "Target de destinació";
$string['log_page_petition_type'] = "Tipus de petició";
$string['log_page_direction'] = "Direcció";
$string['log_page_target_request'] = "Petició de Destinació";
$string['log_page_request_category'] = "Petició Categoria";
$string['log_page_target_data'] = "Dades destí";
$string['log_page_course_id'] = "ID Curs";
$string['log_page_category_id'] = "ID Categoria";
$string['log_page_remove_enrols'] = "Esborrar matriculacions";
$string['log_page_remove_groups'] = "Esborrar grups";
$string['log_page_origin_course_data'] = "Dades curs origen";
$string['log_page_course_fullname'] = "Nom del curs";
$string['log_page_course_shortname'] = "Nom curt Curs";
$string['log_page_course_idnumber'] = "ID Number Curs";
$string['log_page_origin_category_data'] = "Dades categoria Origen";
$string['log_page_category_name'] = "Nom categoria";
$string['log_page_category_idnumber'] = "ID Number Caregoria";
$string['log_page_category_requests'] = "Peticions de Categoria";
$string['log_page_config'] = "Configuració";
$string['log_page_user_data'] = "Dades d'usuari";
$string['log_page_remove_course'] = "Esborrar el curs";
$string['log_page_remove_category'] = "Esborrar el curs";
$string['log_page_remove_exec_time'] = "Temps d'execució";
$string['log_page_origin_activities'] = "Activitats d'origen";
$string['log_page_origin_backup_size'] = "Grandària del Backup (Mb)";
$string['log_page_origin_backup_size_estimated'] = "Mida Benvolgut (Mb)";
$string['log_page_origin_backup_url'] = "URL del fitxer de backup";
$string['log_page_fileurl'] = "URL Arxiu";
$string['log_page_error'] = "Errors";
$string['log_page_error_code'] = "Codi d'error";
$string['log_page_error_msg'] = "Missatge d'Error";
$string['create_site'] = "Crear un lloc";
$string['back_config'] = "Tornar a configuració";
$string['host_url_desc'] = "Afegiu la URL del host";
$string['host_token_desc'] = "Afegiu el Token del host";
$string['delete_site'] = "Esborrar lloc";
$string['delete_site_question'] = "Esteu segur d'esborrar aquest lloc?";
$string['edit_site'] = "Editar lloc";
$string['view_error'] = "Veure error";
$string['backupsize_larger'] = "La mida del backup és més gran que la permesa";
$string['restore_origin_remove'] = "Suprimiu el curs d'origen";
$string['restore_origin_remove_desc'] = "El curs d'origen serà eliminat un cop restaurat";
$string['restore_origin_cat_remove'] = "Suprimiu la categoria d'origen";
$string['restore_origin_cat_remove_desc'] = "La categoria d'origen serà eliminada un cop restaurada completament";
$string['coursetransfer:origin_restore'] = "Restaurar cursos o categories remotes";
$string['coursetransfer:origin_restore_course'] = "Restaurar curs de plataforma origen";
$string['coursetransfer:origin_remove_course'] = "Esborrar curs de plataforma origen";
$string['coursetransfer:origin_remove_category'] = "Esborrar categoria de plataforma origen";
$string['coursetransfer:origin_restore_course_users'] = "Restaurar el curs amb dades d'usuaris d'origen";
$string['coursetransfer:origin_view_courses'] = "Veure cursos de plataforma origen";
$string['coursetransfer:target_restore_enrol_remove'] = "Eliminar rols i matriculacions al curs de destinació";
$string['coursetransfer:target_restore_groups_remove'] = "Eliminar grups i agrupaments al curs de destinació";
$string['coursetransfer:target_restore_content_remove'] = "Esborrar el contingut del curs actual i després restaurar";
$string['coursetransfer:target_restore_merge'] = "Fusionar la còpia de seguretat amb aquest curs";
$string['forbidden'] = "Prohibit";
$string['you_have_not_permission'] = "No teniu permisos per veure aquesta pàgina";
$string['createnewcategory'] = "En nova categoria...";
$string['origin_schedule'] = "Execució en diferit";
$string['origin_schedule_desc'] = "Si la tasca s'executa en diferit, seleccioneu la data d'execució";
$string['origin_schedule_datetime'] = "Data d'execució";
$string['in_new_course'] = "A Nou Curs";
$string['remove_content'] = "Esborrar contingut de destinació";
$string['merge_content'] = "Fusionar contingut a la destinació";
$string['messageprovider:restore_course_completed'] = "Restauració Curs Remot Completada";
$string['messageprovider:restore_category_completed'] = "Restauració Categoria Remota Completada";
$string['messageprovider:remove_course_completed'] = "Esborrat Curs Remot Completada";
$string['messageprovider:remove_category_completed'] = "Esborrat Categoria Remota Completada";
$string['notification_restore_course_completed'] = "Ha finalitzat amb èxit la restauració del curs remot a la vostra destinació: {$a}";
$string['notification_restore_category_completed'] = "Ha finalitzat amb èxit la restauració de la categoria remota a la vostra destinació: {$a}";
$string['notification_remove_course_completed'] = "L'esborrament del curs remot ha finalitzat amb èxit: {$a}";
$string['notification_remove_category_completed'] = "L'esborrament de la categoria remota ha finalitzat amb èxit: {$a}";
$string['view_detail'] = "Veure detall:";
$string['remove_config'] = "Configuració d'Esborrat";
$string['site_exist'] = 'El lloc ja existeix';
$string['host_token_empty'] = 'El host o el token estan buits';
$string['courses_not_selected'] = 'No hi ha cursos seleccionats';
$string['request_timeout'] = 'Timeout';
$string['request_timeout_desc'] = "Temps en segons d'espera petició CURL entre origen i destinació";
$string['clean_adhoc_failed_task'] = 'Tasca que neteja les tasques adhoc que han fallat aquest component';
$string['remove_course_cleanup'] = 'Esborrat definitiu curs';
$string['remove_course_cleanup_desc'] = "Si està actiu, s'esborrarà definitivament el curs sense tenir en compte la paperera de reciclatge";
$string['remove_cat_cleanup'] = 'Esborrat definitiu categoria';
$string['remove_cat_cleanup_desc'] = "Si està actiu, s'esborrarà definitivament la categoria sense tenir en compte la paperera de reciclatge";
$string['in_target_adding_not_remove_enrols'] = 'Destination enrols cannot be deleted when the target is a content merge (--target_target=4)';
$string['in_target_adding_not_remove_groups'] = 'Groups cannot be deleted in destination, when the target is a content merge (--target_target=4)';
