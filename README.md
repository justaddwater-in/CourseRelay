# CourseRelay — SCORM Support for Learndash

**Upload SCORM packages. Embed in LearnDash lessons. Track completion. No LRS. No subscription. No GrassBlade.**

CourseRelay is a free WordPress plugin that gives LearnDash a complete, standalone SCORM player — without requiring any external LRS service or paid add-ons.

---

## The Problem We Solved

Every WordPress LMS plugin treats SCORM as an afterthought. LearnDash requires GrassBlade xAPI Companion ($129/yr) **plus** GrassBlade Cloud LRS ($300+/yr) just to upload a Storyline course and mark a lesson complete. That's $400+/year for something that should be built-in.

CourseRelay removes the LRS requirement entirely. Your SCORM data stays in your WordPress database. Completion happens automatically. Your learners never know the difference.

---

## Features

- ✅ Upload SCORM 1.2 and SCORM 2004 packages (.zip)
- ✅ Auto-detects version, launch file, and mastery score from `imsmanifest.xml`
- ✅ Gutenberg block — embed content in any LearnDash lesson or topic
- ✅ Automatic lesson mark-complete when SCORM reports completion
- ✅ Mastery score / passing percentage support
- ✅ Session resume (`suspend_data` and `lesson_location` preserved)
- ✅ Works with Articulate Storyline, Rise, Adobe Captivate, iSpring, Lectora, and more
- ✅ Completion reports in WordPress admin
- ✅ Per-package display settings (width, height, completion type)
- ✅ Learner data reset tool — no database access needed
- ✅ Compatible with LiteSpeed, WP Rocket, W3 Total Cache (auto-purges on completion)
- ✅ Debug logging mode
- ✅ GPL-2.0+ licensed

---

## Requirements

- WordPress 5.8+
- LearnDash LMS 3.0+
- PHP 7.4+ with ZipArchive extension
- No GrassBlade. No LRS. No external services.

---

## Installation

### From GitHub (Manual)

