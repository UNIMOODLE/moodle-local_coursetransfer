<!-- English version. Versión en español: README.es.md -->
<p align="center">
  <img src="pix/logo.svg" alt="" width="280">
</p>

<h1 align="center">Course Transfer</h1>

<p align="center">
  <img src="https://img.shields.io/badge/version-2.1.0-informational" alt="Version">
  <a href="https://moodle.org"><img src="https://img.shields.io/badge/Moodle-4.5%20--%205.1-orange?logo=moodle" alt="Moodle"></a>
  <img src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/License-GPL--3.0-green" alt="License">
  <a href="https://unimoodle.github.io/"><img src="https://img.shields.io/badge/project%20by-UNIMOODLE-194866" alt="Project by UNIMOODLE"></a>
  <a href="https://tresipunt.com"><img src="https://img.shields.io/badge/made%20by-Tresipunt-F84015" alt="Made by Tresipunt"></a>
</p>

<p align="center"><b>Copy and delete courses and categories between Moodle platforms — no manual export/import.</b></p>

<p align="center"><b>🇬🇧 English</b> · <a href="README.es.md">🇪🇸 Español</a> · <a href="README.ca.md">Català</a> · <a href="README.gl.md">Galego</a> · <a href="README.eu.md">Euskara</a></p>

Course Transfer links two Moodle sites (origin and target) over web services and
lets you **pull** courses or a whole category from another platform, or **delete**
them remotely, through guided wizards and a tracking log. It does not modify the
Moodle core or the theme, and all the heavy lifting (backup, download and
restore) runs in the background via scheduled tasks.

---

## ✨ What it does

- **Summary & integration** — landing panel with the **service token**
  (show/copy/regenerate/revoke) and health checks (token, REST service, service
  user, cron, platforms) so you can tell at a glance whether the pairing is
  ready.
- **Paired platforms** — register and manage the origin/target sites with a
  **connection test** per direction and the pairing status.
- **Restore remote content** — a wizard to pull **a course** or **a whole
  category** (recreating its tree of subcategories and courses) from another
  platform, choosing the destination category with an **autocomplete search**.
- **Delete remote content** — a wizard to delete courses or categories on the
  origin site, with a mandatory confirmation and **schedulable** execution for a
  given date.
- **Execution log** — in-progress and historical requests, with download/restore
  **progress**, filters, **export** (CSV/Excel/ODS), a trash action and a
  **detail** view with a timeline and the associated tasks.
- **CLI automation** — scripts to launch restores/deletions and query the logs
  from the command line.

## 💡 Use cases

- **Yearly migration** — at the start of the academic year, pull the courses or
  a whole category from the production Moodle into a fresh instance.
- **Consolidate into an existing course** — a teacher restores a remote course
  **over their current course** (merge mode) to reuse content.
- **Clone a category** — recreate a category tree with its courses on another
  platform, preserving the hierarchy.
- **End-of-term cleanup** — **schedule** the deletion of old courses or
  categories on the origin site for a specific date.
- **Copy between environments** — move content from one environment (e.g.
  staging) to another (e.g. production) without manually exporting/importing
  files.

## ⚙️ How it works

- Two Moodle platforms are paired by exchanging each other's web service **URL**
  and **token** (the token is obtained on the *Summary* screen).
- When a restore is requested, the **origin** generates a backup (`.mbz`) of the
  course/category and the **target** downloads and restores it.
- The process is **asynchronous**: it relies on adhoc tasks and Moodle **cron**
  on both sides, so cron must be running.
- The user is resolved by equivalence across platforms (by `username`, `email`
  or `idnumber`, configurable).
- It does **not** modify the Moodle core or theme; it uninstalls like any other
  local plugin.

## 📋 Requirements

| Requirement | Version |
|---|---|
| Moodle | 4.5+ (tested up to 5.1) |
| PHP | 8.1+ |
| Configuration | **REST** web services enabled and a **service user** (the plugin helps create it from *Summary*) |
| Other plugins | None required |

## 🚀 Installation

1. Copy the code into `local/coursetransfer/` (on Moodle 5.x:
   `public/local/coursetransfer/`).
2. Finish the installation from **Site administration › Notifications** (or via
   CLI: `php admin/cli/upgrade.php --non-interactive`).
3. Purge the caches (**Site administration › Development › Purge caches** or
   `php admin/cli/purge_caches.php`).
4. Open the plugin's **Summary** screen and click **Create token** to set up the
   web service and the service user. Repeat the installation on the other
   platform and register each site under **Platforms** with the other end's URL
   and token.

## 🔧 Settings

Under **Site administration › Plugins › Local plugins › Course Transfer**
(`admin/settings.php?section=local_coursetransfer`):

