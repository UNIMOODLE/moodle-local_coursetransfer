<!-- Versión en español. English version: README.md -->
<p align="center">
  <img src="pix/logo.svg" alt="" width="280">
</p>

<h1 align="center">Course Transfer</h1>

<p align="center">
  <img src="https://img.shields.io/badge/version-2.2.2-informational" alt="Version">
  <a href="https://moodle.org"><img src="https://img.shields.io/badge/Moodle-4.5%20--%205.3-orange?logo=moodle" alt="Moodle"></a>
  <img src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/License-GPL--3.0-green" alt="License">
  <a href="https://unimoodle.github.io/"><img src="https://img.shields.io/badge/project%20by-UNIMOODLE-194866" alt="Proyecto de UNIMOODLE"></a>
  <a href="https://tresipunt.com"><img src="https://img.shields.io/badge/made%20by-Tresipunt-F84015" alt="Hecho por Tresipunt"></a>
</p>

<p align="center"><b>Copia y borra cursos y categorías entre plataformas Moodle, sin exportar/importar a mano.</b></p>

<p align="center"><a href="README.md">🇬🇧 English</a> · <b>🇪🇸 Español</b> · <a href="README.ca.md">Català</a> · <a href="README.gl.md">Galego</a> · <a href="README.eu.md">Euskara</a></p>

Course Transfer conecta dos Moodle (origen y destino) mediante servicios web y
permite **traer** cursos o una categoría completa desde otra plataforma, o
**borrarlos** en remoto, con asistentes guiados y un registro de seguimiento. No
modifica el núcleo de Moodle ni el tema, y todo el trabajo pesado (copia,
descarga y restauración) corre en segundo plano mediante tareas programadas.

---

## ✨ Qué hace

- **Resumen e integración** — panel de inicio con el **token del servicio**
  (mostrar/copiar/regenerar/revocar) y comprobaciones de salud (token, servicio
  REST, usuario de servicio, cron, plataformas) para saber de un vistazo si el
  emparejamiento está listo.
- **Plataformas emparejadas** — alta y gestión de los sitios origen/destino con
  **test de conexión** por dirección y estado del emparejamiento.
- **Restaurar contenido remoto** — asistente para traer **un curso** o **una
  categoría completa** (recreando su árbol de subcategorías y cursos) desde otra
  plataforma, eligiendo la categoría de destino con **buscador autocompletado**.
- **Borrar contenido remoto** — asistente para eliminar cursos o categorías en
  el sitio de origen, con confirmación obligatoria y ejecución **programable** a
  una fecha.
- **Registro de ejecuciones** — peticiones en curso e histórico, con **progreso**
  de descarga/restauración, filtros, **exportación** (CSV/Excel/ODS), papelera y
  una vista de **detalle** con línea de tiempo y tareas asociadas.
- **Automatización por CLI** — scripts para lanzar restauraciones/borrados y
  consultar los registros desde línea de comandos.

## 💡 Casos de uso

- **Migración anual** — al arrancar el curso, traer los cursos o una categoría
  completa desde el Moodle de producción a una instancia nueva.
- **Consolidar en un curso existente** — un profesor restaura un curso remoto
  **sobre su curso actual** (modo fusión) para reutilizar contenidos.
- **Clonar una categoría** — recrear en otra plataforma un árbol de categorías
  con sus cursos, manteniendo la jerarquía.
- **Limpieza de fin de curso** — **programar** el borrado de cursos o categorías
  antiguos en el sitio de origen para una fecha concreta.
- **Copia entre entornos** — llevar contenidos de un entorno (p. ej. formación)
  a otro (p. ej. producción) sin exportar/importar archivos a mano.

## ⚙️ Cómo funciona

- Dos plataformas Moodle se emparejan intercambiando la **URL** y el **token**
  del servicio web de cada una (el token se obtiene en la pantalla *Resumen*).
- Al pedir una restauración, el **origen** genera una copia de seguridad
  (`.mbz`) del curso/categoría y el **destino** la descarga y la restaura.
