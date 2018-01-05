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
    private static function question_type_exists($name) {
        $question_types = \core_plugin_manager::instance()->get_plugins_of_type('qtype');
        foreach ($question_types as $question_type) {
            if ($question_type->name === $name) {
                return true;
            }
        }
        return false;
    }

    private static function make_generic_question_definition($question_type, $name) {
        $question = new \stdClass();
        $question->category = 4; // This is the 'System Default' for 222
        $question->parent = 0;
        $question->name = '{IMPROV}' . $name;
        $question->questiontext = '&nbsp;';
        $question->questiontextformat = 1;
        $question->generalfeedback = '';
        $question->generalfeedbackformat = 1;
        $question->defaultmark = 1;
        $question->penalty = 0;
        $question->qtype = $question_type;
        $question->length = 1;
        $question->stamp = '';
        $question->version = '';
        $question->hidden = 0;
        $question->timecreated = time();
        $question->timemodified = $question->timecreated;
        $question->createdby = NULL;
        $question->modifiedby = NULL;
        return $question;
    }

    private static function make_multichoice_options($question_id) {
        $options = new \stdClass();
        $options->questionid = $question_id;
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

    private static function make_short_answer_options($question_id) {
        $options = new \stdClass();
        $options->questionid = $question_id;
        $options->usecase = 0;
        return $options;
    }

    private static function make_generic_question_answer($question_id, $format, $answer_text) {
        $answer = new \stdClass();
        $answer->question = $question_id;
        $answer->answer = $answer_text;
        $answer->answerformat = $format;
        $answer->fraction = 1;
        $answer->feedback = '';
        $answer->feedbackformat = 1;
        return $answer;
    }

    private static function improvised_question_definition_exists($name) {
        global $DB;
        return $DB->record_exists('question', ['name' => '{IMPROV}' . $name]);
    }

    private static function insert_multichoice_question_definition($name, $option_count) {
        global $DB;

        // Check if duplicate
        if (self::improvised_question_definition_exists($name)) {
            return;
        }

        // Add question
        $question = self::make_generic_question_definition('multichoice', $name);
        $question->id = $DB->insert_record('question', $question);

        // Add options
        $options = self::make_multichoice_options($question->id);
        $DB->insert_record('qtype_multichoice_options', $options);

        // Add answers
        $begin = ord('A');
        $end = $begin + intval($option_count);
        for ($a = $begin; $a < $end; $a++) {
            $letter = chr($a);
            $answer = self::make_generic_question_answer($question->id, 1, $letter);
            $DB->insert_record('question_answers', $answer);
        }
    }

    private static function insert_shortanswer_question_definition($name) {
        global $DB;

        // Check if duplicate
        if (self::improvised_question_definition_exists($name)) {
            return;
        }

        // Add question
        $question = self::make_generic_question_definition('shortanswer', 'Short answer');
        $question->id = $DB->insert_record('question', $question);

        // Add options
        $options = self::make_short_answer_options($question->id);
        $DB->insert_record('qtype_shortanswer_options', $options);

        // Add answer
        $answer = self::make_generic_question_answer($question->id, 0, '*');
        $DB->insert_record('question_answers', $answer);
    }

    private static function insert_truefalse_question_definition($name) {
        global $DB;

        // Check if duplicate
        if (self::improvised_question_definition_exists($name)) {
            return;
        }

        // Add question
        $question = self::make_generic_question_definition('truefalse', 'True / False');
        $question->id = $DB->insert_record('question', $question);

        // Add answers
        $true_answer = self::make_generic_question_answer($question->id, 0, 'True');
        $true_answer->id = $DB->insert_record('question_answers', $true_answer);
        $false_answer = self::make_generic_question_answer($question->id, 0, 'False');
        $false_answer->id = $DB->insert_record('question_answers', $false_answer);

        // True / False
        $true_false = new \stdClass();
        $true_false->question = $question->id;
        $true_false->trueanswer = $true_answer->id;
        $true_false->falseanswer = $false_answer->id;
        $DB->insert_record('question_truefalse', $true_false);
    }

    private static function insert_stack_algebraic_question_definition($name) {
        global $DB;

        if (!self::question_type_exists('stack')) {
            return;
        }

        // Check if duplicate
        if (self::improvised_question_definition_exists($name)) {
            return;
        }

        // Add question
        $question = self::make_generic_question_definition('stack', $name);
        $question->questiontext = '<p>[[input:ans1]] [[validation:ans1]]</p>';
        $question->id = $DB->insert_record('question', $question);

        // Add options
        $options = new \stdClass();
        $options->questionid = $question->id;
        $options->questionvariables = '';
        $options->specificfeedback = '[[feedback:prt1]]';
        $options->specificfeedbackformat = 1;
        $options->questionnote = '';
        $options->questionsimplify = 1;
        $options->assumepositive = 0;
        $options->prtcorrect = ''; // No feedback
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

        // Add inputs
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

        // Add PRTs
        $prt = new \stdClass();
        $prt->questionid = $question->id;
        $prt->name = 'prt1';
        $prt->value = 1;
        $prt->autosimplify = 1;
        $prt->feedbackvariables = '';
        $prt->firstnodename = 0;
        $prt->id = $DB->insert_record('qtype_stack_prts', $prt);

        // Add PRT Nodes
        $prt_node = new \stdClass();
        $prt_node->questionid = $question->id;
        $prt_node->prtname = 'prt1';
        $prt_node->nodename = 0;
        $prt_node->answertest = 'AlgEquiv';
        $prt_node->sans = 'ans1';
        $prt_node->tans = 'ans1';
        $prt_node->testoptions = '';
        $prt_node->quiet = 0;
        $prt_node->truescoremode = '=';
        $prt_node->truescore = 1;
        $prt_node->truepenalty = null;
        $prt_node->truenextnode = -1;
        $prt_node->trueanswernote = 'prt1-1-T';
        $prt_node->truefeedback = '';
        $prt_node->truefeedbackformat = 1;
        $prt_node->falsescoremode = '=';
        $prt_node->falsescore = 0;
        $prt_node->falsepenalty = null;
        $prt_node->falsenextnode = -1;
        $prt_node->falseanswernote = 'prt1-1-F';
        $prt_node->falsefeedback = '';
        $prt_node->falsefeedbackformat = 1;
        $prt_node->id = $DB->insert_record('qtype_stack_prt_nodes', $prt_node);
    }

    public static function insert_default_improvised_question_definitions() {
        // Multichoice (3, 4 and  5 options)
        for ($i = 3; $i <= 5; $i++) {
            self::insert_multichoice_question_definition("$i Multichoice Options", $i);
        }

        // Short answer
        self::insert_shortanswer_question_definition('Short answer');

        // True or False
        self::insert_truefalse_question_definition('True / False');

        // STACK Algebraic
        self::insert_stack_algebraic_question_definition('Algebraic');
    }

}
