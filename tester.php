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
use mod_aidiscussion\form\benchmark_case_form;
use mod_aidiscussion\form\response_tester_form;

$id = required_param('id', PARAM_INT);
$loadcase = optional_param('loadcase', 0, PARAM_INT);
$editcase = optional_param('editcase', 0, PARAM_INT);
$deletecase = optional_param('deletecase', 0, PARAM_INT);
$runcase = optional_param('runcase', 0, PARAM_INT);
$runall = optional_param('runall', 0, PARAM_BOOL);

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

if ($deletecase > 0) {
    require_sesskey();
    aidiscussion_delete_benchmark_case($deletecase, (int)$aidiscussion->id);
    redirect($testerurl, get_string('benchmarkdeleted', 'mod_aidiscussion'), 0, notification::NOTIFY_SUCCESS);
}

$loadedbenchmark = $loadcase > 0 ? aidiscussion_get_benchmark_case($loadcase, (int)$aidiscussion->id) : null;
$editingbenchmark = $editcase > 0 ? aidiscussion_get_benchmark_case($editcase, (int)$aidiscussion->id) : null;

$PAGE->set_url($testerurl);
$PAGE->set_title(format_string($aidiscussion->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

$previewform = new response_tester_form($testerurl);
$previewdefaults = ['id' => $cm->id];
if ($loadedbenchmark) {
    $previewdefaults += aidiscussion_get_benchmark_preview_defaults($loadedbenchmark);
}
$previewform->set_data($previewdefaults);

$benchmarkform = new benchmark_case_form($testerurl, [
    'aidiscussion' => $aidiscussion,
]);
$benchmarkdefaults = ['id' => $cm->id];
$benchmarkdefaults += aidiscussion_get_benchmark_case_form_defaults($aidiscussion, $editingbenchmark);
$benchmarkform->set_data($benchmarkdefaults);

$preview = null;
$previewerror = '';
$benchmarksuite = null;
$benchmarkrunerror = '';

if ($benchmarkdata = $benchmarkform->get_data()) {
    try {
        $savedid = aidiscussion_save_benchmark_case($aidiscussion, $benchmarkdata, (int)$USER->id);
        redirect(
            new moodle_url($testerurl, ['editcase' => $savedid, 'loadcase' => $savedid]),
            get_string('benchmarksaved', 'mod_aidiscussion'),
            0,
            notification::NOTIFY_SUCCESS
        );
    } catch (\Throwable $e) {
        $benchmarkrunerror = $e->getMessage();
    }
} else if ($previewdata = $previewform->get_data()) {
    try {
        $preview = aidiscussion_build_response_tester_preview(
            $aidiscussion,
            [
                'initialresponse' => (string)($previewdata->sampleinitialresponse ?? ''),
                'airesponses' => (string)($previewdata->sampleairesponses ?? ''),
                'peerresponses' => (string)($previewdata->samplepeerresponses ?? ''),
            ],
            (int)$context->id,
            (int)$USER->id,
        );
    } catch (\Throwable $e) {
        $previewerror = $e->getMessage();
    }
}

if ($runcase > 0) {
    $benchmark = aidiscussion_get_benchmark_case($runcase, (int)$aidiscussion->id);
    if (!$benchmark) {
        $benchmarkrunerror = get_string('benchmarknotfound', 'mod_aidiscussion');
    } else {
        try {
            $result = aidiscussion_run_benchmark_case($aidiscussion, $benchmark, (int)$context->id, (int)$USER->id);
            $benchmarksuite = (object) [
                'results' => [$result],
                'casecount' => 1,
                'successfulcount' => 1,
                'passedcount' => !empty($result->comparison->passed) ? 1 : 0,
                'averagefinaldelta' => abs((float)$result->comparison->finaldelta),
                'averagecriteriondelta' => (float)$result->comparison->averagecriteriondelta,
                'criteriontolerance' => (float)$result->comparison->criteriontolerance,
                'finaltolerance' => (float)$result->comparison->finaltolerance,
            ];
        } catch (\Throwable $e) {
            $benchmarkrunerror = $e->getMessage();
        }
    }
} else if ($runall) {
    try {
        $benchmarksuite = aidiscussion_run_all_benchmarks($aidiscussion, (int)$context->id, (int)$USER->id);
    } catch (\Throwable $e) {
        $benchmarkrunerror = $e->getMessage();
    }
}

$benchmarks = aidiscussion_get_benchmark_cases((int)$aidiscussion->id);

$renderbenchmarksuite = static function (stdClass $suite, stdClass $aidiscussion): string {
    global $OUTPUT;

    if (empty($suite->results)) {
        return $OUTPUT->notification(get_string('nobenchmarks', 'mod_aidiscussion'), notification::NOTIFY_INFO);
    }

    $summary = new html_table();
    $summary->head = [get_string('gradingcomponent', 'mod_aidiscussion'), get_string('value')];
    $summary->data = [
        [get_string('benchmarkcases', 'mod_aidiscussion'), (int)$suite->casecount],
        [get_string('benchmarkpassed', 'mod_aidiscussion'), (int)$suite->passedcount . ' / ' . (int)$suite->casecount],
        [get_string('benchmarkaveragefinaldelta', 'mod_aidiscussion'), format_float((float)$suite->averagefinaldelta, 2)],
        [get_string('benchmarkaveragecriteriondelta', 'mod_aidiscussion'), format_float((float)$suite->averagecriteriondelta, 2)],
        [get_string('benchmarkfinaltolerance', 'mod_aidiscussion'), format_float((float)$suite->finaltolerance, 2)],
        [get_string('benchmarkcriteriontolerance', 'mod_aidiscussion'), format_float((float)$suite->criteriontolerance, 2)],
    ];

    $html = html_writer::table($summary);

    $casetable = new html_table();
    $casetable->head = [
        get_string('benchmarkname', 'mod_aidiscussion'),
        get_string('benchmarkexpectedscore', 'mod_aidiscussion'),
        get_string('benchmarkactualscore', 'mod_aidiscussion'),
        get_string('benchmarkdelta', 'mod_aidiscussion'),
        get_string('benchmarkstatus', 'mod_aidiscussion'),
    ];
    $casetable->data = [];

    foreach ($suite->results as $result) {
        if (!empty($result->error)) {
            $casetable->data[] = [
                format_string($result->benchmark->name),
                get_string('notapplicable', 'mod_aidiscussion'),
                get_string('notapplicable', 'mod_aidiscussion'),
                get_string('notapplicable', 'mod_aidiscussion'),
                s((string)$result->error),
            ];
            continue;
        }

        $casetable->data[] = [
            format_string($result->benchmark->name),
            format_float((float)$result->comparison->expectedfinalscore, 2),
            format_float((float)$result->comparison->actualfinalscore, 2),
            format_float((float)$result->comparison->finaldelta, 2),
            !empty($result->comparison->passed) ?
                get_string('benchmarkpass', 'mod_aidiscussion') :
                get_string('benchmarkreview', 'mod_aidiscussion'),
        ];
    }

    $html .= $OUTPUT->heading(get_string('benchmarkoverview', 'mod_aidiscussion'), 4);
    $html .= html_writer::table($casetable);

    foreach ($suite->results as $result) {
        if (!empty($result->error)) {
            continue;
        }

        $html .= $OUTPUT->heading(format_string($result->benchmark->name), 4);
        if (trim((string)($result->benchmark->description ?? '')) !== '') {
            $html .= html_writer::tag('p', s((string)$result->benchmark->description), ['class' => 'mb-2']);
        }

        $detail = new html_table();
        $detail->head = [get_string('gradingcomponent', 'mod_aidiscussion'), get_string('value')];
        $detail->data = [
            [
                get_string('benchmarkexpectedscore', 'mod_aidiscussion'),
                format_float((float)$result->comparison->expectedfinalscore, 2),
            ],
            [
                get_string('benchmarkactualscore', 'mod_aidiscussion'),
                format_float((float)$result->comparison->actualfinalscore, 2),
            ],
            [
                get_string('benchmarkdelta', 'mod_aidiscussion'),
                format_float((float)$result->comparison->finaldelta, 2),
            ],
            [
                get_string('benchmarkmaxcriteriondelta', 'mod_aidiscussion'),
                format_float((float)$result->comparison->maxcriteriondelta, 2),
            ],
        ];
        $html .= html_writer::table($detail);

        foreach ($result->comparison->areas as $area) {
            $html .= $OUTPUT->heading((string)$area['label'], 5);

            $areatable = new html_table();
            $areatable->head = [
                get_string('criterionname', 'mod_aidiscussion'),
                get_string('benchmarkexpectedscore', 'mod_aidiscussion'),
                get_string('benchmarkactualscore', 'mod_aidiscussion'),
                get_string('benchmarkdelta', 'mod_aidiscussion'),
            ];
            $areatable->data = [];

            foreach ($area['criteria'] as $criterion) {
                $areatable->data[] = [
                    s((string)$criterion['shortname']),
                    format_float((float)$criterion['expectedscore'], 2),
                    format_float((float)$criterion['actualscore'], 2),
                    format_float((float)$criterion['delta'], 2),
                ];
            }

            $areatable->data[] = [
                html_writer::tag('strong', get_string('benchmarkweightedarea', 'mod_aidiscussion')),
                html_writer::tag('strong', format_float((float)$area['expectedweightedpoints'], 2)),
                html_writer::tag('strong', format_float((float)$area['actualweightedpoints'], 2)),
                html_writer::tag('strong', format_float((float)$area['delta'], 2)),
            ];

            $html .= html_writer::table($areatable);
        }

        $html .= $OUTPUT->heading(get_string('feedbackdetails', 'mod_aidiscussion'), 5);
        $html .= aidiscussion_render_grade_feedback($result->actualgrade);
    }

    return $html;
};

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($aidiscussion->name), 2);
echo $OUTPUT->heading(get_string('responsetester', 'mod_aidiscussion'), 3);

echo $OUTPUT->notification(get_string('responsetesterusescurrentsettings', 'mod_aidiscussion'), notification::NOTIFY_INFO);

$actions = html_writer::link($settingsurl, get_string('editactivitysettings', 'mod_aidiscussion'), [
    'class' => 'btn btn-secondary me-2',
]);
$actions .= html_writer::link($viewurl, get_string('openactivity', 'mod_aidiscussion'), [
    'class' => 'btn btn-outline-secondary me-2',
]);
if ($benchmarks) {
    $actions .= html_writer::link(new moodle_url($testerurl, ['runall' => 1]), get_string('runallbenchmarks', 'mod_aidiscussion'), [
        'class' => 'btn btn-primary',
    ]);
}
echo html_writer::div($actions, 'mb-3');

echo $OUTPUT->heading(get_string('previewresults', 'mod_aidiscussion'), 4);
$previewform->display();

if ($loadedbenchmark) {
    echo $OUTPUT->notification(
        get_string('benchmarkloadedforpreview', 'mod_aidiscussion', format_string($loadedbenchmark->name)),
        notification::NOTIFY_INFO
    );
}

if ($previewerror !== '') {
    echo $OUTPUT->notification($previewerror, notification::NOTIFY_ERROR);
}

if ($preview) {
    $summary = new html_table();
    $summary->head = [get_string('gradingcomponent', 'mod_aidiscussion'), get_string('value')];
    $summary->data = [
        [
            get_string('currentgrade', 'mod_aidiscussion'),
            format_float((float)$preview->grade->finalscore, 2) . ' / ' .
                format_float((float)$preview->metrics->grademax, 2),
        ],
        [
            get_string('initialresponsecomponent', 'mod_aidiscussion'),
            format_float((float)$preview->grade->initialscore, 2) . ' / ' .
                format_float((float)$preview->metrics->initialmaxpoints, 2),
        ],
    ];

    if (!empty($aidiscussion->aienabled)) {
        $summary->data[] = [
            get_string('aiinteractioncomponent', 'mod_aidiscussion'),
            format_float((float)$preview->grade->aiscore, 2) . ' / ' .
                format_float((float)$preview->metrics->aimaxpoints, 2),
        ];
    }

    if (!empty($aidiscussion->allowpeerreplies)) {
        $summary->data[] = [
            get_string('peerreplycomponent', 'mod_aidiscussion'),
            format_float((float)$preview->grade->peerscore, 2) . ' / ' .
                format_float((float)$preview->metrics->peermaxpoints, 2),
        ];
    }

    echo html_writer::table($summary);
    echo $OUTPUT->heading(get_string('feedbackdetails', 'mod_aidiscussion'), 5);
    echo aidiscussion_render_grade_feedback($preview->grade);
}

echo $OUTPUT->heading(get_string('benchmarkcases', 'mod_aidiscussion'), 4);
echo $OUTPUT->notification(get_string('benchmarktesterusescurrentsettings', 'mod_aidiscussion'), notification::NOTIFY_INFO);

if ($benchmarkrunerror !== '') {
    echo $OUTPUT->notification($benchmarkrunerror, notification::NOTIFY_ERROR);
}

$benchmarkform->display();

if ($benchmarks) {
    $table = new html_table();
    $table->head = [
        get_string('benchmarkname', 'mod_aidiscussion'),
        get_string('benchmarkdescription', 'mod_aidiscussion'),
        get_string('actions'),
    ];
    $table->data = [];

    foreach ($benchmarks as $benchmark) {
        $loadurl = new moodle_url($testerurl, ['loadcase' => $benchmark->id]);
        $editurl = new moodle_url($testerurl, ['editcase' => $benchmark->id]);
        $runurl = new moodle_url($testerurl, ['runcase' => $benchmark->id]);
        $deleteurl = new moodle_url($testerurl, ['deletecase' => $benchmark->id, 'sesskey' => sesskey()]);

        $actionshtml = html_writer::link($loadurl, get_string('benchmarkload', 'mod_aidiscussion'), ['class' => 'me-2']);
        $actionshtml .= html_writer::link($editurl, get_string('edit'), ['class' => 'me-2']);
        $actionshtml .= html_writer::link($runurl, get_string('benchmarkrun', 'mod_aidiscussion'), ['class' => 'me-2']);
        $actionshtml .= html_writer::link($deleteurl, get_string('delete'));

        $table->data[] = [
            format_string($benchmark->name),
            s((string)($benchmark->description ?? '')),
            $actionshtml,
        ];
    }

    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('nobenchmarks', 'mod_aidiscussion'), notification::NOTIFY_INFO);
}

if ($benchmarksuite) {
    echo $OUTPUT->heading(get_string('benchmarkresults', 'mod_aidiscussion'), 4);
    echo $renderbenchmarksuite($benchmarksuite, $aidiscussion);
}

echo $OUTPUT->footer();
