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
 * Sends a list of all the questions tagged for use with improvisation.
 */
function show_all_improvisation_questions()
{
    global $DB;
    $questions = $DB->get_records_sql('SELECT * FROM {question} WHERE name LIKE ?', ['{IMPROV}%']);
    if (!$questions) {
        print_json([
            'status' => 'error',
            'message' => 'No improvisation questions'
        ]);
        return;
    }
    foreach ($questions as $question) {
        $questions[] = [
            'question_id' => $question->id,
            'name' => str_replace('{IMPROV}', '', $question->name)
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
 */
function start_question($jazzquiz, $session)
{
    $slot = required_param('slot', PARAM_INT);
    $attempt = $session->open_attempt;
    $question = $attempt->quba->get_question($slot);
    $slot = $jazzquiz->add_question_to_running_quiz($session, $question->id);
    if ($slot === 0) {
        print_json([
            'status' => 'error',
            'message' => "Failed to add duplicate question definition by slot $slot"
        ]);
        return;
    }

    // We must update the quba of our attempt
    $attempt_id = required_param('attemptid', PARAM_INT);
    $attempt = $session->get_user_attempt($attempt_id);
    $session->open_attempt = $attempt;

    // Get question to go to
    $question = $session->start_question($slot);
    if (!$question) {
        print_json([
            'status' => 'error',
            'message' => "Invalid slot $slot"
        ]);
        return;
    }

    $session->data->status = 'running';
    $session->save();

    $question_time = 0;
    if ($question->data->notime == 0) {
        // This question has a time limit
        if ($question->data->questiontime == 0) {
            $question_time = $jazzquiz->data->defaultquestiontime;
        } else {
            $question_time = $question->data->questiontime;
        }
    }

    print_json([
        'status' => 'startedquestion',
        'slot' => $question->data->slot,
        'nextstarttime' => $session->data->nextstarttime,
        'notime' => $question->data->notime,
        'questiontime' => $question_time,
        'delay' => $session->data->nextstarttime - time()
    ]);
}

/**
 * @param jazzquiz_session $session
 */
function start_quiz($session)
{
    $session->data->status = 'preparing';
    $session->save();
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
    $current_question = $session->data->slot;
    $js_current_question = required_param('questionid', PARAM_INT);
    if ($current_question != $js_current_question) {
        print_json([
            'status' => 'error',
            'message' => 'Invalid question'
        ]);
        return;
    }

    // Get the attempt for the question
    $attempt = $session->open_attempt;

    // Does it belong to this user?
    if ($attempt->data->userid != $session->get_current_userid()) {
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
    if ($session->data->showfeedback) {
        $feedback = $attempt->get_question_feedback();
    }

    // We need to send the updated sequence check for javascript to update.
    // Get the sequence check on the question form. This allows the question to be resubmitted again.
    list($seqname, $seqvalue) = $attempt->get_sequence_check($session->data->slot);

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
    // Decode the questions parameter into an array
    $questions = required_param('questions', PARAM_RAW);
    $questions = json_decode(urldecode($questions), true);
    if (!$questions) {
        print_json([
            'status' => 'error',
            'message' => 'Failed to decode questions'
        ]);
        return;
    }
    $question_type = optional_param('qtype', '', PARAM_ALPHANUM);

    // Initialize the votes
    $vote = new jazzquiz_vote($session->data->id);
    $slot = $session->data->slot;
    $vote->prepare_options($jazzquiz->data->id, $question_type, $questions, $slot);

    $session->data->status = 'voting';
    $session->save();

    print_json([
        'status' => 'success'
    ]);
}

/**
 * @param jazzquiz_session $session
 */
function save_vote($session)
{
    // Get the id for the attempt that was voted on
    $vote_id = required_param('answer', PARAM_INT);

    // Get the user who voted
    $user_id = $session->get_current_userid();

    // Save the vote
    $vote = new jazzquiz_vote($session->data->id);
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
    $vote = new jazzquiz_vote($session->data->id, $session->data->slot);
    $votes = $vote->get_results();
    print_json([
        'status' => 'success',
        'answers' => $votes,
        'total_students' => $session->get_student_count()
    ]);
}

/**
 * @param jazzquiz_session $session
 */
function end_question($session) // or vote
{
    $session->data->status = 'reviewing';
    $session->save();
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
    $slot = $session->data->slot;
    $question_type = $jazzquiz->get_question_type_by_slot($slot);
    $responses = $session->get_question_results_list($slot, 'open');

    // Check if this has been voted on before
    $vote = new jazzquiz_vote($session->data->id, $slot);
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
        case 'start_quiz':
            start_quiz($session);
            exit;
        case 'save_question':
            save_question($session);
            exit;
        case 'list_improvisation_questions':
            show_all_improvisation_questions();
            exit;
        case 'run_voting':
            run_voting($jazzquiz, $session);
            exit;
        case 'get_vote_results':
            get_vote_results($session);
            exit;
        case 'get_results':
            get_results($jazzquiz, $session);
            exit;
        case 'start_question':
            start_question($jazzquiz, $session);
            exit;
        case 'end_question':
            end_question($session);
            exit;
        case 'get_right_response':
            get_right_response($session);
            exit;
        case 'close_session':
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
        case 'save_question':
            save_question($session);
            exit;
        case 'save_vote':
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

    $jazzquiz = new jazzquiz($course_module_id, null);

    $session = $DB->get_record('jazzquiz_sessions', ['id' => $session_id], '*', MUST_EXIST);
    if (!$session->sessionopen) {
        print_json([
            'status' => 'error',
            'message' => 'Session is closed'
        ]);
        return;
    }

    $session = new jazzquiz_session($jazzquiz, $session);

    $attempt = $session->get_user_attempt($attempt_id);
    if ($attempt->get_status() != 'inprogress') {
        print_json([
            'status' => 'error',
            'message' => "Invalid attempt $attempt_id"
        ]);
        return;
    }
    $session->open_attempt = $attempt;

    if ($jazzquiz->is_instructor()) {
        handle_instructor_request($action, $jazzquiz, $session);
    } else {
        handle_student_request($action, $session);
    }
}

jazzquiz_quizdata();
