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

            // Show relevant instructions
            echo html_writer::tag('p', get_string('instructorsessionsgoing', 'jazzquiz'));

            // Output the link for continuing session
            $id = $this->pageurl->get_param('id');
            $quizid = $this->pageurl->get_param('quizid');
            $path = $this->pageurl->get_path() . '?id=' . $id . '&quizid=' . $quizid . '&action=quizstart';
            $gotosession = '<a href="' . $path . '" class="btn btn-secondary">' . get_string('gotosession', 'jazzquiz') . '</a>';
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
    public function render_quiz(\mod_jazzquiz\jazzquiz_attempt $attempt, \mod_jazzquiz\jazzquiz_session $session)
    {

        $this->init_quiz_js($attempt, $session);

        $output = '';

        $output .= html_writer::start_div('', [
            'id' => 'quizview'
        ]);

        if ($this->rtq->is_instructor()) {

            $output .= html_writer::div($this->render_controls(), 'jazzquizbox hidden', [
                'id' => 'controlbox'
            ]);

            $output .= $this->render_jumpto_modal($attempt);

            $instructions = get_string('instructorquizinst', 'jazzquiz');

        } else {

            $instructions = get_string('studentquizinst', 'jazzquiz');

        }

        $loadingpix = $this->output->pix_icon('i/loading', 'loading...');

        $output .= html_writer::start_div('jazzquizloading', [
            'id' => 'loadingbox'
        ]);

        $output .= html_writer::tag('p', get_string('loading', 'jazzquiz'), [
            'id' => 'loadingtext'
        ]);

        $output .= $loadingpix;
        $output .= html_writer::end_div();

        $output .= html_writer::div($instructions, 'jazzquizbox hidden', [
            'id' => 'jazzquiz_instructions_container'
        ]);

        if ($this->rtq->is_instructor()) {

            $output .= html_writer::div('', 'jazzquizbox padded-box hidden', [
                'id' => 'jazzquiz_correct_answer_container'
            ]);

            $output .= html_writer::div('', 'jazzquizbox hidden', [
                'id' => 'jazzquiz_responded_container'
            ]);

            $output .= html_writer::div('', 'jazzquizbox hidden padded-box', [
                'id' => 'jazzquiz_response_info_container'
            ]);

            $output .= html_writer::div('', 'jazzquizbox hidden', [
                'id' => 'jazzquiz_responses_container'
            ]);

        } else {

            if ($session->get_session()->fully_anonymize) {
                $output .= html_writer::div(get_string('isanonymous', 'mod_jazzquiz'), 'jazzquizbox isanonymous');
            }

        }

        $output .= html_writer::div('', 'jazzquizbox padded-box hidden', [
            'id' => 'jazzquiz_info_container',
        ]);

        // Question form containers
        foreach ($attempt->getSlots() as $slot) {
            // Render the question form.
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
        $output .= html_writer::start_tag('div', [
            'class' => 'jazzquizbox hidden',
            'id' => 'q' . $qnum . '_container'
        ]);

        $onsubmit = '';
        if (!$this->rtq->is_instructor()) {
            $onsubmit .= 'jazzquiz.save_question(\'q' . $qnum . '\');';
        }
        $onsubmit .= 'return false;';

        $output .= html_writer::start_tag('form', [
            'action' => '',
            'method' => 'post',
            'enctype' => 'multipart/form-data',
            'accept-charset' => 'utf-8',
            'id' => 'q' . $qnum,
            'class' => 'jazzquiz_question',
            'onsubmit' => $onsubmit,
            'name' => 'q' . $qnum
        ]);

        $output .= $attempt->render_question($slot);

        $output .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'slots',
            'value' => $slot
        ]);

        $savebtn = html_writer::tag('button', 'Save', [
            'class' => 'btn',
            'id' => 'q' . $qnum . '_save',
            'onclick' => 'jazzquiz.save_question(\'q' . $qnum . '\'); return false;'
        ]);

        $timertext = html_writer::div(get_string('timertext', 'jazzquiz'), 'timertext', [
            'id' => 'q' . $qnum . '_questiontimetext'
        ]);

        $timercount = html_writer::div('', 'timercount', [
            'id' => 'q' . $qnum . '_questiontime'
        ]);

        $rtqQuestion = $attempt->get_question_by_slot($slot);

        if ($rtqQuestion !== false && $rtqQuestion->getTries() > 1 && !$this->rtq->is_instructor()) {

            $count = new stdClass();
            $count->tries = $rtqQuestion->getTries();
            $try_text = html_writer::div(get_string('trycount', 'jazzquiz', $count), 'trycount', [
                'id' => 'q' . $qnum . '_trycount'
            ]);

        } else {

            $try_text = html_writer::div('', 'trycount', [
                'id' => 'q' . $qnum . '_trycount'
            ]);

        }

        // Instructors don't need to save questions
        if (!$this->rtq->is_instructor()) {
            $savebtncont = html_writer::div($savebtn, 'question_save');
        } else {
            $savebtncont = '';
        }

        $output .= html_writer::div($savebtncont . $try_text . $timertext . $timercount, 'save_row');

        // Finish the form.
        $output .= html_writer::end_tag('form');
        $output .= html_writer::end_tag('div');

        return $output;
    }

    private function write_control_button($icon, $text, $id)
    {
        return html_writer::tag('button', '<i class="fa fa-' . $icon . '"></i> ' . $text, [
            'class' => 'btn',
            'id' => $id,
            'onclick' => 'jazzquiz.execute_control_action(\'' . $id . '\');'
        ]);
    }

    private function write_control_buttons($buttons)
    {
        $html = '';
        foreach ($buttons as $button) {
            if (count($button) < 3) {
                continue;
            }
            $html .= $this->write_control_button($button[0], $button[1], $button[2]);
        }
        return $html;
    }

    /**
     * Renders the controls for the quiz for the instructor
     *
     * @return string HTML fragment
     */
    public function render_controls()
    {
        $html = '<div class="quiz-list-buttons quiz-control-buttons hidden">'
            . $this->write_control_buttons([

                ['repeat', 'Re-poll', 'repollquestion'],
                ['bar-chart', 'Vote', 'runvoting'],
                ['edit', 'Improvise', 'startimprovisedquestion'],
                ['bars', 'Jump to', 'jumptoquestion'],
                ['forward', 'Next', 'nextquestion'],
                ['close', 'End', 'endquestion'],
                ['expand', 'Fullscreen', 'showfullscreenresults'],
                ['window-close', 'Quit', 'closesession'],
                ['square-o', 'Responded', 'togglenotresponded'],
                ['square-o', 'Responses', 'toggleresponses'],
                ['square-o', 'Answer', 'showcorrectanswer']

            ])
            . '    <p id="inquizcontrols_state"></p>'
            . '</div>'
            . '<div class="improvise-menu"></div>'

            . '<div class="quiz-list-buttons">'
            .       $this->write_control_button('start', 'Start quiz', 'startquiz')
            .       '<h4 class="inline">No students have joined.</h4>'
            . '</div>';

        return html_writer::div($html, 'btn-hide rtq_inquiz', [
            'id' => 'inquizcontrols'
        ]);
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

            // Hide improvised questions
            if (substr($question->getQuestion()->name, 0, strlen('{IMPROV}')) === '{IMPROV}') {
                continue;
            }

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
        global $CFG;

        // Include classList to add the class List HTML5 for compatibility below IE 10
        $this->page->requires->js('/mod/jazzquiz/js/classList.js');
        $this->page->requires->js('/mod/jazzquiz/js/core.js');

        // add window.onload script manually to handle removing the loading mask
        echo html_writer::start_tag('script', [ 'type' => 'text/javascript' ]);
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

        $jazzquiz = new stdClass();

        // Root values
        $jazzquiz->state = $session->get_session()->status;
        $jazzquiz->is_instructor = $this->rtq->is_instructor();
        $jazzquiz->siteroot = $CFG->wwwroot;

        // Quiz
        $quiz = new stdClass();
        $quiz->activity_id = $this->rtq->getRTQ()->id;
        $quiz->session_id = $session->get_session()->id;
        $quiz->attempt_id = $attempt->id;
        $quiz->session_key = sesskey();
        $quiz->slots = $attempt->getSlots();

        $quiz->questions = [];

        $quiz->resume = new stdClass();

        foreach ($attempt->get_questions() as $q) {

            /** @var \mod_jazzquiz\jazzquiz_question $q */
            $question = new stdClass();
            $question->id = $q->getId();
            $question->questiontime = $q->getQuestionTime();
            $question->tries = $q->getTries();
            $question->question = $q->getQuestion();
            $question->slot = $attempt->get_question_slot($q);

            // If the slot is false, throw exception for invalid question on quiz attempt
            if ($question->slot === false) {
                $a = new stdClass();
                $a->questionname = $q->getQuestion()->name;
                throw new moodle_exception(
                    'invalidquestionattempt',
                    'mod_jazzquiz',
                    '',
                    $a,
                    'invalid slot when building questions array on quiz renderer'
                );
            }

            // Add question to list
            $quiz->questions[$question->slot] = $question;
        }

        $session_state = $session->get_session()->status;

        // Resuming quiz feature
        // This will check if the session has started already and print out
        $quiz->resume->are_we_resuming = false;

        if ($session_state != 'notrunning') {

            $current_question = $session->get_session()->currentquestion;
            $next_start_time = $session->get_session()->nextstarttime;

            switch ($session_state) {

                case 'running':
                    if (empty($current_question)) {
                        break;
                    }

                    // We're in a currently running question
                    $quiz->resume->are_we_resuming = true;
                    $quiz->resume->state = $session_state;
                    $quiz->resume->current_question_slot = $current_question;

                    $nextQuestion = $this->rtq->get_questionmanager()->get_question_with_slot($session->get_session()->currentqnum, $attempt);

                    if ($next_start_time > time()) {

                        // We're wating for question
                        $quiz->resume->action = 'waitforquestion';
                        $quiz->resume->delay = $session->get_session()->nextstarttime - time();
                        $quiz->resume->question_time = $nextQuestion->getQuestionTime();

                    } else {

                        $quiz->resume->action = 'startquestion';

                        // How much time has elapsed since start time

                        // First check if the question has a time limit
                        if ($nextQuestion->getNoTime()) {

                            $quiz->resume->question_time = 0;

                        } else {

                            // Otherwise figure out how much time is left
                            $time_elapsed = time() - $next_start_time;
                            $quiz->resume->question_time = $nextQuestion->getQuestionTime() - $time_elapsed;
                        }

                        // Next check how many tries left
                        $quiz->resume->tries = $attempt->check_tries_left($session->get_session()->currentqnum, $nextQuestion->getTries());
                    }
                    break;

                case 'reviewing':
                case 'endquestion':
                    // If we're reviewing, resume with quiz info of reviewing and just let
                    // set interval capture next question start time
                    $quiz->resume->are_we_resuming = true;
                    $quiz->resume->action = 'reviewing';
                    $quiz->resume->state = $session_state;
                    $quiz->resume->current_question_slot = $current_question;
                    $quiz->question->is_last = $attempt->lastquestion;
                    break;

                case 'preparing':
                case 'voting':
                    $quiz->resume->are_we_resuming = true;
                    $quiz->resume->action = $session_state;
                    $quiz->resume->state = $session_state;
                    $quiz->resume->current_question_slot = $current_question;
                    break;

                default:
                    break;
            }
        }

        // Print data as JSON
        echo html_writer::start_tag('script', array('type' => 'text/javascript'));
        echo "var jazzquiz_root_state = " . json_encode($jazzquiz) . ';';
        echo "var jazzquiz_quiz_state = " . json_encode($quiz) . ';';
        echo html_writer::end_tag('script');

        // Add localization strings
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

        $this->page->requires->strings_for_js([ 'seconds' ], 'moodle');

        // Allow question modifiers to add their own CSS/JS
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
     * @param array $not_responded Array of the people who haven't responded
     * @param int $total
     * @param int $anonymous (0 or 1)
     *
     * @return string HTML fragment for the amount responded
     */
    public function respondedbox($not_responded, $total, $anonymous)
    {
        $responded_count = $total - count($not_responded);

        $output = html_writer::start_div();

        $output .= html_writer::start_div('respondedbox', [ 'id' => 'respondedbox' ]);
        $output .= html_writer::tag('h4', "$responded_count / $total students have responded.", [ 'class' => 'inline' ]);
        $output .= html_writer::end_div();

        // Output the list of students, but only if we're not in anonymous mode
        if (!$anonymous) {
            $output .= html_writer::start_div();
            $output .= html_writer::alist($not_responded, [ 'id' => 'notrespondedlist' ]);
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
            //$params['redirect'] = 'view';
            //$add_improv_questions_url = new moodle_url('/mod/jazzquiz/improvisation.php', $params);
            //$add_improv_questions_button = $this->output->single_button($add_improv_questions_url, 'Add improvisation questions', 'get');
            //echo html_writer::tag('p', $add_improv_questions_button);

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

        $output .= html_writer::start_tag('form', [
            'action' => '',
            'method' => 'post',
            'enctype' => 'multipart/form-data',
            'accept-charset' => 'utf-8',
            'id' => 'q' . $qnum,
            'class' => 'jazzquiz_question',
            'onsubmit' => 'return false;',
            'name' => 'q' . $qnum
        ]);

        $output .= $attempt->render_question($slot, true, 'edit');

        $output .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'slots',
            'value' => $slot
        ]);

        $output .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'slot',
            'value' => $slot
        ]);

        $output .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'action',
            'value' => 'savecomment'
        ]);

        $output .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'sesskey',
            'value' => sesskey()
        ]);

        $save_button = html_writer::empty_tag('input', [
            'type' => 'submit',
            'name' => 'submit',
            'value' => get_string('savequestion', 'jazzquiz'),
            'class' => 'form-submit'
        ]);

        $mark = $attempt->get_slot_mark($slot);
        $max_mark = $attempt->get_slot_max_mark($slot);

        $output .= html_writer::start_tag('p');
        $output .= 'Marked ' . $mark . ' / ' . $max_mark;
        $output .= html_writer::end_tag('p');

        $output .= html_writer::div($save_button, 'save_row');

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

        $output = html_writer::start_div('jazzquizbox', array('id' => 'q' . $qnum . '_container'));
        $output .= $attempt->render_question($slot, true, $this->rtq->get_review_options('after'));
        $output .= html_writer::end_div();

        return $output;
    }

    /** End attempt view rendering **/


}