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
 * @author      Sebastian S. Gundersen <sebastsg@stud.ntnu.no>
 * @copyright   2018 NTNU
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class improviser {

    /** @var jazzquiz $jazzquiz */
    private $jazzquiz;

    /**
     * @param jazzquiz $jazzquiz
     */
    public function __construct($jazzquiz) {
        $this->jazzquiz = $jazzquiz;
    }

    /**
     * Check whether a question type exists or not.
     * @param string $name Name of the question type
     * @return bool
     */
    private function question_type_exists($name) {
        $qtypes = \core_plugin_manager::instance()->get_plugins_of_type('qtype');
        foreach ($qtypes as $qtype) {
            if ($qtype->name === $name) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the specified question name is an improvisational question.
     * @param string $name The name of the improvised question without the prefix.
     * @return \stdClass|false
     */
    private function get_improvised_question_definition($name) {
        global $DB;
        $category = $this->get_default_question_category();
        if (!$category) {
            return false;
        }
        $questions = $DB->get_records('question', [
            'category' => $category->id,
            'name' => '{IMPROV}' . $name
        ]);
        if (!$questions) {
            return false;
        }
        return reset($questions);
    }

    /**
     * Deletes the improvised question definition with matching name if it exists.
     * @param string $name of question
     */
    private function delete_improvised_question($name) {
        $question = $this->get_improvised_question_definition($name);
        if ($question !== false) {
            question_delete_question($question->id);
        }
    }

    /**
     * Returns the default question category for the activity.
     * @return object
     */
    private function get_default_question_category() {
        $context = \context_module::instance($this->jazzquiz->cm->id);
        return question_get_default_category($context->id);
    }

    /**
     * Create a question database object.
     * @param string $qtype What question type to create
     * @param string $name The name of the question to create
     * @return \stdClass | null
     */
    private function make_generic_question_definition($qtype, $name) {
        $existing = $this->get_improvised_question_definition($name);
        if ($existing !== false) {
            return null;
        }
        $category = $this->get_default_question_category();
        if (!$category) {
            return null;
        }
        $question = new \stdClass();
        $question->category = $category->id;
        $question->parent = 0;
        $question->name = '{IMPROV}' . $name;
        $question->questiontext = '&nbsp;';
        $question->questiontextformat = 1;
        $question->generalfeedback = '';
        $question->generalfeedbackformat = 1;
        $question->defaultmark = 1;
        $question->penalty = 0;
        $question->qtype = $qtype;
        $question->length = 1;
        $question->stamp = '';
        $question->version = '';
        $question->hidden = 0;
        $question->timecreated = time();
        $question->timemodified = $question->timecreated;
        $question->createdby = null;
        $question->modifiedby = null;
        return $question;
    }

    /**
     * Create a multichoice options database object.
     * @param int $questionid The ID of the question to make options for
     * @return \stdClass
     */
    private function make_multichoice_options($questionid) {
        $options = new \stdClass();
        $options->questionid = $questionid;
        $options->layout = 0;
        $options->single = 1;
        $options->shuffleanswers = 0;
        $options->correctfeedback = '';
        $options->correctfeedbackformat = 1;
        $options->partiallycorrectfeedback = '';
        $options->partiallycorrectfeedbackformat = 1;
        $options->incorrectfeedback = '';
        $options->incorrectfeedbackformat = 1;
        $options->answernumbering = 'none';
        $options->shownumcorrect = 1;
        return $options;
    }

    /**
     * Make a short answer question options database object.
     * @param int $questionid The ID of the question to make options for
     * @return \stdClass
     */
    private function make_short_answer_options($questionid) {
        $options = new \stdClass();
        $options->questionid = $questionid;
        $options->usecase = 0;
        return $options;
    }

    /**
     * Make an answer for a question.
     * @param int $questionid The ID of the question to make the answer for
     * @param string $format Which format the answer has
     * @param string $answertext The answer text
     * @return \stdClass
     */
    private function make_generic_question_answer($questionid, $format, $answertext) {
        $answer = new \stdClass();
        $answer->question = $questionid;
        $answer->answer = $answertext;
        $answer->answerformat = $format;
        $answer->fraction = 1;
        $answer->feedback = '';
        $answer->feedbackformat = 1;
        return $answer;
    }

    /**
     * Insert a multichoice question to the database.
     * @param string $name The name of the question
     * @param int $optioncount How many answer options should be created
     */
    private function insert_multichoice_question_definition($name, $optioncount) {
        global $DB;
        $question = $this->make_generic_question_definition('multichoice', $name);
        if (!$question) {
            return;
        }
        $question->id = $DB->insert_record('question', $question);
        // Add options.
        $options = $this->make_multichoice_options($question->id);
        $DB->insert_record('qtype_multichoice_options', $options);
        // Add answers.
        $begin = ord('A');
        $end = $begin + intval($optioncount);
        for ($a = $begin; $a < $end; $a++) {
            $letter = chr($a);
            $answer = $this->make_generic_question_answer($question->id, 1, $letter);
            $DB->insert_record('question_answers', $answer);
        }
    }

    /**
     * Insert a short answer question to the database.
     * @param string $name The name of the short answer question
     */
    private function insert_shortanswer_question_definition($name) {
        global $DB;
        $question = $this->make_generic_question_definition('shortanswer', 'Short answer');
        if (!$question) {
            return;
        }
        $question->id = $DB->insert_record('question', $question);
        // Add options.
        $options = $this->make_short_answer_options($question->id);
        $DB->insert_record('qtype_shortanswer_options', $options);
        // Add answer.
        $answer = $this->make_generic_question_answer($question->id, 0, '*');
        $DB->insert_record('question_answers', $answer);
    }

    /**
     * Insert a true/false question to the database.
     * @param string $name The name of the true/false question
     */
    private function insert_truefalse_question_definition($name) {
        global $DB;
        $question = $this->make_generic_question_definition('truefalse', 'True / False');
        if (!$question) {
            return;
        }
        $question->id = $DB->insert_record('question', $question);
        // Add answers.
        $trueanswer = $this->make_generic_question_answer($question->id, 0, 'True');
        $trueanswer->id = $DB->insert_record('question_answers', $trueanswer);
        $falseanswer = $this->make_generic_question_answer($question->id, 0, 'False');
        $falseanswer->id = $DB->insert_record('question_answers', $falseanswer);
        // True / False.
        $truefalse = new \stdClass();
        $truefalse->question = $question->id;
        $truefalse->trueanswer = $trueanswer->id;
        $truefalse->falseanswer = $falseanswer->id;
        $DB->insert_record('question_truefalse', $truefalse);
    }

    /**
     * Insert all the improvised question definitions to the question bank.
     * Every question will have a prefix of {IMPROV}
     */
    public function insert_default_improvised_question_definitions() {
        for ($i = 3; $i <= 5; $i++) {
            $this->insert_multichoice_question_definition("$i Multichoice Options", $i);
        }
        $this->insert_shortanswer_question_definition('Short answer');
        $this->insert_truefalse_question_definition('True / False');
    }

}
