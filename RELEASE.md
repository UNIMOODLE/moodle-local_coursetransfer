# Release 2.2.0

## Summary

Version 2.2.0 of the Course Transfer plugin adds compatibility with Moodle 5.3 and is a
security and data-safety release: it closes access problems in the cross-platform web
services, protects the token shared between platforms and makes sure that nothing is removed
from the origin unless the copy in the target is complete and verified.

## Main changes

- Compatibility with Moodle 4.5, 5.1 and 5.3 (`supported = [405, 503]`), verified on each
  change with PHP 8.1 to 8.4 on PostgreSQL and MariaDB.
- Cross-platform web services can only be called by the plugin's service user (new capability
  `local/coursetransfer:webservice`, granted on upgrade), never from a browser session.
- Paired platforms can only be added or edited by site administrators, with URL validation and
  the core cURL security restored by default.
- The token of the origin no longer travels in URLs nor is stored in the database, task data or
  logs; downloads verify the TLS certificate of the peer (setting `sslverifypeer`).
- The origin is only removed after a complete and verified restore (backup manifest), when no
  new or hidden courses appeared in the origin, and with the identity of the user who asked for
  it. Safety copies of the backups are kept until the origin is removed and the retention
  period ends.
- Idempotent tasks and callbacks: a retried or duplicated call no longer restores twice.
- Complete privacy provider, audit events for removals and new data-safety settings.
- Moodle 5.3 fixes: user creation API and CLI scripts on sites with `forcelogin`.

## Impact

Sites that pair platforms keep working after the upgrade. Read the upgrade notes in
`CHANGES.md`: a peer with a self-signed or incomplete TLS chain stops connecting until its CA
is trusted, new or edited platforms must use a plain base URL (existing ones are kept as
they are), and a service token of a user without the new capability is accepted in 2.2 with a
deprecation warning and rejected in 2.3.

## Notes

- Upgrade both platforms when possible; the protocol stays compatible with 2.1 during this
  version.
- As with any release that touches restore and removal, test it in a pre-production
  environment with the actual peer platforms before deploying it to production.
