<!-- Versió en català. English version: README.md -->
<p align="center">
  <img src="pix/logo.svg" alt="" width="280">
</p>

<h1 align="center">Course Transfer</h1>

<p align="center">
  <img src="https://img.shields.io/badge/version-2.1.0-informational" alt="Versió">
  <a href="https://moodle.org"><img src="https://img.shields.io/badge/Moodle-4.5%20--%205.1-orange?logo=moodle" alt="Moodle"></a>
  <img src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/License-GPL--3.0-green" alt="Llicència">
  <a href="https://unimoodle.github.io/"><img src="https://img.shields.io/badge/project%20by-UNIMOODLE-194866" alt="Projecte d'UNIMOODLE"></a>
  <a href="https://tresipunt.com"><img src="https://img.shields.io/badge/made%20by-Tresipunt-F84015" alt="Fet per Tresipunt"></a>
</p>

<p align="center"><b>Copia i esborra cursos i categories entre plataformes Moodle, sense exportar/importar a mà.</b></p>

<p align="center"><a href="README.md">🇬🇧 English</a> · <a href="README.es.md">🇪🇸 Español</a> · <b>Català</b> · <a href="README.gl.md">Galego</a> · <a href="README.eu.md">Euskara</a></p>

Course Transfer connecta dos Moodle (origen i destinació) mitjançant serveis web
i permet **portar** cursos o una categoria completa des d'una altra plataforma, o
**esborrar-los** en remot, amb assistents guiats i un registre de seguiment. No
modifica el nucli de Moodle ni el tema, i tota la feina pesada (còpia, descàrrega
i restauració) s'executa en segon pla mitjançant tasques programades.

---

## ✨ Què fa

- **Resum i integració** — tauler d'inici amb el **testimoni del servei**
  (mostrar/copiar/regenerar/revocar) i comprovacions de salut (testimoni, servei
  REST, usuari de servei, cron, plataformes) per saber d'un cop d'ull si
  l'aparellament està a punt.
- **Plataformes aparellades** — alta i gestió dels llocs d'origen i de
  destinació amb **prova de connexió** per adreça i estat de l'aparellament.
- **Restaurar contingut remot** — assistent per portar **un curs** o **una
  categoria completa** (recreant-ne l'arbre de subcategories i cursos) des d'una
  altra plataforma, triant la categoria de destinació amb **cercador
  autocompletat**.
- **Esborrar contingut remot** — assistent per eliminar cursos o categories al
  lloc d'origen, amb confirmació obligatòria i execució **programable** a una
  data.
- **Registre d'execucions** — peticions en curs i històric, amb **progrés** de
  descàrrega/restauració, filtres, **exportació** (CSV/Excel/ODS), paperera i
  una vista de **detall** amb línia de temps i tasques associades.
- **Automatització per CLI** — scripts per llançar restauracions o esborraments
  i consultar els registres des de la línia d'ordres.

## 💡 Casos d'ús

- **Migració anual** — en començar el curs, portar els cursos o una categoria
  completa des del Moodle de producció a una instància nova.
- **Consolidar en un curs existent** — un professor restaura un curs remot
  **sobre el seu curs actual** (mode fusió) per reutilitzar continguts.
- **Clonar una categoria** — recrear en una altra plataforma un arbre de
  categories amb els seus cursos, mantenint-ne la jerarquia.
- **Neteja de final de curs** — **programar** l'esborrament de cursos o
  categories antics al lloc d'origen per a una data concreta.
- **Còpia entre entorns** — portar continguts d'un entorn (p. ex. formació) a un
  altre (p. ex. producció) sense exportar/importar fitxers a mà.

## ⚙️ Com funciona

- Dues plataformes Moodle s'aparellen intercanviant l'**URL** i el **testimoni**
  del servei web de cadascuna (el testimoni s'obté a la pantalla *Resum*).
- Quan es demana una restauració, l'**origen** genera una còpia de seguretat
  (`.mbz`) del curs o la categoria i la **destinació** la descarrega i la
  restaura.
- El procés és **asíncron**: es recolza en tasques adhoc i en el **cron** de
  Moodle a banda i banda, de manera que el cron ha d'estar en marxa.
- L'usuari es resol per equivalència entre plataformes (per `username`, `email`
  o `idnumber`, configurable).
- **No** modifica el nucli ni el tema de Moodle; es desinstal·la com qualsevol
  altre connector local.

## 📋 Requisits

| Requisit | Versió |
|---|---|
| Moodle | 4.5+ (provat fins a 5.1) |
| PHP | 8.1+ |
| Configuració | Serveis web **REST** habilitats i un **usuari de servei** (el connector ajuda a crear-lo des de *Resum*) |
| Altres connectors | No en requereix |

## 🚀 Instal·lació

1. Copiar el codi a `local/coursetransfer/` (a Moodle 5.x:
   `public/local/coursetransfer/`).
2. Completar la instal·lació des d'**Administració del lloc › Notificacions**
   (o per CLI: `php admin/cli/upgrade.php --non-interactive`).
3. Buidar les memòries cau (**Administració del lloc › Desenvolupament › Buida
   les memòries cau** o `php admin/cli/purge_caches.php`).
4. Obrir la pantalla **Resum** del connector i prémer **Crea el testimoni** per
   preparar el servei web i l'usuari de servei. Repetir la instal·lació a
   l'altra plataforma i donar d'alta cada lloc a **Plataformes** amb l'URL i el
   testimoni de l'altre extrem.

## 🔧 Configuració

A **Administració del lloc › Connectors › Locals › Course Transfer**
(`admin/settings.php?section=local_coursetransfer`):

| Paràmetre | Efecte |
|---|---|
| **Mida màxima del curs a restaurar** (`target_restore_course_max_size`) | Límit en MB de la còpia (`.mbz`) a restaurar; si se supera, la descàrrega falla i queda reflectit al registre. |
| **Temps d'espera de la petició** (`request_timeout`) | Segons d'espera de les crides entre plataformes. |
| **Ignora la seguretat de cURL** (`ignorecurlsecurity`) | Permet operar contra llocs amb certificats interns o no verificables (fer-ne un ús prudent). |
| **Resultats per pàgina** (`pagesize`) | Nombre de resultats per pàgina als cercadors dels assistents. |
| **Neteja de tasques adhoc fallides** (`clean_adhoc_faildelay`) | Antiguitat a partir de la qual es netegen les tasques fallides (`0` = desactivat). |
| **Buida la paperera en esborrar un curs** (`remove_course_cleanup`) | Elimina definitivament el curs esborrat en remot. |
| **Buida la paperera en esborrar una categoria** (`remove_cat_cleanup`) | Elimina definitivament la categoria esborrada en remot. |
| **Camp de cerca d'usuari** (`origin_field_search_user`) | Camp pel qual s'aparella l'usuari equivalent entre plataformes (`username` / `email` / `idnumber`). |

Les **plataformes aparellades** no es configuren aquí: es gestionen des de la
pantalla **Plataformes** del connector (amb prova de connexió).

## 🖥️ Ús per línia d'ordres (opcional)

Tots els scripts són a `local/coursetransfer/cli/` i admeten `--help` (o `-h`):

**Operacions**

| Script | Què fa |
|---|---|
| `restore_course.php` | Restaura un curs remot en aquest lloc. |
| `restore_category.php` | Restaura una categoria remota completa (amb el seu arbre de subcategories i cursos). |
| `remove_course.php` | Esborra un curs al lloc d'origen (remot). |
| `remove_category.php` | Esborra una categoria completa al lloc d'origen (remot). |

**Consulta de registres**

| Script | Què fa |
|---|---|
| `view_logs.php` | Llista peticions filtrades per tipus, direcció, estat, usuari o data. |
| `view_log_request.php` | Mostra el detall d'una petició concreta (`--requestid=<N>`). |
| `view_log_request_activities_detail.php` | Mostra les seccions i activitats seleccionades en una petició. |
| `view_log_origin_course.php` | Registres d'un curs com a **origen** (peticions rebudes d'un altre Moodle). |
| `view_log_origin_category.php` | Registres d'una categoria com a **origen** (peticions rebudes d'un altre Moodle). |
| `view_log_destiny_course.php` | Registres de restauracions en un curs com a **destinació**. |
| `view_log_target_category.php` | Registres de restauracions en una categoria com a **destinació**. |

