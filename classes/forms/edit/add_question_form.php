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

namespace mod_jazzquiz\forms\edit;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Moodle form for confirming question add and get the time for the question
 * to appear on the page
 *
 * @package     mod_jazzquiz
 * @author      John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright   2014 University of Wisconsin - Madison
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_question_form extends \moodleform
{
    /**
     * Overriding parent function to account for namespace in the class name
     * so that client validation works
     *
     * @return mixed|string
     */
    protected function get_form_identifier()
    {
        $class = get_class($this);
        return preg_replace('/[^a-z0-9_]/i', '_', $class);
    }

    /**
     * Adds form fields to the form
     *
     */
    public function definition()
    {
        $mform = $this->_form;
        $jazzquiz = $this->_customdata['jazzquiz'];
        $defaultTime = $jazzquiz->data->defaultquestiontime;

        $mform->addElement('static', 'questionid', get_string('question', 'jazzquiz'), $this->_customdata['questionname']);

        $mform->addElement('advcheckbox', 'no_time', get_string('no_time_limit', 'jazzquiz'));
        $mform->setType('no_time', PARAM_INT);
        $mform->addHelpButton('no_time', 'no_time', 'jazzquiz');
        $mform->setDefault('no_time', 0);

        $mform->addElement('duration', 'question_time', get_string('question_time', 'jazzquiz'));
        $mform->disabledIf('question_time', 'no_time', 'checked');
        $mform->setType('question_time', PARAM_INT);
        $mform->setDefault('question_time', $defaultTime);
        $mform->addHelpButton('question_time', 'question_time', 'jazzquiz');

        $mform->addElement('text', 'number_of_tries', get_string('number_of_tries', 'jazzquiz'));
        $mform->addRule('number_of_tries', get_string('invalid_number_of_tries', 'jazzquiz'), 'required', null, 'client');
        $mform->addRule('number_of_tries', get_string('invalid_number_of_tries', 'jazzquiz'), 'numeric', null, 'client');
        $mform->setType('number_of_tries', PARAM_INT);
        $mform->setDefault('number_of_tries', 1);
        $mform->addHelpButton('number_of_tries', 'number_of_tries', 'jazzquiz');

        $mform->addElement('advcheckbox', 'show_history_during_quiz', get_string('show_history_during_quiz', 'jazzquiz'));
        $mform->setType('show_history_during_quiz', PARAM_INT);
        $mform->addHelpButton('show_history_during_quiz', 'show_history_during_quiz', 'jazzquiz');
        $mform->setDefault('show_history_during_quiz', $this->_customdata['show_history_during_quiz']);

        if (!empty($this->_customdata['edit'])) {
            $save_string = get_string('save_question', 'jazzquiz');
        } else {
            $save_string = get_string('add_question', 'jazzquiz');
        }

        $this->add_action_buttons(true, $save_string);
    }

    /**
     * Validate indv question time as int
     *
     * @param array $data
     * @param array $files
     *
     * @return array $errors
     */
    public function validation($data, $files)
    {
        $errors = [];
        if (!filter_var($data['question_time'], FILTER_VALIDATE_INT) && $data['question_time'] !== 0) {
            $errors['question_time'] = get_string('invalid_question_time', 'jazzquiz');
        } else if ($data['question_time'] < 0) {
            $errors['question_time'] = get_string('invalid_question_time', 'jazzquiz');
        }
        if (!filter_var($data['number_of_tries'], FILTER_VALIDATE_INT) && $data['number_of_tries'] !== 0) {
            $errors['number_of_tries'] = get_string('invalid_number_of_tries', 'jazzquiz');
        } else if ($data['number_of_tries'] < 1) {
            $errors['number_of_tries'] = get_string('invalid_number_of_tries', 'jazzquiz');
        }
        return $errors;
    }

}
