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

    /** @var \stdClass The attempt record */
    public $data;

    /** @var question_manager $question_manager $the question manager for the class */
    protected $question_manager;

    /** @var \question_usage_by_activity $quba the question usage by activity for this attempt */
    protected $quba;

    /** @var int $qnum The question number count when rendering questions */
    protected $qnum;

    /** @var bool $lastquestion Signifies if this is the last question
     *  Is used during quiz callbacks to help with instructor control
     */
    public $lastquestion;

    /** @var \context_module $context The context for this attempt */
    protected $context;

    /** @var string $response summary HTML fragment of the response summary for the current question */
    public $responsesummary;

    /** @var  array $slotsbyquestionid array of slots keyed by the questionid that they match to */
    protected $slotsbyquestionid;

    /**
     * Construct the class. If data is passed in we set it, otherwise initialize empty class
     *
     * @param question_manager $question_manager
     * @param \stdClass
     * @param \context_module $context
     */
    public function __construct($question_manager, $data = null, $context = null)
    {
        $this->question_manager = $question_manager;
        $this->context = $context;

        if (empty($data)) {

            // Create new attempt
            $this->data = new \stdClass();

            // Create a new quba since we're creating a new attempt
            $this->quba = \question_engine::make_questions_usage_by_activity('mod_jazzquiz', $this->question_manager->jazzquiz->context);
            $this->quba->set_preferred_behaviour('immediatefeedback');

            $attempt_layout = $this->question_manager->add_questions_to_quba($this->quba);

            // Add the attempt layout to this instance
            $this->data->qubalayout = implode(',', $attempt_layout);

        } else {
            // Load it up in this class instance
            $this->data = $data;
            $this->quba = \question_engine::load_questions_usage_by_activity($this->data->questionengid);
        }
    }

    /**
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
     * Returns the class instance of the quba
     *
     * @return \question_usage_by_activity
     */
    public function get_quba()
    {
        return $this->quba;
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
        $question_number = $this->get_question_number();
        $this->add_question_number();
        return $this->quba->render_question($slot, $display_options, $question_number);
    }

    /**
     * @param int $total_tries The total tries
     *
     * @return int The number of tries left
     */
    public function check_tries_left($total_tries)
    {
        if (empty($this->data->responded_count)) {
            $this->data->responded_count = 0;
        }
        $left = $total_tries - $this->data->responded_count;
        return $left;
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
     * Returns an integer representing the question number
     *
     * @return int
     */
    public function get_question_number()
    {
        // TODO: Why is this returning a string? The annotation says it should return an integer...
        if (is_null($this->qnum)) {
            $this->qnum = 1;
            return (string)1;
        }
        return (string)$this->qnum;
    }

    /**
     * Adds 1 to the current qnum, effectively going to the next question
     */
    protected function add_question_number()
    {
        $this->qnum++;
    }

    /**
     * Returns quba layout as an array (the question slots)
     * @return int[]
     */
    public function getSlots()
    {
        return explode(',', $this->data->qubalayout);
    }

    /**
     * Gets the jazzquiz question class object for the slot
     *
     * @param int $asked_slot
     * @return jazzquiz_question|false
     */
    public function get_question_by_slot($asked_slot)
    {
        // Build if not available
        if (empty($this->slotsbyquestionid) || !is_array($this->slotsbyquestionid)) {
            // Build an array of slots keyed by the question id they match to
            $slots_by_question_id = [];
            foreach ($this->getSlots() as $slot) {
                $question_id = $this->quba->get_question($slot)->id;
                $slots_by_question_id[$question_id] = $slot;
            }
            $this->slotsbyquestionid = $slots_by_question_id;
        }
        $question_id = array_search($asked_slot, $this->slotsbyquestionid);
        if (empty($question_id)) {
            return false;
        }
        foreach ($this->get_questions() as $question) {
            if ($question->question->id == $question_id) {
                return $question;
            }
        }
        return false;
    }

    /**
     * Gets the JazzQuiz questions for this attempt
     * @return jazzquiz_question[]
     */
    public function get_questions()
    {
        return $this->question_manager->get_questions();
    }

    /**
     * @param int $slot
     * @return array (array of sequence check name, and then the value
     */
    public function get_sequence_check($slot)
    {
        $attempt = $this->quba->get_question_attempt($slot);
        return [
            $attempt->get_control_field_name('sequencecheck'),
            $attempt->get_sequence_check_count()
        ];
    }

    /**
     * Initialize the head contributions from the question engine
     * @return string
     */
    public function get_html_head_contributions()
    {
        // Get the slots ids from the quba layout
        $slots = explode(',', $this->data->qubalayout);

        // Next load the slot head html and initialize question engine js
        $result = '';
        foreach ($slots as $slot) {
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
            // Update existing record
            try {
                $DB->update_record('jazzquiz_attempts', $this->data);
            } catch (\Exception $e) {
                error_log($e->getMessage());
                return false; // return false on failure
            }
        } else {
            // Insert new record
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
     *
     * @return bool
     */
    public function save_question()
    {
        global $DB;

        $time_now = time();
        $transaction = $DB->start_delegated_transaction();
        if ($this->data->userid < 0) {
            $this->process_anonymous_response($time_now);
        } else {
            $this->quba->process_all_actions($time_now);
        }
        $this->data->timemodified = time();
        $this->data->responded = 1;

        if (empty($this->data->responded_count)) {
            $this->data->responded_count = 0;
        }
        $this->data->responded_count = $this->data->responded_count + 1;

        $this->save();
        $transaction->allow_commit();
        return true;
    }

    protected function process_anonymous_response($time_now)
    {
        foreach ($this->get_slots_in_request() as $slot) {
            if (!$this->quba->validate_sequence_number($slot)) {
                continue;
            }
            $submitted_data = $this->quba->extract_responses($slot);
            //$this->quba->process_action($slot, $submitted_data, $timestamp);
            $qa = $this->quba->get_question_attempt($slot);
            $qa->process_action($submitted_data, $time_now, $this->data->userid);
            $this->quba->get_observer()->notify_attempt_modified($qa);
        }
        $this->quba->update_question_flags();
    }

    /**
     * COPY FROM QUBA IN ORDER TO RUN ANONYMOUS RESPONSES
     *
     *
     * Get the list of slot numbers that should be processed as part of processing
     * the current request.
     * @param array $post_data optional, only intended for testing. Use this data
     * instead of the data from $_POST.
     * @return array of slot numbers.
     */
    protected function get_slots_in_request($post_data = null)
    {
        // Note: we must not use "question_attempt::get_submitted_var()" because there is no attempt instance!!!
        if (is_null($post_data)) {
            $slots = optional_param('slots', null, PARAM_SEQUENCE);
        } else if (array_key_exists('slots', $post_data)) {
            $slots = clean_param($post_data['slots'], PARAM_SEQUENCE);
        } else {
            $slots = null;
        }
        if (is_null($slots)) {
            $slots = $this->quba->get_slots();
        } else if (!$slots) {
            $slots = [];
        } else {
            $slots = explode(',', $slots);
        }
        return $slots;
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
     * Specify whether this is the last question or not.
     *
     * @param bool $is_last_question
     */
    public function set_last_question($is_last_question = false)
    {
        $this->lastquestion = $is_last_question;
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
