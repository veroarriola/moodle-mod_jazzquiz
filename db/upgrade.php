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

defined('MOODLE_INTERNAL') || die();

function xmldb_jazzquiz_upgrade($old_version)
{
    global $DB;

    $db_manager = $DB->get_manager();

    if ($old_version < 2017010880) {
        $table = new xmldb_table('jazzquiz_votes');
        $field = new xmldb_field('slot', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        if (!$db_manager->field_exists($table, $field)) {
            $db_manager->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2017010880, 'jazzquiz');
    }

    if ($old_version < 2017082238) {
        $jazzquiz_table = new xmldb_table('jazzquiz');
        $jazzquiz_grades_table = new xmldb_table('jazzquiz_grades');
        $jazzquiz_questions_table = new xmldb_table('jazzquiz_questions');
        $jazzquiz_sessions_table = new xmldb_table('jazzquiz_sessions');
        $db_manager->drop_table($jazzquiz_grades_table);
        $field = new xmldb_field('reviewoptions', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $db_manager->drop_field($jazzquiz_table, $field);
        $field = new xmldb_field('scale', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $db_manager->drop_field($jazzquiz_table, $field);
        $field = new xmldb_field('graded', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '1');
        $db_manager->drop_field($jazzquiz_table, $field);
        $field = new xmldb_field('grademethod', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, null);
        $db_manager->drop_field($jazzquiz_table, $field);
        $field = new xmldb_field('points', XMLDB_TYPE_INTEGER, '10, 2', null, XMLDB_NOTNULL, null, '1.00', 'tries');
        $db_manager->drop_field($jazzquiz_questions_table, $field);
        $field = new xmldb_field('anonymize_responses', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '0');
        $db_manager->drop_field($jazzquiz_sessions_table, $field);
        $field = new xmldb_field('classresult', XMLDB_TYPE_NUMBER, '6, 2', null, null, null, null);
        $db_manager->drop_field($jazzquiz_sessions_table, $field);
        upgrade_mod_savepoint(true, 2017082238, 'jazzquiz');
    }

    return true;
}
