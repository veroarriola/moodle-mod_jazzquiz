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
 * @package   mod_jazzquiz
 * @author    John Hoopes <moodle@madisoncreativeweb.com>
 * @author    Davo Smith
 * @copyright 2014 University of Wisconsin - Madison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Define all the restore steps that will be used by the restore_jazzquiz_activity_task
 */

/**
 * Structure step to restore one jazzquiz activity
 */
class restore_jazzquiz_activity_structure_step extends restore_questions_activity_structure_step {

    /** @var \stdClass $currentrtqattempt Store the current attempt until the inform_new_usage_id is called */
    private $currentrtqattempt;

    /** @var string $oldquestionorder The old question order to save until the after execution step */
    private $oldquestionorder;


    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('jazzquiz', '/activity/jazzquiz');
        $paths[] = new restore_path_element('jazzquiz_question', '/activity/jazzquiz/questions/question');

        if ($userinfo) {
            $paths[] = new restore_path_element('jazzquiz_grade', '/activity/jazzquiz/grades/grade');
            $paths[] = new restore_path_element('jazzquiz_session', '/activity/jazzquiz/sessions/session');

            $quizattempt = new restore_path_element('jazzquiz_attempt', '/activity/jazzquiz/sessions/session/attempts/attempt');
            $paths[] = $quizattempt;

            // Add states and question usages for the attempts.
            $this->add_question_usages($quizattempt, $paths);

            $paths[] = new restore_path_element('jazzquiz_groupattendance',
                '/activity/jazzquiz/sessions/session/attempts/attempt/groupattendances/groupattendance');
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    protected function process_jazzquiz($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->grouping = $this->get_mappingid('grouping', $data->grouping);
        $this->oldquestionorder = $data->questionorder;
        $data->questionorder = null; // set to null,  This will be updated in after_execute

        $newitemid = $DB->insert_record('jazzquiz', $data);
        $this->apply_activity_instance($newitemid);
    }

    protected function process_jazzquiz_question($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->jazzquizid = $this->get_new_parentid('jazzquiz');
        if ($questionid = $this->get_mappingid('question', $data->questionid)) {
            $data->questionid = $questionid;
        } else {
            return;
        }

        $newitemid = $DB->insert_record('jazzquiz_questions', $data);

        $this->set_mapping('jazzquiz_question', $oldid, $newitemid);
    }

    protected function process_jazzquiz_grade($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->jazzquizid = $this->get_new_parentid('jazzquiz');
        $data->grade = $data->gradeval;
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('jazzquiz_grades', $data);
        $this->set_mapping('jazzquiz_grade', $oldid, $newitemid);
    }

    protected function process_jazzquiz_session($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->jazzquizid = $this->get_new_parentid('jazzquiz');
        $data->created = $this->apply_date_offset($data->created);

        $newitemid = $DB->insert_record('jazzquiz_sessions', $data);
        $this->set_mapping('jazzquiz_session', $oldid, $newitemid);
    }

    protected function process_jazzquiz_attempt($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->sessionid = $this->get_new_parentid('jazzquiz_session');

        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->forgroupid = $this->get_mappingid('group', $data->forgroupid);

        $data->timestart = $this->apply_date_offset($data->timestart);
        $data->timefinish = $this->apply_date_offset($data->timefinish);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $this->currentrtqattempt = clone($data);
    }


    protected function inform_new_usage_id($newusageid) {
        global $DB;

        $data = $this->currentrtqattempt;

        $oldid = $data->id;
        $data->questionengid = $newusageid;

        $newitemid = $DB->insert_record('jazzquiz_attempts', $data);

        $this->set_mapping('jazzquiz_attempt', $oldid, $newitemid, false);
    }

    protected function process_jazzquiz_groupattendance($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->jazzquizid = $this->task->get_activityid();
        $data->sessionid = $this->get_mappingid('jazzquiz_session', $data->sessionid);
        $data->attemptid = $this->get_new_parentid('jazzquiz_attempt');
        $data->groupid = $this->get_mappingid('group', $data->groupid);
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('jazzquiz_groupattendance', $data);

        $this->set_mapping('jazzquiz_groupattendance', $oldid, $newitemid);
    }

    protected function after_execute() {
        global $DB;

        $this->recode_jazzquiz_questionorder();

        // Add intro files
        $this->add_related_files('mod_jazzquiz', 'intro', null);
    }

    /**
     * Recodes the questionorder field for the jazzquiz object
     * base on what we stored in the class var earlier
     *
     * Also deletes unused question records in case a random question record didn't match up with the question order
     *
     */
    protected function recode_jazzquiz_questionorder() {
        global $DB;

        $oldqorder = explode(',', $this->oldquestionorder);
        $newqorder = array();

        foreach ($oldqorder as $oldq) {

            $newqid = $this->get_mappingid('jazzquiz_question', $oldq);
            if ($newqid) {
                $newqorder[] = $newqid;
            }
        }

        $newqorder = implode(',', $newqorder);
        $DB->set_field('jazzquiz', 'questionorder', $newqorder, array('id' => $this->get_task()->get_activityid()));
    }
}