| Setting | Effect |
|---|---|
| **Maximum course size to restore** (`target_restore_course_max_size`) | Limit (MB) of the backup (`.mbz`) to restore; if exceeded, the download fails and it is recorded in the log. |
| **Request timeout** (`request_timeout`) | Seconds to wait for the calls between platforms. |
| **Ignore cURL security** (`ignorecurlsecurity`) | Allows operating against sites with internal/unverifiable certificates (use with care). |
| **Results per page** (`pagesize`) | Number of results per page in the wizard search boxes. |
| **Failed adhoc task cleanup** (`clean_adhoc_faildelay`) | Age from which failed tasks are cleaned up (`0` = disabled). |
| **Empty trash after deleting a course** (`remove_course_cleanup`) | Permanently removes the course deleted remotely. |
| **Empty trash after deleting a category** (`remove_cat_cleanup`) | Permanently removes the category deleted remotely. |
| **User search field** (`origin_field_search_user`) | Field used to match the equivalent user across platforms (`username` / `email` / `idnumber`). |

**Paired platforms** are not configured here: they are managed from the plugin's
**Platforms** screen (with a connection test).

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
* view_log_target_course.php
    - CLI para ver los logs de restauraciones en un curso como destino
* view_log_target_category.php
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

* local_coursetransfer_target

    Contiene la información de los sitios de destino configurados.

## Tests Unitarios
Para ejecutar los test unitarios en Moodle hay que realizar los siguientes pasos utilizando la documentación oficial https://moodledev.io/general/development/tools/phpunit:

1. Instalar PHP Unit con Composer
2. Configurar el archivo config.php según documentación
3. Inicializar el entorno de pruebas con:

   ``php admin\tool\phpunit\cli\init.php``


4. Ejecutar el grupo de test de Course Transfer:

    ``vendor\bin\phpunit --filter local_coursetransfer``
## 🖥️ Command line usage (optional)

All scripts live in `local/coursetransfer/cli/` and accept `--help` (or `-h`):

**Operations**

| Script | What it does |
|---|---|
| `restore_course.php` | Restores a remote course into this site. |
| `restore_category.php` | Restores a whole remote category (with its tree of subcategories and courses). |
| `remove_course.php` | Deletes a course on the origin (remote) site. |
| `remove_category.php` | Deletes a whole category on the origin (remote) site. |

**Log queries**

| Script | What it does |
|---|---|
| `view_logs.php` | Lists requests filtered by type, direction, status, user or date. |
| `view_log_request.php` | Shows the detail of a single request (`--requestid=<N>`). |
| `view_log_request_activities_detail.php` | Shows the sections and activities selected in a request. |
| `view_log_origin_course.php` | Logs for a course as **origin** (requests received from another Moodle). |
| `view_log_origin_category.php` | Logs for a category as **origin** (requests received from another Moodle). |
| `view_log_destiny_course.php` | Logs of restores into a course as **target**. |
| `view_log_target_category.php` | Logs of restores into a category as **target**. |

```bash
# Examples
php local/coursetransfer/cli/restore_course.php --help
php local/coursetransfer/cli/view_logs.php --help
php local/coursetransfer/cli/view_log_request.php --requestid=<N>
```

Exit codes: `0` success · `1` runtime error · `2` usage error.

## 🗑️ Uninstall

Uninstall from **Site administration › Plugins › Plugins overview**. The plugin's
own tables and data (requests and execution logs) are removed; courses and
categories already restored are **not** affected.

## 🛠️ Development (optional)

```bash
# Build the JavaScript (AMD) modules after editing amd/src/
grunt amd --root=local/coursetransfer

# Unit tests
vendor/bin/phpunit --testsuite local_coursetransfer_testsuite

# Acceptance tests
vendor/bin/behat --tags @local_coursetransfer
```

## 🏛️ Credits

**A community project.** In October 2022, the Universities of Valladolid, Complutense de Madrid, País Vasco/EHU, León, Salamanca, Illes Balears, Valencia, Rey Juan Carlos, La Laguna, Zaragoza, Málaga, Córdoba, Extremadura, Vigo, Las Palmas and Burgos created an inter-university group to develop new tools to improve Moodle-based virtual campuses, as part of the European Next Generation funding with the UNIDIGITAL programme.

**Programmed by Tresipunt.** UNIMOODLE held an open competition, and those selected were given the task of creating new, high quality components for the benefit of the entire Moodle community. Course Transfer was developed by Moodle Partner Tresipunt, with 20 years of experience in application development and global business solutions for large national and international companies.

> **This is a UNIMOODLE project** — pushing the Moodle community from the University.

Full credits: [unimoodle.github.io/moodle-local_coursetransfer/credits.html](https://unimoodle.github.io/moodle-local_coursetransfer/credits.html)

## 📄 License

[GNU GPL v3 or later](https://www.gnu.org/copyleft/gpl.html) — 2023 [Tresipunt](https://tresipunt.com) (contacte@tresipunt.com)

---

<p align="center">
  <a href="https://unimoodle.github.io/"><img src="pix/unimoodle_logo.png" alt="UNIMOODLE" width="220"></a>
</p>
<p align="center">
  <img src="https://unimoodle.github.io/assets/images/unidigital-footer2024-1466x187.png" alt="UNIDIGITAL" width="400">
</p>
<p align="center">
  <a href="https://tresipunt.com"><img src="pix/tresipunt_logo.svg" alt="Tresipunt" width="150"></a>
</p>
