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

namespace mod_jazzquiz;

defined('MOODLE_INTERNAL') || die();

/**
 * @package     mod_jazzquiz
 * @author      Sebastian S. Gundersen <sebastsg@stud.ntnu.no>
 * @copyright   2014 University of Wisconsin - Madison
 * @copyright   2018 NTNU
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jazzquiz
{
    /**
     * @var array $review fields Static review fields to add as options
     */
    public static $review_fields = [
        'attempt'          => [ 'theattempt', 'jazzquiz' ],
        'correctness'      => [ 'whethercorrect', 'question' ],
        'marks'            => [ 'marks', 'jazzquiz' ],
        'specificfeedback' => [ 'specificfeedback', 'question' ],
        'generalfeedback'  => [ 'generalfeedback', 'question' ],
        'rightanswer'      => [ 'rightanswer', 'question' ],
        'manualcomment'    => [ 'manualcomment', 'jazzquiz' ]
    ];

    /** @var \stdClass $course_module */
    public $course_module;

    /** @var \stdClass $course */
    public $course;

    /** @var \context_module $context */
    public $context;

    /** @var \plugin_renderer_base|output\edit_renderer $renderer */
    public $renderer;

    /** @var \stdClass $data The jazzquiz database table row */
    public $data;

    /** @var bool $is_instructor */
    protected $is_instructor;

    /** @var jazzquiz_question[] */
    public $questions;

    /**
     * @param int $course_module_id The course module ID
     * @param string $renderer_subtype Renderer sub-type to load if requested
     */
    public function __construct($course_module_id, $renderer_subtype = null)
    {
        global $PAGE, $DB;

        $this->course_module = get_coursemodule_from_id('jazzquiz', $course_module_id, 0, false, MUST_EXIST);

        // TODO: Should login requirement be moved over to caller?
        require_login($this->course_module->course, false, $this->course_module);

        $this->context = \context_module::instance($course_module_id);
        $PAGE->set_context($this->context);
        $this->renderer = $PAGE->get_renderer('mod_jazzquiz', $renderer_subtype);

        $this->course = $DB->get_record('course', [ 'id' => $this->course_module->course ], '*', MUST_EXIST);
        $this->data = $DB->get_record('jazzquiz', [ 'id' => $this->course_module->instance ], '*', MUST_EXIST);
        $this->renderer->set_jazzquiz($this);
        $this->refresh_questions();
    }

    /**
     * Saves the JazzQuiz instance to the database
     * @return bool
     */
    public function save()
    {
        global $DB;
        return $DB->update_record('jazzquiz', $this->data);
    }

    /**
     * Handles adding a question action from the question bank.
     *
     * Displays a form initially to ask how long they'd like the question to be set up for, and then after
     * valid input saves the question to the quiz at the last position
     *
     * @param int $question_id The question bank's question id
     */
    public function add_question($question_id)
    {
        global $DB;

        $question = new \stdClass();
        $question->jazzquizid = $this->data->id;
        $question->questionid = $question_id;
        $question->notime = false;
        $question->questiontime = $this->data->defaultquestiontime;
        $question->slot = count($this->questions) + 1;
        $DB->insert_record('jazzquiz_questions', $question);
        $this->refresh_questions();
    }

    /**
     * Apply a sorted array of jazzquiz_question IDs to the quiz.
     * Questions that are missing from the array will also be removed from the quiz.
     * Duplicate values will silently be removed.
     *
     * @param int[] $order
     */
    public function set_question_order($order)
    {
        global $DB;
        $order = array_unique($order);
        $questions = $DB->get_records('jazzquiz_questions', ['jazzquizid' => $this->data->id], 'slot');
        foreach ($questions as $question) {
            $slot = array_search($question->id, $order);
            if ($slot === false) {
                $DB->delete_records('jazzquiz_questions', ['id' => $question->id]);
                continue;
            }
            $question->slot = $slot + 1;
            $DB->update_record('jazzquiz_questions', $question);
        }
        $this->refresh_questions();
    }

    /**
     * @return int[] of jazzquiz_question id
     */
    public function get_question_order()
    {
        $order = [];
        foreach ($this->questions as $question) {
            $order[] = $question->data->id;
        }
        return $order;
    }

    /**
     * Edit a JazzQuiz question
     *
     * @param int $question_id the JazzQuiz question id
     */
    public function edit_question($question_id)
    {
        global $DB;
        $url = new \moodle_url('/mod/jazzquiz/edit.php', ['id' => $this->course_module->id]);
        $action_url = clone($url);
        $action_url->param('action', 'editquestion');
        $action_url->param('questionid', $question_id);

        $jazzquiz_question = $DB->get_record('jazzquiz_questions', ['id' => $question_id], '*', MUST_EXIST);
        $question = $DB->get_record('question', ['id' => $jazzquiz_question->questionid], '*', MUST_EXIST);

        $mform = new forms\edit\add_question_form($action_url, [
            'jazzquiz' => $this,
            'questionname' => $question->name,
            'edit' => true
        ]);

        // Form handling
        if ($mform->is_cancelled()) {
            // Redirect back to list questions page
            $url->remove_params('action');
            redirect($url, null, 0);
        } else if ($data = $mform->get_data()) {
            $question = new \stdClass();
            $question->id = $jazzquiz_question->id;
            $question->jazzquizid = $this->data->id;
            $question->questionid = $jazzquiz_question->questionid;
            $question->notime = $data->no_time;
            $question->questiontime = $data->question_time;
            $DB->update_record('jazzquiz_questions', $question);
            // Ensure there is no action or question_id in the base url
            $url->remove_params('action', 'questionid');
            redirect($url, null, 0);
        } else {
            // Display the form
            $mform->set_data([
                'question_time' => $jazzquiz_question->questiontime,
                'no_time' => $jazzquiz_question->notime
            ]);
            $this->renderer->print_header();
            $mform->display();
            $this->renderer->footer();
        }
    }

    /**
     * Loads the quiz questions from the database, ordered by slot.
     */
    public function refresh_questions()
    {
        global $DB;
        $this->questions = [];
        $questions = $DB->get_records('jazzquiz_questions', ['jazzquizid' => $this->data->id], 'slot');
        foreach ($questions as $question) {
            $this->questions[$question->slot] = new jazzquiz_question($question);
        }
    }

    /**
     * @param int $jazzquiz_question_id
     * @return jazzquiz_question|bool
     */
    public function get_question_by_id($jazzquiz_question_id) {
        foreach ($this->questions as $question) {
            if ($question->data->id == $jazzquiz_question_id) {
                return $question;
            }
        }
        return false;
    }

    /**
     * Wraps require_capability with the context
     * @param string $capability
     */
    public function require_capability($capability)
    {
        // Throws exception on error
        require_capability($capability, $this->context);
    }

    /**
     * Wrapper for the has_capability function to provide the rtq context
     *
     * @param string $capability
     * @param int $user_id
     *
     * @return bool Whether or not the current user has the capability
     */
    public function has_capability($capability, $user_id = 0)
    {
        if ($user_id !== 0) {
            // Pass in userid if there is one
            return has_capability($capability, $this->context, $user_id);
        }

        // Just do standard check with current user
        return has_capability($capability, $this->context);
    }

    /**
     * Quick function for whether or not the current user is the instructor/can control the quiz
     * @return bool
     */
    public function is_instructor()
    {
        if (is_null($this->is_instructor)) {
            $this->is_instructor = $this->has_capability('mod/jazzquiz:control');
        }
        return $this->is_instructor;
    }

    /**
     * Gets and returns a session specified by id
     * @param int $session_id
     * @return jazzquiz_session
     */
    public function get_session($session_id)
    {
        global $DB;
        $session = $DB->get_record('jazzquiz_sessions', [
            'id' => $session_id
        ], '*', MUST_EXIST);
        return new jazzquiz_session($this, $session);
    }

    /**
     * Gets sessions for this jazzquiz
     *
     * @param array $conditions
     * @return jazzquiz_session[]
     */
    public function get_sessions($conditions = [])
    {
        global $DB;
        $conditions = array_merge([ 'jazzquizid' => $this->data->id ], $conditions);
        $session_records = $DB->get_records('jazzquiz_sessions', $conditions);
        $sessions = [];
        foreach ($session_records as $session_record) {
            $sessions[] = new jazzquiz_session($this, $session_record);
        }
        return $sessions;
    }

    /**
     * Gets all sessions for the realtime quiz that are closed
     *
     * @return array
     */
    public function get_closed_sessions()
    {
        return $this->get_sessions([ 'sessionopen' => 0 ]);
    }

}
