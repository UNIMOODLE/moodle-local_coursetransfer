# Changelog — local_coursetransfer

All notable changes to this plugin are documented here.

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