- El proceso es **asíncrono**: se apoya en tareas adhoc y en el **cron** de
  Moodle en ambos lados, por lo que el cron debe estar en marcha.
- El usuario se resuelve por equivalencia entre plataformas (por `username`,
  `email` o `idnumber`, configurable).
- **No** modifica el núcleo ni el tema de Moodle; se desinstala como cualquier
  plugin local.

## 📋 Requisitos

| Requisito | Versión |
|---|---|
| Moodle | 4.5, 5.1 o 5.3 |
| PHP | 8.1+ |
| Configuración | Servicios web **REST** habilitados y un **usuario de servicio** (el plugin ayuda a crearlo desde *Resumen*) |
| Cron | En marcha en **las dos** plataformas: copias, descargas, restauraciones, borrados y limpiezas son tareas |
| TLS | Certificado válido en cada plataforma emparejada: se rechazan los autofirmados y las cadenas incompletas mientras `sslverifypeer` esté activado |
| Otros plugins | No requiere |

## 🚀 Instalación

1. Copiar el código en `local/coursetransfer/` (en Moodle 5.x:
   `public/local/coursetransfer/`).
2. Completar la instalación desde **Administración del sitio › Notificaciones**
   (o por CLI: `php admin/cli/upgrade.php --non-interactive`).
3. Purgar las cachés (**Administración del sitio › Desarrollo › Purgar cachés**
   o `php admin/cli/purge_caches.php`).
4. Abrir la pantalla **Resumen** del plugin y pulsar **Crear token** para
   preparar el servicio web y el usuario de servicio. Repetir la instalación en
   la otra plataforma y dar de alta cada sitio en **Plataformas** con la URL y
   el token del otro extremo.

## 🔧 Ajustes

En **Administración del sitio › Plugins › Locales › Course Transfer**
(`admin/settings.php?section=local_coursetransfer`):

| Ajuste | Efecto |
|---|---|
| **Tamaño máximo de curso a restaurar** (`target_restore_course_max_size`) | Límite en MB del backup (`.mbz`) a restaurar; si se supera, la descarga falla y queda reflejado en el registro. |
| **Tiempo de espera de la petición** (`request_timeout`) | Segundos de espera de las llamadas entre plataformas. |
| **Permitir conexiones a hosts internos** (`ignorecurlsecurity`) | Desactivado por defecto. Mientras esté activo, los hosts y puertos bloqueados de Moodle (`curlsecurityblockedhosts`) no se aplican a las llamadas entre plataformas. Mejor dejarlo desactivado y permitir en esa lista solo los hosts emparejados. No desactiva la comprobación del certificado. |
| **Verificar el certificado de las plataformas emparejadas** (`sslverifypeer`) | Activado por defecto. Desactivarlo envía el token a pares cuya identidad no se comprueba: solo bajo responsabilidad del administrador. La prueba de conexión explica cuándo no se puede verificar un certificado. |
| **Resultados por página** (`pagesize`) | Número de resultados por página en los buscadores de los asistentes. |
| **Limpieza de tareas adhoc fallidas** (`clean_adhoc_faildelay`) | Antigüedad a partir de la cual se limpian las tareas fallidas (`0` = desactivado). |
| **Vaciar papelera tras borrar curso** (`remove_course_cleanup`) | Elimina definitivamente el curso borrado en remoto. |
| **Vaciar papelera tras borrar categoría** (`remove_cat_cleanup`) | Elimina definitivamente la categoría borrada en remoto. |
| **Campo de búsqueda de usuario** (`origin_field_search_user`) | Campo por el que se empareja el usuario equivalente entre plataformas (`username` / `email` / ID de usuario / `idnumber`). Al cambiarlo se repara la integración. |
| **Comprobar el curso restaurado antes de borrar el origen** (`verifymanifest`) | Activado por defecto. Ver *Protección contra pérdida de datos*. |
| **Días en la papelera antes del borrado definitivo** (`recyclebinretentiondays`) | 30 por defecto, mínimo 1. Ver *Protección contra pérdida de datos*. |
| **Días que se conservan las copias transferidas** (`backup_files_retention`) | 7 por defecto, mínimo 1. Las copias servidas como origen y las copias de seguridad guardadas como destino se borran esos días después de terminar su transferencia. |

