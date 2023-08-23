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
   * Listado de sitios origen, a los que se les podrá pedir copias de seguridad de los cursos. En la misma línea, host y token separados por punto y coma. Sitios separados por salto de línea.
   * Ejemplo:
    
    
    http://dominio.test;5e1bc573434396d2c3267eab3a5fe942
    http://dominio2.test;5e1bc523412sdfasf3243eab3a5fe942
    http://dominio3.test;5e1bc523412sdfasf3243eab3a5fe942

*  Sitios origen
    * local_coursetransfer | origin_sites
    * Listado de sitios origen, a los que se les podrá pedir copias de seguridad de los cursos. En la misma línea, host y token separados por punto y coma. Sitios separados por salto de línea.
    * Ejemplo:


    http://dominio.test;5e1bc573434396d2c3267eab3a5fe942
    http://dominio2.test;5e1bc523412sdfasf3243eab3a5fe942
    http://dominio3.test;5e1bc523412sdfasf3243eab3a5fe942


* Campo usuario origen
    * local_coursetransfer | origin_field_search_user
    * Campo a utilizar para la búsqueda de un usuario en el sitio de origen respecto al sitio de destino: username, email, userid

## Configurar servicio web manual

Por el momento, para que los servicios webs funcionen se debe configurar un token para un usuario de forma manual:
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


