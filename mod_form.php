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
 * Activity settings form for mod_aidiscussion.
 *
 * @package   mod_aidiscussion
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once(__DIR__ . '/locallib.php');

use mod_aidiscussion\local\ai\provider_registry;

/**
 * Module form.
 */
class mod_aidiscussion_mod_form extends moodleform_mod {
    /**
     * Form definition.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;
        $config = get_config('aidiscussion');
        $provideroptions = provider_registry::get_generate_text_options();

        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements();

        $mform->addElement('header', 'promptheader', get_string('prompt', 'mod_aidiscussion'));

        $mform->addElement(
            'editor',
            'prompt_editor',
            get_string('prompt', 'mod_aidiscussion'),
            null,
            [
                'maxfiles' => 0,
                'context' => $this->context,
            ]
        );
        $mform->setType('prompt_editor', PARAM_RAW);
        $mform->addRule('prompt_editor', null, 'required', null, 'client');

        $mform->addElement('header', 'teacherexampleheader', get_string('teacherexampleheader', 'mod_aidiscussion'));
        $mform->addElement(
            'editor',
            'teacherexample_editor',
            get_string('teacherexample', 'mod_aidiscussion'),
            null,
            [
                'maxfiles' => 0,
                'context' => $this->context,
            ]
        );
        $mform->setType('teacherexample_editor', PARAM_RAW);
        $mform->addHelpButton('teacherexample_editor', 'teacherexample', 'mod_aidiscussion');

        $mform->addElement('header', 'rulesheader', get_string('discussionrules', 'mod_aidiscussion'));

        $mform->addElement('advcheckbox', 'postbeforeview', get_string('postbeforeview', 'mod_aidiscussion'));
        $mform->setDefault('postbeforeview', $config->defaultpostbeforeview ?? 1);

        $mform->addElement('advcheckbox', 'requireinitialpost', get_string('requireinitialpost', 'mod_aidiscussion'));
        $mform->setDefault('requireinitialpost', $config->defaultrequireinitialpost ?? 1);

        $mform->addElement('advcheckbox', 'allowpeerreplies', get_string('allowpeerreplies', 'mod_aidiscussion'));
        $mform->setDefault('allowpeerreplies', 1);

        $mform->addElement('text', 'requiredpeerreplies', get_string('requiredpeerreplies', 'mod_aidiscussion'));
        $mform->setType('requiredpeerreplies', PARAM_INT);
        $mform->setDefault('requiredpeerreplies', $config->defaultrequiredpeerreplies ?? 2);
        $mform->disabledIf('requiredpeerreplies', 'allowpeerreplies', 'notchecked');

        $mform->addElement('header', 'aiheader', get_string('aisettings', 'mod_aidiscussion'));

        $mform->addElement('advcheckbox', 'aienabled', get_string('aienabled', 'mod_aidiscussion'));
        $mform->setDefault('aienabled', $config->defaultaienabled ?? 1);

        $mform->addElement('text', 'aidisplayname', get_string('aidisplayname', 'mod_aidiscussion'));
        $mform->setType('aidisplayname', PARAM_TEXT);
        $mform->setDefault('aidisplayname', $config->defaultaidisplayname ?? 'AI facilitator');
        $mform->disabledIf('aidisplayname', 'aienabled', 'notchecked');

        $mform->addElement('select', 'replyprovider', get_string('replyprovider', 'mod_aidiscussion'), $provideroptions);
        $mform->setType('replyprovider', PARAM_TEXT);
        $mform->setDefault('replyprovider', $config->defaultreplyprovider ?? '');
        $mform->disabledIf('replyprovider', 'aienabled', 'notchecked');

        $mform->addElement('select', 'gradeprovider', get_string('gradeprovider', 'mod_aidiscussion'), $provideroptions);
        $mform->setType('gradeprovider', PARAM_TEXT);
        $mform->setDefault('gradeprovider', $config->defaultgradeprovider ?? '');
        $mform->disabledIf('gradeprovider', 'aienabled', 'notchecked');

        $mform->addElement('advcheckbox', 'publicaireplies', get_string('publicaireplies', 'mod_aidiscussion'));
        $mform->setDefault('publicaireplies', $config->defaultpublicaireplies ?? 1);
        $mform->disabledIf('publicaireplies', 'aienabled', 'notchecked');

        $mform->addElement('advcheckbox', 'allowprivateai', get_string('allowprivateai', 'mod_aidiscussion'));
        $mform->setDefault('allowprivateai', $config->defaultallowprivateai ?? 1);
        $mform->disabledIf('allowprivateai', 'aienabled', 'notchecked');

        $mform->addElement('advcheckbox', 'replytopeerreplies', get_string('replytopeerreplies', 'mod_aidiscussion'));
        $mform->setDefault('replytopeerreplies', $config->defaultreplytopeerreplies ?? 1);
        $mform->disabledIf('replytopeerreplies', 'aienabled', 'notchecked');

        $mform->addElement('text', 'minsubstantivewords', get_string('minsubstantivewords', 'mod_aidiscussion'));
        $mform->setType('minsubstantivewords', PARAM_INT);
        $mform->setDefault('minsubstantivewords', $config->defaultminsubstantivewords ?? 20);
        $mform->disabledIf('minsubstantivewords', 'aienabled', 'notchecked');

        $mform->addElement('text', 'maxairepliesperstudent', get_string('maxairepliesperstudent', 'mod_aidiscussion'));
        $mform->setType('maxairepliesperstudent', PARAM_INT);
        $mform->setDefault('maxairepliesperstudent', $config->defaultmaxairepliesperstudent ?? 3);
        $mform->disabledIf('maxairepliesperstudent', 'aienabled', 'notchecked');

        $mform->addElement('text', 'aireplydelayminutes', get_string('aireplydelayminutes', 'mod_aidiscussion'));
        $mform->setType('aireplydelayminutes', PARAM_INT);
        $mform->setDefault('aireplydelayminutes', $config->defaultaireplydelayminutes ?? 0);
        $mform->disabledIf('aireplydelayminutes', 'aienabled', 'notchecked');

        $mform->addElement('text', 'responsetone', get_string('responsetone', 'mod_aidiscussion'));
        $mform->setType('responsetone', PARAM_TEXT);
        $mform->setDefault('responsetone', $config->defaultresponsetone ?? 'Professional, warm, and engaging');
        $mform->disabledIf('responsetone', 'aienabled', 'notchecked');

        $mform->addElement(
            'textarea',
            'responseinstructions',
            get_string('responseinstructions', 'mod_aidiscussion'),
            ['rows' => 6, 'cols' => 80]
        );
        $mform->setType('responseinstructions', PARAM_RAW_TRIMMED);
        $mform->setDefault(
            'responseinstructions',
            $config->defaultresponseinstructions ?? get_string('defaultresponseinstructionsvalue', 'mod_aidiscussion')
        );
        $mform->disabledIf('responseinstructions', 'aienabled', 'notchecked');

        $mform->addElement(
            'textarea',
            'gradinginstructions',
            get_string('gradinginstructions', 'mod_aidiscussion'),
            ['rows' => 6, 'cols' => 80]
        );
        $mform->setType('gradinginstructions', PARAM_RAW_TRIMMED);
        $mform->setDefault(
            'gradinginstructions',
            $config->defaultgradinginstructions ?? get_string('defaultgradinginstructionsvalue', 'mod_aidiscussion')
        );
        $mform->disabledIf('gradinginstructions', 'aienabled', 'notchecked');

        $mform->addElement('advcheckbox', 'useexemplarforgrading', get_string('useexemplarforgrading', 'mod_aidiscussion'));
        $mform->setDefault('useexemplarforgrading', $config->defaultuseexemplarforgrading ?? 0);
        $mform->disabledIf('useexemplarforgrading', 'aienabled', 'notchecked');

        $mform->addElement('text', 'gradingtemperature', get_string('gradingtemperature', 'mod_aidiscussion'));
        $mform->setType('gradingtemperature', PARAM_FLOAT);
        $mform->setDefault('gradingtemperature', $config->defaultgradingtemperature ?? 0.0);
        $mform->disabledIf('gradingtemperature', 'aienabled', 'notchecked');

        $mform->addElement(
            'select',
            'gradinggranularity',
            get_string('gradinggranularity', 'mod_aidiscussion'),
            aidiscussion_get_grading_granularity_options()
        );
        $mform->setType('gradinggranularity', PARAM_TEXT);
        $mform->setDefault('gradinggranularity', $config->defaultgradinggranularity ?? 'half');
        $mform->disabledIf('gradinggranularity', 'aienabled', 'notchecked');

        $mform->addElement('advcheckbox', 'showrubricbeforeposting', get_string('showrubricbeforeposting', 'mod_aidiscussion'));
        $mform->setDefault('showrubricbeforeposting', $config->defaultshowrubricbeforeposting ?? 1);

        $mform->addElement('advcheckbox', 'pseudonymiseusers', get_string('pseudonymiseusers', 'mod_aidiscussion'));
        $mform->setDefault('pseudonymiseusers', $config->defaultpseudonymiseusers ?? 1);

        $mform->addElement('advcheckbox', 'integrityflagsenabled', get_string('integrityflagsenabled', 'mod_aidiscussion'));
        $mform->setDefault('integrityflagsenabled', $config->defaultintegrityflagsenabled ?? 1);

        $mform->addElement('header', 'gradingheader', get_string('grading', 'mod_aidiscussion'));

        $mform->addElement('header', 'rubricsheader', get_string('rubricsheader', 'mod_aidiscussion'));
        $mform->addElement('static', 'rubricbuilderdesc', '', get_string('rubricbuilderdesc', 'mod_aidiscussion'));

        foreach (aidiscussion_get_rubric_area_definitions() as $area => $definition) {
            $instructionsfield = $area . 'rubricinstructions';
            $criteriafield = $area . 'rubriccriteria';

            $mform->addElement(
                'textarea',
                $instructionsfield,
                get_string('rubricinstructionsfor', 'mod_aidiscussion', $definition['label']),
                ['rows' => 3, 'cols' => 80]
            );
            $mform->setType($instructionsfield, PARAM_RAW_TRIMMED);

            $mform->addElement(
                'textarea',
                $criteriafield,
                get_string('rubriccriteriafor', 'mod_aidiscussion', $definition['label']),
                ['rows' => 6, 'cols' => 80]
            );
            $mform->setType($criteriafield, PARAM_RAW_TRIMMED);
            $mform->addHelpButton($criteriafield, 'rubriccriteriaformat', 'mod_aidiscussion');
        }

        $instanceid = (int)($this->current?->instance ?? $this->_instance ?? 0);
        $cmid = (int)($this->current?->coursemodule ?? $this->_cm?->id ?? 0);

        $mform->addElement('header', 'responsetesterheader', get_string('responsetester', 'mod_aidiscussion'));
        if ($instanceid > 0 && $cmid > 0) {
            $testerurl = new moodle_url('/mod/aidiscussion/tester.php', ['id' => $cmid]);
            $link = html_writer::link($testerurl, get_string('openresponsetester', 'mod_aidiscussion'), [
                'class' => 'btn btn-secondary',
            ]);
            $content = html_writer::tag('p', s(get_string('responsetesterdesc', 'mod_aidiscussion')), ['class' => 'mb-2']) . $link;
            $mform->addElement('static', 'responsetesterlink', '', $content);
        } else {
            $mform->addElement('static', 'responsetesterlink', '', get_string('responsetestersavefirst', 'mod_aidiscussion'));
        }

        $mform->addElement('text', 'grade', get_string('grade'));
        $mform->setType('grade', PARAM_INT);
        $mform->setDefault('grade', 100);
        $mform->addRule('grade', null, 'numeric', null, 'client');

        $mform->addElement('text', 'initialweight', get_string('initialweight', 'mod_aidiscussion'));
        $mform->setType('initialweight', PARAM_FLOAT);
        $mform->setDefault('initialweight', 40);
        $mform->addRule('initialweight', null, 'numeric', null, 'client');

        $mform->addElement('text', 'aiweight', get_string('aiweight', 'mod_aidiscussion'));
        $mform->setType('aiweight', PARAM_FLOAT);
        $mform->setDefault('aiweight', 35);
        $mform->addRule('aiweight', null, 'numeric', null, 'client');

        $mform->addElement('text', 'peerweight', get_string('peerweight', 'mod_aidiscussion'));
        $mform->setType('peerweight', PARAM_FLOAT);
        $mform->setDefault('peerweight', 25);
        $mform->addRule('peerweight', null, 'numeric', null, 'client');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Preprocess values before form display.
     *
     * @param array $defaultvalues
     * @return void
     */
    public function data_preprocessing(&$defaultvalues): void {
        if (!empty($defaultvalues['prompt'])) {
            $defaultvalues['prompt_editor'] = [
                'text' => $defaultvalues['prompt'],
                'format' => $defaultvalues['promptformat'] ?? FORMAT_HTML,
            ];
        }

        $defaultvalues['teacherexample_editor'] = [
            'text' => $defaultvalues['teacherexample'] ?? '',
            'format' => $defaultvalues['teacherexampleformat'] ?? FORMAT_HTML,
        ];

        $aidiscussionid = (int)($defaultvalues['instance'] ?? $defaultvalues['id'] ?? 0);
        foreach (aidiscussion_get_rubric_form_defaults($aidiscussionid) as $field => $value) {
            $defaultvalues[$field] = $value;
        }
    }