Las **plataformas emparejadas** no se configuran aquí: se gestionan desde la
pantalla **Plataformas** del plugin (con test de conexión).

## 🛡️ Protección contra pérdida de datos

Los servicios entre plataformas solo se pueden llamar con el **token del usuario
de servicio** del otro sitio (capacidad `local/coursetransfer:webservice`), nunca
desde el navegador, y verifican el **certificado TLS** del otro sitio. Los
permisos se comprueban después sobre el usuario equivalente: el origen solo lista
y copia lo que ese usuario podría copiar a mano. Solo los administradores
(`moodle/site:config`) dan de alta plataformas, y la URL es una URL base sin más,
`http(s)://host[:puerto][/ruta]`, igual al `wwwroot` del otro sitio.

Borrar el origen tras una restauración (de curso o de categoría) es la única
operación que puede perder un curso. Se aplican estas salvaguardas:

| Salvaguarda | Qué protege | Cómo se configura |
|---|---|---|
| **Restauración completa** | El origen se conserva si el plan de restauración informa de módulos o ficheros que faltan (error `10410`). Una categoría solo se borra si todos sus cursos se restauraron sin incidencias, quien lo pide veía todas sus subcategorías en el origen y la categoría del origen no tiene cursos nuevos desde que empezó la restauración. | Siempre activa |
| **Comprobación del manifiesto** | Antes de borrar un curso del origen se comparan con el curso restaurado sus secciones y actividades por módulo (pedidas al origen) y, con usuarios, los matriculados (leídos de la copia). Si falta algo, el origen se conserva y el registro dice qué (`10412`). | `verifymanifest` (activada) |
| **Permisos de quien lo pide** | El borrado se hace como el usuario que lo pidió, en los dos sitios: `local/coursetransfer:origin_remove_course` / `_category` aquí, y `moodle/course:delete` / `moodle/category:manage` más la misma capacidad en el origen. | Roles |
| **Copia de seguridad previa** | Antes de una restauración que puede borrar el origen, el destino guarda una copia del backup. | `backup_files_retention` (nunca se purga antes de que su origen esté borrado más la retención de la papelera; se conserva si la restauración quedó incompleta) |
| **Papelera** | Con *Borrado definitivo del curso* activado, un curso borrado a petición de otra plataforma se queda en la papelera los días configurados antes de vaciarse, y se puede restaurar a mano. Necesita la papelera de categorías de `tool_recyclebin`; borrar una categoría entera siempre vacía su papelera (núcleo de Moodle). Si la papelera del núcleo caduca antes (7 días por defecto), el ajuste y el diagnóstico de *Resumen* lo avisan. | `recyclebinretentiondays` (30) |
| **Auditoría** | Cada borrado pedido a otra plataforma o hecho para ella, y cada decisión de borrar o conservar el origen tras una restauración, queda como evento (*Administración del sitio › Informes › Registros*). | Siempre activa |

Cuando el origen se conserva, la petición sigue mostrando la restauración como
completada, con el motivo en su detalle; una vez revisado, el origen se puede
borrar con el asistente *Borrar contenido remoto*.

## 🔐 Permisos

| Capacidad | Por defecto | Permite |
|---|---|---|
| `local/coursetransfer:origin_restore` | — | Restaurar cursos o categorías remotos (nivel sitio) |
| `local/coursetransfer:origin_restore_course` / `_category` | Gestor, profesor editor / Gestor | Usar el asistente de restauración desde un curso / una categoría |
| `local/coursetransfer:origin_restore_course_users` | Gestor | Incluir usuarios y sus datos en la copia |
| `local/coursetransfer:origin_remove_course` / `_category` | Gestor | Borrar cursos / categorías en el origen |
| `local/coursetransfer:target_restore_*` | Gestor, profesor editor | Borrar contenido, matrículas o grupos del curso destino, o fusionar en él |
| `local/coursetransfer:view_logs` | Gestor | Ver el registro de ejecuciones |
| `local/coursetransfer:manage_requests` | Gestor (clonada de `view_logs`) | Borrar registros y reintentar una restauración |
| `local/coursetransfer:webservice` | Solo el rol del usuario de servicio | Llamar a los servicios que usan las plataformas emparejadas |

