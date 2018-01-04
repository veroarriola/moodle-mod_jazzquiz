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
 * @author    Sebastian S. Gundersen <sebastsg@stud.ntnu.no>
 * @copyright 2014 University of Wisconsin - Madison
 * @copyright 2018 NTNU
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
 * @param jazzquiz $jazzquiz
 */
function show_all_improvise_questions($jazzquiz)
{
    global $DB;
    $question_records = $DB->get_records_sql('SELECT * FROM {question} WHERE name LIKE ?', ['{IMPROV}%']);
    if (!$question_records) {
        print_json([
            'status' => 'error',
            'message' => 'No improvisation questions'
        ]);
        return;
    }
    $questions = [];
    foreach ($question_records as $question) {
        $questions[] = [
            'question_id' => $question->id,
            'jazzquiz_question_id' => 0,
            'name' => str_replace('{IMPROV}', '', $question->name),
            'time' => $jazzquiz->data->defaultquestiontime
        ];
    }
    print_json([
        'status' => 'success',
        'questions' => $questions
    ]);
}

/**
 * Sends a list of all the questions added to the quiz.
 * @param jazzquiz $jazzquiz
 */
function show_all_jump_questions($jazzquiz)
{
    global $DB;
    $sql = 'SELECT q.id AS id, q.name AS name, jq.questiontime AS time, jq.id AS jq_id';
    $sql .= '  FROM {jazzquiz_questions} AS jq';
    $sql .= '  JOIN {question} AS q ON q.id = jq.questionid';
    $sql .= ' WHERE jq.jazzquizid = ?';
    $question_records = $DB->get_records_sql($sql, [$jazzquiz->data->id]);
    $questions = [];
    foreach ($question_records as $question) {
        $questions[] = [
            'question_id' => $question->id,
            'jazzquiz_question_id' => $question->jq_id,
            'name' => $question->name,
            'time' => $question->time
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
function get_question_form($jazzquiz, $session)
{
    $slot = optional_param('slot', 0, PARAM_INT);
    if ($slot === 0) {
        $slot = count($session->questions);
    }
    $html = '';
    $js = '';
    $is_already_submitted = true;
    if (!$session->open_attempt->has_responded($slot)) {
        /** @var output\renderer $renderer */
        $renderer = $jazzquiz->renderer;
        list($html, $js) = $renderer->render_question_form($slot, $session->open_attempt);
        $is_already_submitted = false;
    }
    print_json([
        'html' => $html,
        'js' => $js,
        'question_type' => $session->get_question_type_by_slot($slot),
        'is_already_submitted' => $is_already_submitted
    ]);
}

/**
 * @param jazzquiz_session $session
 */
function start_question($session)
{
    $method = required_param('method', PARAM_ALPHA);
    // $question_id is a Moodle question id
    switch ($method) {
        case 'jump':
            $question_id = required_param('question_id', PARAM_INT);
            $question_time = optional_param('question_time', 0, PARAM_INT);
            $jazzquiz_question_id = optional_param('jazzquiz_question_id', 0, PARAM_INT);
            if ($jazzquiz_question_id !== 0) {
                $session->data->slot = $session->jazzquiz->get_question_by_id($jazzquiz_question_id)->data->slot;
            }
            break;
        case 'repoll':
            $last_slot = count($session->questions);
            if ($last_slot === 0) {
                print_json([
                    'status' => 'error',
                    'message' => 'Nothing to repoll.'
                ]);
                return;
            }
            $question_id = $session->questions[$last_slot]->questionid;
            $question_time = $session->data->currentquestiontime;
            break;
        case 'next':
            $last_slot = count($session->jazzquiz->questions);
            if ($session->data->slot >= $last_slot) {
                print_json([
                    'status' => 'error',
                    'message' => 'No next question.'
                ]);
                return;
            }
            $session->data->slot++;
            $jazzquiz_question = $session->jazzquiz->questions[$session->data->slot];
            $question_id = $jazzquiz_question->question->id;
            $question_time = $jazzquiz_question->data->questiontime;
            break;
        default:
            print_json([
                'status' => 'error',
                'message' => "Invalid method $method"
            ]);
            return;
    }
    list($success, $question_time) = $session->start_question($question_id, $question_time);
    if (!$success) {
        print_json([
            'status' => 'error',
            'message' => "Failed to start question $question_id for session"
        ]);
        return;
    }

    $session->data->status = 'running';
    $session->save();

    print_json([
        'status' => 'success',
        'question_time' => $question_time,
        'delay' => $session->data->nextstarttime - time()
    ]);
}

/**
 * @param jazzquiz_session $session
 */
function start_quiz($session)
{
    if ($session->data->status !== 'notrunning') {
        print_json([
            'status' => 'error',
            'message' => 'Quiz is already running'
        ]);
        return;
    }
    $session->data->status = 'preparing';
    $session->save();
    print_json([
        'status' => 'success'
    ]);
}

/**
 * @param jazzquiz_session $session
 */
function save_question($session)
{
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

    $attempt->save_question(count($session->questions));

    // Only give feedback if specified in session
    $feedback = '';
    if ($session->data->showfeedback) {
        $feedback = $attempt->get_question_feedback();
    }

    print_json([
        'status' => 'success',
        'feedback' => $feedback
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
    $question_type = optional_param('question_type', '', PARAM_ALPHANUM);

    // Initialize the votes
    $vote = new jazzquiz_vote($session->data->id);
    $slot = count($session->questions);
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
    $vote_id = required_param('vote', PARAM_INT);

    // Get the user who voted
    $user_id = $session->get_current_userid();

    // Save the vote
    $vote = new jazzquiz_vote($session->data->id);
    $status = $vote->save_vote($vote_id, $user_id);
    if (!$status) {
        print_json([
            'status' => 'error'
        ]);
        return;
    }
    print_json([
        'status' => 'success'
    ]);
}

/**
 * @param jazzquiz_session $session
 */
function get_vote_results($session)
{
    $slot = count($session->questions);
    $vote = new jazzquiz_vote($session->data->id, $slot);
    $votes = $vote->get_results();
    print_json([
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
        'right_answer' => $session->get_question_right_response()
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
 * @param jazzquiz_session $session
 */
function get_results($session)
{
    // Get the results
    $slot = count($session->questions);
    $question_type = $session->get_question_type_by_slot($slot);
    $responses = $session->get_question_results_list($slot);

    // Check if this has been voted on before
    $vote = new jazzquiz_vote($session->data->id, $slot);
    $has_votes = count($vote->get_results()) > 0;

    print_json([
        'has_votes' => $has_votes,
        'question_type' => $question_type,
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
        case 'get_question_form':
            get_question_form($jazzquiz, $session);
            exit;
        case 'save_question':
            save_question($session);
            exit;
        case 'list_improvise_questions':
            show_all_improvise_questions($jazzquiz);
            exit;
        case 'list_jump_questions':
            show_all_jump_questions($jazzquiz);
            exit;
        case 'run_voting':
            run_voting($jazzquiz, $session);
            exit;
        case 'get_vote_results':
            get_vote_results($session);
            exit;
        case 'get_results':
            get_results($session);
            exit;
        case 'start_question':
            start_question($session);
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
 * @param jazzquiz $jazzquiz
 * @param jazzquiz_session $session
 */
function handle_student_request($action, $jazzquiz, $session)
{
    switch ($action) {
        case 'save_question':
            save_question($session);
            exit;
        case 'save_vote':
            save_vote($session);
            exit;
        case 'get_question_form':
            get_question_form($jazzquiz, $session);
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
    if ($attempt->get_status() !== 'inprogress') {
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
        handle_student_request($action, $jazzquiz, $session);
    }
}

jazzquiz_quizdata();
