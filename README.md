# AI discussion for Moodle

[![Moodle Plugin CI](https://github.com/dta121/moodle-mod_aidiscussion/workflows/Moodle%20Plugin%20CI/badge.svg?branch=main)](https://github.com/dta121/moodle-mod_aidiscussion/actions/workflows/ci.yml)

A structured Moodle discussion activity with an AI facilitator, rubric-based grading, and built-in response testing.

> AI discussion is designed for courses where learners should post an initial response, engage with AI follow-up, reply to peers, and receive rubric-aligned feedback and grades.

## Quick links

- [At a glance](#at-a-glance)
- [Who this is for](#who-this-is-for)
- [What it does](#what-it-does)
- [How a typical activity works](#how-a-typical-activity-works)
- [Installation](#installation)
- [Quick start for site admins](#quick-start-for-site-admins)
- [Quick start for instructors](#quick-start-for-instructors)
- [Grading and calibration](#grading-and-calibration)
- [Current limitations](#current-limitations)

## At a glance

| Item | Details |
| --- | --- |
| Moodle component | `mod_aidiscussion` |
| Moodle folder name | `/mod/aidiscussion` |
| Moodle support | `4.5+` |
| Current release | `0.2.5-alpha` |
| Project status | Usable for pilots and testing, still under active development |

Important naming note:

- The repository may be named `moodle-mod_ai_discussion`.
- The Moodle plugin folder must be named `aidiscussion`.
- Moodle activity module names cannot use underscores in the install folder.

## Who this is for

| Audience | Why it helps |
| --- | --- |
| Instructors | Create guided discussion activities with clearer expectations than a standard forum |
| Instructional designers | Structure participation rules, AI follow-up, and rubric-based grading in one place |
| Site administrators | Support Moodle core providers, plugin-managed providers, or both |

## What it does

### Discussion design

- One teacher prompt per activity.
- Optional post-before-view flow so learners must post before seeing peers.
- Optional requirement that an initial response must be submitted before peer replies count.
- Optional peer reply requirements for participation.

### AI facilitation

- AI can reply to learner posts.
- AI can also reply to peer replies when enabled.
- AI replies can be public or private.
- Teachers can choose the AI display name, tone, reply limits, and reply delay.
- Teachers can add an example response to guide how the AI engages with learners.

### Grading and feedback

- Grades can be weighted across:
  - initial response
  - AI interaction
  - peer replies
- Teachers can define custom rubric criteria for each grading area.
- AI grading can produce criterion-level feedback, overall feedback, and integrity flags.
- Grades update after posting and background task processing.

### Admin flexibility

- Activities can use Moodle core AI providers, plugin-managed providers, or both.
- Plugin-managed providers support:
  - model name
  - API base URL
  - temperature
  - max response length
  - API key or bearer token

## How a typical activity works

1. The instructor creates an AI discussion activity and writes the prompt.
2. The learner submits an initial response.
3. The AI facilitator replies when the post is substantive enough.
4. The learner replies to the AI and, if enabled, to peers.
5. The activity updates progress, feedback, and grade information.

If your Moodle site requires AI policy acceptance, learners may need to accept that policy before their posts can be sent to an AI provider.

## AI provider options

### Moodle core providers

If your site already uses Moodle's AI subsystem, AI discussion can use those configured providers directly.

### Plugin-managed providers

AI discussion can also manage its own providers inside the plugin. Current presets include:

- Anthropic Claude
- OpenAI
- DeepSeek
- Google Gemini
- Ollama (Local)
- MiniMax
- Mistral AI
- xAI (Grok)
- OpenRouter
- Custom (OpenAI-compatible)

## Installation

1. Place the plugin in your Moodle install at `/mod/aidiscussion`.
2. Complete the Moodle upgrade from the web UI or CLI.
3. Confirm cron is running. AI replies and grading depend on background tasks.
4. Go to `Site administration -> Plugins -> Activity modules -> AI discussion`.
5. Configure provider sources and at least one AI provider.
6. Create an activity and review the default settings before using it in a live course.

## Quick start for site admins

1. Install the plugin.
2. Open `Site administration -> Plugins -> Activity modules -> AI discussion`.
3. Review the site-level pages for:
   - activity defaults
   - discussion AI defaults
   - grading and privacy defaults
   - provider sources
   - plugin-managed providers
4. Choose whether the site should use Moodle core providers, plugin-managed providers, or both.
5. Configure at least one discussion provider and one grading provider.
6. Confirm cron is running on a regular schedule.

## Quick start for instructors

1. Add an `AI discussion` activity to a course.
2. Write the teacher prompt.
3. Decide whether learners must post before they can see peers.
4. Configure AI behavior, reply timing, and visibility rules.
5. Build rubric criteria and grading weights.
6. Optionally add a teacher example response.
7. Use `Response Tester` before opening the activity to learners.

## Grading and calibration

AI discussion uses rubric-based grading rather than a single generic AI score.

- Each activity can score initial posts, AI interaction, and peer replies separately.
- Teachers can use `Response Tester` to preview how the current rubric would score sample responses.
- Teachers can save benchmark cases with expected scores and compare actual AI grading to their own calibration targets.
- Grading controls include temperature, score granularity, and whether the teacher example response should also influence grading.

If you want tighter grading consistency, start with:

- a low grading temperature
- clear rubric criteria with concrete descriptions
- saved benchmark cases in the Response Tester

## What instructors can configure inside each activity

| Area | Examples |
| --- | --- |
| Prompt and visibility | Teacher prompt, post-before-view, required initial response, peer reply requirements |
| AI behavior | Enable AI, display name, public or private replies, reply delay, reply limits, tone |
| Provider selection | Discussion AI provider, grading AI provider |
| Grading | Rubric criteria, grading weights, grading instructions, score granularity |
| Learner guidance | Show rubric before posting, teacher example response |
| Privacy and integrity | Pseudonymisation, integrity flags |

## Current limitations

This plugin is already usable, but some Moodle-complete features are still pending.

- backup and restore support is not finished
- privacy API export and delete handlers are not finished
- the discussion UI is functional but still evolving

## Development notes

Architecture notes are available in [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).
