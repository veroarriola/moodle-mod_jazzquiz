<?php

namespace mod_jazzquiz\output;

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
 * Renderer outputting the quiz editing UI.
 *
 * @package mod_jazzquiz
 * @copyright 2015 John Hoopes <john.z.hoopes@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_jazzquiz\traits\renderer_base;

defined('MOODLE_INTERNAL') || die();

class report_renderer extends \plugin_renderer_base
{
    use renderer_base;

    public function report_header()
    {
        $this->base_header('reports');
    }

    public function report_footer()
    {
        $this->base_footer();
    }

    /**
     * Renders and echos the home page for the responses section
     * @param \moodle_url $url
     * @param \mod_jazzquiz\jazzquiz_session[] $sessions
     * @param string|int $selected_id
     */
    public function select_session($url, $sessions, $selected_id = '')
    {
        $output = '';

        $select_session = \html_writer::start_div('');
        $select_session .= \html_writer::tag('h3', get_string('select_session', 'jazzquiz'), ['class' => 'inline-block']) . '<br>';
        $session_select_url = clone($url);
        $session_select_url->param('action', 'viewsession');

        $session_options = [];
        foreach ($sessions as $session) {
            $session_options[$session->data->id] = $session->data->name;
        }

        $session_select = new \single_select($session_select_url, 'sessionid', $session_options, $selected_id);

        $select_session .= \html_writer::div($this->output->render($session_select), 'inline-block');
        $select_session .= \html_writer::end_div();

        $output .= $select_session;
        $output = \html_writer::div($output, 'jazzquizbox');
        echo $output;
    }

    /**
     * Renders the session attempts table
     *
     * @param \mod_jazzquiz\tableviews\sessionattempts $session_attempts
     */
    public function view_session_attempts($session_attempts)
    {
        $session_attempts->setup();
        $session_attempts->show_download_buttons_at([TABLE_P_BOTTOM]);
        $session_attempts->set_data();
        $session_attempts->finish_output();
    }

}