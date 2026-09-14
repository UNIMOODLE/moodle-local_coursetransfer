<!-- Versión en galego. English version: README.md -->
<p align="center">
  <img src="pix/logo.svg" alt="" width="280">
</p>

<h1 align="center">Course Transfer</h1>

<p align="center">
  <img src="https://img.shields.io/badge/version-2.1.0-informational" alt="Versión">
  <a href="https://moodle.org"><img src="https://img.shields.io/badge/Moodle-4.5%20--%205.1-orange?logo=moodle" alt="Moodle"></a>
  <img src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/License-GPL--3.0-green" alt="Licenza">
  <a href="https://unimoodle.github.io/"><img src="https://img.shields.io/badge/project%20by-UNIMOODLE-194866" alt="Proxecto de UNIMOODLE"></a>
  <a href="https://tresipunt.com"><img src="https://img.shields.io/badge/made%20by-Tresipunt-F84015" alt="Feito por Tresipunt"></a>
</p>

<p align="center"><b>Copia e borra cursos e categorías entre plataformas Moodle, sen exportar/importar a man.</b></p>

<p align="center"><a href="README.md">🇬🇧 English</a> · <a href="README.es.md">🇪🇸 Español</a> · <a href="README.ca.md">Català</a> · <b>Galego</b> · <a href="README.eu.md">Euskara</a></p>

Course Transfer conecta dous Moodle (orixe e destino) mediante servizos web e
permite **traer** cursos ou unha categoría completa desde outra plataforma, ou
**borralos** en remoto, con asistentes guiados e un rexistro de seguimento. Non
modifica o núcleo de Moodle nin o tema, e todo o traballo pesado (copia,
descarga e restauración) execútase en segundo plano mediante tarefas programadas.

---

## ✨ Que fai

- **Resumo e integración** — panel de inicio co **testemuño do servizo**
  (amosar/copiar/rexenerar/revogar) e comprobacións de saúde (testemuño, servizo
  REST, usuario de servizo, cron, plataformas) para saber dunha ollada se o
  emparellamento está listo.
- **Plataformas emparelladas** — alta e xestión dos sitios de orixe e de destino
  con **proba de conexión** por enderezo e estado do emparellamento.
- **Restaurar contido remoto** — asistente para traer **un curso** ou **unha
  categoría completa** (recreando a súa árbore de subcategorías e cursos) desde
  outra plataforma, escollendo a categoría de destino cun **buscador
  autocompletado**.
- **Borrar contido remoto** — asistente para eliminar cursos ou categorías no
  sitio de orixe, con confirmación obrigatoria e execución **programable** a unha
  data.
- **Rexistro de execucións** — peticións en curso e histórico, con **progreso**
  de descarga e restauración, filtros, **exportación** (CSV/Excel/ODS), papeleira
  e unha vista de **detalle** con liña de tempo e tarefas asociadas.
- **Automatización por CLI** — scripts para lanzar restauracións ou borrados e
  consultar os rexistros desde a liña de ordes.

## 💡 Casos de uso

- **Migración anual** — ao comezar o curso, traer os cursos ou unha categoría
  completa desde o Moodle de produción a unha instancia nova.
- **Consolidar nun curso existente** — un profesor restaura un curso remoto
  **sobre o seu curso actual** (modo fusión) para reutilizar contidos.
- **Clonar unha categoría** — recrear noutra plataforma unha árbore de
  categorías cos seus cursos, mantendo a xerarquía.
- **Limpeza de fin de curso** — **programar** o borrado de cursos ou categorías
  antigos no sitio de orixe para unha data concreta.
- **Copia entre contornos** — levar contidos dun contorno (p. ex. formación) a
  outro (p. ex. produción) sen exportar/importar ficheiros a man.

## ⚙️ Como funciona

- Dúas plataformas Moodle emparéllanse intercambiando o **URL** e o **testemuño**
  do servizo web de cada unha (o testemuño obtense na pantalla *Resumo*).
- Ao pedir unha restauración, a **orixe** xera unha copia de seguranza (`.mbz`)
  do curso ou da categoría e o **destino** descárgaa e restáuraa.
- O proceso é **asíncrono**: apóiase en tarefas adhoc e no **cron** de Moodle en
  ambos os dous lados, polo que o cron debe estar en marcha.
- O usuario resólvese por equivalencia entre plataformas (por `username`, `email`
  ou `idnumber`, configurable).
- **Non** modifica o núcleo nin o tema de Moodle; desinstálase como calquera
  outro complemento local.

## 📋 Requisitos

| Requisito | Versión |
|---|---|
| Moodle | 4.5+ (probado ata 5.1) |
| PHP | 8.1+ |
| Configuración | Servizos web **REST** habilitados e un **usuario de servizo** (o complemento axuda a crealo desde *Resumo*) |
| Outros complementos | Non require |

## 🚀 Instalación

1. Copiar o código en `local/coursetransfer/` (en Moodle 5.x:
   `public/local/coursetransfer/`).
2. Completar a instalación desde **Administración do sitio › Notificacións**
   (ou por CLI: `php admin/cli/upgrade.php --non-interactive`).
3. Purgar as cachés (**Administración do sitio › Desenvolvemento › Purgar
   cachés** ou `php admin/cli/purge_caches.php`).
4. Abrir a pantalla **Resumo** do complemento e premer **Crear testemuño** para
   preparar o servizo web e o usuario de servizo. Repetir a instalación na outra
   plataforma e dar de alta cada sitio en **Plataformas** co URL e o testemuño do
   outro extremo.

## 🔧 Axustes

