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
 * @author      John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright   2014 University of Wisconsin - Madison
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jazzquiz
{
    /**
     * @var array $review fields Static review fields to add as options
     */
    public static $review_fields = [
        'attempt'          => [ 'theattempt', 'jazzquiz' ],
        'correctness'      => [ 'whethercorrect', 'question' ],
        'marks'            => [ 'marks', 'jazzquiz' ],
        'specificfeedback' => [ 'specificfeedback', 'question' ],
        'generalfeedback'  => [ 'generalfeedback', 'question' ],
        'rightanswer'      => [ 'rightanswer', 'question' ],
        'manualcomment'    => [ 'manualcomment', 'jazzquiz' ]
    ];

    /** @var \stdClass $course_module */
    public $course_module;

    /** @var \stdClass $course */
    public $course;

    /** @var \context_module $context */
    public $context;

    /** @var question_manager $question_manager */
    public $question_manager;

    /** @var \plugin_renderer_base|output\edit_renderer $renderer */
    public $renderer;

    /** @var \stdClass $data The jazzquiz database table row */
    public $data;

    /** @var bool $is_instructor */
    protected $is_instructor;

    /**
     * @param int $course_module_id The course module ID
     * @param string $renderer_subtype Renderer sub-type to load if requested
     */
    public function __construct($course_module_id, $renderer_subtype = null)
    {
        global $PAGE, $DB;

        $this->course_module = get_coursemodule_from_id('jazzquiz', $course_module_id, 0, false, MUST_EXIST);

        // TODO: Should login requirement be moved over to caller?
        require_login($this->course_module->course, false, $this->course_module);

        $this->context = \context_module::instance($course_module_id);
        $PAGE->set_context($this->context);
        $this->renderer = $PAGE->get_renderer('mod_jazzquiz', $renderer_subtype);

        $this->course = $DB->get_record('course', [ 'id' => $this->course_module->course ], '*', MUST_EXIST);
        $this->data = $DB->get_record('jazzquiz', [ 'id' => $this->course_module->instance ], '*', MUST_EXIST);
        $this->renderer->set_jazzquiz($this);
        $this->question_manager = new question_manager($this);
    }

    /**
     * Saves the JazzQuiz instance to the database
     * @return bool
     */
    public function save()
    {
        global $DB;
        return $DB->update_record('jazzquiz', $this->data);
    }

    /**
     * provides a wrapper of the require_capability to always provide the rtq context
     *
     * @param string $capability
     */
    public function require_capability($capability)
    {
        require_capability($capability, $this->context);
        // No return as require_capability will throw exception on error, or just continue
    }

    /**
     * Wrapper for the has_capability function to provide the rtq context
     *
     * @param string $capability
     * @param int $user_id
     *
     * @return bool Whether or not the current user has the capability
     */
    public function has_capability($capability, $user_id = 0)
    {
        if ($user_id !== 0) {
            // Pass in userid if there is one
            return has_capability($capability, $this->context, $user_id);
        }

        // Just do standard check with current user
        return has_capability($capability, $this->context);
    }

    /**
     * Quick function for whether or not the current user is the instructor/can control the quiz
     * @return bool
     */
    public function is_instructor()
    {
        if (is_null($this->is_instructor)) {
            $this->is_instructor = $this->has_capability('mod/jazzquiz:control');
        }
        return $this->is_instructor;
    }

    /**
     * Gets and returns a session specified by id
     * @param int $session_id
     * @return jazzquiz_session
     */
    public function get_session($session_id)
    {
        global $DB;
        $session = $DB->get_record('jazzquiz_sessions', [
            'id' => $session_id
        ], '*', MUST_EXIST);
        return new jazzquiz_session($this, $session);
    }

    /**
     * Gets sessions for this jazzquiz
     *
     * @param array $conditions
     * @return jazzquiz_session[]
     */
    public function get_sessions($conditions = [])
    {
        global $DB;
        $conditions = array_merge([ 'jazzquizid' => $this->data->id ], $conditions);
        $session_records = $DB->get_records('jazzquiz_sessions', $conditions);
        $sessions = [];
        foreach ($session_records as $session_record) {
            $sessions[] = new jazzquiz_session($this, $session_record);
        }
        return $sessions;
    }

    /**
     * Gets all sessions for the realtime quiz that are closed
     *
     * @return array
     */
    public function get_closed_sessions()
    {
        return $this->get_sessions([ 'sessionopen' => 0 ]);
    }

}
