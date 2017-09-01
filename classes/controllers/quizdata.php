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

/**
 * The controller for handling quiz data callbacks from javascript
 *
 * @package     mod_jazzquiz
 * @author      John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright   2014 University of Wisconsin - Madison
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizdata
{

    /** @var \mod_jazzquiz\jazzquiz Realtime quiz class */
    protected $RTQ;

    /** @var \mod_jazzquiz\jazzquiz_session $session The session class for the jazzquiz view */
    protected $session;

    /** @var string $action The specified action to take */
    protected $action;

    /** @var object $context The specific context for this activity */
    protected $context;

    /** @var \moodle_url $pageurl The page url to base other calls on */
    protected $pageurl;

    /** @var array $this ->pagevars An array of page options for the page load */
    protected $pagevars = [];

    /** @var \mod_jazzquiz\utils\jsonlib $jsonlib The jsonlib for returning json */
    protected $jsonlib;

    /**
     * set up the class for the view page
     *
     * @throws \moodle_exception throws exception on error in setting up initial vars when debugging
     */
    public function setup_page()
    {
        global $DB, $PAGE;

        // No page url as this is just a callback.
        $this->pageurl = null;
        $this->jsonlib = new \mod_jazzquiz\utils\jsonlib();

        // Check if this is a jserror, if so, log it and end execution so we're not wasting time.
        $jserror = optional_param('jserror', '', PARAM_ALPHANUMEXT);
        if (!empty($jserror)) {

            // Log the js error on the apache error logs
            error_log($jserror);

            // Set a status and send it saying that we logged the error.
            $this->jsonlib->set('status', 'loggedjserror');
            $this->jsonlib->send_response();
        }

        // Handle exception to avoid displaying errors on a javascript callback.
        try {
            $rtqid = required_param('rtqid', PARAM_INT);
            $sessionid = required_param('sessionid', PARAM_INT);
            $attemptid = required_param('attemptid', PARAM_INT);
            $this->action = required_param('action', PARAM_ALPHANUMEXT);
            $this->pagevars['inquesetion'] = optional_param('inquestion', '', PARAM_ALPHAEXT);

            // only load things asked for, don't assume that we're loading whatever.
            $quiz = $DB->get_record('jazzquiz', array('id' => $rtqid), '*', MUST_EXIST);
            $course = $DB->get_record('course', array('id' => $quiz->course), '*', MUST_EXIST);
            $cm = get_coursemodule_from_instance('jazzquiz', $quiz->id, $course->id, false, MUST_EXIST);
            $session = $DB->get_record('jazzquiz_sessions', array('id' => $sessionid), '*', MUST_EXIST);

            require_login($course->id, false, $cm, false, true);

        } catch (\moodle_exception $e) {
            if (debugging()) { // if debugging throw error as normal.
                throw new $e;
            } else {
                $this->jsonlib->send_error('invalid request');
            }
            exit(); // Stop execution.
        }
        // Check to make sure asked for session is open.
        if ((int)$session->sessionopen !== 1) {
            $this->jsonlib->send_error('invalid session');
        }

        $this->pagevars['pageurl'] = $this->pageurl;
        $this->pagevars['action'] = $this->action;


        $this->RTQ = new \mod_jazzquiz\jazzquiz($cm, $course, $quiz, $this->pageurl, $this->pagevars);

        $this->session = new \mod_jazzquiz\jazzquiz_session($this->RTQ, $this->pageurl, $this->pagevars, $session);

        // Get and validate the attempt.
        $attempt = $this->session->get_user_attempt($attemptid);

        if ($attempt->getStatus() != 'inprogress') {
            $this->jsonlib->send_error('invalidattempt');
        }

        // If the attempt validates, make it the open attempt on the session.
        $this->session->set_open_attempt($attempt);

    }

    /**
     * Start a question and send the response
     *
     */
    private function start_question($question)
    {
        // Get open attempt
        $attempt = $this->session->get_open_attempt();

        // Set status
        $this->session->set_status('running');
        $this->jsonlib->set('status', 'startedquestion');

        // Set general question info
        $this->jsonlib->set('lastquestion', ($attempt->lastquestion ? 'true' : 'false'));
        $this->jsonlib->set('questionid', $question->get_slot());
        $this->jsonlib->set('nextstarttime', $this->session->get_session()->nextstarttime);

        // Set time limit
        $this->jsonlib->set('notime', $question->getNoTime());
        if ($question->getNoTime() == 0) {

            // This question has a time limit
            if ($question->getQuestionTime() == 0) {
                $question_time = $this->RTQ->getRTQ()->defaultquestiontime;
            } else {
                $question_time = $question->getQuestionTime();
            }
            $this->jsonlib->set('questiontime', $question_time);

        } else {

            // No time limit
            $this->jsonlib->set('questiontime', 0);
        }

        // Set delay
        $delay = $this->session->get_session()->nextstarttime - time();
        $this->jsonlib->set('delay', $delay);

        // Send the response
        $this->jsonlib->send_response();
    }

    /**
     * Sends a list of all the questions tagged for use with improvisation.
     *
     */
    private function show_all_improvisation_questions()
    {

        global $DB;

        $quiz_questions = $DB->get_records('jazzquiz_questions', [
            'jazzquizid' => $this->RTQ->getRTQ()->id
        ]);

        if (!$quiz_questions) {
            $this->jsonlib->send_error('no questions');
        } else {

            $questions = [];

            foreach ($quiz_questions as $quiz_question) {

                // Let's get the question data
                $question = $DB->get_record('question', [
                    'id' => $quiz_question->questionid
                ]);

                // Did we find the question?
                if (!$question) {
                    // Only use for debugging.
                    /*$questions[] = [
                        'questionid' => $quiz_question->questionid,
                        'name' => 'This question does not exist.',
                        'slot' => 0
                    ];*/
                    continue;
                }

                // Check if it is an improvisation question
                if (strpos($question->name, '{IMPROV}') === false) {
                    continue;
                }

                // Let's find its question number in the quiz
                $question_order = $this->RTQ->getRTQ()->questionorder;
                $ordered_jazzquiz_question_ids = explode(',', $question_order);
                $slot = 0;
                foreach ($ordered_jazzquiz_question_ids as $id) {
                    $slot++;
                    if ($id == $quiz_question->id) {
                        break;
                    }
                }

                // Add it to the list
                $questions[] = [
                    'questionid' => $question->id,
                    'name' => str_replace('{IMPROV}', '', $question->name),
                    'slot' => $slot
                ];

            }

            // Send the response
            $this->jsonlib->set('status', 'success');
            $this->jsonlib->set('questions', json_encode($questions));
            $this->jsonlib->send_response();

        }
    }

    private function start_goto_question($slot)
    {

        // Are we going to keep or break the flow of the quiz?
        $keep_flow = optional_param('keepflow', '', PARAM_TEXT);

        if (!empty($keep_flow)) {

            // Only one keep_flow at a time. Two improvised questions can be run after eachother.
            if ($this->session->get_session()->nextqnum == 0) {

                // Get last and current slot
                $last_slot = count($this->RTQ->getRTQ()->questionorder);
                $current_slot = intval($this->session->get_session()->currentqnum);

                // Does the next slot exist?
                if ($last_slot >= $current_slot + 1) {

                    // Okay, let's save it
                    $this->session->get_session()->nextqnum = $current_slot + 1;
                    $this->session->save_session();
                }
            }

        }

        // Let's get the question
        $question = $this->session->goto_question($slot);
        if (!$question) {
            $this->jsonlib->send_error('invalid slot ' . $slot);
        }

        // Start the question and send the response
        $this->start_question($question);
    }


    private function start_quiz()
    {
        // Start the quiz
        $this->session->start_quiz();

        // Send response
        $this->jsonlib->set('status', 'startedquiz');
        $this->jsonlib->send_response();
    }

    private function save_question()
    {
        // Check if we're working on the current question for the session
        $current_question = $this->session->get_session()->currentquestion;
        $js_current_question = required_param('questionid', PARAM_INT);
        if ($current_question != $js_current_question) {
            $this->jsonlib->send_error('invalid question');
        }

        // Get the attempt for the question
        $attempt = $this->session->get_open_attempt();

        // Does it belong to this user?
        if ($attempt->userid != $this->session->get_current_userid()) {
            $this->jsonlib->send_error('invalid user');
        }

        // Let's try to save it
        if ($attempt->save_question()) {

            // Only give feedback if specified in session
            if ($this->session->get_session()->showfeedback) {
                $this->jsonlib->set('feedback', $attempt->get_question_feedback());
            } else {
                $this->jsonlib->set('feedback', '');
            }

            // We need to send the updated sequence check for javascript to update.
            // Get the sequence check on the question form. This allows the question to be resubmitted again.
            list($seqname, $seqvalue) = $attempt->get_sequence_check($this->session->get_session()->currentqnum);

            // Send the response
            $this->jsonlib->set('status', 'success');
            $this->jsonlib->set('seqcheckname', $seqname);
            $this->jsonlib->set('seqcheckval', $seqvalue);
            $this->jsonlib->send_response();

        } else {
            $this->jsonlib->send_error('unable to save question');
        }
    }

    private function run_voting()
    {
        if (!isset($_POST['questions'])) {
            $this->jsonlib->send_error('no questions sent');
        }

        // TODO: Not use _POST

        // Decode the questions parameter into an array
        $questions = json_decode(urldecode($_POST['questions']), true);

        // Get question type
        $question_type = '';
        if (isset($_POST['qtype'])) {
            $question_type = $_POST['qtype'];
        }

        if (!$questions) {
            $this->jsonlib->send_error('no questions sent');
        }

        // Initialize the votes
        $vote = new \mod_jazzquiz\jazzquiz_vote($this->session->get_session()->id);
        $slot = $this->session->get_session()->currentqnum;
        $vote->prepare_options($this->RTQ->getRTQ()->id, $question_type, $questions, $slot);

        // Change quiz status
        $this->session->set_status('voting');

        // Send response
        $this->jsonlib->set('status', 'success');
        $this->jsonlib->send_response();

    }

    private function save_vote()
    {
        if (!isset($_POST['answer'])) {
            $this->jsonlib->send_error('no vote');
        }

        // TODO: Use param function instead of _POST - not doing it right now to avoid breaking something

        // Get the id for the attempt that was voted on
        $vote_id = intval($_POST['answer']);

        // Get the user who voted
        $user_id = $this->session->get_current_userid();

        // Save the vote
        $vote = new \mod_jazzquiz\jazzquiz_vote($this->session->get_session()->id);
        $status = $vote->save_vote($vote_id, $user_id);

        // Send response
        $this->jsonlib->set('status', $status);
        $this->jsonlib->send_response();

    }

    private function get_vote_results()
    {
        // Get all the vote results
        $vote = new \mod_jazzquiz\jazzquiz_vote($this->session->get_session()->id, $this->session->get_session()->currentqnum);
        $votes = $vote->get_results();

        // Send the response
        $this->jsonlib->set('answers', json_encode($votes));
        $this->jsonlib->set('status', 'success');
        $this->jsonlib->send_response();
    }

    private function get_not_responded()
    {
        // Get list of users who have yet to respond to the question
        $not_responded_html = $this->session->get_not_responded();

        // Send response
        $this->jsonlib->set('notresponded', $not_responded_html);
        $this->jsonlib->set('status', 'success');
        $this->jsonlib->send_response();
    }

    private function next_question()
    {
        // Are we coming from an improvised question?
        if ($this->session->get_session()->nextqnum != 0) {

            // Yes, we likely are. Let's get that question
            $question = $this->session->goto_question($this->session->get_session()->nextqnum);

            // We should also reset the nextqnum, since we're back in the right quiz flow again.
            $this->session->get_session()->nextqnum = 0;
            $this->session->save_session();

        } else {

            // Doesn't seem that way. Let's just start the next question in the ordered list.
            $question = $this->session->next_question();
        }

        // Start the question
        $this->start_question($question);
    }

    private function repoll_question()
    {
        // Get the question to re-poll
        $question = $this->session->repoll_question();

        // Start the question
        $this->start_question($question);
    }

    private function goto_question()
    {
        // Get the question number to go to
        $slot = optional_param('qnum', '', PARAM_INT);
        if (empty($slot)) {
            $this->jsonlib->send_error('invalid slot');
        }

        // Go to the question
        $this->start_goto_question($slot);
    }

    private function end_question()
    {
        // End the question or vote
        $this->session->set_status('reviewing');

        // Send response
        $this->jsonlib->set('status', 'success');
        $this->jsonlib->send_response();
    }

    private function get_right_response()
    {
        // Get the right answer
        $right_answer = $this->session->get_question_right_response();

        // Send response
        $this->jsonlib->set('rightanswer', $right_answer);
        $this->jsonlib->set('status', 'success');
        $this->jsonlib->send_response();
    }

    private function close_session()
    {
        // End the session
        $this->session->end_session();

        // Save grades
        if (!$this->RTQ->get_grader()->save_all_grades()) {
            $this->jsonlib->send_error('can\'t save grades');
        }

        // Send response
        $this->jsonlib->set('status', 'success');
        $this->jsonlib->send_response();
    }

    private function get_results()
    {
        // Is the question still ongoing? If so, the 'live' parameter should be passed.
        $use_live_filter = isset($_POST['live']);

        // Get the results
        $question_manager = $this->RTQ->get_questionmanager();
        $slot = $this->session->get_session()->currentqnum;
        $question_type = $question_manager->get_questiontype_byqnum($slot);
        $responses = $this->session->get_question_results_list($use_live_filter, $question_type);

        // Check if this has been voted on before
        $vote = new \mod_jazzquiz\jazzquiz_vote($this->session->get_session()->id, $slot);
        $has_votes = count($vote->get_results()) > 0;

        // Send response
        $this->jsonlib->set('has_votes', $has_votes);
        $this->jsonlib->set('qtype', $question_type);
        $this->jsonlib->set('slot', $slot);
        $this->jsonlib->set('responses', $responses);
        $this->jsonlib->set('status', 'success');
        $this->jsonlib->send_response();
    }

    /**
     * Handles instructor requests
     *
     */
    private function handle_instructor_request()
    {
        switch ($this->action) {

            case 'startquiz':
                $this->start_quiz();
                break;

            case 'savequestion':
                $this->save_question();
                break;

            case 'listdummyquestions':
                $this->show_all_improvisation_questions();
                break;

            case 'runvoting':
                $this->run_voting();
                break;

            case 'getvoteresults':
                $this->get_vote_results();
                break;

            case 'getresults':
                $this->get_results();
                break;

            case 'getnotresponded':
                $this->get_not_responded();
                break;

            case 'nextquestion':
                $this->next_question();
                break;

            case 'repollquestion':
                $this->repoll_question();
                break;

            case 'gotoquestion':
                $this->goto_question();
                break;

            case 'endquestion':
                $this->end_question();
                break;

            case 'getrightresponse':
                $this->get_right_response();
                break;

            case 'closesession':
                $this->close_session();
                break;

            default:
                $this->jsonlib->send_error('invalid action');
                break;
        }
    }

    /**
     * Handles student requests
     *
     */
    private function handle_student_request()
    {
        switch ($this->action) {

            case 'savequestion':
                $this->save_question();
                break;

            case 'savevote':
                $this->save_vote();
                break;

            default:
                $this->jsonlib->send_error('invalid action');
                break;
        }
    }

    /**
     * Handles the incoming request
     *
     */
    public function handle_request()
    {
        if ($this->RTQ->is_instructor()) {
            $this->handle_instructor_request();
        } else {
            $this->handle_student_request();
        }
    }

}

