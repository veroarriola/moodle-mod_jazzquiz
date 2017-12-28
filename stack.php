<?php

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/../../question/engine/lib.php');

$input = required_param('input', PARAM_RAW);
$input = urldecode($input);

$question = $DB->get_record_sql('SELECT id FROM {question} WHERE qtype = ? AND name LIKE ?', ['stack', '{IMPROV}%']);
if (!$question) {
    echo json_encode([
        'message' => 'STACK question not found.',
        'latex' => $input,
        'original' => $input
    ]);
    exit;
}

/** @var qtype_stack_question $question */
$question = question_bank::load_question($question->id);
$question->initialise_question_from_seed();
$state = $question->get_input_state('ans1', ['ans1' => $input]);
$latex = $state->contentsdisplayed;

echo json_encode([
    'latex' => $latex,
    'original' => $input
]);
