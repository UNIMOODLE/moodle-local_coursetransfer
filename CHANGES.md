# Changelog — local_coursetransfer

All notable changes to this plugin are documented here.

## 2.2.0 — 2026-11-20 (`2026112001`)

Security and data-integrity release, and **Moodle 5.3 support**. Supported:
Moodle 4.5, 5.1 and 5.3 (`requires` 4.5, `supported = [405, 503]`). **Read the
breaking changes and the upgrade notes before upgrading**: several of them can
stop a pairing that works today, and both platforms of a pairing should be
upgraded (see "Mixed versions").

### ⚠️ Breaking changes

- **The services used between platforms are no longer callable from the
  browser (AJAX)**, and calling them with a web service token will require the
  new capability `local/coursetransfer:webservice`. This covers every
  `origin_*` and `target_*` function, `site_origin_test`, `site_target_test` and
  `get_category_idnumber`. Only the plugin's service user role
  (`local_coursetransfer_ws`) gets the capability: the upgrade assigns it to the
  existing role and makes the role assignable at system level. **In 2.2 a token
  user without the capability is still accepted**, with a deprecation warning in
  the log and in the platform diagnosis; **2.3 will reject it**. If you see the
  warning (typically the service user or its role was recreated or edited by
  hand), open the plugin's **Summary** screen and click **Repair**, or assign the
  capability to the role yourself.
- **Registering, editing, removing and testing platforms requires
  `moodle/site:config`.** Before, any user with access to the AJAX services
  could do it.
- **A platform URL must be `http(s)://host[:port][/path]`**: query strings,
  fragments, credentials and whitespace are rejected (error `18045`), and the URL
  is limited to 255 characters. Existing rows are not rewritten, but they cannot
  be saved again until they match.
- **TLS certificates are verified** on every call to a paired platform and on the
  backup download (new setting `sslverifypeer`, on by default). A peer with a
  self-signed certificate or an incomplete chain stops connecting, and the
  connection test says so explicitly. Install a valid certificate on the peer (or
  its CA in the PHP/cURL trust store of this site); turning `sslverifypeer` off is
  possible, at the administrator's own risk: the token is then sent to a peer
  whose identity is not checked.
- **`ignorecurlsecurity` is off by default on new installations.** Upgraded
  sites keep their current value, which was `1` by default: review it. While on,
  Moodle's `curlsecurityblockedhosts` does not apply to the calls between
  platforms; the safer setup is to leave it off and allow only the paired hosts.
- **The equivalent user no longer sees hidden courses or categories they could
  not back up by hand.** Lists and details on the origin only include courses
  where the user has `moodle/backup:backupcourse` (and
  `moodle/course:viewhiddencourses` for a hidden course), and categories visible
  to the user where they have that capability. A detail of something else is
  refused with `22002`, `23002` or `24002`.
- **Status callbacks are checked against the request.** Each callback only
  applies to a request made from this site, of its type, and from the states
  where the transition makes sense; a repeated call is answered with success and
  changes nothing. Anything else is rejected (`10302`, `14003`, `14013`, `14023`).
- **The origin is only removed after a verified restore.** A restore that ends
  with missing modules or files (`10410`), or whose restored course does not
  match the origin (`10412`, see `verifymanifest`), completes but keeps the
  origin (`10411`). The same applies to a category removal: it only runs when
  every course of the category was restored without issues, when the requester
  could see every subcategory of it on the origin, and when the origin category
  has no new courses since the restore started; otherwise it is held. The
  removal runs as the user who requested the restore and checks their
  capabilities, not the cron's.
- **Removals asked by another platform check the requester on the origin**:
  `moodle/course:delete` (or `moodle/category:manage`) plus
  `local/coursetransfer:origin_remove_course` (or `_category`). Without them the
  removal is refused with `15002` / `19002`.
- **Users included in a backup require `local/coursetransfer:origin_restore_course_users`**
  in every wizard and service, and the backup keeps the locks the origin applies
  to the user (`moodle/backup:userinfo`), as the manual backup form does.