1. Download the latest release zip from the [Releases page](https://github.com/justaddwater-in/courserelay/releases)
2. Go to **WordPress Admin → Plugins → Add New → Upload Plugin**
3. Upload the zip and click **Install Now**
4. Click **Activate Plugin**

### Requirements Check

After activation, make sure:
- LearnDash LMS is active
- PHP `ZipArchive` extension is enabled (it is on most hosts)

---

## How to Use with LearnDash

### Step 1 — Upload a SCORM Package

Go to **CourseRelay → Add New** in your WordPress admin.

![Upload Screen](https://github.com/user-attachments/assets/8d65333f-d0ba-4911-9362-14a297b950c7)

- **SCORM Package** — upload your `.zip` file (exported from Storyline, Rise, Captivate etc.)
- **Title** — auto-detected from the manifest, or enter your own
- **Passing Percentage** — score required to pass (overrides the manifest value)
- **Completion Type** — choose how the LearnDash Mark Complete button behaves:



- **Width / Height** — iframe dimensions (e.g. `100%` / `600px`)

Click **Upload & Process**. CourseRelay extracts the zip, parses the manifest, and stores the content.

---

### Step 2 — Embed in a LearnDash Lesson

![Upload Screen](https://github.com/user-attachments/assets/e521ff3b-2785-4a4d-bfb5-fbc0d756683f)

1. Open a LearnDash lesson or topic in the **Gutenberg editor**
2. Click **+** to add a block → search for **SCORM Content**
3. Select your uploaded package from the dropdown
4. Optionally override width, height, and completion tracking per-block
5. **Publish** the lesson

![Block Editor](https://raw.githubusercontent.com/justaddwater-in/courserelay/main/assets/screenshot-block.png)

---

### Step 3 — Test as a Learner

1. Log in as a test student enrolled in the course
2. Navigate to the lesson — the SCORM content loads in an iframe
3. Complete the content
4. The lesson is automatically marked complete in LearnDash
5. Course progress updates immediately

---

## Completion Flow

```
Learner completes SCORM content
        ↓
SCORM runtime sends LMSSetValue(lesson_status, completed)
        ↓
CourseRelay AJAX handler receives commit
        ↓
Validates registration ID (security check)
        ↓
Finds correct LearnDash lesson (by enrollment)
        ↓
Calls learndash_process_mark_complete()
        ↓
Purges page cache (LiteSpeed, WP Rocket, W3TC)
        ↓
Lesson ✅  Course ✅  Certificate issued
```

---

## Authoring Tool Compatibility

Tested and working with:

| Tool | SCORM 1.2 | SCORM 2004 |
|---|---|---|
| Articulate Storyline 360 | ✅ | ✅ |
| Articulate Rise 360 | ✅ | ✅ |
| Adobe Captivate | ✅ | ✅ |
| iSpring Suite | ✅ | ✅ |
| Lectora | ✅ | ✅ |
| dominKnow Claro | ✅ | ✅ |

> If your authoring tool isn't listed, it will very likely work. SCORM 1.2 and 2004 are standardised specifications.

---

## Global Settings

Go to **CourseRelay → Settings**:

- **Default Width / Height** — applied to all packages unless overridden per-package
- **Default Completion Type** — applied to all packages unless overridden
- **Debug Mode** — writes detailed logs to `wp-content/courserelay-debug.log`

---

## Resetting Learner Progress (for Testing)

Go to **CourseRelay → Settings → Reset Learner Data**.

Enter the User ID, Content ID, Lesson/Post ID, and Course ID — all values are shown in the Completion Reports and the debug log. Click **Reset Learner Progress** to wipe the completion record and SCORM runtime data so the learner starts fresh.

No database access required.

---

## Limitations

We believe in being honest about what this plugin does and doesn't do.

**What CourseRelay does not include:**

| Feature | Status | Notes |
|---|---|---|
| xAPI (Tin Can) content | ❌ | xAPI requires an LRS to receive statements. SCORM only. |
| cmi5 content | ❌ | Same — requires an LRS. |
| SCORM reporting / analytics | ⚠️ Basic | Completion + score only. No interaction-level reporting. |
| Multi-SCO packages | ⚠️ Partial | Only the first SCO is launched. Multi-SCO sequences not supported. |
| SCORM sequencing (SCORM 2004) | ❌ | Navigation rules and sequencing are not implemented. |
| Offline / mobile app support | ❌ | Browser-based only. |
| Built-in video/H5P player | ❌ | SCORM packages only. |

**If you need xAPI, cmi5, or full SCORM reporting**, you need an LRS-based solution. GrassBlade and Tin Canny both do this well.

---

## Security

CourseRelay was built with security as a priority:

- **Zip Slip protection** — every zip entry path is validated before extraction
- **PHP execution blocked** — `.htaccess` rules prevent PHP files in SCORM packages from executing
- **Registration ID validation** — each commit is validated against the user's stored registration UUID using `hash_equals()` (timing-safe)
- **Content ownership check** — users can only write SCORM data to content they have access to
- **Input sanitization** — all user input is validated, sanitized, and escaped
- **Completion type whitelist** — only known completion types are accepted

---

## Frequently Asked Questions

**Do I still need GrassBlade xAPI Companion?**
No. CourseRelay is a complete replacement for SCORM functionality. It handles upload, serving, runtime, and completion entirely independently.

**Which LearnDash versions are supported?**
LearnDash 3.0 and above. Tested on LearnDash 4.10.

**Will this work with LifterLMS / LearnPress / TutorLMS?**
Not yet — LearnDash is the only LMS adapter currently included. Support for other LMS plugins is in development. [Contact us](https://justaddwater.in/contact) if you need this urgently.

**My SCORM content doesn't complete. What do I check?**
1. Enable debug mode in **CourseRelay → Settings**
2. Complete the lesson as a test user
3. Check `wp-content/courserelay-debug.log`
4. Use the Reset Learner Data tool to clear progress between tests

**The course page still shows "not started" after completion.**
If you use a page caching plugin (LiteSpeed, WP Rocket, etc.), make sure the cache is being purged on completion. CourseRelay automatically calls the purge APIs for all major cache plugins. If you use Cloudflare proxy caching, you may need to add a page rule to bypass cache for logged-in users.

---

## Support

**For bugs and feature requests**, please [open an issue](https://github.com/justaddwater-in/courserelay/issues) on GitHub.

**Need SCORM support for a different WordPress LMS?**
We're actively developing adapters for LifterLMS, LearnPress, TutorLMS, MasterStudy, and Sensei. If you're struggling with SCORM integration in any WordPress LMS and need it working sooner, reach out:

📧 **[contact@justaddwater.in](mailto:contact@justaddwater.in)**
🌐 **[justaddwater.in](https://justaddwater.in)**

We offer:
- Custom LMS adapter development
- SCORM migration from other platforms
- LearnDash consultation and custom development
- Hosted eLearning setup and support

---

## Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you'd like to change.

```bash
# Clone the repo
git clone https://github.com/justaddwater-in/courserelay.git

# Install into your local WordPress
cp -r courserelay /path/to/wordpress/wp-content/plugins/
```

---

## Changelog

### 1.0.0
- Initial release
- SCORM 1.2 and 2004 support
- LearnDash lesson/topic/course mark-complete
- Gutenberg block
- Completion reports
- Security hardening (zip slip, registration validation, PHP execution block)
- Cache purge support (LiteSpeed, WP Rocket, W3TC, WP Super Cache)

---

## License

GPL-2.0+ — same as WordPress. See [LICENSE](LICENSE) for details.

---

*Built by [Justaddwater](https://justaddwater.in) — an enterprise eLearning development company.*