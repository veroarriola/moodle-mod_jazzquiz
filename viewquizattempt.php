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
 * This handles the viewing of a quiz attempt
 *
 * Provides grading interface for instructor
 *
 * @package   mod_jazzquiz
 * @author    John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright 2014 University of Wisconsin - Madison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_jazzquiz;

require_once("../../config.php");

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/mod/jazzquiz/locallib.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/mod/jazzquiz/lib.php');
require_once($CFG->libdir . '/tablelib.php');

function jazzquiz_view_quiz_attempt()
{
    global $PAGE, $DB, $USER;

    $pagevars = [];
    $pageurl = new \moodle_url('/mod/jazzquiz/viewquizattempt.php');
    $pageurl->remove_all_params();

    $id = optional_param('id', false, PARAM_INT);
    $quizid = optional_param('quizid', false, PARAM_INT);

    // get necessary records from the DB
    if ($id) {
        $cm = get_coursemodule_from_id('jazzquiz', $id, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $quiz = $DB->get_record('jazzquiz', ['id' => $cm->instance], '*', MUST_EXIST);
    } else {
        $quiz = $DB->get_record('jazzquiz', ['id' => $quizid], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $quiz->course], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('jazzquiz', $quiz->id, $course->id, false, MUST_EXIST);
    }

    $pagevars['action'] = optional_param('action', '', PARAM_ALPHAEXT);
    $pagevars['attemptid'] = required_param('attemptid', PARAM_INT);
    $pagevars['sessionid'] = required_param('sessionid', PARAM_INT);
    $pagevars['slot'] = optional_param('slot', '', PARAM_INT);

    require_login($course->id, false, $cm);

    $pageurl->param('id', $cm->id);
    $pageurl->param('quizid', $quiz->id);
    $pageurl->params($pagevars); // add the page vars variable to the url
    $pagevars['pageurl'] = $pageurl;

    $RTQ = new jazzquiz($cm, $course, $quiz, $pageurl, $pagevars);
    $RTQ->require_capability('mod/jazzquiz:viewownattempts');

    $PAGE->set_pagelayout('popup');
    $PAGE->set_context($RTQ->context);
    $PAGE->set_title(strip_tags($course->shortname . ': ' . get_string("modulename", "jazzquiz") . ': ' . format_string($quiz->name, true)));
    $PAGE->set_heading($course->fullname);
    $PAGE->set_url($pageurl);

    // handle

    // Show the attempt
    $session = $RTQ->get_session($pagevars['sessionid']);
    $attempt = $session->get_user_attempt($pagevars['attemptid']);
    $has_capability = true;

    if (!$RTQ->has_capability('mod/jazzquiz:seeresponses')) {
        // If the current user doesn't have the ability to see responses (or all responses)
        // check that the current one is theirs
        if ($attempt->userid != $USER->id) { // first check if attempts userid and current userid match
            $RTQ->renderer->render_popup_error(get_string('invalid_attempt_access', 'jazzquiz'));
            $has_capability = false;
        }
    }

    if ($has_capability) {
        $params = [
            'relateduserid' => $attempt->userid,
            'objectid' => $attempt->id,
            'context' => $RTQ->context,
            'other' => [
                'jazzquizid' => $RTQ->data->id,
                'sessionid' => $attempt->sessionid
            ]
        ];
        if ($attempt->userid < 0) {
            $params['relateduserid'] = 0;
        }
        $event = event\attempt_viewed::create($params);
        $event->add_record_snapshot('jazzquiz_attempts', $attempt->data);
        $event->trigger();
        $RTQ->renderer->render_attempt($attempt, $session);
    }

}

jazzquiz_view_quiz_attempt();
