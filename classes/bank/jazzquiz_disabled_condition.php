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
 * Defines the jazzquiz_disabled_condition class.
 * @package   mod_jazzquiz
 * @copyright 2013 Ray Morris
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_jazzquiz\bank;

use core_question\bank\search\condition;

defined('MOODLE_INTERNAL') || die();

/**
 * Controls whether hidden or deleted questions are hidden in the list.
 *
 * @copyright 2013 Ray Morris
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jazzquiz_disabled_condition extends condition {

    /**
     * @var string $where
     */
    protected $where;

    /**
     * @var array $params
     */
    protected $params;

    /**
     * Constructor.
     * @param bool $hide whether to include old "deleted" questions.
     */
    public function __construct($hide = true) {
        global $DB;

        $config = get_config('mod_jazzquiz');
        $enabledqtypes = explode(',', $config->enabledqtypes);
        list($sql, $params) = $DB->get_in_or_equal($enabledqtypes, SQL_PARAMS_NAMED, 'aqdc');

        $this->where = 'q.qtype ' . $sql;
        $this->params = $params;
    }

    /**
     * Return the where part of the SQL.
     * @return string
     */
    public function where() {
        return $this->where;
    }

    /**
     * Return the parameters for the where.
     * @return array
     */
    public function params() {
        return $this->params;
    }
}
