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

/**
 * Form used to preview rubric and heuristic grading against sample responses.
 *
 * @package   mod_aidiscussion
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response_tester_form extends \moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('static', 'testerintro', '', get_string('responsetesterpagedesc', 'mod_aidiscussion'));

        $mform->addElement(
            'textarea',
            'sampleinitialresponse',
            get_string('sampleinitialresponse', 'mod_aidiscussion'),
            ['rows' => 8, 'cols' => 80]
        );
        $mform->setType('sampleinitialresponse', PARAM_RAW_TRIMMED);

        $mform->addElement(
            'textarea',
            'sampleairesponses',
            get_string('sampleairesponses', 'mod_aidiscussion'),
            ['rows' => 8, 'cols' => 80]
        );
        $mform->setType('sampleairesponses', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('sampleairesponses', 'sampleairesponses', 'mod_aidiscussion');

        $mform->addElement(
            'textarea',
            'samplepeerresponses',
            get_string('samplepeerresponses', 'mod_aidiscussion'),
            ['rows' => 8, 'cols' => 80]
        );
        $mform->setType('samplepeerresponses', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('samplepeerresponses', 'samplepeerresponses', 'mod_aidiscussion');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('submit', 'previewsubmit', get_string('previewgrade', 'mod_aidiscussion'));
    }
}
