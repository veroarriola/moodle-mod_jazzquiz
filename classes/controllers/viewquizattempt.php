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
 * view quiz attempt controller
 *
 * @package     mod_jazzquiz
 * @author      John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright   2014 University of Wisconsin - Madison
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class viewquizattempt
{

    /** @var \mod_jazzquiz\jazzquiz Realtime quiz class */
    protected $RTQ;

    /** @var \mod_jazzquiz\jazzquiz_session $session The session class for the jazzquiz view */
    protected $session;

    /** @var \moodle_url $pageurl The page url to base other calls on */
    protected $pageurl;

    /** @var array $this ->pagevars An array of page options for the page load */
    protected $pagevars;

    /**
     * set up the class for the view page
     *
     * @param string $baseurl the base url of the page
     */
    public function setup_page($baseurl)
    {
        global $PAGE, $DB;

        $this->pagevars = [];

        $this->pageurl = new \moodle_url($baseurl);
        $this->pageurl->remove_all_params();

        $id = optional_param('id', false, PARAM_INT);
        $quizid = optional_param('quizid', false, PARAM_INT);

        // get necessary records from the DB
        if ($id) {
            $cm = get_coursemodule_from_id('jazzquiz', $id, 0, false, MUST_EXIST);
            $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
            $quiz = $DB->get_record('jazzquiz', ['id' => $cm->instance], '*', MUST_EXIST);
        } else {
            $quiz = $DB->get_record('jazzquiz', ['id' => $quizid], '*', MUST_EXIST);
            $course = $DB->get_record('course', ['id' => $quiz->course], '*', MUST_EXIST);
            $cm = get_coursemodule_from_instance('jazzquiz', $quiz->id, $course->id, false, MUST_EXIST);
        }

        $this->get_parameters(); // get the rest of the parameters and set them in the class


        require_login($course->id, false, $cm);


        $this->pageurl->param('id', $cm->id);
        $this->pageurl->param('quizid', $quiz->id);
        $this->pageurl->params($this->pagevars); // add the page vars variable to the url
        $this->pagevars['pageurl'] = $this->pageurl;

        $this->RTQ = new \mod_jazzquiz\jazzquiz($cm, $course, $quiz, $this->pageurl, $this->pagevars);

        $this->RTQ->require_capability('mod/jazzquiz:viewownattempts');

        $PAGE->set_pagelayout('popup');
        $PAGE->set_context($this->RTQ->getContext());
        $PAGE->set_title(strip_tags($course->shortname . ': ' . get_string("modulename", "jazzquiz") . ': ' .
            format_string($quiz->name, true)));
        $PAGE->set_heading($course->fullname);
        $PAGE->set_url($this->pageurl);
    }


    /**
     * handle the attempt action
     *
     */
    public function handle_request()
    {
        global $USER;

        // Show the attempt

        $session = $this->RTQ->get_session($this->pagevars['sessionid']);
        $attempt = $session->get_user_attempt($this->pagevars['attemptid']);

        $hascapability = true;

        if (!$this->RTQ->has_capability('mod/jazzquiz:seeresponses')) {

            // If the current user doesn't have the ability to see responses (or all responses)
            // check that the current one is theirs

            if ($attempt->userid != $USER->id) { // first check if attempts userid and current userid match

                // if not, next check group settings if we're in group mode
                if ($this->RTQ->group_mode()) {

                    // get user groups and check if the forgroupid is in one of them
                    $usergroups = $this->RTQ->get_groupmanager()->get_user_groups();
                    $usergroupids = array_keys($usergroups);
                    if (!in_array($attempt->forgroupid, $usergroupids)) {
                        $this->RTQ->get_renderer()->render_popup_error(get_string('invalid_attempt_access', 'jazzquiz'));
                        $hascapability = false;
                    }
                } else {
                    $this->RTQ->get_renderer()->render_popup_error(get_string('invalid_attempt_access', 'jazzquiz'));
                    $hascapability = false;
                }
            }
        }

        if ($hascapability) {

            $params = [
                'relateduserid' => $attempt->userid,
                'objectid' => $attempt->id,
                'context' => $this->RTQ->getContext(),
                'other' => [
                    'jazzquizid' => $this->RTQ->getRTQ()->id,
                    'sessionid' => $attempt->sessionid
                ]
            ];

            if ($attempt->userid < 0) {
                $params['relateduserid'] = 0;
            }

            $event = \mod_jazzquiz\event\attempt_viewed::create($params);
            $event->add_record_snapshot('jazzquiz_attempts', $attempt->get_attempt());
            $event->trigger();

            $this->RTQ->get_renderer()->render_attempt($attempt, $session);
        }


    }

    /**
     * Gets other parameters and adding them to the pagevars array
     *
     */
    public function get_parameters()
    {

        $this->pagevars['action'] = optional_param('action', '', PARAM_ALPHAEXT);
        $this->pagevars['attemptid'] = required_param('attemptid', PARAM_INT);
        $this->pagevars['sessionid'] = required_param('sessionid', PARAM_INT);
        $this->pagevars['slot'] = optional_param('slot', '', PARAM_INT);

    }


}

