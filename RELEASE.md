# Release 2.2.1

## Summary

Version 2.2.1 of the Course Transfer plugin makes every transfer carry the whole course, keeps
the enrolments of the origin and fixes the problems reported on GitHub since 2.2.0. It supports
Moodle 4.5, 5.1 and 5.3 and keeps the protocol of 2.2.0.

## Main changes

- A transfer carries the whole course, as a backup with every option ticked, whatever the
  general backup and restore defaults of each site: with user data, also submissions, posts,
  attempts, grades, completion and logs (setting `transferlogs`); without user data, all the
  content.
- Student work is transferred in every selected section, not only in the first one.
- Enrolments of the origin are restored as active manual enrolments, whatever their method.
- Subsections (Moodle 4.5+) travel with their content, as in Moodle's own backup.
- Before removing the origin, the restored course is also checked for user data, grades,
  completion and files; if anything is missing, the origin is kept.
- Retry for course requests that have stopped moving for over an hour.
- Fixes for site policies of the service user, hidden categories in the origin, suspended
  teachers in the origin and the self enrolment page of courses in hidden categories.

## Impact

Upgrade both platforms. Backups with user data include the logs by default and can be larger.
Courses restored without user data now also receive their (empty) groups. See `CHANGES.md` for
the details and the upgrade notes, and the 2.2.0 notes if you upgrade from 2.1.

## Notes

- As with any release that touches restore and removal, test it in a pre-production
  environment with the actual peer platforms before deploying it to production.
