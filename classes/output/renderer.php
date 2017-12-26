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

namespace mod_jazzquiz\output;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/questionlib.php');

/**
 * Quiz renderer
 *
 * @package     mod_jazzquiz
 * @author      John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright   2014 University of Wisconsin - madison
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base
{
    /** @var \mod_jazzquiz\jazzquiz $jazzquiz */
    protected $jazzquiz;

    /** @var array Message to display with the page, is array with the first param being the type of message
     *              the second param being the message
     */
    protected $pageMessage;

    /**
     * @param \mod_jazzquiz\jazzquiz $jazzquiz
     */
    public function set_jazzquiz($jazzquiz)
    {
        $this->jazzquiz = $jazzquiz;
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
        $this->pageMessage = [ $type, $message ];
    }

    /**
     * Base header function to do basic header rendering
     *
     * @param string $tab the current tab to show as active
     */
    public function base_header($tab = 'view')
    {
        echo $this->output->header();
        echo jazzquiz_view_tabs($this->jazzquiz, $tab);
        $this->showMessage();
    }

    /**
     * Base footer function to do basic footer rendering
     */
    public function base_footer()
    {
        echo $this->output->footer();
    }

    /**
     * shows a message if there is one
     */
    protected function showMessage()
    {
        if (empty($this->pageMessage) || !is_array($this->pageMessage)) {
            return;
        }
        switch ($this->pageMessage[0]) {
            case 'error':
                echo $this->output->notification($this->pageMessage[1], 'notifyproblem');
                break;
            case 'success':
                echo $this->output->notification($this->pageMessage[1], 'notifysuccess');
                break;
            case 'info':
                echo $this->output->notification($this->pageMessage[1], 'notifyinfo');
                break;
            default:
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

    /**
     * Basic header for the view page
     *
     * @param bool $rendering_quiz
     */
    public function view_header($rendering_quiz = false)
    {
        $this->base_header('view');
    }

    /**
     * Instructor landing page for the quiz.
     * @param \moodleform $session_form
     * @param bool $session_started true if there is a session already
     */
    public function view_inst_home($session_form, $session_started)
    {
        global $PAGE;

        echo \html_writer::start_div('jazzquizbox');

        if ($session_started) {
            // Show relevant instructions
            echo \html_writer::tag('p', get_string('instructor_sessions_going', 'jazzquiz'));
            // Output the link for continuing session
            $course_module_id = $this->jazzquiz->course_module->id;
            $jazzquiz_id = $this->jazzquiz->data->id;
            $path = $PAGE->url->get_path() . '?id=' . $course_module_id . '&quizid=' . $jazzquiz_id . '&action=quizstart';
            $goto_session = get_string('goto_session', 'jazzquiz');
            echo '<p><a href="' . $path . '" class="btn btn-secondary">' . $goto_session . '</a></p>';
        } else {
            echo \html_writer::tag('p', get_string('teacher_start_instructions', 'jazzquiz'));
            echo \html_writer::empty_tag('br');
            $session_form->display();
        }

        echo \html_writer::end_div();
    }

    /**
     * Student landing page for the quiz.
     * @param \mod_jazzquiz\forms\view\student_start_form $student_start_form
     * @param \mod_jazzquiz\jazzquiz_session $session
     */
    public function view_student_home($student_start_form, $session)
    {
        global $PAGE;

        echo \html_writer::start_div('jazzquizbox');

        $course_module_id = $this->jazzquiz->course_module->id;

        // Check if there is an open session
        if ($session->data) {
            // Show the join quiz button
            echo \html_writer::tag('p', get_string('join_quiz_instructions', 'jazzquiz'));
            echo \html_writer::tag('p', get_string('session_name_text', 'jazzquiz') . $session->data->name);
            // See if the user has attempts
            // If so, let them know that continuing will continue them to their attempt
            if ($session->get_open_attempt_for_current_user()) {
                echo \html_writer::tag('p', get_string('attempt_started', 'jazzquiz'), ['id' => 'quizinfobox']);
            }
            // Add the student join quiz form
            $student_start_form->display();

        } else {
            echo \html_writer::tag('p', get_string('quiz_not_running', 'jazzquiz'));
            // Show a reload page button to make it easy to reload page
            $reload_url = $PAGE->url->get_path() . '?id=' . $course_module_id;
            echo '<p><a href="' . $reload_url . '" class="btn btn-secondary">' . get_string('reload') . '</a></p>';
        }

        echo \html_writer::end_div();

        if (count($this->jazzquiz->get_closed_sessions()) == 0) {
            // Return early if there are no closed sessions
            return;
        }

        echo \html_writer::start_div('jazzquizbox');
        echo \html_writer::end_div();
    }

    /**
     * Renders the quiz to the page
     *
     * @param \mod_jazzquiz\jazzquiz_attempt $attempt
     * @param \mod_jazzquiz\jazzquiz_session $session
     */
    public function render_quiz($attempt, $session)
    {
        $this->init_quiz_js($attempt, $session);

        $output = \html_writer::start_div('', ['id' => 'quizview']);

        if ($this->jazzquiz->is_instructor()) {
            $output .= \html_writer::div($this->render_controls(), 'jazzquizbox hidden', ['id' => 'controlbox']);
            $output .= $this->render_jumpto_modal($attempt);
            $instructions = get_string('instructions_for_instructor', 'jazzquiz');
        } else {
            $instructions = get_string('instructions_for_student', 'jazzquiz');
        }

        $loading_icon = $this->output->pix_icon('i/loading', 'loading...');

        $output .= \html_writer::start_div('jazzquizloading', ['id' => 'loadingbox']);
        $output .= \html_writer::tag('p', get_string('loading', 'jazzquiz'), ['id' => 'loadingtext']);
        $output .= $loading_icon;
        $output .= \html_writer::end_div();

        $output .= \html_writer::div($instructions, 'jazzquizbox hidden', ['id' => 'jazzquiz_instructions_container']);

        if ($this->jazzquiz->is_instructor()) {
            $output .= \html_writer::div('', 'jazzquizbox padded-box hidden', ['id' => 'jazzquiz_correct_answer_container']);
            $output .= \html_writer::start_div('jazzquizbox', ['id' => 'jazzquiz_side_container']);
            $output .= \html_writer::div('', 'jazzquizbox hidden padded-box', ['id' => 'jazzquiz_response_info_container']);
        }

        $output .= \html_writer::div('', 'jazzquizbox hidden', ['id' => 'jazzquiz_question_timer']);

        if ($this->jazzquiz->is_instructor()) {
            $output .= \html_writer::start_div('jazzquizbox', ['id' => 'jazzquiz_responded_container']);
            $output .= \html_writer::start_div();
            $output .= \html_writer::start_div('respondedbox', ['id' => 'respondedbox']);
            $output .= \html_writer::tag('h4', '', ['class' => 'inline']);
            $output .= \html_writer::end_div();
            $output .= \html_writer::end_div();
            $output .= \html_writer::end_div();

            $output .= \html_writer::end_div();
        }

        $output .= \html_writer::div('', 'jazzquizbox padded-box hidden', ['id' => 'jazzquiz_info_container']);

        // JavaScript will place the forms in here.
        $output .= '<div id="jazzquiz_question_forms"></div>';

        if ($this->jazzquiz->is_instructor()) {
            $output .= \html_writer::div('', 'jazzquizbox hidden', ['id' => 'jazzquiz_responses_container']);
        }

        $output .= \html_writer::end_div();
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

        $is_instructor_class = '';
        if ($this->jazzquiz->is_instructor()) {
            $is_instructor_class = ' instructor';
        }
        $output .= \html_writer::start_tag('div', [
            'class' => 'jazzquizbox hidden' . $is_instructor_class,
            'id' => 'jazzquiz_question_box'
        ]);

        $on_submit = '';
        if (!$this->jazzquiz->is_instructor()) {
            $on_submit .= 'jazzquiz.save_question();';
        }
        $on_submit .= 'return false;';

        $output .= \html_writer::start_tag('form', [
            'action' => '',
            'method' => 'post',
            'enctype' => 'multipart/form-data',
            'accept-charset' => 'utf-8',
            'onsubmit' => $on_submit
        ]);

        $output .= $attempt->render_question($slot);

        $output .= \html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'slot',
            'value' => $slot
        ]);

        // Instructors don't need to save questions
        $save_button = '';
        if (!$this->jazzquiz->is_instructor()) {
            $save_button = \html_writer::tag('button', 'Save', [
                'class' => 'btn btn-primary',
                'onclick' => 'jazzquiz.submit_answer(); return false;'
            ]);
            $save_button = \html_writer::div($save_button, 'question_save');
        }

        $output .= \html_writer::div($save_button, 'save_row');

        $output .= \html_writer::end_tag('form');
        $output .= \html_writer::end_tag('div');

        return $output;
    }

    private function write_control_button($icon, $text, $id)
    {
        $text = get_string($text, 'jazzquiz');
        return \html_writer::tag('button', '<i class="fa fa-' . $icon . '"></i> ' . $text, [
            'class' => 'btn',
            'id' => $id,
            'onclick' => "jazzquiz.execute_control_action('$id');"
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
                ['repeat', 'repoll', 'repollquestion'],
                ['bar-chart', 'vote', 'runvoting'],
                ['edit', 'improvise', 'startimprovisedquestion'],
                ['bars', 'jump', 'jumptoquestion'],
                ['forward', 'next', 'nextquestion'],
                ['close', 'end', 'endquestion'],
                ['expand', 'fullscreen', 'showfullscreenresults'],
                ['window-close', 'quit', 'closesession'],
                ['square-o', 'responses', 'toggleresponses'],
                ['square-o', 'answer', 'showcorrectanswer']
            ])
            . '<p id="inquizcontrols_state"></p>'
            . '</div>'
            . '<div class="improvise-menu"></div>'

            . '<div class="quiz-list-buttons">'
            . $this->write_control_button('start', 'startquiz', 'startquiz')
            . '<h4 class="inline">No students have joined.</h4>'
            . $this->write_control_button('close', 'quit', 'exitquiz')
            . '</div><div id="jazzquiz_control_separator"></div>';

        return \html_writer::div($html, 'btn-hide rtq_inquiz', [
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
        $output = \html_writer::start_div('modalDialog', [
            'id' => 'jumptoquestion-dialog'
        ]);

        $output .= \html_writer::start_div();
        $output .= \html_writer::tag('a', 'X', [
            'class' => 'jumptoquestionclose',
            'href' => '#'
        ]);

        $output .= \html_writer::tag('h2', get_string('jump', 'jazzquiz'));
        $output .= \html_writer::tag('p', get_string('jump_question_instructions', 'jazzquiz'));

        // Build our own select for the user to select the question they want to go to
        $output .= \html_writer::start_tag('select', [
            'name' => 'jtq-selectquestion',
            'id' => 'jtq-selectquestion'
        ]);

        // Loop through each question and add it as an option
        foreach ($attempt->jazzquiz->questions as $question) {
            $output .= \html_writer::tag('option', $question->question->name, [
                'value' => $question->question->id
            ]);
        }
        $output .= \html_writer::end_tag('select');
        $output .= \html_writer::tag('button', get_string('jump', 'jazzquiz'), [
            'onclick' => 'jazzquiz.jump_question()'
        ]);
        $output .= \html_writer::end_div();
        $output .= \html_writer::end_div();

        return $output;
    }

    /**
     * Initializes quiz javascript and strings for javascript when on the
     * quiz view page, or the "quizstart" action
     *
     * @param \mod_jazzquiz\jazzquiz_attempt $attempt
     * @param \mod_jazzquiz\jazzquiz_session $session
     */
    public function init_quiz_js($attempt, $session)
    {
        global $CFG;

        // Include classList to add the class List HTML5 for compatibility below IE 10
        $this->page->requires->js('/mod/jazzquiz/js/classList.js');
        $this->page->requires->js('/mod/jazzquiz/js/core.js');

        // Add window.onload script manually to handle removing the loading mask
        // TODO: Remove this inline JavaScript.
        echo \html_writer::start_tag('script');
        echo "(function preLoad(){window.addEventListener('load', function(){jazzquiz.quiz_page_loaded();}, false);}());";
        echo \html_writer::end_tag('script');

        if ($this->jazzquiz->is_instructor()) {
            $this->page->requires->js('/mod/jazzquiz/js/instructor.js');
        } else {
            $this->page->requires->js('/mod/jazzquiz/js/student.js');
        }

        $jazzquiz = new \stdClass();

        // Root values
        $jazzquiz->state = $session->data->status;
        $jazzquiz->is_instructor = $this->jazzquiz->is_instructor();
        $jazzquiz->siteroot = $CFG->wwwroot;

        // Quiz
        $quiz = new \stdClass();
        $quiz->course_module_id = $this->jazzquiz->course_module->id;
        $quiz->activity_id = $this->jazzquiz->data->id;
        $quiz->session_id = $session->data->id;
        $quiz->attempt_id = $attempt->data->id;
        $quiz->session_key = sesskey();
        $quiz->slots = $attempt->quba->get_slots();
        $quiz->total_students = $session->get_student_count();

        $quiz->questions = [];
        $quiz->resume = new \stdClass();

        $ordered_jazzquiz_questions = $attempt->jazzquiz->questions;
        $slot = 1;
        foreach ($ordered_jazzquiz_questions as $q) {
            $question = new \stdClass();
            $question->id = $q->data->id;
            $question->questiontime = $q->data->questiontime;
            $question->question_id = $q->question;
            $question->slot = $slot;
            $quiz->questions[$question->slot] = $question;
            $slot++;
        }

        $session_state = $session->data->status;

        // Resuming quiz feature
        // This will check if the session has started already and print out
        $quiz->resume->are_we_resuming = false;

        if ($session_state != 'notrunning') {

            $current_slot = $session->data->slot;
            $next_start_time = $session->data->nextstarttime;

            switch ($session_state) {

                case 'running':
                    if (empty($current_slot)) {
                        break;
                    }
                    // We're in a currently running question
                    // TODO: Fix this nextQuestion stuff
                    $quiz->resume->are_we_resuming = true;
                    $quiz->resume->state = $session_state;
                    $quiz->resume->current_question_slot = $current_slot;
                    $nextQuestion = $this->jazzquiz->get_question_with_slot($session->data->slot, $attempt);
                    if ($next_start_time > time()) {
                        // We're waiting for question
                        $quiz->resume->action = 'waitforquestion';
                        $quiz->resume->delay = $session->data->nextstarttime - time();
                        $quiz->resume->question_time = $nextQuestion->data->questiontime;
                    } else {
                        $quiz->resume->action = 'startquestion';
                        // How much time has elapsed since start time
                        // First check if the question has a time limit
                        if ($nextQuestion->data->notime) {
                            $quiz->resume->question_time = 0;
                        } else {
                            // Otherwise figure out how much time is left
                            $time_elapsed = time() - $next_start_time;
                            $quiz->resume->question_time = $nextQuestion->data->questiontime - $time_elapsed;
                        }
                    }
                    break;

                case 'reviewing':
                    // If we're reviewing, resume with quiz info of reviewing and just let
                    // set interval capture next question start time
                    $quiz->resume->are_we_resuming = true;
                    $quiz->resume->action = 'reviewing';
                    $quiz->resume->state = $session_state;
                    $quiz->resume->current_question_slot = $current_slot;
                    break;

                case 'preparing':
                case 'voting':
                    $quiz->resume->are_we_resuming = true;
                    $quiz->resume->action = $session_state;
                    $quiz->resume->state = $session_state;
                    $quiz->resume->current_question_slot = $current_slot;
                    break;

                default:
                    break;
            }
        }

        // Print data as JSON
        echo \html_writer::start_tag('script');
        echo "var jazzquiz_root_state = " . json_encode($jazzquiz) . ';';
        echo "var jazzquiz_quiz_state = " . json_encode($quiz) . ';';
        echo \html_writer::end_tag('script');

        // Add localization strings
        $this->page->requires->strings_for_js([
            'question_will_start',
            'in',
            'now',
            'gathering_results',
            'closing_session',
            'session_closed',
            'question_will_end_in',
            'wait_for_reviewing_to_end',
            'answer',
            'responses',
            'responded',
            'wait_for_instructor'
        ], 'jazzquiz');

        $this->page->requires->strings_for_js([
            'seconds'
        ], 'moodle');
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
        $output = \html_writer::start_div();
        $output .= \html_writer::start_div('respondedbox', ['id' => 'respondedbox']);
        $output .= \html_writer::tag('h4', "$responded_count / $total students have responded.", [
            'class' => 'inline'
        ]);
        $output .= \html_writer::end_div();
        $output .= \html_writer::end_div();
        return $output;
    }

    /**
     * No questions view
     *
     * @param bool $is_instructor
     */
    public function no_questions($is_instructor)
    {
        echo $this->output->box_start('generalbox boxaligncenter jazzquizbox');
        echo \html_writer::tag('p', get_string('no_questions', 'jazzquiz'));

        if ($is_instructor) {
            // "Edit quiz" button
            $params = [
                'cmid' => $this->jazzquiz->course_module->id
            ];
            $editurl = new \moodle_url('/mod/jazzquiz/edit.php', $params);
            $editbutton = $this->output->single_button($editurl, get_string('edit', 'jazzquiz'), 'get');
            echo \html_writer::tag('p', $editbutton);
        }
        echo $this->output->box_end();
    }

    /**
     * Basic footer for the view page
     */
    public function view_footer()
    {
        $this->base_footer();
    }

}
