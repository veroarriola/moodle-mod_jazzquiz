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

class jazzquiz_vote
{

    public $session_id;

    public function __construct($session_id)
    {
        $this->session_id = $session_id;
    }

    public function get_results()
    {
        global $DB;

        $votes = $DB->get_records('jazzquiz_votes', [
            'sessionid' => $this->session_id
        ]);

        return $votes;
    }

    public function has_user_voted($user_id)
    {
        global $DB;

        $all_votes = $DB->get_records('jazzquiz_votes', [
            'sessionid' => $this->session_id
        ]);

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

        // Check if this user has already voted on any of the options already
        if ($this->has_user_voted($user_id)) {
            return 'alreadyvoted';
        }

        // Does it exist?
        $exists = $DB->record_exists('jazzquiz_votes', ['id' => $vote_id]);
        if (!$exists) {
            return 'error';
        }

        // Let's get it from the database
        $row = $DB->get_record('jazzquiz_votes', ['id' => $vote_id]);
        if (!$row) {
            return 'error';
        }

        // Seems like an honest vote. Let's add it!
        $row->finalcount++;
        if ($row->userlist != '') {
            $row->userlist .= ',';
        }
        $row->userlist .= $user_id;
        $DB->update_record('jazzquiz_votes', $row);

        return 'success';
    }

    public function prepare_options($rtq_id, $qtype, $options, $slot)
    {
        global $DB;

        // Delete previous voting options for this session
        $DB->delete_records('jazzquiz_votes', [
            'sessionid' => $this->session_id
        ]);

        // Add to database
        foreach ($options as $option) {
            $vote = new \stdClass();
            $vote->jazzquizid = $rtq_id;
            $vote->sessionid = $this->session_id;
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