En **Administración do sitio › Complementos › Locais › Course Transfer**
(`admin/settings.php?section=local_coursetransfer`):

| Axuste | Efecto |
|---|---|
| **Tamaño máximo do curso a restaurar** (`target_restore_course_max_size`) | Límite en MB da copia (`.mbz`) a restaurar; se se supera, a descarga falla e queda reflectido no rexistro. |
| **Tempo de espera da petición** (`request_timeout`) | Segundos de espera das chamadas entre plataformas. |
| **Ignorar a seguranza de cURL** (`ignorecurlsecurity`) | Permite operar contra sitios con certificados internos ou non verificables (usar con coidado). |
| **Resultados por páxina** (`pagesize`) | Número de resultados por páxina nos buscadores dos asistentes. |
| **Limpeza de tarefas adhoc fallidas** (`clean_adhoc_faildelay`) | Antigüidade a partir da cal se limpan as tarefas fallidas (`0` = desactivado). |
| **Baleirar a papeleira tras borrar un curso** (`remove_course_cleanup`) | Elimina definitivamente o curso borrado en remoto. |
| **Baleirar a papeleira tras borrar unha categoría** (`remove_cat_cleanup`) | Elimina definitivamente a categoría borrada en remoto. |
| **Campo de busca de usuario** (`origin_field_search_user`) | Campo polo que se emparella o usuario equivalente entre plataformas (`username` / `email` / `idnumber`). |

As **plataformas emparelladas** non se configuran aquí: xestiónanse desde a
pantalla **Plataformas** do complemento (con proba de conexión).

## 🖥️ Uso por liña de ordes (opcional)

Todos os scripts están en `local/coursetransfer/cli/` e admiten `--help` (ou `-h`):

**Operacións**

| Script | Que fai |
|---|---|
| `restore_course.php` | Restaura un curso remoto neste sitio. |
| `restore_category.php` | Restaura unha categoría remota completa (coa súa árbore de subcategorías e cursos). |
| `remove_course.php` | Borra un curso no sitio de orixe (remoto). |
| `remove_category.php` | Borra unha categoría completa no sitio de orixe (remoto). |

**Consulta de rexistros**

| Script | Que fai |
|---|---|
| `view_logs.php` | Lista peticións filtradas por tipo, dirección, estado, usuario ou data. |
| `view_log_request.php` | Amosa o detalle dunha petición concreta (`--requestid=<N>`). |
| `view_log_request_activities_detail.php` | Amosa as seccións e actividades seleccionadas nunha petición. |
| `view_log_origin_course.php` | Rexistros dun curso como **orixe** (peticións recibidas doutro Moodle). |
| `view_log_origin_category.php` | Rexistros dunha categoría como **orixe** (peticións recibidas doutro Moodle). |
| `view_log_destiny_course.php` | Rexistros de restauracións nun curso como **destino**. |
| `view_log_target_category.php` | Rexistros de restauracións nunha categoría como **destino**. |

```bash
# Exemplos
php local/coursetransfer/cli/restore_course.php --help
php local/coursetransfer/cli/view_logs.php --help
php local/coursetransfer/cli/view_log_request.php --requestid=<N>
```

Códigos de saída: `0` correcto · `1` erro de execución · `2` erro de uso.

## 🗑️ Desinstalación

Desinstálase desde **Administración do sitio › Complementos › Vista xeral de
complementos**. Elimínanse as táboas e datos propios do complemento (peticións e
rexistros de execución); os cursos e categorías xa restaurados **non** se ven
afectados.

## 🛠️ Desenvolvemento (opcional)

```bash
# Compilar os módulos JavaScript (AMD) tras editar amd/src/
grunt amd --root=local/coursetransfer

# Probas unitarias
vendor/bin/phpunit --testsuite local_coursetransfer_testsuite

# Probas de aceptación
vendor/bin/behat --tags @local_coursetransfer
```

## 🏛️ Créditos

**Un proxecto comunitario.** En outubro de 2022, as universidades de Valladolid, Complutense de Madrid, País Vasco/EHU, León, Salamanca, Illes Balears, València, Rey Juan Carlos, La Laguna, Zaragoza, Málaga, Córdoba, Extremadura, Vigo, Las Palmas e Burgos crearon un grupo interuniversitario para desenvolver novas ferramentas que melloren os campus virtuais baseados en Moodle, no marco dos fondos europeos Next Generation co programa UNIDIGITAL.

**Programado por Tresipunt.** UNIMOODLE convocou un concurso aberto, e as empresas seleccionadas recibiron o encargo de crear compoñentes novos e de calidade en beneficio de toda a comunidade Moodle. Course Transfer desenvolveuno Tresipunt, Moodle Partner con 20 anos de experiencia en desenvolvemento de aplicacións e solucións de negocio para grandes empresas nacionais e internacionais.

> **Este é un proxecto UNIMOODLE** — impulsando a comunidade Moodle desde a Universidade.

Créditos completos: [unimoodle.github.io/moodle-local_coursetransfer/credits.html](https://unimoodle.github.io/moodle-local_coursetransfer/credits.html)

## 📄 Licenza

[GNU GPL v3 or later](https://www.gnu.org/copyleft/gpl.html) — 2023 [Tresipunt](https://tresipunt.com) (contacte@tresipunt.com)

---

<p align="center">
  <a href="https://unimoodle.github.io/"><img src="pix/unimoodle_logo.png" alt="UNIMOODLE" width="220"></a>
</p>

<p align="center">
  <a href="https://tresipunt.com"><img src="pix/tresipunt_logo.svg" alt="Tresipunt" width="150"></a>
</p>
