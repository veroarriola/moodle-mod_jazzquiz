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
 * @package   mod_jazzquiz
 * @author    Sebastian S. Gundersen <sebastian@sgundersen.com>
 * @copyright 2014 University of Wisconsin - Madison
 * @copyright 2018 NTNU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jazzquiz {
    /**
     * @var array $review fields Static review fields to add as options
     */
    public static $reviewfields = [
        'attempt' => ['theattempt', 'jazzquiz'],
        'correctness' => ['whethercorrect', 'question'],
        'marks' => ['marks', 'jazzquiz'],
        'specificfeedback' => ['specificfeedback', 'question'],
        'generalfeedback' => ['generalfeedback', 'question'],
        'rightanswer' => ['rightanswer', 'question'],
        'manualcomment' => ['manualcomment', 'jazzquiz']
    ];

    /** @var \stdClass $cm course module */
    public $cm;

    /** @var \stdClass $course */
    public $course;

    /** @var \context_module $context */
    public $context;

    /** @var \plugin_renderer_base|output\renderer $renderer */
    public $renderer;

    /** @var \stdClass $data The jazzquiz database table row */
    public $data;

    /** @var bool $isinstructor */
    protected $isinstructor;

    /** @var jazzquiz_question[] */
    public $questions;

    /**
     * @param int $cmid The course module ID
     * @param string $renderersubtype Renderer sub-type to load if requested
     */
    public function __construct($cmid) {
        global $PAGE, $DB;
        $this->cm = get_coursemodule_from_id('jazzquiz', $cmid, 0, false, MUST_EXIST);

        // TODO: Login requirement must be moved over to caller.
        require_login($this->cm->course, false, $this->cm);

        $this->context = \context_module::instance($cmid);
        $PAGE->set_context($this->context);
        $this->renderer = $PAGE->get_renderer('mod_jazzquiz');

        $this->course = $DB->get_record('course', ['id' => $this->cm->course], '*', MUST_EXIST);
        $this->data = $DB->get_record('jazzquiz', ['id' => $this->cm->instance], '*', MUST_EXIST);
        $this->refresh_questions();
    }

    /**
     * Sets up the display options for the question
     * @param bool $review
     * @param string|\stdClass $reviewoptions
     * @return \question_display_options
     */
    public function get_display_options(bool $review, $reviewoptions) {
        $options = new \question_display_options();
        $options->flags = \question_display_options::HIDDEN;
        $options->context = $this->context;
        $options->marks = \question_display_options::HIDDEN;
        if ($review) {
            // Default display options for review.
            $options->readonly = true;
            $options->hide_all_feedback();
            // Special case for "edit" review options value.
            if ($reviewoptions === 'edit') {
                $options->correctness = \question_display_options::VISIBLE;
                $options->marks = \question_display_options::MARK_AND_MAX;
                $options->feedback = \question_display_options::VISIBLE;
                $options->numpartscorrect = \question_display_options::VISIBLE;
                $options->manualcomment = \question_display_options::EDITABLE;
                $options->generalfeedback = \question_display_options::VISIBLE;
                $options->rightanswer = \question_display_options::VISIBLE;
                $options->history = \question_display_options::VISIBLE;
            } else if ($reviewoptions instanceof \stdClass) {
                foreach (self::$reviewfields as $field => $unused) {
                    if ($reviewoptions->$field == 1) {
                        if ($field == 'specificfeedback') {
                            $field = 'feedback';
                        }
                        if ($field == 'marks') {
                            $options->$field = \question_display_options::MARK_AND_MAX;
                        } else {
                            $options->$field = \question_display_options::VISIBLE;
                        }
                    }
                }
            }
        } else {
            // Default options for running quiz.
            $options->rightanswer = \question_display_options::HIDDEN;
            $options->numpartscorrect = \question_display_options::HIDDEN;
            $options->manualcomment = \question_display_options::HIDDEN;
            $options->manualcommentlink = \question_display_options::HIDDEN;
        }
        return $options;
    }

    /**
     * Get the open session. If none exist, false is returned.
     * @return jazzquiz_session|false
     */
    public function load_open_session() {
        global $DB;
        $sessions = $DB->get_records('jazzquiz_sessions', [
            'jazzquizid' => $this->data->id,
            'sessionopen' => 1
        ], 'id');
        if (count($sessions) === 0) {
            return false;
        }
        $session = reset($sessions);
        return new jazzquiz_session($this, $session->id);
    }

    /**
     * Check if a session is open for this quiz.
     * @return bool true if open
     */
    public function is_session_open() {
        global $DB;
        $sessions = $DB->get_records('jazzquiz_sessions', [
            'jazzquizid' => $this->data->id,
            'sessionopen' => 1
        ]);
        return count($sessions);
    }

    /**
     * Create a new session for this quiz.
     * @param string $name
     * @param int $anonymity
     * @param int $allowguests
     * @return int|false Session ID returned if successful, else false.
     */
    public function create_session(string $name, int $anonymity, int $allowguests) {
        global $DB;
        $this->data->cfganonymity = $anonymity;
        $this->data->cfgallowguests = $allowguests;
        $this->save();
        $session = new \stdClass();
        $session->name = $name;
        $session->jazzquizid = $this->data->id;
        $session->sessionopen = 1;
        $session->status = 'notrunning';
        $session->slot = 0;
        $session->anonymity = $anonymity;
        $session->showfeedback = false;
        $session->allowguests = $allowguests;
        $session->created = time();
        try {
            return $DB->insert_record('jazzquiz_sessions', $session);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Saves the JazzQuiz instance to the database
     * @return bool
     */
    public function save() {
        global $DB;
        return $DB->update_record('jazzquiz', $this->data);
    }

    /**
     * Handles adding a question action from the question bank.
     *
     * Displays a form initially to ask how long they'd like the question to be set up for, and then after
     * valid input saves the question to the quiz at the last position
     *
     * @param int $questionid The question bank's question id
     */
    public function add_question($questionid) {
        global $DB;
        $question = new \stdClass();
        $question->jazzquizid = $this->data->id;
        $question->questionid = $questionid;
        $question->questiontime = $this->data->defaultquestiontime;
        $question->slot = count($this->questions) + 1;
        $DB->insert_record('jazzquiz_questions', $question);
        $this->refresh_questions();
    }

    /**
     * Apply a sorted array of jazzquiz_question IDs to the quiz.
     * Questions that are missing from the array will also be removed from the quiz.
     * Duplicate values will silently be removed.
     *
     * @param int[] $order
     */
    public function set_question_order(array $order) {
        global $DB;
        $order = array_unique($order);
        $questions = $DB->get_records('jazzquiz_questions', ['jazzquizid' => $this->data->id], 'slot');
        foreach ($questions as $question) {
            $slot = array_search($question->id, $order);
            if ($slot === false) {
                $DB->delete_records('jazzquiz_questions', ['id' => $question->id]);
                continue;
            }
            $question->slot = $slot + 1;
            $DB->update_record('jazzquiz_questions', $question);
        }
        $this->refresh_questions();
    }

    /**
     * @return int[] of jazzquiz_question id
     */
    public function get_question_order() : array {
        $order = [];
        foreach ($this->questions as $question) {
            $order[] = $question->data->id;
        }
        return $order;
    }

    /**
     * Edit a JazzQuiz question
     * @param int $questionid the JazzQuiz question id
     */
    public function edit_question($questionid) {
        global $DB;
        $url = new \moodle_url('/mod/jazzquiz/edit.php', ['id' => $this->cm->id]);
        $actionurl = clone($url);
        $actionurl->param('action', 'editquestion');
        $actionurl->param('questionid', $questionid);

        $jazzquizquestion = $DB->get_record('jazzquiz_questions', ['id' => $questionid], '*', MUST_EXIST);
        $question = $DB->get_record('question', ['id' => $jazzquizquestion->questionid], '*', MUST_EXIST);

        $mform = new forms\edit\add_question_form($actionurl, [
            'jazzquiz' => $this,
            'questionname' => $question->name,
            'edit' => true
        ]);

        // Form handling.
        if ($mform->is_cancelled()) {
            // Redirect back to list questions page.
            $url->remove_params('action');
            redirect($url, null, 0);
        } else if ($data = $mform->get_data()) {
            $question = new \stdClass();
            $question->id = $jazzquizquestion->id;
            $question->jazzquizid = $this->data->id;
            $question->questionid = $jazzquizquestion->questionid;
            if ($data->notime) {
                $question->questiontime = 0;
            } else {
                $question->questiontime = $data->questiontime;
            }
            $DB->update_record('jazzquiz_questions', $question);
            // Ensure there is no action or question_id in the base url.
            $url->remove_params('action', 'questionid');
            redirect($url, null, 0);
        } else {
            // Display the form.
            $mform->set_data([
                'questiontime' => $jazzquizquestion->questiontime,
                'notime' => ($jazzquizquestion->questiontime < 1)
            ]);
            $this->renderer->header($this, 'edit');
            echo '<div class="generalbox boxaligncenter jazzquiz-box">';
            $mform->display();
            echo '</div>';
            $this->renderer->footer();
        }
    }

    /**
     * Loads the quiz questions from the database, ordered by slot.
     */
    public function refresh_questions() {
        global $DB;
        $this->questions = [];
        $questions = $DB->get_records('jazzquiz_questions', ['jazzquizid' => $this->data->id], 'slot');
        foreach ($questions as $question) {
            $jazzquestion = new jazzquiz_question($question);
            if ($jazzquestion->is_valid()) {
                $this->questions[$question->slot] = $jazzquestion;
            } else {
                $DB->delete_records('jazzquiz_questions', ['id' => $question->id]);
            }
        }
    }

    /**
     * @param int $jazzquizquestionid
     * @return jazzquiz_question|bool
     */
    public function get_question_by_id($jazzquizquestionid) {
        foreach ($this->questions as $question) {
            if ($question->data->id == $jazzquizquestionid) {
                return $question;
            }
        }
        return false;
    }

    /**
     * Quick function for whether or not the current user is the instructor/can control the quiz
     * @return bool
     */
    public function is_instructor() : bool {
        if (is_null($this->isinstructor)) {
            $this->isinstructor = has_capability('mod/jazzquiz:control', $this->context);
        }
        return $this->isinstructor;
    }

    /**
     * Get all sessions for this jazzquiz
     * @param array $conditions
     * @return \stdClass[]
     */
    public function get_sessions(array $conditions = []) : array {
        global $DB;
        $conditions = array_merge(['jazzquizid' => $this->data->id], $conditions);
        return $DB->get_records('jazzquiz_sessions', $conditions);
    }

}
