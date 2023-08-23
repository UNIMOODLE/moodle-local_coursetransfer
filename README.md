# Plugin Local Course Transfer

Plugin local para la transferencia de cursos entre plataformas

## Compatibilidad

This plugin version is tested for:

* Moodle 4.1.1 (Build: 20230116)) - 2022112801.00

## Requeriments

* Configuración de usuario y Servicios Web REST

## Lenguajes

* English
* Español

## Instalación via uploaded ZIP file ##

1. Inicie sesión en su sitio de Moodle como administrador y vaya a _Administración del sitio>
   Complementos > Instalar complementos_.
2. Cargue el archivo ZIP con el código del complemento. Solo se le debe pedir que agregue
   detalles adicionales si su tipo de complemento no se detecta automáticamente.
3. Verifique el informe de validación del complemento y finalice la instalación.

## Instalación manual ##

El plugin también se puede instalar colocando el contenido de este directorio en
    
    {your/moodle/dirroot}/local/coursetransfer

Luego, inicie sesión en su sitio Moodle como administrador y vaya a _Administración del sitio>
Notificaciones_ para completar la instalación.

Como alternativa, puede ejecutar

    $ php admin/cli/upgrade.php

para completar la instalación desde la línea de comandos.

## Global Configuration

Go to the URL:

    {your/moodle/dirroot}/admin/settings.php?section=local_coursetransfer

*   Tamaño máximo del curso a restaurar
    * local_coursetransfer | destiny_restore_course_max_size
    * Límite en el tamaño de la copia de seguridad (archivo MBZ) del curso origen a restaurar en MB.

*  Sitios destino
   * local_coursetransfer | destiny_sites
   * Listado de sitios destino, a los que se les podrá responder para copias de seguridad o borrado de los cursos. En la misma línea, host y token separados por punto y coma. Sitios separados por salto de línea.

   * Ejemplo:
    
    
    http://dominio.test;5e1bc573434396d2c3267eab3a5fe942
    http://dominio2.test;5e1bc523412sdfasf3243eab3a5fe942
    http://dominio3.test;5e1bc523412sdfasf3243eab3a5fe942

*  Sitios origen
    * local_coursetransfer | origin_sites
    * Listado de sitios origen, a los que se les podrá pedir copias de seguridad o borrado de los cursos. En la misma línea, host y token separados por punto y coma. Sitios separados por salto de línea.
    * Ejemplo:


    http://dominio.test;5e1bc573434396d2c3267eab3a5fe942
    http://dominio2.test;5e1bc523412sdfasf3243eab3a5fe942
    http://dominio3.test;5e1bc523412sdfasf3243eab3a5fe942


* Campo usuario origen
    * local_coursetransfer | origin_field_search_user
    * Campo a utilizar para la búsqueda de un usuario en el sitio de origen respecto al sitio de destino: username, email, userid

## Configurar servicio automático
Después de la instalación, se ejecutará el archivo:

    {your/moodle/dirroot}/local/coursetransfer/postinstall.php

En ese archivo estarán automatizados los siguientes procesos:
1. Creación del rol local_coursetransfer_ws desde el arquetipo coursecreator
2. Asignación de capabilities para el funcionamiento del plugin
3. Creación de un usuario con el rol anterior y username: local_coursetransfer_ws
4. Creación del token para el servicio web del componente local_coursetransfer y con el usuario anterior.

Además, si en cualquier momento se desconfigura algo o se borra algún rol o usuario, se podrá ejecutar el botón de ‘Refrescar’ (ejecuta el archivo postinstall.php y redirige al mismo sitio) para revisar y arreglar cualquier cambio en la configuración de la plataforma: 

    {your/moodle/dirroot}//local/coursetransfer/index.php


En esta misma página podremos ver el token del servicio y un enlace a la configuración del plugin.

## Configurar servicio web manual

También podemos realizar la configuración manual de la siguiente forma:
1. Se recomienda crear un rol específico para este tipo de usuarios
2. Creamos un usuario con la autenticación con servicio web, o utilizamos uno ya existente
3. Le añadimos como rol el nuevo creado de forma global, con los permisos necesarios (webservice/rest:use).
   
    ``{your/moodle/dirroot}/admin/roles/assign.php?contextid=1``

4. Vamos servidor/Servicios Externos

    ``{your/moodle/dirroot}/admin/settings.php?section=externalservices``

5. Habilitamos los servicios webs
   
    ``{your/moodle/dirroot}/admin/search.php?query=enablewebservices``
   
6. Habilitamos el protocolo REST
   
    ``{your/moodle/dirroot}/admin/settings.php?section=webserviceprotocols``
   
7. En el servicio externo ‘local_coursetransfer’ lo añadimos como usuario autorizado
8. Y por último, en gestionar tokens
   
    ``{your/moodle/dirroot}/admin/webservice/tokens.php``
   
9. Creamos un token asignando el servicio de local_coursetransfer al usuario que hemos creado anteriormente.
10. Este token es el que tenemos que utilizar en los otros Moodle para conectarse.

## Accesos directos desde el panel de administración

El administrador tendrá a su disposición los siguientes enlaces en el apartado de 'Extensiones/Restaurar cursos remotos' del panel de administración:

* Configuración: enlace a la configuración del plugin
* Resumen: enlace a la página con el token y el botón de refrescar configuración
* Restaurar cursos o categorías remotas: Enlace donde el administrador podrá ejecutar la restauración de cursos o categorías.
* Eliminación de cursos de plataforma remota: Enlace donde el administrador podrá borrar cursos o categorías remotos.
* Registro de ejecuciones: Tabla para revisar las ejecuciones de restauración y borrado de cursos remotos.

## Ejecuciones por CLI

Se han creado los siguiente script de consola:
* restore_course.php
    - CLI de restauracion de curso
* restore_category.php 
    - CLI de restauracion de categoría
* view_log_destiny_course.php 
    - CLI para ver los logs de restauraciones en un curso como destino
* view_log_destiny_category.php
    - CLI para ver los logs de restauraciones en una categoría como destino
* view_log_origin_course.php
    - CLI para ver los logs de restauraciones en un curso como origen. Las peticiones que ha recibido desde otro Moodle.
* view_log_origin_category.php
    - CLI para ver los logs de restauraciones en una categoría como origen. Las peticiones que ha recibido desde otro Moodle.
* view_log_request.php
    - CLI para ver los logs de una petición.
* view_log_request_activities_detail.php
    - CLI para ver el detalle de las secciones y actividades seleccionadas en una petición.
* view_logs.php
    - CLI para ver peticiones filtradas por tipo, dirección, estado, usuario o fecha.

### Ayuda en CLI
Todos los scripts disponen de ayuda utilizando el argumento help:

    php local/coursetransfer/cli/restore_course.php -h

## Funcionalidades

* RCEP1 - Función para restaurar un grupo de cursos entre plataformas
* RCEP2 - Función para restaurar una categoría de cursos entre plataformas desarrollado como plugin local
* RCEP3 - Script CLI Moodle
* RCEP4 - Plugin de administración para restaurar cursos entre plataformas
* RCEP5 - Plugin docente
* RCEP6 - Plugin Moodle para el administrador que permita el borrado de cursos optimizando el rendimiento. La eliminación de cursos en entornos
* RCEP7 - LOG del estado de restauración y eliminado
* RCEP8 - Tarea scheduled o ad-hoc