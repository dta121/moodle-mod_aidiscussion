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
 * Library callbacks for mod_aidiscussion.
 *
 * @package   mod_aidiscussion
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/locallib.php');

/**
 * Return whether the plugin supports a feature.
 *
 * @param string $feature
 * @return bool|null
 */
function aidiscussion_supports(string $feature): ?bool {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_COMPLETION_HAS_RULES:
            return false;
        default:
            return null;
    }
}

/**
 * Add a new aidiscussion instance.
 *
 * @param stdClass $data
 * @param mod_aidiscussion_mod_form|null $mform
 * @return int
 */
function aidiscussion_add_instance($data, $mform = null): int {
    global $DB;

    $record = aidiscussion_formdata_to_record($data);
    $record->timecreated = time();
    $record->timemodified = $record->timecreated;

    $record->id = $DB->insert_record('aidiscussion', $record);
    aidiscussion_save_rubrics_from_form($record->id, $data);
    aidiscussion_grade_item_update($record);

    return (int)$record->id;
}

/**
 * Update an aidiscussion instance.
 *
 * @param stdClass $data
 * @param mod_aidiscussion_mod_form|null $mform
 * @return bool
 */
function aidiscussion_update_instance($data, $mform = null): bool {
    global $DB;

    $record = aidiscussion_formdata_to_record($data);
    $record->id = (int)$data->instance;
    $record->timemodified = time();

    $updated = $DB->update_record('aidiscussion', $record);
    aidiscussion_save_rubrics_from_form($record->id, $data);
    aidiscussion_grade_item_update($record);

    return $updated;
}

/**
 * Delete an aidiscussion instance.
 *
 * @param int $id
 * @return bool
 */
function aidiscussion_delete_instance(int $id): bool {
    global $DB;

    $aidiscussion = $DB->get_record('aidiscussion', ['id' => $id]);
    if (!$aidiscussion) {
        return false;
    }

    $DB->delete_records('aidiscussion_posts', ['aidiscussionid' => $id]);
    $DB->delete_records('aidiscussion_jobs', ['aidiscussionid' => $id]);
    $DB->delete_records('aidiscussion_grades', ['aidiscussionid' => $id]);
    $DB->delete_records('aidiscussion_grade_overrides', ['aidiscussionid' => $id]);
    $DB->delete_records('aidiscussion_benchmarks', ['aidiscussionid' => $id]);

    $rubrics = $DB->get_records('aidiscussion_rubrics', ['aidiscussionid' => $id], '', 'id');
    if ($rubrics) {
        [$sql, $params] = $DB->get_in_or_equal(array_keys($rubrics), SQL_PARAMS_NAMED);
        $DB->delete_records_select('aidiscussion_criteria', "rubricid {$sql}", $params);
    }
    $DB->delete_records('aidiscussion_rubrics', ['aidiscussionid' => $id]);
    $DB->delete_records('aidiscussion', ['id' => $id]);

    aidiscussion_grade_item_update($aidiscussion, ['deleted' => 1]);
    return true;
}

/**
 * Create or update the grade item for the activity.
 *
 * @param stdClass $aidiscussion
 * @param mixed $grades
 * @return int
 */
function aidiscussion_grade_item_update(stdClass $aidiscussion, $grades = null): int {
    global $CFG;

    require_once($CFG->libdir . '/gradelib.php');

    $item = [
        'itemname' => $aidiscussion->name,
    ];

    if (is_array($grades) && !empty($grades['deleted'])) {
        $item['deleted'] = 1;
        $grades = null;
    } else if (!empty($aidiscussion->grade)) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax'] = (float)$aidiscussion->grade;
        $item['grademin'] = 0;
    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }

    return grade_update(
        'mod/aidiscussion',
        $aidiscussion->course,
        'mod',
        'aidiscussion',
        $aidiscussion->id,
        0,
        $grades,
        $item
    );
}

/**
 * Push stored grades into the Moodle gradebook.
 *
 * @param stdClass $aidiscussion
 * @param int $userid
 * @return void
 */
function aidiscussion_update_grades(stdClass $aidiscussion, int $userid = 0): void {
    global $DB;

    if (empty($aidiscussion->id) || empty($aidiscussion->course)) {
        return;
    }

    $gradeconditions = ['aidiscussionid' => $aidiscussion->id];
    if ($userid) {
        $gradeconditions['userid'] = $userid;
    }

    $records = $DB->get_records('aidiscussion_grades', $gradeconditions);
    $overrides = $DB->get_records('aidiscussion_grade_overrides', $gradeconditions);

    $gradesbyuser = [];
    foreach ($records as $record) {
        $gradesbyuser[(int)$record->userid] = $record;
    }

    $overridesbyuser = [];
    foreach ($overrides as $override) {
        $overridesbyuser[(int)$override->userid] = $override;
    }

    $userids = array_unique(array_merge(array_keys($gradesbyuser), array_keys($overridesbyuser)));

    $grades = [];
    foreach ($userids as $gradeuserid) {
        $record = aidiscussion_apply_grade_override(
            $gradesbyuser[(int)$gradeuserid] ?? null,
            $overridesbyuser[(int)$gradeuserid] ?? null
        );
        if (!$record) {
            continue;
        }

        $grade = new stdClass();
        $grade->userid = (int)$record->userid;
        $grade->rawgrade = (float)$record->finalscore;
        $grade->feedback = aidiscussion_grade_feedback($record->feedbackjson);
        $grade->feedbackformat = FORMAT_PLAIN;
        $grade->datesubmitted = (int)$record->timemodified;
        $grade->dategraded = (int)($record->timegraded ?: $record->timemodified);
        $grades[] = $grade;
    }

    aidiscussion_grade_item_update($aidiscussion, $grades ?: null);
}
