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
    protected $ordered_jazzquiz_questions;

    /** @var \moodle_url */
    protected $base_url;

    /**
     * @param jazzquiz $jazzquiz
     */
    public function __construct($jazzquiz)
    {
        $this->jazzquiz = $jazzquiz;
        $this->base_url = new \moodle_url('/mod/jazzquiz/edit.php', [
            'id' => $jazzquiz->course_module->id
        ]);
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

        // Check if question has already been added
        /*if ($this->is_question_already_present($question_id)) {
            $redirect_url = clone($this->base_url);
            $redirect_url->remove_params('action'); // Go back to base edit page
            redirect($redirect_url, get_string('cantaddquestiontwice', 'jazzquiz'));
        }*/

        $question = new \stdClass();
        $question->jazzquizid = $this->jazzquiz->data->id;
        $question->questionid = $question_id;
        $question->notime = false;
        $question->questiontime = $this->jazzquiz->data->defaultquestiontime;
        $question->tries = 1;
        $question->showhistoryduringquiz = false;

        $jazzquiz_question_id = $DB->insert_record('jazzquiz_questions', $question);

        $this->update_questionorder('addquestion', $jazzquiz_question_id);

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
     * Delete a question on the quiz
     *
     * @param int $question_id The RTQ questionid to delete
     *
     * @return bool
     */
    public function delete_question($question_id)
    {
        global $DB;
        try {
            $DB->delete_records('jazzquiz_questions', [
                'id' => $question_id
            ]);
            $this->update_questionorder('deletequestion', $question_id);
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * Moves a question on the question order for this quiz
     *
     * @param string $direction 'up'||'down'
     * @param int $question_id JazzQuiz question id
     *
     * @return bool
     */
    public function move_question($direction, $question_id)
    {
        if ($direction !== 'up' && $direction !== 'down') {
            return false;
        }
        return $this->update_questionorder('movequestion' . $direction, $question_id);
    }

    /**
     * Public API function for setting the full order of the questions on the jazzquiz
     *
     * Please note that full order must be an array with no specialized keys as only array values are taken
     *
     * @param array $full_order
     * @return bool
     */
    public function set_full_order($full_order = [])
    {
        if (!is_array($full_order)) {
            return false;
        }
        $full_order = array_values($full_order);
        return $this->update_questionorder('replaceorder', null, $full_order);
    }

    /**
     * Returns the questions in the specified question order
     * @return jazzquiz_question[]
     */
    public function get_questions()
    {
        return $this->ordered_jazzquiz_questions;
    }

    /**
     * Gets the question type for the specified question number
     * @param int $slot
     * @return string
     */
    public function get_questiontype_byqnum($slot)
    {
        // Get the actual key for the bank question
        $bank_keys = array_keys($this->ordered_jazzquiz_questions);
        $desired_key = $bank_keys[$slot - 1];
        $jazzquiz_question = $this->ordered_jazzquiz_questions[$desired_key];
        return $jazzquiz_question->question->qtype;
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

        foreach ($this->ordered_jazzquiz_questions as $jazzquiz_question) {
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
        foreach ($this->ordered_jazzquiz_questions as $jazzquiz_question) {
            if (!in_array($jazzquiz_question->question->id, $question_ids)) {
                $question_ids[] = $jazzquiz_question->question->id;
            }
        }
        $questions = question_load_questions($question_ids);

        // Loop through the ordered question bank questions and add them to the quba object
        $attempt_layout = [];
        foreach ($this->ordered_jazzquiz_questions as $jazzquiz_question) {
            $question_id = $jazzquiz_question->question->id;
            $q = \question_bank::make_question($questions[$question_id]);
            $attempt_layout[$jazzquiz_question->data->id] = $quba->add_question($q);
        }

        // Start the questions in the quba
        $quba->start_all_questions();

        /**
         * Return the attempt layout which is a set of ids that are the slot ids from the question engine usage by activity instance
         * these are what are used during an actual attempt rather than the question_id themselves, since the question engine will handle
         * the translation
         */
        return $attempt_layout;
    }

    /**
     * Gets the question order from the rtq object
     *
     * @return string
     */
    protected function get_question_order()
    {
        return $this->jazzquiz->data->questionorder;
    }

    /**
     * Updates question order on RTQ object and then persists to the database
     *
     * @param string
     * @return bool
     */
    protected function set_question_order($question_order)
    {
        $this->jazzquiz->data->questionorder = $question_order;
        return $this->jazzquiz->save();
    }

    /**
     * Updates the question order for the question manager
     *
     * @param string $action
     * @param int $question_id the realtime quiz question id, NOT the question engine question id
     * @param array $full_order An array of question objects to sort as is.
     *                         This is mainly used for the dragdrop callback on the edit page.  If the full order is not specified
     *                         with all questions currently on the quiz, the case will return false
     *
     * @return bool true/false if it was successful
     */
    protected function update_questionorder($action, $question_id, $full_order = [])
    {
        switch ($action) {
            case 'addquestion':
                $question_order = $this->get_question_order();
                if (empty($question_order)) {
                    $question_order = $question_id;
                } else {
                    $question_order .= ',' . $question_id;
                }
                $this->set_question_order($question_order);
                $this->refresh_questions();
                return true;

            case 'deletequestion':
                $question_order = $this->get_question_order();
                $question_order = explode(',', $question_order);
                foreach ($question_order as $index => $current_question_order) {
                    if ($current_question_order == $question_id) {
                        unset($question_order[$index]);
                        break;
                    }
                }
                $new_question_order = implode(',', $question_order);
                $this->set_question_order($new_question_order);
                $this->refresh_questions();
                return true;

            case 'movequestionup':
                $question_order = $this->get_question_order();
                $question_order = explode(',', $question_order);
                foreach ($question_order as $index => $current_question_order) {
                    if ($current_question_order == $question_id) {
                        if ($index == 0) {
                            // Can't move first question up
                            return false;
                        }
                        // If IDs match replace the previous index with the current one
                        // and make the previous index qid the current index
                        $previous_question_order = $question_order[$index - 1];
                        $question_order[$index - 1] = $question_id;
                        $question_order[$index] = $previous_question_order;
                        break;
                    }
                }
                $new_question_order = implode(',', $question_order);
                $this->set_question_order($new_question_order);
                $this->refresh_questions();
                return true;

            case 'movequestiondown':
                $question_order = $this->get_question_order();
                $question_order = explode(',', $question_order);
                $question_order_count = count($question_order);
                foreach ($question_order as $index => $current_question_order) {
                    if ($current_question_order == $question_id) {
                        if ($index == $question_order_count - 1) {
                            // Can't move last question down
                            return false;
                        }
                        // If ids match replace the next index with the current one
                        // and make the next index qid the current index
                        $next_question_order = $question_order[$index + 1];
                        $question_order[$index + 1] = $question_id;
                        $question_order[$index] = $next_question_order;
                        break;
                    }
                }
                $new_question_order = implode(',', $question_order);
                $this->set_question_order($new_question_order);
                $this->refresh_questions();
                return true;

            case 'replaceorder':
                $question_order = $this->get_question_order();
                $question_order = explode(',', $question_order);
                // If we don't have the same number of questions return error
                if (count($full_order) !== count($question_order)) {
                    return false;
                }
                // Next validate that the questions sent all match to a question in the current order
                $all_match = true;
                foreach ($question_order as $current_question_order) {
                    if (!in_array($current_question_order, $full_order)) {
                        $all_match = false;
                    }
                }
                if ($all_match) {
                    $new_question_order = implode(',', $full_order);
                    $this->set_question_order($new_question_order);
                    $this->refresh_questions();
                    return true;
                }
                return false;

            default:
                return false;
        }
    }

    /**
     * Check whether the question id has already been added
     * @param int $question_id
     * @return bool
     */
    protected function is_question_already_present($question_id)
    {
        // Loop through the db rtq questions and see if we find a match
        foreach ($this->ordered_jazzquiz_questions as $jazzquiz_question) {
            if ($jazzquiz_question->data->questionid == $question_id) {
                return true;
            }
        }
        return false;
    }

    /**
     * Refreshes question information from the DB
     *
     * This is the function that should be called so that questions are loaded
     * in the correct order
     */
    private function refresh_questions()
    {
        global $DB;

        $jazzquiz_questions = $DB->get_records('jazzquiz_questions', ['jazzquizid' => $this->jazzquiz->data->id]);

        // Start by ordering the question ids into an array
        $question_order = $this->jazzquiz->data->questionorder;

        // Generate empty array for ordered questions for no question order
        if (empty($question_order)) {
            $this->ordered_jazzquiz_questions = [];
            return;
        } else {
            // Otherwise explode it and continue on
            $question_order = explode(',', $question_order);
        }

        // Using the question order saved in rtq object, get the qbank question ids from the rtq questions
        $ordered_question_ids = [];
        foreach ($question_order as $order_index) {
            // Store the jazzquiz_question id as the key so that it can be used later
            // when adding question time to question bank question object
            $ordered_question_ids[$order_index] = $jazzquiz_questions[$order_index]->questionid;
        }

        // Get bank questions based on the question ids from the RTQ questions table
        list($sql, $params) = $DB->get_in_or_equal($ordered_question_ids);
        $query = "SELECT * FROM {question} WHERE id $sql";
        $questions = $DB->get_records_sql($query, $params);

        // Now order the qbank questions based on the order that we got above
        $ordered_jazzquiz_questions = [];
        foreach ($ordered_question_ids as $jazzquiz_question_id => $question_id) {
            if (empty($questions[$question_id])) {
                continue;
            }
            $jazzquiz_question = new jazzquiz_question($jazzquiz_questions[$jazzquiz_question_id], $questions[$question_id]);
            $ordered_jazzquiz_questions[$jazzquiz_question_id] = $jazzquiz_question;
        }
        $this->ordered_jazzquiz_questions = $ordered_jazzquiz_questions;
    }

}
