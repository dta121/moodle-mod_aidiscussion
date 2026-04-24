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
 * Adhoc task for grade recalculation.
 *
 * @package   mod_aidiscussion
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aidiscussion\task;

/**
 * Adhoc task for grade recalculation.
 */
class process_grading extends \core\task\adhoc_task {
    /**
     * Return task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskprocessgrading', 'mod_aidiscussion');
    }

    /**
     * Execute the queued grading job.
     *
     * @return void
     */
    public function execute(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/aidiscussion/locallib.php');

        $data = (object)$this->get_custom_data();
        $job = aidiscussion_claim_job((int)($data->jobid ?? 0), 'grading');
        if (!$job) {
            return;
        }

        try {
            $aidiscussion = $DB->get_record('aidiscussion', ['id' => $job->aidiscussionid], '*', MUST_EXIST);
            aidiscussion_recalculate_grade_record($aidiscussion, (int)$job->userid);
            aidiscussion_finish_job($job, 'completed', 'Recalculated learner grade.');
        } catch (\Throwable $e) {
            aidiscussion_finish_job($job, 'failed', $e->getMessage());
            mtrace('mod_aidiscussion process_grading failed: ' . $e->getMessage());
        }
    }
}
