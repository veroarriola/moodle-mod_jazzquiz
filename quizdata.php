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
 * Ajax callback script for dealing with quiz data
 * This callback handles saving questions as well as instructor actions
 *
 * @package   mod_jazzquiz
 * @author    John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright 2014 University of Wisconsin - Madison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_jazzquiz;

define('AJAX_SCRIPT', true);

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/jazzquiz/lib.php');
require_once($CFG->libdir . '/filelib.php');

require_login();
require_sesskey();

function print_json($array)
{
    echo json_encode($array);
}

/**
 * @param jazzquiz $jazzquiz
 * @param jazzquiz_session $session
 * @param jazzquiz_question $question
 */
function start_question($jazzquiz, $session, $question)
{
    $attempt = $session->get_open_attempt();
    $session->set_status('running');

    $question_time = 0;
    if ($question->getNoTime() == 0) {
        // This question has a time limit
        if ($question->getQuestionTime() == 0) {
            $question_time = $jazzquiz->getRTQ()->defaultquestiontime;
        } else {
            $question_time = $question->getQuestionTime();
        }
    }

    print_json([
        'status' => 'startedquestion',
        'slot' => $question->get_slot(),
        'lastquestion' => ($attempt->lastquestion ? 'true' : 'false'),
        'nextstarttime' => $session->get_session()->nextstarttime,
        'notime' => $question->getNoTime(),
        'questiontime' => $question_time,
        'delay' => $session->get_session()->nextstarttime - time()
    ]);
}

/**
 * Sends a list of all the questions tagged for use with improvisation.
 * @param jazzquiz $jazzquiz
 */
function show_all_improvisation_questions($jazzquiz)
{
    global $DB;

    $quiz_questions = $DB->get_records('jazzquiz_questions', [
        'jazzquizid' => $jazzquiz->getRTQ()->id
    ]);

    if (!$quiz_questions) {
        print_json([
            'status' => 'error',
            'message' => 'No questions'
        ]);
        return;
    }

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
        $question_order = $jazzquiz->getRTQ()->questionorder;
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

    print_json([
        'status' => 'success',
        'questions' => $questions
    ]);
}

/**
 * @param jazzquiz $jazzquiz
 * @param jazzquiz_session $session
 * @param int $slot
 */
function start_goto_question($jazzquiz, $session, $slot)
{
    // Are we going to keep or break the flow of the quiz?
    $keep_flow = optional_param('keepflow', '', PARAM_TEXT);

    if (!empty($keep_flow)) {
        // Only one keep_flow at a time. Two improvised questions can be run after eachother.
        if ($session->get_session()->nextqnum == 0) {
            // Get last and current slot
            $last_slot = count($jazzquiz->getRTQ()->questionorder);
            $current_slot = intval($session->get_session()->currentqnum);
            // Does the next slot exist?
            if ($last_slot >= $current_slot + 1) {
                // Okay, let's save it
                $session->get_session()->nextqnum = $current_slot + 1;
                $session->save_session();
            }
        }
    }

    // Get question to go to
    $question = $session->goto_question($slot);
    if (!$question) {
        print_json([
            'status' => 'error',
            'message' => "Invalid slot $slot"
        ]);
        return;
    }

    start_question($jazzquiz, $session, $question);
}

/**
 * @param jazzquiz_session $session
 */
function start_quiz($session)
{
    $session->start_quiz();
    print_json([
        'status' => 'startedquiz'
    ]);
}

/**
 * @param jazzquiz_session $session
 */
