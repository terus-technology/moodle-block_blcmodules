# Changelog

All notable changes to `block_blc_modules` for Moodle 4.5 are documented in this file.

---

## [4.5.19] — 2026-06-23

### Added
- Error logs now support dedicated PROBABLE CAUSE, ACTION, and TECHNICAL DETAILS sections.

### Changed
- Refactored error handling throughout the plugin (`add_doc.php`, `bulk_update.php`, `validate_settings.php`, `file_helper.php`, `logger.php`, `blccurl_helper.php`, `services.php`, `blcservice.php`, and related helpers) to use actionable error messages instead of generic failure logs.
- Replaced technical-only error messages with administrator-friendly descriptions where possible.

### Fixed
- Administrators can now identify common causes of failures and recommended remediation steps directly from Moodle logs without requiring source code analysis or support intervention.
- Reduced ambiguous error messages that previously only described what failed without explaining how to resolve the issue.

---

## [4.5.18] — 2026-06-18

### Added
- Severity-aware logging via new `debug_helper` class that categorizes all log messages as `info`, `warning`, `error`, or `critical`, each with an explicit indication of whether core functionality is affected.
- Debug output now respects Moodle's `debug` setting — only critical/error messages appear in `DEBUG_MINIMAL`, warnings appear in `DEBUG_NORMAL`, and full detail (including `info`) appears in `DEBUG_DEVELOPER`.

### Changed
- Replaced all raw `debugging()` calls across the entire plugin (`add_doc.php`, `bulk_update.php`, `bulk_update_processor.php`, `blcservice.php`, `blccurl_helper.php`, `file_helper.php`, `services.php`, `logger.php`, `validate_settings_page.php`) with severity-aware `debug_helper` calls.
- Non-critical failures (e.g., API key mapping, temp file cleanup) are now logged at `warning` or `info` level instead of being indistinguishable from blocking errors.
- Critical errors (e.g., incomplete configuration, module creation failures) are explicitly tagged with `Core functionality affected` in log output.

## [4.5.17] — 2026-06-11

### Changed
- `ensure_api_key_mapping()` now calls the `local_scormurl_update_scorm_mapping` web service instead of writing directly to the `block_scorm_apikey` table. This also eliminates unnecessary missing-table error logs that appeared when the `block_scorm_apikey` table was absent.

### Removed
- Missing table warning alert from UI, which were only used by the old direct-DB access.

---

## [4.5.16] — 2026-06-10

### Fixed
- Double URL encoding in API calls that pass `scormurl` through `moodle_url`, which could break communication with the BLC API server.

---

## [4.5.15] — 2026-06-09

### Fixed
- Collapse/expand toggle button not responding on the SCORM load logs page.
- Moodle 5.0 compatibility issues in plugin upgrade and rendering paths.
- Intermittent failure when downloading SCORM packages through the BLC selector.

### Removed
- Deprecated `curl.php` helper in favour of Moodle core cURL handling.

---

## [4.5.14] — 2026-03-06

### Added
- Clear, actionable error messages when the `block_scorm_apikey` dependency table is missing. A one-time admin notification is shown instead of a fatal error.
- Header docblocks, `MOODLE_INTERNAL` guards, and modern array syntax across `blcservice`.

### Changed
- Refactored `blcservice` error handling: replaced `error_log` with `debugging()`, added JSON decode guards, and allowed partial-success responses.
- Extracted reusable helpers (`fetch_scorm_data`, `fetch_accessibility_document`, `validate_scorm_url`).
- Tightened parameter validation using `self::validate_parameters` instead of ad-hoc handling.

### Fixed
- PHP 8 compatibility issues (string+int concatenation) in the API layer.

---

## [4.5.13] — 2025-12-01

### Added
- SCORM load logging page with dedicated logger class and database schema (`scorm_load_log` table).
- New `scorm_load_processor.php` for real-time log tracking.

### Changed
- SCORM modules are now sorted alphabetically by `scormname` in the selector for easier browsing.
- Course ID retrieval on `section.php` views now uses the body CSS class for reliability.

### Fixed
- Resolved stale-variable bugs when loading multiple SCORM modules in quick succession.

---

## [4.5.12] — 2025-11-06

### Added
- Real-time AJAX progress bar and spinner when loading SCORM modules, with per-module status messages.
- Bulk update process now shows live progress statistics (success / failure / skipped counts) rendered via a Mustache template.
- Database indexes on bulk-update lookup columns for faster processing on large Moodle instances.

### Changed
- Bulk update processor rewritten with memory management (`gc_collect_cycles`), batched SQL, and performance optimisations for sites with hundreds of SCORM modules.
- Modal dialog behaviour: backdrop click and ESC key no longer dismiss the modal; close requires an explicit button click.
- Subject dropdown now shows a loading spinner and caches results in the JavaScript layer for snappier re-opens.

### Fixed
- Duplicate SCORM detection strengthened — previously stale data could cause false-positive "already exists" rejections.
- Google Drive helper class removed; download methods unified under the standard cURL-based approach.

---

## [4.5.11] — 2025-10-29

