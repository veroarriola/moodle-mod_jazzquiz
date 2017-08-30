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
 * Realtime quiz object.  This object contains a lot of dependencies
 * that work together that help to keep all of the dependencies in one
 * class instead of spreading them around to multiple classes
 *
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
    public static $reviewfields = array(
        'attempt' => array('theattempt', 'jazzquiz'),
        'correctness' => array('whethercorrect', 'question'),
        'marks' => array('marks', 'jazzquiz'),
        'specificfeedback' => array('specificfeedback', 'question'),
        'generalfeedback' => array('generalfeedback', 'question'),
        'rightanswer' => array('rightanswer', 'question'),
        'manualcomment' => array('manualcomment', 'jazzquiz')
    );

    /** @var \stdClass $cm */
    protected $cm;

    /** @var \stdClass $course */
    protected $course;

    /** @var \stdClass $jazzquiz */
    protected $jazzquiz;

    /** @var \context_module $context */
    protected $context;

    /** @var bool $isinstructor */
    protected $isinstructor;

    /** @var \mod_jazzquiz\questionmanager $questionmanager */
    protected $questionmanager;

    /** @var \mod_jazzquiz\utils\groupmanager $groupmanager */
    protected $groupmanager;

    /** @var \mod_jazzquiz_renderer $renderer */
    protected $renderer;

    /** @var \mod_jazzquiz\utils\grade $grader The grade utility class to perform gradding options */
    protected $grader;

    /** @var array $pagevars */
    protected $pagevars;

    /**
     * takes the realtime quiz object passed to add/update instance
     * and returns a stdClass of review options for the specified whenname
     *
     * @param \stdClass $formjazzquiz
     * @param string $whenname
     *
     * @return \stdClass
     */
    public static function get_review_options_from_form($formjazzquiz, $whenname)
    {

        $formoptionsgrp = $whenname . 'optionsgrp';
        $formreviewoptions = $formjazzquiz->$formoptionsgrp;

        $reviewoptions = new \stdClass();
        foreach (\mod_jazzquiz\jazzquiz::$reviewfields as $field => $notused) {
            $reviewoptions->$field = $formreviewoptions[$field];
        }

        return $reviewoptions;
    }


    /**
     * Construct a rtq class
     *
     * @param object $cm The course module instance
     * @param object $course The course object the activity is contained in
     * @param object $quiz The specific real time quiz record for this activity
     * @param \moodle_url $pageurl The page url
     * @param array $pagevars The variables and options for the page
     * @param string $renderer_subtype Renderer sub-type to load if requested
     *
     */
    public function __construct($cm, $course, $quiz, $pageurl, $pagevars = array(), $renderer_subtype = null)
    {
        global $CFG, $PAGE;

        $this->cm = $cm;
        $this->course = $course;
        $this->jazzquiz = $quiz;
        $this->pagevars = $pagevars;

        $this->context = \context_module::instance($cm->id);
        $PAGE->set_context($this->context);

        $this->renderer = $PAGE->get_renderer('mod_jazzquiz', $renderer_subtype);
        $this->questionmanager = new \mod_jazzquiz\questionmanager($this, $this->renderer, $this->pagevars);
        $this->grader = new \mod_jazzquiz\utils\grade($this);
        $this->groupmanager = new \mod_jazzquiz\utils\groupmanager($this);

        $this->renderer->init($this, $pageurl, $pagevars);
    }

    /** Get functions */

    /**
     * Get the course module isntance
     *
     * @return object
     */
    public function getCM()
    {
        return $this->cm;
    }

    /**
     * Get the course instance
     *
     * @return object
     */
    public function getCourse()
    {
        return $this->course;
    }

    /**
     * Returns the reqltimequiz database record instance
     *
     * @return object
     */
    public function getRTQ()
    {
        return $this->jazzquiz;
    }

    /**
     * Saves the rtq instance to the database
     *
     * @return bool
     */
    public function saveRTQ()
    {
        global $DB;

        return $DB->update_record('jazzquiz', $this->jazzquiz);
    }

    /**
     * Gets the context for this instance
     *
     * @return \context_module
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * Sets the question manager on this class
     *
     * @param \mod_jazzquiz\questionmanager $questionmanager
     */
    public function set_questionmanager(\mod_jazzquiz\questionmanager $questionmanager)
    {
        $this->questionmanager = $questionmanager;
    }

    /**
     * Returns the class instance of the question manager
     *
     * @return \mod_jazzquiz\questionmanager
     */
    public function get_questionmanager()
    {
        return $this->questionmanager;
    }

    /**
     * Sets the renderer on this class
     *
     * @param \mod_jazzquiz_renderer $renderer
     */
    public function set_renderer(\mod_jazzquiz_renderer $renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * Returns the class instance of the renderer
     *
     * @return \mod_jazzquiz_renderer
     */
    public function get_renderer()
    {
        return $this->renderer;
    }

    /**
     * Gets the grader utility class to perform grading actions
     *
     * @return \mod_jazzquiz\utils\grade
     */
    public function get_grader()
    {
        return $this->grader;
    }

    /**
     * Gets the group manager utility class for group actions
     *
     * @return \mod_jazzquiz\utils\groupmanager
     */
    public function get_groupmanager()
    {
        return $this->groupmanager;
    }

    /**
     * provides a wrapper of the require_capability to always provide the rtq context
     *
     * @param string $capability
     */
    public function require_capability($capability)
    {
        require_capability($capability, $this->context);

        // no return as require_capability will throw exception on error, or just continue
    }

    /**
     * Wrapper for the has_capability function to provide the rtq context
     *
     * @param string $capability
     * @param int $userid
     *
     * @return bool Whether or not the current user has the capability
     */
    public function has_capability($capability, $userid = 0)
    {
        if ($userid !== 0) {
            // pass in userid if there is one
            return has_capability($capability, $this->context, $userid);
        } else {
            // just do standard check with current user
            return has_capability($capability, $this->context);
        }
    }

    /**
     * Quick function for whether or not the current user is the instructor/can control the quiz
     *
     * @return bool
     */
    public function is_instructor()
    {
        if (is_null($this->isinstructor)) {
            $this->isinstructor = $this->has_capability('mod/jazzquiz:control');
        }
        return $this->isinstructor;
    }

    /**
     * Whether or not we're in group mode
     *
     * @return bool
     */
    public function group_mode()
    {
        return (bool) $this->jazzquiz->workedingroups;
    }

    /**
     * Gets the review options for the specified time
     *
     * @param string $whenname The review options time that we want to get the options for
     *
     * @return \stdClass A class of the options
     */
    public function get_review_options($whenname)
    {

        $reviewoptions = json_decode($this->jazzquiz->reviewoptions);

        return $reviewoptions->$whenname;
    }

    /**
     * gets and returns a jazzquiz session specified by sessionid
     *
     * @param int $sessionid
     *
     * @return \mod_jazzquiz\jazzquiz_session
     */
    public function get_session($sessionid)
    {
        global $DB;

        $session = $DB->get_record('jazzquiz_sessions', array('id' => $sessionid), '*', MUST_EXIST);

        return new \mod_jazzquiz\jazzquiz_session($this, $this->pagevars['pageurl'], $this->pagevars, $session);

    }

    /**
     * Gets sessions for this jazzquiz
     *
     * @param array $conditions
     * @return array
     */
    public function get_sessions($conditions = array())
    {
        global $DB;

        $qconditions = array_merge(array('jazzquizid' => $this->getRTQ()->id), $conditions);

        $sessions = $DB->get_records('jazzquiz_sessions', $qconditions);

        $rtqsessions = array();
        foreach ($sessions as $session) {
            $rtqsessions[] = new \mod_jazzquiz\jazzquiz_session($this, $this->pagevars['pageurl'], $this->pagevars, $session);
        }

        return $rtqsessions;
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

    /**
     * This is a method to invoke the question modifier classes
     *
     * * * while params not explicitly defined, the first two arguments are required
     * @param string $action The function that will be called on the question modifier classes,
     *                          function must be defined in basequestionmodifier
     * @param \mod_jazzquiz\jazzquiz_question|null The question that we're going to modifiy.
     *                                                     If null, we'll use all questions defined for this instance
     *
     * Any parameters passed after the first 2 are passed to the action function
     *
     * @throws \moodle_exception Throws moodle exception on errors in invoking methods
     */
    public function call_question_modifiers()
    {

        $params = func_get_args();

        if (empty($params[0])) {
            throw new \moodle_exception('noaction', 'jazzquiz', null, null, 'Invalid call to call_question_modifiers.  No Action');
        } else {
            $action = $params[0];
        }

        // next get the question types we're going to be invoking question modifiers for
        if (!empty($params[1])) {

            if ($params[1] instanceof \mod_jazzquiz\jazzquiz_question) {

                /** @var \mod_jazzquiz\jazzquiz_question $question */
                $question = $params[1];

                // We have a question defined, so we'll use it's question type
                $questiontypes = [ $question->getQuestion()->qtype ];

            } else {

                $questiontypes = [];

            }

        } else {
            // we're going through all question types defined by the instance
            $questiontypes = [];
            $questions = $this->get_questionmanager()->get_questions();
            foreach ($questions as $question) {
                /** @var \mod_jazzquiz\jazzquiz_question $question */
                $questiontypes[] = $question->getQuestion()->qtype;
            }
        }

        if (empty($questiontypes)) {
            throw new \moodle_exception('noquestiontypes', 'jazzquiz', null, null, 'No question types defined for this call');
        }

        // next we'll try to invoke the methods
        $return = null;
        foreach ($questiontypes as $type) {

            // first check to make sure the class exists
            if (class_exists("\\mod_jazzquiz\\questionmodifiers\\" . $type)) {

                // create reflection for it to validate action and params as well as implementing
                $reflection = new \ReflectionClass('\mod_jazzquiz\questionmodifiers\\' . $type);
                if (!$reflection->implementsInterface('\mod_jazzquiz\questionmodifiers\ibasequestionmodifier')) {
                    throw new \moodle_exception('invlidimplementation', 'jazzquiz', null, null, 'You question modifier does not implement the base modifier interface... ' . $type);
                } else {
                    $rMethod = $reflection->getMethod($action);
                    $fparams = array_slice($params, 2);

                    // next validate that we've gotten the right number of parameters for calling the action
                    if ($rMethod->getNumberOfRequiredParameters() != count($fparams)) {
                        throw new \moodle_exception('invalidnumberofparams', 'jazzquiz', null, null, 'Invalid number of parameters passed to question modifiers call');
                    } else {

                        // now just call and return the method's return
                        $class = '\mod_jazzquiz\questionmodifiers\\' . $type;
                        $typemodifier = new $class();
                        $return .= call_user_func_array(array($typemodifier, $action), $fparams);
                    }
                }
            }
        }

        return $return;
    }

}

