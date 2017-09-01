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

namespace mod_jazzquiz;

defined('MOODLE_INTERNAL') || die();

/**
 * A class holder for a jazzquiz session
 *
 * @package     mod_jazzquiz
 * @author      John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright   2014 University of Wisconsin - Madison
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jazzquiz_session
{

    /** @var jazzquiz $rtq jazzquiz object */
    protected $rtq;

    /** @var \stdClass db object for the session */
    protected $session;

    /** @var  array An array of jazzquiz_attempts for the session */
    protected $attempts;

    /** @var jazzquiz_attempt $openAttempt The current open attempt */
    protected $openAttempt;

    /** @var \moodle_url $pageurl */
    protected $pageurl;

    /** @var array */
    protected $pagevars;

    /**
     * Construct a session class
     *
     * @param jazzquiz $rtq
     * @param \moodle_url $pageurl
     * @param array $pagevars
     * @param \stdClass $session (optional) This is optional, and if sent will tell the construct to not load
     *                                      the session based on open sessions for the rtq
     */
    public function __construct($rtq, $pageurl, $pagevars = array(), $session = null)
    {
        global $DB;

        $this->rtq = $rtq;
        $this->pageurl = $pageurl;
        $this->pagevars = $pagevars;

        if (!empty($session)) {
            $this->session = $session;
        } else {
            // Next attempt to get a "current" session for this quiz
            // Returns false if no record is found
            $this->session = $DB->get_record('jazzquiz_sessions', array('jazzquizid' => $this->rtq->getRTQ()->id, 'sessionopen' => 1));
        }
    }


    /**
     * Gets the session var from this class
     *
     * @return \stdClass|false (when there is no open session)
     */
    public function get_session()
    {
        return $this->session;
    }


    /**
     * Creates a new session
     *
     * @param \stdClass $data The data object from moodle form
     *
     * @return bool returns true/false on success or failure
     */
    public function create_session($data)
    {
        global $DB;

        $newSession = new \stdClass();
        $newSession->name = $data->sessionname;
        $newSession->jazzquizid = $this->rtq->getRTQ()->id;
        $newSession->sessionopen = 1;
        $newSession->status = 'notrunning';
        $newSession->anonymize_responses = true; //$data->anonymizeresponses;
        $newSession->fully_anonymize = false; //$data->fullanonymize;
        $newSession->showfeedback = false; //$data->showfeedback;
        $newSession->created = time();

        try {
            $newSessionId = $DB->insert_record('jazzquiz_sessions', $newSession);
        } catch (\Exception $e) {
            return false;
        }

        $newSession->id = $newSessionId;
        $this->session = $newSession;

        return true;
    }

    /**
     *
     *
     * @param string $status
     *
     * @return bool
     */
    public function set_status($status)
    {
        $this->session->status = $status;
        return $this->save_session();
    }


    /**
     * Saves the session object to the db
     *
     * @return bool
     */
    public function save_session()
    {
        global $DB;

        if (isset($this->session->id)) { // update the record
            try {
                $DB->update_record('jazzquiz_sessions', $this->session);
            } catch (\Exception $e) {
                return false; // return false on failure
            }
        } else {
            // insert new record
            try {
                $newId = $DB->insert_record('jazzquiz_sessions', $this->session);
                $this->session->id = $newId;
            } catch (\Exception $e) {
                return false; // return false on failure
            }
        }

        return true; // return true if we get here
    }

    /**
     * Static function to delete a session instance
     *
     * Is static so we don't have to instantiate a session class
     *
     * @param $sessionid
     * @return bool
     */
    public static function delete($sessionid)
    {
        global $DB;

        // delete all attempt qubaids, then all realtime quiz attempts, and then finally itself
        \question_engine::delete_questions_usage_by_activities(new \mod_jazzquiz\utils\qubaids_for_rtq($sessionid));
        $DB->delete_records('jazzquiz_attempts', array('sessionid' => $sessionid));
        $DB->delete_records('jazzquiz_groupattendance', array('sessionid' => $sessionid));
        $DB->delete_records('jazzquiz_sessions', array('id' => $sessionid));

        return true;
    }


    /**
     * Starts the quiz for the session
     *
     * All attempts are already running so basically we get and add the current question
     * to the session and pick a start time so all clients start at the same time
     *
     * @return \mod_jazzquiz\jazzquiz_question
     */
    public function start_quiz()
    {

        $fQuestion = $this->rtq->get_questionmanager()->get_first_question($this->openAttempt);
        if (empty($fQuestion)) {
            return false; // return false for no first question
        }

        $this->set_status('preparing');

        /*$this->session->currentqnum = 1;
        $this->session->nextstarttime = time() + $this->rtq->getRTQ()->waitforquestiontime;
        $this->session->currentquestion = $fQuestion->get_slot();
        if ($fQuestion->getQuestionTime() == 0 && $fQuestion->getNoTime() == 0) {
            $questiontime = $this->rtq->getRTQ()->defaultquestiontime;
        } else if ($fQuestion->getNoTime() == 1) {
            // here we're spoofing a question time of 0.  This is so the javascript recognizes that we don't want a timer
            // as it reads a question time of 0 as no timer
            $questiontime = 0;
        } else {
            $questiontime = $fQuestion->getQuestionTime();
        }

        $this->session->currentquestiontime = $questiontime;*/

        $this->save_session();

        // return the question to respond to the caller
        return $fQuestion;
    }

    /**
     * Ends the quiz and session
     *
     *
     * @return bool Whether or not this was successful
     */
    public function end_session()
    {

        // clear and reset properties on the session
        $this->session->status = 'notrunning';
        $this->session->sessionopen = 0;
        $this->session->currentquestion = null;
        $this->session->currentqnum = null;
        $this->session->currentquestiontime = null;
        $this->session->nextstarttime = null;

        // get all attempts and close them
        $attempts = $this->getall_open_attempts(true);
        foreach ($attempts as $attempt) {
            /** @var \mod_jazzquiz\jazzquiz_attempt $attempt */
            $attempt->close_attempt($this->rtq);
        }
        // save the session as closed
        $this->save_session();

        return true;
    }

    /**
     * The next jazzquiz question instance
     *
     * @return \mod_jazzquiz\jazzquiz_question
     * @throws \Exception Throws exception when invalid question number
     */
    public function next_question()
    {
        $slot = $this->session->currentqnum + 1;
        $question = $this->goto_question($slot);
        if (!$question) {
            throw new \Exception('invalid slot');
        }
        return $question;
    }

    /**
     * Re-poll the "active" question.  really we're just updating times and things to re-poll
     * The question.
     *
     * @return \mod_jazzquiz\jazzquiz_question
     * @throws \Exception Throws exception when invalid question number
     */
    public function repoll_question()
    {
        if (!$question = $this->goto_question($this->session->currentqnum)) {
            throw new \Exception('invalid question number');
        } else {
            return $question;
        }
    }

    /**
     * Tells the session to go to the specified question number
     * That jazzquiz_question is then returned
     *
     * @param int|bool $slot The question number to go to
     * @return \mod_jazzquiz\jazzquiz_question|bool
     */
    public function goto_question($slot = false)
    {
        if ($slot === false) {
            return false;
        }

        $question = $this->rtq->get_questionmanager()->get_question_with_slot($slot, $this->openAttempt);

        $this->session->currentqnum = $slot;
        $this->session->nextstarttime = time() + $this->rtq->getRTQ()->waitforquestiontime;
        $this->session->currentquestion = $question->get_slot();

        if ($question->getQuestionTime() == 0 && $question->getNoTime() == 0) {

            $question_time = $this->rtq->getRTQ()->defaultquestiontime;

        } else if ($question->getNoTime() == 1) {

            // Here we're spoofing a question time of 0.
            // This is so the javascript recognizes that we don't want a timer
            // as it reads a question time of 0 as no timer
            $question_time = 0;

        } else {

            $question_time = $question->getQuestionTime();

        }

        $this->session->currentquestiontime = $question_time;

        $this->save_session();

        // next set all responded to 0 for this question
        $attempts = $this->getall_open_attempts(true);

        foreach ($attempts as $attempt) {
            /** @var \mod_jazzquiz\jazzquiz_attempt $attempt */

            $attempt->responded = 0;
            $attempt->responded_count = 0;
            $attempt->save();
        }

        return $question;
    }

    /**
     * Updates the status of the session
     *
     */
    public function end_question()
    {
        $this->set_status('endquestion');
    }

    /**
     * Gets the results of the current question as an array
     *
     *
     */
    public function get_question_results_list($use_live_filter, $qtype)
    {

        $attempts = $this->getall_open_attempts(false);
        $responses = [];

        foreach ($attempts as $attempt) {
            if ($attempt->responded != 1) {
                continue;
            }
            // $anonymous = ($this->session->anonymize_responses != 0 || $this->session->fully_anonymize != 0);
            $responses[] = $attempt->get_response_data($this->session->currentquestion, $use_live_filter, $qtype);
        }

        return $responses;
    }

    /**
     * Gets the results of the current question
     *
     */
    public function get_question_results()
    {


        // next load all active attempts
        $attempts = $this->getall_open_attempts(false);

        $totalresponsesummary = '';
        $responded = 0;
        foreach ($attempts as $attempt) {
            /** @var \mod_jazzquiz\jazzquiz_attempt $attempt */
            if ($attempt->responded == 1) {
                $responded++;
                $attempt->summarize_response($this->session->currentquestion);
                $anonymous = true;
                if ($this->session->anonymize_responses == 0 && $this->session->fully_anonymize == 0) {
                    $anonymous = false;
                }
                $totalresponsesummary .= $this->rtq->get_renderer()->render_response($attempt, $responded, $anonymous);

            }
        }

        // next allow question modifiers to modify the output
        $currQuestion = $this->rtq->get_questionmanager()->get_question_with_slot($this->session->currentqnum, $this->openAttempt);
        $return = $this->rtq->call_question_modifiers('modify_questionresults_duringquiz', $currQuestion, $currQuestion, $attempts, $totalresponsesummary);

        if (!empty($return)) {
            // we have an updated output
            $totalresponsesummary = $return;
        }

        return $totalresponsesummary;

    }

    /**
     * Builds the content for a quiz box for who hasn't responded.
     *
     * @return string
     */
    public function get_not_responded()
    {
        global $DB;

        // Get all of the open attempts
        $attempts = $this->getall_open_attempts(false);

        $not_responded = [];

        foreach ($attempts as $attempt) {
            /** @var \mod_jazzquiz\jazzquiz_attempt $attempt */
            if ($attempt->responded == 0) {

                if (!is_null($attempt->forgroupid) && $attempt->forgroupid != 0) {

                    // We have a groupid to use instead of the user's name
                    $not_responded[] = $this->rtq->get_groupmanager()->get_group_name($attempt->forgroupid);

                } else {

                    // Get the username
                    if ($user = $DB->get_record('user', [ 'id' => $attempt->userid ])) {

                        // Add to the list
                        $not_responded[] = fullname($user);

                    } else {

                        // This shouldn't happen
                        $not_responded[] = 'undefined user';

                    }

                }
            }
        }

        $anonymous = true;
        if ($this->session->anonymize_responses == 0 && $this->session->fully_anonymize == 0) {
            $anonymous = false;
        }

        $not_responded_box = $this->rtq->get_renderer()->respondedbox($not_responded, count($attempts), $anonymous);

        return $not_responded_box;

    }

    /**
     *
     *
     * @return string
     */
    public function get_question_right_response()
    {

        // just use the instructor's question attempt to re-render the question with the right response

        $attempt = $this->openAttempt;

        $aquba = $attempt->get_quba();

        $correctresponse = $aquba->get_correct_response($this->session->currentquestion);
        if (!is_null($correctresponse)) {
            $aquba->process_action($this->session->currentquestion, $correctresponse);

            $attempt->save();

            $reviewoptions = new \stdClass();
            $reviewoptions->rightanswer = 1;
            $reviewoptions->correctness = 1;
            $reviewoptions->specificfeedback = 1;
            $reviewoptions->generalfeedback = 1;

            return $attempt->render_question($this->session->currentquestion, true, $reviewoptions);
        } else {
            return 'no correct response';
        }
    }


    /**
     * Loads/initializes attempts
     *
     * @param int $preview Whether or not to initialize an attempt as a preview attempt
     * @param int $group The groupid that we want the attempt to be for
     * @param string $groupmembers Comma separated list of groupmembers for this attempt
     * @return bool Returns bool depending on whether or not successful
     *
     * @throws \Exception Throws exception when we can't add group attendance members
     */
    public function init_attempts($preview = 0, $group = null, $groupmembers = null)
    {
        global $DB, $USER;

        if (empty($this->session)) {
            return false;  // return false if there's no session
        }

        $openAttempt = $this->get_open_attempt_for_current_user();

        if ($openAttempt === false) { // create a new attempt

            $attempt = new jazzquiz_attempt($this->rtq->get_questionmanager());
            $attempt->sessionid = $this->session->id;
            $attempt->userid = $this->get_current_userid();
            $attempt->attemptnum = count($this->attempts) + 1;
            $attempt->status = jazzquiz_attempt::NOTSTARTED;
            $attempt->preview = $preview;
            $attempt->timemodified = time();
            $attempt->timestart = time();
            $attempt->responded = null;
            $attempt->responded_count = 0;
            $attempt->timefinish = null;

            // add forgroupid to attempt if we're in group mode
            if ($this->rtq->group_mode()) {
                $attempt->forgroupid = $group;
            } else {
                $attempt->forgroupid = null;
            }

            $attempt->save();
            if (!$this->session->fully_anonymize) {
                // create attempt_created event
                $params = array(
                    'objectid' => $attempt->id,
                    'relateduserid' => $attempt->userid,
                    'courseid' => $this->rtq->getCourse()->id,
                    'context' => $this->rtq->getContext()
                );
                $event = \mod_jazzquiz\event\attempt_started::create($params);
                $event->add_record_snapshot('jazzquiz', $this->rtq->getRTQ());
                $event->add_record_snapshot('jazzquiz_attempts', $attempt->get_attempt());
                $event->trigger();
            }

            if ($this->rtq->group_mode() && $this->rtq->getRTQ()->groupattendance == 1) {
                // if we're doing group attendance add group members to group attendance table

                $groupmembers = explode(',', $groupmembers);
                foreach ($groupmembers as $userid) {
                    $gattendance = new \stdClass();
                    $gattendance->jazzquizid = $this->rtq->getRTQ()->id;
                    $gattendance->sessionid = $this->session->id;
                    $gattendance->attemptid = $attempt->id;
                    $gattendance->groupid = $group;
                    $gattendance->userid = $userid;

                    if (!$DB->insert_record('jazzquiz_groupattendance', $gattendance)) {
                        throw new \Exception('cant and groups for group attendance');
                    }
                }
            }

            $this->openAttempt = $attempt;

        } else {
            // check the preview field on the attempt to see if it's in line with the value passed
            // if not set it to be correct
            if ($openAttempt->preview != $preview) {
                $openAttempt->preview = $preview;
                $openAttempt->save();
            }

            $this->openAttempt = $openAttempt;
        }

        return true; // return true if we get to here
    }

    /**
     * With anonymisation off, just returns the real userid.
     * With anonymisation on, returns a random, negative userid instead.
     * @return int
     */
    public function get_current_userid()
    {
        global $USER;
        if (!$this->session->fully_anonymize) {
            return $USER->id;
        }
        if ($this->rtq->is_instructor()) {
            return $USER->id; // Do not anonymize the instructors.
        }

        // Full Anonymisation is on, so generate a random ID and store it in the USER variable (kept until the user logs out).
        if (empty($USER->mod_jazzquiz_anon_userid)) {
            $USER->mod_jazzquiz_anon_userid = -mt_rand(100000, mt_getrandmax());
        }

        return $USER->mod_jazzquiz_anon_userid;
    }


    /** Attempts functions */

    /**
     * get the open attempt for the user
     *
     * @return jazzquiz_attempt
     */
    public function get_open_attempt()
    {
        return $this->openAttempt;
    }

    /**
     * Sets the open attempt
     * Normally this is only called on the quizdata callback because
     * validation of the attempt occurs before the openAttempt is set for the session
     *
     * @param \mod_jazzquiz\jazzquiz_attempt $attempt
     */
    public function set_open_attempt($attempt)
    {
        $this->openAttempt = $attempt;

    }

    /**
     * Check attempts for the user's groups
     *
     * @return array|bool Returns an array of valid groups to make an attempt for, if empty,
     *                    there are no valid groups to attempt for.
     *                    Returns bool false when there is no session
     */
    public function check_attempt_for_group()
    {
        global $USER, $DB;

        if (empty($this->session)) { // if there is no current session, return false as there will be no attempt
            return false;
        }

        $groups = $this->rtq->get_groupmanager()->get_user_groups_name_array();
        $groups = array_keys($groups);

        $validgroups = array();

        // we need to loop through the groups in case a user is in multiple,
        // and then check if there is a possibility for them to create an attempt for that user
        foreach ($groups as $group) {

            list($sql, $params) = $DB->get_in_or_equal(array($group));
            $query = 'SELECT * FROM {jazzquiz_attempts} WHERE forgroupid ' . $sql .
                ' AND status = ? AND sessionid = ? AND userid != ?';
            $params[] = \mod_jazzquiz\jazzquiz_attempt::INPROGRESS;
            $params[] = $this->session->id;
            $params[] = $USER->id;
            $recs = $DB->get_records_sql($query, $params);
            if (count($recs) == 0) {
                $validgroups[] = $group;
            }
        }

        return $validgroups;

    }

    /**
     * This function is similar to the function above in that we're trying to determine valid groups for the current user
     * but this function basically checks if there's an attempt for the group or not.  and if there is, is the current user
     * the person attempting the quiz.  if so, they can take the quiz, if they are not, then they cannot take the quiz
     *
     * @param int $groupid
     * @return bool
     * @throws \Exception Throws exception when there are more than one attempt for the group.
     *                    This is so it's easier to catch this bug if it does happen in another case
     */
    public function can_take_quiz_for_group($groupid)
    {
        global $DB;

        // return false if there is no session
        if (empty($this->session)) {
            return false;
        }

        // get open attempts for the groupid passed, and determine if the current user can make/resume an attempt for it
        $query = 'SELECT * FROM {jazzquiz_attempts} WHERE forgroupid = ? AND status = ? AND sessionid = ?';
        $params = array();
        $params[] = $groupid;
        $params[] = \mod_jazzquiz\jazzquiz_attempt::INPROGRESS;
        $params[] = $this->session->id;
        $attempts = $DB->get_records_sql($query, $params);

        if (!empty($attempts)) {

            if (count($attempts) > 1) {
                throw new \Exception('Invalid number of attempts created for this group');
            }

            // if there is an open attempt for the group, see if it's for the current user, if it is, they can take the quiz
            // if they are not the same user then they cannot take the quiz
            $attempt = current($attempts);
            if ($this->get_current_userid() != $attempt->userid) {
                return false;
            } else {
                return true;
            }

        } else { // if no attempts, then they can create an attempt for the group
            return true;
        }
    }


    /**
     * Get the users who have attempted this session
     *
     * @return array returns an array of userids that have attempted this session
     */
    public function get_session_users()
    {
        global $DB;

        if (empty($this->session)) {
            return array();  // if there is no session return empty array
        }

        $sql = 'SELECT DISTINCT userid FROM {jazzquiz_attempts} WHERE sessionid = :sessionid';

        return $DB->get_records_sql($sql, array('sessionid' => $this->session->id));
    }

    /**
     * gets a specific attempt from the DB
     *
     * @param int $attemptid
     *
     * @return \mod_jazzquiz\jazzquiz_attempt
     */
    public function get_user_attempt($attemptid)
    {
        global $DB;

        if (empty($this->session)) {
            // if there is no session set, return empty
            return null;
        }

        $dbattempt = $DB->get_record('jazzquiz_attempts', array('id' => $attemptid));

        return new \mod_jazzquiz\jazzquiz_attempt($this->rtq->get_questionmanager(), $dbattempt, $this->rtq->getContext());
    }

    /**
     * Get the current attempt for the current user, if there is one open.  If there is no open attempt
     * for the current user, false is returned
     *
     * @return \mod_jazzquiz\jazzquiz_attempt|bool Returns the open attempt or false if there is none
     */
    public function get_open_attempt_for_current_user()
    {

        // use the getall attempts with the specified options
        // skip checking for groups since we only want to initialize attempts for the actual current user
        $this->attempts = $this->getall_attempts(true, 'all', $this->get_current_userid(), true);

        // go through each attempt and see if any are open.  if not, create a new one.
        $openAttempt = false;
        foreach ($this->attempts as $attempt) {

            /** @var jazzquiz_attempt $attempt doc comment for type hinting */
            if ($attempt->getStatus() == 'inprogress' || $attempt->getStatus() == 'notstarted') {
                $openAttempt = $attempt;
            }
        }

        return $openAttempt;

    }

    /**
     * Gets all of the open attempts for the session
     *
     * @param bool $includepreviews Whether or not to include the preview attempts
     * @return array
     */
    public function getall_open_attempts($includepreviews)
    {
        global $DB;

        $attempts = $this->getall_attempts($includepreviews, 'open');

        return $attempts;
    }

    /**
     *
     *
     * @param bool $includepreviews Whether or not to include the preview attempts
     * @param string $open Whether or not to get open attempts.  'all' means both, otherwise 'open' means open attempts,
     *                      'closed' means closed attempts
     * @param int $userid If specified will get the user's attempts
     * @param bool $skipgroups If set to true, we will not also look for attempts for the user's groups if the rtq is in group mode
     *
     * @return array
     */
    public function getall_attempts($includepreviews, $open = 'all', $userid = null, $skipgroups = false)
    {
        global $DB;

        if (empty($this->session)) {
            // If there is no session, return empty
            return [];
        }

        $sqlparams = [];
        $where = [];

        // Add conditions
        $where[] = 'sessionid = ?';
        $sqlparams[] = $this->session->id;

        if (!$includepreviews) {
            $where[] = 'preview = ?';
            $sqlparams[] = '0';
        }

        switch ($open) {
            case 'open':
                $where[] = 'status = ?';
                $sqlparams[] = jazzquiz_attempt::INPROGRESS;
                break;
            case 'closed':
                $where[] = 'status = ?';
                $sqlparams[] = jazzquiz_attempt::FINISHED;
                break;
            default:
                // add no condition for status when 'all' or something other than open/closed
        }

        if (!is_null($userid)) {

            if ($skipgroups) {
                // if we don't want to find user attempts based on groups just get attempts for specified user
                // usages include "get user attempts", and the grading "get user attempts"
                $where[] = 'userid = ?';
                $sqlparams[] = $userid;
            } else {
                if ($this->rtq->group_mode()) {
                    $usergroups = $this->rtq->get_groupmanager()->get_user_groups($userid);

                    if (!empty($usergroups)) {

                        $selectgroups = [];
                        foreach ($usergroups as $ugroup) {
                            $selectgroups[] = $ugroup->id;
                        }
                        list($insql, $gparams) = $DB->get_in_or_equal($selectgroups);

                        $where[] = 'forgroupid ' . $insql;
                        $sqlparams = array_merge($sqlparams, $gparams);

                    } else { // continue selecting for user query if no groups
                        $where[] = 'userid = ?';
                        $sqlparams[] = $userid;
                    }

                } else { // otherwise keep going the normal way
                    $where[] = 'userid = ?';
                    $sqlparams[] = $userid;
                }
            }
        }

        $wherestring = implode(' AND ', $where);

        $sql = "SELECT * FROM {jazzquiz_attempts} WHERE $wherestring";

        $dbattempts = $DB->get_records_sql($sql, $sqlparams);

        $attempts = [];
        foreach ($dbattempts as $dbattempt) {
            $attempts[$dbattempt->id] = new jazzquiz_attempt($this->rtq->get_questionmanager(), $dbattempt, $this->rtq->getContext());
        }

        return $attempts;

    }

}

