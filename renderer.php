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

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/questionlib.php');

/**
 * Realtime quiz renderer
 *
 * @package     mod_jazzquiz
 * @author      John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright   2014 University of Wisconsin - madison
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_jazzquiz_renderer extends plugin_renderer_base
{

    /** @var array $pagevars Includes other page information needed for rendering functions */
    protected $pagevars;

    /** @var moodle_url $pageurl easy access to the pageurl */
    protected $pageurl;

    /** @var \mod_jazzquiz\jazzquiz $rtq */
    protected $rtq;

    /** @var array Message to display with the page, is array with the first param being the type of message
     *              the second param being the message
     */
    protected $pageMessage;

    //TODO:  eventually think about making page specific renderer helpers so that we can make static calls for standard
    //TODO:      rendering on things.  E.g. editrenderer::questionblock();

    /**
     * Initialize the renderer with some variables
     *
     * @param \mod_jazzquiz\jazzquiz $RTQ
     * @param moodle_url $pageurl Always require the page url
     * @param array $pagevars (optional)
     */
    public function init($RTQ, $pageurl, $pagevars = array())
    {
        $this->pagevars = $pagevars;
        $this->pageurl = $pageurl;
        $this->rtq = $RTQ;
    }


    /**
     * Sets a page message to display when the page is loaded into view
     *
     * base_header() must be called for the message to appear
     *
     * @param string $type
     * @param string $message
     */
    public function setMessage($type, $message)
    {
        $this->pageMessage = array($type, $message);
    }

    /**
     * Base header function to do basic header rendering
     *
     * @param string $tab the current tab to show as active
     */
    public function base_header($tab = 'view')
    {
        echo $this->output->header();
        echo jazzquiz_view_tabs($this->rtq, $tab);
        $this->showMessage(); // shows a message if there is one
    }

    /**
     * Base footer function to do basic footer rendering
     *
     */
    public function base_footer()
    {
        echo $this->output->footer();
    }

    /**
     * shows a message if there is one
     *
     */
    protected function showMessage()
    {

        if (empty($this->pageMessage)) {
            return; // return if there is no message
        }

        if (!is_array($this->pageMessage)) {
            return; // return if it's not an array
        }

        switch ($this->pageMessage[0]) {
            case 'error':
                echo $this->output->notification($this->pageMessage[1], 'notifiyproblem');
                break;
            case 'success':
                echo $this->output->notification($this->pageMessage[1], 'notifysuccess');
                break;
            case 'info':
                echo $this->output->notification($this->pageMessage[1], 'notifyinfo');
                break;
            default:
                // unrecognized notification type
                break;
        }
    }

    /**
     * Shows an error message with the popup layout
     *
     * @param string $message
     */
    public function render_popup_error($message)
    {

        $this->setMessage('error', $message);
        echo $this->output->header();
        $this->showMessage();
        $this->base_footer();
    }



    /** View page functions */

    /**
     * Basic header for the view page
     *
     * @param bool $renderingquiz
     */
    public function view_header($renderingquiz = false)
    {

        // if we're rendering the quiz check if any of the question modifiers need jquery
        if ($renderingquiz) {

            $this->rtq->call_question_modifiers('requires_jquery', null);
            $this->rtq->call_question_modifiers('add_css', null);
        }

        $this->base_header('view');
    }


    /**
     * Displays the home view for the instructor
     *
     * @param \moodleform $sessionform
     * @param bool|\stdclass $sessionstarted is a standard class when there is a session
     */
    public function view_inst_home($sessionform, $sessionstarted)
    {


        if ($sessionstarted) {

            echo html_writer::tag('p', get_string('instructorsessionsgoing', 'jazzquiz'));
            $gotoexistingsession = clone($this->pageurl);
            $gotoexistingsession->param('action', 'quizstart');
            $gotoexistingsession->param('session', $sessionstarted->id);
            $gotosession = $this->output->single_button($gotoexistingsession, get_string('gotosession', 'jazzquiz'));
            echo html_writer::tag('p', $gotosession);
        } else {

            echo html_writer::tag('p', get_string('teacherstartinstruct', 'jazzquiz'));
            echo html_writer::tag('p', get_string('teacherjoinquizinstruct', 'jazzquiz'));
            echo html_writer::empty_tag('br');
            echo $sessionform->display();

        }
    }

    /**
     * Displays the view home.
     *
     * @param \mod_jazzquiz\forms\view\student_start_form $studentstartform
     * @param \mod_jazzquiz\jazzquiz_session $session The jazzquiz session object to call methods on
     */
    public function view_student_home($studentstartform, $session)
    {
        global $USER;

        echo html_writer::start_div('jazzquizbox');

        // Check if there is an open session
        if ($session->get_session()) {

            // Show the join quiz button
            $joinquiz = clone($this->pageurl);
            $joinquiz->param('action', 'quizstart');
            echo html_writer::tag('p', get_string('joinquizinstructions', 'jazzquiz'));
            echo html_writer::tag('p', get_string('sessionnametext', 'jazzquiz') . $session->get_session()->name);

            // see if the user has attempts, if so, let them know that continuing will continue them to their attempt
            if ($session->get_open_attempt_for_current_user()) {
                echo html_writer::tag('p', get_string('attemptstarted', 'jazzquiz'), array('id' => 'quizinfobox'));
            }

            // add the student join quiz form
            $studentstartform->display();

        } else {

            echo html_writer::tag('p', get_string('quiznotrunning', 'jazzquiz'));
            // show a reload page button to make it easy to reload page
            $reloadbutton = $this->output->single_button($this->pageurl, get_string('reload'), 'get');
            echo html_writer::tag('p', $reloadbutton);

        }

        echo html_writer::end_div();

        if (count($this->rtq->get_closed_sessions()) == 0) {
            return; // return early if there are no closed sessions
        }

        echo html_writer::start_div('jazzquizbox');

        // show overall grade


        $a = new stdClass();
        $usergrades = \mod_jazzquiz\utils\grade::get_user_grade($this->rtq->getRTQ(), $USER->id);
        // should only be 1 grade, but we'll always get end()

        if (!empty($usergrades)) {
            $usergrade = end($usergrades);
            $a->overallgrade = number_format($usergrade->rawgrade, 2);

            $a->scale = $this->rtq->getRTQ()->scale;
            echo html_writer::start_tag('h3');
            echo get_string('overallgrade', 'jazzquiz', $a);
            echo html_writer::end_tag('h3');
        } else {
            return;  // if no user grade there are no attempts for this user
        }

        // show attempts table if rtq is set up to show attempts in the after review options
        if ($this->rtq->get_review_options('after')->attempt == 1) {

            echo html_writer::tag('h3', get_string('attempts', 'jazzquiz'));

            $viewownattemptstable = new \mod_jazzquiz\tableviews\ownattempts('viewownattempts', $this->rtq, $this->pageurl);
            $viewownattemptstable->setup();
            $viewownattemptstable->set_data();

            $viewownattemptstable->finish_output();


        }

        echo html_writer::end_div();

    }

    /**
     * Shows a message to students in a group with an open attempt already started
     *
     */
    public function group_session_started()
    {
        echo html_writer::tag('p', get_string('attemptstartedalready', 'mod_jazzquiz'));
    }

    /**
     * Display the group members select form
     *
     * @param \mod_jazzquiz\forms\view\groupselectmembers $selectmembersform
     */
    public function group_member_select($selectmembersform)
    {

        $selectmembersform->display();

    }

    /**
     * Renders the quiz to the page
     *
     * @param \mod_jazzquiz\jazzquiz_attempt $attempt
     * @param \mod_jazzquiz\jazzquiz_session $session
     */
    public function render_quiz(\mod_jazzquiz\jazzquiz_attempt $attempt,
                                \mod_jazzquiz\jazzquiz_session $session)
    {

        $this->init_quiz_js($attempt, $session);

        $output = '';

        $output .= html_writer::start_div('', array('id' => 'quizview'));

        if ($this->rtq->is_instructor()) {
            $output .= html_writer::div($this->render_controls(), 'jazzquizbox hidden', array('id' => 'controlbox'));
            $output .= $this->render_jumpto_modal($attempt);
            $instructions = get_string('instructorquizinst', 'jazzquiz');
        } else {
            $instructions = get_string('studentquizinst', 'jazzquiz');
        }
        $loadingpix = $this->output->pix_icon('i/loading', 'loading...');
        $output .= html_writer::start_div('jazzquizloading', array('id' => 'loadingbox'));
        $output .= html_writer::tag('p', get_string('loading', 'jazzquiz'), array('id' => 'loadingtext'));
        $output .= $loadingpix;
        $output .= html_writer::end_div();


        $output .= html_writer::div($instructions, 'jazzquizbox hidden', array('id' => 'instructionsbox'));

        // have a quiz not responded box for the instructor to know who hasn't responded.
        if ($this->rtq->is_instructor()) {
            $output .= html_writer::div('', 'jazzquizbox hidden', array('id' => 'notrespondedbox'));
        }

        if ($session->get_session()->fully_anonymize && $this->rtq->is_instructor() == 0) {
            $output .= html_writer::div(get_string('isanonymous', 'mod_jazzquiz'), 'jazzquizbox isanonymous');
        }

        // have a quiz information box to show statistics, feedback and more.
        $output .= html_writer::div('', 'jazzquizbox hidden', array('id' => 'quizinfobox'));

        // question form containers
        foreach ($attempt->getSlots() as $slot) {
            // render question form.
            $output .= $this->render_question_form($slot, $attempt);
        }

        $output .= html_writer::end_div();
        echo $output;
    }

    /**
     * Render a specific question in its own form so it can be submitted
     * independently of the rest of the questions
     *
     * @param int $slot the id of the question we're rendering
     * @param \mod_jazzquiz\jazzquiz_attempt $attempt
     *
     * @return string HTML fragment of the question
     */
    public function render_question_form($slot, $attempt)
    {

        $output = '';
        $qnum = $attempt->get_question_number();
        // Start the form.
        $output .= html_writer::start_tag('div', array('class' => 'jazzquizbox hidden', 'id' => 'q' . $qnum . '_container'));

        $output .= html_writer::start_tag('form',
            array('action' => '', 'method' => 'post',
                'enctype' => 'multipart/form-data', 'accept-charset' => 'utf-8',
                'id' => 'q' . $qnum, 'class' => 'jazzquiz_question',
                'name' => 'q' . $qnum));


        $output .= $attempt->render_question($slot);

        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'slots',
            'value' => $slot));


        $savebtn = html_writer::tag('button', 'Save', array(
                'class' => 'btn',
                'id' => 'q' . $qnum . '_save',
                'onclick' => 'jazzquiz.save_question(\'q' . $qnum . '\'); return false;'
            )
        );
        $timertext = html_writer::div(get_string('timertext', 'jazzquiz'), 'timertext', array('id' => 'q' . $qnum . '_questiontimetext'));
        $timercount = html_writer::div('', 'timercount', array('id' => 'q' . $qnum . '_questiontime'));

        $rtqQuestion = $attempt->get_question_by_slot($slot);
        if ($rtqQuestion !== false && $rtqQuestion->getTries() > 1 && !$this->rtq->is_instructor()) {
            $count = new stdClass();
            $count->tries = $rtqQuestion->getTries();
            $trytext = html_writer::div(get_string('trycount', 'jazzquiz', $count), 'trycount', array('id' => 'q' . $qnum . '_trycount'));
        } else {
            $trytext = html_writer::div('', 'trycount', array('id' => 'q' . $qnum . '_trycount'));;
        }

        // instructors don't need to save questions
        if (!$this->rtq->is_instructor()) {
            $savebtncont = html_writer::div($savebtn, 'question_save');
        } else {
            $savebtncont = '';
        }

        $output .= html_writer::div($savebtncont . $trytext . $timertext . $timercount, 'save_row');


        // Finish the form.
        $output .= html_writer::end_tag('form');
        $output .= html_writer::end_tag('div');


        return $output;
    }

    /**
     * Renders the controls for the quiz for the instructor
     *
     * @return string HTML fragment
     */
    public function render_controls()
    {
        $output = '';
            $inqcontrol = '<div class="quiz-list-buttons quiz-control-buttons">';

        $inqcontrol .= html_writer::tag('button', '<i class="fa fa-repeat"></i> Re-poll', array(
                'class' => 'btn',
                'id' => 'repollquestion',
                'onclick' => 'jazzquiz.repoll_question();',
                'disabled' => 'true'
            )
        );

        $inqcontrol .= html_writer::tag('button', '<i class="fa fa-bar-chart"></i> Vote', array(
                'class' => 'btn',
                'id' => 'runvoting',
                'onclick' => 'jazzquiz.run_voting();'
            )
        );

        $inqcontrol .= html_writer::tag('button', '<div class="improvise-menu"></div><i class="fa fa-edit"></i> Improvise', array(
                'class' => 'btn',
                'id' => 'startimprovisedquestion',
                'onclick' => 'jazzquiz.show_improvised_question_setup();'
            )
        );

        $inqcontrol .= html_writer::tag('button', '<i class="fa fa-bars"></i> Jump to', array(
                'class' => 'btn',
                'id' => 'jumptoquestion',
                'onclick' => 'jazzquiz.jumpto_question();',
                'disabled' => 'true'
            )
        );

        $inqcontrol .= html_writer::tag('button', '<i class="fa fa-forward"></i> Next', array(
                'class' => 'btn',
                'id' => 'nextquestion',
                'onclick' => 'jazzquiz.next_question();',
                'disabled' => 'true'
            )
        );

        $inqcontrol .= html_writer::tag('button', '<i class="fa fa-close"></i> End', array(
                'class' => 'btn',
                'id' => 'endquestion',
                'onclick' => 'jazzquiz.end_question();',
                'disabled' => 'true'
            )
        );

        $inqcontrol .= html_writer::tag('button', '<i class="fa fa-refresh"></i>', array(
                'class' => 'btn',
                'id' => 'reloadresults',
                'onclick' => 'jazzquiz.reload_results();'
            )
        );

        $inqcontrol .= html_writer::tag('button', '<i class="fa fa-expand"></i>', array(
                'class' => 'btn',
                'id' => 'showfullscreenresults',
                'onclick' => 'jazzquiz.show_fullscreen_results_view();'
            )
        );


        $inqcontrol .= html_writer::tag('button', '<i class="fa fa-eye"></i> Show answer', array(
                'class' => 'btn',
                'id' => 'showcorrectanswer',
                'onclick' => 'jazzquiz.show_correct_answer();'
            )
        );

        $inqcontrol .= html_writer::tag('button', '<i class="fa fa-minus-square"></i> ' . get_string('hidestudentresponses', 'jazzquiz'), array(
                'class' => 'btn',
                'id' => 'toggleresponses',
                'onclick' => 'jazzquiz.toggle_responses();',
                'disabled' => 'true'
            )
        );
        /*$inqcontrol .= html_writer::tag('button', '<i class="fa fa-minus-square"></i> ' . get_string('hidenotresponded', 'jazzquiz'), array(
                'class'    => 'btn',
                'id'       => 'togglenotresponded',
                'onclick'  => 'jazzquiz.toggle_notresponded();',
                'disabled' => 'true'
            )
        );*/

        $inqcontrol .= html_writer::tag('button', '<i class="fa fa-window-close"></i> Quit', array(
                'class' => 'btn',
                'id' => 'closesession',
                'onclick' => 'jazzquiz.close_session();',
                'disabled' => 'true',
                'style' => 'float:right;'
            )
        );

        $inqcontrol .= '</div>';

        $inqcontrol .= '<div class="quiz-list-buttons">';

        $inqcontrol .= html_writer::tag('button', '<i class="fa fa-start"></i>' . get_string('startquiz', 'jazzquiz'), array(
                'class' => 'btn',
                'id' => 'startquiz',
                'onclick' => 'jazzquiz.start_quiz();'
            )
        );

        $inqcontrol .= '</div>';

        $output .= html_writer::div($inqcontrol, 'btn-hide rtq_inquiz', array('id' => 'inquizcontrols'));

        return $output;
    }

    /**
     * Returns a modal div for displaying the jump to question feature
     *
     * @param \mod_jazzquiz\jazzquiz_attempt $attempt
     * @return string HTML fragment for the modal box
     */
    public function render_jumpto_modal($attempt)
    {

        $output = html_writer::start_div('modalDialog', array('id' => 'jumptoquestion-dialog'));

        $output .= html_writer::start_div();

        $output .= html_writer::tag('a', 'X', array('class' => 'jumptoquestionclose', 'href' => '#'));
        $output .= html_writer::tag('h2', get_string('jumptoquestion', 'jazzquiz'));
        $output .= html_writer::tag('p', get_string('jumptoquesetioninstructions', 'jazzquiz'));

        // build our own select for the user to select the question they want to go to
        $output .= html_writer::start_tag('select', array('name' => 'jtq-selectquestion', 'id' => 'jtq-selectquestion'));
        // loop through each question and add it as an option
        $qnum = 1;
        foreach ($attempt->get_questions() as $question) {
            $output .= html_writer::tag('option', $qnum . ': ' . $question->getQuestion()->name, array('value' => $qnum));
            $qnum++;
        }
        $output .= html_writer::end_tag('select');

        $output .= html_writer::tag('button', get_string('jumptoquestion', 'jazzquiz'), array('onclick' => 'jazzquiz.jumpto_question()'));
        $output .= html_writer::end_div();

        $output .= html_writer::end_div();

        return $output;
    }


    /**
     * Initializes quiz javascript and strings for javascript when on the
     * quiz view page, or the "quizstart" action
     *
     * @param \mod_jazzquiz\jazzquiz_attempt $attempt
     * @param \mod_jazzquiz\jazzquiz_session $session
     * @throws moodle_exception throws exception when invalid question on the attempt is found
     */
    public function init_quiz_js($attempt, $session)
    {
        global $USER, $CFG;


        // include classList javascript to add the class List HTML5 for compatibility
        // below IE 10
        $this->page->requires->js('/mod/jazzquiz/js/classList.js');
        $this->page->requires->js('/mod/jazzquiz/js/core.js');

        // add window.onload script manually to handle removing the loading mask
        echo html_writer::start_tag('script', array('type' => 'text/javascript'));
        echo <<<EOD
            (function preLoad(){
                window.addEventListener('load', function(){jazzquiz.quiz_page_loaded();}, false);
            }());
EOD;
        echo html_writer::end_tag('script');

        if ($this->rtq->is_instructor()) {
            $this->page->requires->js('/mod/jazzquiz/js/instructor.js');
        } else {
            $this->page->requires->js('/mod/jazzquiz/js/student.js');
        }

        // next set up a class to pass to js for js info
        $jsinfo = new stdClass();
        $jsinfo->sesskey = sesskey();
        $jsinfo->siteroot = $CFG->wwwroot;
        $jsinfo->rtqid = $this->rtq->getRTQ()->id;
        $jsinfo->sessionid = $session->get_session()->id;
        $jsinfo->attemptid = $attempt->id;
        $jsinfo->slots = $attempt->getSlots();
        $jsinfo->isinstructor = ($this->rtq->is_instructor() ? 'true' : 'false');

        // manually create the questions stdClass as we can't support JsonSerializable yet
        $questions = array();
        foreach ($attempt->get_questions() as $q) {
            /** @var \mod_jazzquiz\jazzquiz_question $q */
            $question = new stdClass();
            $question->id = $q->getId();
            $question->questiontime = $q->getQuestionTime();
            $question->tries = $q->getTries();
            $question->question = $q->getQuestion();
            $question->slot = $attempt->get_question_slot($q);

            // if the slot is false, throw exception for invalid question on quiz attempt
            if ($question->slot === false) {
                $a = new stdClass();
                $a->questionname = $q->getQuestion()->name;

                throw new moodle_exception('invalidquestionattempt', 'mod_jazzquiz',
                    '', $a,
                    'invalid slot when building questions array on quiz renderer');
            }

            $questions[$question->slot] = $question;
        }
        $jsinfo->questions = $questions;

        // resuming quiz feature
        // this will check if the session has started already and print out
        $jsinfo->resumequiz = 'false';
        if ($session->get_session()->status != 'notrunning') {
            $sessionstatus = $session->get_session()->status;
            $currentquestion = $session->get_session()->currentquestion;
            $nextstarttime = $session->get_session()->nextstarttime;
            if ($sessionstatus == 'running' && !empty($currentquestion)) {
                // we're in a currently running question

                $jsinfo->resumequiz = 'true';
                $jsinfo->resumequizstatus = $sessionstatus;
                $jsinfo->resumequizcurrentquestion = $currentquestion;

                $nextQuestion = $this->rtq->get_questionmanager()->get_question_with_slot($session->get_session()->currentqnum, $attempt);

                if ($nextstarttime > time()) {
                    // we're wating for question
                    $jsinfo->resumequizaction = 'waitforquestion';
                    $jsinfo->resumequizdelay = $session->get_session()->nextstarttime - time();
                    $jsinfo->resumequizquestiontime = $nextQuestion->getQuestionTime();
                } else {
                    $jsinfo->resumequizaction = 'startquestion';

                    // how much time has elapsed since start time

                    // first check if the question has a time limit
                    if ($nextQuestion->getNoTime()) {
                        $jsinfo->resumequizquestiontime = 0;
                    } else { // otherwise figure out how much time is left
                        $timeelapsed = time() - $nextstarttime;
                        $timeLeft = $nextQuestion->getQuestionTime() - $timeelapsed;
                        $jsinfo->resumequizquestiontime = $timeLeft;
                    }

                    // next check how many tries left
                    $jsinfo->resumequestiontries = $attempt->check_tries_left($session->get_session()->currentqnum, $nextQuestion->getTries());
                }
            } else if ($sessionstatus == 'reviewing' || $sessionstatus == 'endquestion' || $sessionstatus == 'voting') {

                // if we're reviewing, resume with quiz info of reviewing and just let
                // set interval capture next question start time
                $jsinfo->resumequiz = 'true';
                $jsinfo->resumequizaction = 'reviewing';
                $jsinfo->resumequizstatus = $sessionstatus;
                $jsinfo->resumequizcurrentquestion = $currentquestion;
                if ($attempt->lastquestion) {
                    $jsinfo->lastquestion = 'true';
                } else {
                    $jsinfo->lastquestion = 'false';
                }
            }
        }


        // print jsinfo to javascript
        echo html_writer::start_tag('script', array('type' => 'text/javascript'));
        echo "rtqinitinfo = " . json_encode($jsinfo);
        echo html_writer::end_tag('script');

        // add strings for js
        $this->page->requires->strings_for_js(array(
            'waitforquestion',
            'gatheringresults',
            'feedbackintro',
            'nofeedback',
            'closingsession',
            'sessionclosed',
            'trycount',
            'notries',
            'timertext',
            'waitforrevewingend',
            'show_correct_answer',
            'hide_correct_answer',
            'hidestudentresponses',
            'showstudentresponses',
            'hidenotresponded',
            'shownotresponded',
            'waitforinstructor'
        ), 'jazzquiz');

        $this->page->requires->strings_for_js(array('seconds'), 'moodle');


        // finally allow question modifiers to add their own css/js
        $this->rtq->call_question_modifiers('add_js', null);

    }

    /**
     * Renders a response for a specific question and attempt
     *
     * @param \mod_jazzquiz\jazzquiz_attempt $attempt
     * @param int $responsecount The number of the response (used for anonymous mode)
     *
     * @return string HTML fragment for the response
     */
    public function render_response($attempt, $responsecount, $anonymous = true)
    {
        global $DB;


        $response = html_writer::start_div('response');

        // check if group mode, if so, give the group name the attempt is for
        if ($anonymous) {
            if ($this->rtq->group_mode()) {
                $name = get_string('group') . ' ' . $responsecount;
            } else {
                $name = get_string('user') . ' ' . $responsecount;
            }
        } else {
            if ($this->rtq->group_mode()) {
                $name = $this->rtq->get_groupmanager()->get_group_name($attempt->forgroupid);
            } else {
                if ($user = $DB->get_record('user', array('id' => $attempt->userid))) {
                    $name = fullname($user);
                } else {
                    $name = get_string('anonymoususer', 'mod_jazzquiz');
                }

            }
        }

        $response .= html_writer::tag('h3', $name, array('class' => 'responsename'));
        $response .= html_writer::div($attempt->responsesummary, 'responsesummary');

        $response .= html_writer::end_div();

        return $response;
    }

    /**
     * Function to provide a display of how many open attempts have responded
     *
     * @param array $notresponded Array of the people who haven't responded
     * @param int $total
     * @param int $anonymous (0 or 1)
     *
     * @return string HTML fragment for the amount responded
     */
    public function respondedbox($notresponded, $total, $anonymous)
    {

        $output = '';

        $output .= html_writer::start_div();

        $output .= html_writer::start_div('respondedbox', array('id' => 'respondedbox'));
        $output .= html_writer::tag('h3', get_string('notresponded', 'jazzquiz'), array('class' => 'inline'));
        $output .= html_writer::div('&nbsp;&nbsp;&nbsp;' . count($notresponded) . '/' . $total, 'inline');
        $output .= html_writer::end_div();

        // output the list of students, but only if we're not in anonymous mode
        if (!$anonymous) {
            $output .= html_writer::start_div();
            $output .= html_writer::alist($notresponded, array('id' => 'notrespondedlist'));
            $output .= html_writer::end_div();
        }

        $output .= html_writer::end_div();

        return $output;
    }


    /**
     * No questions view
     *
     * @param bool $isinstructor
     */
    public function no_questions($isinstructor)
    {

        echo $this->output->box_start('generalbox boxaligncenter jazzquizbox');

        echo html_writer::tag('p', get_string('no_questions', 'jazzquiz'));

        if ($isinstructor) {

            // "Edit quiz" button
            $params = [
                'cmid' => $this->rtq->getCM()->id
            ];
            $editurl = new moodle_url('/mod/jazzquiz/edit.php', $params);
            $editbutton = $this->output->single_button($editurl, get_string('edit', 'jazzquiz'), 'get');
            echo html_writer::tag('p', $editbutton);

            // "Add improvisation questions" button
            $params['redirect'] = 'view';
            $add_improv_questions_url = new moodle_url('/mod/jazzquiz/improvisation.php', $params);
            $add_improv_questions_button = $this->output->single_button($add_improv_questions_url, 'Add improvisation questions', 'get');
            echo html_writer::tag('p', $add_improv_questions_button);

        }

        echo $this->output->box_end();
    }

    /**
     * Basic footer for the view page
     *
     */
    public function view_footer()
    {
        $this->base_footer();
    }


    /** End View page functions */


    /** Attempt view rendering **/


    /**
     * Render a specific attempt
     *
     * @param \mod_jazzquiz\jazzquiz_attempt $attempt
     * @param \mod_jazzquiz\jazzquiz_session $session
     */
    public function render_attempt($attempt, $session)
    {

        //$this->base_header('reviewattempt');
        echo $this->output->header();
        $this->showMessage();

        foreach ($attempt->getSlots() as $slot) {

            if ($this->rtq->is_instructor()) {
                echo $this->render_edit_review_question($slot, $attempt);
            } else {
                echo $this->render_review_question($slot, $attempt);
            }
        }
        $this->base_footer();
    }


    /**
     * Renders an individual question review
     *
     * This is the "edit" version that are for instructors/users who have the control capability
     *
     * @param int $slot
     * @param \mod_jazzquiz\jazzquiz_attempt $attempt
     *
     * @return string HTML fragment
     */
    public function render_edit_review_question($slot, $attempt)
    {

        $qnum = $attempt->get_question_number();
        $output = '';

        $output .= html_writer::start_div('jazzquizbox', array('id' => 'q' . $qnum . '_container'));


        $action = clone($this->pageurl);

        $output .= html_writer::start_tag('form',
            array('action' => '', 'method' => 'post',
                'enctype' => 'multipart/form-data', 'accept-charset' => 'utf-8',
                'id' => 'q' . $qnum, 'class' => 'jazzquiz_question',
                'name' => 'q' . $qnum));


        $output .= $attempt->render_question($slot, true, 'edit');

        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'slots',
            'value' => $slot));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'slot',
            'value' => $slot));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'action',
            'value' => 'savecomment'));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey',
            'value' => sesskey()));

        $savebtn = html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'submit',
            'value' => get_string('savequestion', 'jazzquiz'), 'class' => 'form-submit'));


        $mark = $attempt->get_slot_mark($slot);
        $maxmark = $attempt->get_slot_max_mark($slot);

        $output .= html_writer::start_tag('p');
        $output .= 'Marked ' . $mark . ' / ' . $maxmark;
        $output .= html_writer::end_tag('p');

        $output .= html_writer::div($savebtn, 'save_row');
        // Finish the form.
        $output .= html_writer::end_tag('form');
        $output .= html_writer::end_div();

        return $output;
    }

    /**
     * Render a review question with no editing capabilities.
     *
     * Reviewing will be based upon the after review options specified in module settings
     *
     * @param int $slot
     * @param \mod_jazzquiz\jazzquiz_attempt $attempt
     *
     * @return string HTML fragment for the question
     */
    public function render_review_question($slot, $attempt)
    {

        $qnum = $attempt->get_question_number();
        $output = '';

        $output .= html_writer::start_div('jazzquizbox', array('id' => 'q' . $qnum . '_container'));

        $output .= $attempt->render_question($slot, true, $this->rtq->get_review_options('after'));

        $output .= html_writer::end_div();

        return $output;
    }

    /** End attempt view rendering **/


}