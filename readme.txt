=== CourseRelay - SCORM Support for LearnDash LMS ===
Contributors: justaddwater
Tags: scorm, learndash, lms, elearning, scorm player
Requires at least: 5.8
Tested up to: 7.1
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Complete SCORM player for LearnDash. Upload packages, embed them in lessons, and track completion. No external LRS required.

== Description ==

**CourseRelay** is a standalone SCORM player for WordPress and LearnDash. Upload SCORM 1.2 or SCORM 2004 packages, embed them in LearnDash lessons using a Gutenberg block, and have lessons marked complete automatically when learners finish. Everything runs inside your own WordPress install.

All SCORM runtime data, completion records and learner progress are stored in your WordPress database. Nothing is sent to a third party server, there are no API keys to configure and no external accounts to create.

Full documentation and screenshots: [CourseRelay product page](https://justaddwater.in/products/courserelay-scorm-for-learndash/)

= Why CourseRelay =

Delivering SCORM content in LearnDash usually means running a SCORM companion plugin alongside a hosted Learning Record Store subscription. Together those typically cost several hundred dollars a year just to upload a Storyline course and mark a lesson complete.

CourseRelay removes both dependencies. The plugin handles upload, content serving, the SCORM runtime and LearnDash completion tracking on its own.

= Features =

* Upload SCORM 1.2 and SCORM 2004 zip packages
* Auto-detects SCORM version, launch file and mastery score from imsmanifest.xml
* Gutenberg **SCORM Content** block for LearnDash lessons and topics
* Four display modes: embedded in the page, popup modal, full screen player in a new tab, or full screen player in the same tab
* Automatic LearnDash lesson and course mark-complete on SCORM completion
* LearnDash certificates trigger through the standard LearnDash flow
* Mastery score and passing percentage support, auto-detected or set per package
* Session resume, with suspend_data and lesson_location preserved
* Completion reports in the WordPress admin, filterable by user and content
* Per-package and per-block display settings for width, height and completion type
* Learner data reset tool, no database access needed
* Cache purge support for LiteSpeed, WP Rocket, W3 Total Cache and WP Super Cache
* Debug logging mode
* GPL-2.0+ licensed

= SCORM support =

SCORM 1.2 and SCORM 2004 are both supported for auto-launch, session resume, completion detection, score tracking, mastery score and LearnDash mark-complete.

= Security =

* Zip Slip protection, with every archive entry path validated before extraction
* PHP execution blocked inside uploaded packages
* Registration ID validation on every commit using a timing-safe comparison
* Content ownership checks, so learners can only write data for content they can access
* SCORM iframe sandboxing that still preserves the SCORM parent API
* Learner identifiers redacted from debug logs
* All input validated, sanitized and escaped

= Privacy =

CourseRelay integrates with the WordPress personal data export and erasure tools. No learner data leaves your server.

= Authoring tools =

Tested with Articulate Storyline 360, Articulate Rise 360, Adobe Captivate, iSpring Suite, Lectora and dominKnow Claro. Any tool that exports a valid SCORM 1.2 or SCORM 2004 package should work. If you are publishing from Captivate, SCORM 1.2 gives the most reliable results at this stage.

= What it does not do =

* xAPI (Tin Can) and cmi5 content, which require a Learning Record Store
* SCORM 2004 adaptive sequencing and navigation rules. Linear courses work, branching does not
* Interaction level reporting. Completion and score only, no quiz answer or hotspot data
* Multi-SCO packages are only partially supported. Only the first SCO is launched
* Other WordPress LMS plugins. LearnDash is the only adapter currently included
* Offline or mobile app delivery. Browser only

= Requirements =

* WordPress 5.8 or later
* LearnDash LMS 3.0 or later, tested on LearnDash 4.10.1
* PHP 7.4 or later with the ZipArchive extension

= How to use =

1. Go to **CourseRelay > Add New** and upload a .zip SCORM package
2. Open a LearnDash lesson in the Gutenberg editor
3. Add the **SCORM Content** block and select your package
4. Choose a display mode, and optionally override width, height and completion tracking for that block
5. Publish the lesson. Learners who complete the SCORM content will have the lesson marked complete automatically

== Installation ==

1. Upload the `courserelay` folder to `/wp-content/plugins/`, or install through **Plugins > Add New**
2. Activate the plugin via **Plugins > Installed Plugins**
3. Make sure LearnDash LMS is active and the PHP ZipArchive extension is enabled
4. Go to **CourseRelay > Settings** to set default width, height and completion type

== Frequently Asked Questions ==

= Do I need any other SCORM plugin installed? =

No. CourseRelay handles upload, content serving, the SCORM runtime and LearnDash completion on its own. No companion plugin and no LRS subscription.

= Does this work without LearnDash? =

CourseRelay is built for LearnDash lessons and topics. LearnDash must be active for the supported completion workflow.

= Is an LRS required? =

No. All SCORM runtime and completion data is stored locally in your WordPress database.

= Can SCORM content open in a popup or new window? =

Yes. In the SCORM Content block settings choose Display Mode: Same Page (Embedded), Popup (Modal Window), Full Screen Player (New Tab) or Full Screen Player (Same Tab). Progress and completion are tracked the same way in every mode.

= Which authoring tools are supported? =

Any tool that exports a valid SCORM 1.2 or SCORM 2004 package, including Articulate Storyline, Articulate Rise, Adobe Captivate, iSpring, Lectora and dominKnow Claro.

= Which LearnDash versions are supported? =

LearnDash 3.0 and above. Testing is done against LearnDash 4.10.1.

= Will it work with LifterLMS, LearnPress or TutorLMS? =

Not yet. LearnDash is the only LMS adapter currently included. Adapters for other LMS plugins are in development.

= Will it work with my caching plugin? =

Yes. CourseRelay calls the purge APIs for LiteSpeed, WP Rocket, W3 Total Cache and WP Super Cache when a completion is recorded. If you use Cloudflare proxy caching, add a page rule to bypass cache for logged-in users.

= My SCORM content loads but completion is not tracked. What do I check? =

Enable Debug Mode in **CourseRelay > Settings**, complete the lesson as a test learner, then review `wp-content/courserelay-debug.log`. The log records each step of the completion flow. Use the Reset Learner Data tool to clear progress between tests.

= What happens if a learner does not finish a course in one sitting? =

SCORM runtime data is written to the database on every commit. Position, score and suspension data are saved, so the learner resumes from where they stopped.

= Can I reset a learner's progress for testing? =

Yes. Go to **CourseRelay > Settings** and use the Reset Learner Data tool. Enter the User ID, Content ID, Lesson or Topic ID and Course ID, all of which appear in the Completion Reports and the debug log.

= Where do I get support? =

Use the support forum on this page for questions about the plugin. For bug reports and feature requests you can also open an issue on [GitHub](https://github.com/justaddwater-in/CourseRelay). If you need SCORM support for a different WordPress LMS or custom LearnDash development, get in touch through [justaddwater.in](https://justaddwater.in/).

== Screenshots ==

1. A learner viewing the SCORM content inside a LearnDash lesson.
2. The LearnDash course page showing the lesson and course marked complete after the learner finishes.
3. Add New: upload a SCORM package and set the title, passing percentage and display options.
4. All uploaded SCORM packages listed in the WordPress admin.
5. Completion report showing learner, content, status, score and timestamp.
6. CourseRelay settings screen with defaults, debug mode and the Reset Learner Data tool.
7. Embedding an uploaded package in a LearnDash lesson with the SCORM Content block.
8. The SCORM Content block in place in the LearnDash lesson editor, showing the selected package.
9. The Display Mode option in the SCORM Content block: embedded, popup modal, or full screen player in a new or the same tab.

== Changelog ==

= 1.1.0 =
* New: Display Mode option in the SCORM Content block. Choose Same Page (Embedded), Popup (Modal Window), Full Screen Player (New Tab) or Full Screen Player (Same Tab).
* New: closing the popup player ends the SCORM session so progress is saved, and the lesson re-checks completion immediately.
* New: SCORM 1.2 packages that call the SCORM 2004 API are now supported.
* New: CourseRelay admin menu icon.
* Fix: completion polling now works for a lesson containing a single SCORM package.
* Fix: replacing the package in a lesson no longer leaves the previous package blocking completion.

= 1.0.0 =
* Initial release.
* SCORM 1.2 and SCORM 2004 support, with automatic detection of version, launch file and mastery score from imsmanifest.xml.
* Gutenberg SCORM Content block for LearnDash lessons and topics.
* Automatic LearnDash lesson, topic and course mark-complete, with certificates following the standard LearnDash flow.
* Session resume, with suspend_data and lesson_location preserved.
* Completion reports in the WordPress admin, filterable by user and content.
* Learner data reset tool and debug logging mode.
* WordPress privacy export and erasure integration.
* Security hardening: zip slip protection, blocked PHP execution inside packages, timing-safe registration validation, content ownership checks, iframe sandboxing and redacted debug logs.
* Cache purge support for LiteSpeed, WP Rocket, W3 Total Cache and WP Super Cache.

== Upgrade Notice ==

= 1.1.0 =
Adds popup and full screen display modes, and fixes completion tracking for lessons with a single SCORM package.

== Development ==

Product page: [CourseRelay for LearnDash](https://justaddwater.in/products/courserelay-scorm-for-learndash/)

Source code: [github.com/justaddwater-in/CourseRelay](https://github.com/justaddwater-in/CourseRelay)

CourseRelay is built and maintained by [JustAddWater](https://justaddwater.in/), an eLearning development company.
