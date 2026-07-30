# Changelog — local_coursetransfer

All notable changes to this plugin are documented here.

## 2.0.0

Major release: GUI redesign (Tresipunt design system), Moodle 4.5 compatibility
and stabilisation.

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
