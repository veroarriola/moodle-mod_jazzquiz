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
function jazzquiz_add_instance($jazzquiz) {
    global $DB;

    $jazzquiz->timemodified = time();
    $jazzquiz->timecreated = time();
    if (empty($jazzquiz->graded)) {
        $jazzquiz->graded = 0;
        $jazzquiz->scale = 0;
    }

    // Set default values for removed form elements
    $jazzquiz->graded = 0;
    $jazzquiz->scale = 0;
    $jazzquiz->grademethod = 1;

    // add all review options to the db object in the review options field.
    $jazzquiz->reviewoptions = jazzquiz_process_review_options($jazzquiz);

    $jazzquiz->id = $DB->insert_record('jazzquiz', $jazzquiz);

    jazzquiz_after_add_or_update($jazzquiz);

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
function jazzquiz_update_instance($jazzquiz) {
    global $DB, $PAGE;

    $jazzquiz->timemodified = time();
    $jazzquiz->id = $jazzquiz->instance;
    if (empty($jazzquiz->graded)) {
        $jazzquiz->graded = 0;
        $jazzquiz->scale = 0;
    }
    // add all review options to the db object in the review options field.
    $jazzquiz->reviewoptions = jazzquiz_process_review_options($jazzquiz);

    $DB->update_record('jazzquiz', $jazzquiz);

    jazzquiz_after_add_or_update($jazzquiz);

    // after updating grade item we need to re-grade the sessions
    $jazzquiz = $DB->get_record('jazzquiz', array('id' => $jazzquiz->id));  // need the actual db record
    $course = $DB->get_record('course', array('id' => $jazzquiz->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('jazzquiz', $jazzquiz->id, $course->id, false, MUST_EXIST);
    $rtq = new \mod_jazzquiz\jazzquiz($cm, $course, $jazzquiz, array('pageurl' => $PAGE->url));
    $rtq->get_grader()->save_all_grades();


    return true;
}

/**
 * Proces the review options on the quiz settings page
 *
 * @param \mod_jazzquiz\jazzquiz $jazzquiz
 * @return string
 */
function jazzquiz_process_review_options($jazzquiz) {

    $afterreviewoptions = \mod_jazzquiz\jazzquiz::get_review_options_from_form($jazzquiz, 'after');

    $reviewoptions = new stdClass();
    $reviewoptions->after = $afterreviewoptions;

    // add all review options to the db object in the review options field.
    return json_encode($reviewoptions);
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 **/
function jazzquiz_delete_instance($id) {
    global $DB, $CFG;

    require_once($CFG->dirroot . '/mod/jazzquiz/locallib.php');
    require_once($CFG->libdir . '/questionlib.php');
    require_once($CFG->dirroot . '/question/editlib.php');

    try {
        // make sure the record exists
        $jazzquiz = $DB->get_record('jazzquiz', array('id' => $id), '*', MUST_EXIST);

        // go through each session and then delete them (also deletes all attempts for them)
        $sessions = $DB->get_records('jazzquiz_sessions', array('jazzquizid' => $jazzquiz->id));
        foreach ($sessions as $session) {
            \mod_jazzquiz\jazzquiz_session::delete($session->id);
        }

        // delete all questions for this quiz
        $DB->delete_records('jazzquiz_questions', array('jazzquizid' => $jazzquiz->id));

        // finally delete the jazzquiz object
        $DB->delete_records('jazzquiz', array('id' => $jazzquiz->id));
    } catch(Exception $e) {
        return false;
    }

    return true;
}

/**
 * Function to call other functions for after add or update of a quiz settings page
 *
 * @param int $jazzquiz
 */
function jazzquiz_after_add_or_update($jazzquiz) {

    jazzquiz_grade_item_update($jazzquiz);
}

/**
 * Update the grade item depending on settings passed in
 *
 *
 * @param stdClass   $jazzquiz
 * @param array|null $grades
 *
 * @return int Returns GRADE_UPDATE_OK, GRADE_UPDATE_FAILED, GRADE_UPDATE_MULTIPLE or GRADE_UPDATE_ITEM_LOCKED
 */
function jazzquiz_grade_item_update($jazzquiz, $grades = null) {
    global $CFG;
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir . '/gradelib.php');
    }

    if (array_key_exists('cmidnumber', $jazzquiz)) { // May not be always present.
        $params = array('itemname' => $jazzquiz->name, 'idnumber' => $jazzquiz->cmidnumber);
    } else {
        $params = array('itemname' => $jazzquiz->name);
    }

    if ($jazzquiz->graded == 0) {
        $params['gradetype'] = GRADE_TYPE_NONE;

    } else if ($jazzquiz->graded == 1) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = $jazzquiz->scale;
        $params['grademin'] = 0;

    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/jazzquiz', $jazzquiz->course, 'mod', 'jazzquiz', $jazzquiz->id, 0, $grades, $params);
}


/**
 * Update grades depending on the userid and other settings
 *
 * @param      $jazzquiz
 * @param int  $userid
 * @param bool $nullifnone
 *
 * @return int Returns GRADE_UPDATE_OK, GRADE_UPDATE_FAILED, GRADE_UPDATE_MULTIPLE or GRADE_UPDATE_ITEM_LOCKED
 */
function jazzquiz_update_grades($jazzquiz, $userid = 0, $nullifnone = true) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    if (!$jazzquiz->graded) {
        return jazzquiz_grade_item_update($jazzquiz);

    } else if ($grades = \mod_jazzquiz\utils\grade::get_user_grade($jazzquiz, $userid)) {
        return jazzquiz_grade_item_update($jazzquiz, $grades);

    } else if ($userid and $nullifnone) {
        $grade = new stdClass();
        $grade->userid = $userid;
        $grade->rawgrade = null;

        return jazzquiz_grade_item_update($jazzquiz, $grade);

    } else {
        return jazzquiz_grade_item_update($jazzquiz);
    }

}


/**
 * Reset the grade book
 *
 * @param        $courseid
 * @param string $type
 */
function jazzquiz_reset_gradebook($courseid, $type = '') {


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
function jazzquiz_cron() {
    return true;
}


//////////////////////////////////////////////////////////////////////////////////////
/// Any other jazzquiz functions go here.  Each of them must have a name that
/// starts with jazzquiz_


function jazzquiz_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    global $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    if ($filearea != 'question') {
        return false;
    }

    require_course_login($course, true, $cm);

    $questionid = (int)array_shift($args);

    if (!$quiz = $DB->get_record('jazzquiz', array('id' => $cm->instance))) {
        return false;
    }

    if (!$question = $DB->get_record('jazzquiz_question', array('id' => $questionid, 'quizid' => $cm->instance))) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_jazzquiz/$filearea/$questionid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // finally send the file
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
 * @param string   $component the name of the component we are serving files for.
 * @param string   $filearea the name of the file area.
 * @param int      $qubaid the attempt usage id.
 * @param int      $slot the id of a question in this quiz attempt.
 * @param array    $args the remaining bits of the file path.
 * @param bool     $forcedownload whether the user must be forced to download the file.
 * @param array    $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function mod_jazzquiz_question_pluginfile($course, $context, $component,
                                            $filearea, $qubaid, $slot, $args, $forcedownload, array $options = array()) {
    global $CFG;
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
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/$component/$filearea/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

function jazzquiz_supports($feature) {

    if (!defined('FEATURE_PLAGIARISM')) {
        define('FEATURE_PLAGIARISM', 'plagiarism');
    }

    // this plugin does support groups, just that the plugin code
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
            return true;
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

