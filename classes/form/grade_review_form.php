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

namespace mod_aidiscussion\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/mod/aidiscussion/locallib.php');

/**
 * Teacher form for reviewing, overriding, and regrading one learner.
 *
 * @package   mod_aidiscussion
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_review_form extends \moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;
        $aidiscussion = $this->_customdata['aidiscussion'];
        $metrics = $this->_customdata['metrics'];

        $mform->addElement('static', 'overrideintro', '', get_string('gradereviewoverrideintro', 'mod_aidiscussion'));

        $mform->addElement('header', 'overridescoresheader', get_string('overridescoresheader', 'mod_aidiscussion'));
        foreach (\aidiscussion_get_rubric_area_definitions() as $area => $definition) {
            if (!\aidiscussion_is_rubric_area_enabled($aidiscussion, $area)) {
                continue;
            }

            $field = 'override' . $area . 'score';
            $maxpoints = \aidiscussion_format_score_value((float)($metrics->areas[$area]['maxpoints'] ?? 0.0));
            $label = get_string('overridescorefor', 'mod_aidiscussion', (object) [
                'label' => $definition['label'],
                'max' => $maxpoints,
            ]);
            $mform->addElement('text', $field, $label, ['size' => 8]);
            $mform->setType($field, PARAM_RAW_TRIMMED);
        }

        $mform->addElement('header', 'overridefeedbackheader', get_string('overridefeedbackheader', 'mod_aidiscussion'));
        $mform->addElement('text', 'overridesummary', get_string('overridesummary', 'mod_aidiscussion'), ['size' => 80]);
        $mform->setType('overridesummary', PARAM_RAW_TRIMMED);

        $mform->addElement(
            'textarea',
            'overrideoverall',
            get_string('overrideoverall', 'mod_aidiscussion'),
            ['rows' => 4, 'cols' => 80]
        );
        $mform->setType('overrideoverall', PARAM_RAW_TRIMMED);

        foreach (\aidiscussion_get_rubric_area_definitions() as $area => $definition) {
            if (!\aidiscussion_is_rubric_area_enabled($aidiscussion, $area)) {
                continue;
            }

            $field = 'overridefeedback_' . $area;
            $mform->addElement(
                'textarea',
                $field,
                get_string('overridefeedbackfor', 'mod_aidiscussion', $definition['label']),
                ['rows' => 3, 'cols' => 80]
            );
            $mform->setType($field, PARAM_RAW_TRIMMED);
        }

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);

        $mform->addElement('submit', 'savegradeoverride', get_string('savegradeoverride', 'mod_aidiscussion'));
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
        $aidiscussion = $this->_customdata['aidiscussion'];
        $metrics = $this->_customdata['metrics'];

        foreach (\aidiscussion_get_rubric_area_definitions() as $area => $definition) {
            if (!\aidiscussion_is_rubric_area_enabled($aidiscussion, $area)) {
                continue;
            }

            $field = 'override' . $area . 'score';
            $value = trim((string)($data[$field] ?? ''));
            $maxpoints = (float)($metrics->areas[$area]['maxpoints'] ?? 0.0);

            if ($value === '' || !is_numeric($value)) {
                $errors[$field] = get_string('errreviewscoreinvalid', 'mod_aidiscussion');
                continue;
            }

            $number = (float)$value;
            if ($number < 0.0 || $number > $maxpoints) {
                $errors[$field] = get_string(
                    'errreviewscoreoutofrange',
                    'mod_aidiscussion',
                    \aidiscussion_format_score_value($maxpoints)
                );
            }
        }

        return $errors;
    }
}
