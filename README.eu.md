<!-- Euskarazko bertsioa. English version: README.md -->
<p align="center">
  <img src="pix/logo.svg" alt="" width="280">
</p>

<h1 align="center">Course Transfer</h1>

<p align="center">
  <img src="https://img.shields.io/badge/version-2.1.0-informational" alt="Bertsioa">
  <a href="https://moodle.org"><img src="https://img.shields.io/badge/Moodle-4.5%20--%205.1-orange?logo=moodle" alt="Moodle"></a>
  <img src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/License-GPL--3.0-green" alt="Lizentzia">
  <a href="https://unimoodle.github.io/"><img src="https://img.shields.io/badge/project%20by-UNIMOODLE-194866" alt="UNIMOODLE proiektua"></a>
  <a href="https://tresipunt.com"><img src="https://img.shields.io/badge/made%20by-Tresipunt-F84015" alt="Tresipunt-ek egina"></a>
</p>

<p align="center"><b>Kopiatu eta ezabatu ikastaroak eta kategoriak Moodle plataformen artean, eskuz esportatu/inportatu gabe.</b></p>

<p align="center"><a href="README.md">🇬🇧 English</a> · <a href="README.es.md">🇪🇸 Español</a> · <a href="README.ca.md">Català</a> · <a href="README.gl.md">Galego</a> · <b>Euskara</b></p>

Course Transfer-ek bi Moodle (jatorria eta helmuga) lotzen ditu web zerbitzuen
bidez, eta aukera ematen du beste plataforma batetik ikastaroak edo kategoria oso
bat **ekartzeko**, edo urrunetik **ezabatzeko**, laguntzaile gidatuen eta
jarraipen-erregistro baten bidez. Ez du Moodle-ren muina ez gaia aldatzen, eta
lan astun guztia (kopia, deskarga eta leheneratzea) atzeko planoan egiten da,
programatutako atazen bidez.

---

## ✨ Zer egiten duen

- **Laburpena eta integrazioa** — hasierako panela, **zerbitzuaren tokenarekin**
  (erakutsi/kopiatu/birsortu/baliogabetu) eta egoera-egiaztapenekin (tokena, REST
  zerbitzua, zerbitzu-erabiltzailea, cron-a, plataformak), parekatzea prest
  dagoen begirada batean jakiteko.
- **Parekatutako plataformak** — jatorrizko eta helmugako guneak alta ematea eta
  kudeatzea, norabide bakoitzeko **konexio-probarekin** eta parekatzearen
  egoerarekin.
- **Urruneko edukia leheneratu** — laguntzailea beste plataforma batetik
  **ikastaro bat** edo **kategoria oso bat** ekartzeko (haren azpikategoria eta
  ikastaroen zuhaitza birsortuz), helmugako kategoria **bilatzaile
  osatzailearekin** aukeratuta.
- **Urruneko edukia ezabatu** — laguntzailea jatorrizko gunean ikastaroak edo
  kategoriak ezabatzeko, nahitaezko berrespenarekin eta data jakin baterako
  **programa daitekeen** exekuzioarekin.
- **Exekuzioen erregistroa** — abian diren eskaerak eta historikoa, deskargaren
  eta leheneratzearen **aurrerapenarekin**, iragazkiekin, **esportazioarekin**
  (CSV/Excel/ODS), zakarrontziarekin eta **xehetasun**-ikuspegi batekin, denbora
  lerroa eta lotutako atazak barne.
- **CLI bidezko automatizazioa** — leheneratzeak edo ezabatzeak abiarazteko eta
  erregistroak kontsultatzeko script-ak, komando-lerrotik.

## 💡 Erabilera-kasuak

- **Urteko migrazioa** — ikasturtea hastean, ikastaroak edo kategoria oso bat
  ekartzea produkzioko Moodle-tik instantzia berri batera.
- **Lehendik dagoen ikastaro batean bateratzea** — irakasle batek urruneko
  ikastaro bat **bere uneko ikastaroaren gainean** leheneratzen du (bat-egite
  modua), edukiak berrerabiltzeko.
- **Kategoria bat klonatzea** — beste plataforma batean kategoria-zuhaitz bat
  bere ikastaroekin birsortzea, hierarkia mantenduz.
- **Ikasturte amaierako garbiketa** — jatorrizko gunean ikastaro edo kategoria
  zaharrak ezabatzea **programatzea** data jakin baterako.
- **Inguruneen arteko kopia** — edukiak ingurune batetik (adib. prestakuntza)
  bestera (adib. produkzioa) eramatea, fitxategiak eskuz esportatu/inportatu
  gabe.

