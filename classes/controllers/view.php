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

namespace mod_jazzquiz\controllers;

defined('MOODLE_INTERNAL') || die();

/**
 * view controller class for the view page
 *
 * @package     mod_jazzquiz
 * @author      John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright   2014 University of Wisconsin - Madison
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view extends base
{
    /** @var \mod_jazzquiz\jazzquiz_session $session The session class for the jazzquiz view */
    protected $session;

    /** @var string $action The specified action to take */
    protected $action;

    /** @var object $context The specific context for this activity */
    protected $context;

    /** @var \question_edit_contexts $contexts and array of contexts that has all parent contexts from the RTQ context */
    protected $contexts;

    /**
     * set up the class for the view page
     *
     * @param string $base_url the base url of the page
     */
    public function setup_page($base_url)
    {
        global $PAGE;

        $this->load($base_url, true);

        $this->pageurl->param('id', $this->cm->id);
        $this->pageurl->param('quizid', $this->quiz->id);

        $this->pagevars['action'] = optional_param('action', '', PARAM_ALPHANUM);
        $this->pagevars['group'] = optional_param('group', '0', PARAM_INT);
        $this->pagevars['groupmembers'] = optional_param('groupmembers', '', PARAM_RAW);

        $this->pageurl->param('action', $this->pagevars['action']);

        $this->pagevars['pageurl'] = $this->pageurl;

        $this->jazzquiz = new \mod_jazzquiz\jazzquiz($this->cm, $this->course, $this->quiz, $this->pageurl, $this->pagevars, null);
        $this->jazzquiz->require_capability('mod/jazzquiz:attempt');

        // Set this up in the page vars so it can be passed to things like the renderer
        $this->pagevars['isinstructor'] = $this->jazzquiz->is_instructor();

        // Set up the question manager and the possible JazzQuiz session
        $this->session = new \mod_jazzquiz\jazzquiz_session($this->jazzquiz, $this->pageurl, $this->pagevars);

        $module_name = get_string('modulename', 'jazzquiz');
        $quiz_name = format_string($this->quiz->name, true);

        $PAGE->set_pagelayout('incourse');
        $PAGE->set_context($this->jazzquiz->getContext());
        $PAGE->set_cm($this->jazzquiz->getCM());
        $PAGE->set_title(strip_tags($this->course->shortname . ': ' . $module_name . ': ' . $quiz_name));
        $PAGE->set_heading($this->course->fullname);
        $PAGE->set_url($this->pageurl);
    }

    private function view_no_questions()
    {
        $renderer = $this->jazzquiz->get_renderer();
        $renderer->view_header();
        $renderer->no_questions($this->jazzquiz->is_instructor());
        $renderer->view_footer();
    }

    private function view_quiz_start()
    {
        global $PAGE;
        $renderer = $this->jazzquiz->get_renderer();

        // Set the quiz view page to the base layout for 1 column layout
        $PAGE->set_pagelayout('base');

        if ($this->session->get_session() === false) {

            // Redirect them to the default page with a quick message first
            $redirurl = clone($this->pageurl);
            $redirurl->remove_params('action');
            redirect($redirurl, get_string('no_session', 'jazzquiz'), 5);

        } else {

            // This is here to help prevent race conditions for multiple
            // group members trying to take the quiz at the same time.
            $can_take_quiz = false;

            if ($this->jazzquiz->group_mode()) {

                if (!$this->jazzquiz->is_instructor() && $this->pagevars['group'] == 0) {
                    print_error('invalidgroupid', 'mod_jazzquiz');
                }
                // Check if the user can take the quiz for the group
                if ($this->session->can_take_quiz_for_group($this->pagevars['group'])) {
                    $can_take_quiz = true;
                }

            } else {
                // If no group mode, user will always be able to take quiz
                $can_take_quiz = true;
            }

            if ($can_take_quiz) {

                // Initialize the question attempts
                if (!$this->session->init_attempts($this->jazzquiz->is_instructor(), $this->pagevars['group'], $this->pagevars['groupmembers'])) {
                    print_error('cantinitattempts', 'jazzquiz');
                }

                // Get the current attempt and initialize the head contributions
                $attempt = $this->session->get_open_attempt();
                $attempt->get_html_head_contributions();

                $attempt->setStatus('inprogress');

                // Show the quiz start landing page
                $renderer->view_header(true);
                $renderer->render_quiz($attempt, $this->session);
                $renderer->view_footer();

            } else {
                $renderer->view_header();
                $renderer->group_session_started();
                $renderer->view_footer();
            }
        }
    }

    private function view_select_group_members()
    {
        $renderer = $this->jazzquiz->get_renderer();
        if (empty($this->pagevars['group'])) {
            $view_home = clone($this->pageurl);
            $view_home->remove_params('action');
            redirect($view_home, get_string('invalid_group_selected', 'jazzquiz'), 5);
        } else {
            $this->pageurl->param('group', $this->pagevars['group']);
            $group_select_form = new \mod_jazzquiz\forms\view\groupselectmembers($this->pageurl, [
                'rtq' => $this->jazzquiz,
                'selectedgroup' => $this->pagevars['group']
            ]);
            if ($data = $group_select_form->get_data()) {
                // Basically we want to get all gm* fields
                $gmemnum = 1;
                $group_members = [];
                $data = get_object_vars($data);
                while (isset($data['gm' . $gmemnum])) {
                    if ($data['gm' . $gmemnum] != 0) {
                        $group_members[] = $data['gm' . $gmemnum];
                    }
                    $gmemnum++;
                }
                $this->pageurl->param('groupmembers', implode(',', $group_members));
                $this->pageurl->param('action', 'quizstart');
                // Redirect to the quiz start page
                redirect($this->pageurl, null, 0);
            } else {
                $renderer->view_header();
                $renderer->group_member_select($group_select_form);
                $renderer->view_footer();
            }
        }
    }

    private function view_default()
    {
        global $PAGE, $DB;
        $renderer = $this->jazzquiz->get_renderer();

        // Default is to show view to start quiz (for instructors/quiz controllers)
        // or join quiz (for everyone else)

        // Trigger event for course module viewed
        $event = \mod_jazzquiz\event\course_module_viewed::create([
            'objectid' => $PAGE->cm->instance,
            'context' => $PAGE->context,
        ]);

        $event->add_record_snapshot('course', $this->jazzquiz->getCourse());
        $event->add_record_snapshot($PAGE->cm->modname, $this->jazzquiz->getRTQ());
        $event->trigger();

        // Determine home display based on role
        if ($this->jazzquiz->is_instructor()) {
            $start_session_form = new \mod_jazzquiz\forms\view\start_session($this->pageurl);
            if ($data = $start_session_form->get_data()) {
                // Create a new quiz session
                // First check to see if there are any open sessions
                // This shouldn't occur, but never hurts to check
                $sessions = $DB->get_records('jazzquiz_sessions', [
                    'jazzquizid' => $this->jazzquiz->getRTQ()->id,
                    'sessionopen' => 1
                ]);

                if (!empty($sessions)) {
                    // Error out with that there are existing sessions
                    $renderer->setMessage(get_string('already_existing_sessions', 'jazzquiz'), 'error');
                    $renderer->view_header();
                    $renderer->view_inst_home($start_session_form, $this->session->get_session());
                    $renderer->view_footer();
                    return;
                }

                // Create the session
                $session_created_successfully = $this->session->create_session($data);
                if (!$session_created_successfully) {
                    // Error handling
                    $renderer->setMessage(get_string('unable_to_create_session', 'jazzquiz'), 'error');
                    $renderer->view_header();
                    $renderer->view_inst_home($start_session_form, $this->session->get_session());
                    $renderer->view_footer();
                    return;
                }

                // Redirect to the quiz start
                $quizstarturl = clone($this->pageurl);
                $quizstarturl->param('action', 'quizstart');
                redirect($quizstarturl, null, 0);

            } else {
                $renderer->view_header();
                $renderer->view_inst_home($start_session_form, $this->session->get_session());
                $renderer->view_footer();
            }
        } else {
            // Check to see if the group already started a quiz
            $valid_groups = [];
            if ($this->jazzquiz->group_mode()) {
                // If there is already an attempt for this session for this group for this user:
                //  Don't allow them to start another
                $valid_groups = $this->session->check_attempt_for_group();
                if (empty($valid_groups) && $valid_groups !== false) {
                    $renderer->view_header();
                    $renderer->group_session_started();
                    $renderer->view_footer();
                    return;
                }
                if ($valid_groups === false) {
                    $valid_groups = [];
                }
            }

            $student_start_form_params = [
                'rtq' => $this->jazzquiz,
                'validgroups' => $valid_groups
            ];

            $student_start_form = new \mod_jazzquiz\forms\view\student_start_form($this->pageurl, $student_start_form_params);

            if ($data = $student_start_form->get_data()) {

                $quiz_start_url = clone($this->pageurl);
                $quiz_start_url->param('action', 'quizstart');

                // If data redirect to the quiz start url with the group selected if we're in group mode
                if ($this->jazzquiz->group_mode()) {
                    $groupid = $data->group;
                    $quiz_start_url->param('group', $groupid);
                    // Check if the group attendance feature is enabled.
                    // If so, redirect to the group member select form.
                    // Don't send to group attendance form if an attempt is already started.
                    if ($this->jazzquiz->getRTQ()->groupattendance == 1 && !$this->session->get_open_attempt_for_current_user()) {
                        $quiz_start_url->param('action', 'selectgroupmembers');
                    }
                }

                redirect($quiz_start_url, null, 0);

            } else {
                // Display student home
                // Form will display only if there is an active session.
                $renderer->view_header();
                $renderer->view_student_home($student_start_form, $this->session);
                $renderer->view_footer();
            }
        }
    }

    /**
     * Handle's the page request
     *
     */
    public function handle_request()
    {
        // Check if there are questions or not.
        // If there are no questions, display that message instead, regardless of action.
        if (count($this->jazzquiz->get_questionmanager()->get_questions()) === 0) {
            $this->pagevars['action'] = 'noquestions';
            $this->pageurl->param('action', ''); // Remove the action
        }
        switch ($this->pagevars['action']) {
            case 'noquestions':
                $this->view_no_questions();
                break;
            case 'quizstart':
                $this->view_quiz_start();
                break;
            case 'selectgroupmembers':
                $this->view_select_group_members();
                break;
            default:
                $this->view_default();
                break;
        }
    }

}

