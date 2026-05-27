=== Scormpleted ===
Contributors: justaddwater
Tags: scorm, learndash, lms, elearning, scorm player
Requires at least: 5.8
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Complete SCORM player for LearnDash — upload packages, embed in courses, track completion. No external LRS required.

== Description ==

**Scormpleted** is a standalone SCORM player for WordPress and LearnDash. Upload SCORM 1.2 or SCORM 2004 packages, embed them in LearnDash lessons using a Gutenberg block, and automatically mark lessons complete when learners finish — all without any external LRS service.

= Features =

* Upload SCORM 1.2 and SCORM 2004 zip packages
* Auto-detects version, launch file, and mastery score from imsmanifest.xml
* Gutenberg block to embed content in LearnDash lessons and topics
* Automatic LearnDash lesson mark-complete on SCORM completion
* Mastery score / passing percentage support
* Session resume (suspend_data / lesson_location preserved)
* Completion reports in WordPress admin
* Per-content display settings (width, height)
* Learner data reset tool (no database access needed)
* Debug logging mode
* No external LRS, no subscription, no extra plugins required

= Requirements =

* LearnDash LMS 3.0+
* PHP 7.4+ with ZipArchive extension
* WordPress 5.8+

= SCORM Support =

| Feature | SCORM 1.2 | SCORM 2004 |
|---|---|---|
| Auto-launch | ✅ | ✅ |
| Session resume | ✅ | ✅ |
| Completion detection | ✅ | ✅ |
| Score tracking | ✅ | ✅ |
| Mastery score | ✅ | ✅ |
| LearnDash mark complete | ✅ | ✅ |

= How to use =

1. Go to **Scormpleted → Add New** and upload a .zip SCORM package
2. Open a LearnDash lesson in the Gutenberg editor
3. Add the **SCORM Content** block and select your package
4. Publish the lesson — learners who complete the SCORM content will have the lesson marked complete automatically

== Installation ==

1. Upload the `scormpleted` folder to `/wp-content/plugins/`
2. Activate via **Plugins → Installed Plugins**
3. LearnDash must also be active

== Frequently Asked Questions ==

= Does this work without LearnDash? =

The SCORM player works standalone — learners can view content. LearnDash completion tracking requires LearnDash to be active.

= Which authoring tools are supported? =

Any tool that exports a valid SCORM 1.2 or SCORM 2004 package — Articulate Storyline, Articulate Rise, Adobe Captivate, iSpring, Lectora, and others.

= Is an LRS required? =

No. All data is stored locally in your WordPress database.

= Can I reset a learner's progress for testing? =

Yes. Go to **Scormpleted → Settings** and use the Reset Learner Data tool.

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release.
