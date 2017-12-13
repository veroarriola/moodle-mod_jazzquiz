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

/**
 * @package   mod_jazzquiz
 * @author    John Hoopes <moodle@madisoncreativeweb.com>
 * @author    Davo Smith
 * @copyright 2014 University of Wisconsin - Madison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// General
$string['modulename'] = 'JazzQuiz';
$string['modulename_help'] = '
<p>
    The JazzQuiz activity enables an instructor to create and administer quizzes in real-time. All regular quiz question types can be used in the JazzQuiz.
</p>
<p>
    JazzQuiz allows individual or group participation. Group attendance is possible so points given during the quiz will only be applied to the participants that attended the session.
    Questions can be set to allow multiple attempts. A time limit may be set to automatically end the question, or the instructor can manually end the question and move on to the next one.
    The instructor also has the ability to jump to different questions while  running the session. Instructors can monitor group or individual participation, real-time responses of the participants and the question being polled.
</p>
<p>
    Grading for group participation can be done automatically by transferring the grade from the single responder to the other group members.
</p>
<p>
    The instructor has options to show hints, give feedback and show correct answers to students upon quiz completion.
</p>
<p>
    JazzQuizzes may be used as a vehicle for delivering Team Based Learning inside Moodle.
</p>';

$string['modulenameplural'] = 'JazzQuizzes';
$string['jazzquizsettings'] = 'General JazzQuiz settings';
$string['pluginadministration'] = 'JazzQuiz administration';
$string['pluginname'] = 'JazzQuiz';

$string['attempts'] = 'Attempts';
$string['invalid_attempt_access'] = 'You do not have permission to access this attempt';
$string['jazzquiz:addinstance'] = 'Add an instance of jazzquiz';
$string['jazzquiz:attempt'] = 'Attempt an JazzQuiz';
$string['jazzquiz:control'] = 'Control an JazzQuiz. (Usually for instructors only)';
$string['jazzquiz:editquestions'] = 'Edit questions for an JazzQuiz.';
$string['jazzquiz:seeresponses'] = 'View other student responses to grade them';
$string['jazzquiz:viewownattempts'] = 'Allows students to see their own attempts at a quiz';

// Tabs
$string['view'] = 'View';
$string['edit'] = 'Edit';
$string['review'] = 'Review';
$string['reports'] = 'Reports';

// Info
$string['question_will_start'] = 'The question will start';
$string['in'] = 'in';
$string['now'] = 'now';
$string['wait_for_students'] = 'Waiting for students to connect';
$string['loading'] = 'Initializing quiz';
$string['closing_session'] = 'Closing session...';
$string['session_closed'] = 'Session is now closed';
$string['no_tries'] = 'You have no tries left for this question';
$string['wait_for_reviewing_to_end'] = 'The instructor is currently reviewing the previous question. Please wait for the next question to start';
$string['wait_for_instructor'] = 'Please wait for the instructor to start the next question.';
$string['gathering_results'] = 'Gathering results...';
$string['question_will_end_in'] = 'The question will end in';
$string['you_have_n_tries_left'] = 'You have {$a->tries} tries left.';

// Event
$string['event_attempt_started'] = 'Attempt started';
$string['event_attempt_viewed'] = 'Attempt viewed';
$string['event_question_answered'] = 'Question answered for attempt';

// Form
$string['general_settings'] = 'General JazzQuiz settings';
$string['introduction'] = 'Introduction';
$string['default_question_time'] = 'Default question time';
$string['default_question_time_help'] = 'The default time to display each question.<br>This can be overridden by individual questions.';
$string['wait_for_question_time'] = 'Wait for question time';
$string['wait_for_question_time_help'] = 'The time to wait for a question to start.';
$string['group_work_settings'] = 'Group settings';
$string['no_change_groups_label'] = '&nbsp;';
$string['no_change_groups'] = 'You cannot change groups after creating sessions or there are no groupings defined for this course.';
$string['worked_in_groups'] = 'Will work in groups.';
$string['worked_in_groups_help'] = 'Check this box to indicate that students will work in groups. Be sure to select a grouping below';
$string['grouping'] = 'Grouping';
$string['grouping_help'] = 'Select the grouping that you\'d like to use for grouping students';
$string['group_attendance'] = 'Allow group attendance';
$string['group_attendance_help'] = 'If this box is enabled, the student taking the quiz can select which students in their group that are in attendance.';
$string['review_option_settings'] = 'Review options';
$string['review_after'] = 'Review options after session is over';
$string['the_attempt'] = 'The attempt';
$string['the_attempt_help'] = 'Whether the student can review the attempt at all.';
$string['review_after'] = 'After the sessions are closed';
$string['manual_comment'] = 'Manual Comment';
$string['manual_comment_help'] = 'The comment that instructors can add when grading an attempt';

// Edit
$string['questions'] = 'Questions';
$string['add'] = 'Add';
$string['question'] = 'Question';
$string['add_question'] = 'Add question';
$string['delete_question'] = 'Delete question {$a}';
$string['question_finished'] = 'Question finished, waiting for results';
$string['question_move_down'] = 'Move question {$a} down';
$string['question_move_up'] = 'Move question {$a} up';
$string['question_time'] = 'Question time';
$string['question_time_help'] = 'Question time in seconds.';
$string['no_time_limit'] = 'No time limit';
$string['no_time_help'] = 'Check this field to have no timer on this question. <p>The instructor will then be required to click the end question button for the question to end</p>';
$string['invalid_question_time'] = 'Question time must be an integer of 0 or above';
$string['number_of_tries'] = 'Number of tries';
$string['invalid_number_of_tries'] = 'Number of tries must be an integer of 1 or above';
$string['number_of_tries_help'] = 'Number of tries for a user to try at a question. Students will still be bound by the question time limit';
$string['show_history_during_quiz'] = 'Show response history';
$string['show_history_during_quiz_help'] = 'Show the student/group response history for this question while reviewing responses to a question during a quiz.';
$string['successfully_moved_question'] = 'Successfully moved question';
$string['failed_to_move_question'] = 'Couldn\'t move question';
$string['successfully_deleted_question'] = 'Successfully deleted question';
$string['failed_to_delete_question'] = 'Couldn\'t delete question';
$string['edit_question'] = 'Edit question';
$string['save_question'] = 'Save question';
$string['cant_add_question_twice'] = 'You can not add the same question more than once to a quiz';
$string['edit_page_open_session_error'] = 'You cannot edit a quiz question or layout while a session is open.';
$string['create_new_question'] = 'Create new question';
$string['add_to_quiz'] = 'Add to quiz';

// Session
$string['quiz_not_running'] = 'Quiz not running at the moment - wait for your teacher to start it. Use the reload button to reload this page to check again';
$string['teacher_start_instructions'] = 'Start a quiz for the students to take.<br>Define a session name below to help when looking through the results at a later date.';
$string['no_questions'] = 'There are no questions added to this quiz.';
$string['session_name'] = 'Session name';
$string['session_name_required'] = 'The session name is required';
$string['start_session'] = 'Start Session';
$string['unable_to_create_session'] = 'Unable to create sesson';
$string['cant_init_attempts'] = 'Can\'t initialize attempts for you';
$string['session_name_text'] = '<span style="font-weight:bold;">Session: </span>';
$string['join_quiz_instructions'] = 'Click below to join the quiz';
$string['instructor_sessions_going'] = 'There is a session already in progress. Please click the button below to go to the session';
$string['goto_session'] = 'Go to session in progress';
$string['no_session'] = 'There is no open session';
$string['join_quiz'] = 'Join Quiz';
$string['select_group'] = 'Select your group';
$string['attempt_started_already'] = 'An attempt has already been started by one of your group members';
$string['attempt_started'] = 'An attempt has already been started by you, please click below to continue to your open attempt';
$string['invalid_group_id'] = 'A valid group id is required for students';
$string['first_session'] = 'First session';
$string['last_session'] = 'Last session';
$string['view_stats'] = 'View quiz stats';
$string['jump_question_instructions'] = 'Select a question that you\'d like to go to:';
$string['invalid_question_attempt'] = 'Invalid Question ($a->questionname) being added to quiz attempt. ';

// Instructor Controls
$string['startquiz'] = 'Start quiz';
$string['repoll'] = 'Re-poll';
$string['vote'] = 'Vote';
$string['improvise'] = 'Improvise';
$string['jump'] = 'Jump';
$string['next'] = 'Next';
$string['end'] = 'End';
$string['fullscreen'] = 'Fullscreen';
$string['quit'] = 'Quit';
$string['responses'] = 'Responses';
$string['answer'] = 'Answer';

// Quiz Review
$string['select_session'] = 'Select session to review:';
$string['group_membership'] = 'Group membership';
$string['view_attempt'] = 'View attempt';
$string['attendance_list'] = 'Attendance list';

// Attempts
$string['time_completed'] = 'Time completed';
$string['time_modified'] = 'Time modified';
$string['started_on'] = 'Started on';
$string['attempt_number'] = 'Attempt Number';
$string['response_attempt_controls'] = 'Edit/View Attempt';

// Admin Settings
$string['enabled_question_types'] = 'Enable question types';
$string['enabled_question_types_info'] = 'Question types that are enabled for use within instances of the JazzQuiz activity.';

// Instructions.
$string['instructions_for_student'] = '<p>Please wait for the instructor to start the quiz.</p>';
$string['instructions_for_instructor'] = '
    <h3>Please make sure to read the instructions:</h3>
    <table>
        <tr>
            <td>
                <i class="fa fa-repeat"></i> Re-poll
            </td>
            <td>
                Allows the instructor to re-poll the current or previous question.
            </td>
        </tr>
        <tr>
            <td>
                <i class="fa fa-bar-chart"></i> Vote
            </td>
            <td>
                 Let the students vote on their answers. The instructor can click on an answer to toggle whether it should be included in the vote or not.
            </td>
        </tr>
        <tr>
            <td>
                <i class="fa fa-edit"></i> Improvise
            </td>
            <td>
                Shows a list of questions made for improvising. Write the question on the blackboard and ask for input with these questions.
            </td>
        </tr>
        <tr>
            <td>
                <i class="fa fa-bars"></i> Jump to
            </td>
            <td>
                Open a dialog box to direct all users to a specific question in the quiz.
            </td>
        </tr>
        <tr>
            <td>
                <i class="fa fa-forward"></i> Next
            </td>
            <td>
                Continue on to the next question.
            </td>
        </tr>
        <tr>
            <td>
                <i class="fa fa-close"></i> End
            </td>
            <td>
                End the current question.
            </td>
        </tr>
        <tr>
            <td>
                <i class="fa fa-expand"></i> Fullscreen
            </td>
            <td>
                Show the results in fullscreen. The answers will not appear during a question, so you can keep this up throughout the session.
            </td>
        </tr>
        <tr>
            <td>
                <i class="fa fa-square-o"></i> / <i class="fa fa-check-square-o"></i> Answer
            </td>
            <td>
                Gives the instructor a view of the question with the correct response selected.
            </td>
        </tr>
        <tr>
            <td>
                <i class="fa fa-square-o"></i> / <i class="fa fa-check-square-o"></i> Responses
            </td>
            <td>
                Hide or show the students\' answers.
            </td>
        </tr>
        <tr>
            <td>
                <i class="fa fa-window-close"></i> Quit
            </td>
            <td>
                Exit the current quiz session.
            </td>
        </tr>
    </table>';
