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

use \mod_jazzquiz\forms\edit\add_question_form;

/**
 * Question manager class
 *
 * Provides utility functions to manage questions for a JazzQuiz
 *
 * Basically this class provides an interface to internally map the questions added to a JazzQuiz to
 * questions in the question bank. Calling get_questions() will return an ordered array of question objects
 * from the questions table and not the jazzquiz_questions table. That table is only used internally by this class.
 *
 * @package     mod_jazzquiz
 * @author      John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright   2014 University of Wisconsin - Madison
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_manager
{
    /** @var jazzquiz */
    public $jazzquiz;

    /** @var jazzquiz_question[] */
    public $jazzquiz_questions;

    /** @var \moodle_url */
    protected $base_url;

    /**
     * @param jazzquiz $jazzquiz
     */
    public function __construct($jazzquiz)
    {
        $this->jazzquiz = $jazzquiz;
        $this->base_url = new \moodle_url('/mod/jazzquiz/edit.php', ['id' => $jazzquiz->course_module->id]);
        $this->refresh_questions();
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
        $question->jazzquizid = $this->jazzquiz->data->id;
        $question->questionid = $question_id;
        $question->notime = false;
        $question->questiontime = $this->jazzquiz->data->defaultquestiontime;
        $question->tries = 1;
        $question->showhistoryduringquiz = false;
        $question->slot = count($this->jazzquiz_questions) + 1;
        $DB->insert_record('jazzquiz_questions', $question);
        $this->refresh_questions();

        // Ensure there is no action or questionid in the base url
        $this->base_url->remove_params('action', 'questionid');

        redirect($this->base_url, null, 0);
    }

    /**
     * Edit a JazzQuiz question
     *
     * @param int $question_id the JazzQuiz question id
     */
    public function edit_question($question_id)
    {
        global $DB;

        $action_url = clone($this->base_url);
        $action_url->param('action', 'editquestion');
        $action_url->param('questionid', $question_id);

        $jazzquiz_question = $DB->get_record('jazzquiz_questions', ['id' => $question_id], '*', MUST_EXIST);
        $question = $DB->get_record('question', ['id' => $jazzquiz_question->questionid], '*', MUST_EXIST);

        $mform = new add_question_form($action_url, [
            'jazzquiz' => $this->jazzquiz,
            'questionname' => $question->name,
            'show_history_during_quiz' => $jazzquiz_question->showhistoryduringquiz,
            'edit' => true
        ]);

        // Form handling
        if ($mform->is_cancelled()) {

            // Redirect back to list questions page
            $this->base_url->remove_params('action');
            redirect($this->base_url, null, 0);

        } else if ($data = $mform->get_data()) {

            $question = new \stdClass();
            $question->id = $jazzquiz_question->id;
            $question->jazzquizid = $this->jazzquiz->data->id;
            $question->questionid = $jazzquiz_question->questionid;
            $question->notime = $data->no_time;
            $question->questiontime = $data->question_time;
            $question->tries = $data->number_of_tries;

            $DB->update_record('jazzquiz_questions', $question);

            // Ensure there is no action or question_id in the base url
            $this->base_url->remove_params('action', 'questionid');
            redirect($this->base_url, null, 0);

        } else {

            // Display the form
            $mform->set_data([
                'question_time' => $jazzquiz_question->questiontime,
                'no_time' => $jazzquiz_question->notime,
                'number_of_tries' => $jazzquiz_question->tries
            ]);

            $this->jazzquiz->renderer->print_header();
            $this->jazzquiz->renderer->addquestionform($mform);
            $this->jazzquiz->renderer->footer();
        }
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
        $questions = $DB->get_records('jazzquiz_questions', ['jazzquizid' => $this->jazzquiz->data->id], 'slot');
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
        foreach ($this->jazzquiz_questions as $question) {
            $order[] = $question->data->id;
        }
        return $order;
    }

    /**
     * Get question type for the specified slot
     * @param int $slot
     * @return string|false
     */
    public function get_question_type_by_slot($slot)
    {
        if (count($this->jazzquiz_questions) >= $slot || $slot < 1) {
            return false;
        }
        $question = $this->jazzquiz_questions[$slot - 1];
        return $question->question->qtype;
    }

    /**
     * @param jazzquiz_attempt $attempt
     * @return jazzquiz_question
     */
    public function get_first_question($attempt)
    {
        return $this->get_question_with_slot(1, $attempt);
    }

    /**
     * Gets a jazzquiz_question object with the slot set
     *
     * @param int $slot
     * @param jazzquiz_attempt $attempt The current attempt
     *
     * @return jazzquiz_question
     */
    public function get_question_with_slot($slot, $attempt)
    {
        $quba = $attempt->get_quba();

        // TODO: Fix this
        //$attempt->set_last_question($is_last_question);

        $quba_question = $quba->get_question($slot);

        foreach ($this->jazzquiz_questions as $jazzquiz_question) {
            if ($jazzquiz_question->question->id == $quba_question->id) {
                // Set the slot on the bank question as this is the actual id we're using for question number
                $jazzquiz_question->slot = $slot;
                return $jazzquiz_question;
            }
        }

        // No question
        return null;
    }

    /**
     * add the questions to the question usage
     * This is called by the question_attempt class on construct of a new attempt
     *
     * @param \question_usage_by_activity $quba
     * @return int[] jazzquiz_question id => slot
     */
    public function add_questions_to_quba($quba)
    {
        // We need the question ids of our questions
        $question_ids = [];
        foreach ($this->jazzquiz_questions as $jazzquiz_question) {
            if (!in_array($jazzquiz_question->question->id, $question_ids)) {
                $question_ids[] = $jazzquiz_question->question->id;
            }
        }
        $questions = question_load_questions($question_ids);

        // Loop through the ordered question bank questions and add them to the quba object
        foreach ($this->jazzquiz_questions as $jazzquiz_question) {
            $question_id = $jazzquiz_question->question->id;
            $q = \question_bank::make_question($questions[$question_id]);
            $quba->add_question($q);
        }

        // Start the questions in the quba
        $quba->start_all_questions();
    }

    /**
     * Loads the quiz questions from the database, ordered by slot.
     */
    private function refresh_questions()
    {
        global $DB;
        $this->jazzquiz_questions = [];
        $questions = $DB->get_records('jazzquiz_questions', ['jazzquizid' => $this->jazzquiz->data->id], 'slot');
        foreach ($questions as $question) {
            $this->jazzquiz_questions[] = new jazzquiz_question($question);
        }
    }

}
