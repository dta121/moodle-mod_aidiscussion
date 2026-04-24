# Architecture Notes

## Product shape

This plugin is being built as a new Moodle activity module:

- Component: `mod_aidiscussion`
- Install path: `/mod/aidiscussion`
- Target baseline: Moodle `4.5+`

The first version is intentionally scoped to one teacher prompt per activity, with a threaded discussion underneath it.

## Core activity rules

The agreed defaults are:

- single-prompt discussion
- students post before seeing peer responses
- students complete an initial response before peer replies count
- AI replies are public by default
- teachers can switch AI interactions to private
- AI may reply to peer replies when enabled and heuristics say the reply is substantive
- immediate grade updates
- teacher override remains possible
- pseudonymisation defaults on
- integrity heuristics are flags only

## AI integration strategy

Instead of inventing a separate site-wide gateway plugin, this module is scaffolded to sit on top of Moodle 4.5's core AI subsystem and its provider plugins.

On Moodle 4.5, the AI manager exposes provider plugins, not named provider instances. This scaffold therefore stores provider component names such as:

- `aiprovider_openai`
- `aiprovider_azureai`
- any additional third-party `aiprovider_*` plugins installed on the site

That gives us:

- admin-managed provider plugin configuration
- action-level provider enablement
- provider selection infrastructure
- core AI policy support
- a reusable site-wide AI foundation for future plugins

For `mod_aidiscussion`, the activity stores:

- one provider plugin for discussion replies
- one provider plugin for grading

If the grading provider is empty, the discussion provider is used as the fallback.

## Multi-provider roadmap

The activity is provider-agnostic. On Moodle 4.5 the site can expose multiple provider plugins to teachers, for example:

- OpenAI
- Azure AI
- Ollama, if installed as an AI provider plugin
- Anthropic, Google, Meta, or other providers, if installed as AI provider plugins

Important limitation:

- Moodle 4.5 does not give this activity multiple named provider instances per vendor in the core API that exists on this local branch.
- If you want teachers to choose among multiple named OpenAI or Anthropic model profiles from one vendor, we should add a dedicated local gateway/settings layer in a follow-up pass.

## Grading approach

The scaffold uses a custom rubric data model inside the activity rather than Moodle's native advanced grading forms.

Reason:

- this activity has three grading components
- AI is expected to score each component independently
- the activity needs to aggregate those components into one gradebook score
- the activity also needs to store structured AI feedback, rubric evidence, and integrity flags

The gradebook still receives a normal numeric activity grade.

## Data model

The initial schema separates the major concerns:

- `aidiscussion`
  - activity configuration and defaults
- `aidiscussion_posts`
  - student, teacher, and AI posts
- `aidiscussion_jobs`
  - queued or completed background work
- `aidiscussion_grades`
  - per-user computed scores and feedback payloads
- `aidiscussion_rubrics`
  - rubric containers for `initial`, `ai`, and `peer`
- `aidiscussion_criteria`
  - criterion rows for each rubric

## Background processing

The workflow is designed for adhoc tasks:

1. Student submits a post.
2. Heuristics decide whether AI should reply.
3. If yes, an adhoc task generates the reply.
4. Another adhoc task recalculates that learner's grade state.
5. Gradebook is updated immediately.

This keeps page loads fast and gives room for retries, logging, and provider failover later.

## Compatibility posture

The scaffold is written against Moodle `4.5` APIs and avoids relying on undocumented internals. Forward compatibility with later `5.x` releases should be managed by:

- storing provider instance ids rather than provider-specific config
- keeping AI orchestration in thin service classes
- avoiding legacy UI callbacks unless required
- isolating schema changes behind upgrade steps as the feature set grows