## ⚙️ Nola funtzionatzen duen

- Bi Moodle plataforma parekatzen dira bakoitzaren web zerbitzuaren **URLa** eta
  **tokena** trukatuz (tokena *Laburpena* pantailan lortzen da).
- Leheneratze bat eskatzean, **jatorriak** ikastaroaren edo kategoriaren
  segurtasun-kopia (`.mbz`) sortzen du, eta **helmugak** deskargatu eta
  leheneratzen du.
- Prozesua **asinkronoa** da: adhoc atazetan eta Moodle-ren **cron**-ean
  oinarritzen da bi aldeetan, beraz cron-ak martxan egon behar du.
- Erabiltzailea plataformen arteko baliokidetasunaren bidez ebazten da
  (`username`, `email` edo `idnumber` bidez, konfiguragarria).
- **Ez** du Moodle-ren muina ez gaia aldatzen; beste edozein plugin lokal bezala
  desinstalatzen da.

## 📋 Betekizunak

| Betekizuna | Bertsioa |
|---|---|
| Moodle | 4.5+ (5.1 arte probatuta) |
| PHP | 8.1+ |
| Konfigurazioa | **REST** web zerbitzuak gaituta eta **zerbitzu-erabiltzaile** bat (pluginak *Laburpena* pantailatik sortzen laguntzen du) |
| Beste pluginak | Ez du behar |

## 🚀 Instalazioa

1. Kopiatu kodea `local/coursetransfer/` karpetan (Moodle 5.x-en:
   `public/local/coursetransfer/`).
2. Osatu instalazioa **Gunearen administrazioa › Jakinarazpenak** ataletik (edo
   CLI bidez: `php admin/cli/upgrade.php --non-interactive`).
3. Hustu cacheak (**Gunearen administrazioa › Garapena › Hustu cacheak** edo
   `php admin/cli/purge_caches.php`).
4. Ireki pluginaren **Laburpena** pantaila eta sakatu **Sortu tokena**, web
   zerbitzua eta zerbitzu-erabiltzailea prestatzeko. Errepikatu instalazioa beste
   plataforman eta eman alta gune bakoitza **Plataformak** atalean, beste
   muturraren URLarekin eta tokenarekin.

## 🔧 Ezarpenak

**Gunearen administrazioa › Pluginak › Lokalak › Course Transfer** atalean
(`admin/settings.php?section=local_coursetransfer`):

| Ezarpena | Eragina |
|---|---|
| **Leheneratzeko ikastaroaren gehienezko tamaina** (`target_restore_course_max_size`) | Leheneratu beharreko kopiaren (`.mbz`) MB muga; gainditzen bada, deskargak huts egiten du eta erregistroan jasotzen da. |
| **Eskaeraren itxaron-denbora** (`request_timeout`) | Plataformen arteko deien itxaron-segundoak. |
| **Ez ikusi cURL segurtasuna** (`ignorecurlsecurity`) | Barne-ziurtagiriak edo egiaztaezinak dituzten guneekin lan egitea ahalbidetzen du (kontuz erabili). |
| **Emaitzak orriko** (`pagesize`) | Laguntzaileen bilatzaileetan orriko agertzen diren emaitza kopurua. |
| **Huts egindako adhoc atazen garbiketa** (`clean_adhoc_faildelay`) | Zenbat denbora igarota garbitzen diren huts egindako atazak (`0` = desaktibatuta). |
| **Hustu zakarrontzia ikastaroa ezabatu ondoren** (`remove_course_cleanup`) | Urrunetik ezabatutako ikastaroa behin betiko kentzen du. |
| **Hustu zakarrontzia kategoria ezabatu ondoren** (`remove_cat_cleanup`) | Urrunetik ezabatutako kategoria behin betiko kentzen du. |
| **Erabiltzailea bilatzeko eremua** (`origin_field_search_user`) | Plataformen artean erabiltzaile baliokidea parekatzeko erabiltzen den eremua (`username` / `email` / `idnumber`). |

**Parekatutako plataformak** ez dira hemen konfiguratzen: pluginaren
**Plataformak** pantailatik kudeatzen dira (konexio-probarekin).

## 🖥️ Komando-lerroko erabilera (aukerakoa)

Script guztiak `local/coursetransfer/cli/` karpetan daude eta `--help` (edo `-h`)
onartzen dute:

**Eragiketak**

