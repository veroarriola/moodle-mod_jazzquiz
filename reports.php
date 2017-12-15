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
 * @author    John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright 2014 University of Wisconsin - Madison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/mod/jazzquiz/locallib.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/mod/jazzquiz/lib.php');
require_once($CFG->libdir . '/tablelib.php');

function jazzquiz_reports()
{
    global $PAGE;

    $course_module_id = optional_param('id', false, PARAM_INT);
    if (!$course_module_id) {
        // Probably a login redirect that doesn't include any ID.
        // Go back to the main Moodle page, because we have no info.
        header('Location: /');
        exit;
    }

    $jazzquiz = new \mod_jazzquiz\jazzquiz($course_module_id, 'report');
    $jazzquiz->require_capability('mod/jazzquiz:seeresponses');
    $renderer = $jazzquiz->renderer;

    $report_type = optional_param('reporttype', 'overview', PARAM_ALPHA);
    $action = optional_param('action', '', PARAM_ALPHANUM);

    $url = new \moodle_url('/mod/jazzquiz/reports.php');
    $url->param('id', $course_module_id);
    $url->param('quizid', $jazzquiz->getRTQ()->id);
    $url->param('reporttype', $report_type);
    $url->param('action', $action);

    $PAGE->set_pagelayout('incourse');
    $PAGE->set_context($jazzquiz->context);
    $PAGE->set_title(strip_tags($jazzquiz->course->shortname . ': ' . get_string('modulename', 'jazzquiz') . ': ' . format_string($jazzquiz->name, true)));
    $PAGE->set_heading($jazzquiz->course->fullname);
    $PAGE->set_url($url);

    $report = new \mod_jazzquiz\reports\report_overview($jazzquiz);
    $is_download = isset($_GET['download']);
    if (!$is_download) {
        $renderer->report_header();
    }
    $report->handle_request($action, $url);
    if (!$is_download) {
        $renderer->report_footer();
    }
}

jazzquiz_reports();
