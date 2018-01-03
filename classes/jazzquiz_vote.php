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
class jazzquiz_vote
{
    public $session_id;
    public $slot;

    public function __construct($session_id, $slot = 0)
    {
        $this->session_id = $session_id;
        $this->slot = $slot;
    }

    public function get_results()
    {
        global $DB;
        $votes = $DB->get_records('jazzquiz_votes', [
            'sessionid' => $this->session_id,
            'slot' => $this->slot
        ]);
        return $votes;
    }

    public function has_user_voted($user_id)
    {
        global $DB;

        $all_votes = $DB->get_records('jazzquiz_votes', [ 'sessionid' => $this->session_id ]);
        if (!$all_votes) {
            return false;
        }

        // Go through all the existing votes
        foreach ($all_votes as $vote) {
            // Get all the users who voted for this
            $users_voted = explode(',', $vote->userlist);
            if ($users_voted) {
                // Go through all the users who has voted on this attempt
                foreach ($users_voted as $user_voted) {
                    // Is this the user who is currently trying to vote?
                    if ($user_voted == $user_id) {
                        // Yes, the user has already voted!
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function save_vote($vote_id, $user_id)
    {
        global $DB;
        if ($this->has_user_voted($user_id)) {
            return false;
        }
        $exists = $DB->record_exists('jazzquiz_votes', [ 'id' => $vote_id ]);
        if (!$exists) {
            return false;
        }
        $row = $DB->get_record('jazzquiz_votes', [ 'id' => $vote_id ]);
        if (!$row) {
            return false;
        }
        // Likely an honest vote.
        $row->finalcount++;
        if ($row->userlist != '') {
            $row->userlist .= ',';
        }
        $row->userlist .= $user_id;
        $DB->update_record('jazzquiz_votes', $row);
        return true;
    }

    public function prepare_options($jazzquiz_id, $question_type, $options, $slot)
    {
        global $DB;

        // Delete previous voting options for this session
        $DB->delete_records('jazzquiz_votes', ['sessionid' => $this->session_id]);

        // Add to database
        foreach ($options as $option) {
            $vote = new \stdClass();
            $vote->jazzquizid = $jazzquiz_id;
            $vote->sessionid = $this->session_id;
            $vote->attempt = $option['text'];
            $vote->initialcount = $option['count'];
            $vote->finalcount = 0;
            $vote->userlist = '';
            $vote->qtype = $question_type;
            $vote->slot = $slot;
            $DB->insert_record('jazzquiz_votes', $vote);
        }
    }

}
