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
 * Edit page for editing questions on the quiz
 *
 * @package   mod_jazzquiz
 * @author    Sebastian S. Gundersen <sebastsg@stud.ntnu.no>
 * @copyright 2014 University of Wisconsin - Madison
 * @copyright 2018 NTNU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_jazzquiz;

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/jazzquiz/lib.php');
require_once($CFG->dirroot . '/mod/jazzquiz/locallib.php');
require_once($CFG->dirroot . '/question/editlib.php');

function print_json($array) {
    echo json_encode($array);
}

/**
 * Check if this JazzQuiz has an open session.
 * @param int $jazzquiz_id
 * @return bool
 */
function jazzquiz_session_open($jazzquiz_id) {
    global $DB;
    $sessions = $DB->get_records('jazzquiz_sessions', [
        'jazzquizid' => $jazzquiz_id,
        'sessionopen' => 1
    ]);
    return count($sessions) > 0;
}

/**
 * Gets the question bank view based on the options passed in at the page setup.
 * @param $contexts
 * @param jazzquiz $jazzquiz
 * @param \moodle_url $url
 * @param $page_vars
 * @return string
 */
function get_question_bank_view($contexts, $jazzquiz, $url, $page_vars) {
    $questions_per_page = optional_param('qperpage', 10, PARAM_INT);
    $question_page = optional_param('qpage', 0, PARAM_INT);
    // Capture question bank display in buffer to have the renderer render output.
    ob_start();
    $question_bank = new bank\jazzquiz_question_bank_view($contexts, $url, $jazzquiz->course, $jazzquiz->course_module);
    $question_bank->display('editq', $question_page, $questions_per_page, $page_vars['cat'], true, true, true);
    return ob_get_clean();
}

/**
 * Echos the list of questions using the renderer for jazzquiz.
 * @param \context[] $contexts
 * @param jazzquiz $jazzquiz
 * @param \moodle_url $url
 * @param array $page_vars
 */
function list_questions($contexts, $jazzquiz, $url, $page_vars) {
    $question_bank_view = get_question_bank_view($contexts, $jazzquiz, $url, $page_vars);
    $questions = $jazzquiz->questions;
    $jazzquiz->renderer->listquestions($questions, $question_bank_view, $url);
}

/**
 * @param jazzquiz $jazzquiz
 */
function jazzquiz_edit_order($jazzquiz) {
    $order = required_param('order', PARAM_RAW);
    $order = json_decode($order);
    $jazzquiz->set_question_order($order);
}

/**
 * @param jazzquiz $jazzquiz
 * @param \moodle_url $url
 */
function jazzquiz_edit_add_question($jazzquiz, $url) {
    $question_id = required_param('questionid', PARAM_INT);
    $jazzquiz->add_question($question_id);
    // Ensure there is no action or questionid in the base url
    $url->remove_params('action', 'questionid');
    redirect($url, null, 0);
}

/**
 * @param jazzquiz $jazzquiz
 */
function jazzquiz_edit_edit_question($jazzquiz) {
    $question_id = required_param('questionid', PARAM_INT);
    $jazzquiz->edit_question($question_id);
}

/**
 * @param jazzquiz $jazzquiz
 * @param $contexts
 * @param \moodle_url $url
 * @param $page_vars
 */
function jazzquiz_edit_list_questions($jazzquiz, $contexts, $url, $page_vars) {
    $jazzquiz->renderer->print_header();
    list_questions($contexts, $jazzquiz, $url, $page_vars);
    $jazzquiz->renderer->footer();
}

function jazzquiz_edit() {
    global $PAGE;

    $action = optional_param('action', 'listquestions', PARAM_ALPHA);

    // Inconsistency in question_edit_setup.
    if (isset($_GET['id'])) {
        $_GET['cmid'] = $_GET['id'];
    }
    if (isset($_POST['id'])) {
        $_POST['cmid'] = $_POST['id'];
    }

    list(
        $url,
        $contexts,
        $course_module_id,
        $course_module,
        $module, // jazzquiz database record
        $page_vars) = question_edit_setup('editq', '/mod/jazzquiz/edit.php', true);

    $jazzquiz = new jazzquiz($course_module_id, 'edit');
    $renderer = $jazzquiz->renderer;

    $module_name = get_string('modulename', 'jazzquiz');
    $quiz_name = format_string($jazzquiz->data->name, true);

    $PAGE->set_url($url);
    $PAGE->set_title(strip_tags($jazzquiz->course->shortname . ': ' . $module_name . ': ' . $quiz_name));
    $PAGE->set_heading($jazzquiz->course->fullname);

    if (jazzquiz_session_open($jazzquiz->data->id)) {
        // Can't edit during a session.
        $renderer->print_header();
        $renderer->opensession();
        $renderer->footer();
        return;
    }

    switch ($action) {
        case 'order':
            jazzquiz_edit_order($jazzquiz);
            break;
        case 'addquestion':
            jazzquiz_edit_add_question($jazzquiz, $url);
            break;
        case 'editquestion':
            jazzquiz_edit_edit_question($jazzquiz);
            break;
        case 'listquestions':
            jazzquiz_edit_list_questions($jazzquiz, $contexts, $url, $page_vars);
            break;
        default:
            break;
    }
}

jazzquiz_edit();
