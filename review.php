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
 * Teacher review, override, and regrade UI for mod_aidiscussion.
 *
 * @package   mod_aidiscussion
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use core\output\notification;
use mod_aidiscussion\form\grade_review_form;

$id = required_param('id', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$regrade = optional_param('regrade', 0, PARAM_BOOL);
$clearoverride = optional_param('clearoverride', 0, PARAM_BOOL);

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

$listurl = new moodle_url('/mod/aidiscussion/review.php', ['id' => $cm->id]);
$pageurl = $userid > 0 ? new moodle_url($listurl, ['userid' => $userid]) : $listurl;
$viewurl = new moodle_url('/mod/aidiscussion/view.php', ['id' => $cm->id]);
$testerurl = new moodle_url('/mod/aidiscussion/tester.php', ['id' => $cm->id]);

if ($userid > 0 && $regrade) {
    require_sesskey();
    try {
        aidiscussion_recalculate_grade_record($aidiscussion, $userid, [
            'contextid' => (int)$context->id,
            'actoruserid' => (int)$USER->id,
        ]);
        $state = aidiscussion_get_user_grade_state($aidiscussion, $userid);
        $message = !empty($state->override) ?
            get_string('regradecompletedoverrideactive', 'mod_aidiscussion') :
            get_string('regradecompleted', 'mod_aidiscussion');
        redirect($pageurl, $message, 0, notification::NOTIFY_SUCCESS);
    } catch (Throwable $e) {
        redirect($pageurl, $e->getMessage(), 0, notification::NOTIFY_ERROR);
    }
}

if ($userid > 0 && $clearoverride) {
    require_sesskey();
    aidiscussion_clear_grade_override($aidiscussion, $userid);
    redirect($pageurl, get_string('gradeoverridecleared', 'mod_aidiscussion'), 0, notification::NOTIFY_SUCCESS);
}

$posts = aidiscussion_load_posts((int)$aidiscussion->id);
$participants = aidiscussion_get_review_participants($aidiscussion);
$selecteduser = null;
$state = null;
$overrideuser = null;
$reviewform = null;

if ($userid > 0) {
    $selecteduser = $DB->get_record(
        'user',
        ['id' => $userid],
        'id, firstname, lastname, middlename, firstnamephonetic, lastnamephonetic, alternatename, picture, imagealt, email',
        MUST_EXIST
    );
    $state = aidiscussion_get_user_grade_state($aidiscussion, $userid, $posts);

    if (!empty($state->override) && !empty($state->override->overriddenby)) {
        $overrideuser = $DB->get_record(
            'user',
            ['id' => (int)$state->override->overriddenby],
            'id, firstname, lastname, middlename, firstnamephonetic, lastnamephonetic, alternatename, picture, imagealt',
            IGNORE_MISSING
        );
    }

    $reviewform = new grade_review_form($pageurl, [
        'aidiscussion' => $aidiscussion,
        'metrics' => $state->metrics,
    ]);
    $reviewform->set_data([
        'id' => $cm->id,
        'userid' => $userid,
    ] + aidiscussion_get_grade_review_form_defaults($aidiscussion, $state->metrics, $state->effectivegrade));

    if ($formdata = $reviewform->get_data()) {
        aidiscussion_save_grade_override(
            $aidiscussion,
            $userid,
            (array)$formdata,
            (int)$USER->id,
            $state->metrics,
            $state->autograde
        );
        redirect($pageurl, get_string('gradeoverridesaved', 'mod_aidiscussion'), 0, notification::NOTIFY_SUCCESS);
    }
}

$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($aidiscussion->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($aidiscussion->name), 2);
echo $OUTPUT->heading(get_string('gradereview', 'mod_aidiscussion'), 3);

$topactions = html_writer::link($viewurl, get_string('openactivity', 'mod_aidiscussion'), [
    'class' => 'btn btn-outline-secondary me-2',
]);
$topactions .= html_writer::link($testerurl, get_string('openresponsetester', 'mod_aidiscussion'), [
    'class' => 'btn btn-outline-secondary me-2',
]);
if ($userid > 0) {
    $topactions .= html_writer::link($listurl, get_string('gradereviewlist', 'mod_aidiscussion'), [
        'class' => 'btn btn-secondary',
    ]);
}
echo html_writer::div($topactions, 'mb-4');

if ($userid <= 0) {
    if (!$participants) {
        echo $OUTPUT->notification(get_string('gradereviewempty', 'mod_aidiscussion'), notification::NOTIFY_INFO);
        echo $OUTPUT->footer();
        return;
    }

    $table = new html_table();
    $table->head = [
        get_string('learner', 'mod_aidiscussion'),
        get_string('currentgrade', 'mod_aidiscussion'),
        get_string('gradingsource', 'mod_aidiscussion'),
        get_string('lastupdated', 'mod_aidiscussion'),
        get_string('actions'),
    ];
    $table->data = [];

    foreach ($participants as $participant) {
        $reviewuserurl = new moodle_url($listurl, ['userid' => $participant->user->id]);
        $scoretext = $participant->effectivegrade ?
            format_float((float)$participant->effectivegrade->finalscore, 2) . ' / ' .
                format_float((float)$participant->metrics->grademax, 2) :
            get_string('notapplicable', 'mod_aidiscussion');
        $table->data[] = [
            fullname($participant->user),
            $scoretext,
            s((string)$participant->sourcelabel),
            $participant->lastupdated ? userdate($participant->lastupdated) : get_string('notapplicable', 'mod_aidiscussion'),
            html_writer::link($reviewuserurl, get_string('reviewgrade', 'mod_aidiscussion')),
        ];
    }

    echo html_writer::table($table);
    echo $OUTPUT->footer();
    return;
}

$reviewactions = '';
$regradeurl = new moodle_url($pageurl, ['regrade' => 1, 'sesskey' => sesskey()]);
$reviewactions .= html_writer::link($regradeurl, get_string('regradeautomaticscore', 'mod_aidiscussion'), [
    'class' => 'btn btn-primary me-2',
]);
if (!empty($state->override)) {
    $clearurl = new moodle_url($pageurl, ['clearoverride' => 1, 'sesskey' => sesskey()]);
    $reviewactions .= html_writer::link($clearurl, get_string('cleargradeoverride', 'mod_aidiscussion'), [
        'class' => 'btn btn-outline-danger',
    ]);
}
echo html_writer::div($reviewactions, 'mb-4');

echo $OUTPUT->heading(get_string('gradereviewfor', 'mod_aidiscussion', fullname($selecteduser)), 4);

$summary = new html_table();
$summary->head = [get_string('gradingcomponent', 'mod_aidiscussion'), get_string('value')];
$summary->data = [
    [
        get_string('currentgrade', 'mod_aidiscussion'),
        $state->effectivegrade ?
            format_float((float)$state->effectivegrade->finalscore, 2) . ' / ' .
                format_float((float)$state->metrics->grademax, 2) :
            get_string('notapplicable', 'mod_aidiscussion'),
    ],
    [get_string('gradingsource', 'mod_aidiscussion'), aidiscussion_get_grade_source_label($state->effectivegrade)],
    [
        get_string('lastupdated', 'mod_aidiscussion'),
        !empty($state->effectivegrade->timemodified) ?
            userdate((int)$state->effectivegrade->timemodified) :
            get_string('notapplicable', 'mod_aidiscussion'),
    ],
];

if (!empty($state->autograde)) {
    $summary->data[] = [
        get_string('automaticgrade', 'mod_aidiscussion'),
        format_float((float)$state->autograde->finalscore, 2) . ' / ' .
            format_float((float)$state->metrics->grademax, 2),
    ];
    $summary->data[] = [
        get_string('automaticsource', 'mod_aidiscussion'),
        aidiscussion_get_grade_source_label($state->autograde),
    ];
}

if (!empty($state->autograde->modelname) && (string)$state->autograde->modelname !== 'heuristic-v1') {
    $summary->data[] = [
        get_string('pluginprovidermodel', 'mod_aidiscussion'),
        s((string)$state->autograde->modelname),
    ];
}

if ($overrideuser) {
    $summary->data[] = [
        get_string('overriddenby', 'mod_aidiscussion'),
        fullname($overrideuser),
    ];
}

echo html_writer::table($summary);

if (!empty($state->override)) {
    echo $OUTPUT->notification(get_string('gradeoverrideactive', 'mod_aidiscussion'), notification::NOTIFY_INFO);
}

if ($state->effectivegrade) {
    echo $OUTPUT->heading(get_string('currentgradefeedback', 'mod_aidiscussion'), 4);
    echo aidiscussion_render_grade_feedback($state->effectivegrade);
}

if (!empty($state->override) && !empty($state->autograde)) {
    echo $OUTPUT->heading(get_string('automaticgradefeedback', 'mod_aidiscussion'), 4);
    echo aidiscussion_render_grade_feedback($state->autograde);
}

echo $OUTPUT->heading(get_string('learnersubmissions', 'mod_aidiscussion'), 4);
echo aidiscussion_render_review_submission_context($aidiscussion, $posts, $userid, $context);

echo $OUTPUT->heading(get_string('gradeoverride', 'mod_aidiscussion'), 4);
$reviewform->display();

echo $OUTPUT->footer();
