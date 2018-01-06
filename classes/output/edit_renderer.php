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

defined('MOODLE_INTERNAL') || die();

/**
 * Renderer outputting the quiz editing UI.
 *
 * @package   mod_jazzquiz
 * @author    Sebastian S. Gundersen <sebastsg@stud.ntnu.no>
 * @copyright 2016 University of Wisconsin - Madison
 * @copyright 2018 NTNU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_renderer extends \plugin_renderer_base {

    /**
     * Prints edit page header
     * @param \mod_jazzquiz\jazzquiz $jazzquiz
     */
    public function header($jazzquiz) {
        echo $this->output->header();
        echo jazzquiz_view_tabs($jazzquiz, 'edit');
        echo $this->output->box_start('generalbox boxaligncenter jazzquiz-box');
    }

    /**
     * Ends the edit page with the footer of Moodle
     */
    public function footer() {
        echo $this->output->box_end();
        echo $this->output->footer();
    }

    /**
     * Render the list questions view for the edit page
     *
     * @param \mod_jazzquiz\jazzquiz $jazzquiz
     * @param array $questions Array of questions
     * @param string $questionbankview HTML for the question bank view
     * @param \moodle_url $url
     */
    public function listquestions($jazzquiz, $questions, $questionbankview, $url) {
        global $CFG;

        echo \html_writer::start_div('row', ['id' => 'questionrow']);
        echo \html_writer::start_div('inline-block span6');
        echo \html_writer::tag('h2', get_string('questions', 'jazzquiz'));
        echo $this->show_questionlist($questions, $url);
        echo \html_writer::end_div();
        echo \html_writer::start_div('inline-block span6');
        echo $questionbankview;
        echo \html_writer::end_div();
        echo \html_writer::end_div();

        $this->page->requires->js('/mod/jazzquiz/js/core.js');
        $this->page->requires->js('/mod/jazzquiz/js/sortable/sortable.min.js');
        $this->page->requires->js('/mod/jazzquiz/js/edit_quiz.js');

        $jazzquizjson = new \stdClass();
        $jazzquizjson->siteroot = $CFG->wwwroot;

        $quizjson = new \stdClass();
        $quizjson->courseModuleId = $jazzquiz->cm->id;
        $quizjson->activityId = $jazzquiz->data->id;
        $quizjson->sessionKey = sesskey();

        echo '<script>';
        echo 'var jazzquizRootState = ' . json_encode($jazzquizjson) . ';';
        echo 'var jazzquizQuizState = ' . json_encode($quizjson) . ';';
        echo '</script>';

        $this->page->requires->strings_for_js([
            'success',
            'error'
        ], 'core');
    }

    /**
     * Builds the question list from the questions passed in
     *
     * @param \mod_jazzquiz\jazzquiz_question[] $questions
     * @param \moodle_url $url
     * @return string
     */
    protected function show_questionlist($questions, $url) {
        $return = '<ol class="questionlist">';
        $questionnumber = 1;
        foreach ($questions as $question) {
            $return .= '<li data-question-id="' . $question->data->id . '">';
            $return .= $this->display_question_block($question, $questionnumber, count($questions), $url);
            $return .= '</li>';
            $questionnumber++;
        }
        $return .= '</ol>';
        return $return;
    }

    /**
     * sets up what is displayed for each question on the edit quiz question listing
     *
     * @param \mod_jazzquiz\jazzquiz_question $question
     * @param int $slot
     * @param int $questioncount
     * @param \moodle_url $url
     * @return string
     */
    protected function display_question_block($question, $slot, $questioncount, $url) {
        $return = '';

        $dragicon = new \pix_icon('i/dragdrop', 'dragdrop');
        $return .= \html_writer::div($this->output->render($dragicon), 'dragquestion');
        $return .= \html_writer::div(print_question_icon($question->question), 'icon');

        $namehtml = '<p>' . $question->question->name . '</p>';
        $return .= \html_writer::div($namehtml, 'name');
        $controlhtml = '';

        $spacericon = new \pix_icon('spacer', 'space', null, ['class' => 'smallicon space']);

        // If we're on a later question than the first one add the move up control
        if ($slot > 1) {
            $alt = get_string('question_move_up', 'mod_jazzquiz', $slot);
            $upicon = new \pix_icon('t/up', $alt);
            $data = 'data-action="up" data-question-id="' . $question->data->id . '"';
            $controlhtml .= '<a class="edit-question-action"' . $data . '>' . $this->output->render($upicon) . '</a>';
        } else {
            $controlhtml .= $this->output->render($spacericon);
        }

        // if we're not on the last question add the move down control
        if ($slot < $questioncount) {
            $alt = get_string('question_move_down', 'mod_jazzquiz', $slot);
            $downicon = new \pix_icon('t/down', $alt);
            $data = 'data-action="down" data-question-id="' . $question->data->id . '"';
            $controlhtml .= '<a class="edit-question-action"' . $data . '>' . $this->output->render($downicon) . '</a>';
        } else {
            $controlhtml .= $this->output->render($spacericon);
        }

        // Always add edit and delete icons
        $editurl = clone($url);
        $editurl->param('action', 'editquestion');
        $editurl->param('questionid', $question->data->id);
        $alt = get_string('edit_question', 'jazzquiz', $slot);
        $editicon = new \pix_icon('t/edit', $alt);
        $controlhtml .= \html_writer::link($editurl, $this->output->render($editicon));

        $alt = get_string('delete_question', 'mod_jazzquiz', $slot);
        $deleteicon = new \pix_icon('t/delete', $alt);
        $data = 'data-action="delete" data-question-id="' . $question->data->id . '"';
        $controlhtml .= '<a class="edit-question-action"' . $data . '>' . $this->output->render($deleteicon) . '</a>';
        $return .= \html_writer::div($controlhtml, 'controls');
        return $return;
    }

    public function session_is_open_error() {
        echo \html_writer::tag('h3', get_string('edit_page_open_session_error', 'jazzquiz'));
    }

}
