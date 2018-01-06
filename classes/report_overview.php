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

/**
 * @package     mod_jazzquiz
 * @author      Sebastian S. Gundersen <sebastsg@stud.ntnu.no>
 * @copyright   2018 NTNU
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_overview {

    /** @var output\report_renderer $renderer */
    protected $renderer;

    /**
     * Constructor.
     */
    public function __construct() {
        global $PAGE;
        $this->renderer = $PAGE->get_renderer('mod_jazzquiz', 'report');
    }

    /**
     * Escape the characters used for structuring the CSV contents.
     * @param string $text
     * @return string
     */
    private function csv_escape($text) {
        $text = str_replace("\r", '', $text);
        $text = str_replace("\n", '', $text);
        $text = str_replace(',', "\,", $text);
        return $text;
    }

    /**
     * @param jazzquiz_session $session
     * @param jazzquiz_attempt[] $attempts
     */
    private function output_csv_report($session, $attempts) {
        global $USER;
        $name = $session->data->id . '_' . $session->data->name;
        header('Content-Disposition: attachment; filename=session_' . $name . '.csv');
        // Go through the slots on the first attempt to output the header row.
        $attempt = reset($attempts);
        $slots = $attempt->quba->get_slots();
        echo 'Student,';
        foreach ($slots as $slot) {
            $questionattempt = $attempt->quba->get_question_attempt($slot);
            $question = $questionattempt->get_question();
            $questionname = str_replace('{IMPROV}', '', $question->name);
            $qtype = $attempt->quba->get_question_attempt($slot)->get_question()->get_type_name();
            // TODO: Make adding type optional?
            $questionname .= " ($qtype)";
            $questionname = $this->csv_escape($questionname);
            echo "$questionname,";
        }
        echo "\r\n";
        foreach ($attempts as $attempt) {
            // TODO: Remove this. It's pretty horrendous.
            // It might cause confusion if multiple teachers are in the same session.
            // Just want to remove the 'previews' field. There are better ways to handle its use.
            if ($USER->id == $attempt->data->userid) {
                continue;
            }
            echo $this->csv_escape($attempt->get_user_full_name()) . ',';
            $slots = $attempt->quba->get_slots();
            foreach ($slots as $slot) {
                $attempt_response = reset($attempt->get_response_data($slot));
                if (!$attempt_response) {
                    echo ',';
                    continue;
                }
                $attempt_response = $this->csv_escape($attempt_response);
                echo "$attempt_response,";
            }
            echo "\r\n";
        }
    }

    /**
     * @param jazzquiz_session $session
     * @param jazzquiz_attempt $attempt
     */
    private function output_csv_response($session, $attempt) {
        $slot = required_param('slot', PARAM_INT);
        $quba = $attempt->quba;
        $questionattempt = $quba->get_question_attempt($slot);
        $question = $questionattempt->get_question();
        $session->load_attempts();
        $responses = $session->get_question_results_list($slot);
        $responses = $responses['responses'];
        $name = $session->data->id . '_' . $session->data->name . '_' . $question->name;
        header('Content-Disposition: attachment; filename=session_' . $name . '.csv');
        echo $question->questiontext . "\r\n";
        foreach ($responses as $response) {
            echo $response['response'] . "\r\n";
        }
    }

    /**
     * @param jazzquiz_session $session
     * @param jazzquiz_attempt $attempt
     */
    private function output_csv_attendance($session, $attempt) {
        global $DB;

        $alluserids = $session->get_users();

        // This starts with all the ids, but is filtered below.
        // Should probably be refactored in the future.
        $notrespondeduserids = $alluserids;
        $totalresponded = [];
        $slots = $attempt->quba->get_slots();

        foreach ($slots as $slot) {
            $responded = $session->get_responded_list($slot);
            if ($responded) {
                $totalresponded = array_merge($totalresponded, $responded);
            }
        }

        $name = $session->data->id . '_' . $session->data->name;
        header('Content-Disposition: attachment; filename=session_' . $name . '_attendance.csv');

        if ($totalresponded) {
            $respondedwithcount = [];
            foreach ($totalresponded as $respondeduserid) {
                foreach ($notrespondeduserids as $notrespondedindex => $notrespondeduserid) {
                    if ($notrespondeduserid === $respondeduserid) {
                        unset($notrespondeduserids[$notrespondedindex]);
                        break;
                    }
                }
                if (!isset($respondedwithcount[$respondeduserid])) {
                    $respondedwithcount[$respondeduserid] = 1;
                } else {
                    $respondedwithcount[$respondeduserid]++;
                }
            }
            if ($respondedwithcount) {
                foreach ($respondedwithcount as $respondeduserid => $respondedcount) {
                    $user = $DB->get_record('user', ['id' => $respondeduserid]);
                    $userfullname = fullname($user);
                    echo $userfullname . ',' . $respondedcount . "\r\n";
                }
                foreach ($notrespondeduserids as $notrespondeduserid) {
                    $user = $DB->get_record('user', ['id' => $notrespondeduserid]);
                    $userfullname = fullname($user);
                    echo $userfullname . ",0\r\n";
                }
            }
        }
    }

    /**
     * @param jazzquiz $jazzquiz
     */
    private function output_csv($jazzquiz) {
        $sessionid = required_param('sessionid', PARAM_INT);
        $session = new jazzquiz_session($jazzquiz, $sessionid);
        $session->load_attempts();
        $attempt = reset($session->attempts);
        if (!$attempt) {
            return;
        }
        header('Content-Type: application/csv');
        $csvtype = required_param('csvtype', PARAM_ALPHANUM);
        switch ($csvtype) {
            case 'report':
                $this->output_csv_report($session, $session->attempts);
                break;
            case 'response':
                $this->output_csv_response($session, $attempt);
                break;
            case 'attendance':
                $this->output_csv_attendance($session, $attempt);
                break;
            default:
                break;
        }
    }

    /**
     * @param jazzquiz $jazzquiz
     * @param \moodle_url $pageurl
     */
    private function view_session($jazzquiz, $pageurl) {
        global $DB, $PAGE;

        $sessionid = required_param('sessionid', PARAM_INT);
        if (empty($sessionid)) {
            // If no session id just go to the home page
            $redirecturl = new \moodle_url('/mod/jazzquiz/reports.php', [
                'id' => $jazzquiz->cm->id,
                'quizid' => $jazzquiz->data->id
            ]);
            redirect($redirecturl, null, 3);
        }

        $session = new jazzquiz_session($jazzquiz, $sessionid);
        $session->load_attempts();
        $pageurl->param('sessionid', $sessionid);

        $sessions = $jazzquiz->get_sessions();
        $this->renderer->select_session($pageurl, $sessions, $sessionid);

        $quizattempt = reset($session->attempts);
        if (!$quizattempt) {
            echo '<div class="jazzquiz-box"><p>No attempts found.</p></div>';
            return;
        }

        $row = $quizattempt->data;
        $slots = $quizattempt->quba->get_slots();
        $quba = $quizattempt->quba;

        $PAGE->requires->js('/mod/jazzquiz/js/core.js');
        $PAGE->requires->js('/mod/jazzquiz/js/instructor.js');

        echo '<script> var jazzquizResponses = []; </script>';

        $totalresponded = [];

        $id = required_param('id', PARAM_INT);
        $quizid = required_param('quizid', PARAM_INT);

        echo '<div id="report_overview_controls" class="jazzquiz-box">';
        echo "<button class=\"btn btn-primary\" onclick=\"jQuery('#report_overview_responded').fadeIn();jQuery('#report_overview_responses').fadeOut();\">Attendance</button>";
        echo "<button class=\"btn btn-primary\" onclick=\"jQuery('#report_overview_responses').fadeIn();jQuery('#report_overview_responded').fadeOut();\">Responses</button>";
        $urlparams = "?id=$id&quizid=$quizid&reporttype=overview&action=csv&csvtype=report&download&sessionid=$sessionid";
        echo '<a href="reports.php' . $urlparams . '">Download report</a>';
        echo '</div>';

        echo '<div id="report_overview_responses" class="hidden">';

        foreach ($slots as $slot) {
            $questionattempt = $quba->get_question_attempt($slot);
            $question = $questionattempt->get_question();
            $responses = $session->get_question_results_list($slot);
            $responses = $responses['responses'];

            $responded = $session->get_responded_list($slot);
            if ($responded) {
                $totalresponded = array_merge($totalresponded, $responded);
            }

            $wrapperid = 'jazzquiz_wrapper_responses_' . intval($slot);
            $tableid = 'responses_wrapper_table_' . intval($slot);

            $questionname = str_replace('{IMPROV}', '', $question->name);
            $qtype = $quba->get_question_attempt($slot)->get_question()->get_type_name();

            echo '<div class="jazzquiz-box" id="' . $wrapperid . '">'
                . "<h2>$questionname</h2>"
                . '<span class="jazzquiz-latex-wrapper">'
                . '<span class="filter_mathjaxloader_equation">' . $question->questiontext . '</span>'
                . '</span>'
                . '<table id="' . $tableid . '" class="jazzquiz-responses-overview"></table>';

            $url_params = "?id=$id&quizid=$quizid&reporttype=overview&action=csv&csvtype=response&download&sessionid=$sessionid&slot=$slot";
            echo '<br><a href="reports.php' . $url_params . '">Download responses</a>';
            echo '</div>';

            echo '<script>'
                . "jazzquizResponses[$slot] = " . json_encode($responses) . ';'
                . 'setTimeout(function() {'
                . 'jazzquiz.quiz.attemptId = ' . $row->id . ';'

                // TODO: This is kind of a hack... Should refactor the JavaScript.
                . 'jazzquiz.options.showResponses = true;'
                . 'jazzquiz.state = "reviewing";'

                . 'jazzquiz.setResponses("'
                . $wrapperid . '", "' . $tableid . '", jazzquizResponses[' . $slot . '], "' . $qtype . '", "report_' . $slot . '"'
                . ');'
                . '}, 1000);'
                . '</script>';

        }

        echo '</div>';

        $alluserids = $session->get_users();

        // This starts with all the ids, but is filtered below.
        // Should probably be refactored in the future.
        $notrespondeduserids = $alluserids;

        echo '<div id="report_overview_responded" class="jazzquiz-box">';
        echo '<h2>' . get_string('attendance_list', 'jazzquiz') . '</h2>';
        if ($totalresponded) {
            $respondedwithcount = [];
            foreach ($totalresponded as $respondeduserid) {
                foreach ($notrespondeduserids as $notrespondedindex => $notrespondeduserid) {
                    if ($notrespondeduserid === $respondeduserid) {
                        unset($notrespondeduserids[$notrespondedindex]);
                        break;
                    }
                }
                if (!isset($respondedwithcount[$respondeduserid])) {
                    $respondedwithcount[$respondeduserid] = 1;
                } else {
                    $respondedwithcount[$respondeduserid]++;
                }
            }
            if ($respondedwithcount) {
                $attendancelistcsv = '';
                echo '<table>';
                echo '<tr><th>Student</th><th>Responses</th></tr>';
                // TODO: Refactor
                foreach ($respondedwithcount as $respondeduserid => $respondedcount) {
                    $user = $DB->get_record('user', ['id' => $respondeduserid]);
                    $userfullname = fullname($user);
                    echo '<tr>';
                    echo '<td>' . $userfullname . '</td>';
                    echo '<td>' . $respondedcount . ' responses</td>';
                    echo '</tr>';
                    $attendancelistcsv .= $userfullname . ',' . $respondedcount . '<br>';
                }
                foreach ($notrespondeduserids as $notrespondeduserid) {
                    $user = $DB->get_record('user', ['id' => $notrespondeduserid]);
                    $userfullname = fullname($user);
                    echo '<tr>';
                    echo '<td>' . $userfullname . '</td>';
                    echo '<td>0 responses</td>';
                    echo '</tr>';
                    $attendancelistcsv .= $userfullname . ',0<br>';
                }

                echo '</table>';
                echo '<br>';
                echo '<p><b>' . count($alluserids) . '</b> students joined the quiz.</p>';
                echo '<p><b>' . count($respondedwithcount) . '</b> students answered at least one question.</p>';
                echo '<br>';
                $url_params = "?id=$id&quizid=$quizid&reporttype=overview&action=csv&csvtype=attendance&download&sessionid=$sessionid";
                echo '<a href="reports.php' . $url_params . '">Download attendance list</a>';
            }
        }
        echo '</div>';
    }

    /**
     * Handle the request for this specific report
     *
     * @param jazzquiz $jazzquiz
     * @param string $action
     * @param \moodle_url $url
     */
    public function handle_request($jazzquiz, $action, $url) {
        switch ($action) {
            case 'viewsession':
                $this->view_session($jazzquiz, $url);
                break;
            case 'csv':
                $this->output_csv($jazzquiz);
                break;
            default:
                $sessions = $jazzquiz->get_sessions();
                $this->renderer->select_session($url, $sessions);
                break;
        }
    }

}
