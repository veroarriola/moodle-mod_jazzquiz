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
 * The reports page
 *
 * This handles displaying results
 *
 * @package   mod_jazzquiz
 * @author    Sebastian S. Gundersen <sebastsg@stud.ntnu.no>
 * @copyright 2018 NTNU
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

require_login();

/**
 * @param jazzquiz $jazzquiz
 * @param \moodle_url $url
 */
function jazzquiz_view_session_report($jazzquiz, $url) {
    $sessionid = required_param('sessionid', PARAM_INT);
    if (empty($sessionid)) {
        // If no session id just go to the home page.
        $redirecturl = new \moodle_url('/mod/jazzquiz/reports.php', [
            'id' => $jazzquiz->cm->id,
            'quizid' => $jazzquiz->data->id
        ]);
        redirect($redirecturl, null, 3);
    }
    $session = new jazzquiz_session($jazzquiz, $sessionid);
    $session->load_attempts();
    $url->param('sessionid', $sessionid);
    $context = $jazzquiz->renderer->view_session_report($session, $url);
    echo $jazzquiz->renderer->render_from_template('jazzquiz/report', $context);
}

/**
 * @param jazzquiz $jazzquiz
 */
function jazzquiz_export_session_report($jazzquiz) {
    $what = required_param('what', PARAM_ALPHANUM);
    $sessionid = required_param('sessionid', PARAM_INT);
    $session = new jazzquiz_session($jazzquiz, $sessionid);
    $session->load_attempts();
    $attempt = reset($session->attempts);
    if (!$attempt) {
        return;
    }
    header('Content-Type: application/csv');
    $exporter = new exporter();
    switch ($what) {
        case 'report':
            $exporter->export_session_csv($session, $session->attempts);
            break;
        case 'question':
            $slot = required_param('slot', PARAM_INT);
            $exporter->export_session_question_csv($session, $attempt, $slot);
            break;
        case 'attendance':
            $exporter->export_attendance_csv($session, $attempt);
            break;
        default:
            break;
    }
}

/**
 * @param jazzquiz $jazzquiz
 * @param \moodle_url $url
 */
function jazzquiz_select_session_report($jazzquiz, $url) {
    $sessions = $jazzquiz->get_sessions();
    $context = $jazzquiz->renderer->get_select_session_context($url, $sessions, 0);
    echo $jazzquiz->renderer->render_from_template('jazzquiz/report', [
        'select_session' => $context
    ]);
}

/**
 * Entry point for viewing reports.
 */
function jazzquiz_reports() {
    global $PAGE;

    $cmid = optional_param('id', false, PARAM_INT);
    if (!$cmid) {
        // Probably a login redirect that doesn't include any ID.
        // Go back to the main Moodle page, because we have no info.
        header('Location: /');
        exit;
    }

    $jazzquiz = new jazzquiz($cmid);
    $jazzquiz->require_capability('mod/jazzquiz:seeresponses');

    $action = optional_param('action', '', PARAM_ALPHANUM);

    $url = new \moodle_url('/mod/jazzquiz/reports.php');
    $url->param('id', $cmid);
    $url->param('quizid', $jazzquiz->data->id);
    $url->param('action', $action);

    $modulename = get_string('modulename', 'jazzquiz');
    $quizname = format_string($jazzquiz->data->name, true);

    $PAGE->set_pagelayout('incourse');
    $PAGE->set_context($jazzquiz->context);
    $PAGE->set_title(strip_tags($jazzquiz->course->shortname . ': ' . $modulename . ': ' . $quizname));
    $PAGE->set_heading($jazzquiz->course->fullname);
    $PAGE->set_url($url);

    $isdownload = optional_param('download', false, PARAM_BOOL);
    if (!$isdownload) {
        $jazzquiz->renderer->header($jazzquiz, 'reports');
    }
    switch ($action) {
        case 'view':
            jazzquiz_view_session_report($jazzquiz, $url);
            break;
        case 'export':
            jazzquiz_export_session_report($jazzquiz);
            break;
        default:
            jazzquiz_select_session_report($jazzquiz, $url);
            break;
    }
    if (!$isdownload) {
        $jazzquiz->renderer->footer();
    }
}

jazzquiz_reports();