- **Deleting execution logs and retrying a restore require the new
  `local/coursetransfer:manage_requests`** instead of `view_logs`. It is cloned
  from `view_logs` on upgrade, so roles that could do it keep it.
- **`coursetransfer::validate_origin_site()` is removed.** It always returned
  `true` and nothing in the plugin used it.

### Added

- **Moodle 5.3 support.** The service user is created with
  `\core\user::create_user()` where it exists (MDL-82650), and the CLI scripts run
  as the site administrator, so they work with `forcelogin`, on by default in new
  5.3 sites.
- **Protection against data loss** (new settings section):
  - **Check the restored course before removing the origin**
    (`verifymanifest`, on by default). Before removing an origin course after its
    restore, sections and activities per module (asked of the origin) and, with
    users, enrolled users (read from the backup) are compared with the restored
    course. If anything is missing the origin is kept and the request records
    what (`10412`). The restore plan (missing modules or files) is always checked.
  - **Days in the recycle bin before the definitive deletion**
    (`recyclebinretentiondays`, 30). With `remove_course_cleanup` on, a course
    removed at the request of another platform stays that many days in the
    recycle bin (category recycle bin of `tool_recyclebin` enabled), instead of
    being purged about four minutes later. If the core recycle bin expires items
    sooner (7 days by default), the setting and the Summary diagnosis warn about
    it. Removing a whole category always empties its recycle bin (Moodle core).
  - **Safety copy**: before a restore that may lead to removing the origin, the
    target keeps a copy of the backup (file area `safetycopy`).
  - **Days to keep transferred backups** (`backup_files_retention`, 7) and the
    scheduled task **Delete the backups of finished transfers**
    (`cleanup_backup_files_task`, daily at 04:xx). It deletes the backups served
    as origin and the safety copies of transfers that finished that many days
    ago; never those of a transfer in progress, and never a copy whose origin
    has not been removed yet plus the recycle bin retention.
- **Setting `sslverifypeer`** (on by default): verify the TLS certificate of the
  paired platforms. The connection test of the Platforms page explains when a
  certificate cannot be verified.
- **Audit events** for every removal between platforms:
  `remote_removal_requested` (target), `remote_removal_executed` and
  `remote_removal_rejected` (origin), and `chained_removal_decided` (after a
  restore, whether the origin removal was allowed or held, and why).
- **Privacy provider.** Declares the data kept in the request and platform
  tables, what is sent to the remote platform (the user field and value, and the
  course backup with user data), and the backup files. Export works per user;
  deletion anonymises the rows (`userid = 0`) instead of removing them, because
  requests are the shared log of a transfer between two sites.
- **Integrity checks of the download**: the size of the downloaded backup must
  match the size the origin announced (`13004`), and the target's
  `target_restore_course_max_size` now also applies to the download (`13005`).
- New error codes: `10302`, `10303`, `10410`, `10411`, `10412`, `11005`,
  `13004`, `13005`, `14003`, `14013`, `14023`, `15002`, `15003`, `19002`,
  `22002`, `23002`, `24002`.

### Fixed

- **A restore could run twice on the same course**: a repeated "backup
  completed" callback queued a second download, a cron retry of the restore task
  ran the restore again, and a retry from the log could replace a task that was
  running. Each step is now idempotent by request status, and the restore takes a
  lock per request.
- **A cron retry after a completed restore overwrote `COMPLETED` with error
  `11100`**. Finished requests are no longer touched, and nothing after the
  restore can fail the task.
- **Requests stuck "in progress" for ever** when a task failed: the backup and
  removal tasks now catch any error, mark the request as failed and tell the
  other site. The origin also records a failed backup when the target answered
  the error callback, and when the backup file could not be published.
- **Tasks whose request had been deleted failed in a loop** (PHP 8 fatal): they
  now end quietly.
- **A category restore reported success with courses missing.** It completes
  only when every course of its snapshot of the origin category has a completed
  request; a course or subcategory that cannot be created (`11301`, `11005`)
  leaves it incomplete instead of being skipped silently. Two courses finishing
  at the same time can no longer both mark it complete.
