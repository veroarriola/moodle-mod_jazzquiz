<?php

define('AJAX_SCRIPT', true);

require_once( __DIR__ . '/../../config.php');

$input = required_param('input', PARAM_RAW);
$input_name = required_param('name', PARAM_ALPHANUMEXT);
$activequiz_attempt_id = required_param('id', PARAM_INT);

$path_stack_utils = __DIR__ . '/../../question/type/stack/stack/utils.class.php';
$path_stack_mathsoutput = __DIR__ . '/../../question/type/stack/stack/mathsoutput/mathsoutput.class.php';

if (!file_exists($path_stack_utils) || !file_exists($path_stack_mathsoutput)) {
    echo json_encode([
        'status' => 'stack not found',
        'latex' => $input,
        'original' => $input
    ]);
    exit;
}

require_once(__DIR__ . '/../../question/engine/lib.php');
require_once($path_stack_utils);
require_once($path_stack_mathsoutput);

$PAGE->set_context(context_system::instance());

$result = stack_maths::process_display_castext($input);

$activequiz_attempt = $DB->get_record('activequiz_attempts', ['id' => $activequiz_attempt_id]);
$question_attempt = $DB->get_record('question_attempts', ['questionusageid' => $activequiz_attempt->questionengid]);

$data_mapper = new question_engine_data_mapper();
$qa = $data_mapper->load_question_attempt($question_attempt->id);
$question = $qa->get_question();

$state = $question->get_input_state($input_name, [$input_name => $input]);

$latex = $state->__get('contentsdisplayed');

echo json_encode([
    'latex' => $latex,
    'original' => $input
]);