## 🖥️ Uso por línea de comandos (opcional)

Todos los scripts están en `local/coursetransfer/cli/` y admiten `--help` (o `-h`):

**Operaciones**

| Script | Qué hace |
|---|---|
| `restore_course.php` | Restaura un curso remoto en este sitio. |
| `restore_category.php` | Restaura una categoría remota completa (con su árbol de subcategorías y cursos). |
| `remove_course.php` | Borra un curso en el sitio de origen (remoto). |
| `remove_category.php` | Borra una categoría completa en el sitio de origen (remoto). |

**Consulta de registros**

| Script | Qué hace |
|---|---|
| `view_logs.php` | Lista peticiones filtradas por tipo, dirección, estado, usuario o fecha. |
| `view_log_request.php` | Muestra el detalle de una petición concreta (`--requestid=<N>`). |
| `view_log_request_activities_detail.php` | Muestra las secciones y actividades seleccionadas en una petición. |
| `view_log_origin_course.php` | Registros de un curso como **origen** (peticiones recibidas de otro Moodle). |
| `view_log_origin_category.php` | Registros de una categoría como **origen** (peticiones recibidas de otro Moodle). |
| `view_log_destiny_course.php` | Registros de restauraciones en un curso como **destino**. |
| `view_log_target_category.php` | Registros de restauraciones en una categoría como **destino**. |

```bash
# Ejemplos
php local/coursetransfer/cli/restore_course.php --help
php local/coursetransfer/cli/view_logs.php --help
php local/coursetransfer/cli/view_log_request.php --requestid=<N>
```

Códigos de salida: `0` correcto · `1` error de ejecución · `2` error de uso.

## 🗑️ Desinstalación

Se desinstala desde **Administración del sitio › Plugins › Vista general de
plugins**. Se eliminan las tablas y datos propios del plugin (peticiones y
registros de ejecución); los cursos y categorías ya restaurados **no** se ven
afectados.

## 🛠️ Desarrollo (opcional)

```bash
# Compilar los módulos JavaScript (AMD) tras editar amd/src/
grunt amd --root=local/coursetransfer

# Tests unitarios
vendor/bin/phpunit --testsuite local_coursetransfer_testsuite

# Tests de aceptación
vendor/bin/behat --tags @local_coursetransfer
```

## 🏛️ Créditos

**Un proyecto comunitario.** En octubre de 2022, las universidades de Valladolid, Complutense de Madrid, País Vasco/EHU, León, Salamanca, Illes Balears, València, Rey Juan Carlos, La Laguna, Zaragoza, Málaga, Córdoba, Extremadura, Vigo, Las Palmas y Burgos crearon un grupo interuniversitario para desarrollar nuevas herramientas que mejoren los campus virtuales basados en Moodle, en el marco de los fondos europeos Next Generation con el programa UNIDIGITAL.

**Programado por Tresipunt.** UNIMOODLE convocó un concurso abierto, y las empresas seleccionadas recibieron el encargo de crear componentes nuevos y de calidad en beneficio de toda la comunidad Moodle. Course Transfer lo desarrolló Tresipunt, Moodle Partner con 20 años de experiencia en desarrollo de aplicaciones y soluciones de negocio para grandes empresas nacionales e internacionales.

> **Este es un proyecto UNIMOODLE** — impulsando la comunidad Moodle desde la Universidad.

Créditos completos: [unimoodle.github.io/moodle-local_coursetransfer/credits.html](https://unimoodle.github.io/moodle-local_coursetransfer/credits.html)

## 📄 Licencia

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
