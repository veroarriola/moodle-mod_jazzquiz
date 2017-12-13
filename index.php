<?php
//
// Capability definitions for the jazzquiz module.
//
// The capabilities are loaded into the database table when the module is
// installed or updated. Whenever the capability definitions are updated,
// the module version number should be bumped up.
//
// The system has four possible values for a capability:
// CAP_ALLOW, CAP_PREVENT, CAP_PROHIBIT, and inherit (not set).
//
//
// CAPABILITY NAMING CONVENTION
//
// It is important that capability names are unique. The naming convention
// for capabilities that are specific to modules and blocks is as follows:
//   [mod/block]/<component_name>:<capabilityname>
//
// component_name should be the same as the directory name of the mod or block.
//
// Core moodle capabilities are defined thus:
//    moodle/<capabilityclass>:<capabilityname>
//
// Examples: mod/forum:viewpost
//           block/recent_activity:view
//           moodle/site:deleteuser
//
// The variable name for the capability definitions array follows the format
//   $<componenttype>_<component_name>_capabilities
//
// For the core capabilities, the variable is $moodle_capabilities.

/**
 * This page lists all the instances of jazzquiz in a particular course
 *
 * @author: Davosmith
 * @package jazzquiz
 **/

require_once('../../config.php');
require_once('lib.php');

$id = required_param('id', PARAM_INT); // Course ID
$course = $DB->get_record('course', [ 'id' => $id ]);
if (!$course) {
    error('Course ID is incorrect');
}

$PAGE->set_url(new moodle_url('/mod/jazzquiz/index.php', [ 'id' => $course->id ]));
require_course_login($course);
$PAGE->set_pagelayout('incourse');

/// Get all required strings
$strjazzquizzes = get_string('modulenameplural', 'jazzquiz');
$strjazzquiz = get_string('modulename', 'jazzquiz');

$PAGE->navbar->add($strjazzquizzes);
$PAGE->set_title(strip_tags($course->shortname . ': ' . $strjazzquizzes));
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

/// Get all the appropriate data
$jazzquizzes = get_all_instances_in_course('jazzquiz', $course);
if (!$jazzquizzes) {
    notice('There are no jazzquizes', "../../course/view.php?id=$course->id");
    die;
}

/// Print the list of instances (your module will probably extend this)
$timenow = time();
$strname = get_string('name');
$strweek = get_string('week');
$strtopic = get_string('topic');

$table = new html_table();

if ($course->format == 'weeks') {
    $table->head = [ $strweek, $strname ];
    $table->align = [ 'center', 'left' ];
} else if ($course->format == "topics") {
    $table->head = [ $strtopic, $strname ];
    $table->align = [ 'center', 'left' ];
} else {
    $table->head = [ $strname ];
    $table->align = [ 'left', 'left' ];
}

foreach ($jazzquizzes as $jazzquiz) {
    $url = new moodle_url('/mod/jazzquiz/view.php', [
        'cmid' => $jazzquiz->coursemodule
    ]);
    if (!$jazzquiz->visible) {
        // Show dimmed if the mod is hidden
        $link = '<a class="dimmed" href="' . $url . '">' . $jazzquiz->name . '</a>';
    } else {
        // Show normal if the mod is visible
        $link = '<a href="' . $url . '">' . $jazzquiz->name . '</a>';
    }
    if ($course->format == 'weeks' || $course->format == 'topics') {
        $table->data[] = [ $jazzquiz->section, $link ];
    } else {
        $table->data[] = [ $link ];
    }
}

echo html_writer::table($table);
echo $OUTPUT->footer();

