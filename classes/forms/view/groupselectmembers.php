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

namespace mod_jazzquiz\forms\view;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 *
 *
 * @package   mod_jazzquiz
 * @author    John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright 2014 University of Wisconsin - Madison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class groupselectmembers extends \moodleform
{
    /**
     * Defines form definition
     *
     */
    public function definition()
    {
        $custom_data = $this->_customdata;
        $mform = $this->_form;

        /** @var \mod_jazzquiz\jazzquiz $rtq */
        $rtq = $custom_data['rtq'];

        $selected_group = $custom_data['selectedgroup'];

        $group_members = $rtq->get_groupmanager()->get_group_members($selected_group);

        $group_member_index = 1;

        foreach ($group_members as $group_member) {

            $attributes = [
                'group' => 1
            ];

            $mform->addElement('advcheckbox', 'gm' . $group_member_index, null, fullname($group_member), $attributes, [
                0,
                $group_member->id
            ]);

            $group_member_index++;

        }
        $this->add_checkbox_controller(1);

        $mform->addElement('submit', 'submitbutton', get_string('join_quiz', 'mod_jazzquiz'));

    }
}