| Script-a | Zer egiten duen |
|---|---|
| `restore_course.php` | Urruneko ikastaro bat leheneratzen du gune honetan. |
| `restore_category.php` | Urruneko kategoria oso bat leheneratzen du (bere azpikategoria eta ikastaroen zuhaitzarekin). |
| `remove_course.php` | Ikastaro bat ezabatzen du jatorrizko gunean (urrunekoa). |
| `remove_category.php` | Kategoria oso bat ezabatzen du jatorrizko gunean (urrunekoa). |

**Erregistroen kontsulta**

| Script-a | Zer egiten duen |
|---|---|
| `view_logs.php` | Eskaerak zerrendatzen ditu motaren, norabidearen, egoeraren, erabiltzailearen edo dataren arabera iragazita. |
| `view_log_request.php` | Eskaera zehatz baten xehetasuna erakusten du (`--requestid=<N>`). |
| `view_log_request_activities_detail.php` | Eskaera batean hautatutako atalak eta jarduerak erakusten ditu. |
| `view_log_origin_course.php` | Ikastaro baten erregistroak **jatorri** gisa (beste Moodle batetik jasotako eskaerak). |
| `view_log_origin_category.php` | Kategoria baten erregistroak **jatorri** gisa (beste Moodle batetik jasotako eskaerak). |
| `view_log_destiny_course.php` | Ikastaro batean **helmuga** gisa egindako leheneratzeen erregistroak. |
| `view_log_target_category.php` | Kategoria batean **helmuga** gisa egindako leheneratzeen erregistroak. |

```bash
# Adibideak
php local/coursetransfer/cli/restore_course.php --help
php local/coursetransfer/cli/view_logs.php --help
php local/coursetransfer/cli/view_log_request.php --requestid=<N>
```

Irteera-kodeak: `0` zuzena · `1` exekuzio-errorea · `2` erabilera-errorea.

## 🗑️ Desinstalazioa

**Gunearen administrazioa › Pluginak › Pluginen ikuspegi orokorra** ataletik
desinstalatzen da. Pluginaren taulak eta datuak ezabatzen dira (eskaerak eta
exekuzio-erregistroak); dagoeneko leheneratutako ikastaroek eta kategoriek
**ez** dute eraginik jasotzen.

## 🛠️ Garapena (aukerakoa)

```bash
# JavaScript moduluak (AMD) konpilatu amd/src/ editatu ondoren
grunt amd --root=local/coursetransfer

# Unitate-probak
vendor/bin/phpunit --testsuite local_coursetransfer_testsuite

# Onarpen-probak
vendor/bin/behat --tags @local_coursetransfer
```

## 🏛️ Kredituak

**Komunitate-proiektua.** 2022ko urrian, Valladolid, Madrilgo Complutense, Euskal Herriko Unibertsitatea/EHU, Leon, Salamanca, Illes Balears, Valentzia, Rey Juan Carlos, La Laguna, Zaragoza, Malaga, Kordoba, Extremadura, Vigo, Las Palmas eta Burgosko unibertsitateek unibertsitate arteko talde bat sortu zuten, Moodlen oinarritutako campus birtualak hobetzeko tresna berriak garatzeko, Europako Next Generation funtsen barruan eta UNIDIGITAL programarekin.

**Tresipunt-ek programatua.** UNIMOODLEk lehiaketa irekia egin zuen, eta hautatutako enpresek osagai berriak eta kalitatezkoak sortzeko ardura hartu zuten, Moodle komunitate osoaren mesedetan. Course Transfer Tresipunt-ek garatu du: Moodle Partner bat, aplikazioen garapenean eta enpresa handi nazional eta nazioartekoentzako negozio-soluzioetan 20 urteko esperientzia duena.

> **UNIMOODLE proiektu bat da hau** — Moodle komunitatea unibertsitatetik bultzatuz.

Kreditu osoak: [unimoodle.github.io/moodle-local_coursetransfer/credits.html](https://unimoodle.github.io/moodle-local_coursetransfer/credits.html)

## 📄 Lizentzia

[GNU GPL v3 or later](https://www.gnu.org/copyleft/gpl.html) — 2023 [Tresipunt](https://tresipunt.com) (contacte@tresipunt.com)

---

<p align="center">
  <a href="https://unimoodle.github.io/"><img src="pix/unimoodle_logo.png" alt="UNIMOODLE" width="220"></a>
</p>

<p align="center">
  <a href="https://tresipunt.com"><img src="pix/tresipunt_logo.svg" alt="Tresipunt" width="150"></a>
</p>
