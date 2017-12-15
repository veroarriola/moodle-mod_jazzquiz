<?php

namespace mod_jazzquiz\output;

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

/**
 * Renderer outputting the quiz editing UI.
 *
 * @package mod_jazzquiz
 * @copyright 2016 John Hoopes <john.z.hoopes@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_jazzquiz\traits\renderer_base;

defined('MOODLE_INTERNAL') || die();

class edit_renderer extends \plugin_renderer_base
{
    use renderer_base;

    /**
     * Prints edit page header
     */
    public function print_header()
    {
        $this->base_header('edit');
        echo $this->output->box_start('generalbox boxaligncenter jazzquizbox');
    }

    /**
     * Render the list questions view for the edit page
     *
     * @param array $questions Array of questions
     * @param string $questionbankview HTML for the question bank view
     */
    public function listquestions($questions, $questionbankview, $url)
    {
        global $CFG;

        echo \html_writer::start_div('row', [
            'id' => 'questionrow'
        ]);
        echo \html_writer::start_div('inline-block span6');
        echo \html_writer::tag('h2', get_string('questions', 'jazzquiz'));
        echo \html_writer::div('', 'rtqstatusbox rtqhiddenstatus', [
            'id' => 'editstatus'
        ]);
        echo $this->show_questionlist($questions, $url);
        echo \html_writer::end_div();
        echo \html_writer::start_div('inline-block span6');
        echo $questionbankview;
        echo \html_writer::end_div();
        echo \html_writer::end_div();

        $this->page->requires->js('/mod/jazzquiz/js/core.js');
        $this->page->requires->js('/mod/jazzquiz/js/sortable/sortable.min.js');
        $this->page->requires->js('/mod/jazzquiz/js/edit_quiz.js');

        $jazzquiz = new \stdClass();
        $jazzquiz->siteroot = $CFG->wwwroot;

        $quiz = new \stdClass();
        $quiz->course_module_id = $this->jazzquiz->course_module->id;
        $quiz->activity_id = $this->jazzquiz->getRTQ()->id;
        $quiz->session_key = sesskey();

        echo '<script>';
        echo 'var jazzquiz_root_state = ' . json_encode($jazzquiz) . ';';
        echo 'var jazzquiz_quiz_state = ' . json_encode($quiz) . ';';
        echo '</script>';

        $this->page->requires->strings_for_js([
            'success',
            'error'
        ], 'core');
    }

    /**
     * Builds the question list from the questions passed in
     *
     * @param array $questions an array of \mod_jazzquiz\jazzquiz_question
     * @return string
     */
    protected function show_questionlist($questions, $url)
    {
        $return = '<ol class="questionlist">';
        $question_count = count($questions);
        $question_number = 1;
        foreach ($questions as $question) {
            // Hide improvised questions
            if (substr($question->getQuestion()->name, 0, strlen('{IMPROV}')) === '{IMPROV}') {
                continue;
            }
            /** @var \mod_jazzquiz\jazzquiz_question $question */
            $return .= '<li data-questionid="' . $question->getId() . '">';
            $return .= $this->display_question_block($question, $question_number, $question_count, $url);
            $return .= '</li>';
            $question_number++;
        }
        $return .= '</ol>';
        return $return;
    }

    /**
     * sets up what is displayed for each question on the edit quiz question listing
     *
     * @param \mod_jazzquiz\jazzquiz_question $question
     * @param int $qnum The question number we're currently on
     * @param int $qcount The total number of questions
     *
     * @return string
     */
    protected function display_question_block($question, $qnum, $qcount, $url)
    {
        $return = '';

        $drag_icon = new \pix_icon('i/dragdrop', 'dragdrop');
        $return .= \html_writer::div($this->output->render($drag_icon), 'dragquestion');
        $return .= \html_writer::div(print_question_icon($question->getQuestion()), 'icon');

        $name_html = '<p>' . $question->getQuestion()->name . '</p>';
        $return .= \html_writer::div($name_html, 'name');
        $controlHTML = '';

        $spacer_icon = new \pix_icon('spacer', 'space', null, [
            'class' => 'smallicon space'
        ]);

        // If we're on a later question than the first one add the move up control
        if ($qnum > 1) {
            $alt = get_string('question_move_up', 'mod_jazzquiz', $qnum);
            $up_icon = new \pix_icon('t/up', $alt);
            $data = 'data-action="moveup" data-question-id="' . $question->getId() . '"';
            $controlHTML .= '<a class="edit-question-action"' . $data . '>' . $this->output->render($up_icon). '</a>';
        } else {
            $controlHTML .= $this->output->render($spacer_icon);
        }

        // if we're not on the last question add the move down control
        if ($qnum < $qcount) {
            $alt = get_string('question_move_down', 'mod_jazzquiz', $qnum);
            $down_icon = new \pix_icon('t/down', $alt);
            $data = 'data-action="movedown" data-question-id="' . $question->getId() . '"';
            $controlHTML .= '<a class="edit-question-action"' . $data . '>' . $this->output->render($down_icon). '</a>';
        } else {
            $controlHTML .= $this->output->render($spacer_icon);
        }

        // Always add edit and delete icons
        $edit_url = clone($url);
        $edit_url->param('action', 'editquestion');
        $edit_url->param('questionid', $question->getId());
        $alt = get_string('edit_question', 'jazzquiz', $qnum);
        $delete_icon = new \pix_icon('t/edit', $alt);
        $controlHTML .= \html_writer::link($edit_url, $this->output->render($delete_icon));

        $alt = get_string('delete_question', 'mod_jazzquiz', $qnum);
        $delete_icon = new \pix_icon('t/delete', $alt);
        $data = 'data-action="deletequestion" data-question-id="' . $question->getId() . '"';
        $controlHTML .= '<a class="edit-question-action"' . $data . '>' . $this->output->render($delete_icon). '</a>';
        $return .= \html_writer::div($controlHTML, 'controls');
        return $return;
    }

    /**
     * renders the add question form
     *
     * @param moodleform $mform
     */
    public function addquestionform($mform)
    {
        echo $mform->display();
    }

    public function opensession()
    {
        echo \html_writer::tag('h3', get_string('edit_page_open_session_error', 'jazzquiz'));
    }

    /**
     * Ends the edit page with the footer of Moodle
     */
    public function footer()
    {
        echo $this->output->box_end();
        $this->base_footer();
    }

}
