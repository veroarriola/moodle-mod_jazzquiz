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
     * Check whether or not the question is enabled in the config.
     * @param string $name of question
     * @return bool
     */
    private function is_question_enabled($name) {
        $config = get_config('mod_jazzquiz');
        if (!isset($config->improvenabled)) {
            $config->improvenabled = '';
            return false;
        }
        $enabled = explode(',', $config->improvenabled);
        return in_array($name, $enabled);
    }

    /**
     * Check whether or not the question was added to database by checking the config.
     * @param string $name of question
     * @return bool
     */
    private function is_question_added($name) {
        $config = get_config('mod_jazzquiz');
        if (!isset($config->improvadded)) {
            $config->improvadded = '';
            return false;
        }
        $added = explode(',', $config->improvadded);
        return in_array($name, $added);
    }

    /**
     * Store that we have added the question to the database.
     * @param string $name
     */
    private function set_question_added($name) {
        $config = get_config('mod_jazzquiz');
        $improvadded = [];
        if (isset($config->improvadded)) {
            $improvadded = explode(',', $config->improvadded);
        }
        $enabled = in_array($name, $improvadded);
        if ($enabled) {
            return;
        }
        $improvadded[] = $name;
        $improvadded = implode(',', $improvadded);
        set_config('improvadded', $improvadded, 'mod_jazzquiz');
    }

    /**
     * Remove the 'enable' and 'added' config for this question
     * @param string $name of question
     */
    private function disable_question($name) {
        $config = get_config('mod_jazzquiz');
        if (isset($config->improvenabled)) {
            $enabled = explode(',', $config->improvenabled);
            $key = array_search($name, $enabled);
            if ($key !== false) {
                unset($enabled[$key]);
                $enabled = implode(',', $enabled);
                set_config('improvenabled', $enabled, 'mod_jazzquiz');
            }
        }
        if (isset($config->improvadded)) {
            $added = explode(',', $config->improvadded);
            $key = array_search($name, $added);
            if ($key !== false) {
                unset($added[$key]);
                $added = implode(',', $added);
                set_config('improvadded', $added, 'mod_jazzquiz');
            }
        }
    }

    /**
     * Deletes the question definition if it exists, but should be disabled.
     * @param string $name of question
     * @return bool true if deleted or already deleted
     */
    private function delete_if_disabled($name) {
        $enabled = $this->is_question_enabled($name);
        $added = $this->is_question_added($name);
        $question = $this->get_improvised_question_definition($name);
        if ($enabled && !$question) {
            // The question is enabled, but does not exist in database.
            if ($added) {
                // This question was originally added, but something (other than JazzQuiz) has deleted it.
                // Remove 'added' and 'enabled' for this question
                $this->disable_question($name);
                // Tell the caller to not add the question.
                return true;
            }
            // The question is enabled, but the question has not been added.
            // Tell the caller to add the question.
            return false;
        }
        if (!$enabled && $question) {
            // The question is disabled, but it exists in the question bank.
            // We must delete it here.
            question_delete_question($question->id);
            // Remove it from config too.
            if ($added) {
                $this->disable_question($name);
            }
        }
        // The question is disabled, and we have made sure the question is deleted.
        // Tell the caller to not add the question.
        return true;
    }

    /**
     * Create a question database object.
     * @param string $qtype What question type to create
     * @param string $name The name of the question to create
     * @return \stdClass | null
     */
    private function make_generic_question_definition($qtype, $name) {
        $context = \context_module::instance($this->jazzquiz->cm->id);
        $category = question_get_default_category($context->id);
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
     * Get the specified question name is an improvisational question.
     * @param string $name The name of the improvised question without the prefix.
     * @return \stdClass|false
     */
    private function get_improvised_question_definition($name) {
        global $DB;
        $questions = $DB->get_records('question', ['name' => '{IMPROV}' . $name]);
        if (!$questions) {
            return false;
        }
        return reset($questions);
    }

    /**
     * Insert a multichoice question to the database.
     * @param string $name The name of the question
     * @param int $optioncount How many answer options should be created
     */
    private function insert_multichoice_question_definition($name, $optioncount) {
        global $DB;
        if ($this->delete_if_disabled($name)) {
            return;
        }

        // Add question.
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

        $this->set_question_added($name);
    }

    /**
     * Insert a short answer question to the database.
     * @param string $name The name of the short answer question
     */
    private function insert_shortanswer_question_definition($name) {
        global $DB;
        if ($this->delete_if_disabled($name)) {
            return;
        }

        // Add question.
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

        $this->set_question_added($name);
    }

    /**
     * Insert a true/false question to the database.
     * @param string $name The name of the true/false question
     */
    private function insert_truefalse_question_definition($name) {
        global $DB;
        if ($this->delete_if_disabled($name)) {
            return;
        }

        // Add question.
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

        $this->set_question_added($name);
    }

    /**
     * Insert a STACK question to the database.
     * @param string $name The name of the STACK question
     */
    private function insert_stack_algebraic_question_definition($name) {
        global $DB;
        if (!$this->question_type_exists('stack')) {
            return;
        }
        if ($this->delete_if_disabled($name)) {
            return;
        }

        // Add question.
        $question = $this->make_generic_question_definition('stack', $name);
        if (!$question) {
            return;
        }
        $question->questiontext = '<p>[[input:ans1]] [[validation:ans1]]</p>';
        $question->id = $DB->insert_record('question', $question);

        // Add options.
        $options = new \stdClass();
        $options->questionid = $question->id;
        $options->questionvariables = '';
        $options->specificfeedback = '[[feedback:prt1]]';
        $options->specificfeedbackformat = 1;
        $options->questionnote = '';
        $options->questionsimplify = 1;
        $options->assumepositive = 0;
        $options->prtcorrect = ''; // No feedback.
        $options->prtcorrectformat = 1;
        $options->prtpartiallycorrect = '';
        $options->prtpartiallycorrectformat = 1;
        $options->prtincorrect = '';
        $options->prtincorrectformat = 1;
        $options->multiplicationsign = 'dot';
        $options->sqrtsign = 1;
        $options->complexno = 'i';
        $options->inversetrig = 'cos-1';
        $options->matrixparens = '[';
        $options->variantsselectionseed = '';
        $options->id = $DB->insert_record('qtype_stack_options', $options);

        // Add inputs.
        $input = new \stdClass();
        $input->questionid = $question->id;
        $input->name = 'ans1';
        $input->type = 'algebraic';
        $input->tans = '1';
        $input->boxsize = 40;
        $input->strictsyntax = 1;
        $input->insertstars = 0;
        $input->syntaxhint = '';
        $input->syntaxattribute = 0;
        $input->forbidwords = '';
        $input->allowwords = '';
        $input->forbidfloat = 0;
        $input->requirelowestterms = 0;
        $input->checkanswertype = 0;
        $input->mustverify = 1;
        $input->showvalidation = 1;
        $input->options = '';
        $input->id = $DB->insert_record('qtype_stack_inputs', $input);

        // Add PRTs.
        $prt = new \stdClass();
        $prt->questionid = $question->id;
        $prt->name = 'prt1';
        $prt->value = 1;
        $prt->autosimplify = 1;
        $prt->feedbackvariables = '';
        $prt->firstnodename = 0;
        $prt->id = $DB->insert_record('qtype_stack_prts', $prt);

        // Add PRT Nodes.
        $prtnode = new \stdClass();
        $prtnode->questionid = $question->id;
        $prtnode->prtname = 'prt1';
        $prtnode->nodename = 0;
        $prtnode->answertest = 'AlgEquiv';
        $prtnode->sans = 'ans1';
        $prtnode->tans = 'ans1';
        $prtnode->testoptions = '';
        $prtnode->quiet = 0;
        $prtnode->truescoremode = '=';
        $prtnode->truescore = 1;
        $prtnode->truepenalty = null;
        $prtnode->truenextnode = -1;
        $prtnode->trueanswernote = 'prt1-1-T';
        $prtnode->truefeedback = '';
        $prtnode->truefeedbackformat = 1;
        $prtnode->falsescoremode = '=';
        $prtnode->falsescore = 0;
        $prtnode->falsepenalty = null;
        $prtnode->falsenextnode = -1;
        $prtnode->falseanswernote = 'prt1-1-F';
        $prtnode->falsefeedback = '';
        $prtnode->falsefeedbackformat = 1;
        $prtnode->id = $DB->insert_record('qtype_stack_prt_nodes', $prtnode);

        $this->set_question_added($name);
    }

    /**
     * Get the default improvisation questions.
     * @return string[] of question names
     */
    public static function get_default_improvised_questions() {
        return [
            '3 Multichoice Options',
            '4 Multichoice Options',
            '5 Multichoice Options',
            'Short answer',
            'True / False',
            'Algebraic'
        ];
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
        $this->insert_stack_algebraic_question_definition('Algebraic');
    }

}