    /**
     * Validation callback.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $prompt = trim((string)($data['prompt_editor']['text'] ?? ''));
        if ($prompt === '') {
            $errors['prompt_editor'] = get_string('errrequiredprompt', 'mod_aidiscussion');
        }

        if (!empty($data['aienabled']) && empty($data['replyprovider'])) {
            $errors['replyprovider'] = get_string('errproviderrequired', 'mod_aidiscussion');
        } else if (!empty($data['replyprovider']) && !provider_registry::is_known_provider((string)$data['replyprovider'])) {
            $errors['replyprovider'] = get_string('errproviderinvalid', 'mod_aidiscussion');
        }

        if (!empty($data['gradeprovider']) && !provider_registry::is_known_provider((string)$data['gradeprovider'])) {
            $errors['gradeprovider'] = get_string('errproviderinvalid', 'mod_aidiscussion');
        }

        if (!empty($data['aienabled']) && trim((string)($data['aidisplayname'] ?? '')) === '') {
            $errors['aidisplayname'] = get_string('erraidisplayname', 'mod_aidiscussion');
        }

        $weights = (float)$data['initialweight'] + (float)$data['aiweight'] + (float)$data['peerweight'];
        if (abs($weights - 100.0) > 0.0001) {
            $errors['peerweight'] = get_string('errweightsmustsum', 'mod_aidiscussion');
        }

        if ((int)$data['minsubstantivewords'] < 1) {
            $errors['minsubstantivewords'] = get_string('errminwords', 'mod_aidiscussion');
        }

        if ((int)$data['maxairepliesperstudent'] < 1) {
            $errors['maxairepliesperstudent'] = get_string('errmaxaireplies', 'mod_aidiscussion');
        }

        if ((int)$data['aireplydelayminutes'] < 0) {
            $errors['aireplydelayminutes'] = get_string('erraireplydelayminutes', 'mod_aidiscussion');
        }

        if ((float)$data['gradingtemperature'] < 0.0 || (float)$data['gradingtemperature'] > 1.0) {
            $errors['gradingtemperature'] = get_string('errgradingtemperature', 'mod_aidiscussion');
        }

        if (!isset(aidiscussion_get_grading_granularity_options()[(string)($data['gradinggranularity'] ?? '')])) {
            $errors['gradinggranularity'] = get_string('errgradinggranularity', 'mod_aidiscussion');
        }

        if (!empty($data['allowpeerreplies']) && (int)$data['requiredpeerreplies'] < 0) {
            $errors['requiredpeerreplies'] = get_string('errrequiredpeers', 'mod_aidiscussion');
        }

        $errors = $errors + aidiscussion_validate_rubric_form_data($data);

        if ((int)$data['grade'] < 0) {
            $errors['grade'] = get_string('error');
        }

        return $errors;
    }
}
