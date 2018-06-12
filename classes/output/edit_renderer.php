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
    public function list_questions($jazzquiz, $questions, $questionbankview, $url) {
        global $CFG;

        $slot = 1;
        $list = [];
        foreach ($questions as $question) {
            $editurl = clone($url);
            $editurl->param('action', 'editquestion');
            $editurl->param('questionid', $question->data->id);
            $list[] = [
                'id' => $question->data->id,
                'name' => $question->question->name,
                'first' => $slot === 1,
                'last' => $slot === count($questions),
                'slot' => $slot,
                'editurl' => $editurl,
                'icon' => print_question_icon($question->question)
            ];
            $slot++;
        }

        echo $this->render_from_template('jazzquiz/edit_question_list', [
            'questions' => $list,
            'qbank' => $questionbankview
        ]);

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

    public function session_is_open_error() {
        echo \html_writer::tag('h3', get_string('edit_page_open_session_error', 'jazzquiz'));
    }

}
