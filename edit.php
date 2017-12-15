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
 * @author    John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright 2014 University of Wisconsin - Madison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_jazzquiz;

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/jazzquiz/lib.php');
require_once($CFG->dirroot . '/mod/jazzquiz/locallib.php');
require_once($CFG->dirroot . '/question/editlib.php');

function print_json($array)
{
    echo json_encode($array);
}

/**
 * Check if this JazzQuiz has an open session.
 * @param int $jazzquiz_id
 * @return bool
 */
function jazzquiz_session_open($jazzquiz_id)
{
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
 * @param string $url
 * @param $page_vars
 * @return string
 */
function get_question_bank_view($contexts, $jazzquiz, $url, $page_vars)
{
    $questions_per_page = optional_param('qperpage', 10, PARAM_INT);
    $question_page = optional_param('qpage', 0, PARAM_INT);
    // Capture question bank display in buffer to have the renderer render output.
    ob_start();
    $question_bank = new jazzquiz_question_bank_view($contexts, $url, $jazzquiz->course, $jazzquiz->course_module);
    $question_bank->display('editq', $question_page, $questions_per_page, $page_vars['cat'], true, true, true);
    return ob_get_clean();
}

/**
 * Echos the list of questions using the renderer for jazzquiz.
 * @param $contexts
 * @param jazzquiz $jazzquiz
 * @param string $url
 * @param $page_vars
 */
function list_questions($contexts, $jazzquiz, $url, $page_vars)
{
    $question_bank_view = get_question_bank_view($contexts, $jazzquiz, $url, $page_vars);
    $questions = $jazzquiz->question_manager->get_questions();
    $jazzquiz->renderer->listquestions($questions, $question_bank_view, $url);
}

/**
 * @param jazzquiz $jazzquiz
 */
function jazzquiz_edit_drag_and_drop($jazzquiz)
{
    $question_order = optional_param('questionorder', '', PARAM_RAW);
    if ($question_order === '') {
        print_json([
            'status' => 'error',
            'message' => 'Question order not specified'
        ]);
        exit;
    }
    $question_order = explode(',', $question_order);
    $success = $jazzquiz->question_manager->set_full_order($question_order);
    if (!$success) {
        print_json([
            'status' => 'error',
            'message' => 'Unable to re-sort questions'
        ]);
        exit;
    }
}

/**
 * @param jazzquiz $jazzquiz
 * @param $contexts
 * @param string $url
 * @param $page_vars
 * @param $direction
 */
function jazzquiz_edit_move($jazzquiz, $contexts, $url, $page_vars, $direction)
{
    $question_id = required_param('questionid', PARAM_INT);
    $direction = substr($direction, 4);
    if ($jazzquiz->question_manager->move_question($direction, $question_id)) {
        $type = 'success';
        $message = get_string('successfully_moved_question', 'jazzquiz');
    } else {
        $type = 'error';
        $message = get_string('failed_to_move_question', 'jazzquiz');
    }
    $renderer = $jazzquiz->renderer;
    $renderer->setMessage($type, $message);
    $renderer->print_header();
    list_questions($contexts, $jazzquiz, $url, $page_vars);
    $renderer->footer();
}

/**
 * @param jazzquiz $jazzquiz
 */
function jazzquiz_edit_add_question($jazzquiz)
{
    $question_id = required_param('questionid', PARAM_INT);
    $jazzquiz->question_manager->add_question($question_id);
}

/**
 * @param jazzquiz $jazzquiz
 */
function jazzquiz_edit_edit_question($jazzquiz)
{
    $question_id = required_param('questionid', PARAM_INT);
    $jazzquiz->question_manager->edit_question($question_id);
}

/**
 * @param jazzquiz $jazzquiz
 * @param $contexts
 * @param string $url
 * @param $page_vars
 */
function jazzquiz_edit_delete_question($jazzquiz, $contexts, $url, $page_vars)
{
    $question_id = required_param('questionid', PARAM_INT);
    if ($jazzquiz->question_manager->delete_question($question_id)) {
        $type = 'success';
        $message = get_string('successfully_deleted_question', 'jazzquiz');
    } else {
        $type = 'error';
        $message = get_string('failed_to_delete_question', 'jazzquiz');
    }
    $renderer = $jazzquiz->renderer;
    $renderer->setMessage($type, $message);
    $renderer->print_header();
    list_questions($contexts, $jazzquiz, $url, $page_vars);
    $renderer->footer();
}

/**
 * @param jazzquiz $jazzquiz
 * @param $contexts
 * @param string $url
 * @param $page_vars
 */
function jazzquiz_edit_list_questions($jazzquiz, $contexts, $url, $page_vars)
{
    $jazzquiz->renderer->print_header();
    list_questions($contexts, $jazzquiz, $url, $page_vars);
    $jazzquiz->renderer->footer();
}

function jazzquiz_edit()
{
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

    // We know no session is open, and we also know this is an instructor.
    // Before we modify anything, we have to remove the improvised questions.
    $improviser = new improviser();
    $improviser->remove_improvised_questions_from_quiz($module->id);
    // We don't have to add back the improvised questions, because we only need them in view.
    // Since $jazzquiz now has outdated question order, we must reload it:
    $jazzquiz->reload();

    $module_name = get_string('modulename', 'jazzquiz');
    $quiz_name = format_string($jazzquiz->name, true);

    $PAGE->set_url($url);
    $PAGE->set_title(strip_tags($jazzquiz->course->shortname . ': ' . $module_name . ': ' . $quiz_name));
    $PAGE->set_heading($jazzquiz->course->fullname);

    if (jazzquiz_session_open($jazzquiz->getRTQ()->id)) {
        // Can't edit during a session.
        $renderer->print_header();
        $renderer->opensession();
        $renderer->footer();
        return;
    }

    switch ($action) {
        case 'dragdrop':
            jazzquiz_edit_drag_and_drop($jazzquiz);
            break;
        case 'moveup':
        case 'movedown':
            jazzquiz_edit_move($jazzquiz, $contexts, $url, $page_vars, $action);
            break;
        case 'addquestion':
            jazzquiz_edit_add_question($jazzquiz);
            break;
        case 'editquestion':
            jazzquiz_edit_edit_question($jazzquiz);
            break;
        case 'deletequestion':
            jazzquiz_edit_delete_question($jazzquiz, $contexts, $url, $page_vars);
            break;
        case 'listquestions':
            jazzquiz_edit_list_questions($jazzquiz, $contexts, $url, $page_vars);
            break;
        default:
            break;
    }
}

jazzquiz_edit();
