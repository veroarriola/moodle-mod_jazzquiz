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

if ($ADMIN->fulltree) {

    $choices = [];
    $defaults = [];

    foreach (question_bank::get_creatable_qtypes() as $name => $question_type) {
        $full_plugin_name = $question_type->plugin_name();
        $plugin_name = explode('_', $full_plugin_name)[1];
        $choices[$plugin_name] = $question_type->menu_name();
        $defaults[$plugin_name] = 1;
    }

    $settings->add(
        new admin_setting_configmulticheckbox(
            'jazzquiz/enabledqtypes',
            get_string('enabled_question_types', 'jazzquiz'),
            get_string('enabled_question_types_info', 'jazzquiz'),
            $defaults,
            $choices
        )
    );

}