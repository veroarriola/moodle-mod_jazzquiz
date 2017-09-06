<?php

namespace mod_jazzquiz\reports;

class report_overview
{
    /**
     * @var \mod_jazzquiz\jazzquiz $jazzquiz
     */
    protected $jazzquiz;

    /**
     * @var \mod_jazzquiz\output\report_overview_renderer $renderer
     */
    protected $renderer;

    /**
     * report_overview constructor.
     * @param \mod_jazzquiz\jazzquiz $jazzquiz
     */
    public function __construct(\mod_jazzquiz\jazzquiz $jazzquiz)
    {
        global $PAGE;
        $this->renderer = $PAGE->get_renderer('mod_jazzquiz', 'report_overview');
        $this->jazzquiz = $jazzquiz;
    }

    /**
     * Handle the request for this specific report
     *
     * @param \moodle_url $page_url
     * @param array $page_vars
     * @return void
     */
    public function handle_request($page_url, $page_vars)
    {
        global $PAGE;

        $this->renderer->init($this->jazzquiz, $page_url, $page_vars);

        switch ($page_vars['action']) {

            case 'viewsession':
                $session_id = required_param('sessionid', PARAM_INT);
                if (empty($session_id)) {

                    // If no session id just go to the home page
                    $redirect_url = new \moodle_url('/mod/jazzquiz/reports.php', [
                        'id' => $this->jazzquiz->getCM()->id,
                        'quizid' => $this->jazzquiz->getRTQ()->id
                    ]);
                    redirect($redirect_url, null, 3);
                }

                $session = $this->jazzquiz->get_session($session_id);
                $page_url->param('sessionid', $session_id);

                $sessions = $this->jazzquiz->get_sessions();
                $this->renderer->select_session($sessions, $session_id);

                $quiz_attempts = $session->getall_attempts(false);
                $quiz_attempt = reset($quiz_attempts);

                if (!$quiz_attempt) {
                    echo '<div class="jazzquizbox"><p>No attempt found.</p></div>';
                    break;
                }

                $row = $quiz_attempt->get_attempt();
                $slots = explode(',', $row->qubalayout);
                $quba = $quiz_attempt->get_quba();

                $PAGE->requires->js('/mod/jazzquiz/js/core.js');
                $PAGE->requires->js('/mod/jazzquiz/js/instructor.js');

                echo '<script>';
                echo 'var jazzquiz_responses = [];';
                echo '</script>';

                foreach ($slots as $slot) {

                    $question_attempt = $quba->get_question_attempt($slot);
                    $question = $question_attempt->get_question();
                    $responses = $session->get_question_results_list($slot, 'all');

                    $wrapper_id = 'jazzquiz_wrapper_responses_' . intval($slot);
                    $table_id = 'responses_wrapper_table_' . intval($slot);

                    $question_name = str_replace('{IMPROV}', '', $question->name);
                    $question_type = $quba->get_question_attempt($slot)->get_question()->get_type_name();

                    echo '<div class="jazzquizbox" id="' . $wrapper_id . '">'
                        . "<h2>$question_name</h2>"
                        . '<span class="jazzquiz-latex-wrapper">'
                        . '<span class="filter_mathjaxloader_equation">' . $question->questiontext . '</span>'
                        . '</span>'
                        . '<table id="' . $table_id . '" class="jazzquiz-responses-overview"></table>'
                        . '</div>';

                    echo '<script>'
                        . "jazzquiz_responses[$slot] = " . json_encode($responses) . ';'
                        . 'setTimeout(function() {'
                        . 'jazzquiz.quiz.attempt_id = ' . $row->id . ';'
                        . 'jazzquiz.quiz_info_responses("'
                        . $wrapper_id . '", "' . $table_id . '", jazzquiz_responses[' . $slot . '], "' . $question_type . '", ' . $slot
                        . ');'
                        . '}, 1000);'
                        . '</script>';
                }

                break;

            default:
                $sessions = $this->jazzquiz->get_sessions();
                $this->renderer->select_session($sessions);
                break;
        }

    }

}