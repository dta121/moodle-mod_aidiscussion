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
 * View page for mod_aidiscussion.
 *
 * @package   mod_aidiscussion
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use core_ai\manager as core_ai_manager;
use core\output\notification;
use mod_aidiscussion\local\ai\provider_registry;

$id = optional_param('id', 0, PARAM_INT);
$n = optional_param('n', 0, PARAM_INT);
$acceptaipolicy = optional_param('acceptaipolicy', 0, PARAM_BOOL);

[
    'cm' => $cm,
    'course' => $course,
    'aidiscussion' => $aidiscussion,
    'context' => $context,
] = aidiscussion_get_activity_from_params($id, $n);

require_login($course, true, $cm);
require_capability('mod/aidiscussion:view', $context);

$viewurl = new moodle_url('/mod/aidiscussion/view.php', ['id' => $cm->id]);

if ($acceptaipolicy && confirm_sesskey()) {
    $usercontext = context_user::instance($USER->id);
    require_capability('moodle/ai:acceptpolicy', $usercontext);
    core_ai_manager::user_policy_accepted($USER->id, $context->id);
    redirect($viewurl, get_string('aipolicyaccepted', 'mod_aidiscussion'), null, notification::NOTIFY_SUCCESS);
}

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$PAGE->set_url($viewurl);
$PAGE->set_title(format_string($aidiscussion->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

$canmanage = aidiscussion_user_can_manage($context, $USER->id);
$policyrequired = aidiscussion_user_requires_ai_policy($aidiscussion, $USER->id);
$progress = aidiscussion_get_user_progress($aidiscussion, $USER->id);
$postingpermission = aidiscussion_get_posting_permission($aidiscussion, $context, $USER->id);
$tree = aidiscussion_get_visible_post_tree($aidiscussion, $context, $USER->id);

echo $OUTPUT->header();

if (!empty($aidiscussion->intro)) {
    echo format_module_intro('aidiscussion', $aidiscussion, $cm->id);
}

echo $OUTPUT->heading(get_string('prompt', 'mod_aidiscussion'), 3);
echo html_writer::div(
    format_text($aidiscussion->prompt, $aidiscussion->promptformat, ['context' => $context]),
    'mod-aidiscussion-prompt mb-4'
);

$rulestable = new html_table();
$rulestable->head = [get_string('setting'), get_string('value')];
$rulestable->data = [
    [get_string('postbeforeview', 'mod_aidiscussion'), aidiscussion_bool_text($aidiscussion->postbeforeview)],
    [get_string('allowpeerreplies', 'mod_aidiscussion'), aidiscussion_bool_text($aidiscussion->allowpeerreplies)],
    [
        get_string('requiredpeerreplies', 'mod_aidiscussion'),
        !empty($aidiscussion->allowpeerreplies)
            ? (string)$aidiscussion->requiredpeerreplies
            : get_string('notapplicable', 'mod_aidiscussion'),
    ],
    [get_string('aienabled', 'mod_aidiscussion'), aidiscussion_bool_text($aidiscussion->aienabled)],
];
echo $OUTPUT->heading(get_string('discussionoverview', 'mod_aidiscussion'), 3);
echo html_writer::table($rulestable);

if (!empty($aidiscussion->showrubricbeforeposting)) {
    echo $OUTPUT->heading(get_string('rubricoverview', 'mod_aidiscussion'), 3);
    echo aidiscussion_render_rubric_overview($aidiscussion);
}

if (!$canmanage) {
    $progresstable = new html_table();
    $progresstable->head = [get_string('progressitem', 'mod_aidiscussion'), get_string('value')];
    $flags = [];
    foreach ($progress->flags as $flag) {
        $flags[] = $flag['message'];
    }
    $flagtext = !empty($aidiscussion->integrityflagsenabled) ?
        ($flags ? implode('; ', $flags) : get_string('none')) :
        get_string('notapplicable', 'mod_aidiscussion');
    $progresstable->data = [
        [
            get_string('initialresponsecomponent', 'mod_aidiscussion'),
            $progress->hasinitialpost ? get_string('yes') : get_string('no'),
        ],
        [get_string('aiinteractioncomponent', 'mod_aidiscussion'), (string)$progress->substantiveaireplycount],
        [
            get_string('peerreplycomponent', 'mod_aidiscussion'),
            $progress->substantivepeerreplycount . ' / ' . $progress->requiredpeerreplies,
        ],
        [
            get_string('currentgrade', 'mod_aidiscussion'),
            format_float($progress->finalscore, 2) . ' / ' . format_float($progress->grademax, 2),
        ],
        [get_string('integrityflags', 'mod_aidiscussion'), $flagtext],
    ];
    echo $OUTPUT->heading(get_string('yourprogress', 'mod_aidiscussion'), 3);
    echo html_writer::table($progresstable);

    if (!empty($progress->grade)) {
        echo $OUTPUT->heading(get_string('feedbackdetails', 'mod_aidiscussion'), 3);
        echo aidiscussion_render_grade_feedback($progress->grade);
    }
}

if ($canmanage) {
    $summary = new html_table();
    $summary->head = [get_string('setting'), get_string('value')];
    $summary->data = [
        [get_string('aidisplayname', 'mod_aidiscussion'), aidiscussion_get_ai_display_name($aidiscussion)],
        [
            get_string('replyprovider', 'mod_aidiscussion'),
            provider_registry::get_provider_name((string)$aidiscussion->replyprovider),
        ],
        [
            get_string('gradeprovider', 'mod_aidiscussion'),
            provider_registry::get_provider_name((string)$aidiscussion->gradeprovider),
        ],
        [get_string('publicaireplies', 'mod_aidiscussion'), aidiscussion_bool_text($aidiscussion->publicaireplies)],
        [get_string('replytopeerreplies', 'mod_aidiscussion'), aidiscussion_bool_text($aidiscussion->replytopeerreplies)],
        [get_string('minsubstantivewords', 'mod_aidiscussion'), (string)$aidiscussion->minsubstantivewords],
        [get_string('maxairepliesperstudent', 'mod_aidiscussion'), (string)$aidiscussion->maxairepliesperstudent],
        [
            get_string('aireplydelayminutes', 'mod_aidiscussion'),
            get_string('delayminutesvalue', 'mod_aidiscussion', (int)$aidiscussion->aireplydelayminutes),
        ],
    ];
    echo $OUTPUT->heading(get_string('configuration', 'mod_aidiscussion'), 3);
    echo html_writer::table($summary);

    $testerurl = new moodle_url('/mod/aidiscussion/tester.php', ['id' => $cm->id]);
    $reviewurl = new moodle_url('/mod/aidiscussion/review.php', ['id' => $cm->id]);
    echo html_writer::div(
        html_writer::link($reviewurl, get_string('opengradereview', 'mod_aidiscussion'), [
            'class' => 'btn btn-primary me-2',
        ]) .
        html_writer::link($testerurl, get_string('openresponsetester', 'mod_aidiscussion'), [
            'class' => 'btn btn-secondary',
        ]),
        'mb-4'
    );
}

if (!empty($aidiscussion->aienabled) && empty($aidiscussion->replyprovider)) {
    echo $OUTPUT->notification(get_string('providernotconfigured', 'mod_aidiscussion'), notification::NOTIFY_WARNING);
}

if ($policyrequired) {
    echo $OUTPUT->notification(
        get_string('aipolicyrequiredtopost', 'mod_aidiscussion', aidiscussion_get_ai_display_name($aidiscussion)),
        notification::NOTIFY_WARNING
    );
    echo html_writer::div(
        format_text(get_string('userpolicy', 'ai'), FORMAT_HTML, ['context' => $context]),
        'border rounded p-3 mb-3'
    );
    $accepturl = new moodle_url($viewurl, [
        'acceptaipolicy' => 1,
        'sesskey' => sesskey(),
    ]);
    echo $OUTPUT->single_button($accepturl, get_string('acceptai', 'ai'), 'post');
}

if ($tree['locked']) {
    echo $OUTPUT->notification(get_string('postbeforeviewlocked', 'mod_aidiscussion'), notification::NOTIFY_INFO);
}

if (!empty($postingpermission['allowed'])) {
    $posturl = new moodle_url('/mod/aidiscussion/post.php', [
        'cmid' => $cm->id,
        'returnurl' => $viewurl->out(false),
    ]);
    echo $OUTPUT->single_button($posturl, get_string('addinitialresponse', 'mod_aidiscussion'));
} else if (!$canmanage && !$progress->hasinitialpost) {
    echo $OUTPUT->notification($postingpermission['reason'], notification::NOTIFY_INFO);
}

echo $OUTPUT->heading(get_string('discussionposts', 'mod_aidiscussion'), 3);
echo aidiscussion_render_post_tree($aidiscussion, $cm, $context, $USER->id, $viewurl);

echo $OUTPUT->footer();
