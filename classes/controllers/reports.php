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

namespace mod_jazzquiz\controllers;

defined('MOODLE_INTERNAL') || die();

/**
 * The reports controller
 *
 * @package     mod_jazzquiz
 * @author      John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright   2014 University of Wisconsin - Madison
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reports extends base
{
    /** @var \mod_jazzquiz\jazzquiz_session $session The session class for the jazzquiz view */
    protected $session;

    /** @var  \mod_jazzquiz\output\report_renderer $renderer */
    protected $renderer;

    /**
     * set up the class for the view page
     *
     * @param string $base_url the base url of the page
     */
    public function setup_page($base_url)
    {
        global $PAGE;

        $this->load($base_url);

        $this->pageurl->param('id', $this->cm->id);
        $this->pageurl->param('quizid', $this->quiz->id);

        $this->pagevars['report_type'] = optional_param('reporttype', 'overview', PARAM_ALPHA);
        $this->pagevars['action'] = optional_param('action', '', PARAM_ALPHANUM);

        $this->pageurl->param('reporttype', $this->pagevars['report_type']);
        $this->pageurl->param('action', $this->pagevars['action']);

        $this->pagevars['pageurl'] = $this->pageurl;

        $this->jazzquiz = new \mod_jazzquiz\jazzquiz($this->cm, $this->course, $this->quiz, $this->pageurl, $this->pagevars, 'report');
        $this->jazzquiz->require_capability('mod/jazzquiz:seeresponses');

        $this->renderer = $this->jazzquiz->renderer;

        $PAGE->set_pagelayout('incourse');
        $PAGE->set_context($this->jazzquiz->context);
        $PAGE->set_title(strip_tags($this->course->shortname . ': ' . get_string('modulename', 'jazzquiz') . ': ' . format_string($this->quiz->name, true)));
        $PAGE->set_heading($this->course->fullname);
        $PAGE->set_url($this->pageurl);
    }

    /**
     * Handles the page request
     *
     */
    public function handle_request()
    {
        $report = new \mod_jazzquiz\reports\report_overview($this->jazzquiz);
        $is_download = isset($_GET['download']);
        if (!$is_download) {
            $this->renderer->report_header($this->pageurl, $this->pagevars);
        }
        $report->handle_request($this->pageurl, $this->pagevars);
        if (!$is_download) {
            $this->renderer->report_footer();
        }
    }

}