function save_question($session)
{
    // Check if we're working on the current question for the session
    $current_question = $session->get_session()->currentquestion;
    $js_current_question = required_param('questionid', PARAM_INT);
    if ($current_question != $js_current_question) {
        print_json([
            'status' => 'error',
            'message' => 'Invalid question'
        ]);
        return;
    }

    // Get the attempt for the question
    $attempt = $session->get_open_attempt();

    // Does it belong to this user?
    if ($attempt->userid != $session->get_current_userid()) {
        print_json([
            'status' => 'error',
            'message' => 'Invalid user'
        ]);
        return;
    }

    $attempt_saved = $attempt->save_question();
    if (!$attempt_saved) {
        print_json([
            'status' => 'error',
            'message' => 'Unable to save attempt'
        ]);
        return;
    }

    // Only give feedback if specified in session
    $feedback = '';
    if ($session->get_session()->showfeedback) {
        $feedback = $attempt->get_question_feedback();
    }

    // We need to send the updated sequence check for javascript to update.
    // Get the sequence check on the question form. This allows the question to be resubmitted again.
    list($seqname, $seqvalue) = $attempt->get_sequence_check($session->get_session()->currentqnum);

    print_json([
        'status' => 'success',
        'feedback' => $feedback,
        'seqcheckname' => $seqname,
        'seqcheckval' => $seqvalue
    ]);
}

/**
 * @param jazzquiz $jazzquiz
 * @param jazzquiz_session $session
 */
function run_voting($jazzquiz, $session)
{
    // TODO: Not use _POST

    if (!isset($_POST['questions'])) {
        print_json([
            'status' => 'error',
            'message' => 'No questions sent'
        ]);
        return;
    }

    // Decode the questions parameter into an array
    $questions = json_decode(urldecode($_POST['questions']), true);
    if (!$questions) {
        print_json([
            'status' => 'error',
            'message' => 'Failed to decode questions'
        ]);
        return;
    }

    // Get question type
    $question_type = '';
    if (isset($_POST['qtype'])) {
        $question_type = $_POST['qtype'];
    }

    // Initialize the votes
    $vote = new jazzquiz_vote($session->get_session()->id);
    $slot = $session->get_session()->currentqnum;
    $vote->prepare_options($jazzquiz->getRTQ()->id, $question_type, $questions, $slot);

    $session->set_status('voting');

    print_json([
        'status' => 'success'
    ]);
}

/**
 * @param jazzquiz_session $session
 */
function save_vote($session)
{
    // TODO: Not use _POST

    if (!isset($_POST['answer'])) {
        print_json([
            'status' => 'error',
            'message' => 'No vote in parameters'
        ]);
        return;
    }

    // Get the id for the attempt that was voted on
    $vote_id = intval($_POST['answer']);

    // Get the user who voted
    $user_id = $session->get_current_userid();

    // Save the vote
    $vote = new jazzquiz_vote($session->get_session()->id);
    $status = $vote->save_vote($vote_id, $user_id);

    print_json([
        'status' => $status
    ]);
}

/**
 * @param jazzquiz_session $session
 */
function get_vote_results($session)
{
    $vote = new jazzquiz_vote($session->get_session()->id, $session->get_session()->currentqnum);
    $votes = $vote->get_results();
    print_json([
        'status' => 'success',
        'answers' => $votes,
        'total_students' => $session->get_student_count()
    ]);
}

/**
 * @param jazzquiz $jazzquiz
 * @param jazzquiz_session $session
 */
function next_question($jazzquiz, $session)
{
    // Are we coming from an improvised question?
    if ($session->get_session()->nextqnum != 0) {
        // Yes, we likely are. Let's get that question
        $question = $session->goto_question($session->get_session()->nextqnum);
        // We should also reset the nextqnum, since we're back in the right quiz flow again.
        $session->get_session()->nextqnum = 0;
        $session->save_session();
    } else {
        // Doesn't seem that way. Let's just start the next question in the ordered list.
        $question = $session->next_question();
    }
    start_question($jazzquiz, $session, $question);
}

/**
 * @param jazzquiz $jazzquiz
 * @param jazzquiz_session $session
 */
function repoll_question($jazzquiz, $session)
{
    $question = $session->repoll_question();
    start_question($jazzquiz, $session, $question);
}

/**
 * @param jazzquiz $jazzquiz
 * @param jazzquiz_session $session
 */
function goto_question($jazzquiz, $session)
{
    // Get the question number to go to
    $slot = optional_param('qnum', '', PARAM_INT);
    if (empty($slot)) {
        print_json([
            'status' => 'error',
            'message' => 'Invalid slot'
        ]);
        return;
    }
    start_goto_question($jazzquiz, $session, $slot);
}

/**
 * @param jazzquiz_session $session
 */
