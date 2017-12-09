<?php

namespace mod_jazzquiz\controllers;

defined('MOODLE_INTERNAL') || die();

/**
 * Base controller class
 *
 * @package     mod_jazzquiz
 * @author      John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright   2014 University of Wisconsin - Madison
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class base
{
    /** @var \mod_jazzquiz\jazzquiz Active quiz class */
    protected $jazzquiz;

    /** @var \moodle_url $pageurl The page url to base other calls on */
    protected $pageurl;

    /** @var array $this ->pagevars An array of page options for the page load */
    protected $pagevars;

    /** @var */
    protected $cm;

    /** @var */
    protected $course;

    /** @var */
    protected $quiz;

    /**
     * @param string $base_url the base url of the page
     * @param bool $update_improvised whether to update the improvised questions or not
     */
    protected function load($base_url, $update_improvised = false)
    {
        global $DB;

        $this->pagevars = [];
        $this->pageurl = new \moodle_url($base_url);
        $this->pageurl->remove_all_params();

        $id = optional_param('cmid', false, PARAM_INT);
        if (!$id) {
            $id = optional_param('id', false, PARAM_INT);
        }

        $quiz_id = optional_param('quizid', false, PARAM_INT);

        if ($id) {
            $this->cm = get_coursemodule_from_id('jazzquiz', $id, 0, false, MUST_EXIST);

            if ($update_improvised) {
                $this->update_improvised_questions_for_quiz($this->cm->instance, $this->cm->id);
            }

            $this->course = $DB->get_record('course', [
                'id' => $this->cm->course
            ], '*', MUST_EXIST);

            $this->quiz = $DB->get_record('jazzquiz', [
                'id' => $this->cm->instance
            ], '*', MUST_EXIST);

        } else if ($quiz_id) {

            $this->quiz = $DB->get_record('jazzquiz', [
                'id' => $quiz_id
            ], '*', MUST_EXIST);

            if ($update_improvised) {
                $this->update_improvised_questions_for_quiz($this->quiz->id, $this->cm->id);
                // TODO: Avoid fetching again?
                $this->quiz = $DB->get_record('jazzquiz', [
                    'id' => $this->quiz->id
                ], '*', MUST_EXIST);
            }

            $this->course = $DB->get_record('course', [
                'id' => $this->quiz->course
            ], '*', MUST_EXIST);

            $this->cm = get_coursemodule_from_instance('jazzquiz', $this->quiz->id, $this->course->id, false, MUST_EXIST);

        } else {
            // Probably a login redirect that doesn't include any ID.
            // Let's go back to the main Moodle page, because we have no info here.
            header('Location: /');
            exit;
        }
        $this->context = \context_module::instance($this->cm->id);
        require_login($this->course->id, false, $this->cm);
    }

    private function update_improvised_questions_for_quiz($jazzquiz_id, $course_module_id)
    {
        // Add improvised questions if client is an instructor
        if (!has_capability('mod/jazzquiz:control', \context_module::instance($course_module_id))) {
            return;
        }
        // Remove and re-add all the improvised questions to make sure they're all added and last.
        $improviser = new \mod_jazzquiz\improviser();
        $improviser->remove_improvised_questions_from_quiz($jazzquiz_id);
        $improviser->add_improvised_questions_to_quiz($jazzquiz_id);
    }

}