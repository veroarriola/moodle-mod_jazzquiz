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
class add_question_form extends \moodleform {

    /**
     * Overriding parent function to account for namespace in the class name
     * so that client validation works
     *
     * @return mixed|string
     */
    protected function get_form_identifier() {

        $class = get_class($this);

        return preg_replace('/[^a-z0-9_]/i', '_', $class);
    }


    /**
     * Adds form fields to the form
     *
     */
    public function definition() {

        $mform = $this->_form;
        $rtq = $this->_customdata['rtq'];
        $defaultTime = $rtq->getRTQ()->defaultquestiontime;

        $mform->addElement('static', 'questionid', get_string('question', 'jazzquiz'), $this->_customdata['questionname']);

        $mform->addElement('advcheckbox', 'notime', get_string('notime', 'jazzquiz'));
        $mform->setType('notime', PARAM_INT);
        $mform->addHelpButton('notime', 'notime', 'jazzquiz');
        $mform->setDefault('notime', 0);

        $mform->addElement('duration', 'indvquestiontime', get_string('indvquestiontime', 'jazzquiz'));
        $mform->disabledIf('indvquestiontime', 'notime', 'checked');
        $mform->setType('indvquestiontime', PARAM_INT);
        $mform->setDefault('indvquestiontime', $defaultTime);
        $mform->addHelpButton('indvquestiontime', 'indvquestiontime', 'jazzquiz');

        $mform->addElement('text', 'numberoftries', get_string('numberoftries', 'jazzquiz'));
        $mform->addRule('numberoftries', get_string('invalid_numberoftries', 'jazzquiz'), 'required', null, 'client');
        $mform->addRule('numberoftries', get_string('invalid_numberoftries', 'jazzquiz'), 'numeric', null, 'client');
        $mform->setType('numberoftries', PARAM_INT);
        $mform->setDefault('numberoftries', 1);
        $mform->addHelpButton('numberoftries', 'numberoftries', 'jazzquiz');

        $mform->addElement('text', 'points', get_string('points', 'jazzquiz'));
        $mform->addRule('points', get_string('invalid_points', 'jazzquiz'), 'required', null, 'client');
        $mform->addRule('points', get_string('invalid_points', 'jazzquiz'), 'numeric', null, 'client');
        $mform->setType('points', PARAM_FLOAT);
        $mform->setDefault('points', number_format($this->_customdata['defaultmark'], 2));
        $mform->addHelpButton('points', 'points', 'jazzquiz');

        $mform->addElement('advcheckbox', 'showhistoryduringquiz', get_string('showhistoryduringquiz', 'jazzquiz'));
        $mform->setType('showhistoryduringquiz', PARAM_INT);
        $mform->addHelpButton('showhistoryduringquiz', 'showhistoryduringquiz', 'jazzquiz');
        $mform->setDefault('showhistoryduringquiz', $this->_customdata['showhistoryduringquiz']);

        if (!empty($this->_customdata['edit'])) {
            $savestring = get_string('savequestion', 'jazzquiz');
        } else {
            $savestring = get_string('addquestion', 'jazzquiz');
        }

        $this->add_action_buttons(true, $savestring);

    }

    /**
     * Validate indv question time as int
     *
     * @param array $data
     * @param array $files
     *
     * @return array $errors
     */
    public function validation($data, $files) {

        $errors = array();

        if (!filter_var($data['indvquestiontime'], FILTER_VALIDATE_INT) && $data['indvquestiontime'] !== 0) {
            $errors['indvquestiontime'] = get_string('invalid_indvquestiontime', 'jazzquiz');
        } else if ($data['indvquestiontime'] < 0) {
            $errors['indvquestiontime'] = get_string('invalid_indvquestiontime', 'jazzquiz');
        }

        if (!filter_var($data['numberoftries'], FILTER_VALIDATE_INT) && $data['numberoftries'] !== 0) {
            $errors['numberoftries'] = get_string('invalid_numberoftries', 'jazzquiz');
        } else if ($data['numberoftries'] < 1) {
            $errors['numberoftries'] = get_string('invalid_numberoftries', 'jazzquiz');
        }

        if (!filter_var($data['points'], FILTER_VALIDATE_FLOAT) && filter_var($data['points'], FILTER_VALIDATE_FLOAT) != 0) {
            $errors['points'] = get_string('invalid_points', 'jazzquiz');
        } else if (filter_var($data['points'], FILTER_VALIDATE_FLOAT) < 0) {
            $errors['points'] = get_string('invalid_points', 'jazzquiz');
        }

        return $errors;
    }

}

