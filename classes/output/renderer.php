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

namespace mod_jazzquiz\output;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/questionlib.php');

/**
 * To load a question without refreshing the page, we need the JavaScript for the question.
 * Moodle stores this in page_requirements_manager, but there is no way to read the JS that is required.
 * This class takes in the manager and keeps the JS for when we want to get a diff.
 * NOTE: This class is placed here because it will only ever be used by renderer::render_question_form()
 * TODO: Look into removing this class in the future.
 * @package    mod_jazzquiz\output
 * @author     Sebastian S. Gundersen <sebastsg@stud.ntnu.no>
 * @copyright  2018 NTNU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_requirements_diff extends \page_requirements_manager {

    /** @var array $beforeinitjs */
    private $beforeinitjs;

    /** @var array $beforeamdjs */
    private $beforeamdjs;

    /** @var array $beforecss */
    private $beforecss;

    /**
     * Constructor.
     * @param \page_requirements_manager $manager
     */
    public function __construct($manager) {
        $this->beforeinitjs = $manager->jsinitcode;
        $this->beforeamdjs = $manager->amdjscode;
        $this->beforecss = $manager->cssurls;
    }

    /**
     * Run an array_diff on the required JavaScript when this
     * was constructed and the one passed to this function.
     * @param \page_requirements_manager $manager
     * @return array the JavaScript that was added in-between constructor and this call.
     */
    public function get_js_diff($manager) {
        $jsinitcode = array_diff($manager->jsinitcode, $this->beforeinitjs);
        $amdjscode = array_diff($manager->amdjscode, $this->beforeamdjs);
        return array_merge($jsinitcode, $amdjscode);
    }

    /**
     * Run an array_diff on the required CSS when this
     * was constructed and the one passed to this function.
     * @param \page_requirements_manager $manager
     * @return array the CSS that was added in-between constructor and this call.
     */
    public function get_css_diff($manager) {
        return array_keys(array_diff($manager->cssurls, $this->beforecss));
    }

}

