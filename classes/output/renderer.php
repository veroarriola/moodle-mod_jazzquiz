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
 * To load a question without refreshing the page, we need the JavaScript for the question.
 * Moodle stores this in page_requirements_manager, but there is no way to read the JS that is required.
 * This class takes in the manager and keeps the JS for when we want to get a diff.
 * NOTE: This class is placed here because it will only ever be used by renderer::render_question_form()
 * TODO: Look into removing this class in the future.
 * @package    mod_jazzquiz\output
 * @author     Sebastian S. Gundersen <sebastsg@stud.ntnu.no>
 * @copyright  2018 NTNU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_requirements_diff extends \page_requirements_manager {

    /** @var array $before */
    private $before;

    /**
     * Constructor.
     * @param \page_requirements_manager $manager
     */
    public function __construct($manager) {
        $this->before = $manager->jsinitcode;
    }

    /**
     * Run an array_diff on the required JavaScript when this
     * was constructed and the one passed to this function.
     * @param \page_requirements_manager $manager
     * @return array the JavaScript that was added in-between constructor and this call.
     */
    public function get_js_diff($manager) {
        return array_diff($manager->jsinitcode, $this->before);
    }
}

/**
 * Quiz renderer
 *
 * @package    mod_jazzquiz
 * @author     Sebastian S. Gundersen <sebastsg@stud.ntnu.no>
 * @copyright  2014 University of Wisconsin - Madison
 * @copyright  2018 NTNU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {

    /**
     * Render the header for the page.
     * @param \mod_jazzquiz\jazzquiz $jazzquiz
     * @param string $tab The active tab on the page
     */
    public function header($jazzquiz, $tab = 'view') {
        echo $this->output->header();
        echo jazzquiz_view_tabs($jazzquiz, $tab);
    }

    /**
     * Render the footer for the page.
     */
    public function footer() {
        echo $this->output->footer();
    }

    /**
     * For instructors.
     * @param \moodleform $sessionform
     */
    public function start_session_form($sessionform) {
        echo \html_writer::start_div('jazzquiz-box');
        echo \html_writer::tag('p', get_string('teacher_start_instructions', 'jazzquiz'));
        echo \html_writer::empty_tag('br');
        $sessionform->display();
        echo \html_writer::end_div();
    }

    /**
     * For instructors.
     * @param \mod_jazzquiz\jazzquiz $jazzquiz
     */
    public function continue_session_form($jazzquiz) {
        global $PAGE;
        echo \html_writer::start_div('jazzquiz-box');
        echo \html_writer::tag('p', get_string('instructor_sessions_going', 'jazzquiz'));
        // Output the link for continuing session.
        $path = $PAGE->url->get_path() . '?id=' . $jazzquiz->cm->id . '&quizid=' . $jazzquiz->data->id . '&action=quizstart';
        $gotosession = get_string('goto_session', 'jazzquiz');
        echo '<p><a href="' . $path . '" class="btn btn-secondary">' . $gotosession . '</a></p>';
        echo \html_writer::end_div();
    }

    /**
     * Show the "join quiz" form for students.
     * @param \mod_jazzquiz\forms\view\student_start_form $studentstartform
     * @param \mod_jazzquiz\jazzquiz_session $session
     */
    public function join_quiz_form($studentstartform, $session) {
        echo \html_writer::start_div('jazzquiz-box');
        echo \html_writer::tag('p', get_string('join_quiz_instructions', 'jazzquiz'));
        echo \html_writer::tag('p', '<b>' . get_string('session', 'jazzquiz') . ':</b> ' . $session->data->name);
        if ($session->attempt) {
            echo \html_writer::tag('p', get_string('attempt_started', 'jazzquiz'));
        }
        $studentstartform->display();
        echo \html_writer::end_div();
    }

    /**
     * Show the "quiz not running" page for students.
     * @param int $cmid the course module id for the quiz
     */
    public function quiz_not_running($cmid) {
        global $PAGE;
        echo \html_writer::start_div('jazzquiz-box');
        echo \html_writer::tag('p', get_string('quiz_not_running', 'jazzquiz'));
        $reloadurl = $PAGE->url->get_path() . '?id=' . $cmid;
        echo '<p><a href="' . $reloadurl . '" class="btn btn-secondary">' . get_string('reload') . '</a></p>';
        echo \html_writer::end_div();
    }

    /**
     * Renders the quiz to the page
     * @param \mod_jazzquiz\jazzquiz_session $session
     */
    public function render_quiz($session) {
        $this->init_quiz_js($session);

        $output = \html_writer::start_div('', ['id' => 'jazzquiz']);

        if ($session->jazzquiz->is_instructor()) {
            $output .= \html_writer::div($this->render_controls(), 'jazzquiz-box', ['id' => 'jazzquiz_controls_box']);
        }

        $loading_icon = $this->output->pix_icon('i/loading', 'loading...');

        $output .= \html_writer::start_div('', ['id' => 'jazzquiz_loading']);
        $output .= \html_writer::tag('p', get_string('loading', 'jazzquiz'));
        $output .= $loading_icon;
        $output .= \html_writer::end_div();

        if ($session->jazzquiz->is_instructor()) {
            $output .= \html_writer::div('', 'jazzquiz-box hidden', ['id' => 'jazzquiz_correct_answer_container']);
            $output .= \html_writer::start_div('jazzquiz-box', ['id' => 'jazzquiz_side_container']);
            $output .= \html_writer::div('', 'jazzquiz-box hidden', ['id' => 'jazzquiz_response_info_container']);
        }

        $output .= \html_writer::div('', 'jazzquiz-box hidden', ['id' => 'jazzquiz_question_timer']);

        if ($session->jazzquiz->is_instructor()) {
            $output .= \html_writer::start_div('jazzquiz-box', ['id' => 'jazzquiz_responded_container']);
            $output .= \html_writer::start_div();
            $output .= \html_writer::start_div('', ['id' => 'jazzquiz_responded_box']);
            $output .= \html_writer::tag('h4', '', ['class' => 'inline']);
            $output .= \html_writer::end_div();
            $output .= \html_writer::end_div();
            $output .= \html_writer::end_div();

            $output .= \html_writer::end_div();
        }

        $output .= \html_writer::div('', 'jazzquiz-box hidden', ['id' => 'jazzquiz_info_container']);
        $output .= '<div id="jazzquiz_question_box"></div>';
        if ($session->jazzquiz->is_instructor()) {
            $output .= \html_writer::div('', 'jazzquiz-box hidden', ['id' => 'jazzquiz_responses_container']);
        }

        $output .= \html_writer::end_div();
        echo $output;
    }

    /**
     * Render the question specified by slot
     * @param \mod_jazzquiz\jazzquiz $jazzquiz
     * @param \question_usage_by_activity $quba
     * @param int $slot
     * @param bool $review Whether or not we're reviewing the attempt
     * @param string|\stdClass $reviewoptions Can be string for overall actions like "edit" or an object of review options
     * @return string the HTML fragment for the question
     */
    public function render_question($jazzquiz, $quba, $slot, $review = false, $reviewoptions = '') {
        $displayoptions = $jazzquiz->get_display_options($review, $reviewoptions);
        return $quba->render_question($slot, $displayoptions, $slot);
    }

    /**
     * Render a specific question in its own form so it can be submitted
     * independently of the rest of the questions
     *
     * @param int $slot the id of the question we're rendering
     * @param \mod_jazzquiz\jazzquiz_attempt $attempt
     * @param \mod_jazzquiz\jazzquiz $jazzquiz
     * @param bool $isinstructor
     *
     * @return string[] html, javascript
     */
    public function render_question_form($slot, $attempt, $jazzquiz, $isinstructor) {
        global $PAGE;

        $output = '';

        $isinstructorclass = '';
        if ($isinstructor) {
            $isinstructorclass = ' instructor';
        }
        $output .= \html_writer::start_tag('div', ['class' => 'jazzquiz-box' . $isinstructorclass]);

        $output .= \html_writer::start_tag('form', [
            'id' => 'jazzquiz_question_form',
            'action' => '',
            'method' => 'post',
            'enctype' => 'multipart/form-data',
            'accept-charset' => 'utf-8',
            'onsubmit' => 'return false;'
        ]);

        $differ = new page_requirements_diff($PAGE->requires);
        $output .= $this->render_question($jazzquiz, $attempt->quba, $slot);
        $js = implode("\n", $differ->get_js_diff($PAGE->requires)) . "\n";

        $output .= \html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'slot',
            'value' => $slot
        ]);

        // Only students need to save their answers.
        if (!$isinstructor) {
            $savebutton = \html_writer::tag('button', 'Save', ['class' => 'btn btn-primary']);
            $savebutton = \html_writer::div($savebutton, 'jazzquiz-save-question');
            $output .= \html_writer::div($savebutton, 'save_row');
        }

        $output .= \html_writer::end_tag('form');
        $output .= \html_writer::end_tag('div');

        return [$output, $js];
    }

    /**
     * Render a control button.
     * @param string $icon
     * @param string $text
     * @return string html
     */
    private function write_control_button($icon, $text) {
        $id = "jazzquiz_control_$text";
        $text = get_string($text, 'jazzquiz');
        $contents = '<i class="fa fa-' . $icon . '"></i> ' . $text;
        return \html_writer::tag('button', $contents, [
            'class' => 'btn',
            'id' => $id
        ]);
    }

    /**
     * Render an array of control buttons.
     * @param array $buttons [][icon, text]
     * @return string html
     */
    private function write_control_buttons($buttons) {
        $html = '';
        foreach ($buttons as $button) {
            if (count($button) < 2) {
                continue;
            }
            $html .= $this->write_control_button($button[0], $button[1]);
        }
        return $html;
    }

    /**
     * Renders the controls for the quiz for the instructor.
     * @return string html
     */
    public function render_controls() {
        $html = '<div class="quiz-list-buttons quiz-control-buttons hidden">'
            . $this->write_control_buttons([
                ['repeat', 'repoll'],
                ['bar-chart', 'vote'],
                ['edit', 'improvise'],
                ['bars', 'jump'],
                ['forward', 'next'],
                ['close', 'end'],
                ['expand', 'fullscreen'],
                ['window-close', 'quit'],
                ['square-o', 'responses'],
                ['square-o', 'answer']
            ])
            //. '<p id="jazzquiz_controls_state"></p>'
            . '</div>'
            . '<div id="jazzquiz_improvise_menu" class="start-question-menu"></div>'
            . '<div id="jazzquiz_jump_menu" class="start-question-menu"></div>'

            . '<div class="quiz-list-buttons">'
            . $this->write_control_button('start', 'startquiz')
            . '<h4 class="inline"></h4>'
            . $this->write_control_button('close', 'exit')
            . '</div><div id="jazzquiz_control_separator"></div>';

        return \html_writer::div($html, '', ['id' => 'jazzquiz_controls']);
    }

    /**
     * Initializes JavaScript state and strings for the view page.
     * @param \mod_jazzquiz\jazzquiz_session $session
     */
    public function init_quiz_js($session) {
        global $CFG;

        $this->page->requires->js('/question/qengine.js');
        $this->page->requires->js('/mod/jazzquiz/js/core.js');
        if ($session->jazzquiz->is_instructor()) {
            $this->page->requires->js('/mod/jazzquiz/js/instructor.js');
        } else {
            $this->page->requires->js('/mod/jazzquiz/js/student.js');
        }

        // Add window.onload script manually to handle removing the loading mask
        // TODO: Remove this inline JavaScript.
        echo \html_writer::start_tag('script');
        echo "(function preLoad(){window.addEventListener('load', function(){jazzquiz.initialize();}, false);}());";
        echo \html_writer::end_tag('script');

        // Root values
        $jazzquizjson = new \stdClass();
        $jazzquizjson->isInstructor = $session->jazzquiz->is_instructor();
        $jazzquizjson->siteroot = $CFG->wwwroot;

        // Quiz
        $quizjson = new \stdClass();
        $quizjson->courseModuleId = $session->jazzquiz->cm->id;
        $quizjson->activityId = $session->jazzquiz->data->id;
        $quizjson->sessionId = $session->data->id;
        $quizjson->attemptId = $session->attempt->data->id;
        $quizjson->sessionKey = sesskey();
        if ($session->jazzquiz->is_instructor()) {
            $quizjson->totalStudents = $session->get_student_count();
            $quizjson->totalQuestions = count($session->jazzquiz->questions);
        }

        // Print data as JSON
        echo \html_writer::start_tag('script');
        echo "var jazzquizRootState = " . json_encode($jazzquizjson) . ';';
        echo "var jazzquizQuizState = " . json_encode($quizjson) . ';';
        echo \html_writer::end_tag('script');

        // Add localization strings
        $this->page->requires->strings_for_js([
            'question_will_start_in_x_seconds',
            'question_will_start_now',
            'closing_session',
            'session_closed',
            'question_will_end_in_x_seconds',
            'answer',
            'responses',
            'responded',
            'wait_for_instructor',
            'instructions_for_student',
            'instructions_for_instructor',
            'no_students_have_joined',
            'one_student_has_joined',
            'x_students_have_joined',
            'click_to_show_original_results',
            'click_to_show_vote_results',
            'showing_vote_results',
            'showing_original_results',
            'failed_to_end_question',
            'error_getting_vote_results',
            'a_out_of_b_voted',
            'a_out_of_b_responded',
            'error_starting_vote',
            'error_getting_current_results',
            'error_with_request',
            'x_seconds_left',
            'error_saving_vote',
            'you_already_voted',
        ], 'jazzquiz');
    }

}
