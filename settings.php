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
 * @author    Andrew Hancox <andrewdchancox@googlemail.com>
 * @copyright 2015 Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/question/engine/bank.php');
require_once($CFG->dirroot . '/mod/jazzquiz/classes/improviser.php');

if ($ADMIN->fulltree) {
    $choices = [];
    $defaults = [];
    foreach (question_bank::get_creatable_qtypes() as $name => $qtype) {
        $fullpluginname = $qtype->plugin_name();
        $pluginname = explode('_', $fullpluginname)[1];
        $choices[$pluginname] = $qtype->menu_name();
        $defaults[$pluginname] = 1;
    }
    $settings->add(
        new admin_setting_configmulticheckbox(
            'mod_jazzquiz/enabledqtypes',
            get_string('enabled_question_types', 'jazzquiz'),
            get_string('enabled_question_types_info', 'jazzquiz'),
            $defaults,
            $choices
        )
    );

    $defaultimprovquestions = \mod_jazzquiz\improviser::get_default_improvised_questions();
    $choices = [];
    $defaults = [];
    foreach ($defaultimprovquestions as $question) {
        $choices[$question] = $question;
        $defaults[$question] = 1;
    }

    $settings->add(
        new admin_setting_configmulticheckbox(
            'mod_jazzquiz/improvenabled',
            get_string('improv_enabled_questions', 'jazzquiz'),
            get_string('improv_enabled_questions_info', 'jazzquiz'),
            $defaults,
            $choices
        )
    );
}