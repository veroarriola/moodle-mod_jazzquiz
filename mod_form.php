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
 * The main configuration form
 *
 * @package   mod_jazzquiz
 * @author    John Hoopes <moodle@madisoncreativeweb.com>
 * @author    Davo Smith
 * @copyright 2014 University of Wisconsin - Madison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_jazzquiz_mod_form extends moodleform_mod
{

    public function __construct($current, $section, $cm, $course)
    {
        parent::__construct($current, $section, $cm, $course);
    }

    function definition()
    {
        $mform =& $this->_form;

        /// Adding the "general" fieldset, where all the common settings are showed
        $mform->addElement('header', 'general', get_string('general', 'form'));

        /// Adding the standard "name" field
        $mform->addElement('text', 'name', get_string('name'), [
            'size' => '64'
        ]);

        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements(get_string('description'));

        $mform->addElement('duration', 'defaultquestiontime', get_string('default_question_time', 'jazzquiz'));
        $mform->setDefault('defaultquestiontime', 180);
        $mform->setType('defaultquestiontime', PARAM_INT);
        $mform->addHelpButton('defaultquestiontime', 'default_question_time', 'jazzquiz');

        $mform->addElement('duration', 'waitforquestiontime', get_string('wait_for_question_time', 'jazzquiz'));
        $mform->setDefault('waitforquestiontime', 0);
        $mform->setType('waitforquestiontime', PARAM_INT);
        $mform->addHelpButton('waitforquestiontime', 'wait_for_question_time', 'jazzquiz');

        // Check if there are any sessions
        $change_groups = true;
        if (!empty($this->_instance)) {
            global $DB;
            $sessions = $DB->get_records('jazzquiz_sessions', [
                'jazzquizid' => $this->_instance
            ]);
            if (!empty($sessions)) {
                $change_groups = false;
            }
        }

        // Group settings
        $mform->addElement('header', 'groupsettings', get_string('group_work_settings', 'jazzquiz'));

        $course_groupings = $this->get_groupings();

        if ($change_groups == false || empty($course_groupings)) {
            $mform->addElement('static', 'nogroups', get_string('no_change_groups_label', 'jazzquiz'), get_string('no_change_groups', 'jazzquiz'));
        }

        $mform->addElement('advcheckbox', 'workedingroups', get_string('worked_in_groups', 'jazzquiz'));
        $mform->addHelpButton('workedingroups', 'worked_in_groups', 'jazzquiz');
        $mform->setDefault('workedingroups', 0);

        $mform->addElement('select', 'grouping', get_string('grouping', 'jazzquiz'), $course_groupings);
        $mform->disabledIf('grouping', 'workedingroups');
        $mform->setType('grouping', PARAM_INT);
        $mform->addHelpButton('grouping', 'grouping', 'jazzquiz');

        $mform->addElement('advcheckbox', 'groupattendance', get_string('group_attendance', 'jazzquiz'));
        $mform->addHelpButton('groupattendance', 'group_attendance', 'jazzquiz');
        $mform->disabledIf('groupattendance', 'workedingroups');
        $mform->setDefault('groupattendance', 0);

        if ($change_groups == false || empty($course_groupings)) {
            $mform->freeze('workedingroups');
            $mform->freeze('grouping');
            $mform->freeze('groupattendance');
        }

        // Add standard elements, common to all modules
        $this->standard_coursemodule_elements();

        // Add standard buttons, common to all modules
        $this->add_action_buttons();
    }

    /**
     * Gets a grouping array for display
     *
     * @return array
     */
    protected function get_groupings()
    {
        // Get the courseid from the module's course context
        if (get_class($this->context) == 'context_course') {
            // If the context defined for the form is a context course just get its id
            $courseid = $this->context->instanceid;
        } else {
            $cmcontext = context_module::instance($this->_cm->id);
            $coursecontext = $cmcontext->get_course_context(false);
            $courseid = $coursecontext->instanceid;
        }

        $groupings = groups_get_all_groupings($courseid);

        // Create an array with just the grouping id and name
        $retgroupings = [];
        $retgroupings[''] = get_string('none');
        foreach ($groupings as $grouping) {
            $retgroupings[$grouping->id] = $grouping->name;
        }

        return $retgroupings;
    }

}
