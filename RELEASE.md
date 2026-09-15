# Release 2.1.0

## Summary

Version 2.1.0 of the Course Transfer plugin corresponds to Pull Request #71 and introduces the main update for Moodle 4.5–5.1, along with a complete redesign of the interface and the course/category restore and deletion workflows.

## Main changes

- Updated compatibility with Moodle 4.5, 5.0 and 5.1.
- Complete redesign of the plugin screens and user experience across transfer processes.
- New wizard-style flows for restoring courses, restoring categories, deleting courses and deleting categories.
- Improved course search and destination/origin category selection.
- New execution, detail and log views to monitor requests and transfer results.
- Modernisation of web services and frontend components to align with recent Moodle versions.
- Internal adjustments to backup management, file naming, request flows and course/category handling.
- Review of permissions, administration pages and configuration screens to improve maintainability and usability.

## Impact

This release prepares the plugin for recent Moodle versions with a more modern interface and a clearer workflow for administrators and users.

## Notes

- PR #71 is identified as the release "Course Transfer 2.1.0: compatibility with Moodle 4.5–5.1 and complete redesign of the screens".
- This change includes a significant review of the frontend and the plugin's internal logic, so it is recommended to test it in Moodle 4.5–5.1 environments before production deployment.
