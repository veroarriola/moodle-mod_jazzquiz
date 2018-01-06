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

namespace mod_jazzquiz;

require_once("../../config.php");
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/mod/jazzquiz/locallib.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/mod/jazzquiz/lib.php');
require_once($CFG->libdir . '/tablelib.php');

function jazzquiz_reports() {
    global $PAGE;

    $cmid = optional_param('id', false, PARAM_INT);
    if (!$cmid) {
        // Probably a login redirect that doesn't include any ID.
        // Go back to the main Moodle page, because we have no info.
        header('Location: /');
        exit;
    }

    $jazzquiz = new jazzquiz($cmid, 'report');
    $jazzquiz->require_capability('mod/jazzquiz:seeresponses');
    $renderer = $jazzquiz->renderer;

    $reporttype = optional_param('reporttype', 'overview', PARAM_ALPHA);
    $action = optional_param('action', '', PARAM_ALPHANUM);

    $url = new \moodle_url('/mod/jazzquiz/reports.php');
    $url->param('id', $cmid);
    $url->param('quizid', $jazzquiz->data->id);
    $url->param('reporttype', $reporttype);
    $url->param('action', $action);

    $modulename = get_string('modulename', 'jazzquiz');
    $quizname = format_string($jazzquiz->data->name, true);

    $PAGE->set_pagelayout('incourse');
    $PAGE->set_context($jazzquiz->context);
    $PAGE->set_title(strip_tags($jazzquiz->course->shortname . ': ' . $modulename . ': ' . $quizname));
    $PAGE->set_heading($jazzquiz->course->fullname);
    $PAGE->set_url($url);

    $report = new report_overview();
    $is_download = isset($_GET['download']); // TODO: optional_param.
    if (!$is_download) {
        $renderer->header($jazzquiz);
    }
    $report->handle_request($jazzquiz, $action, $url);
    if (!$is_download) {
        $renderer->footer();
    }
}

jazzquiz_reports();
