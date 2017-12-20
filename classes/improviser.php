<?php

namespace mod_jazzquiz;

defined('MOODLE_INTERNAL') || die();

class improviser
{
    public function add_improvised_question_instance($jazzquiz_id, $question_id)
    {
        global $DB;

        // Get the JazzQuiz
        $jazzquiz = $DB->get_record('jazzquiz', [ 'id' => $jazzquiz_id ]);
        if (!$jazzquiz) {
            return;
        }

        // Create the new JazzQuiz question
        $question = new \stdClass();
        $question->jazzquizid = $jazzquiz_id;
        $question->questionid = $question_id;
        $question->notime = 0;
        $question->questiontime = $jazzquiz->defaultquestiontime;
        $question->tries = 1;
        $question->showhistoryduringquiz = 0;

        // Save to database
        $jazzquiz_question_id = $DB->insert_record('jazzquiz_questions', $question);

        // We must also update the question order for the JazzQuiz
        if ($jazzquiz->questionorder != '') {
            $jazzquiz->questionorder .= ',';
        }
        $jazzquiz->questionorder .= $jazzquiz_question_id;
        $DB->update_record('jazzquiz', $jazzquiz);
    }

    public function remove_improvised_question_instance($jazzquiz_id, $question_id)
    {
        global $DB;

        // Get the JazzQuiz
        $jazzquiz = $DB->get_record('jazzquiz', [
            'id' => $jazzquiz_id
        ]);
        if (!$jazzquiz) {
            return;
        }

        // Get current quiz questions with this question
        // We need the ID
        $existing_improvised_questions = $DB->get_records('jazzquiz_questions', [
            'jazzquizid' => $jazzquiz_id,
            'questionid' => $question_id
        ]);
        if (!$existing_improvised_questions) {
            return;
        }

        // Delete the improvised question
        $DB->delete_records('jazzquiz_questions', [
            'jazzquizid' => $jazzquiz_id,
            'questionid' => $question_id
        ]);

        // Update question order
        $question_order = explode(',', $jazzquiz->questionorder);
        foreach ($question_order as $index => $order_question_id) {
            foreach ($existing_improvised_questions as $existing_improvised_question) {
                if ($order_question_id == $existing_improvised_question->id) {
                    unset($question_order[$index]);
                    break;
                }
            }
        }
        $jazzquiz->questionorder = implode(',', $question_order);
        $DB->update_record('jazzquiz', $jazzquiz);
    }

