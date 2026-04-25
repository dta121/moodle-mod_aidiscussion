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
 * Form used to save calibration benchmark cases for the Response Tester.
 *
 * @package   mod_aidiscussion
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class benchmark_case_form extends \moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;
        $aidiscussion = $this->_customdata['aidiscussion'];
        $rubrics = \aidiscussion_get_effective_rubrics($aidiscussion);

        $mform->addElement('static', 'benchmarkintro', '', get_string('benchmarktesterpagedesc', 'mod_aidiscussion'));

        $mform->addElement('text', 'benchmarkname', get_string('benchmarkname', 'mod_aidiscussion'), ['size' => 64]);
        $mform->setType('benchmarkname', PARAM_TEXT);

        $mform->addElement(
            'textarea',
            'benchmarkdescription',
            get_string('benchmarkdescription', 'mod_aidiscussion'),
            ['rows' => 3, 'cols' => 80]
        );
        $mform->setType('benchmarkdescription', PARAM_RAW_TRIMMED);

        $mform->addElement(
            'textarea',
            'benchmarkinitialresponse',
            get_string('sampleinitialresponse', 'mod_aidiscussion'),
            ['rows' => 8, 'cols' => 80]
        );
        $mform->setType('benchmarkinitialresponse', PARAM_RAW_TRIMMED);

        $mform->addElement(
            'textarea',
            'benchmarkairesponses',
            get_string('sampleairesponses', 'mod_aidiscussion'),
            ['rows' => 8, 'cols' => 80]
        );
        $mform->setType('benchmarkairesponses', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('benchmarkairesponses', 'sampleairesponses', 'mod_aidiscussion');

        $mform->addElement(
            'textarea',
            'benchmarkpeerresponses',
            get_string('samplepeerresponses', 'mod_aidiscussion'),
            ['rows' => 8, 'cols' => 80]
        );
        $mform->setType('benchmarkpeerresponses', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('benchmarkpeerresponses', 'samplepeerresponses', 'mod_aidiscussion');

        foreach (\aidiscussion_get_rubric_area_definitions() as $area => $definition) {
            if (!\aidiscussion_is_rubric_area_enabled($aidiscussion, $area)) {
                continue;
            }

            $field = 'benchmarkexpected_' . $area;
            $criterionnames = array_map(static function ($criterion): string {
                return (string)$criterion->shortname;
            }, $rubrics[$area]->criteria ?? []);
            $mform->addElement(
                'textarea',
                $field,
                get_string('benchmarkexpectedfor', 'mod_aidiscussion', $definition['label']),
                ['rows' => 6, 'cols' => 80]
            );
            $mform->setType($field, PARAM_RAW_TRIMMED);
            $mform->addHelpButton($field, 'benchmarkexpectedformat', 'mod_aidiscussion');
            $mform->addElement(
                'static',
                $field . '_criteria',
                '',
                get_string('benchmarkcriterionlist', 'mod_aidiscussion', implode(', ', $criterionnames))
            );
        }

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'benchmarkcaseid');
        $mform->setType('benchmarkcaseid', PARAM_INT);

        $mform->addElement('submit', 'savebenchmarkcase', get_string('savebenchmarkcase', 'mod_aidiscussion'));
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
        return $errors + \aidiscussion_validate_benchmark_case_form_data($aidiscussion, $data);
    }
}
