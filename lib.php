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
 * Library of functions and constants for module jazzquiz
 *
 * @package   mod_jazzquiz
 * @author    John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright 2014 University of Wisconsin - Madison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $instance An object from the form in mod.html
 * @return int The id of the newly inserted jazzquiz record
 **/
function jazzquiz_add_instance($jazzquiz)
{
    global $DB;
    $jazzquiz->timemodified = time();
    $jazzquiz->timecreated = time();
    $jazzquiz->id = $DB->insert_record('jazzquiz', $jazzquiz);
    return $jazzquiz->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will update an existing instance with new data.
 *
 * @param object $instance An object from the form in mod.html
 * @return boolean Success/Fail
 **/
function jazzquiz_update_instance($jazzquiz)
{
    global $DB;
    $jazzquiz->timemodified = time();
    $jazzquiz->id = $jazzquiz->instance;
    $DB->update_record('jazzquiz', $jazzquiz);
    return true;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 **/
function jazzquiz_delete_instance($id)
{
    global $DB, $CFG;

    require_once($CFG->dirroot . '/mod/jazzquiz/locallib.php');
    require_once($CFG->libdir . '/questionlib.php');
    require_once($CFG->dirroot . '/question/editlib.php');

    try {

        $jazzquiz = $DB->get_record('jazzquiz', [
            'id' => $id
        ], '*', MUST_EXIST);

        // Go through each session and then delete them (also deletes all attempts for them)
        $sessions = $DB->get_records('jazzquiz_sessions', [
            'jazzquizid' => $jazzquiz->id
        ]);
        foreach ($sessions as $session) {
            \mod_jazzquiz\jazzquiz_session::delete($session->id);
        }

        // Delete all questions for this quiz
        $DB->delete_records('jazzquiz_questions', [
            'jazzquizid' => $jazzquiz->id
        ]);

        // Finally delete the jazzquiz object
        $DB->delete_records('jazzquiz', [
            'id' => $jazzquiz->id
        ]);

    } catch (Exception $e) {
        return false;
    }

    return true;
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @uses $CFG
 * @return boolean
 * @todo Finish documenting this function
 **/
function jazzquiz_cron()
{
    return true;
}

function jazzquiz_pluginfile($course, $cm, $context, $file_area, $args, $forcedownload, $options = [])
{
    global $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    if ($file_area != 'question') {
        return false;
    }

    require_course_login($course, true, $cm);

    $question_id = (int) array_shift($args);

    $quiz = $DB->get_record('jazzquiz', [
        'id' => $cm->instance
    ]);

    if (!$quiz) {
        return false;
    }

    $question = $DB->get_record('jazzquiz_question', [
        'id' => $question_id,
        'quizid' => $cm->instance
    ]);

    if (!$question) {
        return false;
    }

    $fs = get_file_storage();

    $relative_path = implode('/', $args);
    $full_path = "/$context->id/mod_jazzquiz/$file_area/$question_id/$relative_path";

    if (!$file = $fs->get_file_by_hash(sha1($full_path)) or $file->is_directory()) {
        return false;
    }

    // Finally send the file
    send_stored_file($file);

    return false;
}

/**
 * Called via pluginfile.php -> question_pluginfile to serve files belonging to
 * a question in a question_attempt when that attempt is a quiz attempt.
 *
 * @package  mod_quiz
 * @category files
 * @param stdClass $course course settings object
 * @param stdClass $context context object
 * @param string $component the name of the component we are serving files for.
 * @param string $filearea the name of the file area.
 * @param int $qubaid the attempt usage id.
 * @param int $slot the id of a question in this quiz attempt.
 * @param array $args the remaining bits of the file path.
 * @param bool $forcedownload whether the user must be forced to download the file.
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function mod_jazzquiz_question_pluginfile($course, $context, $component, $filearea, $qubaid, $slot, $args, $forcedownload, $options = [])
{
    //require_once($CFG->dirroot . '/mod/quiz/locallib.php');
    /*
    $attemptobj = quiz_attempt::create_from_usage_id($qubaid);
    require_login($attemptobj->get_course(), false, $attemptobj->get_cm());

    if ($attemptobj->is_own_attempt() && !$attemptobj->is_finished()) {
        // In the middle of an attempt.
        if (!$attemptobj->is_preview_user()) {
            $attemptobj->require_capability('mod/quiz:attempt');
        }
        $isreviewing = false;

    } else {
        // Reviewing an attempt.
        $attemptobj->check_review_capability();
        $isreviewing = true;
    }

    if (!$attemptobj->check_file_access($slot, $isreviewing, $context->id,
        $component, $filearea, $args, $forcedownload)) {
        send_file_not_found();
    }*/

    $fs = get_file_storage();
    $relative_path = implode('/', $args);
    $full_path = "/$context->id/$component/$filearea/$relative_path";

    if (!$file = $fs->get_file_by_hash(sha1($full_path)) or $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

function jazzquiz_supports($feature)
{

    if (!defined('FEATURE_PLAGIARISM')) {
        define('FEATURE_PLAGIARISM', 'plagiarism');
    }

    // This plugin does support groups, just that the plugin code
    // manages it instead of using the Moodle provided functionality

    switch ($feature) {
        case FEATURE_GROUPS:
            return false;
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_GROUPMEMBERSONLY:
            return false;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return false;
        case FEATURE_COMPLETION_HAS_RULES:
            return false;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_RATE:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_PLAGIARISM:
            return false;
        case FEATURE_USES_QUESTIONS:
            return true;

        default:
            return null;
    }
}

