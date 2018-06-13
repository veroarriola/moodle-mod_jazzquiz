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

require_login();

/**
 * Check if this JazzQuiz has an open session.
 * @param int $jazzquizid
 * @return bool
 */
function jazzquiz_session_open($jazzquizid) {
    global $DB;
    $sessions = $DB->get_records('jazzquiz_sessions', [
        'jazzquizid' => $jazzquizid,
        'sessionopen' => 1
    ]);
    return count($sessions) > 0;
}

/**
 * Gets the question bank view based on the options passed in at the page setup.
 * @param $contexts
 * @param jazzquiz $jazzquiz
 * @param \moodle_url $url
 * @param $pagevars
 * @return string
 */
function get_question_bank_view($contexts, $jazzquiz, $url, $pagevars) {
    $questionsperpage = optional_param('qperpage', 10, PARAM_INT);
    $questionpage = optional_param('qpage', 0, PARAM_INT);
    // Capture question bank display in buffer to have the renderer render output.
    ob_start();
    $questionbank = new bank\jazzquiz_question_bank_view($contexts, $url, $jazzquiz->course, $jazzquiz->cm);
    $questionbank->display('editq', $questionpage, $questionsperpage, $pagevars['cat'], true, true, true);
    return ob_get_clean();
}

/**
 * Echos the list of questions using the renderer for jazzquiz.
 * @param \context[] $contexts
 * @param jazzquiz $jazzquiz
 * @param \moodle_url $url
 * @param array $pagevars
 */
function list_questions($contexts, $jazzquiz, $url, $pagevars) {
    $questionbankview = get_question_bank_view($contexts, $jazzquiz, $url, $pagevars);
    $questions = $jazzquiz->questions;
    $jazzquiz->renderer->list_questions($jazzquiz, $questions, $questionbankview, $url);
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
    $questionid = required_param('questionid', PARAM_INT);
    $jazzquiz->add_question($questionid);
    // Ensure there is no action or questionid in the base url.
    $url->remove_params('action', 'questionid');
    redirect($url, null, 0);
}

/**
 * @param jazzquiz $jazzquiz
 */
function jazzquiz_edit_edit_question($jazzquiz) {
    $questionid = required_param('questionid', PARAM_INT);
    $jazzquiz->edit_question($questionid);
}

/**
 * @param jazzquiz $jazzquiz
 * @param $contexts
 * @param \moodle_url $url
 * @param $pagevars
 */
function jazzquiz_edit_list_questions($jazzquiz, $contexts, $url, $pagevars) {
    $jazzquiz->renderer->header($jazzquiz, 'edit');
    list_questions($contexts, $jazzquiz, $url, $pagevars);
    $jazzquiz->renderer->footer();
}

/**
 * View edit page.
 */
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
        $cmid,
        $cm,
        $module, // JazzQuiz database record.
        $pagevars) = question_edit_setup('editq', '/mod/jazzquiz/edit.php', true);

    $jazzquiz = new jazzquiz($cmid);
    $renderer = $jazzquiz->renderer;

    $modulename = get_string('modulename', 'jazzquiz');
    $quizname = format_string($jazzquiz->data->name, true);

    $PAGE->set_url($url);
    $PAGE->set_title(strip_tags($jazzquiz->course->shortname . ': ' . $modulename . ': ' . $quizname));
    $PAGE->set_heading($jazzquiz->course->fullname);

    if (jazzquiz_session_open($jazzquiz->data->id)) {
        // Can't edit during a session.
        $renderer->header($jazzquiz, 'edit');
        $renderer->session_is_open_error();
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
            jazzquiz_edit_list_questions($jazzquiz, $contexts, $url, $pagevars);
            break;
        default:
            break;
    }
}

jazzquiz_edit();