### Added
- **Google Drive support**: SCORM packages hosted on Google Drive can now be streamed directly into Moodle with improved filename detection and error handling.
- `subject` field added to `block_blc_modules` database table with corresponding UI filters.
- Bootstrap 5 compatibility layer for Moodle 5.0+ — modals, popovers, and tooltips updated from Bootstrap 4 data attributes.

### Changed
- Subject extraction in `get_subjects()` rewritten to use a single SQL `DISTINCT` query instead of a PHP loop, dramatically improving performance on large datasets.
- Accessibility document fetching centralised through the BLC API rather than ad-hoc HTTP calls.
- Button styles unified under the `.btn-blc-modules` class with consistent hover effects across all module action buttons.
- AJAX timeout increased for long-running SCORM downloads; detailed success/failure logging added to every AJAX call.
- SCORM package filename validation strengthened to reject malformed or empty names before download begins.
- SCORM URL parameter type relaxed from `PARAM_URL` to `PARAM_TEXT` for broader compatibility.

### Fixed
- Stale variable reuse after sequential module loads no longer causes incorrect data to carry over.

---

## [4.5.10] — 2025-09-30

### Added
- Comprehensive error handling for JSON API responses: `json_last_error()` checks and array-structure validation on every API call.
- Database schema update to support enhanced SCORM data fields.

### Changed
- All SQL queries converted from positional (`?`) to named (`:param`) parameters for readability and maintainability.
- Variable initialisation cleaned up across `blcservice` to prevent uninitialised-variable warnings.

---

## [4.5.9] — 2025-07-30

### Added
- New external API endpoints for SCORM operations: module loading (`load_scorm`), deletion (`load_scorm_delete`), subject listing (`load_scormsubject`), and URL retrieval (`load_scormurls`).
- SCORM loading now supports fetching from external BLC URLs with AJAX-driven feedback.

### Removed
- Deprecated `load_scormurls.php` and `version_check.php` scripts replaced by the new external service layer.

### Fixed
- Removed stale debug statements and commented-out code in `blcservice`.

---

## [4.5.8] — 2025-07-25

### Added
- README section documenting how to continue using the legacy `master` branch for older Moodle installations.

### Changed
- SCORM report page template: consistent font-weight across all data cells.

---

## [4.5.7] — 2025-07-16

### Added
- **SCORM Report Page** — interactive chart and data tables showing module usage statistics per course, subject, and time period. Rendered via Mustache (`scorm_report_page.mustache`).
- **SCORM Update Page** — refactored `update_scorm.php` to use the Mustache renderer (`update_scorm_page.mustache`) with structured output classes.
- **Settings Validation Page** — refactored `validate_settings.php` to use the Mustache renderer (`validate_settings_page.mustache`).
- External service definitions registered via `db/services.php` for the SCORM web-service API.

### Changed
- All core PHP files (`add_doc.php`, `validate_settings.php`, `update_scorm.php`, `scorm_report.php`, `version_check.php`) moved under the `block_blc_modules\helper` namespace with proper `use` statements.
- SQL queries in `add_doc.php` and `load_scorm.php` converted to parameterised queries.
- `settings.php` migrated from `new lang_string()` to `get_string()` for Moodle best-practice compliance.
- SCORM filesize detection now uses a lightweight HTTP `HEAD` request instead of downloading the whole file.
- Typo corrections: `stdclass` → `stdClass` throughout.

### Fixed
- Missing class name for the `load_scrom` event.
- Font Awesome icons replaced with Moodle 4.5-compatible alternatives.

---

## [4.5.6] — 2023-09-26

### Added
- Search functionality inside the curriculum area dropdown for quicker navigation.

### Changed
- Curriculum area list re-alphabetised for consistency.

### Fixed
- Modal selection state now resets properly when reopening after being dismissed.
- All relative URLs converted to absolute URLs (missing `https://` prefix could break navigation).

---

## [4.5.5] — 2023-08-10

### Fixed
- cURL certificate verification issue on Moodle 4.x when adding new BLC modules. Added fallback certificate bundle handling.

---

## [4.5.4] — 2022-05-18

### Changed
- Miscellaneous bug fixes and stability improvements.

---

## [4.5.3] — 2022-03-21

### Changed
- **AMD Module Migration**: JavaScript loading converted from legacy inline `<script>` tags to AMD modules (`amd/src/`), with proper `RequireJS` dependency management.
- Helper functions extracted from the main block class into dedicated utility classes.
- Removed unused legacy JS files.

---

## [4.5.2] — 2021-08-12

### Changed
- Module sorting order adjusted in the BLC selector.

### Fixed
- API key settings page now restricted to administrators only — non-admin users can no longer view or modify the API key.

---

## [4.5.1] — 2020-12-17

### Added
- `locallib.php` with shared helper functions.
- Basic SCORM usage report page.

### Fixed
- "Empty SCORM package" issue when downloaded packages contained zero-byte files.

---

## [4.5.0] — 2020-07-17

### Added
- **BLC Modules Summer 2020 Update** — major feature release with bulk update capability, improved SCORM package handling, and updated README documentation.

---

## [1.0.0] — 2019-04-06

### Added
- Initial public release of `block_blc_modules` (post-beta).
- Core functionality: browse and add SCORM packages from the Blended Learning Consortium repository into Moodle courses.
- API key configuration and validation tools.
- README with installation, update, and prerequisites documentation.