- **The restore progress (`restored`) stayed at 0** after a completed restore:
  the task saved its stale copy of the request over it. Long tasks now update
  only the fields they change.
- **`clean_adhoc_failed_task` never cleaned anything with the default
  threshold** (core caps the fail delay at one day). It now also removes tasks
  with no attempts left, never a task that is running, and no longer writes the
  task data (which could hold a token) to the log.
- **Repair (`postinstall`) disabled every other web service protocol**: it now
  adds REST to the enabled list. It no longer adds duplicate rows to
  `external_services_users`, and makes the service role assignable at system
  level.
- **The "remove category" step checked `origin_remove_course`** instead of
  `origin_remove_category`.
- **Changing `origin_field_search_user` cut short the save of the settings
  page** with a redirect: the repair now runs in place.
- **The course recycle bin purge could delete another course with the same
  shortname**: it is narrowed to the removed course's category and removal time,
  and does nothing when still ambiguous.
- **The tests required `lib/cronlib.php`**, removed from Moodle 5.x, which broke
  the whole site's PHPUnit suite.
- A backup rejected for its size is deleted on the origin, the downloaded backup
  is deleted after a complete restore (it is kept when the restore was
  incomplete), and the temporary download file is removed as soon as the task
  ends.

### Changed

- The service user's email moves from `coursetransfer@test.xxx` (a real TLD) to
  `local_coursetransfer_ws@example.invalid`, and `emailstop` is set: Moodle no
  longer sends it email (backup reports, notifications). Only the account the
  plugin created is changed on upgrade.
- Capabilities declare their risks (`riskbitmask`), so they show up in the
  permission overview.
- The download no longer has a total timeout (large backups): it is aborted only
  when stalled (below 1 KB/s for 5 minutes), never follows redirects and uses the
  same `ignorecurlsecurity` policy as the web service calls.
- The **Repair** action of the Summary screen asks for confirmation and needs a
  session key.
- The platforms page never sends the stored token to the browser: leave the
  token field empty when editing to keep it (only for the same host).
- Errors shown when testing a platform are bounded: the body of the remote
  answer is no longer displayed.
- `get_category_idnumber` rejects patterns longer than 255 characters, invalid
  ones or too costly to evaluate (backtracking limit), with an explicit error.

### Security

- **Fixed: an authenticated user without any capability of this plugin could
  remove courses and categories of the site** through the services used between
  platforms, called from the browser. Upgrade every site that has the plugin
  installed, origin or target.
- **The origin's token no longer travels in the backup URL nor is stored with
  it.** The download task reads the token when it runs and sends it in the body
  of a POST; `fileurl` and the task data keep the URL without token, and
  `cli/view_log_request.php` masks any token it prints.
- **A token could reach a host named by the caller**: the target appended the
  origin's token to whatever backup URL it received, and the download followed
  redirects. The download now never follows redirects, and is always made to the
  origin as registered on the target: the URL is rebuilt with the registered host
  (which also covers an origin registered with an alias of its `wwwroot`). A URL
  whose path is not the backup's `webservice/pluginfile.php` is rejected
  (`10303`).
- Platform registration is limited to administrators and to plain base URLs,
  so the site's tokens can only be sent to hosts an administrator registered.
- Peer services check permissions on a trusted caller (the service user) and
  then on the equivalent user; a peer can no longer list everything on the
  origin.
- `get_category_idnumber` is protected against costly regular expressions
  (ReDoS).

### Deprecated

- **`local_coursetransfer_origin_remove_step1`, `origin_remove_step3` and
  `origin_remove_cat_step3`**: no screen uses them (superseded by
  `restore_wizard_remove_submit`). They are no longer callable from AJAX, still
  work as web service functions for external callers, and will be removed in the
  next version.
- **Token users without `local/coursetransfer:webservice`** calling the peer
  services: accepted in 2.2 with a deprecation warning, rejected in 2.3.
