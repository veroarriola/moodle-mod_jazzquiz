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

namespace mod_jazzquiz\controllers;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/editlib.php');

/**
 * edit controller class to act as a controller for the edit page
 *
 * @package     mod_jazzquiz
 * @author      John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright   2014 University of Wisconsin - Madison
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit extends base
{
    /** @var string $action The specified action to take. */
    protected $action;

    /** @var object $context The specific context for this activity. */
    protected $context;

    /** @var \question_edit_contexts $contexts Contains parent contexts of JazzQuiz. */
    protected $contexts;

    /** @var  \mod_jazzquiz\output\edit_renderer $renderer . */
    protected $renderer;

    /**
     * Sets up the edit page
     *
     * @param string $base_url the base url of the
     */
    public function setup_page($base_url)
    {
        global $PAGE;

        $this->load($base_url);
        $this->action = optional_param('action', 'listquestions', PARAM_ALPHA);

        // Inconsistency in question_edit_setup.
        $_GET['cmid'] = $_GET['id'];

        list(
            $this->pageurl,
            $this->contexts,
            $course_module_id,
            $this->cm,
            $this->quiz,
            $this->pagevars) = question_edit_setup('editq', '/mod/jazzquiz/edit.php', true);

        $PAGE->set_url($this->pageurl);
        $this->pagevars['pageurl'] = $this->pageurl;

        $module_name = get_string('modulename', 'jazzquiz');
        $quiz_name = format_string($this->quiz->name, true);

        $PAGE->set_title(strip_tags($this->course->shortname . ': ' . $module_name . ': ' . $quiz_name));
        $PAGE->set_heading($this->course->fullname);

        $this->jazzquiz = new \mod_jazzquiz\jazzquiz($this->cm, $this->course, $this->quiz, $this->pageurl, 'edit');
        $this->renderer = $this->jazzquiz->renderer;
    }

    /**
     * Handles the action specified
     *
     */
    public function handle_action()
    {
        global $DB;

        // Is there a session open?
        $sessions = $DB->get_records('jazzquiz_sessions', [
            'jazzquizid' => $this->jazzquiz->getRTQ()->id,
            'sessionopen' => '1'
        ]);

        if ($sessions) {
            // Can't edit during a session.
            $this->renderer->print_header();
            $this->renderer->opensession();
            $this->renderer->footer();
            return;
        }

        // We know no session is open, and we also know this is an instructor.
        // Before we modify anything, we have to remove the improvised questions.
        $improviser = new \mod_jazzquiz\improviser();
        $improviser->remove_improvised_questions_from_quiz($this->jazzquiz->getRTQ()->id);

        // Let's edit
        switch ($this->action) {

            case 'dragdrop':
                $jsonlib = new \mod_jazzquiz\utils\jsonlib();
                $question_order = optional_param('questionorder', '', PARAM_RAW);
                if ($question_order === '') {
                    $jsonlib->send_error('invalid request');
                }
                $question_order = explode(',', $question_order);
                $success = $this->jazzquiz->question_manager->set_full_order($question_order);
                if ($success) {
                    $jsonlib->send_response();
                } else {
                    $jsonlib->send_error('unable to re-sort questions');
                }
                break;

            case 'moveup':
            case 'movedown':
                $question_id = required_param('questionid', PARAM_INT);
                $direction = substr($this->action, 4);
                if ($this->jazzquiz->question_manager->move_question($direction, $question_id)) {
                    $type = 'success';
                    $message = get_string('successfully_moved_question', 'jazzquiz');
                } else {
                    $type = 'error';
                    $message = get_string('failed_to_move_question', 'jazzquiz');
                }
                $this->renderer->setMessage($type, $message);
                $this->renderer->print_header();
                $this->list_questions();
                $this->renderer->footer();
                break;

            case 'addquestion':
                $question_id = required_param('questionid', PARAM_INT);
                $this->jazzquiz->question_manager->add_question($question_id);
                break;

            case 'editquestion':
                $question_id = required_param('rtqquestionid', PARAM_INT);
                $this->jazzquiz->question_manager->edit_question($question_id);
                break;

            case 'deletequestion':
                $question_id = required_param('questionid', PARAM_INT);
                if ($this->jazzquiz->question_manager->delete_question($question_id)) {
                    $type = 'success';
                    $message = get_string('successfully_deleted_question', 'jazzquiz');
                } else {
                    $type = 'error';
                    $message = get_string('failed_to_delete_question', 'jazzquiz');
                }
                $this->renderer->setMessage($type, $message);
                $this->renderer->print_header();
                $this->list_questions();
                $this->renderer->footer();
                break;

            case 'listquestions':
                $this->renderer->print_header();
                $this->list_questions();
                $this->renderer->footer();
                break;

            default:
                break;
        }
    }

    /**
     * Returns the RTQ instance
     *
     * @return \mod_jazzquiz\jazzquiz
     */
    public function getRTQ()
    {
        return $this->jazzquiz;
    }

    /**
     * Echos the list of questions using the renderer for jazzquiz.
     *
     */
    protected function list_questions()
    {
        $question_bank_view = $this->get_questionbank_view();
        $questions = $this->jazzquiz->question_manager->get_questions();
        $this->renderer->listquestions($questions, $question_bank_view);
    }

    /**
     * Gets the question bank view based on the options passed in at the page setup.
     *
     * @return string
     */
    protected function get_questionbank_view()
    {
        $questions_per_page = optional_param('qperpage', 10, PARAM_INT);
        $question_page = optional_param('qpage', 0, PARAM_INT);

        // Capture question bank display in buffer to have the renderer render output.
        ob_start();
        $question_bank = new \mod_jazzquiz\jazzquiz_question_bank_view($this->contexts, $this->pageurl, $this->jazzquiz->course, $this->jazzquiz->course_module);
        $question_bank->display('editq', $question_page, $questions_per_page, $this->pagevars['cat'], true, true, true);
        return ob_get_clean();
    }

}

