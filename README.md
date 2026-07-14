<<<<<<< HEAD
# Scormpleted — SCORM Support for Learndash

**Upload SCORM packages. Embed in LearnDash lessons. Track completion. No LRS. No subscription. No GrassBlade.**

Scormpleted is a free WordPress plugin that gives LearnDash a complete, standalone SCORM player — without requiring any external LRS service or paid add-ons.

---

## The Problem We Solved

Every WordPress LMS plugin treats SCORM as an afterthought. LearnDash requires GrassBlade xAPI Companion ($129/yr) **plus** GrassBlade Cloud LRS ($300+/yr) just to upload a Storyline course and mark a lesson complete. That's $400+/year for something that should be built-in.

Scormpleted removes the LRS requirement entirely. Your SCORM data stays in your WordPress database. Completion happens automatically. Your learners never know the difference.

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

1. Download the latest release zip from the [Releases page](https://github.com/justaddwater-in/scormpleted/releases)
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

Go to **Scormpleted → Add New** in your WordPress admin.

![Upload Screen](https://github.com/user-attachments/assets/8d65333f-d0ba-4911-9362-14a297b950c7)

- **SCORM Package** — upload your `.zip` file (exported from Storyline, Rise, Captivate etc.)
- **Title** — auto-detected from the manifest, or enter your own
- **Passing Percentage** — score required to pass (overrides the manifest value)
- **Completion Type** — choose how the LearnDash Mark Complete button behaves:



- **Width / Height** — iframe dimensions (e.g. `100%` / `600px`)

Click **Upload & Process**. Scormpleted extracts the zip, parses the manifest, and stores the content.

---

### Step 2 — Embed in a LearnDash Lesson

![Upload Screen](https://github.com/user-attachments/assets/e521ff3b-2785-4a4d-bfb5-fbc0d756683f)

1. Open a LearnDash lesson or topic in the **Gutenberg editor**
2. Click **+** to add a block → search for **SCORM Content**
3. Select your uploaded package from the dropdown
4. Optionally override width, height, and completion tracking per-block
5. **Publish** the lesson

![Block Editor](https://raw.githubusercontent.com/justaddwater-in/scormpleted/main/assets/screenshot-block.png)

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
Scormpleted AJAX handler receives commit
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

Go to **Scormpleted → Settings**:

- **Default Width / Height** — applied to all packages unless overridden per-package
- **Default Completion Type** — applied to all packages unless overridden
- **Debug Mode** — writes detailed logs to `wp-content/scormpleted-debug.log`

---

## Resetting Learner Progress (for Testing)

Go to **Scormpleted → Settings → Reset Learner Data**.

Enter the User ID, Content ID, Lesson/Post ID, and Course ID — all values are shown in the Completion Reports and the debug log. Click **Reset Learner Progress** to wipe the completion record and SCORM runtime data so the learner starts fresh.

No database access required.

---

## Limitations

We believe in being honest about what this plugin does and doesn't do.

**What Scormpleted does not include:**

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

Scormpleted was built with security as a priority:

- **Zip Slip protection** — every zip entry path is validated before extraction
- **PHP execution blocked** — `.htaccess` rules prevent PHP files in SCORM packages from executing
- **Registration ID validation** — each commit is validated against the user's stored registration UUID using `hash_equals()` (timing-safe)
- **Content ownership check** — users can only write SCORM data to content they have access to
- **Input sanitization** — all user input is validated, sanitized, and escaped
- **Completion type whitelist** — only known completion types are accepted

---

## Frequently Asked Questions

**Do I still need GrassBlade xAPI Companion?**
No. Scormpleted is a complete replacement for SCORM functionality. It handles upload, serving, runtime, and completion entirely independently.

**Which LearnDash versions are supported?**
LearnDash 3.0 and above. Tested on LearnDash 4.10.

**Will this work with LifterLMS / LearnPress / TutorLMS?**
Not yet — LearnDash is the only LMS adapter currently included. Support for other LMS plugins is in development. [Contact us](https://justaddwater.in/contact) if you need this urgently.

**My SCORM content doesn't complete. What do I check?**
1. Enable debug mode in **Scormpleted → Settings**
2. Complete the lesson as a test user
3. Check `wp-content/scormpleted-debug.log`
4. Use the Reset Learner Data tool to clear progress between tests

**The course page still shows "not started" after completion.**
If you use a page caching plugin (LiteSpeed, WP Rocket, etc.), make sure the cache is being purged on completion. Scormpleted automatically calls the purge APIs for all major cache plugins. If you use Cloudflare proxy caching, you may need to add a page rule to bypass cache for logged-in users.

---

## Support

**For bugs and feature requests**, please [open an issue](https://github.com/justaddwater-in/scormpleted/issues) on GitHub.

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
git clone https://github.com/justaddwater-in/scormpleted.git

# Install into your local WordPress
cp -r scormpleted /path/to/wordpress/wp-content/plugins/
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
=======
# Scormpleted



## Getting started

To make it easy for you to get started with GitLab, here's a list of recommended next steps.

Already a pro? Just edit this README.md and make it your own. Want to make it easy? [Use the template at the bottom](#editing-this-readme)!

## Add your files

* [Create](https://docs.gitlab.com/user/project/repository/web_editor/#create-a-file) or [upload](https://docs.gitlab.com/user/project/repository/web_editor/#upload-a-file) files
* [Add files using the command line](https://docs.gitlab.com/topics/git/add_files/#add-files-to-a-git-repository) or push an existing Git repository with the following command:

```
cd existing_repo
git remote add origin https://gitlab.com/jaw4/scormpleted.git
git branch -M main
git push -uf origin main
```

## Integrate with your tools

* [Set up project integrations](https://gitlab.com/jaw4/scormpleted/-/settings/integrations)

## Collaborate with your team

* [Invite team members and collaborators](https://docs.gitlab.com/user/project/members/)
* [Create a new merge request](https://docs.gitlab.com/user/project/merge_requests/creating_merge_requests/)
* [Automatically close issues from merge requests](https://docs.gitlab.com/user/project/issues/managing_issues/#closing-issues-automatically)
* [Enable merge request approvals](https://docs.gitlab.com/user/project/merge_requests/approvals/)
* [Set auto-merge](https://docs.gitlab.com/user/project/merge_requests/auto_merge/)

## Test and Deploy

Use the built-in continuous integration in GitLab.

* [Get started with GitLab CI/CD](https://docs.gitlab.com/ci/quick_start/)
* [Analyze your code for known vulnerabilities with Static Application Security Testing (SAST)](https://docs.gitlab.com/user/application_security/sast/)
* [Deploy to Kubernetes, Amazon EC2, or Amazon ECS using Auto Deploy](https://docs.gitlab.com/topics/autodevops/requirements/)
* [Use pull-based deployments for improved Kubernetes management](https://docs.gitlab.com/user/clusters/agent/)
* [Set up protected environments](https://docs.gitlab.com/ci/environments/protected_environments/)

***

# Editing this README

When you're ready to make this README your own, just edit this file and use the handy template below (or feel free to structure it however you want - this is just a starting point!). Thanks to [makeareadme.com](https://www.makeareadme.com/) for this template.

## Suggestions for a good README

Every project is different, so consider which of these sections apply to yours. The sections used in the template are suggestions for most open source projects. Also keep in mind that while a README can be too long and detailed, too long is better than too short. If you think your README is too long, consider utilizing another form of documentation rather than cutting out information.

## Name
Choose a self-explaining name for your project.

## Description
Let people know what your project can do specifically. Provide context and add a link to any reference visitors might be unfamiliar with. A list of Features or a Background subsection can also be added here. If there are alternatives to your project, this is a good place to list differentiating factors.

## Badges
On some READMEs, you may see small images that convey metadata, such as whether or not all the tests are passing for the project. You can use Shields to add some to your README. Many services also have instructions for adding a badge.

## Visuals
Depending on what you are making, it can be a good idea to include screenshots or even a video (you'll frequently see GIFs rather than actual videos). Tools like ttygif can help, but check out Asciinema for a more sophisticated method.

## Installation
Within a particular ecosystem, there may be a common way of installing things, such as using Yarn, NuGet, or Homebrew. However, consider the possibility that whoever is reading your README is a novice and would like more guidance. Listing specific steps helps remove ambiguity and gets people to using your project as quickly as possible. If it only runs in a specific context like a particular programming language version or operating system or has dependencies that have to be installed manually, also add a Requirements subsection.

## Usage
Use examples liberally, and show the expected output if you can. It's helpful to have inline the smallest example of usage that you can demonstrate, while providing links to more sophisticated examples if they are too long to reasonably include in the README.

## Support
Tell people where they can go to for help. It can be any combination of an issue tracker, a chat room, an email address, etc.

## Roadmap
If you have ideas for releases in the future, it is a good idea to list them in the README.

## Contributing
State if you are open to contributions and what your requirements are for accepting them.

For people who want to make changes to your project, it's helpful to have some documentation on how to get started. Perhaps there is a script that they should run or some environment variables that they need to set. Make these steps explicit. These instructions could also be useful to your future self.

You can also document commands to lint the code or run tests. These steps help to ensure high code quality and reduce the likelihood that the changes inadvertently break something. Having instructions for running tests is especially helpful if it requires external setup, such as starting a Selenium server for testing in a browser.

## Authors and acknowledgment
Show your appreciation to those who have contributed to the project.

## License
For open source projects, say how it is licensed.

## Project status
If you have run out of energy or time for your project, put a note at the top of the README saying that development has slowed down or stopped completely. Someone may choose to fork your project or volunteer to step in as a maintainer or owner, allowing your project to keep going. You can also make an explicit request for maintainers.
>>>>>>> 049fca8cdac9e2ae3bb30409b977d8e6919a8570
