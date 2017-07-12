<?php

define('AJAX_SCRIPT', true);

require_once('../../config.php');

//require_sesskey();

$improviser = new \mod_jazzquiz\improviser();

// Get course module id
$course_module_id = optional_param('cmid', false, PARAM_INT);
if (!$course_module_id) {
    die('course module id required');
}

// Redirect user to view the quiz
$redirect = optional_param('redirect', false, PARAM_TEXT);
if (!$redirect || $redirect == 'view') {
    $redirect = 'view.php?id=';
} else if ($redirect == 'edit') {
    $redirect = 'edit.php?cmid=';
}
header('Location: ' . $redirect . $course_module_id);

// Get the course module
$course_module = get_coursemodule_from_id('jazzquiz', $course_module_id, 0, false, MUST_EXIST);
if (!$course_module) {
    die('course module not found');
}

// Get the ActiveQuiz
$jazzquiz = $DB->get_record('jazzquiz', ['id' => $course_module->instance]);
if (!$jazzquiz) {
    die('jazzquiz not found');
}

// Insert the default improvised questions
// TODO: Make this optional
$improviser->insert_default_improvised_question_definitions();

// Find all the dummy questions
$dummy_questions = $DB->get_records_sql('SELECT * FROM {question} WHERE name LIKE ?', ['{IMPROV}%']);
if (!$dummy_questions) {
    // Probably no dummy questions.
    return;
}

// Get all the existing quiz questions
$quiz_questions = $DB->get_records('jazzquiz_questions', [
    'jazzquizid' => $jazzquiz->id
]);

if (!$quiz_questions) {

    // No questions for this quiz? Let's get right to adding the dummy ones then.
    foreach ($dummy_questions as $dummy_question) {
        $improviser->add_improvised_question_instance($jazzquiz->id, $dummy_question->id);
    }

} else {

    // We should only add the ones that don't already exist.
    foreach ($dummy_questions as $dummy_question) {

        $exists = false;

        foreach ($quiz_questions as $quiz_question) {

            if ($dummy_question->id == $quiz_question->questionid) {
                $exists = true;
                break;
            }

        }

        if (!$exists) {
            $improviser->add_improvised_question_instance($jazzquiz->id, $dummy_question->id);
        }
    }

}
