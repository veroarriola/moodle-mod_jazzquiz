<?php

namespace mod_jazzquiz\reports\overview;

use mod_jazzquiz\reports\ireport;
use mod_jazzquiz\tableviews\overallgradesview;

class report_overview extends \mod_jazzquiz\reports\jazzquiz_report_base implements ireport {

    /**
     * The tableview for the current request.  is added by the handle request function.
     *
     * @var
     */
    protected $tableview;

    /**
     * @var \mod_jazzquiz\output\report_overview_renderer $renderer
     */
    protected $renderer;

    /**
     * report_overview constructor.
     * @param \mod_jazzquiz\jazzquiz $jazzquiz
     */
    public function __construct(\mod_jazzquiz\jazzquiz $jazzquiz) {
        global $PAGE;

        $this->renderer = $PAGE->get_renderer('mod_jazzquiz', 'report_overview');
        parent::__construct($jazzquiz);
    }

    /**
     * Handle the request for this specific report
     *
     * @param \moodle_url $pageurl
     * @param array $pagevars
     * @return void
     */
    public function handle_request($pageurl, $pagevars) {

        $this->renderer->init($this->jazzquiz, $pageurl, $pagevars);

        // switch the action
        switch($pagevars['action']) {
            case 'regradeall':

                if($this->jazzquiz->get_grader()->save_all_grades(true)) {
                    $this->renderer->setMessage('success',  get_string('successregrade', 'jazzquiz'));
                }else {
                    $this->renderer->setMessage('error',  get_string('errorregrade', 'jazzquiz'));
                }

                $sessions = $this->jazzquiz->get_sessions();
                $this->renderer->showMessage();
                $this->renderer->select_session($sessions);
                $this->renderer->home();

                break;
            case 'viewsession':

                $session_id = required_param('sessionid', PARAM_INT);

                if (empty($session_id)) { // if no session id just go to the home page

                    $redirecturl = new \moodle_url('/mod/jazzquiz/reports.php', [
                        'id' => $this->jazzquiz->getCM()->id,
                        'quizid' => $this->jazzquiz->getRTQ()->id
                    ]);
                    redirect($redirecturl, null, 3);
                }

                $session = $this->jazzquiz->get_session($session_id);
                $pageurl->param('sessionid', $session_id);
                $sessionattempts = new \mod_jazzquiz\tableviews\sessionattempts('sessionattempts', $this->jazzquiz,
                    $session, $pageurl);

                $sessions = $this->jazzquiz->get_sessions();
                $this->renderer->select_session($sessions, $session_id);
                $this->renderer->view_session_attempts($sessionattempts);


                break;
            default:

                $sessions = $this->jazzquiz->get_sessions();
                $this->renderer->select_session($sessions);
                $this->renderer->home();

                break;
        }

    }



}