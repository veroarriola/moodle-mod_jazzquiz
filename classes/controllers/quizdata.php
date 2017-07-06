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

namespace mod_activequiz\controllers;

defined('MOODLE_INTERNAL') || die();

/**
 * The controller for handling quiz data callbacks from javascript
 *
 * @package     mod_activequiz
 * @author      John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright   2014 University of Wisconsin - Madison
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quizdata {

    /** @var \mod_activequiz\activequiz Realtime quiz class */
    protected $RTQ;

    /** @var \mod_activequiz\activequiz_session $session The session class for the activequiz view */
    protected $session;

    /** @var string $action The specified action to take */
    protected $action;

    /** @var object $context The specific context for this activity */
    protected $context;

    /** @var \moodle_url $pageurl The page url to base other calls on */
    protected $pageurl;

    /** @var array $this ->pagevars An array of page options for the page load */
    protected $pagevars = array();

    /** @var \mod_activequiz\utils\jsonlib $jsonlib The jsonlib for returning json */
    protected $jsonlib;

    /**
     * set up the class for the view page
     *
     * @throws \moodle_exception throws exception on error in setting up initial vars when debugging
     */
    public function setup_page() {
        global $DB, $PAGE;

        // no page url as this is just a callback.
        $this->pageurl = null;
        $this->jsonlib = new \mod_activequiz\utils\jsonlib();


        // first check if this is a jserror, if so, log it and end execution so we're not wasting time.
        $jserror = optional_param('jserror', '', PARAM_ALPHANUMEXT);
        if (!empty($jserror)) {
            // log the js error on the apache error logs
            error_log($jserror);

            // set a status and send it saying that we logged the error.
            $this->jsonlib->set('status', 'loggedjserror');
            $this->jsonlib->send_response();
        }

        // use try/catch in order to catch errors and not display them on a javascript callback.
        try {
            $rtqid = required_param('rtqid', PARAM_INT);
            $sessionid = required_param('sessionid', PARAM_INT);
            $attemptid = required_param('attemptid', PARAM_INT);
            $this->action = required_param('action', PARAM_ALPHANUMEXT);
            $this->pagevars['inquesetion'] = optional_param('inquestion', '', PARAM_ALPHAEXT);

            // only load things asked for, don't assume that we're loading whatever.
            $quiz = $DB->get_record('activequiz', array('id' => $rtqid), '*', MUST_EXIST);
            $course = $DB->get_record('course', array('id' => $quiz->course), '*', MUST_EXIST);
            $cm = get_coursemodule_from_instance('activequiz', $quiz->id, $course->id, false, MUST_EXIST);
            $session = $DB->get_record('activequiz_sessions', array('id' => $sessionid), '*', MUST_EXIST);

            require_login($course->id, false, $cm, false, true);
        } catch(\moodle_exception $e) {
            if (debugging()) { // if debugging throw error as normal.
                throw new $e;
            } else {
                $this->jsonlib->send_error('invalid request');
            }
            exit(); // stop execution.
        }
        // check to make sure asked for session is open.
        if ((int)$session->sessionopen !== 1) {
            $this->jsonlib->send_error('invalidsession');
        }

        $this->pagevars['pageurl'] = $this->pageurl;
        $this->pagevars['action'] = $this->action;


        $this->RTQ = new \mod_activequiz\activequiz($cm, $course, $quiz, $this->pageurl, $this->pagevars);

        $this->session = new \mod_activequiz\activequiz_session($this->RTQ, $this->pageurl, $this->pagevars, $session);

        // get and validate the attempt.
        $attempt = $this->session->get_user_attempt($attemptid);

        if ($attempt->getStatus() != 'inprogress') {
            $this->jsonlib->send_error('invalidattempt');
        }
        // if the attempt validates, make it the open attempt on the session.
        $this->session->set_open_attempt($attempt);


    }

    /**
     * Handles the incoming request
     *
     */
    public function handle_request() {

        global $DB; // TODO: Put the voting logic in a new class

        switch ($this->action) {
            case 'startquiz':

                // only allow instructors to perform this action
                if ($this->RTQ->is_instructor()) {
                    $firstquestion = $this->session->start_quiz();

                    $this->jsonlib->set('status', 'startedquiz');
                    $this->jsonlib->send_response();

                    /*$this->jsonlib->set('status', 'startedquiz');
                    $this->jsonlib->set('questionid', $firstquestion->get_slot());
                    $this->jsonlib->set('nextstarttime', $this->session->get_session()->nextstarttime);

                    $this->jsonlib->set('notime', $firstquestion->getNoTime());
                    if ($firstquestion->getNoTime() == 0) {
                        // this question has a time limit

                        if ($firstquestion->getQuestionTime() == 0) {
                            $questiontime = $this->RTQ->getRTQ()->defaultquestiontime;
                        } else {
                            $questiontime = $firstquestion->getQuestionTime();
                        }
                        $this->jsonlib->set('questiontime', $questiontime);
                    } else {
                        $this->jsonlib->set('questiontime', 0);
                    }
                    $delay = $this->session->get_session()->nextstarttime - time();
                    $this->jsonlib->set('delay', $delay);

                    $qattempt = $this->session->get_open_attempt();
                    $this->jsonlib->set('lastquestion', ($qattempt->lastquestion ? 'true' : 'false'));
                    $this->jsonlib->send_response();*/

                } else {
                    $this->jsonlib->send_error('invalidaction');
                }

                break;
            case 'savequestion':

                // check if we're working on the current question for the session
                $currentquestion = $this->session->get_session()->currentquestion;
                $jscurrentquestion = required_param('questionid', PARAM_INT);
                if ($currentquestion != $jscurrentquestion) {
                    $this->jsonlib->send_error('invalid question');
                }

                // if we pass attempt to save the question
                $qattempt = $this->session->get_open_attempt();

                // make sure the attempt belongs to the current user
                if ($qattempt->userid != $this->session->get_current_userid()) {
                    $this->jsonlib->send_error('invalid user');
                }

                if ($qattempt->save_question()) {

                    $this->jsonlib->set('status', 'success');

                    // Only give feedback if specified in session
                    if ($this->session->get_session()->showfeedback) {
                        $this->jsonlib->set('feedback', $qattempt->get_question_feedback());
                    } else {
                        $this->jsonlib->set('feedback', '');
                    }

                    // next we need to send back the updated sequence check for javascript to update
                    // the sequence check on the question form.  this allows the question to be resubmitted again
                    list($seqname, $seqvalue) = $qattempt->get_sequence_check($this->session->get_session()->currentqnum);

                    $this->jsonlib->set('seqcheckname', $seqname);
                    $this->jsonlib->set('seqcheckval', $seqvalue);
                    $this->jsonlib->send_response();
                } else {
                    $this->jsonlib->send_error('unable to save question');
                }

                break;
            case 'listdummyquestions':

                if ($this->RTQ->is_instructor()) {

                    $quiz_questions = $DB->get_records('activequiz_questions', [
                        'activequizid' => $this->RTQ->getRTQ()->id
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
                                $questions[] = [
                                    'questionid' => $quiz_question->questionid,
                                    'name' => 'This question does not exist.',
                                    'slot' => 0
                                ];
                                continue;
                            }

                            // Check if it is an improvisation question
                            if (strpos($question->name, '{IMPROV}') === false) {
                                continue;
                            }

                            // Let's find its question number in the quiz
                            $question_order = $this->RTQ->getRTQ()->questionorder;
                            $ordered_activequiz_question_ids = explode(',', $question_order);
                            $slot = 0;
                            foreach ($ordered_activequiz_question_ids as $id) {
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
                } else {
                    $this->jsonlib->send_error('invalidaction');
                }

                break;
            case 'runvoting':

                if ($this->RTQ->is_instructor() && isset($_POST['questions'])) {

                    $questions = json_decode(urldecode($_POST['questions']), true);

                    if (!$questions) {
                        $this->jsonlib->send_error('no questions sent');
                    } else {

                        // Delete previous voting options for this session
                        $DB->delete_records('activequiz_votes', [
                            'sessionid' => $this->session->get_session()->id
                        ]);

                        // Add to database
                        foreach ($questions as $question) {
                            $vote = new \stdClass();
                            $vote->activequizid = $this->RTQ->getRTQ()->id;
                            $vote->sessionid = $this->session->get_session()->id;
                            $vote->attempt = $question['text'];
                            $vote->initialcount = $question['count'];
                            $vote->finalcount = 0;
                            $vote->userlist = '';
                            $DB->insert_record('activequiz_votes', $vote);
                        }

                        // Change quiz status
                        $this->session->set_status('voting');

                        // Send response
                        $this->jsonlib->set('status', 'success');
                        $this->jsonlib->send_response();
                    }

                } else {
                    $this->jsonlib->send_error('invalidaction');
                }
                break;

            case 'savevote':
                if ($this->RTQ->is_instructor() || !isset($_POST['answer'])) {
                    $this->jsonlib->send_error('invalidaction');
                } else {
                    $answer = intval($_POST['answer']);
                    $exists = $DB->record_exists('activequiz_votes', ['id' => $answer]);
                    if ($exists) {
                        $row = $DB->get_record('activequiz_votes', ['id' => $answer]);
                        if ($row) {
                            $user_id = $this->session->get_current_userid();

                            // Check if this user has already voted on any of the options already
                            $already_voted = false;
                            $all_votes = $DB->get_records('activequiz_votes', [
                                'sessionid' => $this->session->get_session()->id
                            ]);
                            if ($all_votes) {
                                foreach ($all_votes as $vote) {
                                    $users_voted = explode(',', $vote->userlist);
                                    if ($users_voted) {
                                        foreach ($users_voted as $user_voted) {
                                            if ($user_voted == $user_id) {
                                                $already_voted = true;
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                            if ($already_voted) {
                                // Trying to cheat the results, eh?
                                $this->jsonlib->set('status', 'alreadyvoted');
                            } else {
                                // Seems like an honest vote. Let's add it!
                                $row->finalcount++;
                                if ($row->userlist != '') {
                                    $row->userlist .= ',';
                                }
                                $row->userlist .= $user_id;
                                $DB->update_record('activequiz_votes', $row);
                                $this->jsonlib->set('status', 'success');
                            }
                        } else {
                            $this->jsonlib->set('status', 'error');
                        }
                    } else {
                        $this->jsonlib->set('status', 'error');
                    }
                    $this->jsonlib->send_response();
                }
                break;

            case 'getvoteresults':
                if ($this->RTQ->is_instructor()) {
                    $answers = $DB->get_records('activequiz_votes', ['sessionid' => $this->session->get_session()->id]);
                    $this->jsonlib->set('answers', json_encode($answers));
                    $this->jsonlib->set('status', 'success');
                    $this->jsonlib->send_response();
                } else {
                    $this->jsonlib->send_error('invalidaction');
                }
                break;

            case 'getresults':

                // only allow instructors to perform this action
                if ($this->RTQ->is_instructor()) {

                    $this->session->set_status('reviewing');
                    // get the current question results
                    $responses = $this->session->get_question_results_list(false);

                    $this->jsonlib->set('responses', $responses);
                    $this->jsonlib->set('status', 'success');
                    $this->jsonlib->set('qtype', $this->RTQ->get_questionmanager()->get_questiontype_byqnum($this->session->get_session()->currentqnum));
                    $this->jsonlib->send_response();

                } else {
                    $this->jsonlib->send_error('invalidaction');
                }

                break;
            case 'getcurrentresults': // case to get the results of the question currently going
                if ($this->RTQ->is_instructor()) {
                    $responses = $this->session->get_question_results_list(true);
                    $this->jsonlib->set('responses', $responses);
                    $this->jsonlib->set('status', 'success');
                    $this->jsonlib->set('qtype', $this->RTQ->get_questionmanager()->get_questiontype_byqnum($this->session->get_session()->currentqnum));
                    $this->jsonlib->send_response();
                } else {
                    $this->jsonlib->send_error('invalidaction');
                }
                break;
            case 'getnotresponded':

                // only allow instructors to perform this action
                if ($this->RTQ->is_instructor()) {

                    $notrespondedHTML = $this->session->get_not_responded();

                    $this->jsonlib->set('notresponded', $notrespondedHTML);
                    $this->jsonlib->set('status', 'success');
                    $this->jsonlib->send_response();

                } else {
                    $this->jsonlib->send_error('invalidaction');
                }

                break;
            case 'nextquestion':

                // only allow instructors to perform this action
                if ($this->RTQ->is_instructor()) {

                    if ($this->session->get_session()->nextqnum != 0) {
                        $nextquestion = $this->session->goto_question($this->session->get_session()->nextqnum);
                        $this->session->get_session()->nextqnum = 0;
                        $this->session->save_session();
                    } else {
                        $nextquestion = $this->session->next_question();
                    }

                    $this->session->set_status('running');
                    $this->jsonlib->set('status', 'startedquestion');
                    $qattempt = $this->session->get_open_attempt();
                    $this->jsonlib->set('lastquestion', ($qattempt->lastquestion ? 'true' : 'false'));
                    $this->jsonlib->set('questionid', $nextquestion->get_slot());
                    $this->jsonlib->set('nextstarttime', $this->session->get_session()->nextstarttime);

                    $this->jsonlib->set('notime', $nextquestion->getNoTime());
                    if ($nextquestion->getNoTime() == 0) {
                        // this question has a time limit

                        if ($nextquestion->getQuestionTime() == 0) {
                            $questiontime = $this->RTQ->getRTQ()->defaultquestiontime;
                        } else {
                            $questiontime = $nextquestion->getQuestionTime();
                        }
                        $this->jsonlib->set('questiontime', $questiontime);
                    } else {
                        $this->jsonlib->set('questiontime', 0);
                    }
                    $delay = $this->session->get_session()->nextstarttime - time();
                    $this->jsonlib->set('delay', $delay);

                    $this->jsonlib->send_response();

                } else {
                    $this->jsonlib->send_error('invalidaction');
                }

                break;
            case 'repollquestion':

                if ($this->RTQ->is_instructor()) {

                    $repollquestion = $this->session->repoll_question();
                    $this->session->set_status('running');
                    $this->jsonlib->set('status', 'startedquestion');
                    $qattempt = $this->session->get_open_attempt();
                    $this->jsonlib->set('lastquestion', ($qattempt->lastquestion ? 'true' : 'false'));
                    $this->jsonlib->set('questionid', $repollquestion->get_slot());
                    $this->jsonlib->set('nextstarttime', $this->session->get_session()->nextstarttime);

                    $this->jsonlib->set('notime', $repollquestion->getNoTime());
                    if ($repollquestion->getNoTime() == 0) {
                        // this question has a time limit

                        if ($repollquestion->getQuestionTime() == 0) {
                            $questiontime = $this->RTQ->getRTQ()->defaultquestiontime;
                        } else {
                            $questiontime = $repollquestion->getQuestionTime();
                        }
                        $this->jsonlib->set('questiontime', $questiontime);
                    } else {
                        $this->jsonlib->set('questiontime', 0);
                    }
                    $delay = $this->session->get_session()->nextstarttime - time();
                    $this->jsonlib->set('delay', $delay);

                    $this->jsonlib->send_response();

                } else {
                    $this->jsonlib->send_error('invalidaction');
                }

                break;
            case 'gotoquestion':

                if ($this->RTQ->is_instructor()) {

                    $qnum = optional_param('qnum', '', PARAM_INT);

                    if (empty($qnum)) {
                        $this->jsonlib->send_error('invalid question number');
                    }

                    $keepflow = optional_param('keepflow', '', PARAM_TEXT);
                    if (!empty($keepflow)) {
                        // Only one keepflow at a time. Two improvised questions can be run after eachother.
                        if ($this->session->get_session()->nextqnum == 0) {
                            $this->session->get_session()->nextqnum = $this->session->get_session()->currentqnum + 1;
                            $this->session->save_session();
                        }
                    }

                    if (!$question = $this->session->goto_question($qnum)) {
                        $this->jsonlib->send_error('invalid question number');
                    }

                    $this->session->set_status('running');
                    $this->jsonlib->set('status', 'startedquestion');
                    $qattempt = $this->session->get_open_attempt();
                    $this->jsonlib->set('lastquestion', ($qattempt->lastquestion ? 'true' : 'false'));
                    $this->jsonlib->set('questionid', $question->get_slot());
                    $this->jsonlib->set('nextstarttime', $this->session->get_session()->nextstarttime);

                    $this->jsonlib->set('notime', $question->getNoTime());
                    if ($question->getNoTime() == 0) {
                        // this question has a time limit

                        if ($question->getQuestionTime() == 0) {
                            $questiontime = $this->RTQ->getRTQ()->defaultquestiontime;
                        } else {
                            $questiontime = $question->getQuestionTime();
                        }
                        $this->jsonlib->set('questiontime', $questiontime);
                    } else {
                        $this->jsonlib->set('questiontime', 0);
                    }
                    $delay = $this->session->get_session()->nextstarttime - time();
                    $this->jsonlib->set('delay', $delay);

                    $this->jsonlib->send_response();

                } else {
                    $this->jsonlib->send_error('invalidaction');
                }

                break;
            case 'endquestion':
                // update the session status to say that we're ending the question (this will in turn update students

                if ($this->RTQ->is_instructor()) {

                    $this->session->end_question();
                    $this->jsonlib->set('status', 'success');
                    $this->jsonlib->send_response();

                } else {
                    $this->jsonlib->send_error('invalidaction');
                }

                break;
            case 'getrightresponse':

                if ($this->RTQ->is_instructor()) {

                    $rightresponsequestion = $this->session->get_question_right_response();

                    $this->jsonlib->set('rightanswer', $rightresponsequestion);
                    $this->jsonlib->set('status', 'success');
                    $this->jsonlib->send_response();

                } else {
                    $this->jsonlib->send_error('invalidaction');
                }

                break;
            case 'closesession':

                // only allow instructors to perform this action
                if ($this->RTQ->is_instructor()) {

                    $this->session->end_session();

                    // next calculate and save grades

                    if (!$this->RTQ->get_grader()->save_all_grades()) {
                        $this->jsonlib->send_error('can\'t save grades');
                    }

                    $this->jsonlib->set('status', 'success');
                    $this->jsonlib->send_response();
                } else {
                    $this->jsonlib->send_error('invalidaction');
                }

                break;
            default:
                $this->jsonlib->send_error('invalidaction');
                break;
        }
    }
}

