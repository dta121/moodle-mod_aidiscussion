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
 * Teacher-only response tester for mod_aidiscussion.
 *
 * @package   mod_aidiscussion
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use core\output\notification;
use mod_aidiscussion\form\response_tester_form;

$id = required_param('id', PARAM_INT);

[
    'cm' => $cm,
    'course' => $course,
    'aidiscussion' => $aidiscussion,
    'context' => $context,
] = aidiscussion_get_activity_from_params($id, 0);

require_login($course, true, $cm);
require_capability('mod/aidiscussion:view', $context);

if (!aidiscussion_user_can_manage($context, $USER->id)) {
    throw new required_capability_exception($context, 'mod/aidiscussion:grade', 'nopermissions', '');
}

$testerurl = new moodle_url('/mod/aidiscussion/tester.php', ['id' => $cm->id]);
$viewurl = new moodle_url('/mod/aidiscussion/view.php', ['id' => $cm->id]);
$settingsurl = new moodle_url('/course/modedit.php', ['update' => $cm->id, 'return' => 1]);

$PAGE->set_url($testerurl);
$PAGE->set_title(format_string($aidiscussion->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

$mform = new response_tester_form($testerurl);
$mform->set_data([
    'id' => $cm->id,
]);

$preview = null;
if ($data = $mform->get_data()) {
    $preview = aidiscussion_build_response_tester_preview($aidiscussion, [
        'initialresponse' => (string)($data->sampleinitialresponse ?? ''),
        'airesponses' => (string)($data->sampleairesponses ?? ''),
        'peerresponses' => (string)($data->samplepeerresponses ?? ''),
    ]);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($aidiscussion->name), 2);
echo $OUTPUT->heading(get_string('responsetester', 'mod_aidiscussion'), 3);

echo $OUTPUT->notification(get_string('responsetesterusescurrentsettings', 'mod_aidiscussion'), notification::NOTIFY_INFO);

$actions = html_writer::link($settingsurl, get_string('editactivitysettings', 'mod_aidiscussion'), [
    'class' => 'btn btn-secondary me-2',
]);
$actions .= html_writer::link($viewurl, get_string('openactivity', 'mod_aidiscussion'), [
    'class' => 'btn btn-outline-secondary',
]);
echo html_writer::div($actions, 'mb-3');

$mform->display();

if ($preview) {
    $summary = new html_table();
    $summary->head = [get_string('gradingcomponent', 'mod_aidiscussion'), get_string('value')];
    $summary->data = [
        [
            get_string('currentgrade', 'mod_aidiscussion'),
            format_float($preview->metrics->finalscore, 2) . ' / ' . format_float($preview->metrics->grademax, 2),
        ],
        [
            get_string('initialresponsecomponent', 'mod_aidiscussion'),
            format_float($preview->metrics->initialpoints, 2) . ' / ' . format_float($preview->metrics->initialmaxpoints, 2),
        ],
    ];

    if (!empty($aidiscussion->aienabled)) {
        $summary->data[] = [
            get_string('aiinteractioncomponent', 'mod_aidiscussion'),
            format_float($preview->metrics->aipoints, 2) . ' / ' . format_float($preview->metrics->aimaxpoints, 2),
        ];
    }

    if (!empty($aidiscussion->allowpeerreplies)) {
        $summary->data[] = [
            get_string('peerreplycomponent', 'mod_aidiscussion'),
            format_float($preview->metrics->peerpoints, 2) . ' / ' . format_float($preview->metrics->peermaxpoints, 2),
        ];
    }

    echo $OUTPUT->heading(get_string('previewresults', 'mod_aidiscussion'), 3);
    echo html_writer::table($summary);
    echo $OUTPUT->heading(get_string('feedbackdetails', 'mod_aidiscussion'), 4);
    echo aidiscussion_render_grade_feedback($preview->grade);
}

echo $OUTPUT->footer();
