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
 * Create a new post or reply in mod_aidiscussion.
 *
 * @package   mod_aidiscussion
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use core\output\notification;
use mod_aidiscussion\form\post_form;

$cmid = required_param('cmid', PARAM_INT);
$parentid = optional_param('parentid', 0, PARAM_INT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

[
    'cm' => $cm,
    'course' => $course,
    'aidiscussion' => $aidiscussion,
    'context' => $context,
] = aidiscussion_get_activity_from_params($cmid, 0);

require_login($course, true, $cm);
require_capability('mod/aidiscussion:view', $context);

$returnurlobj = $returnurl !== '' ? new moodle_url($returnurl) : new moodle_url('/mod/aidiscussion/view.php', ['id' => $cm->id]);
$parent = null;

if ($parentid) {
    $posts = aidiscussion_load_posts($aidiscussion->id);
    if (empty($posts[$parentid])) {
        throw new moodle_exception('invalidparentpost', 'mod_aidiscussion');
    }
    $parent = $posts[$parentid];
}

$permission = aidiscussion_get_posting_permission($aidiscussion, $context, $USER->id, $parent);
if (empty($permission['allowed'])) {
    redirect($returnurlobj, $permission['reason'], null, notification::NOTIFY_ERROR);
}

if (aidiscussion_user_requires_ai_policy($aidiscussion, $USER->id)) {
    redirect(
        $returnurlobj,
        get_string('aipolicyrequiredtopost', 'mod_aidiscussion', aidiscussion_get_ai_display_name($aidiscussion)),
        null,
        notification::NOTIFY_WARNING
    );
}

$mform = new post_form(null, [
    'parentsummary' => $parent ? aidiscussion_get_parent_summary($parent) : '',
    'isreply' => !empty($parent),
]);
$mform->set_data([
    'cmid' => $cm->id,
    'parentid' => $parentid,
    'returnurl' => $returnurlobj->out(false),
]);

if ($mform->is_cancelled()) {
    redirect($returnurlobj);
}

if ($data = $mform->get_data()) {
    $post = aidiscussion_create_post(
        $aidiscussion,
        $context,
        $USER->id,
        (string)$data->content,
        (int)$data->parentid,
        FORMAT_PLAIN
    );
    aidiscussion_after_post_created($aidiscussion, $cm, $context, $post);

    $message = empty($parent) ?
        get_string('responsesaved', 'mod_aidiscussion') :
        get_string('replysaved', 'mod_aidiscussion');
    redirect($returnurlobj, $message, null, notification::NOTIFY_SUCCESS);
}

$PAGE->set_url('/mod/aidiscussion/post.php', [
    'cmid' => $cm->id,
    'parentid' => $parentid,
]);
$PAGE->set_title(format_string($aidiscussion->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($aidiscussion->name), 2);
echo $OUTPUT->heading(get_string($parent ? 'replyheading' : 'newresponseheading', 'mod_aidiscussion'), 3);

echo html_writer::div(
    format_text($aidiscussion->prompt, $aidiscussion->promptformat, ['context' => $context]),
    'mod-aidiscussion-prompt mb-4'
);

$mform->display();

echo $OUTPUT->footer();