/**
 * Quiz renderer
 *
 * @package    mod_jazzquiz
 * @author     Sebastian S. Gundersen <sebastsg@stud.ntnu.no>
 * @copyright  2014 University of Wisconsin - Madison
 * @copyright  2018 NTNU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {

    /**
     * Render the header for the page.
     * @param \mod_jazzquiz\jazzquiz $jazzquiz
     * @param string $tab The active tab on the page
     */
    public function header($jazzquiz, $tab) {
        echo $this->output->header();
        echo jazzquiz_view_tabs($jazzquiz, $tab);
    }

    /**
     * Render the footer for the page.
     */
    public function footer() {
        echo $this->output->footer();
    }

    /**
     * For instructors.
     * @param \moodleform $sessionform
     */
    public function start_session_form($sessionform) {
        echo $this->render_from_template('jazzquiz/start_session', [
            'form' => $sessionform->render()
        ]);
    }

    /**
     * For instructors.
     * @param \mod_jazzquiz\jazzquiz $jazzquiz
     */
    public function continue_session_form($jazzquiz) {
        global $PAGE;
        $cmid = $jazzquiz->cm->id;
        $id = $jazzquiz->data->id;
        echo $this->render_from_template('jazzquiz/continue_session', [
            'path' => $PAGE->url->get_path() . "?id=$cmid&quizid=$id&action=quizstart"
        ]);
    }

    /**
     * Show the "join quiz" form for students.
     * @param \mod_jazzquiz\forms\view\student_start_form $studentstartform
     * @param \mod_jazzquiz\jazzquiz_session $session
     */
    public function join_quiz_form($studentstartform, $session) {
        echo $this->render_from_template('jazzquiz/join_session', [
            'name' => $session->data->name,
            'started' => ($session->attempt !== false),
            'form' => $studentstartform->render()
        ]);
    }

    /**
     * Show the "quiz not running" page for students.
     * @param int $cmid the course module id for the quiz
     */
    public function quiz_not_running($cmid) {
        global $PAGE;
        echo $this->render_from_template('jazzquiz/no_session', [
            'reload' => $PAGE->url->get_path() . '?id=' . $cmid
        ]);
    }

    /**
     * Renders the quiz to the page
     * @param \mod_jazzquiz\jazzquiz_session $session
     */
    public function render_quiz($session) {
        $this->require_quiz($session);
        $buttons = function($buttons) {
            $result = [];
            foreach ($buttons as $button) {
                $result[] = [
                    'icon' => $button[0],
                    'id' => $button[1],
                    'text' => get_string($button[1], 'jazzquiz')
                ];
            }
            return $result;
        };
        echo $this->render_from_template('jazzquiz/quiz', [
            'buttons' => $buttons([
                ['repeat', 'repoll'],
                ['bar-chart', 'vote'],
                ['edit', 'improvise'],
                ['bars', 'jump'],
                ['forward', 'next'],
                ['close', 'end'],
                ['expand', 'fullscreen'],
                ['window-close', 'quit'],
                ['square-o', 'responses'],
                ['square-o', 'answer']
            ]),
            'instructor' => $session->jazzquiz->is_instructor()
        ]);
    }

    /**
     * Render the question specified by slot
     * @param \mod_jazzquiz\jazzquiz $jazzquiz
     * @param \question_usage_by_activity $quba
     * @param int $slot
     * @param bool $review Whether or not we're reviewing the attempt
     * @param string|\stdClass $reviewoptions Can be string for overall actions like "edit" or an object of review options
     * @return string the HTML fragment for the question
     */
    public function render_question($jazzquiz, $quba, $slot, $review = false, $reviewoptions = '') {
        $displayoptions = $jazzquiz->get_display_options($review, $reviewoptions);
        $quba->render_question_head_html($slot);
        return $quba->render_question($slot, $displayoptions, $slot);
    }

    /**
     * Render a specific question in its own form so it can be submitted
     * independently of the rest of the questions
     *
     * @param int $slot the id of the question we're rendering
     * @param \mod_jazzquiz\jazzquiz_attempt $attempt
     * @param \mod_jazzquiz\jazzquiz $jazzquiz
     * @param bool $isinstructor
     *
     * @return string[] html, javascript, css
     */
    public function render_question_form($slot, $attempt, $jazzquiz, $isinstructor) {
        global $PAGE;
        $differ = new page_requirements_diff($PAGE->requires);
        ob_start();
        $questionhtml = $this->render_question($jazzquiz, $attempt->quba, $slot);
        $questionhtmlechoed = ob_get_clean();
        $js = implode("\n", $differ->get_js_diff($PAGE->requires));
        $css = $differ->get_css_diff($PAGE->requires);
        $output = $this->render_from_template('jazzquiz/question', [
            'instructor' => $isinstructor,
            'question' => $questionhtml . $questionhtmlechoed,
            'slot' => $slot
        ]);
        return [$output, $js, $css];
    }

    /**
     * Renders and echos the home page for the responses section
     * @param \moodle_url $url
     * @param \stdClass[] $sessions
     * @param int $selectedid
     */
    public function get_select_session_context($url, $sessions, $selectedid) {
        $selecturl = clone($url);
        $selecturl->param('action', 'view');
        usort($sessions, function ($a, $b) {
            return strcmp(strtolower($a->name), strtolower($b->name));
        });
        return [
            'method' => 'get',
            'action' => $selecturl->out_omit_querystring(),
            'formid' => 'jazzquiz_select_session_form',
            'id' => 'jazzquiz_select_session',
            'name' => 'sessionid',
            'options' => array_map(function ($session) use ($selectedid) {
                return [
                    'name' => $session->name,
                    'value' => $session->id,
                    'selected' => intval($selectedid) === intval($session->id),
                    'optgroup' => false
                ];
            }, $sessions),
            'params' => array_map(function ($key, $value) {
                return [
                    'name' => $key,
                    'value' => $value
                ];
            }, array_keys($selecturl->params()), $selecturl->params()),
        ];
    }

    /**
     * Render the list questions view for the edit page
     *
     * @param \mod_jazzquiz\jazzquiz $jazzquiz
     * @param array $questions Array of questions
     * @param string $questionbankview HTML for the question bank view
     * @param \moodle_url $url
     */
    public function list_questions($jazzquiz, $questions, $questionbankview, $url) {
        $slot = 1;
        $list = [];
        foreach ($questions as $question) {
            $editurl = clone($url);
            $editurl->param('action', 'editquestion');
            $editurl->param('questionid', $question->data->id);
            $list[] = [
                'id' => $question->data->id,
                'name' => $question->question->name,
                'first' => $slot === 1,
                'last' => $slot === count($questions),
                'slot' => $slot,
                'editurl' => $editurl,
                'icon' => print_question_icon($question->question)
            ];
            $slot++;
        }

        echo $this->render_from_template('jazzquiz/edit_question_list', [
            'questions' => $list,
            'qbank' => $questionbankview
        ]);

        $this->require_edit($jazzquiz->cm->id);
    }

    public function session_is_open_error() {
        echo \html_writer::tag('h3', get_string('edit_page_open_session_error', 'jazzquiz'));
    }

    /**
     * @param \mod_jazzquiz\jazzquiz_session $session
     * @param \moodle_url $url
     */
    public function view_session_report($session, $url) {
        global $DB;

        $slots = [];
        $students = [];

        $quizattempt = reset($session->attempts);
        if (!$quizattempt) {
            echo '<div class="jazzquiz-box"><p>';
            echo get_string('no_attempts_found', 'jazzquiz');
            echo '</p></div>';
            return [];
        }
        $quba = $quizattempt->quba;
        $qubaslots = $quba->get_slots();
        $totalresponded = [];

        foreach ($qubaslots as $qubaslot) {
            $questionattempt = $quba->get_question_attempt($qubaslot);
            $question = $questionattempt->get_question();
            $results = $session->get_question_results_list($qubaslot);
            list($results['responses'], $mergecount) = $session->get_merged_responses($qubaslot, $results['responses']);
            $responses = $results['responses'];

            $responded = $session->get_responded_list($qubaslot);
            if ($responded) {
                $totalresponded = array_merge($totalresponded, $responded);
            }

            $slots[] = [
                'num' => $qubaslot,
                'name' => str_replace('{IMPROV}', '', $question->name),
                'type' => $quba->get_question_attempt($qubaslot)->get_question()->get_type_name(),
                'description' => $question->questiontext,
                'responses' => $responses
            ];
        }

        // TODO: Slots should not be passed as parameter to AMD module.
        // It quickly gets over 1KB, which shows debug warning.
        $this->require_review($session, $slots);

        $notrespondeduserids = $session->get_users();
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
        foreach ($respondedwithcount as $respondeduserid => $respondedcount) {
            $user = $DB->get_record('user', ['id' => $respondeduserid]);
            $students[] = [
                'name' => fullname($user),
                'count' => $respondedcount
            ];
        }
        foreach ($notrespondeduserids as $notrespondeduserid) {
            $user = $DB->get_record('user', ['id' => $notrespondeduserid]);
            $students[] = [
                'name' => fullname($user),
                'count' => 0
            ];
        }

        $jazzquiz = $session->jazzquiz;
        $sessions = $jazzquiz->get_sessions();
        return [
            'select_session' => $jazzquiz->renderer->get_select_session_context($url, $sessions, $session->data->id),
            'session' => [
                'slots' => $slots,
                'students' => $students,
                'count_total' => count($students),
                'count_answered' => count($students) - count($notrespondeduserids),
                'cmid' => $jazzquiz->cm->id,
                'quizid' => $jazzquiz->data->id,
                'id' => $session->data->id
            ]
        ];
    }

    /**
     * @param \mod_jazzquiz\jazzquiz_session $session
     */
    public function require_core($session) {
        $this->page->requires->js_call_amd('mod_jazzquiz/core', 'initialize', [
            $session->jazzquiz->cm->id,
            $session->jazzquiz->data->id,
            $session->data->id,
            $session->attempt ? $session->attempt->data->id : 0,
            sesskey()
        ]);
    }

    /**
     * @param \mod_jazzquiz\jazzquiz_session $session
     */
    public function require_quiz($session) {
        $this->require_core($session);
        $this->page->requires->js('/question/qengine.js');
        if ($session->jazzquiz->is_instructor()) {
            $count = count($session->jazzquiz->questions);
            $params =  [$count, false, []];
            $this->page->requires->js_call_amd('mod_jazzquiz/instructor', 'initialize', $params);
        } else {
            $this->page->requires->js_call_amd('mod_jazzquiz/student', 'initialize');
        }
    }

    /**
     * @param int $cmid
     */
    public function require_edit($cmid) {
        $this->page->requires->js('/mod/jazzquiz/js/sortable.min.js');
        $this->page->requires->js_call_amd('mod_jazzquiz/edit', 'initialize', [$cmid]);
    }

    /**
     * @param \mod_jazzquiz\jazzquiz_session $session
     * @param array $slots
     */
    public function require_review($session, $slots) {
        $this->require_core($session);
        $count = count($session->jazzquiz->questions);
        $params = [$count, true, $slots];
        $this->page->requires->js_call_amd('mod_jazzquiz/instructor', 'initialize', $params);
    }

}