```bash
# Exemples
php local/coursetransfer/cli/restore_course.php --help
php local/coursetransfer/cli/view_logs.php --help
php local/coursetransfer/cli/view_log_request.php --requestid=<N>
```

Codis de sortida: `0` correcte · `1` error d'execució · `2` error d'ús.

## 🗑️ Desinstal·lació

Es desinstal·la des d'**Administració del lloc › Connectors › Vista general de
connectors**. S'eliminen les taules i dades pròpies del connector (peticions i
registres d'execució); els cursos i categories ja restaurats **no** es veuen
afectats.

## 🛠️ Desenvolupament (opcional)

```bash
# Compilar els mòduls JavaScript (AMD) després d'editar amd/src/
grunt amd --root=local/coursetransfer

# Proves unitàries
vendor/bin/phpunit --testsuite local_coursetransfer_testsuite

# Proves d'acceptació
vendor/bin/behat --tags @local_coursetransfer
```

## 🏛️ Crèdits

**Un projecte comunitari.** L'octubre de 2022, les universitats de Valladolid, Complutense de Madrid, País Basc/EHU, Lleó, Salamanca, Illes Balears, València, Rey Juan Carlos, La Laguna, Saragossa, Màlaga, Còrdova, Extremadura, Vigo, Las Palmas i Burgos van crear un grup interuniversitari per desenvolupar noves eines que millorin els campus virtuals basats en Moodle, en el marc dels fons europeus Next Generation amb el programa UNIDIGITAL.

**Programat per Tresipunt.** UNIMOODLE va convocar un concurs obert, i les empreses seleccionades van rebre l'encàrrec de crear components nous i de qualitat en benefici de tota la comunitat Moodle. Course Transfer el va desenvolupar Tresipunt, Moodle Partner amb 20 anys d'experiència en desenvolupament d'aplicacions i solucions de negoci per a grans empreses nacionals i internacionals.

> **Aquest és un projecte UNIMOODLE** — impulsant la comunitat Moodle des de la Universitat.

Crèdits complets: [unimoodle.github.io/moodle-local_coursetransfer/credits.html](https://unimoodle.github.io/moodle-local_coursetransfer/credits.html)

## 📄 Llicència

[GNU GPL v3 or later](https://www.gnu.org/copyleft/gpl.html) — 2023 [Tresipunt](https://tresipunt.com) (contacte@tresipunt.com)

---

<p align="center">
  <a href="https://unimoodle.github.io/"><img src="pix/unimoodle_logo.png" alt="UNIMOODLE" width="220"></a>
</p>

<p align="center">
  <a href="https://tresipunt.com"><img src="pix/tresipunt_logo.svg" alt="Tresipunt" width="150"></a>
</p>