function end_question($session) // or vote
{
    $session->set_status('reviewing');
    print_json([
        'status' => 'success'
    ]);
}

/**
 * @param jazzquiz_session $session
 */
function get_right_response($session)
{
    print_json([
        'status' => 'success',
        'rightanswer' => $session->get_question_right_response()
    ]);
}

/**
 * @param jazzquiz_session $session
 */
function close_session($session)
{
    $session->end_session();
    print_json([
        'status' => 'success'
    ]);
}

/**
 * @param jazzquiz $jazzquiz
 * @param jazzquiz_session $session
 */
function get_results($jazzquiz, $session)
{
    // Get the results
    $question_manager = $jazzquiz->question_manager;
    $slot = $session->get_session()->currentqnum;
    $question_type = $question_manager->get_questiontype_byqnum($slot);
    $responses = $session->get_question_results_list($slot, 'open');

    // Check if this has been voted on before
    $vote = new \mod_jazzquiz\jazzquiz_vote($session->get_session()->id, $slot);
    $has_votes = count($vote->get_results()) > 0;

    print_json([
        'status' => 'success',
        'has_votes' => $has_votes,
        'qtype' => $question_type,
        'slot' => $slot,
        'responses' => $responses['responses'],
        'total_students' => $responses['student_count']
    ]);
}

/**
 * @param string $action
 * @param jazzquiz $jazzquiz
 * @param jazzquiz_session $session
 */
function handle_instructor_request($action, $jazzquiz, $session)
{
    switch ($action) {
        case 'startquiz':
            start_quiz($session);
            exit;
        case 'savequestion':
            save_question($session);
            exit;
        case 'listdummyquestions':
            show_all_improvisation_questions($jazzquiz);
            exit;
        case 'runvoting':
            run_voting($jazzquiz, $session);
            exit;
        case 'getvoteresults':
            get_vote_results($session);
            exit;
        case 'getresults':
            get_results($jazzquiz, $session);
            exit;
        case 'nextquestion':
            next_question($jazzquiz, $session);
            exit;
        case 'repollquestion':
            repoll_question($jazzquiz, $session);
            exit;
        case 'gotoquestion':
            goto_question($jazzquiz, $session);
            exit;
        case 'endquestion':
            end_question($session);
            exit;
        case 'getrightresponse':
            get_right_response($session);
            exit;
        case 'closesession':
            close_session($session);
            exit;
        default:
            print_json([
                'status' => 'error',
                'message' => 'Invalid action'
            ]);
            exit;
    }
}

/**
 * @param string $action
 * @param jazzquiz_session $session
 */
function handle_student_request($action, $session)
{
    switch ($action) {
        case 'savequestion':
            save_question($session);
            exit;
        case 'savevote':
            save_vote($session);
            exit;
        default:
            print_json([
                'status' => 'error',
                'message' => 'Invalid action'
            ]);
            exit;
    }
}

function jazzquiz_quizdata()
{
    global $DB;

    $course_module_id = required_param('id', PARAM_INT);
    $session_id = required_param('sessionid', PARAM_INT);
    $attempt_id = required_param('attemptid', PARAM_INT);
    $action = required_param('action', PARAM_ALPHANUMEXT);

    $jazzquiz = new \mod_jazzquiz\jazzquiz($course_module_id, null);

    $session = $DB->get_record('jazzquiz_sessions', [ 'id' => $session_id ], '*', MUST_EXIST);
    if (!$session->sessionopen) {
        print_json([
            'status' => 'error',
            'message' => 'Session is closed'
        ]);
        return;
    }

    $session = new \mod_jazzquiz\jazzquiz_session($jazzquiz, $session);

    $attempt = $session->get_user_attempt($attempt_id);
    if ($attempt->getStatus() != 'inprogress') {
        print_json([
            'status' => 'error',
            'message' => "Invalid attempt $attempt_id"
        ]);
        return;
    }
    $session->set_open_attempt($attempt);

    if ($jazzquiz->is_instructor()) {
        handle_instructor_request($action, $jazzquiz, $session);
    } else {
        handle_student_request($action, $session);
    }
}

jazzquiz_quizdata();
