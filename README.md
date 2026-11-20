<!-- English version. Versión en español: README.es.md -->
<p align="center">
  <img src="pix/logo.svg" alt="" width="280">
</p>

<h1 align="center">Course Transfer</h1>

<p align="center">
  <img src="https://img.shields.io/badge/version-2.2.0-informational" alt="Version">
  <a href="https://moodle.org"><img src="https://img.shields.io/badge/Moodle-4.5%20--%205.3-orange?logo=moodle" alt="Moodle"></a>
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
- **Data-loss protection** — the origin is only removed after a complete and
  verified restore, removed courses stay in the recycle bin for a configurable
  time, and every removal between platforms is recorded as an event (see below).

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
- The services used between platforms can only be called with the other
  site's **service user token** (capability `local/coursetransfer:webservice`; in 2.2 a token user without it is still accepted with a deprecation warning, 2.3 will reject it),
  never from the browser. Permissions are then checked on the equivalent user:
  the origin only lists and backs up what that user could back up by hand.
- Calls between platforms verify the **TLS certificate** of the other site.
- It does **not** modify the Moodle core or theme; it uninstalls like any other
  local plugin.

## 📋 Requirements

| Requirement | Version |
|---|---|
| Moodle | 4.5, 5.1 or 5.3 |
| PHP | 8.1+ |
| Configuration | **REST** web services enabled and a **service user** (the plugin creates it from *Summary*) |
| Cron | Running on **both** platforms: backups, downloads, restores, removals and cleanups are tasks |
| TLS | A valid certificate on every paired platform: self-signed certificates or incomplete chains are rejected while `sslverifypeer` is on |
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
| **Allow connections to internal hosts** (`ignorecurlsecurity`) | Off by default. While on, Moodle's blocked hosts and ports (`curlsecurityblockedhosts`) do not apply to the calls between platforms. Prefer leaving it off and allowing only the paired hosts in that list. It does not disable the certificate check. |
| **Verify the certificate of the paired platforms** (`sslverifypeer`) | On by default. Turning it off sends the token to peers whose identity is not checked: only at the administrator's own risk. The connection test explains when a certificate cannot be verified. |
| **Results per page** (`pagesize`) | Number of results per page in the wizard search boxes. |
| **Failed adhoc task cleanup** (`clean_adhoc_faildelay`) | Age from which failed tasks are cleaned up (`0` = disabled). |
| **Empty trash after deleting a course** (`remove_course_cleanup`) | Permanently removes the course deleted remotely. |
| **Empty trash after deleting a category** (`remove_cat_cleanup`) | Permanently removes the category deleted remotely. |
| **User search field** (`origin_field_search_user`) | Field used to match the equivalent user across platforms (`username` / `email` / user ID / `idnumber`). Changing it repairs the integration. |
| **Check the restored course before removing the origin** (`verifymanifest`) | On by default. See *Data-loss protection*. |
| **Days in the recycle bin before the definitive deletion** (`recyclebinretentiondays`) | Default 30, minimum 1. See *Data-loss protection*. |
| **Days to keep transferred backups** (`backup_files_retention`) | Default 7, minimum 1. Backups served as origin and safety copies kept as target are deleted this many days after their transfer finished. |

**Paired platforms** are not configured here: they are managed from the plugin's
**Platforms** screen (with a connection test), and only by administrators
(`moodle/site:config`). A platform URL is a plain base URL,
`http(s)://host[:port][/path]`, and must match the other site's `wwwroot`.

## 🛡️ Data-loss protection

Removing the origin after a restore (course or category) is the only operation
that can lose a course. These safeguards apply:

| Safeguard | What it protects | How to configure it |
|---|---|---|
| **Complete restore** | The origin is kept if the restore plan reports missing modules or files (error `10410`). A category is removed only when every course of it was restored without issues, the requester could see all its subcategories on the origin, and the origin category has no new courses since the restore started. | Always on |
| **Manifest check** | Before removing an origin course, its sections and activities per module (asked of the origin) and, with users, the enrolled users (read from the backup) are compared with the restored course. If anything is missing, the origin is kept and the log says what (`10412`). | `verifymanifest` (on) |
| **Requester's permissions** | The removal runs as the user who asked for it, on both sites: `local/coursetransfer:origin_remove_course` / `_category` here, and `moodle/course:delete` / `moodle/category:manage` plus the same capability on the origin. | Roles |
| **Safety copy** | Before a restore that may remove the origin, the target keeps a copy of the backup. | `backup_files_retention` (never purged before its origin is removed plus the recycle bin retention; kept when the restore was incomplete) |
| **Recycle bin** | With *Definitive deletion of course* on, a course removed at the request of another platform stays in the recycle bin for the configured days before it is purged, so it can still be restored by hand. Needs the category recycle bin of `tool_recyclebin`; removing a whole category always empties its bin (Moodle core). If the core recycle bin expires items sooner (7 days by default), the setting and the Summary diagnosis warn about it. | `recyclebinretentiondays` (30) |
| **Audit** | Every removal asked of or done for another platform, and every decision to remove or keep the origin after a restore, is logged as an event (*Site administration › Reports › Logs*). | Always on |

When the origin is kept, the request still shows the restore as completed, with
the reason in its detail; the origin can then be removed with the *Delete remote
content* wizard once checked.

## 🔐 Permissions

| Capability | Default | Lets the user |
|---|---|---|
| `local/coursetransfer:origin_restore` | — | Restore remote courses or categories (site level) |
| `local/coursetransfer:origin_restore_course` / `_category` | Manager, editing teacher / Manager | Use the restore wizard from a course / category |
| `local/coursetransfer:origin_restore_course_users` | Manager | Include users and their data in the backup |
| `local/coursetransfer:origin_remove_course` / `_category` | Manager | Remove courses / categories on the origin |
| `local/coursetransfer:target_restore_*` | Manager, editing teacher | Delete content, enrolments or groups of the target course, or merge into it |
| `local/coursetransfer:view_logs` | Manager | See the execution log |
| `local/coursetransfer:manage_requests` | Manager (cloned from `view_logs`) | Delete log entries and retry a restore |
| `local/coursetransfer:webservice` | Only the service user role | Call the services used by peer platforms |

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
