# Changelog — local_coursetransfer

All notable changes to this plugin are documented here.

## 2.2.1 — 2026-11-20 (`2026112002`)

Maintenance release: complete course transfers and fixes reported on GitHub. Supported:
Moodle 4.5, 5.1 and 5.3. Upgrade both platforms; the protocol does not change.

### Changed

- **A transfer carries the whole course, whatever the general backup and restore defaults
  of each site.** With user data, the backup includes everything the backup form includes
  with every option ticked (submissions, posts, attempts, grades and their history,
  completion, comments, role assignments, logs…); without user data, all the course content.
  New setting `transferlogs` (on by default) to leave the logs out. Groups and groupings are
  now restored also without user data (empty), as course content. Every section and activity
  carries its user data too, not only the course-level settings. Privacy decisions locked by
  the administrator of the origin (user data off, anonymisation) are kept.
- **Enrolments of the origin are restored as active manual enrolments**, whatever their
  method (database, LDAP, cohort, meta, self…), as the restore option "Restore users as
  manual enrolments" does, while manual enrolments are enabled on the target. Methods tied to
  the origin (self enrolment with a key, guest access) are not recreated (#43, #42).
- **Retry is also offered for course requests that have stopped**: no changes for over an
  hour (six while the origin makes the backup) and past their scheduled time, in the
  executions page and in the request detail. Requests still moving cannot be relaunched
  (error `10024`), and a course of a category restore is only restored again from its
  downloaded backup, never relaunched apart from its category (error `10025`) (#45).
- Origin courses follow the same access rule as Moodle's manual backup: an active enrolment
  or access to the course without one (#60).

### Fixed

- With "include users" and a selection of sections, only the first section kept its user
  data: student submissions, posts and attempts of the rest were not transferred (#42).
- Subsections (Moodle 4.5+): the content of a subsection always follows the subsection
  activity, as in Moodle's own backup, so no activity is restored outside its subsection (#58).
- The service user keeps the site policies accepted: new status check "Site policies" with
  Repair, accepted again on a web upgrade (after a CLI upgrade, use Repair on the status
  page), support for `$CFG->sitepolicy`, and a clear error on the origin when they are
  pending (`error_wspolicynotagreed`) (#53).
- Restores of backups made by an origin on Moodle 5.x are no longer held for a setting that
  does not exist there.
- The files of every included activity are restored even when activities are off in the
  general restore defaults of the target.
- A course in a hidden category of the origin no longer stops its restore or the removal of
  the origin; the upgrade grants `moodle/category:viewhiddencategories` to the service role
  (#47, #62).
- The category menu link no longer breaks pages of a hidden category, such as the self
  enrolment page of a visible course in it, and is only shown to who may use it (#63).
- On the origin, only a live local account can act for a user of the target: deleted,
  suspended or duplicated accounts are rejected with a clear error (`17002`, `17004`,
  `17005`).
- Retry runs once at a time per request (`10026`), decides on the stored request and records
  why a relaunch failed; the retry page shows any error as a message (`10027`).
- A remote removal that was done is recorded as completed on both platforms, even when the
  notice between them failed (`15010` note on the origin; on the target, an earlier error is
  kept as a note).
- A backup size limit of 0 means no limit on the origin too: it rejected every backup.
- An existing target course chosen for more than one origin in replace mode, or with a restore
  still running, is rejected before anything is launched (`10030`): the second replace would
  empty what the first restored.
- A scheduled removal or restore with a date in the past or beyond 30 days is rejected
  (`10031`) instead of running at once.
- Only finished requests without pending tasks nor a kept safety copy can be deleted from the
  history (`30703`), and every deletion is logged (event `request_deleted`).
- The remove wizard no longer says a removed category can be recovered from the recycle bin:
  deleting a category also empties its recycle bin.
- Wizards: when a submission only partly succeeds, or its result cannot be confirmed (timeout,
  lost connection), they show what was launched and a link to the log, and do not offer to run
  it again; the run button cannot be pressed twice while a submission is in progress.
- Wizards: an error of the origin (connection, permissions, user not found…) is shown with its
  code, what to do and a Retry button, instead of "no results" or an empty category tree.
- Wizards: an invalid or past scheduled date is flagged before submitting, and the same
  existing target in replace mode for two origins is flagged in the destination step.
- The origin no longer offers its front page as a course to restore or remove.
- Translations: four strings missing in Galician and Basque.

### Data safety

- A completed request whose origin was kept on purpose says so ("Completed · the origin was
  kept"), with the reason and what to do, instead of a plain "Completed".
- Before removing the origin, the restored course is also checked against the backup: the
  backup carries the whole course, every included activity carries its user data, and the
  restored course has at least the grades, activity completion states and files of the
  backup that this kind of restore brings. Otherwise the removal is held and the reason is
  recorded. If the backup could not be read, the removal is held too.

### Upgrade notes

- Backups with user data include the logs by default and can be larger: untick
  `transferlogs` if needed.
- A transfer from a 2.2.0 or older origin is checked against its backup: if that origin's
  general backup defaults left something out, the removal of the origin is held. Upgrade
  the origin.

## 2.2.0 — 2026-11-20 (`2026112001`)

Moodle 5.3 support, robustness and data-integrity release. Supported:
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
  `moodle/site:config`.**
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
  task data to the log.
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

### Robustness

- Stricter access control on the services used between platforms and on the
  management of paired platforms. **Upgrade every site that has the plugin
  installed, origin or target.**
- The token shared between platforms is handled more strictly: it is no longer
  part of URLs, stored data or logs, and it is only sent to registered platforms
  over verified TLS.
- Stricter validation of the data received from peer platforms.

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
  upgraded. Upgrade both anyway: the improvements of this version only apply to
  the sites that run it.
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
  `2026111902` (service user email) and `2026112001` (cleanup of stored
  backup URLs). No data is removed.

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

## [1.1.1] - 2024-06-18

- Maintenance update and capability string fixes.

## [Earlier releases]

- Previous versions included course restoration, category restoration, remote deletion, logs, and scheduled tasks.
