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

// Get the JazzQuiz
$jazzquiz = $DB->get_record('jazzquiz', ['id' => $course_module->instance]);
if (!$jazzquiz) {
    die('jazzquiz not found');
}

// Insert the default improvised questions
// The reason we always add them here is to ensure that all new improvised questions are included.
$improviser->insert_default_improvised_question_definitions();

$improviser->add_improvised_questions_to_quiz($jazzquiz->id);
