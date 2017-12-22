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
global $CFG;
require_once($CFG->libdir . '/tablelib.php');

/**
 * Table lib subclass for showing a session attempts
 *
 * @package     mod_jazzquiz
 * @author      John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright   2014 University of Wisconsin - Madison
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sessionattempts extends \flexible_table implements \renderable
{
    /** @var \mod_jazzquiz\jazzquiz $jazzquiz */
    protected $jazzquiz;

    /** @var \mod_jazzquiz\jazzquiz_session $session The session we're showing attempts for */
    protected $session;

    /**
     * Contstruct this table class
     *
     * @param string $uniqueid The unique id for the table
     * @param \mod_jazzquiz\jazzquiz $jazzquiz
     * @param \mod_jazzquiz\jazzquiz_session $session
     * @param \moodle_url $pageurl
     */
    public function __construct($uniqueid, $jazzquiz, $session, $pageurl)
    {
        $this->jazzquiz = $jazzquiz;
        $this->session = $session;
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
            'fullname' => get_string('name'),
            'attempt' => get_string('attempt_number', 'jazzquiz'),
            'preview' => get_string('preview'),
            'timestart' => get_string('started_on', 'jazzquiz'),
            'timefinish' => get_string('time_completed', 'jazzquiz'),
            'timemodified' => get_string('time_modified', 'jazzquiz'),
            'status' => get_string('status'),
        ];

        if (!$isdownloading) {
            $columns['edit'] = get_string('response_attempt_controls', 'jazzquiz');
        }

        $this->define_columns(array_keys($columns));
        $this->define_headers(array_values($columns));

        $this->collapsible(true);

        $this->column_class('fullname', 'bold');

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
        global $CFG, $OUTPUT;

        $download = $this->is_downloading();
        $tabledata = $this->get_data();

        foreach ($tabledata as $item) {
            $row = [];
            if (!$download) {
                if ($item->userid > 0) {
                    $user_link = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $item->userid . '&amp;course=' . $this->jazzquiz->course->id . '">';
                    $user_link_end = '</a>';
                } else {
                    $user_link = '';
                    $user_link_end = '';
                }
                $user_link .= $item->username . $user_link_end;
                $row[] = $user_link;
            } else {
                $row[] = $item->username;
            }

            $row[] = $item->attemptno;
            $row[] = $item->preview;
            $row[] = date('m-d-Y H:i:s', $item->timestart);
            if (!empty($item->timefinish)) {
                $row[] = date('m-d-Y H:i:s', $item->timefinish);
            } else {
                $row[] = ' - ';
            }
            $row[] = date('m-d-Y H:i:s', $item->timemodified);
            $row[] = $item->status;

            // Add in controls column

            // View attempt
            $view_attempt_url = new \moodle_url('/mod/jazzquiz/viewquizattempt.php');
            $view_attempt_url->param('quizid', $this->jazzquiz->data->id);
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
     * @return array $data The array of data to show
     */
    protected function get_data()
    {
        global $DB;

        $data = [];
        $attempts = $this->session->get_all_attempts(true);
        $user_ids = [];
        foreach ($attempts as $attempt) {
            if ($attempt->data->userid > 0) {
                $user_ids[] = $attempt->data->userid;
            }
        }

        // Get user records to get the full name
        if (!empty($user_ids)) {
            list($useridsql, $params) = $DB->get_in_or_equal($user_ids);
            $sql = 'SELECT * FROM {user} WHERE id ' . $useridsql;
            $userrecs = $DB->get_records_sql($sql, $params);
        } else {
            $userrecs = [];
        }

        foreach ($attempts as $attempt) {
            $ditem = new \stdClass();
            $ditem->attemptid = $attempt->data->id;
            $ditem->sessionid = $attempt->data->sessionid;
            if (isset($userrecs[$attempt->data->userid])) {
                $name = fullname($userrecs[$attempt->data->userid]);
                $user_id = $attempt->data->userid;
            } else {
                $name = get_string('anonymoususer', 'mod_jazzquiz');
                $user_id = null;
            }
            $ditem->userid = $user_id;
            $ditem->username = $name;
            $ditem->attemptno = $attempt->data->attemptnum;
            $ditem->preview = $attempt->data->preview;
            $ditem->status = $attempt->get_status();
            $ditem->timestart = $attempt->data->timestart;
            $ditem->timefinish = $attempt->data->timefinish;
            $ditem->timemodified = $attempt->data->timemodified;
            $data[$attempt->data->id] = $ditem;
        }
        return $data;
    }

}

