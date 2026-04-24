# mod_aidiscussion

Scaffold for a new Moodle activity module that combines a structured discussion board with AI replies, AI-assisted grading, peer discussion requirements, and integrity heuristics.

Important naming note:

- The Moodle activity component is `mod_aidiscussion`.
- The install path in Moodle must therefore be `/mod/aidiscussion`.
- The repository name can still be `moodle-mod_ai_discussion`, but the plugin folder inside Moodle cannot use an underscore in the activity name.

Current scaffold includes:

- plugin metadata for Moodle `4.5+`
- site-level defaults in `settings.php`
- activity settings form in `mod_form.php`
- base schema in `db/install.xml`
- gradebook integration hooks in `lib.php`
- AI provider selection helper that reads configured Moodle 4.5 AI provider plugins
- placeholder adhoc task classes for AI reply generation and grading
- architecture notes in `docs/ARCHITECTURE.md`

Not implemented yet:

- student posting UI
- AI policy acceptance workflow on the activity page
- actual AI prompt orchestration and conversation memory assembly
- heuristic reply suppression
- rubric builder UI
- automatic integrity flagging logic
- backup/restore support
- privacy export/delete handlers
