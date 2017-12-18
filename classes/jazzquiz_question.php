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

namespace mod_jazzquiz;

defined('MOODLE_INTERNAL') || die();

/**
 * A JazzQuiz question object
 *
 * @package     mod_jazzquiz
 * @author      John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright   2014 University of Wisconsin - Madison
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jazzquiz_question
{
    /** @var \stdClass $data */
    public $data;

    /** @var \stdClass $question the question bank question data */
    public $question;

    /** @var int $slot The quba slot for this question */
    public $slot;

    /**
     * @param \stdClass $data (jazzquiz_question)
     * @param \stdClass $question
     */
    public function __construct($data, $question)
    {
        $this->data = $data;
        $this->question = $question;
        $this->slot = null;
    }

}
