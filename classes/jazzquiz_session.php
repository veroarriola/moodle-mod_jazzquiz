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

    /** @var jazzquiz_attempt|false $attempt The current user's quiz attempt. False if not loaded. */
    public $attempt;

    /** @var \stdClass[] jazzquiz_session_questions */
    public $questions;

    /**
     * Constructor.
     * @param jazzquiz $jazzquiz
     * @param int $sessionid
     */
    public function __construct($jazzquiz, $sessionid) {
        global $DB;
        $this->jazzquiz = $jazzquiz;
        $this->attempts = [];
        $this->attempt = false;
        $this->questions = [];
        $this->data = $DB->get_record('jazzquiz_sessions', [
            'jazzquizid' => $this->jazzquiz->data->id,
            'id' => $sessionid
        ], '*', MUST_EXIST);
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
     * @param int $sessionid
     * @return bool
     */
    public static function delete($sessionid) {
        global $DB;
        // Delete all attempt quba ids, then all JazzQuiz attempts, and then finally itself.
        $condition = new \qubaid_join('{jazzquiz_attempts} jqa', 'jqa.questionengid', 'jqa.sessionid = :sessionid', [
            'sessionid' => $sessionid
        ]);
        \question_engine::delete_questions_usage_by_activities($condition);
        $DB->delete_records('jazzquiz_attempts', ['sessionid' => $sessionid]);
        $DB->delete_records('jazzquiz_session_questions', ['sessionid' => $sessionid]);
        $DB->delete_records('jazzquiz_votes', ['sessionid' => $sessionid]);
        $DB->delete_records('jazzquiz_sessions', ['id' => $sessionid]);
        return true;
    }

    /**
     * Closes the attempts and ends the session.
     * @return bool Whether or not this was successful
     */
    public function end_session() {
        $this->data->status = 'notrunning';
        $this->data->sessionopen = 0;
        $this->data->currentquestiontime = null;
        $this->data->nextstarttime = null;
        foreach ($this->attempts as $attempt) {
            $attempt->close_attempt($this->jazzquiz);
        }
        return $this->save();
    }

    /**
     * Merge responses with 'from' to 'into'
     * @param int $slot Session question slot
     * @param string $from Original response text
     * @param string $into Merged response text
     */
    public function merge_responses($slot, $from, $into) {
        global $DB;
        $merge = new \stdClass();
        $merge->sessionid = $this->data->id;
        $merge->slot = $slot;
        $merge->ordernum = count($DB->get_records('jazzquiz_merges', [
            'sessionid' => $this->data->id,
            'slot' => $slot
        ]));
        $merge->original = $from;
        $merge->merged = $into;
        $DB->insert_record('jazzquiz_merges', $merge);
    }

    /**
     * Undo the last merge of the specified question.
     * @param int $slot Session question slot
     */
    public function undo_merge($slot) {
        global $DB;
        $merge = $DB->get_records('jazzquiz_merges', [
            'sessionid' => $this->data->id,
            'slot' => $slot
        ], 'ordernum desc', '*', 0, 1);
        if (count($merge) === 0) {
            return;
        }
        $merge = reset($merge);
        $DB->delete_records('jazzquiz_merges', ['id' => $merge->id]);
    }

    /**
     * Get the merged responses.
     * @param int $slot Session question slot
     * @param string[]['response'] $responses Original responses
     * @return string[]['response'], int Merged responses and count of merges.
     */
    public function get_merged_responses($slot, $responses) {
        global $DB;
        $merges = $DB->get_records('jazzquiz_merges', [
            'sessionid' => $this->data->id,
            'slot' => $slot
        ]);
        $count = 0;
        foreach ($merges as $merge) {
            foreach ($responses as &$response) {
                if ($merge->original === $response['response']) {
                    $response['response'] = $merge->merged;
                    $count++;
                }
            }
        }
        return [$responses, $count];
    }

    /**
     * Tells the session to go to the specified question number
     * That jazzquiz_question is then returned
     * @param int $questionid (from question bank)
     * @param int $questiontime in seconds ("<0" => no time, "0" => default)
     * @return mixed[] $success, $question_time
     */
    public function start_question($questionid, $questiontime) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        $sessionquestion = new \stdClass();
        $sessionquestion->sessionid = $this->data->id;
        $sessionquestion->questionid = $questionid;
        $sessionquestion->questiontime = $questiontime;
        $sessionquestion->slot = count($DB->get_records('jazzquiz_session_questions', ['sessionid' => $this->data->id])) + 1;
        $sessionquestion->id = $DB->insert_record('jazzquiz_session_questions', $sessionquestion);
        $this->questions[$sessionquestion->slot] = $sessionquestion;

        foreach ($this->attempts as &$attempt) {
            $attempt->create_missing_attempts($this);
            $attempt->save();
        }

        $this->data->currentquestiontime = $questiontime;
        $this->data->nextstarttime = time() + $this->jazzquiz->data->waitforquestiontime;
        $this->save();

        $transaction->allow_commit();

        return [true, $questiontime];
    }

    /**
     * Create a quiz attempt for the specified user.
     * @param int $userid The user to create the attempt for
     */
    public function initialize_attempt($userid) {
        // Check if this user has already joined the quiz.
        foreach ($this->attempts as &$attempt) {
            if ($attempt->data->userid == $userid) {
                $attempt->create_missing_attempts($this);
                $attempt->save();
                $this->attempt = $attempt;
                return;
            }
        }
        // For users who have not yet joined the quiz.
        $this->attempt = new jazzquiz_attempt($this->jazzquiz->context);
        $this->attempt->data->sessionid = $this->data->id;
        $this->attempt->data->userid = $userid;
        $this->attempt->data->status = jazzquiz_attempt::NOTSTARTED;
        $this->attempt->data->timemodified = time();
        $this->attempt->data->timestart = time();
        $this->attempt->data->responded = null;
        $this->attempt->data->responded_count = 0;
        $this->attempt->data->timefinish = null;
        $this->attempt->create_missing_attempts($this);
        $this->attempt->save();

        $event = event\attempt_started::create([
            'objectid' => $this->attempt->data->id,
            'relateduserid' => $this->attempt->data->userid,
            'courseid' => $this->jazzquiz->course->id,
            'context' => $this->jazzquiz->context
        ]);
        $event->add_record_snapshot('jazzquiz', $this->jazzquiz->data);
        $event->add_record_snapshot('jazzquiz_attempts', $this->attempt->data);
        $event->trigger();
    }

    /**
     * Load all the attempts for this session.
     */
    public function load_attempts() {
        global $DB;
        $this->attempts = [];
        $attempts = $DB->get_records('jazzquiz_attempts', ['sessionid' => $this->data->id]);
        foreach ($attempts as $attempt) {
            $this->attempts[$attempt->id] = new jazzquiz_attempt($this->jazzquiz->context, $attempt);
        }
    }

    /**
     * Load the current attempt for the user.
     */
    public function load_attempt() {
        global $DB, $USER;
        $attempt = $DB->get_record('jazzquiz_attempts', [
            'sessionid' => $this->data->id,
            'userid' => $USER->id
        ]);
        if (!$attempt) {
            $this->attempt = false;
            return;
        }
        $this->attempt = new jazzquiz_attempt($this->jazzquiz->context, $attempt);
    }

    /**
     * Load all the session questions.
     */
    public function load_session_questions() {
        global $DB;
        $this->questions = $DB->get_records('jazzquiz_session_questions', [
            'sessionid' => $this->data->id
        ], 'slot');
        foreach ($this->questions as $question) {
            unset($this->questions[$question->id]);
            $this->questions[$question->slot] = $question;
        }
    }

    /**
     * Get the user IDs for who have attempted this session
     * @return int[] user IDs that have attempted this session
     */
    public function get_users() {
        $users = [];
        foreach ($this->attempts as $attempt) {
            // TODO: Eventually remove this status check.
            if ($attempt->data->status == jazzquiz_attempt::PREVIEW) {
                continue;
            }
            $users[] = $attempt->data->userid;
        }
        return $users;
    }

    /**
     * Get the total number of students participating in the quiz.
     * @return int
     */
    public function get_student_count() {
        return count($this->attempts);
    }

    /**
     * Get the correct answer rendered in HTML.
     * @return string HTML
     */
    public function get_question_right_response() {
        // Use the current user's attempt to render the question with the right response.
        $quba = $this->attempt->quba;
        $slot = count($this->questions);
        $correctresponse = $quba->get_correct_response($slot);
        if (is_null($correctresponse)) {
            return 'No correct response';
        }
        $quba->process_action($slot, $correctresponse);
        $this->attempt->save();
        $reviewoptions = new \stdClass();
        $reviewoptions->rightanswer = 1;
        $reviewoptions->correctness = 1;
        $reviewoptions->specificfeedback = 1;
        $reviewoptions->generalfeedback = 1;
        /** @var output\renderer $renderer */
        $renderer = $this->jazzquiz->renderer;
        ob_start();
        $html = $renderer->render_question($this->jazzquiz, $this->attempt->quba, $slot, true, $reviewoptions);
        $htmlechoed = ob_get_clean();
        return $html . $htmlechoed;
    }

    /**
     * Gets the results of the current question as an array
     * @param int $slot
     * @return array
     */
    public function get_question_results_list($slot) {
        $responses = [];
        $responded = 0;
        foreach ($this->attempts as $attempt) {
            if ($attempt->data->responded != 1) {
                continue;
            }
            $attemptresponses = $attempt->get_response_data($slot);
            $responses = array_merge($responses, $attemptresponses);
            $responded++;
        }
        foreach ($responses as &$response) {
            $response = ['response' => $response];
        }
        return [
            'responses' => $responses,
            'responded' => $responded,
            'student_count' => $this->get_student_count()
        ];
    }

    /**
     * Gets the users who have responded to the current question as an array
     * @param int $slot
     * @return int[] of user IDs
     */
    public function get_responded_list($slot) {
        $responded = [];
        foreach ($this->attempts as $attempt) {
            if ($attempt->data->responded != 1) {
                continue;
            }
            if ($attempt->has_responded($slot)) {
                $responded[] = $attempt->data->userid;
            }
        }
        return $responded;
    }

    /**
     * @todo Temporary function. Should be removed at a later time.
     * @param int $slot
     * @return string
     */
    public function get_question_type_by_slot($slot) {
        global $DB;
        $id = $this->questions[$slot]->questionid;
        $question = $DB->get_record('question', ['id' => $id], 'qtype');
        return $question->qtype;
    }

}
