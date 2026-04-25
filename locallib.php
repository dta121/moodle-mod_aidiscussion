<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Local helpers for mod_aidiscussion.
 *
 * @package   mod_aidiscussion
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_ai\manager as core_ai_manager;
use mod_aidiscussion\local\ai\provider_client;
use mod_aidiscussion\local\ai\provider_registry;
use mod_aidiscussion\task\process_ai_reply;
use mod_aidiscussion\task\process_grading;

/**
 * Load activity records from standard module params.
 *
 * @param int $id Course module id.
 * @param int $n Activity instance id.
 * @return array
 */
function aidiscussion_get_activity_from_params(int $id = 0, int $n = 0): array {
    global $DB;

    if ($id) {
        $cm = get_coursemodule_from_id('aidiscussion', $id, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $aidiscussion = $DB->get_record('aidiscussion', ['id' => $cm->instance], '*', MUST_EXIST);
    } else {
        $aidiscussion = $DB->get_record('aidiscussion', ['id' => $n], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $aidiscussion->course], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('aidiscussion', $aidiscussion->id, $course->id, false, MUST_EXIST);
    }

    $context = context_module::instance($cm->id);

    return [
        'cm' => $cm,
        'course' => $course,
        'aidiscussion' => $aidiscussion,
        'context' => $context,
    ];
}

/**
 * Convert module form data into a DB record.
 *
 * @param stdClass $data
 * @return stdClass
 */
function aidiscussion_formdata_to_record(stdClass $data): stdClass {
    $record = new stdClass();

    $fields = [
        'course',
        'name',
        'intro',
        'introformat',
        'postbeforeview',
        'requireinitialpost',
        'allowpeerreplies',
        'replytopeerreplies',
        'publicaireplies',
        'allowprivateai',
        'aienabled',
        'aidisplayname',
        'replyprovider',
        'gradeprovider',
        'showrubricbeforeposting',
        'pseudonymiseusers',
        'integrityflagsenabled',
        'minsubstantivewords',
        'maxairepliesperstudent',
        'aireplydelayminutes',
        'requiredpeerreplies',
        'grade',
        'initialweight',
        'aiweight',
        'peerweight',
        'responsetone',
        'responseinstructions',
        'gradinginstructions',
        'useexemplarforgrading',
        'gradingtemperature',
        'gradinggranularity',
    ];

    foreach ($fields as $field) {
        $record->{$field} = $data->{$field} ?? null;
    }

    $record->replyprovider = trim((string)($record->replyprovider ?? ''));
    $record->gradeprovider = trim((string)($record->gradeprovider ?? ''));
    $record->aidisplayname = trim((string)($record->aidisplayname ?? ''));
    if ($record->aidisplayname === '') {
        $record->aidisplayname = 'AI facilitator';
    }
    if ($record->gradeprovider === '') {
        $record->gradeprovider = $record->replyprovider;
    }
    $record->postbeforeview = (int)!empty($record->postbeforeview);
    $record->requireinitialpost = (int)!empty($record->requireinitialpost);
    $record->allowpeerreplies = (int)!empty($record->allowpeerreplies);
    $record->replytopeerreplies = (int)!empty($record->replytopeerreplies);
    $record->publicaireplies = (int)!empty($record->publicaireplies);
    $record->allowprivateai = (int)!empty($record->allowprivateai);
    $record->aienabled = (int)!empty($record->aienabled);
    $record->showrubricbeforeposting = (int)!empty($record->showrubricbeforeposting);
    $record->pseudonymiseusers = (int)!empty($record->pseudonymiseusers);
    $record->integrityflagsenabled = (int)!empty($record->integrityflagsenabled);
    $record->useexemplarforgrading = (int)!empty($record->useexemplarforgrading);
    $record->grade = (int)$record->grade;
    $record->minsubstantivewords = (int)$record->minsubstantivewords;
    $record->maxairepliesperstudent = (int)$record->maxairepliesperstudent;
    $record->aireplydelayminutes = max(0, (int)$record->aireplydelayminutes);
    $record->requiredpeerreplies = (int)$record->requiredpeerreplies;
    $record->initialweight = (float)$record->initialweight;
    $record->aiweight = (float)$record->aiweight;
    $record->peerweight = (float)$record->peerweight;
    $record->responsetone = trim((string)$record->responsetone);
    $record->responseinstructions = trim((string)$record->responseinstructions);
    $record->gradinginstructions = trim((string)$record->gradinginstructions);
    $record->gradingtemperature = max(0.0, min(1.0, (float)($record->gradingtemperature ?? 0.0)));
    $record->gradinggranularity = aidiscussion_normalise_grading_granularity((string)($record->gradinggranularity ?? 'half'));
    $record->prompt = trim((string)($data->prompt_editor['text'] ?? ''));
    $record->promptformat = (int)($data->prompt_editor['format'] ?? FORMAT_HTML);
    $record->teacherexample = trim((string)($data->teacherexample_editor['text'] ?? ''));
    $record->teacherexampleformat = (int)($data->teacherexample_editor['format'] ?? FORMAT_HTML);

    return $record;
}

/**
 * Convert stored feedback JSON into a gradebook-safe feedback string.
 *
 * @param string|null $feedbackjson
 * @return string
 */
function aidiscussion_grade_feedback(?string $feedbackjson): string {
    if (empty($feedbackjson)) {
        return '';
    }

    $decoded = json_decode($feedbackjson, true);
    if (!is_array($decoded)) {
        return '';
    }

    if (!empty($decoded['overall'])) {
        return trim((string)$decoded['overall']);
    }

    if (!empty($decoded['summary'])) {
        return trim((string)$decoded['summary']);
    }

    return '';
}

/**
 * Human readable yes/no string.
 *
 * @param bool|int $value
 * @return string
 */
function aidiscussion_bool_text($value): string {
    return empty($value) ? get_string('no') : get_string('yes');
}

/**
 * Return rubric area metadata.
 *
 * @return array
 */
function aidiscussion_get_rubric_area_definitions(): array {
    return [
        'initial' => [
            'label' => get_string('initialresponsecomponent', 'mod_aidiscussion'),
            'weightfield' => 'initialweight',
        ],
        'ai' => [
            'label' => get_string('aiinteractioncomponent', 'mod_aidiscussion'),
            'weightfield' => 'aiweight',
        ],
        'peer' => [
            'label' => get_string('peerreplycomponent', 'mod_aidiscussion'),
            'weightfield' => 'peerweight',
        ],
    ];
}

/**
 * Return whether the rubric area is active for this activity.
 *
 * @param stdClass $aidiscussion
 * @param string $area
 * @return bool
 */
function aidiscussion_is_rubric_area_enabled(stdClass $aidiscussion, string $area): bool {
    return match ($area) {
        'ai' => !empty($aidiscussion->aienabled),
        'peer' => !empty($aidiscussion->allowpeerreplies),
        default => true,
    };
}

/**
 * Return the weight assigned to a rubric area.
 *
 * @param stdClass $aidiscussion
 * @param string $area
 * @return float
 */
function aidiscussion_get_rubric_area_weight(stdClass $aidiscussion, string $area): float {
    $definitions = aidiscussion_get_rubric_area_definitions();
    $field = $definitions[$area]['weightfield'] ?? null;
    return $field ? (float)($aidiscussion->{$field} ?? 0) : 0.0;
}

/**
 * Return the stored score field for a rubric area.
 *
 * @param string $area
 * @return string
 */
function aidiscussion_get_rubric_area_score_field(string $area): string {
    return match ($area) {
        'initial' => 'initialscore',
        'ai' => 'aiscore',
        'peer' => 'peerscore',
        default => throw new coding_exception('Unknown rubric area: ' . $area),
    };
}

/**
 * Decode stored feedback JSON.
 *
 * @param string|null $feedbackjson
 * @return array
 */
function aidiscussion_decode_feedback_json(?string $feedbackjson): array {
    if (empty($feedbackjson)) {
        return [];
    }

    $decoded = json_decode($feedbackjson, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Return area-level feedback text keyed by rubric area.
 *
 * @param stdClass|null $grade
 * @return array
 */
function aidiscussion_get_grade_area_feedback_map(?stdClass $grade): array {
    if (!$grade) {
        return [];
    }

    $feedbackdata = aidiscussion_decode_feedback_json((string)($grade->feedbackjson ?? ''));
    $areas = [];

    foreach (aidiscussion_get_rubric_area_definitions() as $area => $definition) {
        $feedback = trim((string)($feedbackdata['areas'][$area]['feedback'] ?? ''));
        if ($feedback === '' && isset($feedbackdata[$area])) {
            $feedback = trim((string)$feedbackdata[$area]);
        }
        if ($feedback !== '') {
            $areas[$area] = $feedback;
        }
    }

    return $areas;
}

/**
 * Return a human-readable label for the grade source.
 *
 * @param stdClass|null $grade
 * @return string
 */
function aidiscussion_get_grade_source_label(?stdClass $grade): string {
    if (!$grade) {
        return get_string('notapplicable', 'mod_aidiscussion');
    }

    if (!empty($grade->isoverrideactive) || (string)($grade->providercomponent ?? '') === 'teacher-override') {
        return get_string('teacheroverride', 'mod_aidiscussion');
    }

    if ((string)($grade->providercomponent ?? '') === '' || (string)($grade->modelname ?? '') === 'heuristic-v1') {
        return get_string('heuristicgrade', 'mod_aidiscussion');
    }

    return provider_registry::get_provider_name((string)$grade->providercomponent);
}

/**
 * Return available criterion score granularity options.
 *
 * @return array
 */
function aidiscussion_get_grading_granularity_options(): array {
    return [
        'exact' => get_string('gradinggranularityexact', 'mod_aidiscussion'),
        'half' => get_string('gradinggranularityhalf', 'mod_aidiscussion'),
        'whole' => get_string('gradinggranularitywhole', 'mod_aidiscussion'),
    ];
}

/**
 * Normalise a configured criterion score granularity value.
 *
 * @param string $value
 * @return string
 */
function aidiscussion_normalise_grading_granularity(string $value): string {
    $value = trim($value);
    $options = aidiscussion_get_grading_granularity_options();
    return isset($options[$value]) ? $value : 'half';
}

/**
 * Return the built-in rubric templates.
 *
 * @return array
 */
function aidiscussion_get_default_rubric_templates(): array {
    return [
        'initial' => [
            'instructions' => 'Evaluate whether the learner addresses the teacher prompt with a clear, developed response.',
            'criteria' => [
                [
                    'shortname' => 'Addresses the prompt',
                    'maxscore' => 4.0,
                    'description' => 'Directly answers the teacher prompt and stays on topic.',
                ],
                [
                    'shortname' => 'Support and reasoning',
                    'maxscore' => 3.0,
                    'description' => 'Uses reasons, examples, or evidence to support the response.',
                ],
                [
                    'shortname' => 'Clarity and completeness',
                    'maxscore' => 3.0,
                    'description' => 'Communicates clearly and develops the idea enough for discussion.',
                ],
            ],
        ],
        'ai' => [
            'instructions' => 'Evaluate how well the learner engages with the AI follow-up and extends their thinking.',
            'criteria' => [
                [
                    'shortname' => 'Responds substantively',
                    'maxscore' => 4.0,
                    'description' => 'Answers the AI follow-up with more than a brief acknowledgement.',
                ],
                [
                    'shortname' => 'Extends thinking',
                    'maxscore' => 3.0,
                    'description' => 'Adds reflection, clarification, or a new example.',
                ],
                [
                    'shortname' => 'Uses feedback productively',
                    'maxscore' => 3.0,
                    'description' => 'Builds on the AI response in a meaningful way.',
                ],
            ],
        ],
        'peer' => [
            'instructions' => 'Evaluate whether the learner contributes constructively to peer discussion.',
            'criteria' => [
                [
                    'shortname' => 'Engages a peer directly',
                    'maxscore' => 4.0,
                    'description' => 'Responds to a classmate\'s specific idea or question.',
                ],
                [
                    'shortname' => 'Adds value to the discussion',
                    'maxscore' => 3.0,
                    'description' => 'Moves the discussion forward with evidence, a question, or a new perspective.',
                ],
                [
                    'shortname' => 'Professional discussion style',
                    'maxscore' => 3.0,
                    'description' => 'Uses respectful, constructive language.',
                ],
            ],
        ],
    ];
}

/**
 * Format rubric criteria for the textarea-based editor.
 *
 * @param array $criteria
 * @return string
 */
function aidiscussion_format_rubric_criteria_text(array $criteria): string {
    $lines = [];
    foreach ($criteria as $criterion) {
        $shortname = trim((string)($criterion['shortname'] ?? $criterion->shortname ?? ''));
        $maxscore = (float)($criterion['maxscore'] ?? $criterion->maxscore ?? 0);
        $description = trim((string)($criterion['description'] ?? $criterion->description ?? ''));
        if ($shortname === '' || $maxscore <= 0) {
            continue;
        }
        $lines[] = $shortname . ' | ' . format_float($maxscore, -1, false) . ' | ' . $description;
    }

    return implode("\n", $lines);
}

/**
 * Parse rubric criteria text from the activity form.
 *
 * @param string $text
 * @return array
 */
function aidiscussion_parse_rubric_criteria_text(string $text): array {
    $criteria = [];
    $lines = preg_split('/\r\n|\r|\n/', trim($text));

    foreach ($lines as $index => $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $parts = array_map('trim', explode('|', $line, 3));
        if (count($parts) < 2) {
            throw new invalid_parameter_exception(get_string('errrubriccriterionformat', 'mod_aidiscussion', $index + 1));
        }

        $shortname = trim((string)$parts[0]);
        $maxscore = trim((string)$parts[1]);
        $description = trim((string)($parts[2] ?? ''));

        if ($shortname === '') {
            throw new invalid_parameter_exception(get_string('errrubriccriterionname', 'mod_aidiscussion', $index + 1));
        }

        if (!is_numeric($maxscore) || (float)$maxscore <= 0) {
            throw new invalid_parameter_exception(get_string('errrubriccriterionscore', 'mod_aidiscussion', $index + 1));
        }

        $criteria[] = [
            'sortorder' => count($criteria),
            'shortname' => $shortname,
            'description' => $description,
            'maxscore' => round((float)$maxscore, 5),
        ];
    }

    return $criteria;
}

/**
 * Return rubric form defaults for the activity.
 *
 * @param int $aidiscussionid
 * @return array
 */
function aidiscussion_get_rubric_form_defaults(int $aidiscussionid = 0): array {
    $defaults = [];
    $rubrics = $aidiscussionid ? aidiscussion_get_stored_rubrics($aidiscussionid) : [];
    $templates = aidiscussion_get_default_rubric_templates();

    foreach (aidiscussion_get_rubric_area_definitions() as $area => $definition) {
        $rubric = $rubrics[$area] ?? null;
        $instructionsfield = $area . 'rubricinstructions';
        $criteriafield = $area . 'rubriccriteria';

        if ($rubric && !empty($rubric->criteria)) {
            $defaults[$instructionsfield] = (string)$rubric->instructions;
            $defaults[$criteriafield] = aidiscussion_format_rubric_criteria_text($rubric->criteria);
            continue;
        }

        $defaults[$instructionsfield] = $templates[$area]['instructions'];
        $defaults[$criteriafield] = aidiscussion_format_rubric_criteria_text($templates[$area]['criteria']);
    }

    return $defaults;
}

/**
 * Validate rubric fields from the activity form.
 *
 * @param array $data
 * @return array
 */
function aidiscussion_validate_rubric_form_data(array $data): array {
    $errors = [];

    foreach (aidiscussion_get_rubric_area_definitions() as $area => $definition) {
        $criteriafield = $area . 'rubriccriteria';
        $criteriatext = trim((string)($data[$criteriafield] ?? ''));
        if ($criteriatext === '') {
            continue;
        }

        try {
            aidiscussion_parse_rubric_criteria_text($criteriatext);
        } catch (invalid_parameter_exception $e) {
            $errors[$criteriafield] = $e->getMessage();
        }
    }

    return $errors;
}

/**
 * Load saved rubrics for an activity.
 *
 * @param int $aidiscussionid
 * @return array
 */
function aidiscussion_get_stored_rubrics(int $aidiscussionid): array {
    global $DB;

    $rubrics = $DB->get_records('aidiscussion_rubrics', ['aidiscussionid' => $aidiscussionid], '', '*');
    if (!$rubrics) {
        return [];
    }

    $criteria = [];
    [$sql, $params] = $DB->get_in_or_equal(array_keys($rubrics), SQL_PARAMS_NAMED);
    $records = $DB->get_records_select('aidiscussion_criteria', "rubricid {$sql}", $params, 'rubricid ASC, sortorder ASC, id ASC');
    foreach ($records as $record) {
        $criteria[$record->rubricid][] = $record;
    }

    $loaded = [];
    foreach ($rubrics as $rubric) {
        $rubric->criteria = $criteria[$rubric->id] ?? [];
        $loaded[$rubric->rubricarea] = $rubric;
    }

    return $loaded;
}

/**
 * Return the effective rubrics for an activity, falling back to built-in templates.
 *
 * @param stdClass $aidiscussion
 * @return array
 */
function aidiscussion_get_effective_rubrics(stdClass $aidiscussion): array {
    $stored = !empty($aidiscussion->id) ? aidiscussion_get_stored_rubrics((int)$aidiscussion->id) : [];
    $templates = aidiscussion_get_default_rubric_templates();
    $rubrics = [];

    foreach (aidiscussion_get_rubric_area_definitions() as $area => $definition) {
        if (!empty($stored[$area]) && !empty($stored[$area]->criteria)) {
            $rubrics[$area] = $stored[$area];
            continue;
        }

        $criteria = [];
        $maxscore = 0.0;
        foreach ($templates[$area]['criteria'] as $index => $criterion) {
            $maxscore += (float)$criterion['maxscore'];
            $criteria[] = (object) [
                'id' => 0,
                'rubricid' => 0,
                'sortorder' => $index,
                'shortname' => $criterion['shortname'],
                'description' => $criterion['description'],
                'maxscore' => (float)$criterion['maxscore'],
                'timecreated' => 0,
                'timemodified' => 0,
            ];
        }

        $rubrics[$area] = (object) [
            'id' => 0,
            'aidiscussionid' => (int)($aidiscussion->id ?? 0),
            'rubricarea' => $area,
            'name' => $definition['label'],
            'description' => '',
            'instructions' => $templates[$area]['instructions'],
            'maxscore' => $maxscore,
            'criteria' => $criteria,
            'timecreated' => 0,
            'timemodified' => 0,
        ];
    }

    return $rubrics;
}

/**
 * Persist rubric definitions from the activity form.
 *
 * @param int $aidiscussionid
 * @param stdClass $data
 * @return void
 */
function aidiscussion_save_rubrics_from_form(int $aidiscussionid, stdClass $data): void {
    global $DB;

    $existing = aidiscussion_get_stored_rubrics($aidiscussionid);
    $validareas = array_keys(aidiscussion_get_rubric_area_definitions());

    foreach (aidiscussion_get_rubric_area_definitions() as $area => $definition) {
        $instructions = trim((string)($data->{$area . 'rubricinstructions'} ?? ''));
        $criteriatext = trim((string)($data->{$area . 'rubriccriteria'} ?? ''));
        $criteria = $criteriatext === '' ? [] : aidiscussion_parse_rubric_criteria_text($criteriatext);
        $rubric = $existing[$area] ?? null;

        if (empty($criteria)) {
            if ($rubric) {
                $DB->delete_records('aidiscussion_criteria', ['rubricid' => $rubric->id]);
                $DB->delete_records('aidiscussion_rubrics', ['id' => $rubric->id]);
            }
            continue;
        }

        $maxscore = array_sum(array_column($criteria, 'maxscore'));
        $record = (object) [
            'aidiscussionid' => $aidiscussionid,
            'rubricarea' => $area,
            'name' => $definition['label'],
            'description' => '',
            'instructions' => $instructions,
            'maxscore' => round((float)$maxscore, 5),
            'timemodified' => time(),
        ];

        if ($rubric) {
            $record->id = $rubric->id;
            $DB->update_record('aidiscussion_rubrics', $record);
            $rubricid = $rubric->id;
            $DB->delete_records('aidiscussion_criteria', ['rubricid' => $rubricid]);
        } else {
            $record->timecreated = time();
            $rubricid = $DB->insert_record('aidiscussion_rubrics', $record);
        }

        foreach ($criteria as $index => $criterion) {
            $criterionrecord = (object) [
                'rubricid' => $rubricid,
                'sortorder' => $index,
                'shortname' => $criterion['shortname'],
                'description' => $criterion['description'],
                'maxscore' => $criterion['maxscore'],
                'timecreated' => time(),
                'timemodified' => time(),
            ];
            $DB->insert_record('aidiscussion_criteria', $criterionrecord);
        }
    }

    foreach ($existing as $area => $rubric) {
        if (!in_array($area, $validareas, true)) {
            $DB->delete_records('aidiscussion_criteria', ['rubricid' => $rubric->id]);
            $DB->delete_records('aidiscussion_rubrics', ['id' => $rubric->id]);
        }
    }
}

/**
 * Return whether the current or specified user can manage the activity.
 *
 * @param context_module $context
 * @param int $userid
 * @return bool
 */
function aidiscussion_user_can_manage(context_module $context, int $userid = 0): bool {
    return has_capability('mod/aidiscussion:manageai', $context, $userid) ||
        has_capability('mod/aidiscussion:grade', $context, $userid);
}

/**
 * Convert formatted text to plain text.
 *
 * @param string $text
 * @param int $format
 * @return string
 */
function aidiscussion_to_plain_text(string $text, int $format = FORMAT_HTML): string {
    if ((int)$format === (int)FORMAT_HTML) {
        $text = html_to_text($text, 0, false);
        if (preg_match('/<[^>]+>/', $text)) {
            $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
            $text = preg_replace('/<\/?(p|div|li|ul|ol|blockquote|h[1-6]|tr|table)[^>]*>/i', "\n", $text);
            $text = strip_tags($text);
        }
    }

    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);

    return trim((string)$text);
}

/**
 * Count words in post content.
 *
 * @param string $content
 * @return int
 */
function aidiscussion_count_words(string $content): int {
    $plain = aidiscussion_to_plain_text($content, FORMAT_HTML);
    if ($plain === '') {
        return 0;
    }

    preg_match_all('/\p{L}[\p{L}\p{N}\']*/u', $plain, $matches);
    return count($matches[0]);
}

/**
 * Normalize content for hashing and simple comparisons.
 *
 * @param string $content
 * @return string
 */
function aidiscussion_normalise_text(string $content): string {
    $plain = core_text::strtolower(aidiscussion_to_plain_text($content, FORMAT_HTML));
    $plain = preg_replace('/\s+/u', ' ', $plain);
    return trim((string)$plain);
}

/**
 * Build a stable content hash.
 *
 * @param string $content
 * @return string
 */
function aidiscussion_hash_content(string $content): string {
    $normalized = aidiscussion_normalise_text($content);
    return $normalized === '' ? '' : hash('sha256', $normalized);
}

/**
 * Determine whether content is just a trivial acknowledgement.
 *
 * @param string $content
 * @return bool
 */
function aidiscussion_is_trivial_acknowledgement(string $content): bool {
    $normalized = aidiscussion_normalise_text($content);
    if ($normalized === '') {
        return true;
    }

    $trivialphrases = [
        'ok',
        'okay',
        'k',
        'thanks',
        'thank you',
        'i agree',
        'agreed',
        'good point',
        'nice',
        'sounds good',
        'great',
        'cool',
        'yes',
        'no',
    ];

    if (in_array($normalized, $trivialphrases, true)) {
        return true;
    }

    return aidiscussion_count_words($normalized) < 3;
}

/**
 * Determine whether a post is substantive enough for AI and grading.
 *
 * @param stdClass $aidiscussion
 * @param string $content
 * @param int|null $wordcount
 * @return bool
 */
function aidiscussion_is_substantive_post(stdClass $aidiscussion, string $content, ?int $wordcount = null): bool {
    $wordcount = $wordcount ?? aidiscussion_count_words($content);
    if ($wordcount < max(1, (int)$aidiscussion->minsubstantivewords)) {
        return false;
    }

    return !aidiscussion_is_trivial_acknowledgement($content);
}

/**
 * Limit text length for prompt assembly.
 *
 * @param string $text
 * @param int $maxchars
 * @return string
 */
function aidiscussion_limit_text(string $text, int $maxchars): string {
    if (core_text::strlen($text) <= $maxchars) {
        return $text;
    }

    return core_text::substr($text, 0, $maxchars - 1) . '…';
}

/**
 * Build a pseudonym for AI prompts.
 *
 * @param stdClass $aidiscussion
 * @param int $userid
 * @return string
 */
function aidiscussion_build_pseudonym(stdClass $aidiscussion, int $userid): string {
    global $CFG;

    $seed = $CFG->siteidentifier . ':' . $aidiscussion->id . ':' . $userid;
    return 'Learner ' . strtoupper(substr(hash('sha256', $seed), 0, 8));
}

/**
 * Get the student's initial top-level post.
 *
 * @param int $aidiscussionid
 * @param int $userid
 * @return stdClass|null
 */
function aidiscussion_get_initial_post(int $aidiscussionid, int $userid): ?stdClass {
    global $DB;

    $post = $DB->get_record('aidiscussion_posts', [
        'aidiscussionid' => $aidiscussionid,
        'userid' => $userid,
        'parentid' => 0,
        'posttype' => 'student',
    ], '*', IGNORE_MULTIPLE);

    return $post ?: null;
}

/**
 * Return whether the user has posted an initial response.
 *
 * @param int $aidiscussionid
 * @param int $userid
 * @return bool
 */
function aidiscussion_user_has_initial_post(int $aidiscussionid, int $userid): bool {
    return aidiscussion_get_initial_post($aidiscussionid, $userid) !== null;
}

/**
 * Resolve the configured display name for the activity AI persona.
 *
 * @param stdClass $aidiscussion
 * @return string
 */
function aidiscussion_get_ai_display_name(stdClass $aidiscussion): string {
    $name = trim((string)($aidiscussion->aidisplayname ?? ''));
    return $name !== '' ? $name : 'AI facilitator';
}

/**
 * Resolve the configured provider for grading.
 *
 * @param stdClass $aidiscussion
 * @return string
 */
function aidiscussion_get_grade_provider_component(stdClass $aidiscussion): string {
    $provider = trim((string)($aidiscussion->gradeprovider ?? ''));
    if ($provider === '') {
        $provider = trim((string)($aidiscussion->replyprovider ?? ''));
    }

    return $provider;
}

/**
 * Return whether grading should use an AI provider.
 *
 * @param stdClass $aidiscussion
 * @return bool
 */
function aidiscussion_uses_provider_grading(stdClass $aidiscussion): bool {
    return !empty($aidiscussion->aienabled) && aidiscussion_get_grade_provider_component($aidiscussion) !== '';
}

/**
 * Return whether the teacher exemplar should influence grading.
 *
 * @param stdClass $aidiscussion
 * @return bool
 */
function aidiscussion_use_teacher_exemplar_for_grading(stdClass $aidiscussion): bool {
    return !empty($aidiscussion->useexemplarforgrading);
}

/**
 * Return the configured grading temperature.
 *
 * @param stdClass $aidiscussion
 * @return float
 */
function aidiscussion_get_grading_temperature(stdClass $aidiscussion): float {
    return max(0.0, min(1.0, (float)($aidiscussion->gradingtemperature ?? 0.0)));
}

/**
 * Return the configured criterion score granularity.
 *
 * @param stdClass $aidiscussion
 * @return string
 */
function aidiscussion_get_grading_granularity(stdClass $aidiscussion): string {
    return aidiscussion_normalise_grading_granularity((string)($aidiscussion->gradinggranularity ?? 'half'));
}

/**
 * Return the numeric score step enforced by the selected grading granularity.
 *
 * @param stdClass $aidiscussion
 * @return float
 */
function aidiscussion_get_grading_score_step(stdClass $aidiscussion): float {
    return match (aidiscussion_get_grading_granularity($aidiscussion)) {
        'whole' => 1.0,
        'half' => 0.5,
        default => 0.0,
    };
}

/**
 * Return the effective criterion score tolerance for benchmark comparison.
 *
 * @param stdClass $aidiscussion
 * @return float
 */
function aidiscussion_get_benchmark_criterion_tolerance(stdClass $aidiscussion): float {
    return match (aidiscussion_get_grading_granularity($aidiscussion)) {
        'whole' => 1.0,
        'half' => 0.5,
        default => 0.25,
    };
}

/**
 * Return the effective final grade tolerance for benchmark comparison.
 *
 * @param stdClass $aidiscussion
 * @return float
 */
function aidiscussion_get_benchmark_final_tolerance(stdClass $aidiscussion): float {
    return max(1.0, round(((float)($aidiscussion->grade ?? 100)) * 0.05, 2));
}

/**
 * Return whether the user must accept the Moodle AI policy before posting.
 *
 * @param stdClass $aidiscussion
 * @param int $userid
 * @return bool
 */
function aidiscussion_user_requires_ai_policy(stdClass $aidiscussion, int $userid): bool {
    if (empty($aidiscussion->aienabled) || trim((string)$aidiscussion->replyprovider) === '') {
        return false;
    }

    if (!class_exists(core_ai_manager::class)) {
        return false;
    }

    return !core_ai_manager::get_user_policy_status($userid);
}

/**
 * Resolve a display name for a post author.
 *
 * @param stdClass $post
 * @return string
 */
function aidiscussion_get_post_author_name(stdClass $post): string {
    if ($post->posttype === 'ai') {
        if (trim((string)($post->aidisplayname ?? '')) !== '') {
            return (string)$post->aidisplayname;
        }

        return get_string('aifacilitator', 'mod_aidiscussion');
    }

    if (!empty($post->userid)) {
        return fullname($post);
    }

    if ($post->authorrole === 'teacher') {
        return get_string('teacher', 'mod_aidiscussion');
    }

    return get_string('unknownauthor', 'mod_aidiscussion');
}

/**
 * Load all posts for an activity with derived thread metadata.
 *
 * @param int $aidiscussionid
 * @return array
 */
function aidiscussion_load_posts(int $aidiscussionid): array {
    global $DB;

    $sql = "SELECT p.*,
                   d.aidisplayname,
                   u.firstname,
                   u.lastname,
                   u.middlename,
                   u.firstnamephonetic,
                   u.lastnamephonetic,
                   u.alternatename,
                   u.picture,
                   u.imagealt
              FROM {aidiscussion_posts} p
        INNER JOIN {aidiscussion} d
                ON d.id = p.aidiscussionid
         LEFT JOIN {user} u
                ON u.id = p.userid
             WHERE p.aidiscussionid = :aidiscussionid
          ORDER BY p.timecreated ASC, p.id ASC";
    $records = $DB->get_records_sql($sql, ['aidiscussionid' => $aidiscussionid]);

    $posts = [];
    foreach ($records as $record) {
        $record->children = [];
        $record->depth = 0;
        $record->rootid = 0;
        $record->threadownerid = 0;
        $record->authorname = aidiscussion_get_post_author_name($record);
        $posts[$record->id] = $record;
    }

    foreach ($posts as $id => $post) {
        if (!empty($post->parentid) && isset($posts[$post->parentid])) {
            $parent = $posts[$post->parentid];
            $post->depth = $parent->depth + 1;
            $post->rootid = $parent->rootid ?: $parent->id;
            $post->threadownerid = $parent->threadownerid ?: (int)$parent->userid;
        } else {
            $post->depth = 0;
            $post->rootid = $post->id;
            $post->threadownerid = (int)$post->userid;
        }
        $posts[$id] = $post;
    }

    return $posts;
}

/**
 * Return whether the viewer is locked out from peers until they post.
 *
 * @param stdClass $aidiscussion
 * @param int $userid
 * @param bool $canmanage
 * @return bool
 */
function aidiscussion_is_postbeforeview_locked(stdClass $aidiscussion, int $userid, bool $canmanage = false): bool {
    if ($canmanage || empty($aidiscussion->postbeforeview)) {
        return false;
    }

    return !aidiscussion_user_has_initial_post($aidiscussion->id, $userid);
}

/**
 * Check whether the viewer may see the post.
 *
 * @param stdClass $aidiscussion
 * @param stdClass $post
 * @param int $userid
 * @param bool $hasinitialpost
 * @param bool $canmanage
 * @return bool
 */
function aidiscussion_can_view_post(
    stdClass $aidiscussion,
    stdClass $post,
    int $userid,
    bool $hasinitialpost,
    bool $canmanage,
): bool {
    if ($canmanage) {
        return true;
    }

    if (!empty($aidiscussion->postbeforeview) && !$hasinitialpost) {
        if ((int)$post->userid !== $userid && (int)$post->threadownerid !== $userid) {
            return false;
        }
    }

    if ($post->visibility === 'public') {
        return true;
    }

    return (int)$post->userid === $userid || (int)$post->threadownerid === $userid;
}

/**
 * Build a visible post tree for rendering.
 *
 * @param stdClass $aidiscussion
 * @param context_module $context
 * @param int $userid
 * @return array
 */
function aidiscussion_get_visible_post_tree(stdClass $aidiscussion, context_module $context, int $userid): array {
    $posts = aidiscussion_load_posts($aidiscussion->id);
    $canmanage = aidiscussion_user_can_manage($context, $userid);
    $hasinitialpost = aidiscussion_user_has_initial_post($aidiscussion->id, $userid);

    $visibleposts = [];
    foreach ($posts as $post) {
        if (aidiscussion_can_view_post($aidiscussion, $post, $userid, $hasinitialpost, $canmanage)) {
            $post->children = [];
            $visibleposts[$post->id] = $post;
        }
    }

    $roots = [];
    foreach ($visibleposts as $id => $post) {
        if (!empty($post->parentid) && isset($visibleposts[$post->parentid])) {
            $visibleposts[$post->parentid]->children[] = $id;
        } else {
            $roots[] = $id;
        }
    }

    return [
        'posts' => $visibleposts,
        'roots' => $roots,
        'hasinitialpost' => $hasinitialpost,
        'canmanage' => $canmanage,
        'locked' => aidiscussion_is_postbeforeview_locked($aidiscussion, $userid, $canmanage),
    ];
}

/**
 * Return a permission decision for posting or replying.
 *
 * @param stdClass $aidiscussion
 * @param context_module $context
 * @param int $userid
 * @param stdClass|null $parent
 * @param bool|null $hasinitialpost
 * @param bool|null $canmanage
 * @return array
 */
function aidiscussion_get_posting_permission(
    stdClass $aidiscussion,
    context_module $context,
    int $userid,
    ?stdClass $parent = null,
    ?bool $hasinitialpost = null,
    ?bool $canmanage = null,
): array {
    $canmanage = $canmanage ?? aidiscussion_user_can_manage($context, $userid);
    $hasinitialpost = $hasinitialpost ?? aidiscussion_user_has_initial_post($aidiscussion->id, $userid);

    if (!has_capability('mod/aidiscussion:post', $context, $userid)) {
        return [
            'allowed' => false,
            'reason' => get_string('postingnotallowed', 'mod_aidiscussion'),
        ];
    }

    if (aidiscussion_user_requires_ai_policy($aidiscussion, $userid)) {
        return [
            'allowed' => false,
            'reason' => get_string('aipolicyrequiredtopost', 'mod_aidiscussion', aidiscussion_get_ai_display_name($aidiscussion)),
        ];
    }

    if ($parent === null) {
        if (!$canmanage && $hasinitialpost) {
            return [
                'allowed' => false,
                'reason' => get_string('alreadypostedinitial', 'mod_aidiscussion'),
            ];
        }

        return ['allowed' => true, 'reason' => ''];
    }

    if (
        !$canmanage && $parent->visibility === 'private' && (int)$parent->threadownerid !== $userid &&
            (int)$parent->userid !== $userid
    ) {
        return [
            'allowed' => false,
            'reason' => get_string('privatebranchrestricted', 'mod_aidiscussion'),
        ];
    }

    if ($parent->posttype === 'ai') {
        if ($canmanage || (int)$parent->threadownerid === $userid || (int)$parent->userid === $userid) {
            return ['allowed' => true, 'reason' => ''];
        }

        return [
            'allowed' => false,
            'reason' => get_string('onlythreadownercanreplyai', 'mod_aidiscussion'),
        ];
    }

    $ispeerreply = !empty($parent->userid) && (int)$parent->userid !== $userid;
    if ($ispeerreply) {
        if (empty($aidiscussion->allowpeerreplies)) {
            return [
                'allowed' => false,
                'reason' => get_string('peerrepliesdisabled', 'mod_aidiscussion'),
            ];
        }

        if (!has_capability('mod/aidiscussion:replypeer', $context, $userid) && !$canmanage) {
            return [
                'allowed' => false,
                'reason' => get_string('peerreplynotallowed', 'mod_aidiscussion'),
            ];
        }

        if (!$canmanage && !empty($aidiscussion->requireinitialpost) && !$hasinitialpost) {
            return [
                'allowed' => false,
                'reason' => get_string('initialpostrequiredbeforepeerreply', 'mod_aidiscussion'),
            ];
        }
    }

    return ['allowed' => true, 'reason' => ''];
}

/**
 * Create a student or teacher post.
 *
 * @param stdClass $aidiscussion
 * @param context_module $context
 * @param int $userid
 * @param string $content
 * @param int $parentid
 * @param int $contentformat
 * @return stdClass
 */
function aidiscussion_create_post(
    stdClass $aidiscussion,
    context_module $context,
    int $userid,
    string $content,
    int $parentid = 0,
    int $contentformat = FORMAT_PLAIN,
): stdClass {
    global $DB;

    $content = trim($content);
    $posts = aidiscussion_load_posts($aidiscussion->id);
    $parent = null;

    if ($parentid) {
        if (!isset($posts[$parentid])) {
            throw new moodle_exception('invalidparentpost', 'mod_aidiscussion');
        }
        $parent = $posts[$parentid];
    }

    $permission = aidiscussion_get_posting_permission($aidiscussion, $context, $userid, $parent);
    if (empty($permission['allowed'])) {
        throw new moodle_exception('cannotposthere', 'mod_aidiscussion', '', null, $permission['reason']);
    }

    $canmanage = aidiscussion_user_can_manage($context, $userid);
    $authorrole = $canmanage ? 'teacher' : 'student';
    $visibility = $parent ? $parent->visibility : 'public';
    $wordcount = aidiscussion_count_words($content);

    $record = (object) [
        'aidiscussionid' => $aidiscussion->id,
        'userid' => $userid,
        'authorrole' => $authorrole,
        'parentid' => $parentid,
        'visibility' => $visibility,
        'posttype' => $authorrole,
        'content' => $content,
        'contentformat' => $contentformat,
        'wordcount' => $wordcount,
        'issubstantive' => (int)aidiscussion_is_substantive_post($aidiscussion, $content, $wordcount),
        'providercomponent' => '',
        'modelname' => '',
        'pseudonym' => $authorrole === 'student' && !empty($aidiscussion->pseudonymiseusers) ?
            aidiscussion_build_pseudonym($aidiscussion, $userid) : null,
        'contenthash' => aidiscussion_hash_content($content),
        'timecreated' => time(),
        'timemodified' => time(),
    ];

    $record->id = $DB->insert_record('aidiscussion_posts', $record);

    $posts = aidiscussion_load_posts($aidiscussion->id);
    return $posts[$record->id];
}

/**
 * Handle follow-on work after a user submits a post.
 *
 * @param stdClass $aidiscussion
 * @param stdClass $cm
 * @param context_module $context
 * @param stdClass $post
 * @return void
 */
function aidiscussion_after_post_created(
    stdClass $aidiscussion,
    stdClass $cm,
    context_module $context,
    stdClass $post,
): void {
    if (!empty($post->userid) && $post->authorrole === 'student') {
        aidiscussion_recalculate_grade_record($aidiscussion, (int)$post->userid, [
            'forceheuristic' => aidiscussion_uses_provider_grading($aidiscussion),
        ]);

        if (aidiscussion_uses_provider_grading($aidiscussion)) {
            aidiscussion_queue_grading_job($aidiscussion, (int)$post->userid);
        }
    }

    if (aidiscussion_should_queue_ai_reply($aidiscussion, $post)) {
        aidiscussion_queue_ai_reply_job($aidiscussion, $post);
    }
}

/**
 * Decide whether a student post should trigger an AI reply.
 *
 * @param stdClass $aidiscussion
 * @param stdClass $post
 * @return bool
 */
function aidiscussion_should_queue_ai_reply(stdClass $aidiscussion, stdClass $post): bool {
    if ($post->authorrole !== 'student' || empty($post->userid)) {
        return false;
    }

    if (empty($aidiscussion->aienabled) || trim((string)$aidiscussion->replyprovider) === '') {
        return false;
    }

    if (!class_exists(core_ai_manager::class) || !core_ai_manager::get_user_policy_status((int)$post->userid)) {
        return false;
    }

    if (empty($post->issubstantive) || aidiscussion_is_trivial_acknowledgement($post->content)) {
        return false;
    }

    if (
        aidiscussion_count_reply_jobs_for_user($aidiscussion->id, (int)$post->userid) >=
            max(1, (int)$aidiscussion->maxairepliesperstudent)
    ) {
        return false;
    }

    if (empty($post->parentid)) {
        return true;
    }

    $posts = aidiscussion_load_posts($aidiscussion->id);
    $parent = $posts[$post->parentid] ?? null;
    if (!$parent) {
        return true;
    }

    if ($parent->posttype === 'ai') {
        return true;
    }

    if ((int)$parent->userid !== (int)$post->userid) {
        return !empty($aidiscussion->replytopeerreplies);
    }

    return true;
}

/**
 * Count AI reply jobs already created for a learner.
 *
 * @param int $aidiscussionid
 * @param int $userid
 * @return int
 */
function aidiscussion_count_reply_jobs_for_user(int $aidiscussionid, int $userid): int {
    global $DB;

    [$statussql, $statusparams] = $DB->get_in_or_equal(['queued', 'running', 'completed'], SQL_PARAMS_NAMED);
    $params = [
        'aidiscussionid' => $aidiscussionid,
        'userid' => $userid,
        'jobtype' => 'reply',
    ] + $statusparams;

    return (int)$DB->count_records_select(
        'aidiscussion_jobs',
        "aidiscussionid = :aidiscussionid
         AND userid = :userid
         AND jobtype = :jobtype
         AND status {$statussql}",
        $params
    );
}

/**
 * Queue an AI reply job.
 *
 * @param stdClass $aidiscussion
 * @param stdClass $post
 * @return int
 */
function aidiscussion_queue_ai_reply_job(stdClass $aidiscussion, stdClass $post): int {
    global $DB;

    $job = (object) [
        'aidiscussionid' => $aidiscussion->id,
        'postid' => $post->id,
        'userid' => (int)$post->userid,
        'jobtype' => 'reply',
        'status' => 'queued',
        'attempts' => 0,
        'lastmessage' => '',
        'runnerprovider' => (string)$aidiscussion->replyprovider,
        'timecreated' => time(),
        'timestarted' => 0,
        'timecompleted' => 0,
    ];

    $job->id = $DB->insert_record('aidiscussion_jobs', $job);

    $task = new process_ai_reply();
    $task->set_component('mod_aidiscussion');
    $task->set_custom_data([
        'jobid' => $job->id,
    ]);
    if (!empty($aidiscussion->aireplydelayminutes)) {
        $task->set_next_run_time(time() + ((int)$aidiscussion->aireplydelayminutes * MINSECS));
    }
    \core\task\manager::queue_adhoc_task($task);

    return (int)$job->id;
}

/**
 * Queue a grading recalculation job.
 *
 * @param stdClass $aidiscussion
 * @param int $userid
 * @return int
 */
function aidiscussion_queue_grading_job(stdClass $aidiscussion, int $userid): int {
    global $DB;

    $job = (object) [
        'aidiscussionid' => $aidiscussion->id,
        'postid' => 0,
        'userid' => $userid,
        'jobtype' => 'grading',
        'status' => 'queued',
        'attempts' => 0,
        'lastmessage' => '',
        'runnerprovider' => aidiscussion_get_grade_provider_component($aidiscussion),
        'timecreated' => time(),
        'timestarted' => 0,
        'timecompleted' => 0,
    ];

    $job->id = $DB->insert_record('aidiscussion_jobs', $job);

    $task = new process_grading();
    $task->set_component('mod_aidiscussion');
    $task->set_custom_data([
        'jobid' => $job->id,
    ]);
    \core\task\manager::queue_adhoc_task($task);

    return (int)$job->id;
}

/**
 * Mark a queued job as running.
 *
 * @param int $jobid
 * @param string $jobtype
 * @return stdClass|null
 */
function aidiscussion_claim_job(int $jobid, string $jobtype): ?stdClass {
    global $DB;

    $transaction = $DB->start_delegated_transaction();
    $job = $DB->get_record('aidiscussion_jobs', ['id' => $jobid], '*');

    if (!$job || $job->jobtype !== $jobtype || $job->status !== 'queued') {
        $transaction->allow_commit();
        return null;
    }

    $job->status = 'running';
    $job->attempts = (int)$job->attempts + 1;
    $job->timestarted = time();
    $DB->update_record('aidiscussion_jobs', $job);
    $transaction->allow_commit();

    return $job;
}

/**
 * Complete a queued job with status and message.
 *
 * @param stdClass $job
 * @param string $status
 * @param string $message
 * @return void
 */
function aidiscussion_finish_job(stdClass $job, string $status, string $message = ''): void {
    global $DB;

    $job->status = $status;
    $job->lastmessage = aidiscussion_limit_text($message, 1000);
    $job->timecompleted = time();
    $DB->update_record('aidiscussion_jobs', $job);
}

/**
 * Return pending AI reply jobs keyed by source post id.
 *
 * @param int $aidiscussionid
 * @return array
 */
function aidiscussion_get_pending_reply_jobs(int $aidiscussionid): array {
    global $DB;

    $records = $DB->get_records_select(
        'aidiscussion_jobs',
        "aidiscussionid = :aidiscussionid AND jobtype = :jobtype AND status IN ('queued', 'running')",
        [
            'aidiscussionid' => $aidiscussionid,
            'jobtype' => 'reply',
        ],
        'timecreated ASC',
        'id, postid, status'
    );

    $jobs = [];
    foreach ($records as $record) {
        $jobs[(int)$record->postid] = $record->status;
    }

    return $jobs;
}

/**
 * Get the direct branch history for a post.
 *
 * @param array $posts
 * @param int $postid
 * @return array
 */
function aidiscussion_get_branch_history(array $posts, int $postid): array {
    $branch = [];
    $seen = [];
    $current = $posts[$postid] ?? null;

    while ($current && empty($seen[$current->id])) {
        $branch[] = $current;
        $seen[$current->id] = true;

        if (empty($current->parentid) || !isset($posts[$current->parentid])) {
            break;
        }

        $current = $posts[$current->parentid];
    }

    return array_reverse($branch);
}

/**
 * Build compact memory turns for a student within an activity.
 *
 * @param array $posts
 * @param int $userid
 * @param int $currentpostid
 * @return array
 */
function aidiscussion_get_student_memory_posts(array $posts, int $userid, int $currentpostid): array {
    $memory = [];

    foreach ($posts as $post) {
        if ($post->id === $currentpostid) {
            break;
        }

        if ((int)$post->userid === $userid || ($post->posttype === 'ai' && (int)$post->threadownerid === $userid)) {
            $memory[] = $post;
        }
    }

    return array_slice($memory, -6);
}

/**
 * Build a human-readable label for AI prompt history.
 *
 * @param stdClass $post
 * @return string
 */
function aidiscussion_get_prompt_speaker_label(stdClass $post): string {
    if ($post->posttype === 'ai') {
        return trim((string)($post->aidisplayname ?? '')) !== '' ? (string)$post->aidisplayname : 'AI facilitator';
    }

    if ($post->authorrole === 'teacher') {
        return 'Teacher';
    }

    if (!empty($post->pseudonym)) {
        return $post->pseudonym;
    }

    return 'Student';
}

/**
 * Build the AI reply prompt for a student post.
 *
 * @param stdClass $aidiscussion
 * @param array $posts
 * @param stdClass $post
 * @return string
 */
function aidiscussion_build_ai_reply_prompt(stdClass $aidiscussion, array $posts, stdClass $post): string {
    $aidisplayname = aidiscussion_get_ai_display_name($aidiscussion);
    $teacherprompt = aidiscussion_to_plain_text((string)$aidiscussion->prompt, (int)$aidiscussion->promptformat);
    $teacherexample = aidiscussion_to_plain_text(
        (string)($aidiscussion->teacherexample ?? ''),
        (int)($aidiscussion->teacherexampleformat ?? FORMAT_HTML)
    );
    $branch = aidiscussion_get_branch_history($posts, $post->id);
    $memory = aidiscussion_get_student_memory_posts($posts, (int)$post->userid, $post->id);

    $historyids = [];
    $historylines = [];
    foreach (array_merge($memory, $branch) as $historypost) {
        if (!empty($historyids[$historypost->id])) {
            continue;
        }
        $historyids[$historypost->id] = true;
        $speaker = aidiscussion_get_prompt_speaker_label($historypost);
        $content = aidiscussion_limit_text(
            aidiscussion_to_plain_text((string)$historypost->content, (int)$historypost->contentformat),
            900
        );
        if ($content !== '') {
            $historylines[] = $speaker . ': ' . $content;
        }
    }

    $instructions = trim((string)$aidiscussion->responseinstructions);
    $tone = trim((string)$aidiscussion->responsetone);

    $lines = [
        'You are ' . $aidisplayname . ' for a Moodle discussion activity.',
        'Respond in plain text only.',
        'Do not use markdown, bullet lists, or greetings.',
        'Keep the response between 60 and 180 words.',
        'Acknowledge the student\'s idea directly, extend the thinking, and ask at most one follow-up question.',
    ];

    if ($tone !== '') {
        $lines[] = 'Tone: ' . $tone . '.';
    }

    if ($instructions !== '') {
        $lines[] = 'Teacher reply instructions: ' . aidiscussion_limit_text(aidiscussion_to_plain_text($instructions), 1200);
    }

    $lines[] = 'Teacher prompt: ' . aidiscussion_limit_text($teacherprompt, 2000);
    if ($teacherexample !== '') {
        $lines[] = 'Teacher exemplar response: ' . aidiscussion_limit_text($teacherexample, 2200);
        $lines[] = 'Use the teacher exemplar as guidance for depth, framing, and preferred reasoning.';
        $lines[] = 'Do not copy it verbatim or mention that it was provided.';
    }
    $lines[] = 'Conversation history:';
    $lines[] = implode("\n", $historylines);
    $lines[] = 'Write the next AI reply now.';

    return aidiscussion_limit_text(implode("\n\n", array_filter($lines)), 12000);
}

/**
 * Create the stored AI post from a generated reply.
 *
 * @param stdClass $aidiscussion
 * @param stdClass $parent
 * @param int $targetuserid
 * @param string $content
 * @param string $providercomponent
 * @param string|null $modelname
 * @return stdClass
 */
function aidiscussion_create_ai_post(
    stdClass $aidiscussion,
    stdClass $parent,
    int $targetuserid,
    string $content,
    string $providercomponent,
    ?string $modelname = null,
): stdClass {
    global $DB;

    $content = trim(aidiscussion_to_plain_text($content, FORMAT_HTML));
    $record = (object) [
        'aidiscussionid' => $aidiscussion->id,
        'userid' => null,
        'authorrole' => 'ai',
        'parentid' => $parent->id,
        'visibility' => ($parent->visibility === 'private' || empty($aidiscussion->publicaireplies)) ? 'private' : 'public',
        'posttype' => 'ai',
        'content' => $content,
        'contentformat' => FORMAT_PLAIN,
        'wordcount' => aidiscussion_count_words($content),
        'issubstantive' => 1,
        'providercomponent' => $providercomponent,
        'modelname' => trim((string)$modelname),
        'pseudonym' => !empty($aidiscussion->pseudonymiseusers) ? aidiscussion_build_pseudonym($aidiscussion, $targetuserid) : null,
        'contenthash' => aidiscussion_hash_content($content),
        'timecreated' => time(),
        'timemodified' => time(),
    ];

    $record->id = $DB->insert_record('aidiscussion_posts', $record);

    $posts = aidiscussion_load_posts($aidiscussion->id);
    return $posts[$record->id];
}

/**
 * Compute the learner's current participation metrics.
 *
 * @param stdClass $aidiscussion
 * @param int $userid
 * @param array|null $posts
 * @return stdClass
 */
function aidiscussion_calculate_user_metrics(stdClass $aidiscussion, int $userid, ?array $posts = null): stdClass {
    $posts = $posts ?? aidiscussion_load_posts($aidiscussion->id);

    $studentposts = [];
    foreach ($posts as $post) {
        if ($post->authorrole === 'student' && (int)$post->userid === $userid) {
            $studentposts[] = $post;
        }
    }

    $initialpost = null;
    foreach ($studentposts as $post) {
        if (empty($post->parentid)) {
            $initialpost = $post;
            break;
        }
    }

    $peerreplies = [];
    $aireplies = [];
    $nonsubstantivecount = 0;
    $hashcounts = [];

    foreach ($studentposts as $post) {
        if (empty($post->issubstantive)) {
            $nonsubstantivecount++;
        }

        if (!empty($post->contenthash)) {
            $hashcounts[$post->contenthash] = ($hashcounts[$post->contenthash] ?? 0) + 1;
        }

        if (empty($post->parentid) || empty($posts[$post->parentid])) {
            continue;
        }

        $parent = $posts[$post->parentid];
        if ($parent->posttype === 'ai') {
            $aireplies[] = $post;
            continue;
        }

        if (!empty($parent->userid) && (int)$parent->userid !== $userid) {
            $peerreplies[] = $post;
        }
    }

    $substantivepeercount = count(array_filter($peerreplies, static function ($post) {
        return !empty($post->issubstantive);
    }));
    $substantiveaicount = count(array_filter($aireplies, static function ($post) {
        return !empty($post->issubstantive);
    }));

    $targetwords = max(1, (int)$aidiscussion->minsubstantivewords);
    $initialfraction = 0.0;
    if ($initialpost) {
        $initialfraction = min(1.0, $initialpost->wordcount / $targetwords);
        if (empty($initialpost->issubstantive)) {
            $initialfraction = min($initialfraction, 0.5);
        }
    }

    $aifraction = !empty($aidiscussion->aienabled) ? min(1.0, $substantiveaicount / 1) : 1.0;
    $peergoal = !empty($aidiscussion->allowpeerreplies) ? max(0, (int)$aidiscussion->requiredpeerreplies) : 0;
    $peerfraction = $peergoal > 0 ? min(1.0, $substantivepeercount / $peergoal) : 1.0;

    $effectiveinitialweight = max(0.0, (float)$aidiscussion->initialweight);
    $effectiveaiweight = !empty($aidiscussion->aienabled) ? max(0.0, (float)$aidiscussion->aiweight) : 0.0;
    $effectivepeerweight = !empty($aidiscussion->allowpeerreplies) ? max(0.0, (float)$aidiscussion->peerweight) : 0.0;
    $weighttotal = $effectiveinitialweight + $effectiveaiweight + $effectivepeerweight;
    if ($weighttotal <= 0.0) {
        $weighttotal = 100.0;
        $effectiveinitialweight = 100.0;
        $effectiveaiweight = 0.0;
        $effectivepeerweight = 0.0;
    }

    $grademax = max(0.0, (float)$aidiscussion->grade);
    $initialmaxpoints = $grademax * ($effectiveinitialweight / $weighttotal);
    $aimaxpoints = $grademax * ($effectiveaiweight / $weighttotal);
    $peermaxpoints = $grademax * ($effectivepeerweight / $weighttotal);
    $initialpoints = $initialmaxpoints * $initialfraction;
    $aipoints = $aimaxpoints * $aifraction;
    $peerpoints = $peermaxpoints * $peerfraction;
    $finalscore = min($grademax, $initialpoints + $aipoints + $peerpoints);

    $flags = [];
    foreach ($hashcounts as $hashcount) {
        if ($hashcount > 1) {
            $flags[] = [
                'code' => 'duplicate_reused',
                'message' => get_string('integrityduplicatecontent', 'mod_aidiscussion'),
                'severity' => 'medium',
            ];
            break;
        }
    }

    if ($nonsubstantivecount >= 2) {
        $flags[] = [
            'code' => 'repeated_low_substance',
            'message' => get_string('integrityrepeatedlowsubstance', 'mod_aidiscussion'),
            'severity' => 'low',
        ];
    }

    return (object) [
        'hasinitialpost' => !empty($initialpost),
        'initialpostid' => $initialpost->id ?? 0,
        'initialwordcount' => $initialpost->wordcount ?? 0,
        'initialfraction' => $initialfraction,
        'initialmaxpoints' => round($initialmaxpoints, 5),
        'initialpoints' => round($initialpoints, 5),
        'aireplycount' => count($aireplies),
        'substantiveaireplycount' => $substantiveaicount,
        'aifraction' => $aifraction,
        'aimaxpoints' => round($aimaxpoints, 5),
        'aipoints' => round($aipoints, 5),
        'peerreplycount' => count($peerreplies),
        'substantivepeerreplycount' => $substantivepeercount,
        'requiredpeerreplies' => $peergoal,
        'peerfraction' => $peerfraction,
        'peermaxpoints' => round($peermaxpoints, 5),
        'peerpoints' => round($peerpoints, 5),
        'finalscore' => round($finalscore, 5),
        'grademax' => $grademax,
        'areas' => [
            'initial' => [
                'fraction' => $initialfraction,
                'points' => round($initialpoints, 5),
                'maxpoints' => round($initialmaxpoints, 5),
                'evidence' => 'Initial response word count: ' . (int)($initialpost->wordcount ?? 0) . '.',
            ],
            'ai' => [
                'fraction' => $aifraction,
                'points' => round($aipoints, 5),
                'maxpoints' => round($aimaxpoints, 5),
                'evidence' => 'Substantive replies to ' . aidiscussion_get_ai_display_name($aidiscussion) . ': ' .
                    (int)$substantiveaicount . '.',
            ],
            'peer' => [
                'fraction' => $peerfraction,
                'points' => round($peerpoints, 5),
                'maxpoints' => round($peermaxpoints, 5),
                'evidence' => 'Substantive peer replies: ' . (int)$substantivepeercount . ' of ' . (int)$peergoal . '.',
            ],
        ],
        'flags' => $flags,
    ];
}

/**
 * Build heuristic narrative feedback for a rubric area.
 *
 * @param stdClass $aidiscussion
 * @param string $area
 * @param array $areadata
 * @return string
 */
function aidiscussion_get_area_feedback_text(stdClass $aidiscussion, string $area, array $areadata): string {
    $fraction = (float)($areadata['fraction'] ?? 0.0);
    $evidence = trim((string)($areadata['evidence'] ?? ''));

    if ($fraction >= 0.95) {
        $lead = get_string('rubricfeedbackstrong', 'mod_aidiscussion');
    } else if ($fraction >= 0.70) {
        $lead = get_string('rubricfeedbackdeveloping', 'mod_aidiscussion');
    } else if ($fraction >= 0.35) {
        $lead = get_string('rubricfeedbackpartial', 'mod_aidiscussion');
    } else {
        $lead = get_string('rubricfeedbacklimited', 'mod_aidiscussion');
    }

    return trim($lead . ' ' . $evidence);
}

/**
 * Build rubric-based criterion progress from heuristic area evidence.
 *
 * @param stdClass $aidiscussion
 * @param stdClass $metrics
 * @return array
 */
function aidiscussion_build_rubric_progress(stdClass $aidiscussion, stdClass $metrics): array {
    $rubrics = aidiscussion_get_effective_rubrics($aidiscussion);
    $areas = [];

    foreach (aidiscussion_get_rubric_area_definitions() as $area => $definition) {
        if (!aidiscussion_is_rubric_area_enabled($aidiscussion, $area)) {
            continue;
        }

        $rubric = $rubrics[$area];
        $areadata = $metrics->areas[$area] ?? [
            'fraction' => 0.0,
            'points' => 0.0,
            'maxpoints' => 0.0,
            'evidence' => '',
        ];
        $criteria = [];

        foreach ($rubric->criteria as $criterion) {
            $criterionscore = round((float)$criterion->maxscore * (float)$areadata['fraction'], 5);
            $criteria[] = [
                'shortname' => (string)$criterion->shortname,
                'description' => (string)$criterion->description,
                'score' => $criterionscore,
                'maxscore' => round((float)$criterion->maxscore, 5),
                'feedback' => aidiscussion_get_area_feedback_text($aidiscussion, $area, $areadata),
            ];
        }

        $areas[$area] = [
            'label' => $definition['label'],
            'weight' => aidiscussion_get_rubric_area_weight($aidiscussion, $area),
            'rubricmaxscore' => round((float)$rubric->maxscore, 5),
            'weightedpoints' => round((float)$areadata['points'], 5),
            'weightedmaxpoints' => round((float)$areadata['maxpoints'], 5),
            'instructions' => (string)$rubric->instructions,
            'feedback' => aidiscussion_get_area_feedback_text($aidiscussion, $area, $areadata),
            'criteria' => $criteria,
        ];
    }

    return [
        'source' => 'heuristic-rubric-v2',
        'areas' => $areas,
    ];
}

/**
 * Split response tester text into individual sample entries.
 *
 * @param string $text
 * @return array
 */
function aidiscussion_parse_response_tester_entries(string $text): array {
    $text = str_replace(["\r\n", "\r"], "\n", trim($text));
    if ($text === '') {
        return [];
    }

    $entries = preg_split("/\n\s*\n+/u", $text);
    $entries = array_map(static function (string $entry): string {
        return trim($entry);
    }, $entries);

    return array_values(array_filter($entries, static function (string $entry): bool {
        return $entry !== '';
    }));
}

/**
 * Format expected benchmark criterion rows for the benchmark editor textarea.
 *
 * @param array $criteria
 * @return string
 */
function aidiscussion_format_benchmark_expected_text(array $criteria): string {
    $lines = [];
    foreach ($criteria as $criterion) {
        if (empty($criterion['shortname']) && empty($criterion->shortname)) {
            continue;
        }

        $shortname = trim((string)($criterion['shortname'] ?? $criterion->shortname ?? ''));
        $score = (float)($criterion['score'] ?? $criterion->score ?? 0.0);
        $feedback = trim((string)($criterion['feedback'] ?? $criterion->feedback ?? ''));
        $line = $shortname . ' | ' . aidiscussion_format_score_value($score);
        if ($feedback !== '') {
            $line .= ' | ' . $feedback;
        }
        $lines[] = $line;
    }

    return implode("\n", $lines);
}

/**
 * Decode stored benchmark expectation JSON.
 *
 * @param string|null $json
 * @return array
 */
function aidiscussion_decode_benchmark_expected_json(?string $json): array {
    if (empty($json)) {
        return [];
    }

    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Parse expected benchmark criteria text for one rubric area.
 *
 * @param stdClass $aidiscussion
 * @param string $area
 * @param string $text
 * @return array
 */
function aidiscussion_parse_benchmark_expected_text(stdClass $aidiscussion, string $area, string $text): array {
    $rubrics = aidiscussion_get_effective_rubrics($aidiscussion);
    $rubric = $rubrics[$area] ?? null;
    if (!$rubric) {
        return [
            'feedback' => '',
            'criteria' => [],
        ];
    }

    $lines = preg_split('/\r\n|\r|\n/', trim($text));
    $lines = array_values(array_filter(array_map(static function (?string $line): string {
        return trim((string)$line);
    }, $lines), static function (string $line): bool {
        return $line !== '';
    }));

    if (!$lines) {
        throw new invalid_parameter_exception(get_string('errbenchmarkcriteriarequired', 'mod_aidiscussion'));
    }

    $criterionindex = [];
    foreach ($rubric->criteria as $criterion) {
        $criterionindex[aidiscussion_normalise_grading_key((string)$criterion->shortname)] = $criterion;
    }

    $parsed = [];
    foreach ($lines as $index => $line) {
        $parts = array_map('trim', explode('|', $line, 3));
        if (count($parts) < 2) {
            throw new invalid_parameter_exception(get_string('errbenchmarkcriterionformat', 'mod_aidiscussion', $index + 1));
        }

        $shortname = trim((string)$parts[0]);
        $scorevalue = trim((string)$parts[1]);
        $feedback = trim((string)($parts[2] ?? ''));
        $criterionkey = aidiscussion_normalise_grading_key($shortname);

        if ($shortname === '' || !isset($criterionindex[$criterionkey])) {
            throw new invalid_parameter_exception(get_string('errbenchmarkcriterionunknown', 'mod_aidiscussion', $index + 1));
        }
        if (isset($parsed[$criterionkey])) {
            throw new invalid_parameter_exception(get_string('errbenchmarkcriterionduplicate', 'mod_aidiscussion', $shortname));
        }
        if (!is_numeric($scorevalue)) {
            throw new invalid_parameter_exception(get_string('errbenchmarkcriterionscore', 'mod_aidiscussion', $index + 1));
        }

        $criterion = $criterionindex[$criterionkey];
        $score = (float)$scorevalue;
        if ($score < 0.0 || $score > (float)$criterion->maxscore) {
            throw new invalid_parameter_exception(get_string('errbenchmarkcriterionscore', 'mod_aidiscussion', $index + 1));
        }

        $normalisedscore = aidiscussion_normalise_provider_score($aidiscussion, $score, (float)$criterion->maxscore);
        if (abs($normalisedscore - $score) > 0.00001 && aidiscussion_get_grading_score_step($aidiscussion) > 0.0) {
            throw new invalid_parameter_exception(get_string('errbenchmarkcriteriongranularity', 'mod_aidiscussion', $index + 1));
        }

        $parsed[$criterionkey] = [
            'shortname' => (string)$criterion->shortname,
            'score' => $normalisedscore,
            'feedback' => $feedback,
        ];
    }

    $criteria = [];
    $missing = [];
    foreach ($rubric->criteria as $criterion) {
        $criterionkey = aidiscussion_normalise_grading_key((string)$criterion->shortname);
        if (!isset($parsed[$criterionkey])) {
            $missing[] = (string)$criterion->shortname;
            continue;
        }
        $criteria[] = $parsed[$criterionkey];
    }

    if ($missing) {
        throw new invalid_parameter_exception(
            get_string('errbenchmarkcriterionmissing', 'mod_aidiscussion', implode(', ', $missing))
        );
    }

    return [
        'feedback' => get_string('benchmarkareafeedbackdefault', 'mod_aidiscussion'),
        'criteria' => $criteria,
    ];
}

/**
 * Validate stored benchmark expectation JSON against the current rubric.
 *
 * @param stdClass $aidiscussion
 * @param array $expectedjson
 * @return void
 */
function aidiscussion_validate_benchmark_expected_json(stdClass $aidiscussion, array $expectedjson): void {
    $rubrics = aidiscussion_get_effective_rubrics($aidiscussion);
    foreach (aidiscussion_get_rubric_area_definitions() as $area => $definition) {
        if (!aidiscussion_is_rubric_area_enabled($aidiscussion, $area)) {
            continue;
        }

        if (empty($expectedjson['areas'][$area]['criteria']) || !is_array($expectedjson['areas'][$area]['criteria'])) {
            throw new invalid_parameter_exception(get_string('errbenchmarkstalearea', 'mod_aidiscussion', $definition['label']));
        }

        $expectedcriteria = [];
        foreach ($expectedjson['areas'][$area]['criteria'] as $criterion) {
            if (!is_array($criterion)) {
                continue;
            }
            $criterionkey = aidiscussion_normalise_grading_key((string)($criterion['shortname'] ?? ''));
            if ($criterionkey !== '') {
                $expectedcriteria[$criterionkey] = true;
            }
        }

        $missing = [];
        foreach ($rubrics[$area]->criteria as $criterion) {
            $criterionkey = aidiscussion_normalise_grading_key((string)$criterion->shortname);
            if (!isset($expectedcriteria[$criterionkey])) {
                $missing[] = (string)$criterion->shortname;
            }
        }

        if ($missing) {
            throw new invalid_parameter_exception(
                get_string('errbenchmarkcriterionmissing', 'mod_aidiscussion', implode(', ', $missing))
            );
        }
    }
}

/**
 * Return benchmark case defaults for the benchmark editor form.
 *
 * @param stdClass $aidiscussion
 * @param stdClass|null $benchmark
 * @return array
 */
function aidiscussion_get_benchmark_case_form_defaults(stdClass $aidiscussion, ?stdClass $benchmark = null): array {
    $defaults = [
        'benchmarkcaseid' => 0,
        'benchmarkname' => '',
        'benchmarkdescription' => '',
        'benchmarkinitialresponse' => '',
        'benchmarkairesponses' => '',
        'benchmarkpeerresponses' => '',
    ];

    foreach (aidiscussion_get_rubric_area_definitions() as $area => $definition) {
        $defaults['benchmarkexpected_' . $area] = '';
    }

    if (!$benchmark) {
        return $defaults;
    }

    $expectedjson = aidiscussion_decode_benchmark_expected_json((string)$benchmark->expectedjson);
    $defaults['benchmarkcaseid'] = (int)$benchmark->id;
    $defaults['benchmarkname'] = (string)$benchmark->name;
    $defaults['benchmarkdescription'] = (string)($benchmark->description ?? '');
    $defaults['benchmarkinitialresponse'] = (string)($benchmark->sampleinitialresponse ?? '');
    $defaults['benchmarkairesponses'] = (string)($benchmark->sampleairesponses ?? '');
    $defaults['benchmarkpeerresponses'] = (string)($benchmark->samplepeerresponses ?? '');

    foreach (aidiscussion_get_rubric_area_definitions() as $area => $definition) {
        $defaults['benchmarkexpected_' . $area] = aidiscussion_format_benchmark_expected_text(
            $expectedjson['areas'][$area]['criteria'] ?? []
        );
    }

    return $defaults;
}

/**
 * Return preview defaults based on a saved benchmark case.
 *
 * @param stdClass $benchmark
 * @return array
 */
function aidiscussion_get_benchmark_preview_defaults(stdClass $benchmark): array {
    return [
        'sampleinitialresponse' => (string)($benchmark->sampleinitialresponse ?? ''),
        'sampleairesponses' => (string)($benchmark->sampleairesponses ?? ''),
        'samplepeerresponses' => (string)($benchmark->samplepeerresponses ?? ''),
    ];
}

/**
 * Validate benchmark case form data against the current rubric.
 *
 * @param stdClass $aidiscussion
 * @param array $data
 * @return array
 */
function aidiscussion_validate_benchmark_case_form_data(stdClass $aidiscussion, array $data): array {
    $errors = [];

    if (trim((string)($data['benchmarkname'] ?? '')) === '') {
        $errors['benchmarkname'] = get_string('errbenchmarknamerequired', 'mod_aidiscussion');
    }

    foreach (aidiscussion_get_rubric_area_definitions() as $area => $definition) {
        if (!aidiscussion_is_rubric_area_enabled($aidiscussion, $area)) {
            continue;
        }

        $field = 'benchmarkexpected_' . $area;
        try {
            aidiscussion_parse_benchmark_expected_text($aidiscussion, $area, (string)($data[$field] ?? ''));
        } catch (invalid_parameter_exception $e) {
            $errors[$field] = $e->getMessage();
        }
    }

    return $errors;
}

/**
 * Load saved benchmark cases for an activity.
 *
 * @param int $aidiscussionid
 * @return array
 */
function aidiscussion_get_benchmark_cases(int $aidiscussionid): array {
    global $DB;

    return $DB->get_records('aidiscussion_benchmarks', ['aidiscussionid' => $aidiscussionid], 'name ASC, id ASC');
}

/**
 * Load one saved benchmark case for an activity.
 *
 * @param int $benchmarkid
 * @param int $aidiscussionid
 * @return stdClass|null
 */
function aidiscussion_get_benchmark_case(int $benchmarkid, int $aidiscussionid): ?stdClass {
    global $DB;

    $record = $DB->get_record('aidiscussion_benchmarks', [
        'id' => $benchmarkid,
        'aidiscussionid' => $aidiscussionid,
    ]);

    return $record ?: null;
}

/**
 * Save or update a benchmark case from the tester page.
 *
 * @param stdClass $aidiscussion
 * @param stdClass $data
 * @param int $userid
 * @return int
 */
function aidiscussion_save_benchmark_case(stdClass $aidiscussion, stdClass $data, int $userid): int {
    global $DB;

    $expectedjson = [
        'summary' => trim((string)($data->benchmarkdescription ?? '')) !== '' ?
            trim((string)$data->benchmarkdescription) :
            get_string('benchmarksummarydefault', 'mod_aidiscussion'),
        'overall' => trim((string)($data->benchmarkdescription ?? '')),
        'areas' => [],
    ];

    foreach (aidiscussion_get_rubric_area_definitions() as $area => $definition) {
        if (!aidiscussion_is_rubric_area_enabled($aidiscussion, $area)) {
            continue;
        }

        $expectedjson['areas'][$area] = aidiscussion_parse_benchmark_expected_text(
            $aidiscussion,
            $area,
            (string)($data->{'benchmarkexpected_' . $area} ?? '')
        );
    }

    $record = (object) [
        'aidiscussionid' => (int)$aidiscussion->id,
        'name' => trim((string)$data->benchmarkname),
        'description' => trim((string)($data->benchmarkdescription ?? '')),
        'sampleinitialresponse' => trim((string)($data->benchmarkinitialresponse ?? '')),
        'sampleairesponses' => trim((string)($data->benchmarkairesponses ?? '')),
        'samplepeerresponses' => trim((string)($data->benchmarkpeerresponses ?? '')),
        'expectedjson' => json_encode($expectedjson),
        'userid' => $userid,
        'timemodified' => time(),
    ];

    $benchmarkid = (int)($data->benchmarkcaseid ?? 0);
    if ($benchmarkid > 0) {
        if (!aidiscussion_get_benchmark_case($benchmarkid, (int)$aidiscussion->id)) {
            throw new invalid_parameter_exception(get_string('benchmarknotfound', 'mod_aidiscussion'));
        }
        $record->id = $benchmarkid;
        $DB->update_record('aidiscussion_benchmarks', $record);
        return $benchmarkid;
    }

    $record->timecreated = time();
    return (int)$DB->insert_record('aidiscussion_benchmarks', $record);
}

/**
 * Delete one saved benchmark case.
 *
 * @param int $benchmarkid
 * @param int $aidiscussionid
 * @return void
 */
function aidiscussion_delete_benchmark_case(int $benchmarkid, int $aidiscussionid): void {
    global $DB;

    $DB->delete_records('aidiscussion_benchmarks', [
        'id' => $benchmarkid,
        'aidiscussionid' => $aidiscussionid,
    ]);
}

/**
 * Build a lightweight synthetic post for response tester previews.
 *
 * @param stdClass $aidiscussion
 * @param int $id
 * @param string $authorrole
 * @param string $posttype
 * @param int $userid
 * @param int $parentid
 * @param string $content
 * @param int $threadownerid
 * @return stdClass
 */
function aidiscussion_build_response_tester_post(
    stdClass $aidiscussion,
    int $id,
    string $authorrole,
    string $posttype,
    int $userid,
    int $parentid,
    string $content,
    int $threadownerid = 0,
): stdClass {
    $content = trim($content);
    $wordcount = aidiscussion_count_words($content);

    return (object) [
        'id' => $id,
        'aidiscussionid' => (int)($aidiscussion->id ?? 0),
        'userid' => $userid,
        'authorrole' => $authorrole,
        'parentid' => $parentid,
        'visibility' => 'public',
        'posttype' => $posttype,
        'content' => $content,
        'contentformat' => FORMAT_PLAIN,
        'wordcount' => $wordcount,
        'issubstantive' => $authorrole === 'student'
            ? (int)aidiscussion_is_substantive_post($aidiscussion, $content, $wordcount)
            : 1,
        'providercomponent' => '',
        'modelname' => '',
        'pseudonym' => null,
        'contenthash' => aidiscussion_hash_content($content),
        'timecreated' => 0,
        'timemodified' => 0,
        'threadownerid' => $threadownerid ?: $userid,
    ];
}

/**
 * Build a synthetic discussion thread set for the response tester.
 *
 * @param stdClass $aidiscussion
 * @param array $samples
 * @param int $testuserid
 * @return array
 */
function aidiscussion_build_response_tester_posts(
    stdClass $aidiscussion,
    array $samples,
    int $testuserid = 999999999,
): array {
    $posts = [];
    $nextid = 1;
    $peeruserid = $testuserid - 1;

    $initialresponse = trim((string)($samples['initialresponse'] ?? ''));
    $airesponses = $samples['airesponses'] ?? [];
    $peerresponses = $samples['peerresponses'] ?? [];
    $rootpostid = 0;

    if ($initialresponse !== '') {
        $posts[$nextid] = aidiscussion_build_response_tester_post(
            $aidiscussion,
            $nextid,
            'student',
            'student',
            $testuserid,
            0,
            $initialresponse,
            $testuserid,
        );
        $rootpostid = $nextid;
        $nextid++;
    }

    foreach ($airesponses as $index => $response) {
        $aipostid = $nextid++;
        $posts[$aipostid] = aidiscussion_build_response_tester_post(
            $aidiscussion,
            $aipostid,
            'ai',
            'ai',
            0,
            $rootpostid,
            'Sample AI follow-up ' . ($index + 1),
            $testuserid,
        );

        $posts[$nextid] = aidiscussion_build_response_tester_post(
            $aidiscussion,
            $nextid,
            'student',
            'student',
            $testuserid,
            $aipostid,
            (string)$response,
            $testuserid,
        );
        $nextid++;
    }

    foreach ($peerresponses as $index => $response) {
        $peerpostid = $nextid++;
        $posts[$peerpostid] = aidiscussion_build_response_tester_post(
            $aidiscussion,
            $peerpostid,
            'student',
            'student',
            $peeruserid,
            0,
            'Sample peer post ' . ($index + 1),
            $peeruserid,
        );

        $posts[$nextid] = aidiscussion_build_response_tester_post(
            $aidiscussion,
            $nextid,
            'student',
            'student',
            $testuserid,
            $peerpostid,
            (string)$response,
            $testuserid,
        );
        $nextid++;
    }

    return $posts;
}

/**
 * Resolve the module context id for an activity.
 *
 * @param stdClass $aidiscussion
 * @return int
 */
function aidiscussion_resolve_activity_context_id(stdClass $aidiscussion): int {
    $cm = get_coursemodule_from_instance(
        'aidiscussion',
        $aidiscussion->id,
        $aidiscussion->course,
        false,
        MUST_EXIST
    );

    return (int)\context_module::instance($cm->id)->id;
}

/**
 * Collect the learner-authored posts that should be graded.
 *
 * @param stdClass $aidiscussion
 * @param array $posts
 * @param int $userid
 * @return array
 */
function aidiscussion_get_user_grading_posts(stdClass $aidiscussion, array $posts, int $userid): array {
    $initialpost = null;
    $airesponses = [];
    $peerresponses = [];

    foreach ($posts as $post) {
        if ($post->authorrole !== 'student' || (int)$post->userid !== $userid) {
            continue;
        }

        if (empty($post->parentid)) {
            if (!$initialpost) {
                $initialpost = $post;
            }
            continue;
        }

        $parent = $posts[$post->parentid] ?? null;
        if (!$parent) {
            continue;
        }

        if ($parent->posttype === 'ai') {
            $airesponses[] = [
                'prompt' => $parent,
                'response' => $post,
            ];
            continue;
        }

        if (!empty($parent->userid) && (int)$parent->userid !== $userid) {
            $peerresponses[] = [
                'prompt' => $parent,
                'response' => $post,
            ];
        }
    }

    return [
        'initial' => $initialpost,
        'ai' => $airesponses,
        'peer' => $peerresponses,
    ];
}

/**
 * Build plain-text learner evidence for the grading prompt.
 *
 * @param stdClass $aidiscussion
 * @param array $posts
 * @param int $userid
 * @return string
 */
function aidiscussion_build_grading_evidence_text(stdClass $aidiscussion, array $posts, int $userid): string {
    $gradedposts = aidiscussion_get_user_grading_posts($aidiscussion, $posts, $userid);
    $lines = [];

    if (!empty($gradedposts['initial'])) {
        $lines[] = 'Initial response:';
        $lines[] = aidiscussion_limit_text(
            aidiscussion_to_plain_text(
                (string)$gradedposts['initial']->content,
                (int)$gradedposts['initial']->contentformat
            ),
            2200
        );
    } else {
        $lines[] = 'Initial response:';
        $lines[] = 'None submitted.';
    }

    $lines[] = 'Replies to ' . aidiscussion_get_ai_display_name($aidiscussion) . ':';
    if (empty($gradedposts['ai'])) {
        $lines[] = 'None submitted.';
    } else {
        foreach ($gradedposts['ai'] as $index => $pair) {
            $lines[] = 'AI prompt ' . ($index + 1) . ':';
            $lines[] = aidiscussion_limit_text(
                aidiscussion_to_plain_text(
                    (string)$pair['prompt']->content,
                    (int)$pair['prompt']->contentformat
                ),
                800
            );
            $lines[] = 'Student reply ' . ($index + 1) . ':';
            $lines[] = aidiscussion_limit_text(
                aidiscussion_to_plain_text(
                    (string)$pair['response']->content,
                    (int)$pair['response']->contentformat
                ),
                1600
            );
        }
    }

    $lines[] = 'Peer replies:';
    if (empty($gradedposts['peer'])) {
        $lines[] = 'None submitted.';
    } else {
        foreach ($gradedposts['peer'] as $index => $pair) {
            $lines[] = 'Peer post ' . ($index + 1) . ':';
            $lines[] = aidiscussion_limit_text(
                aidiscussion_to_plain_text(
                    (string)$pair['prompt']->content,
                    (int)$pair['prompt']->contentformat
                ),
                800
            );
            $lines[] = 'Student reply ' . ($index + 1) . ':';
            $lines[] = aidiscussion_limit_text(
                aidiscussion_to_plain_text(
                    (string)$pair['response']->content,
                    (int)$pair['response']->contentformat
                ),
                1600
            );
        }
    }

    return aidiscussion_limit_text(implode("\n", $lines), 9000);
}

/**
 * Format a criterion score for prompts and calibration displays.
 *
 * @param float $value
 * @return string
 */
function aidiscussion_format_score_value(float $value): string {
    $formatted = number_format($value, 2, '.', '');
    $formatted = rtrim(rtrim($formatted, '0'), '.');
    return $formatted === '-0' ? '0' : $formatted;
}

/**
 * Return the allowed criterion scores for the configured granularity.
 *
 * @param stdClass $aidiscussion
 * @param float $maxscore
 * @return array
 */
function aidiscussion_get_allowed_grading_scores(stdClass $aidiscussion, float $maxscore): array {
    $step = aidiscussion_get_grading_score_step($aidiscussion);
    if ($step <= 0.0) {
        return [];
    }

    $scores = [];
    for ($score = 0.0; $score <= $maxscore + 0.00001; $score += $step) {
        $scores[] = round(min($score, $maxscore), 5);
    }

    if (empty($scores) || abs((float)end($scores) - $maxscore) > 0.00001) {
        $scores[] = round($maxscore, 5);
    }

    return array_values(array_unique($scores));
}

/**
 * Format a score band for display in prompts.
 *
 * @param float $min
 * @param float $max
 * @return string
 */
function aidiscussion_format_score_band(float $min, float $max): string {
    if (abs($min - $max) <= 0.00001) {
        return aidiscussion_format_score_value($max);
    }

    return aidiscussion_format_score_value($min) . '-' . aidiscussion_format_score_value($max);
}

/**
 * Build generic criterion score anchors for provider grading prompts.
 *
 * @param stdClass $aidiscussion
 * @param float $maxscore
 * @return string
 */
function aidiscussion_build_grading_anchor_text(stdClass $aidiscussion, float $maxscore): string {
    $labels = [
        'missing, off-topic, or unsupported',
        'limited, partial, or uneven evidence',
        'clear evidence with some development',
        'strong, specific, and complete evidence',
    ];
    $scores = aidiscussion_get_allowed_grading_scores($aidiscussion, $maxscore);
    $bands = [];

    if ($scores) {
        $chunksize = max(1, (int)ceil(count($scores) / 4));
        $chunks = array_chunk($scores, $chunksize);
        foreach ($chunks as $index => $chunk) {
            $bands[] = aidiscussion_format_score_band((float)reset($chunk), (float)end($chunk)) .
                ' = ' . $labels[min($index, count($labels) - 1)];
        }
        $allowedtext = 'Allowed scores: ' . implode(', ', array_map('aidiscussion_format_score_value', $scores)) . '.';
    } else {
        $bounds = [
            0.0,
            round($maxscore * 0.25, 2),
            round($maxscore * 0.5, 2),
            round($maxscore * 0.75, 2),
            round($maxscore, 2),
        ];
        foreach ($labels as $index => $label) {
            $bands[] = aidiscussion_format_score_band($bounds[$index], $bounds[$index + 1]) . ' = ' . $label;
        }
        $allowedtext = 'Allowed scores: any value from 0 to ' . aidiscussion_format_score_value($maxscore) .
            ' with up to 2 decimals.';
    }

    return 'Score bands: ' . implode('; ', $bands) . '. ' . $allowedtext;
}

/**
 * Quantise a criterion score to the configured grading granularity.
 *
 * @param stdClass $aidiscussion
 * @param float $score
 * @param float $maxscore
 * @return float
 */
function aidiscussion_normalise_provider_score(stdClass $aidiscussion, float $score, float $maxscore): float {
    $score = max(0.0, min($maxscore, $score));
    $step = aidiscussion_get_grading_score_step($aidiscussion);
    if ($step > 0.0) {
        $score = round(round($score / $step) * $step, 5);
    } else {
        $score = round($score, 2);
    }

    return max(0.0, min($maxscore, $score));
}

/**
 * Build the provider grading prompt.
 *
 * @param stdClass $aidiscussion
 * @param array $posts
 * @param int $userid
 * @param stdClass $metrics
 * @return string
 */
function aidiscussion_build_grading_prompt(
    stdClass $aidiscussion,
    array $posts,
    int $userid,
    stdClass $metrics,
): string {
    $teacherprompt = aidiscussion_to_plain_text((string)$aidiscussion->prompt, (int)$aidiscussion->promptformat);
    $teacherexample = aidiscussion_to_plain_text(
        (string)($aidiscussion->teacherexample ?? ''),
        (int)($aidiscussion->teacherexampleformat ?? FORMAT_HTML)
    );
    $gradinginstructions = trim((string)($aidiscussion->gradinginstructions ?? ''));
    $rubrics = aidiscussion_get_effective_rubrics($aidiscussion);
    $evidence = aidiscussion_build_grading_evidence_text($aidiscussion, $posts, $userid);
    $granularity = aidiscussion_get_grading_granularity($aidiscussion);
    $lines = [
        'You are grading one learner in a Moodle AI discussion activity.',
        'Return one valid JSON object only.',
        'Do not use markdown fences or any text outside the JSON object.',
        'Score only the learner\'s own writing.',
        'Use AI replies and peer posts only as context for the learner\'s responses.',
        'Do not change area keys or criterion shortnames.',
        'Keep the JSON concise so it fits in a short response budget.',
        'Limit summary to 20 words.',
        'Limit overall to 60 words.',
        'Limit each area feedback to 25 words.',
        'Limit each criterion feedback to 15 words.',
        'Return at most 2 integrity flags.',
        'Use an empty integrityflags array unless there is a clear, specific concern.',
        'Criterion score granularity: ' . ($granularity === 'whole' ? 'whole numbers only' :
            ($granularity === 'half' ? '0.5 increments only' : 'up to 2 decimals')),
        'Teacher prompt: ' . aidiscussion_limit_text($teacherprompt, 2200),
    ];

    if ($gradinginstructions !== '') {
        $lines[] = 'Teacher grading instructions: ' .
            aidiscussion_limit_text(aidiscussion_to_plain_text($gradinginstructions), 2200);
    }

    if ($teacherexample !== '' && aidiscussion_use_teacher_exemplar_for_grading($aidiscussion)) {
        $lines[] = 'Teacher exemplar response: ' . aidiscussion_limit_text($teacherexample, 2200);
        $lines[] = 'Use the exemplar for expected depth and framing, but do not require the learner to mirror it.';
    }

    $lines[] = 'Activity settings:';
    $lines[] = '- Required peer replies: ' . (int)$metrics->requiredpeerreplies;
    $lines[] = '- Minimum substantive words: ' . (int)$aidiscussion->minsubstantivewords;
    $lines[] = '- AI display name: ' . aidiscussion_get_ai_display_name($aidiscussion);
    $lines[] = 'Learner evidence:';
    $lines[] = $evidence;
    $lines[] = 'Rubric schema:';

    foreach (aidiscussion_get_rubric_area_definitions() as $area => $definition) {
        if (!aidiscussion_is_rubric_area_enabled($aidiscussion, $area)) {
            continue;
        }

        $rubric = $rubrics[$area];
        $weightedmax = format_float((float)($metrics->areas[$area]['maxpoints'] ?? 0), 2);
        $lines[] = 'Area key: ' . $area;
        $lines[] = 'Area label: ' . $definition['label'];
        $lines[] = 'Area weight (%): ' . format_float(aidiscussion_get_rubric_area_weight($aidiscussion, $area), 0);
        $lines[] = 'Weighted max points: ' . $weightedmax;
        if (trim((string)$rubric->instructions) !== '') {
            $lines[] = 'Area instructions: ' . aidiscussion_limit_text((string)$rubric->instructions, 1800);
        }
        foreach ($rubric->criteria as $criterion) {
            $lines[] = '- shortname: ' . $criterion->shortname .
                ' | maxscore: ' . format_float((float)$criterion->maxscore, -1) .
                ' | description: ' . aidiscussion_limit_text((string)$criterion->description, 1200) .
                ' | ' . aidiscussion_build_grading_anchor_text($aidiscussion, (float)$criterion->maxscore);
        }
    }

    $lines[] = 'Required JSON schema:';
    $lines[] = '{';
    $lines[] = '  "summary": "Short overall scoring summary.",';
    $lines[] = '  "overall": "Short overall feedback.",';
    $lines[] = '  "areas": {';
    $lines[] = '    "initial": {';
    $lines[] = '      "feedback": "Short area-level feedback.",';
    $lines[] = '      "criteria": [';
    $lines[] = '        {"shortname": "Criterion shortname", "score": 0, "feedback": "Short criterion feedback."}';
    $lines[] = '      ]';
    $lines[] = '    }';
    $lines[] = '  },';
    $lines[] = '  "integrityflags": [';
    $lines[] = '    {"code": "optional_code", "message": "Optional non-blocking concern.", "severity": "low"}';
    $lines[] = '  ]';
    $lines[] = '}';
    $lines[] = 'Use only these severities for integrity flags: low, medium, high.';
    $lines[] = 'If an area has no learner evidence yet, score its criteria as 0 and explain briefly why.';

    return aidiscussion_limit_text(implode("\n\n", array_filter($lines)), 18000);
}

/**
 * Extract the first valid JSON object from model output.
 *
 * @param string $text
 * @return array
 */
function aidiscussion_extract_json_payload(string $text): array {
    $candidate = trim($text);
    $fence = preg_quote(str_repeat(chr(96), 3), '/');

    if (preg_match('/' . $fence . '(?:json)?\s*(\{.*\})\s*' . $fence . '/is', $candidate, $matches)) {
        $candidate = trim((string)$matches[1]);
    }

    $decoded = json_decode($candidate, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    $start = strpos($candidate, '{');
    $end = strrpos($candidate, '}');
    if ($start === false || $end === false || $end <= $start) {
        throw new \UnexpectedValueException('The grading provider did not return a JSON object.');
    }

    $candidate = substr($candidate, $start, ($end - $start) + 1);
    $decoded = json_decode($candidate, true);
    if (!is_array($decoded)) {
        throw new \UnexpectedValueException('The grading provider returned invalid JSON.');
    }

    return $decoded;
}

/**
 * Normalise a rubric key for case-insensitive matching.
 *
 * @param string $value
 * @return string
 */
function aidiscussion_normalise_grading_key(string $value): string {
    return core_text::strtolower(trim($value));
}

/**
 * Parse and validate a provider grading response.
 *
 * @param string $text
 * @return array
 */
function aidiscussion_parse_provider_grading_response(string $text): array {
    $decoded = aidiscussion_extract_json_payload($text);

    if (isset($decoded['result']) && is_array($decoded['result'])) {
        $decoded = $decoded['result'];
    }

    if (empty($decoded['areas']) || !is_array($decoded['areas'])) {
        throw new \UnexpectedValueException('The grading provider response is missing the areas object.');
    }

    return $decoded;
}

/**
 * Merge heuristic and provider integrity flags.
 *
 * @param array $heuristicflags
 * @param mixed $providerflags
 * @return array
 */
function aidiscussion_normalise_provider_flags(array $heuristicflags, $providerflags): array {
    $providerflags = is_array($providerflags) ? $providerflags : [];
    $flags = [];
    $seen = [];

    foreach (array_merge($heuristicflags, $providerflags) as $flag) {
        if (!is_array($flag)) {
            continue;
        }

        $message = trim((string)($flag['message'] ?? ''));
        if ($message === '') {
            continue;
        }

        $code = trim((string)($flag['code'] ?? ''));
        if ($code === '') {
            $code = 'flag_' . (count($flags) + 1);
        }

        $severity = trim((string)($flag['severity'] ?? 'low'));
        if (!in_array($severity, ['low', 'medium', 'high'], true)) {
            $severity = 'low';
        }

        $key = aidiscussion_normalise_grading_key($code . '|' . $message);
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $flags[] = [
            'code' => $code,
            'message' => aidiscussion_limit_text(aidiscussion_to_plain_text($message), 400),
            'severity' => $severity,
        ];
    }

    return $flags;
}

/**
 * Build grading payloads from a provider scoring response.
 *
 * @param stdClass $aidiscussion
 * @param stdClass $metrics
 * @param array $gradingdata
 * @return array
 */
function aidiscussion_build_provider_grade_payload(
    stdClass $aidiscussion,
    stdClass $metrics,
    array $gradingdata,
): array {
    $rubrics = aidiscussion_get_effective_rubrics($aidiscussion);
    $areas = [];
    $areaweights = [
        'initial' => 0.0,
        'ai' => 0.0,
        'peer' => 0.0,
    ];
    $feedbackareas = [];

    foreach (aidiscussion_get_rubric_area_definitions() as $area => $definition) {
        if (!aidiscussion_is_rubric_area_enabled($aidiscussion, $area)) {
            continue;
        }

        $rubric = $rubrics[$area];
        $rawarea = $gradingdata['areas'][$area] ?? [];
        $rawarea = is_array($rawarea) ? $rawarea : [];
        $rawcriteria = $rawarea['criteria'] ?? [];
        $rawcriteria = is_array($rawcriteria) ? array_values($rawcriteria) : [];
        $criteriaindex = [];

        foreach ($rawcriteria as $rawcriterion) {
            if (!is_array($rawcriterion)) {
                continue;
            }

            $criterionkey = trim((string)($rawcriterion['shortname'] ?? $rawcriterion['name'] ?? ''));
            if ($criterionkey === '') {
                continue;
            }

            $criteriaindex[aidiscussion_normalise_grading_key($criterionkey)] = $rawcriterion;
        }

        $criteria = [];
        $rawscore = 0.0;
        foreach ($rubric->criteria as $index => $criterion) {
            $criterionkey = aidiscussion_normalise_grading_key((string)$criterion->shortname);
            $rawcriterion = $criteriaindex[$criterionkey] ?? ($rawcriteria[$index] ?? []);
            $rawcriterion = is_array($rawcriterion) ? $rawcriterion : [];

            $score = aidiscussion_normalise_provider_score(
                $aidiscussion,
                (float)($rawcriterion['score'] ?? 0),
                (float)$criterion->maxscore
            );
            $feedback = trim((string)($rawcriterion['feedback'] ?? $rawcriterion['notes'] ?? ''));
            if ($feedback === '') {
                $feedback = aidiscussion_get_area_feedback_text($aidiscussion, $area, $metrics->areas[$area] ?? []);
            } else {
                $feedback = aidiscussion_limit_text(aidiscussion_to_plain_text($feedback), 1000);
            }

            $criteria[] = [
                'shortname' => (string)$criterion->shortname,
                'description' => (string)$criterion->description,
                'score' => round($score, 5),
                'maxscore' => round((float)$criterion->maxscore, 5),
                'feedback' => $feedback,
            ];
            $rawscore += $score;
        }

        $rubricmaxscore = max(0.0, (float)$rubric->maxscore);
        $fraction = $rubricmaxscore > 0.0 ? min(1.0, $rawscore / $rubricmaxscore) : 0.0;
        $weightedmaxpoints = (float)($metrics->areas[$area]['maxpoints'] ?? 0.0);
        $weightedpoints = round($weightedmaxpoints * $fraction, 5);
        $areaweights[$area] = $weightedpoints;

        $areafeedback = trim((string)($rawarea['feedback'] ?? ''));
        if ($areafeedback === '') {
            $areafeedback = aidiscussion_get_area_feedback_text($aidiscussion, $area, $metrics->areas[$area] ?? []);
        } else {
            $areafeedback = aidiscussion_limit_text(aidiscussion_to_plain_text($areafeedback), 1200);
        }

        $areas[$area] = [
            'label' => $definition['label'],
            'weight' => aidiscussion_get_rubric_area_weight($aidiscussion, $area),
            'rubricmaxscore' => round($rubricmaxscore, 5),
            'weightedpoints' => $weightedpoints,
            'weightedmaxpoints' => round($weightedmaxpoints, 5),
            'instructions' => (string)$rubric->instructions,
            'feedback' => $areafeedback,
            'criteria' => $criteria,
        ];
        $feedbackareas[$area] = [
            'label' => $definition['label'],
            'feedback' => $areafeedback,
        ];
    }

    $finalscore = round(array_sum($areaweights), 5);
    $summary = trim((string)($gradingdata['summary'] ?? ''));
    if ($summary === '') {
        $summary = get_string('aigradingfeedbacksummary', 'mod_aidiscussion', [
            'score' => format_float($finalscore, 2),
            'grademax' => format_float((float)$metrics->grademax, 2),
        ]);
    } else {
        $summary = aidiscussion_limit_text(aidiscussion_to_plain_text($summary), 1200);
    }

    $overall = trim((string)($gradingdata['overall'] ?? ''));
    if ($overall === '') {
        $overall = $summary;
    } else {
        $overall = aidiscussion_limit_text(aidiscussion_to_plain_text($overall), 2000);
    }

    $flags = [];
    if (!empty($aidiscussion->integrityflagsenabled)) {
        $flags = aidiscussion_normalise_provider_flags(
            $metrics->flags,
            $gradingdata['integrityflags'] ?? ($gradingdata['flags'] ?? [])
        );
    }

    $rubricprogress = [
        'source' => 'ai-provider-rubric-v1',
        'areas' => $areas,
    ];
    $feedbackjson = json_encode([
        'source' => 'ai-provider-rubric-v1',
        'summary' => $summary,
        'overall' => $overall,
        'areas' => $feedbackareas,
    ]);

    return [
        'rubricprogress' => $rubricprogress,
        'feedbackjson' => $feedbackjson,
        'integrityjson' => json_encode($flags),
        'flags' => $flags,
        'areascores' => $areaweights,
        'finalscore' => $finalscore,
    ];
}

/**
 * Return text generation options for provider-backed grading.
 *
 * @param stdClass $aidiscussion
 * @return array
 */
function aidiscussion_get_grading_generation_options(stdClass $aidiscussion): array {
    $options = [
        'temperature' => aidiscussion_get_grading_temperature($aidiscussion),
    ];

    $providercomponent = aidiscussion_get_grade_provider_component($aidiscussion);
    if (!provider_registry::is_plugin_provider($providercomponent)) {
        return $options;
    }

    $config = provider_registry::get_plugin_provider_config($providercomponent);
    $options['maxtokens'] = max(1024, (int)($config['maxtokens'] ?? 0));

    return $options;
}

/**
 * Build a provider-backed grade record.
 *
 * @param stdClass $aidiscussion
 * @param stdClass $metrics
 * @param int $userid
 * @param int $contextid
 * @param int $actoruserid
 * @param array|null $posts
 * @return stdClass
 */
function aidiscussion_build_provider_grade_record(
    stdClass $aidiscussion,
    stdClass $metrics,
    int $userid,
    int $contextid,
    int $actoruserid,
    ?array $posts = null,
): stdClass {
    $posts = $posts ?? aidiscussion_load_posts($aidiscussion->id);
    $providercomponent = aidiscussion_get_grade_provider_component($aidiscussion);
    $prompt = aidiscussion_build_grading_prompt($aidiscussion, $posts, $userid, $metrics);
    $generationoptions = aidiscussion_get_grading_generation_options($aidiscussion);
    $result = provider_client::generate_text(
        $providercomponent,
        $contextid,
        $actoruserid,
        $prompt,
        $generationoptions
    );

    if (empty($result->success) || trim((string)$result->generatedcontent) === '') {
        throw new \UnexpectedValueException('The grading provider returned an empty grading response.');
    }

    try {
        $gradingdata = aidiscussion_parse_provider_grading_response((string)$result->generatedcontent);
    } catch (\Throwable $e) {
        $repairprompt = $prompt . "\n\n" .
            'IMPORTANT: Your previous reply was invalid or truncated. ' .
            'Return one complete JSON object only. ' .
            'Keep feedback extremely short and use an empty integrityflags array unless there is a clear concern.';
        $result = provider_client::generate_text(
            $providercomponent,
            $contextid,
            $actoruserid,
            $repairprompt,
            $generationoptions
        );

        if (empty($result->success) || trim((string)$result->generatedcontent) === '') {
            throw new \UnexpectedValueException('The grading provider returned an empty grading response.');
        }

        $gradingdata = aidiscussion_parse_provider_grading_response((string)$result->generatedcontent);
    }

    $payload = aidiscussion_build_provider_grade_payload($aidiscussion, $metrics, $gradingdata);

    return (object) [
        'aidiscussionid' => (int)$aidiscussion->id,
        'userid' => $userid,
        'initialscore' => round((float)($payload['areascores']['initial'] ?? 0.0), 5),
        'aiscore' => round((float)($payload['areascores']['ai'] ?? 0.0), 5),
        'peerscore' => round((float)($payload['areascores']['peer'] ?? 0.0), 5),
        'finalscore' => round((float)$payload['finalscore'], 5),
        'criterionjson' => json_encode($payload['rubricprogress']),
        'feedbackjson' => $payload['feedbackjson'],
        'integrityjson' => $payload['integrityjson'],
        'providercomponent' => (string)$result->providercomponent,
        'modelname' => trim((string)$result->modelname),
        'timegraded' => time(),
        'timemodified' => time(),
    ];
}

/**
 * Build the best available grade record for a learner.
 *
 * @param stdClass $aidiscussion
 * @param int $userid
 * @param array $options
 * @return stdClass
 */
function aidiscussion_generate_grade_record(stdClass $aidiscussion, int $userid, array $options = []): stdClass {
    $posts = $options['posts'] ?? null;
    $metrics = $options['metrics'] ?? aidiscussion_calculate_user_metrics($aidiscussion, $userid, $posts);
    $forceheuristic = !empty($options['forceheuristic']);
    $allowfallback = !array_key_exists('allowfallback', $options) || !empty($options['allowfallback']);

    if ($forceheuristic || !aidiscussion_uses_provider_grading($aidiscussion)) {
        return aidiscussion_build_grade_record($aidiscussion, $metrics, $userid);
    }

    $contextid = (int)($options['contextid'] ?? 0);
    if ($contextid <= 0) {
        $contextid = aidiscussion_resolve_activity_context_id($aidiscussion);
    }

    $actoruserid = (int)($options['actoruserid'] ?? $userid);
    if ($actoruserid <= 0) {
        $actoruserid = $userid;
    }

    try {
        return aidiscussion_build_provider_grade_record(
            $aidiscussion,
            $metrics,
            $userid,
            $contextid,
            $actoruserid,
            $posts
        );
    } catch (\Throwable $e) {
        if (!$allowfallback) {
            throw $e;
        }

        return aidiscussion_build_grade_record($aidiscussion, $metrics, $userid);
    }
}

/**
 * Build serialised rubric and feedback payloads for a grade record.
 *
 * @param stdClass $aidiscussion
 * @param stdClass $metrics
 * @return array
 */
function aidiscussion_build_grade_payload(stdClass $aidiscussion, stdClass $metrics): array {
    $rubricprogress = aidiscussion_build_rubric_progress($aidiscussion, $metrics);
    $flags = !empty($aidiscussion->integrityflagsenabled) ? $metrics->flags : [];

    $feedbackjson = json_encode([
        'source' => 'heuristic-rubric-v2',
        'summary' => get_string('heuristicfeedbacksummary', 'mod_aidiscussion', [
            'score' => format_float($metrics->finalscore, 2),
            'grademax' => format_float($metrics->grademax, 2),
        ]),
        'overall' => get_string('heuristicfeedbackoverall', 'mod_aidiscussion', [
            'initial' => (int)$metrics->hasinitialpost ? get_string('yes') : get_string('no'),
            'ai' => (int)$metrics->substantiveaireplycount,
            'peer' => (int)$metrics->substantivepeerreplycount,
            'requiredpeer' => (int)$metrics->requiredpeerreplies,
        ]),
        'initial' => get_string('heuristicfeedbackinitial', 'mod_aidiscussion', [
            'wordcount' => (int)$metrics->initialwordcount,
        ]),
        'ai' => get_string('heuristicfeedbackai', 'mod_aidiscussion', [
            'count' => (int)$metrics->substantiveaireplycount,
            'name' => aidiscussion_get_ai_display_name($aidiscussion),
        ]),
        'peer' => get_string('heuristicfeedbackpeer', 'mod_aidiscussion', [
            'count' => (int)$metrics->substantivepeerreplycount,
            'required' => (int)$metrics->requiredpeerreplies,
        ]),
        'areas' => array_map(static function (array $area): array {
            return [
                'label' => $area['label'],
                'feedback' => $area['feedback'],
            ];
        }, $rubricprogress['areas']),
    ]);

    return [
        'rubricprogress' => $rubricprogress,
        'flags' => $flags,
        'criterionjson' => json_encode($rubricprogress),
        'feedbackjson' => $feedbackjson,
        'integrityjson' => json_encode($flags),
    ];
}

/**
 * Build an unsaved grade record from computed metrics.
 *
 * @param stdClass $aidiscussion
 * @param stdClass $metrics
 * @param int $userid
 * @return stdClass
 */
function aidiscussion_build_grade_record(stdClass $aidiscussion, stdClass $metrics, int $userid): stdClass {
    $payload = aidiscussion_build_grade_payload($aidiscussion, $metrics);

    return (object) [
        'aidiscussionid' => (int)$aidiscussion->id,
        'userid' => $userid,
        'initialscore' => $metrics->initialpoints,
        'aiscore' => $metrics->aipoints,
        'peerscore' => $metrics->peerpoints,
        'finalscore' => $metrics->finalscore,
        'criterionjson' => $payload['criterionjson'],
        'feedbackjson' => $payload['feedbackjson'],
        'integrityjson' => $payload['integrityjson'],
        'providercomponent' => '',
        'modelname' => 'heuristic-v1',
        'timegraded' => time(),
        'timemodified' => time(),
    ];
}

/**
 * Load one stored teacher override for a learner.
 *
 * @param int $aidiscussionid
 * @param int $userid
 * @return stdClass|null
 */
function aidiscussion_get_grade_override(int $aidiscussionid, int $userid): ?stdClass {
    global $DB;

    $record = $DB->get_record('aidiscussion_grade_overrides', [
        'aidiscussionid' => $aidiscussionid,
        'userid' => $userid,
    ]);

    return $record ?: null;
}

/**
 * Apply a stored teacher override to a grade record.
 *
 * @param stdClass|null $grade
 * @param stdClass|null $override
 * @return stdClass|null
 */
function aidiscussion_apply_grade_override(?stdClass $grade, ?stdClass $override): ?stdClass {
    if (!$grade && !$override) {
        return null;
    }

    if (!$override) {
        $effective = $grade ? clone $grade : null;
        if ($effective) {
            $effective->isoverrideactive = false;
        }
        return $effective;
    }

    $effective = $grade ? clone $grade : (object) [
        'aidiscussionid' => (int)$override->aidiscussionid,
        'userid' => (int)$override->userid,
        'initialscore' => 0.0,
        'aiscore' => 0.0,
        'peerscore' => 0.0,
        'finalscore' => 0.0,
        'criterionjson' => '',
        'feedbackjson' => '',
        'integrityjson' => '',
        'providercomponent' => '',
        'modelname' => '',
        'timegraded' => 0,
        'timemodified' => 0,
    ];

    $effective->initialscore = (float)$override->initialscore;
    $effective->aiscore = (float)$override->aiscore;
    $effective->peerscore = (float)$override->peerscore;
    $effective->finalscore = (float)$override->finalscore;
    $effective->criterionjson = (string)($override->criterionjson ?? '');
    $effective->feedbackjson = (string)($override->feedbackjson ?? '');
    $effective->integrityjson = (string)($override->integrityjson ?? '');
    $effective->providercomponent = 'teacher-override';
    $effective->modelname = 'teacher-override';
    $effective->timegraded = (int)($override->timemodified ?: $override->timecreated);
    $effective->timemodified = (int)($override->timemodified ?: $override->timecreated);
    $effective->isoverrideactive = true;
    $effective->overriddenby = (int)$override->overriddenby;
    $effective->automaticgrade = $grade ? clone $grade : null;
    $effective->override = $override;

    return $effective;
}

/**
 * Return the grade state for a learner, including any active override.
 *
 * @param stdClass $aidiscussion
 * @param int $userid
 * @param array|null $posts
 * @return stdClass
 */
function aidiscussion_get_user_grade_state(stdClass $aidiscussion, int $userid, ?array $posts = null): stdClass {
    global $DB;

    $posts = $posts ?? aidiscussion_load_posts($aidiscussion->id);
    $metrics = aidiscussion_calculate_user_metrics($aidiscussion, $userid, $posts);
    $autograde = $DB->get_record('aidiscussion_grades', [
        'aidiscussionid' => $aidiscussion->id,
        'userid' => $userid,
    ]) ?: null;
    $override = aidiscussion_get_grade_override((int)$aidiscussion->id, $userid);
    $effectivegrade = aidiscussion_apply_grade_override($autograde, $override);

    if (!$effectivegrade && ($metrics->hasinitialpost || $metrics->aireplycount > 0 || $metrics->peerreplycount > 0)) {
        $effectivegrade = aidiscussion_build_grade_record($aidiscussion, $metrics, $userid);
        $effectivegrade->isoverrideactive = false;
    }

    if ($effectivegrade) {
        $metrics->grade = $effectivegrade;
        $metrics->initialpoints = (float)$effectivegrade->initialscore;
        $metrics->aipoints = (float)$effectivegrade->aiscore;
        $metrics->peerpoints = (float)$effectivegrade->peerscore;
        $metrics->finalscore = (float)$effectivegrade->finalscore;

        $storedflags = !empty($effectivegrade->integrityjson) ?
            json_decode((string)$effectivegrade->integrityjson, true) :
            [];
        if (is_array($storedflags)) {
            $metrics->flags = $storedflags;
        }
    } else {
        $metrics->grade = null;
    }

    return (object) [
        'metrics' => $metrics,
        'autograde' => $autograde,
        'override' => $override,
        'effectivegrade' => $effectivegrade,
    ];
}

/**
 * Build default values for the grade review override form.
 *
 * @param stdClass $aidiscussion
 * @param stdClass $metrics
 * @param stdClass|null $grade
 * @return array
 */
function aidiscussion_get_grade_review_form_defaults(
    stdClass $aidiscussion,
    stdClass $metrics,
    ?stdClass $grade,
): array {
    $feedbackdata = aidiscussion_decode_feedback_json($grade ? (string)$grade->feedbackjson : '');
    $areafeedback = aidiscussion_get_grade_area_feedback_map($grade);
    $defaults = [
        'overridesummary' => trim((string)($feedbackdata['summary'] ?? '')),
        'overrideoverall' => trim((string)($feedbackdata['overall'] ?? '')),
    ];

    foreach (aidiscussion_get_rubric_area_definitions() as $area => $definition) {
        if (!aidiscussion_is_rubric_area_enabled($aidiscussion, $area)) {
            continue;
        }

        $scorefield = aidiscussion_get_rubric_area_score_field($area);
        $defaults['override' . $area . 'score'] = aidiscussion_format_score_value(
            $grade ? (float)($grade->{$scorefield} ?? 0.0) : (float)($metrics->areas[$area]['points'] ?? 0.0),
        );
        $defaults['overridefeedback_' . $area] = $areafeedback[$area] ??
            aidiscussion_get_area_feedback_text($aidiscussion, $area, $metrics->areas[$area] ?? []);
    }

    if ($defaults['overridesummary'] === '') {
        $defaults['overridesummary'] = get_string('gradeoverridesummarydefault', 'mod_aidiscussion', [
            'score' => format_float((float)($grade->finalscore ?? $metrics->finalscore), 2),
            'grademax' => format_float((float)$metrics->grademax, 2),
        ]);
    }

    if ($defaults['overrideoverall'] === '') {
        $defaults['overrideoverall'] = $defaults['overridesummary'];
    }

    return $defaults;
}

/**
 * Build a manual override payload from teacher-entered component scores and feedback.
 *
 * @param stdClass $aidiscussion
 * @param stdClass $metrics
 * @param array $data
 * @param stdClass|null $currentgrade
 * @return array
 */
function aidiscussion_build_manual_override_payload(
    stdClass $aidiscussion,
    stdClass $metrics,
    array $data,
    ?stdClass $currentgrade = null,
): array {
    $rubrics = aidiscussion_get_effective_rubrics($aidiscussion);
    $currentfeedback = aidiscussion_get_grade_area_feedback_map($currentgrade);
    $areas = [];
    $feedbackareas = [];
    $areascores = [
        'initial' => 0.0,
        'ai' => 0.0,
        'peer' => 0.0,
    ];

    foreach (aidiscussion_get_rubric_area_definitions() as $area => $definition) {
        if (!aidiscussion_is_rubric_area_enabled($aidiscussion, $area)) {
            continue;
        }

        $rubric = $rubrics[$area];
        $weightedmaxpoints = (float)($metrics->areas[$area]['maxpoints'] ?? 0.0);
        $weightedpoints = max(0.0, min($weightedmaxpoints, (float)($data['override' . $area . 'score'] ?? 0.0)));
        $fraction = $weightedmaxpoints > 0.0 ? min(1.0, $weightedpoints / $weightedmaxpoints) : 0.0;

        $areafeedback = trim((string)($data['overridefeedback_' . $area] ?? ($currentfeedback[$area] ?? '')));
        if ($areafeedback === '') {
            $areafeedback = aidiscussion_get_area_feedback_text($aidiscussion, $area, $metrics->areas[$area] ?? []);
        } else {
            $areafeedback = aidiscussion_limit_text(aidiscussion_to_plain_text($areafeedback), 1200);
        }

        $criteria = [];
        foreach ($rubric->criteria as $criterion) {
            $criterionscore = aidiscussion_normalise_provider_score(
                $aidiscussion,
                (float)$criterion->maxscore * $fraction,
                (float)$criterion->maxscore
            );
            $criteria[] = [
                'shortname' => (string)$criterion->shortname,
                'description' => (string)$criterion->description,
                'score' => round($criterionscore, 5),
                'maxscore' => round((float)$criterion->maxscore, 5),
                'feedback' => $areafeedback,
            ];
        }

        $areas[$area] = [
            'label' => $definition['label'],
            'weight' => aidiscussion_get_rubric_area_weight($aidiscussion, $area),
            'rubricmaxscore' => round((float)$rubric->maxscore, 5),
            'weightedpoints' => round($weightedpoints, 5),
            'weightedmaxpoints' => round($weightedmaxpoints, 5),
            'instructions' => (string)$rubric->instructions,
            'feedback' => $areafeedback,
            'criteria' => $criteria,
        ];
        $feedbackareas[$area] = [
            'label' => $definition['label'],
            'feedback' => $areafeedback,
        ];
        $areascores[$area] = round($weightedpoints, 5);
    }

    $finalscore = round(min((float)$metrics->grademax, array_sum($areascores)), 5);
    $summary = trim((string)($data['overridesummary'] ?? ''));
    if ($summary === '') {
        $summary = get_string('gradeoverridesummarydefault', 'mod_aidiscussion', [
            'score' => format_float($finalscore, 2),
            'grademax' => format_float((float)$metrics->grademax, 2),
        ]);
    } else {
        $summary = aidiscussion_limit_text(aidiscussion_to_plain_text($summary), 1200);
    }

    $overall = trim((string)($data['overrideoverall'] ?? ''));
    if ($overall === '') {
        $overall = $summary;
    } else {
        $overall = aidiscussion_limit_text(aidiscussion_to_plain_text($overall), 2000);
    }

    $flags = [];
    if (!empty($aidiscussion->integrityflagsenabled)) {
        $flags = !empty($currentgrade->integrityjson) ? json_decode((string)$currentgrade->integrityjson, true) : [];
        if (!is_array($flags)) {
            $flags = $metrics->flags;
        }
    }

    return [
        'rubricprogress' => [
            'source' => 'teacher-override-v1',
            'areas' => $areas,
        ],
        'feedbackjson' => json_encode([
            'source' => 'teacher-override-v1',
            'summary' => $summary,
            'overall' => $overall,
            'areas' => $feedbackareas,
        ]),
        'integrityjson' => json_encode($flags),
        'areascores' => $areascores,
        'finalscore' => $finalscore,
    ];
}

/**
 * Save or update a teacher grade override.
 *
 * @param stdClass $aidiscussion
 * @param int $userid
 * @param array $data
 * @param int $teacherid
 * @param stdClass|null $metrics
 * @param stdClass|null $currentgrade
 * @return stdClass
 */
function aidiscussion_save_grade_override(
    stdClass $aidiscussion,
    int $userid,
    array $data,
    int $teacherid,
    ?stdClass $metrics = null,
    ?stdClass $currentgrade = null,
): stdClass {
    global $DB;

    $metrics = $metrics ?? aidiscussion_calculate_user_metrics($aidiscussion, $userid);
    if ($currentgrade === null) {
        $currentgrade = $DB->get_record('aidiscussion_grades', [
            'aidiscussionid' => $aidiscussion->id,
            'userid' => $userid,
        ]) ?: null;
    }
    $payload = aidiscussion_build_manual_override_payload($aidiscussion, $metrics, $data, $currentgrade);
    $existing = aidiscussion_get_grade_override((int)$aidiscussion->id, $userid);

    $record = (object) [
        'aidiscussionid' => (int)$aidiscussion->id,
        'userid' => $userid,
        'initialscore' => round((float)($payload['areascores']['initial'] ?? 0.0), 5),
        'aiscore' => round((float)($payload['areascores']['ai'] ?? 0.0), 5),
        'peerscore' => round((float)($payload['areascores']['peer'] ?? 0.0), 5),
        'finalscore' => round((float)$payload['finalscore'], 5),
        'criterionjson' => json_encode($payload['rubricprogress']),
        'feedbackjson' => $payload['feedbackjson'],
        'integrityjson' => $payload['integrityjson'],
        'overriddenby' => $teacherid,
        'timemodified' => time(),
    ];

    if ($existing) {
        $record->id = $existing->id;
        $DB->update_record('aidiscussion_grade_overrides', $record);
    } else {
        $record->timecreated = time();
        $record->id = $DB->insert_record('aidiscussion_grade_overrides', $record);
    }

    aidiscussion_update_grades($aidiscussion, $userid);

    return $record;
}

/**
 * Clear an active teacher override for a learner.
 *
 * @param stdClass $aidiscussion
 * @param int $userid
 * @return void
 */
function aidiscussion_clear_grade_override(stdClass $aidiscussion, int $userid): void {
    global $DB;

    $DB->delete_records('aidiscussion_grade_overrides', [
        'aidiscussionid' => $aidiscussion->id,
        'userid' => $userid,
    ]);

    aidiscussion_update_grades($aidiscussion, $userid);
}

/**
 * Load participants who have discussion activity or stored grades for review.
 *
 * @param stdClass $aidiscussion
 * @return array
 */
function aidiscussion_get_review_participants(stdClass $aidiscussion): array {
    global $DB;

    $posts = aidiscussion_load_posts($aidiscussion->id);
    $autogrades = $DB->get_records('aidiscussion_grades', ['aidiscussionid' => $aidiscussion->id]);
    $overrides = $DB->get_records('aidiscussion_grade_overrides', ['aidiscussionid' => $aidiscussion->id]);

    $gradesbyuser = [];
    foreach ($autogrades as $grade) {
        $gradesbyuser[(int)$grade->userid] = $grade;
    }

    $overridesbyuser = [];
    foreach ($overrides as $override) {
        $overridesbyuser[(int)$override->userid] = $override;
    }

    $userids = [];
    foreach ($posts as $post) {
        if ($post->authorrole === 'student' && !empty($post->userid)) {
            $userids[(int)$post->userid] = true;
        }
    }
    foreach (array_keys($gradesbyuser) as $userid) {
        $userids[(int)$userid] = true;
    }
    foreach (array_keys($overridesbyuser) as $userid) {
        $userids[(int)$userid] = true;
    }

    if (!$userids) {
        return [];
    }

    $users = $DB->get_records_list(
        'user',
        'id',
        array_keys($userids),
        '',
        'id, firstname, lastname, middlename, firstnamephonetic, lastnamephonetic, alternatename, picture, imagealt, email'
    );

    $participants = [];
    foreach ($users as $user) {
        $metrics = aidiscussion_calculate_user_metrics($aidiscussion, (int)$user->id, $posts);
        $autograde = $gradesbyuser[(int)$user->id] ?? null;
        $override = $overridesbyuser[(int)$user->id] ?? null;
        $effectivegrade = aidiscussion_apply_grade_override($autograde, $override);

        if (!$effectivegrade && ($metrics->hasinitialpost || $metrics->aireplycount > 0 || $metrics->peerreplycount > 0)) {
            $effectivegrade = aidiscussion_build_grade_record($aidiscussion, $metrics, (int)$user->id);
            $effectivegrade->isoverrideactive = false;
        }

        $participants[] = (object) [
            'user' => $user,
            'metrics' => $metrics,
            'autograde' => $autograde,
            'override' => $override,
            'effectivegrade' => $effectivegrade,
            'sourcelabel' => aidiscussion_get_grade_source_label($effectivegrade),
            'lastupdated' => (int)(($effectivegrade->timemodified ?? 0) ?: ($effectivegrade->timegraded ?? 0)),
        ];
    }

    usort($participants, static function (stdClass $left, stdClass $right): int {
        return strnatcasecmp(fullname($left->user), fullname($right->user));
    });

    return $participants;
}

/**
 * Build a response tester preview without creating real posts or grades.
 *
 * @param stdClass $aidiscussion
 * @param array $samples
 * @param int $contextid
 * @param int $actoruserid
 * @return stdClass
 */
function aidiscussion_build_response_tester_preview(
    stdClass $aidiscussion,
    array $samples,
    int $contextid,
    int $actoruserid,
): stdClass {
    $testuserid = 999999999;
    $initialresponse = trim((string)($samples['initialresponse'] ?? ''));
    $airesamples = isset($samples['airesponses']) && is_array($samples['airesponses']) ?
        $samples['airesponses'] :
        aidiscussion_parse_response_tester_entries((string)($samples['airesponses'] ?? ''));
    $peersamples = isset($samples['peerresponses']) && is_array($samples['peerresponses']) ?
        $samples['peerresponses'] :
        aidiscussion_parse_response_tester_entries((string)($samples['peerresponses'] ?? ''));

    $airesamples = array_values(array_filter(array_map(static function ($response): string {
        return trim((string)$response);
    }, $airesamples), static function (string $response): bool {
        return $response !== '';
    }));
    $peersamples = array_values(array_filter(array_map(static function ($response): string {
        return trim((string)$response);
    }, $peersamples), static function (string $response): bool {
        return $response !== '';
    }));

    $normalisedsamples = [
        'initialresponse' => $initialresponse,
        'airesponses' => $airesamples,
        'peerresponses' => $peersamples,
    ];

    $posts = aidiscussion_build_response_tester_posts($aidiscussion, $normalisedsamples, $testuserid);
    $metrics = aidiscussion_calculate_user_metrics($aidiscussion, $testuserid, $posts);
    $grade = aidiscussion_generate_grade_record($aidiscussion, $testuserid, [
        'posts' => $posts,
        'metrics' => $metrics,
        'contextid' => $contextid,
        'actoruserid' => $actoruserid,
        'allowfallback' => false,
    ]);

    return (object) [
        'samples' => $normalisedsamples,
        'posts' => $posts,
        'metrics' => $metrics,
        'grade' => $grade,
    ];
}

/**
 * Build a teacher-calibrated expected grade record for one benchmark case.
 *
 * @param stdClass $aidiscussion
 * @param stdClass $metrics
 * @param array $expectedjson
 * @param int $userid
 * @return stdClass
 */
function aidiscussion_build_benchmark_expected_grade(
    stdClass $aidiscussion,
    stdClass $metrics,
    array $expectedjson,
    int $userid = 0,
): stdClass {
    $payload = aidiscussion_build_provider_grade_payload($aidiscussion, $metrics, $expectedjson);

    return (object) [
        'aidiscussionid' => (int)($aidiscussion->id ?? 0),
        'userid' => $userid,
        'initialscore' => round((float)($payload['areascores']['initial'] ?? 0.0), 5),
        'aiscore' => round((float)($payload['areascores']['ai'] ?? 0.0), 5),
        'peerscore' => round((float)($payload['areascores']['peer'] ?? 0.0), 5),
        'finalscore' => round((float)$payload['finalscore'], 5),
        'criterionjson' => json_encode($payload['rubricprogress']),
        'feedbackjson' => $payload['feedbackjson'],
        'integrityjson' => $payload['integrityjson'],
        'providercomponent' => 'teacher-benchmark',
        'modelname' => 'teacher-calibrated',
        'timegraded' => time(),
        'timemodified' => time(),
    ];
}

/**
 * Compare an actual benchmark grade to the teacher target.
 *
 * @param stdClass $aidiscussion
 * @param stdClass $expectedgrade
 * @param stdClass $actualgrade
 * @return stdClass
 */
function aidiscussion_compare_benchmark_grades(
    stdClass $aidiscussion,
    stdClass $expectedgrade,
    stdClass $actualgrade,
): stdClass {
    $expectedcriteriondata = json_decode((string)$expectedgrade->criterionjson, true) ?: [];
    $actualcriteriondata = json_decode((string)$actualgrade->criterionjson, true) ?: [];
    $areas = [];
    $criterioncount = 0;
    $criteriondeltasum = 0.0;
    $maxcriteriondelta = 0.0;

    foreach (aidiscussion_get_rubric_area_definitions() as $area => $definition) {
        if (!aidiscussion_is_rubric_area_enabled($aidiscussion, $area)) {
            continue;
        }

        $expectedarea = $expectedcriteriondata['areas'][$area] ?? [];
        $actualarea = $actualcriteriondata['areas'][$area] ?? [];
        $actualcriteriaindex = [];
        foreach (($actualarea['criteria'] ?? []) as $criterion) {
            if (!is_array($criterion)) {
                continue;
            }

            $criterionkey = aidiscussion_normalise_grading_key((string)($criterion['shortname'] ?? ''));
            if ($criterionkey !== '') {
                $actualcriteriaindex[$criterionkey] = $criterion;
            }
        }

        $criteria = [];
        foreach (($expectedarea['criteria'] ?? []) as $criterion) {
            if (!is_array($criterion)) {
                continue;
            }

            $criterionkey = aidiscussion_normalise_grading_key((string)($criterion['shortname'] ?? ''));
            $actualcriterion = $actualcriteriaindex[$criterionkey] ?? [];
            $expectedscore = (float)($criterion['score'] ?? 0.0);
            $actualscore = (float)($actualcriterion['score'] ?? 0.0);
            $delta = round($actualscore - $expectedscore, 5);
            $absdelta = abs($delta);

            $criterioncount++;
            $criteriondeltasum += $absdelta;
            $maxcriteriondelta = max($maxcriteriondelta, $absdelta);
            $criteria[] = [
                'shortname' => (string)($criterion['shortname'] ?? ''),
                'expectedscore' => $expectedscore,
                'actualscore' => $actualscore,
                'delta' => $delta,
            ];
        }

        $expectedweighted = (float)($expectedarea['weightedpoints'] ?? 0.0);
        $actualweighted = (float)($actualarea['weightedpoints'] ?? 0.0);
        $areas[$area] = [
            'label' => $definition['label'],
            'expectedweightedpoints' => $expectedweighted,
            'actualweightedpoints' => $actualweighted,
            'delta' => round($actualweighted - $expectedweighted, 5),
            'criteria' => $criteria,
        ];
    }

    $finaldelta = round((float)$actualgrade->finalscore - (float)$expectedgrade->finalscore, 5);
    $criteriontolerance = aidiscussion_get_benchmark_criterion_tolerance($aidiscussion);
    $finaltolerance = aidiscussion_get_benchmark_final_tolerance($aidiscussion);

    return (object) [
        'areas' => $areas,
        'expectedfinalscore' => (float)$expectedgrade->finalscore,
        'actualfinalscore' => (float)$actualgrade->finalscore,
        'finaldelta' => $finaldelta,
        'maxcriteriondelta' => $maxcriteriondelta,
        'averagecriteriondelta' => $criterioncount > 0 ? round($criteriondeltasum / $criterioncount, 5) : 0.0,
        'criterioncount' => $criterioncount,
        'criteriontolerance' => $criteriontolerance,
        'finaltolerance' => $finaltolerance,
        'passed' => abs($finaldelta) <= $finaltolerance && $maxcriteriondelta <= $criteriontolerance,
    ];
}

/**
 * Run one saved benchmark case through the current grading pipeline.
 *
 * @param stdClass $aidiscussion
 * @param stdClass $benchmark
 * @param int $contextid
 * @param int $actoruserid
 * @return stdClass
 */
function aidiscussion_run_benchmark_case(
    stdClass $aidiscussion,
    stdClass $benchmark,
    int $contextid,
    int $actoruserid,
): stdClass {
    $samples = [
        'initialresponse' => (string)($benchmark->sampleinitialresponse ?? ''),
        'airesponses' => (string)($benchmark->sampleairesponses ?? ''),
        'peerresponses' => (string)($benchmark->samplepeerresponses ?? ''),
    ];
    $expectedjson = aidiscussion_decode_benchmark_expected_json((string)$benchmark->expectedjson);
    aidiscussion_validate_benchmark_expected_json($aidiscussion, $expectedjson);

    $preview = aidiscussion_build_response_tester_preview($aidiscussion, $samples, $contextid, $actoruserid);
    $expectedgrade = aidiscussion_build_benchmark_expected_grade(
        $aidiscussion,
        $preview->metrics,
        $expectedjson,
        (int)$preview->grade->userid
    );

    return (object) [
        'benchmark' => $benchmark,
        'samples' => $preview->samples,
        'metrics' => $preview->metrics,
        'expectedgrade' => $expectedgrade,
        'actualgrade' => $preview->grade,
        'comparison' => aidiscussion_compare_benchmark_grades($aidiscussion, $expectedgrade, $preview->grade),
    ];
}

/**
 * Run all saved benchmark cases for the activity.
 *
 * @param stdClass $aidiscussion
 * @param int $contextid
 * @param int $actoruserid
 * @return stdClass
 */
function aidiscussion_run_all_benchmarks(stdClass $aidiscussion, int $contextid, int $actoruserid): stdClass {
    $cases = aidiscussion_get_benchmark_cases((int)$aidiscussion->id);
    $results = [];
    $passedcount = 0;
    $finaldeltasum = 0.0;
    $criteriondeltasum = 0.0;
    $successfulcount = 0;

    foreach ($cases as $benchmark) {
        try {
            $result = aidiscussion_run_benchmark_case($aidiscussion, $benchmark, $contextid, $actoruserid);
            if (!empty($result->comparison->passed)) {
                $passedcount++;
            }
            $finaldeltasum += abs((float)$result->comparison->finaldelta);
            $criteriondeltasum += (float)$result->comparison->averagecriteriondelta;
            $successfulcount++;
            $results[] = $result;
        } catch (\Throwable $e) {
            $results[] = (object) [
                'benchmark' => $benchmark,
                'error' => $e->getMessage(),
            ];
        }
    }

    return (object) [
        'results' => $results,
        'casecount' => count($cases),
        'successfulcount' => $successfulcount,
        'passedcount' => $passedcount,
        'averagefinaldelta' => $successfulcount > 0 ? round($finaldeltasum / $successfulcount, 5) : 0.0,
        'averagecriteriondelta' => $successfulcount > 0 ? round($criteriondeltasum / $successfulcount, 5) : 0.0,
        'criteriontolerance' => aidiscussion_get_benchmark_criterion_tolerance($aidiscussion),
        'finaltolerance' => aidiscussion_get_benchmark_final_tolerance($aidiscussion),
    ];
}

/**
 * Recalculate and persist a learner's grade record.
 *
 * @param stdClass $aidiscussion
 * @param int $userid
 * @param array $options
 * @return stdClass
 */
function aidiscussion_recalculate_grade_record(stdClass $aidiscussion, int $userid, array $options = []): stdClass {
    global $DB;

    $record = aidiscussion_generate_grade_record($aidiscussion, $userid, $options);
    $existing = $DB->get_record('aidiscussion_grades', [
        'aidiscussionid' => $aidiscussion->id,
        'userid' => $userid,
    ], 'id', IGNORE_MISSING);

    if ($existing) {
        $record->id = $existing->id;
        $DB->update_record('aidiscussion_grades', $record);
    } else {
        $record->id = $DB->insert_record('aidiscussion_grades', $record);
    }

    aidiscussion_update_grades($aidiscussion, $userid);

    return $record;
}

/**
 * Return learner progress plus the stored grade record.
 *
 * @param stdClass $aidiscussion
 * @param int $userid
 * @return stdClass
 */
function aidiscussion_get_user_progress(stdClass $aidiscussion, int $userid): stdClass {
    $state = aidiscussion_get_user_grade_state($aidiscussion, $userid);
    return $state->metrics;
}

/**
 * Render the learner submissions that inform grading review.
 *
 * @param stdClass $aidiscussion
 * @param array $posts
 * @param int $userid
 * @param context_module $context
 * @return string
 */
function aidiscussion_render_review_submission_context(
    stdClass $aidiscussion,
    array $posts,
    int $userid,
    context_module $context,
): string {
    global $OUTPUT;

    $gradedposts = aidiscussion_get_user_grading_posts($aidiscussion, $posts, $userid);
    $sections = [];

    if (!empty($gradedposts['initial'])) {
        $sections[] = html_writer::div(
            html_writer::tag('h5', get_string('initialresponsecomponent', 'mod_aidiscussion'), ['class' => 'mb-2']) .
            html_writer::div(
                format_text(
                    $gradedposts['initial']->content,
                    $gradedposts['initial']->contentformat,
                    ['context' => $context, 'para' => true]
                ),
                'mod-aidiscussion-review-body'
            ),
            'border rounded p-3 mb-3'
        );
    }

    foreach ($gradedposts['ai'] as $index => $pair) {
        $title = get_string('aiinteractioncomponent', 'mod_aidiscussion') . ' ' . ($index + 1);
        $sections[] = html_writer::div(
            html_writer::tag('h5', s($title), ['class' => 'mb-2']) .
            html_writer::tag('p', s($pair['prompt']->authorname), ['class' => 'fw-bold mb-1']) .
            html_writer::div(
                format_text($pair['prompt']->content, $pair['prompt']->contentformat, ['context' => $context, 'para' => true]),
                'mb-3'
            ) .
            html_writer::tag('p', s($pair['response']->authorname), ['class' => 'fw-bold mb-1']) .
            html_writer::div(
                format_text(
                    $pair['response']->content,
                    $pair['response']->contentformat,
                    ['context' => $context, 'para' => true]
                ),
                ''
            ),
            'border rounded p-3 mb-3'
        );
    }

    foreach ($gradedposts['peer'] as $index => $pair) {
        $title = get_string('peerreplycomponent', 'mod_aidiscussion') . ' ' . ($index + 1);
        $sections[] = html_writer::div(
            html_writer::tag('h5', s($title), ['class' => 'mb-2']) .
            html_writer::tag('p', s($pair['prompt']->authorname), ['class' => 'fw-bold mb-1']) .
            html_writer::div(
                format_text($pair['prompt']->content, $pair['prompt']->contentformat, ['context' => $context, 'para' => true]),
                'mb-3'
            ) .
            html_writer::tag('p', s($pair['response']->authorname), ['class' => 'fw-bold mb-1']) .
            html_writer::div(
                format_text(
                    $pair['response']->content,
                    $pair['response']->contentformat,
                    ['context' => $context, 'para' => true]
                ),
                ''
            ),
            'border rounded p-3 mb-3'
        );
    }

    if (!$sections) {
        return $OUTPUT->notification(get_string('nolearneractivity', 'mod_aidiscussion'), 'info');
    }

    return html_writer::div(implode('', $sections), 'mod-aidiscussion-review-context');
}

/**
 * Render the rubric overview shown on the activity page.
 *
 * @param stdClass $aidiscussion
 * @return string
 */
function aidiscussion_render_rubric_overview(stdClass $aidiscussion): string {
    $rubrics = aidiscussion_get_effective_rubrics($aidiscussion);
    $html = '';

    foreach (aidiscussion_get_rubric_area_definitions() as $area => $definition) {
        if (!aidiscussion_is_rubric_area_enabled($aidiscussion, $area)) {
            continue;
        }

        $rubric = $rubrics[$area];
        $title = $definition['label'] . ' (' . format_float(aidiscussion_get_rubric_area_weight($aidiscussion, $area), 0) . '%)';
        $inner = html_writer::tag('h5', s($title), ['class' => 'mb-2']);

        if (trim((string)$rubric->instructions) !== '') {
            $inner .= html_writer::tag('p', s($rubric->instructions), ['class' => 'text-muted']);
        }

        $table = new html_table();
        $table->head = [
            get_string('criterionname', 'mod_aidiscussion'),
            get_string('maxscore', 'mod_aidiscussion'),
            get_string('description'),
        ];
        $table->data = [];

        foreach ($rubric->criteria as $criterion) {
            $table->data[] = [
                s($criterion->shortname),
                format_float((float)$criterion->maxscore, -1),
                s((string)$criterion->description),
            ];
        }

        $inner .= html_writer::table($table);
        $html .= html_writer::div($inner, 'border rounded p-3 mb-3');
    }

    return $html;
}

/**
 * Render detailed rubric feedback for the learner.
 *
 * @param stdClass|null $grade
 * @return string
 */
function aidiscussion_render_grade_feedback(?stdClass $grade): string {
    if (!$grade || empty($grade->criterionjson)) {
        return '';
    }

    $criteriondata = json_decode((string)$grade->criterionjson, true);
    $feedbackdata = !empty($grade->feedbackjson) ? json_decode((string)$grade->feedbackjson, true) : [];
    $flags = !empty($grade->integrityjson) ? json_decode((string)$grade->integrityjson, true) : [];

    if (!is_array($criteriondata) || empty($criteriondata['areas'])) {
        return '';
    }

    $html = '';
    if (!empty($feedbackdata['summary'])) {
        $html .= html_writer::tag('p', s((string)$feedbackdata['summary']), ['class' => 'mb-2']);
    }
    if (!empty($feedbackdata['overall'])) {
        $html .= html_writer::tag('p', s((string)$feedbackdata['overall']), ['class' => 'mb-3']);
    }

    foreach ($criteriondata['areas'] as $area => $areadata) {
        $title = trim((string)($areadata['label'] ?? ''));
        if ($title === '') {
            continue;
        }

        $weighted = format_float((float)($areadata['weightedpoints'] ?? 0), 2) . ' / ' .
            format_float((float)($areadata['weightedmaxpoints'] ?? 0), 2);
        $inner = html_writer::tag('h5', s($title), ['class' => 'mb-2']);
        $inner .= html_writer::tag(
            'p',
            s(get_string('rubricweightedscorevalue', 'mod_aidiscussion', $weighted)),
            ['class' => 'mb-2 text-muted']
        );

        $areafeedback = $feedbackdata['areas'][$area]['feedback'] ?? $areadata['feedback'] ?? '';
        if ($areafeedback !== '') {
            $inner .= html_writer::tag('p', s((string)$areafeedback), ['class' => 'mb-3']);
        }

        $table = new html_table();
        $table->head = [
            get_string('criterionname', 'mod_aidiscussion'),
            get_string('criterionprogress', 'mod_aidiscussion'),
            get_string('notes'),
        ];
        $table->data = [];
        foreach (($areadata['criteria'] ?? []) as $criterion) {
            $scoretext = format_float((float)($criterion['score'] ?? 0), 2) . ' / ' .
                format_float((float)($criterion['maxscore'] ?? 0), 2);
            $table->data[] = [
                s((string)($criterion['shortname'] ?? '')),
                s($scoretext),
                s((string)($criterion['feedback'] ?? '')),
            ];
        }

        $inner .= html_writer::table($table);
        $html .= html_writer::div($inner, 'border rounded p-3 mb-3');
    }

    if (is_array($flags) && $flags) {
        $items = '';
        foreach ($flags as $flag) {
            $items .= html_writer::tag('li', s((string)($flag['message'] ?? '')));
        }
        $html .= html_writer::div(
            html_writer::tag('h5', get_string('integrityflags', 'mod_aidiscussion'), ['class' => 'mb-2']) .
            html_writer::tag('ul', $items),
            'border rounded p-3 mb-3'
        );
    }

    return html_writer::div($html, 'mod-aidiscussion-grade-feedback');
}

/**
 * Build the display snippet for the reply target in the form.
 *
 * @param stdClass $parent
 * @return string
 */
function aidiscussion_get_parent_summary(stdClass $parent): string {
    $label = aidiscussion_get_post_author_name($parent);
    $content = aidiscussion_limit_text(
        s(aidiscussion_to_plain_text((string)$parent->content, (int)$parent->contentformat)),
        240
    );

    return $label . ': ' . $content;
}

/**
 * Render the visible post tree.
 *
 * @param stdClass $aidiscussion
 * @param stdClass $cm
 * @param context_module $context
 * @param int $userid
 * @param moodle_url $returnurl
 * @return string
 */
function aidiscussion_render_post_tree(
    stdClass $aidiscussion,
    stdClass $cm,
    context_module $context,
    int $userid,
    moodle_url $returnurl,
): string {
    $state = aidiscussion_get_visible_post_tree($aidiscussion, $context, $userid);
    $posts = $state['posts'];

    if (empty($posts)) {
        return html_writer::div(
            $GLOBALS['OUTPUT']->notification(get_string('nopostsyet', 'mod_aidiscussion'), 'info'),
            'mod-aidiscussion-empty'
        );
    }

    $pendingjobs = aidiscussion_get_pending_reply_jobs($aidiscussion->id);
    $html = '';
    foreach ($state['roots'] as $rootid) {
        $html .= aidiscussion_render_post_node(
            $posts[$rootid],
            $posts,
            $aidiscussion,
            $cm,
            $context,
            $userid,
            $returnurl,
            $pendingjobs,
            $state['hasinitialpost'],
            $state['canmanage']
        );
    }

    return html_writer::div($html, 'mod-aidiscussion-post-tree');
}

/**
 * Render a single post and its children.
 *
 * @param stdClass $post
 * @param array $posts
 * @param stdClass $aidiscussion
 * @param stdClass $cm
 * @param context_module $context
 * @param int $userid
 * @param moodle_url $returnurl
 * @param array $pendingjobs
 * @param bool $hasinitialpost
 * @param bool $canmanage
 * @return string
 */
function aidiscussion_render_post_node(
    stdClass $post,
    array $posts,
    stdClass $aidiscussion,
    stdClass $cm,
    context_module $context,
    int $userid,
    moodle_url $returnurl,
    array $pendingjobs,
    bool $hasinitialpost,
    bool $canmanage,
): string {
    $classes = ['border', 'rounded', 'p-3', 'mb-3'];
    if ($post->posttype === 'ai') {
        $classes[] = 'bg-light';
    }

    $headerbits = [
        html_writer::tag('strong', s($post->authorname)),
        html_writer::span(userdate($post->timecreated), 'text-muted small ms-2'),
    ];

    if ($post->visibility === 'private') {
        $headerbits[] = html_writer::span(
            get_string('privatebranch', 'mod_aidiscussion'),
            'badge rounded-pill bg-secondary ms-2'
        );
    }

    if (isset($pendingjobs[$post->id])) {
        $headerbits[] = html_writer::span(
            get_string('aireplypending', 'mod_aidiscussion'),
            'badge rounded-pill bg-info text-dark ms-2'
        );
    }

    $body = html_writer::div(
        format_text($post->content, $post->contentformat, ['context' => $context, 'para' => true]),
        'mod-aidiscussion-post-body'
    );

    $actions = '';
    $permission = aidiscussion_get_posting_permission(
        $aidiscussion,
        $context,
        $userid,
        $post,
        $hasinitialpost,
        $canmanage
    );
    if (!empty($permission['allowed'])) {
        $replyurl = new moodle_url('/mod/aidiscussion/post.php', [
            'cmid' => $cm->id,
            'parentid' => $post->id,
            'returnurl' => $returnurl->out(false),
        ]);
        $actions = html_writer::div(
            html_writer::link($replyurl, get_string('replylink', 'mod_aidiscussion'), ['class' => 'btn btn-link btn-sm ps-0']),
            'mod-aidiscussion-post-actions'
        );
    }

    $childrenhtml = '';
    foreach ($post->children as $childid) {
        $childrenhtml .= aidiscussion_render_post_node(
            $posts[$childid],
            $posts,
            $aidiscussion,
            $cm,
            $context,
            $userid,
            $returnurl,
            $pendingjobs,
            $hasinitialpost,
            $canmanage
        );
    }

    $inner = html_writer::div(implode('', $headerbits), 'mod-aidiscussion-post-header mb-2');
    $inner .= $body;
    $inner .= $actions;
    if ($childrenhtml !== '') {
        $inner .= html_writer::div($childrenhtml, 'ms-4 mt-3');
    }

    return html_writer::div($inner, implode(' ', $classes));
}
