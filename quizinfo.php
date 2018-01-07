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
 * This is used so the javascript can act accordingly to the instructor's actions
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

require_login();
require_sesskey();

// TODO: This file should be merged with quizdata.php

function quiz_info() {
    global $DB;

    // If they've passed the sesskey information grab the session info
    $sessionid = required_param('sessionid', PARAM_INT);

    // First determine if we get a session.
    $session = $DB->get_record('jazzquiz_sessions', ['id' => $sessionid]);
    if (!$session) {
        return [
            'status' => 'error',
            'message' => "Invalid session $sessionid"
        ];
    }

    // Next we need to get the JazzQuiz object and course module object to make sure a student can log in for the session asked for
    $jazzquiz = $DB->get_record('jazzquiz', ['id' => $session->jazzquizid]);
    if (!$jazzquiz) {
        return [
            'status' => 'error',
            'message' => "Invalid JazzQuiz $session->jazzquizid"
        ];
    }

    try {
        $course = $DB->get_record('course', ['id' => $jazzquiz->course], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('jazzquiz', $jazzquiz->id, $course->id, false, MUST_EXIST);
        require_login($course->id, false, $cm, false, true);
    } catch (\Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Did not find course ' . $jazzquiz->course
        ];
    }

    // Check if the session is open
    if ($session->sessionopen == 0) {
        return [
            'status' => 'sessionclosed',
            'message' => 'The specified session is closed'
        ];
    }

    switch ($session->status) {

        // Just a generic response with the state
        case 'notrunning':
            $jazzquiz = new jazzquiz($cm->id);
            if ($jazzquiz->is_instructor()) {
                $session = new jazzquiz_session($jazzquiz, $session->id);
                $session->load_attempts();
                return [
                    'status' => $session->data->status,
                    'student_count' => $session->get_student_count()
                ];
            }
        // fall-through
        case 'preparing':
        case 'reviewing':
            return [
                'status' => $session->status,
                'slot' => $session->slot // For the preplanned questions.
            ];

        // TODO: Not send options here. Quizdata should probably take care of that.
        case 'voting':
            $voteoptions = $DB->get_records('jazzquiz_votes', ['sessionid' => $sessionid]);
            $options = [];
            $html = '<div class="jazzquiz-vote">';
            $i = 0;
            foreach ($voteoptions as $voteoption) {
                $options[] = [
                    'text' => $voteoption->attempt,
                    'id' => $voteoption->id,
                    'question_type' => $voteoption->qtype,
                    'content_id' => "vote_answer_label_$i"
                ];
                $html .= '<label>';
                $html .= '<input class="jazzquiz-select-vote" type="radio" name="vote" value="' . $voteoption->id . '">';
                $html .= '<span id="vote_answer_label_' . $i . '">' . $voteoption->attempt . '</span>';
                $html .= '</label><br>';
                $i++;
            }
            $html .= '</div>';
            $html .= '<button id="jazzquiz_save_vote" class="btn btn-primary">Save</button>';
            return [
                'status' => 'voting',
                'html' => $html,
                'options' => $options
            ];

        // Send the currently active question
        case 'running':
            return [
                'status' => 'running',
                'questiontime' => $session->currentquestiontime,
                'delay' => $session->nextstarttime - time()
            ];

        // This should not be reached, but if it ever is, let's just assume the quiz is not running.
        default:
            return [
                'status' => 'notrunning',
                'message' => 'Unknown error. State: ' . $session->status
            ];
    }
}

$starttime = microtime(true);
$info = quiz_info();
$endtime = microtime(true);
$info['debugmu'] = $endtime - $starttime;
echo json_encode($info);
