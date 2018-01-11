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

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/../../question/engine/lib.php');

require_login();

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