- **Formats of 2.1 kept for one version**: download tasks queued by 2.1 (token in
  the URL), backup tasks with the old custom data and recycle bin purge tasks
  without a removal time are still read and completed. They will no longer be
  read in the next version.

### Upgrade notes

- **Mixed versions.** The protocol between platforms keeps its functions and
  parameters, so a 2.2.0 site can still pair with a 2.1 peer while both are
  upgraded. Upgrade both anyway: a 2.1 peer still accepts the vulnerable AJAX
  calls, and a 2.1 target still sends the origin's token in the download URL.
- After upgrading, open **Summary** and check that every health indicator is
  green, and **test the connection** of each platform. If the service user check
  fails, click **Repair**.
- Review `ignorecurlsecurity` (see breaking changes) and the certificates of your
  peers: test the connection of each platform; a certificate problem is reported
  there.
- Look for deprecation warnings about `local/coursetransfer:webservice` in the
  log or in the platform diagnosis, and fix the service role before 2.3.
- Cron must run on **both** platforms: backups, downloads, restores, removals,
  recycle bin purges and backup cleanups are scheduled or adhoc tasks.
- Upgrade steps: `2026111900` (capability for the service role),
  `2026111901` (column `origin_manifest` in `local_coursetransfer_request`) and
  `2026111902` (service user email). No data is removed.

## 2.1.0 — 2026-09-14

Housekeeping and hardening release, prepared for publication. **No behaviour
changes**: the plugin does the same as 2.0.1. It is now verified by CI across ten
combinations: PHP 8.1 – 8.4, Moodle 4.5 and 5.1, PostgreSQL and MariaDB.

### Added

- **Continuous integration** on every push and pull request
  (`.github/workflows/ci.yml`), running the full `moodle-plugin-ci` suite.
- **`.gitattributes`** pinning line endings to LF, so a clone on Windows does not
  rewrite the whole tree.
- **Example contexts** for the seven templates that had none (or had an empty one),
  so Mustache Lint can render and validate them.

### Fixed

- **Four duplicated string keys.** `target_category_id_integer`, `site_not_found`,
  `deleteintarget` and `direction` were declared twice, so the second declaration
  silently won. In the first two cases the surviving text was the wrong one: the
  CLI error message lost its usage hint, and `site_not_found` diverged from the
  wording still used by the `eu` and `gl` files. The first declaration is now the
  one kept, which also realigns the five languages.

- **An upgrade step had no savepoint.** The `2024040500` block —the one renaming the
  `destiny` table and its fields to `target`— never called
  `upgrade_plugin_savepoint()`, so it was re-executed on every subsequent upgrade of
  a site coming from an older version.

- **PHP 8.4 compatibility:** 41 parameters relied on the implicit nullable of a
  `null` default, deprecated in 8.4. They are now explicitly nullable. Two
  parameters of `get_logs_filter_sql()` had no declared type at all.

- **Accessibility:** the activities and configuration modals pointed
  `aria-labelledby` at an id that does not exist (`…Title` instead of
  `…LongTitle`), so no screen reader could resolve their title.

- **Invalid markup:** a `<div>` inside a `<button>` and a `<span>` as a direct child
  of a `<ul>`, neither of which is allowed.

- **A stylesheet rule leaked outside the plugin:** `input[type="checkbox"]:active`
  and `:disabled` were not scoped, so they applied to the whole Moodle site.

- **A template declared the wrong name:** `components/activity.mustache` identified
  itself as `components/section`.

### Changed

- **CSS moved out of the Mustache templates.** A `<style>` block inside the body is
  invalid HTML and, in the table components, was duplicated once per rendered row.
  It now lives in `styles.css`.
- **The code passes `moodle-plugin-ci` clean**: PHP Lint, Moodle Code Checker
  (`--max-warnings 0`), Moodle PHPDoc Checker, Validate, Upgrade savepoints,
  Mustache Lint and Grunt (`--max-lint-warnings 0`). This meant a large but purely
  cosmetic reformat, plus splitting three oversized methods in the wizard modules
  into named helpers. The language files are also sorted alphabetically and
  stripped of section comments, which the checker does not allow.

