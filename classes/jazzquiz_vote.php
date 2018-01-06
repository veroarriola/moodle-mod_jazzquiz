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
 * @package     mod_jazzquiz
 * @author      Sebastian S. Gundersen <sebastsg@stud.ntnu.no>
 * @copyright   2018 NTNU
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jazzquiz_vote {

    /** @var int $sessionid */
    protected $sessionid;

    /** @var int $slot */
    protected $slot;

    /**
     * Constructor.
     * @param int $sessionid
     * @param int $slot
     */
    public function __construct($sessionid, $slot = 0) {
        $this->sessionid = $sessionid;
        $this->slot = $slot;
    }

    /**
     * Get the results for the vote.
     * @return array
     */
    public function get_results() {
        global $DB;
        $votes = $DB->get_records('jazzquiz_votes', [
            'sessionid' => $this->sessionid,
            'slot' => $this->slot
        ]);
        return $votes;
    }

    /**
     * Check whether a user has voted or not.
     * @param int $userid
     * @return bool
     */
    public function has_user_voted($userid) {
        global $DB;
        $allvotes = $DB->get_records('jazzquiz_votes', ['sessionid' => $this->sessionid]);
        if (!$allvotes) {
            return false;
        }
        // Go through all the existing votes
        foreach ($allvotes as $vote) {
            // Get all the users who voted for this
            $usersvoted = explode(',', $vote->userlist);
            if (!$usersvoted) {
                continue;
            }
            // Go through all the users who has voted on this attempt
            foreach ($usersvoted as $uservoted) {
                // Is this the user who is currently trying to vote?
                if ($uservoted == $userid) {
                    // Yes, the user has already voted!
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Save the vote for a user.
     * @param int $voteid
     * @param int $userid
     * @return bool
     */
    public function save_vote($voteid, $userid) {
        global $DB;
        if ($this->has_user_voted($userid)) {
            return false;
        }
        $exists = $DB->record_exists('jazzquiz_votes', ['id' => $voteid]);
        if (!$exists) {
            return false;
        }
        $row = $DB->get_record('jazzquiz_votes', ['id' => $voteid]);
        if (!$row) {
            return false;
        }
        // Likely an honest vote.
        $row->finalcount++;
        if ($row->userlist != '') {
            $row->userlist .= ',';
        }
        $row->userlist .= $userid;
        $DB->update_record('jazzquiz_votes', $row);
        return true;
    }

    /**
     * Insert the options for the vote.
     * @param int $jazzquizid
     * @param string $qtype
     * @param array $options
     * @param int $slot
     */
    public function prepare_options($jazzquizid, $qtype, $options, $slot) {
        global $DB;

        // Delete previous voting options for this session
        $DB->delete_records('jazzquiz_votes', ['sessionid' => $this->sessionid]);

        // Add to database
        foreach ($options as $option) {
            $vote = new \stdClass();
            $vote->jazzquizid = $jazzquizid;
            $vote->sessionid = $this->sessionid;
            $vote->attempt = $option['text'];
            $vote->initialcount = $option['count'];
            $vote->finalcount = 0;
            $vote->userlist = '';
            $vote->qtype = $qtype;
            $vote->slot = $slot;
            $DB->insert_record('jazzquiz_votes', $vote);
        }
    }

}
