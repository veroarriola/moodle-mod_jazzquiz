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
 * The view page where you can start or participate in a session.
 *
 * @package   mod_jazzquiz
 * @author    Sebastian S. Gundersen <sebastsg@stud.ntnu.no>
 * @copyright 2014 University of Wisconsin - Madison
 * @copyright 2018 NTNU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_jazzquiz;

require_once("../../config.php");
require_once($CFG->dirroot . '/mod/jazzquiz/lib.php');
require_once($CFG->dirroot . '/mod/jazzquiz/locallib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/editlib.php');

/**
 * @param jazzquiz $jazzquiz
 */
function jazzquiz_view_start_quiz($jazzquiz) {
    global $PAGE;
    $renderer = $jazzquiz->renderer;
    $url = $PAGE->url;

    // Set the quiz view page to the base layout for 1 column layout
    $PAGE->set_pagelayout('base');

    $session = new jazzquiz_session($jazzquiz);
    if ($session->data === false) {
        // Redirect them to the default page with a quick message first
        $redirect_url = clone($url);
        $redirect_url->remove_params('action');
        redirect($redirect_url, get_string('no_session', 'jazzquiz'), 5);
        return;
    }

    // Initialize the question attempts
    $attempts_initialized = $session->init_attempts($jazzquiz->is_instructor());
    if (!$attempts_initialized) {
        print_error('cantinitattempts', 'jazzquiz');
    }

    // Get the current attempt and initialize the head contributions
    $session->open_attempt->get_html_head_contributions();
    $session->open_attempt->set_status('inprogress');

    // Show the quiz start landing page
    $renderer->view_header(true);
    $renderer->render_quiz($session);
    $renderer->view_footer();
}

/**
 * @param jazzquiz $jazzquiz
 */
function jazzquiz_view_default($jazzquiz) {
    global $PAGE, $DB;
    $renderer = $jazzquiz->renderer;
    $url = $PAGE->url;
    $session = new jazzquiz_session($jazzquiz);

    // Show view to start quiz (for instructors) or join quiz (for students)

    // Trigger event for course module viewed
    $event = event\course_module_viewed::create([
        'objectid' => $PAGE->cm->instance,
        'context' => $PAGE->context,
    ]);
    $event->add_record_snapshot('course', $jazzquiz->course);
    $event->add_record_snapshot($PAGE->cm->modname, $jazzquiz->data);
    $event->trigger();

    // Determine home display based on role
    if ($jazzquiz->is_instructor()) {
        $start_session_form = new forms\view\start_session($url);
        $data = $start_session_form->get_data();
        if ($data) {
            // Create a new quiz session
            // First check to see if there are any open sessions
            // This shouldn't occur, but never hurts to check
            $sessions = $DB->get_records('jazzquiz_sessions', [
                'jazzquizid' => $jazzquiz->data->id,
                'sessionopen' => 1
            ]);

            if (!empty($sessions)) {
                // Error out with that there are existing sessions
                $renderer->setMessage(get_string('already_existing_sessions', 'jazzquiz'), 'error');
                $renderer->view_header();
                $renderer->view_inst_home($start_session_form, $session->data->sessionopen);
                $renderer->view_footer();
                return;
            }

            // Create the session
            $session_created_successfully = $session->create_session($data);
            if (!$session_created_successfully) {
                // Error handling
                $renderer->setMessage(get_string('unable_to_create_session', 'jazzquiz'), 'error');
                $renderer->view_header();
                $renderer->view_inst_home($start_session_form, $session->data->sessionopen);
                $renderer->view_footer();
                return;
            }

            // Redirect to the quiz start
            $quiz_start_url = clone($url);
            $quiz_start_url->param('action', 'quizstart');
            redirect($quiz_start_url, null, 0);

        } else {
            $renderer->view_header();
            $renderer->view_inst_home($start_session_form, $session->data->sessionopen);
            $renderer->view_footer();
        }
    } else {

        $student_start_form = new forms\view\student_start_form($url, [
            'rtq' => $jazzquiz
        ]);

        $data = $student_start_form->get_data();
        if ($data) {
            $quiz_start_url = clone($url);
            $quiz_start_url->param('action', 'quizstart');
            redirect($quiz_start_url, null, 0);
        } else {
            // Form will display only if there is an active session.
            $renderer->view_header();
            $renderer->view_student_home($student_start_form, $session);
            $renderer->view_footer();
        }
    }
}

function jazzquiz_view() {
    global $PAGE;

    $course_module_id = optional_param('id', false, PARAM_INT);
    if (!$course_module_id) {
        // Probably a login redirect that doesn't include any ID.
        // Go back to the main Moodle page, because we have no info.
        header('Location: /');
        exit;
    }

    $action = optional_param('action', '', PARAM_ALPHANUM);
    $jazzquiz = new jazzquiz($course_module_id, null);
    $jazzquiz->require_capability('mod/jazzquiz:attempt');
    $module_name = get_string('modulename', 'jazzquiz');
    $quiz_name = format_string($jazzquiz->name, true);
    $question_count = count($jazzquiz->questions);

    $url = new \moodle_url('/mod/jazzquiz/view.php');
    $url->param('id', $course_module_id);
    $url->param('quizid', $jazzquiz->id);
    $url->param('action', $action);

    $PAGE->set_pagelayout('incourse'); // todo: remove this line?
    $PAGE->set_context($jazzquiz->context);
    $PAGE->set_cm($jazzquiz->course_module);
    $PAGE->set_title(strip_tags($jazzquiz->course->shortname . ': ' . $module_name . ': ' . $quiz_name));
    $PAGE->set_heading($jazzquiz->course->fullname);
    $PAGE->set_url($url);

    $renderer = $jazzquiz->renderer;

    if ($jazzquiz->is_instructor()) {
        // TODO: Find a better place to add the question definitions.
        improviser::insert_default_improvised_question_definitions();
    }

    if ($question_count === 0) {
        $renderer->view_header();
        $renderer->no_questions($jazzquiz->is_instructor());
        $renderer->view_footer();
        return;
    }

    if ($action === 'quizstart') {
        jazzquiz_view_start_quiz($jazzquiz);
    } else {
        jazzquiz_view_default($jazzquiz);
    }
}

jazzquiz_view();
