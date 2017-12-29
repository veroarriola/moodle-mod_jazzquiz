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
 * jazzquiz Attempt wrapper class to encapsulate functions needed to individual
 * attempt records
 *
 * @package     mod_jazzquiz
 * @author      John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright   2014 University of Wisconsin - Madison
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jazzquiz_attempt
{
    /** Constants for the status of the attempt */
    const NOTSTARTED = 0;
    const INPROGRESS = 10;
    const ABANDONED = 20;
    const FINISHED = 30;

    /** @var \stdClass */
    public $data;

    /** @var jazzquiz */
    public $jazzquiz;

    /** @var \question_usage_by_activity $quba the question usage by activity for this attempt */
    public $quba;

    /** @var \context_module $context The context for this attempt */
    protected $context;

    /**
     * Construct the class. If data is passed in we set it, otherwise initialize empty class
     *
     * @param jazzquiz $jazzquiz
     * @param \stdClass $data
     * @param \context_module $context
     */
    public function __construct($jazzquiz, $data = null, $context = null)
    {
        $this->jazzquiz = $jazzquiz;
        $this->context = $context;
        if (empty($data)) {
            // Create new attempt
            $this->data = new \stdClass();
            // Create a new quba since we're creating a new attempt
            $this->quba = \question_engine::make_questions_usage_by_activity('mod_jazzquiz', $this->jazzquiz->context);
            $this->quba->set_preferred_behaviour('immediatefeedback');
        } else {
            // Load it up in this class instance
            $this->data = $data;
            $this->quba = \question_engine::load_questions_usage_by_activity($this->data->questionengid);
        }
    }

    /**
     * @param jazzquiz_session $session
     * @return bool false if invalid question id
     */
    public function create_missing_attempts($session)
    {
        foreach ($session->questions as $slot => $question) {
            if ($this->quba->next_slot_number() > $slot) {
                continue;
            }
            $question_definition = reset(question_load_questions([$question->questionid]));
            if (!$question_definition) {
                return false;
            }
            $question = \question_bank::make_question($question_definition);
            $slot = $this->quba->add_question($question);
            $this->quba->start_question($slot);
            $this->data->responded = 0;
            $this->data->responded_count = 0;
            $this->save();
        }
        return true;
    }

    /**
     * Fetches user from database and returns the full name.
     * @return string
     */
    public function get_user_full_name()
    {
        global $DB;
        $user = $DB->get_record('user', [ 'id' => $this->data->userid ]);
        return fullname($user);
    }

    /**
     * Returns a string representation of the "number" status that is actually stored
     * @return string
     * @throws \Exception throws exception upon an undefined status
     */
    public function get_status()
    {
        switch ($this->data->status) {
            case self::NOTSTARTED:
                return 'notstarted';
            case self::INPROGRESS:
                return 'inprogress';
            case self::ABANDONED:
                return 'abandoned';
            case self::FINISHED:
                return 'finished';
            default:
                throw new \Exception('undefined status for attempt');
                break;
        }
    }

    /**
     * Set the status of the attempt and then save it
     * @param string $status
     * @return bool
     */
    public function set_status($status)
    {
        switch ($status) {
            case 'notstarted':
                $this->data->status = self::NOTSTARTED;
                break;
            case 'inprogress':
                $this->data->status = self::INPROGRESS;
                break;
            case 'abandoned':
                $this->data->status = self::ABANDONED;
                break;
            case 'finished':
                $this->data->status = self::FINISHED;
                break;
            default:
                return false;
        }
        return $this->save();
    }

    /**
     * Render the question specified by slot
     *
     * @param int $slot
     * @param bool $review Whether or not we're reviewing the attempt
     * @param string|\stdClass $review_options Can be string for overall actions like "edit" or an object of review options
     * @return string the HTML fragment for the question
     */
    public function render_question($slot, $review = false, $review_options = '')
    {
        $display_options = $this->get_display_options($review, $review_options);
        return $this->quba->render_question($slot, $display_options, $slot);
    }

    /**
     * Sets up the display options for the question
     * @param bool $review
     * @param string $review_options
     * @return \question_display_options
     */
    protected function get_display_options($review = false, $review_options = '')
    {
        $options = new \question_display_options();
        $options->flags = \question_display_options::HIDDEN;
        $options->context = $this->context;
        $options->marks = \question_display_options::HIDDEN;

        if ($review) {

            // Default display options for review
            $options->readonly = true;
            $options->hide_all_feedback();

            // Special case for "edit" review options value
            if ($review_options === 'edit') {
                $options->correctness = \question_display_options::VISIBLE;
                $options->marks = \question_display_options::MARK_AND_MAX;
                $options->feedback = \question_display_options::VISIBLE;
                $options->numpartscorrect = \question_display_options::VISIBLE;
                $options->manualcomment = \question_display_options::EDITABLE;
                $options->generalfeedback = \question_display_options::VISIBLE;
                $options->rightanswer = \question_display_options::VISIBLE;
                $options->history = \question_display_options::VISIBLE;
            } else if ($review_options instanceof \stdClass) {
                foreach (jazzquiz::$review_fields as $field => $not_used) {
                    if ($review_options->$field == 1) {
                        if ($field == 'specificfeedback') {
                            $field = 'feedback';
                        }
                        if ($field == 'marks') {
                            $options->$field = \question_display_options::MARK_AND_MAX;
                        } else {
                            $options->$field = \question_display_options::VISIBLE;
                        }
                    }
                }
            }
        } else {
            // Default options for running quiz
            $options->rightanswer = \question_display_options::HIDDEN;
            $options->numpartscorrect = \question_display_options::HIDDEN;
            $options->manualcomment = \question_display_options::HIDDEN;
            $options->manualcommentlink = \question_display_options::HIDDEN;
        }
        return $options;
    }

    /**
     * Initialize the head contributions from the question engine
     * @return string
     */
    public function get_html_head_contributions()
    {
        // Next load the slot head html and initialize question engine js
        $result = '';
        foreach ($this->quba->get_slots() as $slot) {
            $result .= $this->quba->render_question_head_html($slot);
        }
        $result .= \question_engine::initialise_js();
        return $result;
    }

    /**
     * saves the current attempt class
     *
     * @return bool
     */
    public function save()
    {
        global $DB;

        // Save the question usage by activity object
        \question_engine::save_questions_usage_by_activity($this->quba);

        // Add the quba id as the questionengid
        // This is here because for new usages there is no id until we save it
        $this->data->questionengid = $this->quba->get_id();
        $this->data->timemodified = time();

        if (isset($this->data->id)) {
            try {
                $DB->update_record('jazzquiz_attempts', $this->data);
            } catch (\Exception $e) {
                error_log($e->getMessage());
                return false;
            }
        } else {
            try {
                $this->data->id = $DB->insert_record('jazzquiz_attempts', $this->data);
            } catch (\Exception $e) {
                return false;
            }
        }
        return true;
    }

    /**
     * Saves a question attempt from the jazzquiz question
     */
    public function save_question()
    {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $this->quba->process_all_actions();
        $this->data->timemodified = time();
        $this->data->responded = 1;
        if (empty($this->data->responded_count)) {
            $this->data->responded_count = 0;
        }
        $this->data->responded_count++;
        $this->save();
        $transaction->allow_commit();
    }

    /**
     * Gets the feedback for the specified question slot
     *
     * If no slot is defined, we attempt to get that from the slots param passed
     * back from the form submission
     *
     * @param int $slot The slot for which we want to get feedback
     * @return string HTML fragment of the feedback
     */
    public function get_question_feedback($slot = -1)
    {
        global $PAGE;
        if ($slot === -1) {
            // Attempt to get it from the slots param sent back from a question processing
            $slots = required_param('slots', PARAM_ALPHANUMEXT);
            $slots = explode(',', $slots);
            $slot = $slots[0]; // always just get the first thing from explode
        }
        $question_definition = $this->quba->get_question($slot);
        $question_renderer = $question_definition->get_renderer($PAGE);
        $display_options = $this->get_display_options();
        return $question_renderer->feedback($this->quba->get_question_attempt($slot), $display_options);
    }

    /**
     * @param $slot
     * @return \stdClass[]
     */
    private function get_steps($slot)
    {
        global $DB;

        // Fetch all steps from the database
        $attempt = $this->quba->get_question_attempt($slot);
        $steps = $DB->get_records('question_attempt_steps', [
            'questionattemptid' => $attempt->get_database_id()
        ], 'sequencenumber desc');

        // Let's filter the steps
        $result = [];
        foreach ($steps as $step) {
            switch ($step->state) {
                case 'gaveup':
                    // The attempt is irrelevant, since it was never completed.
                    return [];
                case 'gradedright':
                    // We don't want the correct answer, which is saved in this step.
                    break;
                default:
                    // This is most likely an input step.
                    $result[] = $step;
                    break;
            }
        }

        // Return the filtered steps
        return $result;
    }

    /**
     * @param int $step_id
     * @return \stdClass[]
     */
    private function get_step_data($step_id)
    {
        global $DB;
        return $DB->get_records('question_attempt_step_data', [
            'attemptstepid' => $step_id
        ], 'id desc');
    }

    /**
     * @param int $slot
     * @return string[]
     */
    private function get_response_data_multichoice($slot)
    {
        global $DB;

        // Find steps
        $steps = $this->get_steps($slot);
        if (!$steps) {
            return [];
        }

        // Go through all the steps to find the needed data
        $order = [];
        $chosen_answers = [];
        foreach ($steps as $step) {

            // Find step data
            $all_data = $this->get_step_data($step->id);
            if (!$all_data) {
                continue;
            }

            $choices_found = count($chosen_answers) > 0;

            // Keep in mind we're looping backwards.
            // Therefore, the last answer is prioritised.
            foreach ($all_data as $data) {
                if ($data->name === '_order') {
                    if (!$order) {
                        $order = explode(',', $data->value);
                    }
                } else if ($data->name === 'answer') {
                    if (!$choices_found) {
                        $chosen_answers[] = $data->value;
                    }
                } else if (substr($data->name, 0, 6) === 'choice') {
                    if (!$choices_found && $data->value == 1) {
                        $chosen_answers[] = substr($data->name, 6);
                    }
                }
            }
        }

        // Find the answer strings
        $responses = [];
        foreach ($chosen_answers as $chosen_answer) {
            if (isset($order[$chosen_answer])) {
                $option = $DB->get_record('question_answers', [
                    'id' => $order[$chosen_answer]
                ]);
                if ($option) {
                    $responses[] = $option->answer;
                }
            }
        }

        return $responses;
    }

    /**
     * @param int $slot
     * @return string
     */
    private function get_response_data_true_or_false($slot)
    {
        // Find steps
        $steps = $this->get_steps($slot);
        if (!$steps) {
            return '';
        }
        $step = reset($steps);

        // Find data
        $data = $this->get_step_data($step->id);
        if (!$data) {
            return '';
        }
        $data = array_shift($data);

        // Return response
        if ($data->value == 1) {
            return 'True';
        }
        return 'False';
    }

    /**
     * @param int $slot
     * @return string
     */
    private function get_response_data_stack($slot)
    {
        // Find steps
        $steps = $this->get_steps($slot);
        if (!$steps) {
            return '';
        }
        $step = reset($steps);

        // Find data
        $data = $this->get_step_data($step->id);
        if (!$data) {
            return '';
        }

        // STACK saves two rows for some reason, and it seems impossible to tell apart the answers in a general way.
        if (count($data) > 1) {
            $data_1 = array_shift($data);
            $data_2 = array_shift($data);
            $data = $data_1;
            if (substr($data_1->name, -4, 4) === '_val') {
                $data = $data_2;
            }
        } else {
            $data = array_shift($data);
        }

        return $data->value;
    }

    /**
     * @param int $slot
     * @return string
     */
    private function get_response_data_general($slot)
    {
        // Find step
        $steps = $this->get_steps($slot);
        if (!$steps) {
            return '';
        }
        $step = reset($steps);

        // Find data
        $data = $this->get_step_data($step->id);
        if (!$data) {
            return '';
        }
        $data = reset($data);

        // Return response
        return $data->value;
    }

    /**
     * Returns response data as an array
     * @param int $slot
     * @return string[]
     */
    public function get_response_data($slot)
    {
        $responses = [];
        $question_type = $this->quba->get_question_attempt($slot)->get_question()->get_type_name();
        switch ($question_type) {
            case 'multichoice':
                $responses = $this->get_response_data_multichoice($slot);
                break;
            case 'truefalse':
                $responses[] = $this->get_response_data_true_or_false($slot);
                break;
            case 'stack':
                $responses[] = $this->get_response_data_stack($slot);
                break;
            default:
                $responses[] = $this->get_response_data_general($slot);
                break;
        }
        return $responses;
    }

    /**
     * Returns whether current user has responded
     * @param int $slot
     * @return bool
     */
    public function has_responded($slot)
    {
        $steps = $this->get_steps($slot);
        if (!$steps) {
            return false;
        }
        foreach ($steps as $step) {
            if ($step->state === 'gradedright') {
                return true;
            }
            if ($step->state === 'gaveup') {
                return false;
            }
        }
        // There is no "gaveup" step, which means it might be under a different state.
        return true;
    }

    /**
     * Closes the attempt
     *
     * @param jazzquiz $rtq
     *
     * @return bool Weather or not it was successful
     */
    public function close_attempt($rtq)
    {
        $this->quba->finish_all_questions(time());
        $this->data->status = self::FINISHED;
        $this->data->timefinish = time();
        $this->save();
        $params = [
            'objectid' => $this->data->id,
            'context' => $rtq->context,
            'relateduserid' => $this->data->userid
        ];
        $event = event\attempt_ended::create($params);
        $event->add_record_snapshot('jazzquiz_attempts', $this->data);
        $event->trigger();
        return true;
    }

}
