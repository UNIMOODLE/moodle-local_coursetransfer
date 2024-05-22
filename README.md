![Logo Plugin Course Transfer](https://docs.moodle.org/all/es/images_es/thumb/9/9b/coursetransfer_logo.png/300px-coursetransfer_logo.png)

# Local Course Transfer Plugin

Local plugin for transferring courses between platforms

## Compatibility

The plugin has been tested on the following versions:

* Moodle 4.1.1 (Build: 20230116) - 2022112801.00
* Moodle 3.11.17+ (Build: 20231124) - 2021051717.06

## Requirements

* User configuration and REST Web Services

## Languages

* English
* Spanish

## Installation via uploaded ZIP file

1. Log in to your Moodle site as an administrator and go to _Site Administration > Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be asked to add additional details if your plugin type is not automatically detected.
3. Verify the plugin validation report and complete the installation.

## Manual Installation

The plugin can also be installed by placing the contents of this directory in

    {your/moodle/dirroot}/local/coursetransfer

Then, log in to your Moodle site as an administrator and go to _Site Administration > Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

## Global Configuration

Go to the URL:

    {your/moodle/dirroot}/admin/settings.php?section=local_coursetransfer

*   Maximum course size to restore
    * local_coursetransfer | destiny_restore_course_max_size
    * Limit on the size of the course backup (MBZ file) to be restored in MB. If the file to be restored is larger, there will be an error during the download step, reflected in the corresponding restoration table.

*  Destination sites
   * local_coursetransfer | destiny_sites
   * List of destination sites to which backups or deletions of courses can be responded to. Click to manage destination sites.

*  Origin sites
    * local_coursetransfer | origin_sites
    * List of origin sites from which backups or deletions of courses can be requested. Click to manage destination sites.

* Origin user field
    * local_coursetransfer | origin_field_search_user
    * Field to use for searching a user on the origin site relative to the destination site: username, email, userid, idnumber.
    
     *This will be the way to authenticate a user on the origin and destination platforms. Example: If you select username, a user will be able to view, restore, and delete, according to their permissions, the courses associated with the same username on the other platform.*

## Configure Automatic Service
After installation, the following processes will be automated in the file:

    {your/moodle/dirroot}/local/coursetransfer/postinstall.php

1. Creation of the local_coursetransfer_ws role from the coursecreator archetype
2. Assignment of capabilities for the plugin to function
3. Creation of a user with the above role and username: local_coursetransfer_ws
4. Activation of web services on the platform, the REST protocol, and web services documentation.
5. Creation of the token for the web service of the local_coursetransfer component with the above user.

Additionally, if something is misconfigured or a role or user is deleted at any time, the 'Refresh' button can be executed (runs the postinstall.php file and redirects to the same site) to review and fix any configuration changes on the platform:

    {your/moodle/dirroot}//local/coursetransfer/index.php

On this same page, we can see the service token and a link to the plugin configuration.

## Configure Web Service Manually

We can also configure it manually as follows:
1. It is recommended to create a specific role for this type of users.
2. Create a user with web service authentication, or use an existing one.
3. Assign the newly created role globally with the necessary permissions (webservice/rest:use).

    ``{your/moodle/dirroot}/admin/roles/assign.php?contextid=1``

4. Go to Server/External Services

    ``{your/moodle/dirroot}/admin/settings.php?section=externalservices``

5. Enable web services

    ``{your/moodle/dirroot}/admin/search.php?query=enablewebservices``

6. Enable the REST protocol

    ``{your/moodle/dirroot}/admin/settings.php?section=webserviceprotocols``

7. In the 'local_coursetransfer' external service, add it as an authorized user.
8. Finally, manage tokens

    ``{your/moodle/dirroot}/admin/webservice/tokens.php``

9. Create a token assigning the local_coursetransfer service to the previously created user.
10. This token is what needs to be used in other Moodle instances to connect.

## Summary

On this same page, we can see the service token and a link to the plugin configuration.

    /local/coursetransfer/index.php 

Additionally, if something is misconfigured or a role or user is deleted at any time, the 'Refresh' button can be executed (runs the postinstall.php file and redirects to the same site) to review and fix any configuration changes on the platform.

## Shortcuts from the Administration Panel

The administrator will have the following links available in the 'Extensions/Restore remote courses' section of the administration panel:

* Configuration: link to the plugin configuration
* Summary: link to the page with the token and the refresh configuration button
* Restore remote courses or categories: Link where the administrator can execute the restoration of courses or categories.
* Delete courses from the remote platform: Link where the administrator can delete remote courses or categories.
* Execution log: Table to review the execution of remote course restoration and deletion.

## CLI Executions

The following console scripts have been created:
* restore_course.php
    - CLI for course restoration
* restore_category.php 
    - CLI for category restoration
* view_log_destiny_course.php 
    - CLI to view the logs of restorations in a destination course
* view_log_destiny_category.php
    - CLI to view the logs of restorations in a destination category
* view_log_origin_course.php
    - CLI to view the logs of restorations in an origin course. Requests received from another Moodle.
* view_log_origin_category.php
    - CLI to view the logs of restorations in an origin category. Requests received from another Moodle.
* view_log_request.php
    - CLI to view the logs of a request.
* view_log_request_activities_detail.php
    - CLI to view the details of the sections and activities selected in a request.
* view_logs.php
    - CLI to view requests filtered by type, direction, status, user, or date.

### CLI Help
All scripts have help available using the help argument:

    php local/coursetransfer/cli/restore_course.php -h

## Features

* RCEP1 - Function to restore a group of courses between platforms
* RCEP2 - Function to restore a category of courses between platforms developed as a local plugin
* RCEP3 - Moodle CLI Script
* RCEP4 - Administration plugin to restore courses between platforms
* RCEP5 - Teacher plugin
* RCEP6 - Moodle plugin for the administrator to delete courses optimizing performance. Course deletion in environments
* RCEP7 - Log of restoration and deletion status
* RCEP8 - Scheduled or ad-hoc task

## Deferred Tasks
The administrator can select, both from the console and the graphical interface, whether the task will be executed as soon as possible or on a specific date.
For this, in the following features:

* Course restoration
* Category restoration
* Remote course deletion
* Remote category deletion

A configuration will appear to select whether the task will be executed in a deferred manner.

At the time of clicking on that configuration, the user can select the date when the execution will start on the origin platform.

Thus, the cron will only execute that task when the execution date has passed.

## Notifications
When a functionality is executed, asynchronous ad-hoc tasks are used that are executed by Moodle's cron.
For this reason, notification functionality has been added in the following executions:
* Course restoration
* Category restoration
* Remote course deletion
* Remote category deletion

The plugin comes with a default configuration, but this configuration can be modified by the administrator or the user:

   ``/message/notificationpreferences.php``

If web is selected, the notification will be via the web application.

And if Email is selected, the user will receive an email when the functionality is completed.

The administrator can disable these notifications for all users in any case: ```/admin/message.php```

## Database Tables

* local_coursetransfer_request
  
  Contains information about requests between platforms.

* local_coursetransfer_origin
  
  Contains information about configured origin sites.

* local_coursetransfer_destiny 
  
    Contains information about configured destination sites.

## Unit Tests
To run unit tests in Moodle, follow these steps using the official documentation https://moodledev.io/general/development/tools/phpunit:

1. Install PHP Unit with Composer
2. Configure the config.php file according to the documentation
3. Initialize the test environment with:

   ``php admin\tool\phpunit\cli\init.php``

4. Run the Course Transfer test group:

    ``vendor\bin\phpunit --filter local_coursetransfer``


![Logo Plugin Course Transfer](https://docs.moodle.org/all/es/images_es/thumb/9/9b/coursetransfer_logo.png/300px-coursetransfer_logo.png)

# Plugin Local Course Transfer

Plugin local para la transferencia de cursos entre plataformas

## Compatibilidad

El plugin ha sido probado en las siguientes versiones:

* Moodle 4.1.1 (Build: 20230116) - 2022112801.00
* Moodle 3.11.17+ (Build: 20231124) - 2021051717.06

## Requisitos

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

Ir a la URL:

    {your/moodle/dirroot}/admin/settings.php?section=local_coursetransfer

*   Tamaño máximo del curso a restaurar
    * local_coursetransfer | destiny_restore_course_max_size
    * Límite en el tamaño de la copia de seguridad (archivo MBZ) del curso origen a restaurar en MB. Si el archivo a restaurar es más grande, habrá un error al realizar el paso de la descarga, reflejando el error en la tabla de restauración correspondiente.

*  Sitios destino
   * local_coursetransfer | destiny_sites
   * Listado de sitios destino, a los que se les podrá responder para copias de seguridad o borrado de los cursos. Hay que hacer clic para ir a la gestión de sitios de destino:


*  Sitios origen
    * local_coursetransfer | origin_sites
    * Listado de sitios origen, a los que se les podrá pedir copias de seguridad o borrado de los cursos. . Hay que hacer clic para ir a la gestión de sitios de destino.


* Campo usuario origen
    * local_coursetransfer | origin_field_search_user
    * Campo a utilizar para la búsqueda de un usuario en el sitio de origen respecto al sitio de destino: username, email, userid, idnumber.
    
     *Será la forma de autenticar un usuario en las plataformas de origen y destino. Ejemplo: Si seleccionamos username, un usuario podrá visualizar, restaurar y borrar, según sus permisos, los cursos asociados en la otra plataforma al usuarios con el mismo username.*

## Configurar servicio automático
Después de la instalación, se ejecutará el archivo:

    {your/moodle/dirroot}/local/coursetransfer/postinstall.php

En ese archivo estarán automatizados los siguientes procesos:
1. Creación del rol local_coursetransfer_ws desde el arquetipo coursecreator
2. Asignación de capabilities para el funcionamiento del plugin
3. Creación de un usuario con el rol anterior y username: local_coursetransfer_ws
4. Activación de los servicios webs en la plataforma, el protocolo REST y la documentación de servicios webs.
5. Creación del token para el servicio web del componente local_coursetransfer y con el usuario anterior.

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

## Resumen

En esta misma página podremos ver el token del servicio y un enlace a la configuración del plugin.

    /local/coursetransfer/index.php 

Además, si en cualquier momento se desconfigura algo o se borra algún rol o usuario, se podrá ejecutar el botón de ‘Refrescar’ (ejecuta el archivo postinstall.php y redirige al mismo sitio) para revisar y arreglar cualquier cambio en la configuración de la plataforma.


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

## Tareas en diferido
El administrador puede seleccionar, tanto por consola, como por interfaz gráfica, si la tarea se ejecutará lo antes posible, o en una fecha determinada.
Para ello, en siguientes funcionalidades:

* Restauración de curso
* Restauración de categoría
* Borrado de curso remoto
* Borrado de categoría remota

Aparecerá una configuración para poder seleccionar, si la tarea se ejecutará de forma diferida.

En el momento de hacer clic en esa configuración, el usuario podrá seleccionar la fecha en la que comenzará la ejecución en la plataforma de origen.

De esta forma, el cron solo ejecutará esa tarea cuando la fecha de ejecución se haya sobrepasado.

## Notificaciones
Cuando se ejecuta una funcionalidad, se utilizan tareas adhoc asíncronas que se ejecutan mediante el cron de Moodle.
Por este motivo, se ha añadido la funcionalidad de aviso por notificación en las siguientes ejecuciones:
* Restauración de curso
* Restauración de categoría
* Borrado de curso remoto
* Borrado de categoría remota
El plugin trae una configuración por defecto, pero esta configuración, se puede modificar por el administrador o por el usuario:

   ``/message/notificationpreferences.php``

Si se selecciona web, la notificación será mediante la aplicación web.

Y si selecciona Email, el usuario recibirá un email cuando la funcionalidad se complete.

El administrador podrá desactivar, en cualquier caso, estas notificaciones para todos los usuarios: ```/admin/message.php```

## Tablas de Base de datos

* local_coursetransfer_request
  
  Contiene la información de las peticiones entre plataformas.

* local_coursetransfer_origin
  
  Contiene la información de los sitios de origen configurados.

* local_coursetransfer_destiny 
  
    Contiene la información de los sitios de destino configurados.

## Tests Unitarios
Para ejecutar los test unitarios en Moodle hay que realizar los siguientes pasos utilizando la documentación oficial https://moodledev.io/general/development/tools/phpunit:

1. Instalar PHP Unit con Composer
2. Configurar el archivo config.php según documentación
3. Inicializar el entorno de pruebas con:

   ``php admin\tool\phpunit\cli\init.php``


4. Ejecutar el grupo de test de Course Transfer:

    ``vendor\bin\phpunit --filter local_coursetransfer``