    private function make_generic_question_definition($question_type, $name)
    {
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

    private function make_multichoice_options($question_id)
    {
        $options = new \stdClass();
        $options->questionid = $question_id;
        $options->layout = 0;
        $options->single = 1;
        $options->shuffleanswers = 0;
        $options->correctfeedback = ''; // There is no feedback
        $options->correctfeedbackformat = 1;
        $options->partiallycorrectfeedback = '';
        $options->partiallycorrectfeedbackformat = 1;
        $options->incorrectfeedback = '';
        $options->incorrectfeedbackformat = 1;
        $options->answernumbering = 'none';
        $options->shownumcorrect = 1;
        return $options;
    }

    private function make_short_answer_options($question_id)
    {
        $options = new \stdClass();
        $options->questionid = $question_id;
        $options->usecase = 0;
        return $options;
    }

    private function make_generic_question_answer($question_id, $format, $answer_text)
    {
        $answer = new \stdClass();
        $answer->question = $question_id; // NOTE: This might be renamed to questionid in future versions of Moodle.
        $answer->answer = $answer_text;
        $answer->answerformat = $format;
        $answer->fraction = 1;
        $answer->feedback = '';
        $answer->feedbackformat = 1;
        return $answer;
    }

    private function improvised_question_definition_exists($name)
    {
        global $DB;
        return $DB->record_exists('question', ['name' => '{IMPROV}' . $name]);
    }

    private function insert_multichoice_question_definition($name, $option_count)
    {
        global $DB;

        // Check if duplicate
        if ($this->improvised_question_definition_exists($name)) {
            return;
        }

        // Add question
        $question = $this->make_generic_question_definition('multichoice', $name);
        $question->id = $DB->insert_record('question', $question);

        // Add options
        $options = $this->make_multichoice_options($question->id);
        $DB->insert_record('qtype_multichoice_options', $options);

        // Add answers
        $begin = ord('A');
        $end = $begin + intval($option_count);
        for ($a = $begin; $a < $end; $a++) {
            $letter = chr($a);
            $answer = $this->make_generic_question_answer($question->id, 1, $letter);
            $DB->insert_record('question_answers', $answer);
        }
    }

    private function insert_shortanswer_question_definition($name)
    {
        global $DB;

        // Check if duplicate
        if ($this->improvised_question_definition_exists($name)) {
            return;
        }

        // Add question
        $question = $this->make_generic_question_definition('shortanswer', 'Short answer');
        $question->id = $DB->insert_record('question', $question);

        // Add options
        $options = $this->make_short_answer_options($question->id);
        $DB->insert_record('qtype_shortanswer_options', $options);

        // Add answer
        $answer = $this->make_generic_question_answer($question->id, 0, '*');
        $DB->insert_record('question_answers', $answer);
    }

    private function insert_truefalse_question_definition($name)
    {
        global $DB;

        // Check if duplicate
        if ($this->improvised_question_definition_exists($name)) {
            return;
        }

        // Add question
        $question = $this->make_generic_question_definition('truefalse', 'True / False');
        $question->id = $DB->insert_record('question', $question);

        // Add answers
        $true_answer = $this->make_generic_question_answer($question->id, 0, 'True');
        $true_answer->id = $DB->insert_record('question_answers', $true_answer);
        $false_answer = $this->make_generic_question_answer($question->id, 0, 'False');
        $false_answer->id = $DB->insert_record('question_answers', $false_answer);

        // True / False
        $true_false = new \stdClass();
        $true_false->question = $question->id; // NOTE: This might be renamed to questionid in future versions of Moodle.
        $true_false->trueanswer = $true_answer->id;
        $true_false->falseanswer = $false_answer->id;
        $DB->insert_record('question_truefalse', $true_false);
    }

    private function insert_stack_algebraic_question_definition($name)
    {
        global $DB;

        // Check if duplicate
        if ($this->improvised_question_definition_exists($name)) {
            return;
        }

        // Add question
        $question = $this->make_generic_question_definition('stack', $name);
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
        $prt_node->truepenalty = NULL;
        $prt_node->truenextnode = -1;
        $prt_node->trueanswernote = 'prt1-1-T';
        $prt_node->truefeedback = '';
        $prt_node->truefeedbackformat = 1;
        $prt_node->falsescoremode = '=';
        $prt_node->falsescore = 0;
        $prt_node->falsepenalty = NULL;
        $prt_node->falsenextnode = -1;
        $prt_node->falseanswernote = 'prt1-1-F';
        $prt_node->falsefeedback = '';
        $prt_node->falsefeedbackformat = 1;
        $prt_node->id = $DB->insert_record('qtype_stack_prt_nodes', $prt_node);
    }

    public function insert_default_improvised_question_definitions()
    {
        // Multichoice (3, 4 and  5 options)
        for ($i = 3; $i <= 5; $i++) {
            $this->insert_multichoice_question_definition("$i Multichoice Options", $i);
        }

        // Short answer
        $this->insert_shortanswer_question_definition('Short answer');

        // True or False
        $this->insert_truefalse_question_definition('True / False');

        // STACK Algebraic
        $this->insert_stack_algebraic_question_definition('Algebraic');
    }

    /*public function remove_improvised_questions_from_quiz($jazzquiz_id)
    {
        global $DB;

        // Find all the improvised questions
        $improvised_questions = $DB->get_records_sql('SELECT * FROM {question} WHERE name LIKE ?', ['{IMPROV}%']);
        if (!$improvised_questions) {
            return;
        }

        // Get the questions for the quiz
        $quiz_questions = $DB->get_records('jazzquiz_questions', [
            'jazzquizid' => $jazzquiz_id
        ]);
        if (!$quiz_questions) {
            return;
        }

        // Remove the improvised questions
        foreach ($improvised_questions as $improvised_question) {
            foreach ($quiz_questions as $quiz_question) {
                if ($improvised_question->id == $quiz_question->questionid) {
                    $this->remove_improvised_question_instance($jazzquiz_id, $improvised_question->id);
                }
            }
        }
    }

    public function add_improvised_questions_to_quiz($jazzquiz_id)
    {
        global $DB;

        // Find all the improvised questions
        $improvised_questions = $DB->get_records_sql('SELECT * FROM {question} WHERE name LIKE ?', ['{IMPROV}%']);
        if (!$improvised_questions) {
            $this->insert_default_improvised_question_definitions();
        }

        $quiz_questions = $DB->get_records('jazzquiz_questions', [
            'jazzquizid' => $jazzquiz_id
        ]);

        if (!$quiz_questions) {
            // No questions for this quiz? Let's get right to adding the dummy ones then.
            foreach ($improvised_questions as $improvised_question) {
                $this->add_improvised_question_instance($jazzquiz_id, $improvised_question->id);
            }
        } else {
            // We should only add the ones that don't already exist.
            foreach ($improvised_questions as $improvised_question) {
                $exists = false;
                foreach ($quiz_questions as $quiz_question) {
                    if ($improvised_question->id == $quiz_question->questionid) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $this->add_improvised_question_instance($jazzquiz_id, $improvised_question->id);
                }
            }
        }
    }*/

}
