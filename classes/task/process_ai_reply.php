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

namespace mod_aidiscussion\task;

use mod_aidiscussion\local\ai\provider_client;

defined('MOODLE_INTERNAL') || die();

/**
 * Adhoc task for AI reply processing.
 */
class process_ai_reply extends \core\task\adhoc_task {
    /**
     * Return task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskprocessaireply', 'mod_aidiscussion');
    }

    /**
     * Execute the queued reply job.
     *
     * @return void
     */
    public function execute(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/aidiscussion/locallib.php');

        $data = (object)$this->get_custom_data();
        $job = aidiscussion_claim_job((int)($data->jobid ?? 0), 'reply');
        if (!$job) {
            return;
        }

        try {
            $aidiscussion = $DB->get_record('aidiscussion', ['id' => $job->aidiscussionid], '*', MUST_EXIST);
            if (empty($aidiscussion->aienabled) || trim((string)$aidiscussion->replyprovider) === '') {
                aidiscussion_finish_job($job, 'skipped', 'AI reply skipped because the activity AI is disabled.');
                return;
            }

            $cm = get_coursemodule_from_instance('aidiscussion', $aidiscussion->id, $aidiscussion->course, false, MUST_EXIST);
            $context = \context_module::instance($cm->id);
            $posts = aidiscussion_load_posts($aidiscussion->id);
            $post = $posts[$job->postid] ?? null;

            if (!$post) {
                aidiscussion_finish_job($job, 'failed', 'Source post no longer exists.');
                return;
            }

            if (!aidiscussion_should_queue_ai_reply($aidiscussion, $post)) {
                aidiscussion_finish_job($job, 'skipped', 'The source post is no longer eligible for an AI reply.');
                return;
            }

            $prompt = aidiscussion_build_ai_reply_prompt($aidiscussion, $posts, $post);
            $result = provider_client::generate_text(
                (string)$aidiscussion->replyprovider,
                (int)$context->id,
                (int)$job->userid,
                $prompt
            );

            if (empty($result->success) || trim((string)$result->generatedcontent) === '') {
                $message = trim((string)($result->errormessage ?? ''));
                if ($message === '') {
                    $message = 'The AI provider returned an empty reply.';
                }
                aidiscussion_finish_job($job, 'failed', $message);
                return;
            }

            $aipost = aidiscussion_create_ai_post(
                $aidiscussion,
                $post,
                (int)$job->userid,
                (string)$result->generatedcontent,
                (string)$result->providercomponent,
                (string)$result->modelname
            );

            aidiscussion_finish_job($job, 'completed', 'Created AI reply post ' . $aipost->id . '.');
        } catch (\Throwable $e) {
            aidiscussion_finish_job($job, 'failed', $e->getMessage());
            mtrace('mod_aidiscussion process_ai_reply failed: ' . $e->getMessage());
        }
    }
}
