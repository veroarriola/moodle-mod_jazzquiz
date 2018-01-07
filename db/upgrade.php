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

defined('MOODLE_INTERNAL') || die();

function xmldb_jazzquiz_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2018010509) {

        // Define field notime to be dropped from jazzquiz_questions.
        $table = new xmldb_table('jazzquiz_questions');
        $field = new xmldb_field('notime');

        // Conditionally launch drop field notime.
        if ($dbman->field_exists($table, $field)) {

            // Set all questiontime fields to 0 if notime is 1.
            $DB->execute('UPDATE {jazzquiz_questions} SET questiontime = 0 WHERE notime = 1');

            // Drop the field.
            $dbman->drop_field($table, $field);
        }

        // jazzquiz savepoint reached.
        upgrade_mod_savepoint(true, 2018010509, 'jazzquiz');
    }

    if ($oldversion < 2018010527) {

        // Define field attemptnum to be dropped from jazzquiz_attempts.
        $table = new xmldb_table('jazzquiz_attempts');
        $field = new xmldb_field('attemptnum');

        // Conditionally launch drop field attemptnum.
        if ($dbman->field_exists($table, $field)) {

            // Drop the field.
            $dbman->drop_field($table, $field);
        }

        // Define field preview to be dropped from jazzquiz_attempts.
        $table = new xmldb_table('jazzquiz_attempts');
        $field = new xmldb_field('preview');

        // Conditionally launch drop field preview.
        if ($dbman->field_exists($table, $field)) {

            // Drop the field.
            $dbman->drop_field($table, $field);
        }

        // jazzquiz savepoint reached.
        upgrade_mod_savepoint(true, 2018010527, 'jazzquiz');
    }

    return true;
}
