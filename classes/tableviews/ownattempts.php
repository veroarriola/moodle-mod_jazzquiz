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

namespace mod_jazzquiz\tableviews;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

/**
 *
 *
 * @package   mod_realtimquiz
 * @author    John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright 2014 University of Wisconsin - madison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ownattempts extends \flexible_table
{
    /** @var \mod_jazzquiz\jazzquiz $rtq */
    protected $rtq;

    /**
     * Contstruct this table class
     *
     * @param string $uniqueid The unique id for the table
     * @param \mod_jazzquiz\jazzquiz $rtq
     * @param \moodle_url $pageurl
     */
    public function __construct($uniqueid, $rtq, $pageurl)
    {
        $this->rtq = $rtq;
        $this->baseurl = $pageurl;
        parent::__construct($uniqueid);
    }

    /**
     * Setup the table, i.e. table headers
     *
     */
    public function setup()
    {
        // Set var for is downloading
        $isdownloading = $this->is_downloading();

        $this->set_attribute('cellspacing', '0');

        $columns = [
            'session' => get_string('session_name', 'jazzquiz'),
            'timestart' => get_string('started_on', 'jazzquiz'),
            'timefinish' => get_string('time_completed', 'jazzquiz'),
        ];

        if ($this->rtq->group_mode()) {
            $columns['group'] = get_string('group');
        }

        if (!$isdownloading) {
            $columns['attemptview'] = get_string('view_attempt', 'jazzquiz');
        }

        $this->define_columns(array_keys($columns));
        $this->define_headers(array_values($columns));

        $this->sortable(false);
        $this->collapsible(false);

        $this->column_class('session', 'bold');

        $this->set_attribute('cellspacing', '0');
        $this->set_attribute('cellpadding', '2');
        $this->set_attribute('id', 'attempts');
        $this->set_attribute('class', 'generaltable generalbox');
        $this->set_attribute('align', 'center');

        parent::setup();
    }


    /**
     * Sets the data to the table
     *
     */
    public function set_data()
    {
        global $OUTPUT;

        $table_data = $this->get_data();

        foreach ($table_data as $item) {

            $row = [];

            $row[] = $item->session_name;
            $row[] = date('m-d-Y H:i:s', $item->timestart);
            $row[] = date('m-d-Y H:i:s', $item->timefinish);

            if ($this->rtq->group_mode()) {
                $row[] = $item->group;
            }

            // Add in controls column

            // View attempt
            $view_attempt_url = new \moodle_url('/mod/jazzquiz/viewquizattempt.php');
            $view_attempt_url->param('quizid', $this->rtq->getRTQ()->id);
            $view_attempt_url->param('sessionid', $item->sessionid);
            $view_attempt_url->param('attemptid', $item->attemptid);

            $view_attempt_pix = new \pix_icon('t/preview', 'preview');
            $popup = new \popup_action('click', $view_attempt_url, 'viewquizattempt');

            $action_link = new \action_link($view_attempt_url, '', $popup, [
                'target' => '_blank'
            ], $view_attempt_pix);

            $row[] = $OUTPUT->render($action_link);

            $this->add_data($row);
        }

    }

    /**
     * Gets the data for the table
     *
     * @return array $data The array of data to show
     */
    protected function get_data()
    {
        global $USER;

        $data = [];

        $sessions = $this->rtq->get_sessions();

        foreach ($sessions as $session) {
            /** @var \mod_jazzquiz\jazzquiz_session $session */
            $sessionattempts = $session->getall_attempts(false, 'closed', $USER->id);

            foreach ($sessionattempts as $sattempt) {
                $ditem = new \stdClass();
                $ditem->attemptid = $sattempt->id;
                $ditem->sessionid = $sattempt->sessionid;
                $ditem->session_name = $session->get_session()->name;
                if ($this->rtq->group_mode()) {
                    $ditem->group = $this->rtq->get_groupmanager()->get_group_name($sattempt->forgroupid);
                }
                $ditem->timestart = $sattempt->timestart;
                $ditem->timefinish = $sattempt->timefinish;
                $data[$sattempt->id] = $ditem;
            }
        }

        return $data;
    }

}