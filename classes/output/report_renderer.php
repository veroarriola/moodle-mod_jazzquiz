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

namespace mod_jazzquiz\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Renderer for the reports page.
 *
 * @package   mod_jazzquiz
 * @author    Sebastian S. Gundersen <sebastsg@stud.ntnu.no>
 * @copyright 2015 John Hoopes <john.z.hoopes@gmail.com>
 * @copyright 2018 NTNU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_renderer extends \plugin_renderer_base {

    /**
     * Render the header for the page.
     * @param \mod_jazzquiz\jazzquiz $jazzquiz
     */
    public function header($jazzquiz) {
        echo $this->output->header();
        echo jazzquiz_view_tabs($jazzquiz, 'reports');
    }

    /**
     * Render the footer for the page.
     */
    public function footer() {
        echo $this->output->footer();
    }

    /**
     * Renders and echos the home page for the responses section
     * @param \moodle_url $url
     * @param \stdClass[] $sessions
     * @param string|int $selectedid
     */
    public function select_session($url, $sessions, $selectedid = '') {
        $output = '';
        $selectsessionstring = get_string('select_session', 'jazzquiz');
        $selectsession = \html_writer::start_div('');
        $selectsession .= \html_writer::tag('h3', $selectsessionstring, ['class' => 'inline-block']);
        $selectsession .= '<br>';
        $sessionselecturl = clone($url);
        $sessionselecturl->param('action', 'viewsession');

        usort($sessions, function($a, $b) {
            return strcmp(strtolower($a->name), strtolower($b->name));
        });
        $sessionoptions = [];
        foreach ($sessions as $session) {
            $sessionoptions[$session->id] = $session->name;
        }

        $sessionselect = new \single_select($sessionselecturl, 'sessionid', $sessionoptions, $selectedid);

        $selectsession .= \html_writer::div($this->output->render($sessionselect), 'inline-block');
        $selectsession .= \html_writer::end_div();

        $output .= $selectsession;
        $output = \html_writer::div($output, 'jazzquiz-box');
        echo $output;
    }

}
