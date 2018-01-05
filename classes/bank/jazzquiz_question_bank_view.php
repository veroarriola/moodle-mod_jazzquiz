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

namespace mod_jazzquiz\bank;

defined('MOODLE_INTERNAL') || die();

/**
 * Subclass of the question bank view class to change the way it works/looks
 *
 * @package     mod_jazzquiz
 * @author      John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright   2014 University of Wisconsin - Madison
 * @copyright   2018 NTNU
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jazzquiz_question_bank_view extends \core_question\bank\view {
    /**
     * Define the columns we want to be displayed on the question bank
     *
     * @return array
     */
    protected function wanted_columns() {
        // Full class names for question bank columns
        $columns = [
            '\\mod_jazzquiz\\bank\\question_bank_add_to_jazzquiz_action_column',
            'core_question\\bank\\checkbox_column',
            'core_question\\bank\\question_type_column',
            'core_question\\bank\\question_name_column',
            'core_question\\bank\\preview_action_column'
        ];
        foreach ($columns as $column) {
            $this->requiredcolumns[$column] = new $column($this);
        }
        return $this->requiredcolumns;
    }

    /**
     * Shows the question bank editing interface.
     *
     * The function also processes a number of actions:
     *
     * Actions affecting the question pool:
     * move           Moves a question to a different category
     * deleteselected Deletes the selected questions from the category
     * Other actions:
     * category      Chooses the category
     * displayoptions Sets display options
     */
    public function display($tab_name, $page, $per_page, $cat, $recurse, $show_hidden, $show_question_text) {
        global $OUTPUT;

        if ($this->process_actions_needing_ui()) {
            return;
        }

        $edit_contexts = $this->contexts->having_one_edit_tab_cap($tab_name);

        // Category selection form.
        echo $OUTPUT->heading(get_string('questionbank', 'question'), 2);
        array_unshift($this->searchconditions, new \core_question\bank\search\hidden_condition(!$show_hidden));
        array_unshift($this->searchconditions, new \core_question\bank\search\category_condition($cat, $recurse, $edit_contexts, $this->baseurl, $this->course));
        array_unshift($this->searchconditions, new jazzquiz_disabled_condition());
        $this->display_options_form($show_question_text, '/mod/jazzquiz/edit.php');

        // Continues with list of questions.
        $this->display_question_list(
            $this->contexts->having_one_edit_tab_cap($tab_name),
            $this->baseurl,
            $cat,
            $this->cm,
            null,
            $page,
            $per_page,
            $show_hidden,
            $show_question_text,
            $this->contexts->having_cap('moodle/question:add')
        );
    }

    /**
     * Generate an "add to quiz" url so that when clicked the question will be added to the quiz
     *
     * @param int $question_id
     *
     * @return \moodle_url Moodle url to add the question
     */
    public function get_add_to_jazzquiz_url($question_id) {
        $params = $this->baseurl->params();
        $params['questionid'] = $question_id;
        $params['action'] = 'addquestion';
        $params['sesskey'] = sesskey();
        return new \moodle_url('/mod/jazzquiz/edit.php', $params);
    }

    /**
     * This has been taken from the base class to allow us to call our own version of
     * create_new_question_button.
     *
     * @param $category
     * @param $can_add
     * @throws \coding_exception
     */
    protected function create_new_question_form($category, $can_add) {
        echo '<div class="createnewquestion">';
        if ($can_add) {
            $this->create_new_question_button($category->id, $this->editquestionurl->params(), get_string('create_new_question', 'jazzquiz'));
        } else {
            print_string('nopermissionadd', 'question');
        }
        echo '</div>';
    }

    /**
     * Print a button for creating a new question. This will open question/addquestion.php,
     * which in turn goes to question/question.php before getting back to $params['returnurl']
     * (by default the question bank screen).
     *
     * This has been taken from question/editlib.php and adapted to allow us to use the $allowedqtypes
     * param on print_choose_qtype_to_add_form
     *
     * @param int $category_id The id of the category that the new question should be added to.
     * @param array $params Other parameters to add to the URL. You need either $params['cmid'] or
     *      $params['courseid'], and you should probably set $params['returnurl']
     * @param string $caption the text to display on the button.
     * @param string $tooltip a tooltip to add to the button (optional).
     * @param bool $disabled if true, the button will be disabled.
     */
    private function create_new_question_button($category_id, $params, $caption, $tooltip = '', $disabled = false) {
        global $OUTPUT;
        static $choice_form_printed = false;

        $config = get_config('jazzquiz');
        $enabled_types = explode(',', $config->enabledqtypes);

        $params['category'] = $category_id;
        $url = new \moodle_url('/question/addquestion.php', $params);

        echo $OUTPUT->single_button($url, $caption, 'get', [
            'disabled' => $disabled,
            'title' => $tooltip
        ]);

        if (!$choice_form_printed) {
            echo '<div id="qtypechoicecontainer">';
            echo print_choose_qtype_to_add_form([], $enabled_types);
            echo "</div>\n";
            $choice_form_printed = true;
        }

        // Add some top margin to the table (not viable via CSS)
        echo '<br><br>';
    }
}