## 2.0.1 — 2026-09-09

Bug fix release. No schema changes and no new settings; the upgrade step only
normalises data already stored.

### Fixed

- **Platforms page and site removal failing with `textconditionsnotallowed`:** the
  "in use" lookup compared the TEXT column `siteurl` with a plain equality condition.
  It now uses `sql_compare_text()`.

- **Site lookups comparing only the first 32 characters of the URL:**
  `coursetransfer_sites::get_by_host()` used the default length of
  `sql_compare_text()`, so on the database engines that cast the column two
  platforms sharing that prefix (for example the same campus for two academic
  years) resolved to the same row — returning the wrong site and its token.
  Both lookups now compare 255 characters.

- **Host stored differently from how it is looked up:** `siteurl` was written
  verbatim while every lookup normalised it, so a host registered with a
  trailing slash never matched and its platform showed as unused (and could be
  deleted while it still had requests). Requests now normalise the host on
  write, `get_by_host()` normalises its argument, and the upgrade step strips
  the trailing slash from rows already stored.

- **Concurrent backups from the same user overwriting each other:** course
  backups shared core's default `backup.mbz` filename, so two overlapping
  coursetransfer requests by the same user deleted each other's file in the
  user's private backup area (error 10201). Each backup request now gets a
  unique filename.

## 2.0.0 — 2026-07-30

Major release: GUI redesign (Tresipunt design system), Moodle 4.5 compatibility
and stabilisation. Requires Moodle 4.5+ (supported: 4.5 – 5.1).

### ⚠️ Breaking changes — CLI (`cli/*.php`)

Third-party automation that calls these scripts should review the following.
**Script names, argument names and their meaning are unchanged**, and the
machine-readable success line (`... HAS STARTED - VIEW LOG IN:
view_log_request.php --requestid=N`) is preserved. What changed:

- **Error output moved to STDERR.** Validation and runtime errors are now
  emitted with `cli_error()` (STDERR) instead of `cli_writeln()` (STDOUT).
  Success output still goes to STDOUT. Scripts that captured errors from STDOUT
  must now read STDERR (e.g. `2>&1`).
- **Standardised exit codes:** `0` success · `1` runtime error · `2`
  usage/validation error. Previously validation errors exited `128` and
  `--help` exited `2`; now `--help` exits `0`.
- **Unique error codes.** The inline error codes no longer collide
  (`restore_category` used to reuse `40001`/`40011`, now `40011`/`40012`).
- **`restore_course.php --target_not_remove_activities`** is now honoured. It
  was documented and accepted but silently ignored; passing it now actually
  keeps the listed activities. If not passed, behaviour is unchanged.
- Info messages are now localised (`Scheduler Time`, the ">200 results" notice
  that was hardcoded in Spanish, invalid-schedule message).

### Fixed

- **Data loss in `restore_course.php`:** on a failed restore the error handler
  deleted the target course unconditionally — including a **pre-existing**
  target (`--target_target=3|4`). It now only deletes a course that this run
  created (`--target_target=2`).
- CLI scripts now fail with a clear, localised message (pointing to
  postinstall) when the web service user does not exist yet, instead of an
  opaque fatal.
- `view_logs.php` no longer risks an undefined-index notice for a request whose
  status is outside the known set.

### Changed

- **Moodle 4.5 external API:** removed `require_once($CFG->libdir .
  '/externallib.php')` across the plugin (it aborts PHPUnit in 4.5 via
  `require_phpunit_isolation()`); classes now use the `core_external\`
  namespace and token generation uses `\core_external\util::generate_token()`.
- Full GUI redesign of all screens (summary/token, paired platforms, restore
  and remove wizards, logs and detail) on the Tresipunt design system.
- Removed the legacy multi-step restore/remove flow and its dead code.

### Notes

- Requires Moodle 4.5+.
