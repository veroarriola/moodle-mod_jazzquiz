<?php

define('AJAX_SCRIPT', true);

require_once('../../config.php');

//require_sesskey();

function add_dummy_question($activequizid, $questionid) {

    global $DB;

    // Get the ActiveQuiz
    $activequiz = $DB->get_record('activequiz', ['id' => $activequizid]);
    if (!$activequiz) {
        return;
    }

    // Create the new ActiveQuiz question
    $question = new \stdClass();
    $question->activequizid = $activequizid;
    $question->questionid = $questionid;
    $question->notime = 0;
    $question->questiontime = 60;
    $question->tries = 1;
    $question->points = 1;
    $question->showhistoryduringquiz = 0;

    // Save to database
    $activequiz_question_id = $DB->insert_record('activequiz_questions', $question);

    // We must also update the question order for the ActiveQuiz
    if ($activequiz->questionorder != '') {
        $activequiz->questionorder .= ',';
    }
    $activequiz->questionorder .= $activequiz_question_id;
    $DB->update_record('activequiz', $activequiz);

}

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
$course_module = get_coursemodule_from_id('activequiz', $course_module_id, 0, false, MUST_EXIST);
if (!$course_module) {
    die('course module not found');
}

// Get the ActiveQuiz
$activequiz = $DB->get_record('activequiz', ['id' => $course_module->instance]);
if (!$activequiz) {
    die('activequiz not found');
}

// Find all the dummy questions
$dummy_questions = $DB->get_records_sql('SELECT * FROM {question} WHERE name LIKE ?', [ '{IMPROV}%' ]);
if (!$dummy_questions) {
    // Probably no dummy questions.
    return;
}

// Get all the existing quiz questions
$quiz_questions = $DB->get_records('activequiz_questions', [
    'activequizid' => $activequiz->id
]);

if (!$quiz_questions) {

    // No questions for this quiz? Let's get right to adding the dummy ones then.
    foreach ($dummy_questions as $dummy_question) {
        add_dummy_question($activequiz->id, $dummy_question->id);
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
            add_dummy_question($activequiz->id, $dummy_question->id);
        }
    }

}
