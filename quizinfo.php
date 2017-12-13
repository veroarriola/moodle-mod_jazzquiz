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
 * Simple callback page to handle the many hits for quiz status when running
 *
 * This is used so the javascript can act accordingly to the instructor's actions
 *
 * @package   mod_jazzquiz
 * @author    John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright 2014 University of Wisconsin - Madison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once('../../config.php');
require_sesskey();

// If they've passed the sesskey information grab the session info
$session_id = required_param('sessionid', PARAM_INT);

$jsonlib = new \mod_jazzquiz\utils\jsonlib();

// First determine if we get a session.
$session = $DB->get_record('jazzquiz_sessions', [
    'id' => $session_id
]);
if (!$session) {
    $jsonlib->send_error("invalid session $session_id");
}

// Next we need to get the JazzQuiz object and course module object to make sure a student can log in for the session asked for
$jazzquiz = $DB->get_record('jazzquiz', [
    'id' => $session->jazzquizid
]);
if (!$jazzquiz) {
    $jsonlib->send_error("invalid jazzquiz $session->jazzquizid");
}

try {
    $course = $DB->get_record('course', [ 'id' => $jazzquiz->course ], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('jazzquiz', $jazzquiz->id, $course->id, false, MUST_EXIST);
    require_login($course->id, false, $cm, false, true);
} catch (Exception $e) {
    $jsonlib->send_error('invalid request');
    exit;
}

// Check if the session is open
if ($session->sessionopen == 0) {
    $jsonlib->set('status', 'sessionclosed');
    $jsonlib->send_response();
}

switch ($session->status) {

    // Just a generic response with the state
    case 'notrunning':
        $jazzquiz = new \mod_jazzquiz\jazzquiz($cm->id);
        if ($jazzquiz->is_instructor()) {
            $session_obj = new \mod_jazzquiz\jazzquiz_session($jazzquiz, $session);
            $attempts = $session_obj->getall_open_attempts(false);
            $jsonlib->set('students', count($attempts));
        }
        // fall-through
    case 'preparing':
    case 'endquestion':
    case 'reviewing':
        $jsonlib->set('status', $session->status);
        $jsonlib->send_response();
        break;

    // TODO: Not send options here. Quizdata should probably take care of that.
    case 'voting':
        $vote_options = $DB->get_records('jazzquiz_votes', [
            'sessionid' => $sessionid
        ]);
        $options = [];
        foreach ($vote_options as $vote_option) {
            $options[] = [
                'text' => $vote_option->attempt,
                'id' => $vote_option->id,
                'qtype' => $vote_option->qtype,
                'slot' => $vote_option->slot
            ];
        }
        $jsonlib->set('status', 'voting');
        $jsonlib->set('options', json_encode($options));
        $jsonlib->send_response();
        break;

    // Send the currently active question
    case 'running':
        if (empty($session->currentquestion)) {
            $jsonlib->set('status', 'notrunning');
            $jsonlib->send_response();
        }
        // otherwise send a response of the current question with the next start time
        $jsonlib->set('status', 'running');
        $jsonlib->set('currentquestion', $session->currentquestion);
        $jsonlib->set('questiontime', $session->currentquestiontime);
        $delay = $session->nextstarttime - time();
        $jsonlib->set('delay', $delay);
        $jsonlib->send_response();
        break;

    // This should not be reached, but if it ever is, let's just assume the quiz is not running.
    default:
        $jsonlib->set('status', 'notrunning');
        $jsonlib->set('debug', 'Unknown error. State: ' . $session->status);
        $jsonlib->send_response();
        break;
}
