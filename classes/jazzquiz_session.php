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
 * @package     mod_jazzquiz
 * @author      Sebastian S. Gundersen <sebastsg@stud.ntnu.no>
 * @copyright   2014 University of Wisconsin - Madison
 * @copyright   2018 NTNU
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jazzquiz_session {
    /** @var jazzquiz $jazzquiz */
    public $jazzquiz;

    /** @var \stdClass $data The jazzquiz_session database table row */
    public $data;

    /** @var jazzquiz_attempt[] */
    public $attempts;

    /** @var jazzquiz_attempt $open_attempt The current open attempt */
    public $open_attempt;

    /** @var \stdClass[] jazzquiz_session_questions */
    public $questions;

    /**
     * @param jazzquiz $jazzquiz
     * @param \stdClass $data
     */
    public function __construct($jazzquiz, $data = null) {
        global $DB;
        $this->jazzquiz = $jazzquiz;
        if (!empty($data)) {
            $this->data = $data;
        } else {
            // Next attempt to get a "current" session for this quiz
            // Returns false if no record is found
            $this->data = $DB->get_record('jazzquiz_sessions', [
                'jazzquizid' => $this->jazzquiz->data->id,
                'sessionopen' => 1
            ]);
        }
        $this->questions = $DB->get_records('jazzquiz_session_questions', ['sessionid' => $this->data->id], 'slot');
        foreach ($this->questions as $question) {
            unset($this->questions[$question->id]);
            $this->questions[$question->slot] = $question;
        }
    }

    // TODO: Temporary function. Should be removed at a later time.
    public function get_question_type_by_slot($slot) {
        global $DB;
        $id = $this->questions[$slot]->questionid;
        $question = $DB->get_record('question', ['id' => $id], 'qtype');
        return $question->qtype;
    }

    /**
     * Creates a new session
     * @param \stdClass $data The data object from moodle form
     * @return bool returns true/false on success or failure
     */
    public function create_session($data) {
        global $DB;

        $new_session = new \stdClass();
        $new_session->name = $data->session_name;
        $new_session->jazzquizid = $this->jazzquiz->data->id;
        $new_session->sessionopen = 1;
        $new_session->status = 'notrunning';
        $new_session->anonymize_responses = true;
        $new_session->fully_anonymize = false;
        $new_session->showfeedback = false;
        $new_session->created = time();

        try {
            $new_session_id = $DB->insert_record('jazzquiz_sessions', $new_session);
        } catch (\Exception $e) {
            return false;
        }

        $new_session->id = $new_session_id;
        $this->data = $new_session;
        return true;
    }

    /**
     * Saves the session object to the database
     * @return bool
     */
    public function save() {
        global $DB;
        // Update if we already have a session, otherwise create it.
        if (isset($this->data->id)) {
            try {
                $DB->update_record('jazzquiz_sessions', $this->data);
            } catch (\Exception $e) {
                return false;
            }
        } else {
            try {
                $this->data->id = $DB->insert_record('jazzquiz_sessions', $this->data);
            } catch (\Exception $e) {
                return false;
            }
        }
        return true;
    }

    /**
     * Deletes the specified session, as well as the attempts.
     * @param int $session_id
     * @return bool
     */
    public static function delete($session_id) {
        global $DB;
        // Delete all attempt quba ids, then all JazzQuiz attempts, and then finally itself
        $quba_condition = new \qubaid_join('{jazzquiz_attempts} jqa', 'jqa.questionengid', 'jqa.sessionid = :sessionid', [
            'sessionid' => $session_id
        ]);
        \question_engine::delete_questions_usage_by_activities($quba_condition);
        $DB->delete_records('jazzquiz_attempts', ['sessionid' => $session_id]);
        $DB->delete_records('jazzquiz_session_questions', ['sessionid' => $session_id]);
        $DB->delete_records('jazzquiz_votes', ['sessionid' => $session_id]);
        $DB->delete_records('jazzquiz_sessions', ['id' => $session_id]);
        return true;
    }

    /**
     * Ends this session.
     * @return bool Whether or not this was successful
     */
    public function end_session() {
        // Clear and reset properties on the session
        $this->data->status = 'notrunning';
        $this->data->sessionopen = 0;
        $this->data->currentquestiontime = null;
        $this->data->nextstarttime = null;

        // Get all attempts and close them
        $attempts = $this->get_all_attempts(true, 'open');
        foreach ($attempts as $attempt) {
            $attempt->close_attempt($this->jazzquiz);
        }

        // Save the session as closed
        return $this->save();
    }

    /**
     * Tells the session to go to the specified question number
     * That jazzquiz_question is then returned
     * @param int $question_id (from question bank)
     * @param int $question_time in seconds ("<0" => no time, "0" => default)
     * @return mixed[] $success, $question_time (if parameter was 0, this question time will be the default)
     */
    public function start_question($question_id, $question_time) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        if ($question_time === 0) {
            $question_time = $this->jazzquiz->data->defaultquestiontime;
        }

        $session_question = new \stdClass();
        $session_question->sessionid = $this->data->id;
        $session_question->questionid = $question_id;
        $session_question->questiontime = $question_time;
        $session_question->slot = count($DB->get_records('jazzquiz_session_questions', ['sessionid' => $this->data->id])) + 1;
        $session_question->id = $DB->insert_record('jazzquiz_session_questions', $session_question);
        $this->questions[$session_question->slot] = $session_question;

        $attempts = $this->get_all_attempts(true);
        foreach ($attempts as &$attempt) {
            $attempt->create_missing_attempts($this);
        }

        $this->data->currentquestiontime = $question_time;
        $this->data->nextstarttime = time() + $this->jazzquiz->data->waitforquestiontime;
        $this->save();

        $transaction->allow_commit();

        return [true, $question_time];
    }

    /**
     * Gets the results of the current question as an array
     * @param int $slot
     * @return array
     */
    public function get_question_results_list($slot) {
        $attempts = $this->get_all_attempts(false, 'all');
        $responses = [];

        foreach ($attempts as $attempt) {
            if ($attempt->data->responded != 1) {
                continue;
            }
            $attempt_responses = $attempt->get_response_data($slot);
            $responses = array_merge($responses, $attempt_responses);
        }

        // TODO: Remove this and update the JavaScript instead. The 'response' key is kinda useless.
        foreach ($responses as &$response) {
            $response = ['response' => $response];
        }

        return [
            'responses' => $responses,
            'student_count' => count($attempts)
        ];
    }

    /**
     * Gets the users who have responded to the current question as an array
     * @param int $slot
     * @param bool $open
     * @return int[] of user IDs
     */
    public function get_responded_list($slot, $open) {
        $attempts = $this->get_all_attempts(false, $open);
        $responded = [];
        foreach ($attempts as $attempt) {
            if ($attempt->data->responded != 1) {
                continue;
            }
            $has_responded = $attempt->has_responded($slot);
            if ($has_responded) {
                $responded[] = $attempt->data->userid;
            }
        }
        return $responded;
    }

    public function get_student_count() {
        return count($this->get_all_attempts(false, 'open'));
    }

    /**
     * @return string
     */
    public function get_question_right_response() {
        // Just use the instructor's question attempt to re-render the question with the right response
        $attempt = $this->open_attempt;
        $quba = $attempt->quba;
        $slot = count($this->questions);
        $correct_response = $quba->get_correct_response($slot);
        if (is_null($correct_response)) {
            return 'No correct response';
        }
        $quba->process_action($slot, $correct_response);
        $attempt->save();
        $review_options = new \stdClass();
        $review_options->rightanswer = 1;
        $review_options->correctness = 1;
        $review_options->specificfeedback = 1;
        $review_options->generalfeedback = 1;
        return $attempt->render_question($slot, true, $review_options);
    }

    /**
     * Loads/initializes attempts
     * @param int $preview Whether or not to initialize an attempt as a preview attempt
     * @return bool Returns bool depending on whether or not successful
     */
    public function init_attempts($preview = 0) {
        if (empty($this->data)) {
            return false;
        }
        $attempt = $this->get_open_attempt_for_current_user();
        if (!$attempt) {
            $attempt = new jazzquiz_attempt($this->jazzquiz);
            $attempt->data->sessionid = $this->data->id;
            $attempt->data->userid = $this->get_current_userid();
            $attempt->data->attemptnum = count($this->attempts) + 1;
            $attempt->data->status = jazzquiz_attempt::NOTSTARTED;
            $attempt->data->preview = $preview;
            $attempt->data->timemodified = time();
            $attempt->data->timestart = time();
            $attempt->data->responded = null;
            $attempt->data->responded_count = 0;
            $attempt->data->timefinish = null;
            $attempt->save();
            $event = event\attempt_started::create([
                'objectid' => $attempt->data->id,
                'relateduserid' => $attempt->data->userid,
                'courseid' => $this->jazzquiz->course->id,
                'context' => $this->jazzquiz->context
            ]);
            $event->add_record_snapshot('jazzquiz', $this->jazzquiz->data);
            $event->add_record_snapshot('jazzquiz_attempts', $attempt->data);
            $event->trigger();
        } else {
            // Check the preview field on the attempt to see if it's in line with the value passed
            // If not set it to be correct
            if ($attempt->data->preview != $preview) {
                $attempt->data->preview = $preview;
                $attempt->save();
            }
        }
        $attempt->create_missing_attempts($this);
        $this->open_attempt = $attempt;
        return true;
    }

    /**
     * With anonymisation off, just returns the real user id.
     * With anonymisation on, returns a random, negative user id instead.
     * @return int
     */
    public function get_current_userid() {
        global $USER;
        if (!$this->data->fully_anonymize) {
            return $USER->id;
        }
        if ($this->jazzquiz->is_instructor()) {
            return $USER->id; // Do not anonymize the instructors.
        }

        // Full Anonymisation is on, so generate a random ID and store it in the USER variable (kept until the user logs out).
        if (empty($USER->mod_jazzquiz_anon_userid)) {
            $USER->mod_jazzquiz_anon_userid = -mt_rand(100000, mt_getrandmax());
        }

        return $USER->mod_jazzquiz_anon_userid;
    }

    /**
     * Get the users who have attempted this session
     *
     * @return array returns an array of user IDs that have attempted this session
     */
    public function get_session_users() {
        global $DB;
        if (empty($this->data)) {
            return [];
        }
        $sql = 'SELECT DISTINCT userid FROM {jazzquiz_attempts} WHERE sessionid = :sessionid';
        return $DB->get_records_sql($sql, ['sessionid' => $this->data->id]);
    }

    /**
     * Get the specific attempt from the DB
     * @param int $attempt_id
     * @return jazzquiz_attempt
     */
    public function get_user_attempt($attempt_id) {
        global $DB;
        if (empty($this->data)) {
            return null;
        }
        $attempt = $DB->get_record('jazzquiz_attempts', ['id' => $attempt_id]);
        return new jazzquiz_attempt($this->jazzquiz, $attempt, $this->jazzquiz->context);
    }

    /**
     * Get the current attempt for the current user, if there is one open.  If there is no open attempt
     * for the current user, false is returned
     *
     * @return jazzquiz_attempt|bool Returns the open attempt or false if there is none
     */
    public function get_open_attempt_for_current_user() {
        // Use the get_all_attempts with the specified options
        // Skip checking for groups since we only want to initialize attempts for the actual current user
        $this->attempts = $this->get_all_attempts(true, 'all', $this->get_current_userid());

        // Go through each attempt and see if any are open.  if not, create a new one.
        $open_attempt = false;
        foreach ($this->attempts as $attempt) {
            if ($attempt->get_status() == 'inprogress' || $attempt->get_status() == 'notstarted') {
                $open_attempt = $attempt;
            }
        }
        return $open_attempt;
    }

    /**
     *
     * @param bool $include_previews Whether or not to include the preview attempts
     * @param string $open Whether or not to get open attempts.  'all' means both, otherwise 'open' means open attempts,
     *                      'closed' means closed attempts
     * @param int $user_id If specified will get the user's attempts
     *
     * @return jazzquiz_attempt[]
     */
    public function get_all_attempts($include_previews, $open = 'all', $user_id = null) {
        global $DB;

        if (empty($this->data)) {
            // If there is no session, return empty
            return [];
        }

        $sql_params = [];
        $where = [];

        // Add conditions
        $where[] = 'sessionid = ?';
        $sql_params[] = $this->data->id;

        if (!$include_previews) {
            $where[] = 'preview = ?';
            $sql_params[] = '0';
        }

        switch ($open) {
            case 'open':
                $where[] = 'status = ?';
                $sql_params[] = jazzquiz_attempt::INPROGRESS;
                break;
            case 'closed':
                $where[] = 'status = ?';
                $sql_params[] = jazzquiz_attempt::FINISHED;
                break;
            default:
                // add no condition for status when 'all' or something other than open/closed
        }

        if (!is_null($user_id)) {
            $where[] = 'userid = ?';
            $sql_params[] = $user_id;
        }

        $where_string = implode(' AND ', $where);
        $sql = "SELECT * FROM {jazzquiz_attempts} WHERE $where_string";
        $db_attempts = $DB->get_records_sql($sql, $sql_params);

        $attempts = [];
        foreach ($db_attempts as $db_attempt) {
            $attempts[$db_attempt->id] = new jazzquiz_attempt($this->jazzquiz, $db_attempt, $this->jazzquiz->context);
        }
        return $attempts;
    }

}
