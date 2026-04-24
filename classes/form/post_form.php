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
 * Simple post form for AI discussion replies.
 *
 * @package   mod_aidiscussion
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class post_form extends \moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;
        $parentsummary = trim((string)($this->_customdata['parentsummary'] ?? ''));
        $isreply = !empty($this->_customdata['isreply']);

        if ($parentsummary !== '') {
            $mform->addElement('static', 'parentsummary', get_string('respondingto', 'mod_aidiscussion'), $parentsummary);
        }

        $mform->addElement(
            'textarea',
            'content',
            get_string($isreply ? 'replymessage' : 'yourresponse', 'mod_aidiscussion'),
            ['rows' => 12, 'cols' => 80]
        );
        $mform->setType('content', PARAM_RAW_TRIMMED);
        $mform->addRule('content', get_string('required'), 'required', null, 'client');

        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement('hidden', 'parentid');
        $mform->setType('parentid', PARAM_INT);

        $mform->addElement('hidden', 'returnurl');
        $mform->setType('returnurl', PARAM_LOCALURL);

        $submitlabel = get_string($isreply ? 'submitreply' : 'submitresponse', 'mod_aidiscussion');
        $this->add_action_buttons(true, $submitlabel);
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

        if (trim((string)($data['content'] ?? '')) === '') {
            $errors['content'] = get_string('errrequiredcontent', 'mod_aidiscussion');
        }

        return $errors;
    }
}
