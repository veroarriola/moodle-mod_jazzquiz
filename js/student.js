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
 * @package   mod_jazzquiz
 * @author    Sebastian S. Gundersen <sebastsg@stud.ntnu.no>
 * @copyright 2014 University of Wisconsin - Madison
 * @copyright 2018 NTNU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

jazzquiz.change_quiz_state = function(state, data) {

    this.is_new_state = (this.state !== state);
    this.state = state;

    switch (state) {

        case 'notrunning':
            this.show_info(this.text('instructions_for_student'));
            break;

        case 'preparing':
            this.show_info(this.text('wait_for_instructor'));
            break;

        case 'running':
            if (!this.quiz.question.is_running) {
                const started = this.start_question_countdown(data.question_time, data.delay);
                if (!started) {
                    this.show_info(this.text('wait_for_instructor'));
                }
            }
            break;

        case 'reviewing':
            this.quiz.question.is_vote_running = false;
            this.quiz.question.is_running = false;
            this.hide_question_timer();
            this.show_info(this.text('wait_for_instructor'));
            break;

        case 'sessionclosed':
            this.show_info(this.text('session_closed'));
            break;

        case 'voting':
            if (this.quiz.question.is_vote_running === undefined || !this.quiz.question.is_vote_running) {
                this.show_info(data.html);
                const options = data.options;
                for (let i = 0; i < options.length; i++) {
                    this.add_mathjax_element(options[i].content_id, options[i].text);
                    if (options[i].question_type === 'stack') {
                        this.render_maxima_equation(options[i].text, options[i].content_id);
                    }
                }
                this.quiz.question.is_vote_running = true;
            }
            break;

        default:
            break;
    }
};

/**
 * Submit answer for the current question.
 */
jazzquiz.submit_answer = function() {
    this.hide_question_timer();
    if (this.quiz.question.is_saving) {
        // Don't save twice
        return;
    }
    this.quiz.question.is_saving = true;
    if (typeof tinyMCE !== 'undefined') {
        tinyMCE.triggerSave();
    }
    const serialized = jQuery('#jazzquiz_question_form').serializeArray();
    let data = {
        action: 'save_question'
    };
    for (let name in serialized) {
        if (serialized.hasOwnProperty(name)) {
            data[serialized[name].name] = serialized[name].value;
        }
    }
    this.post('quizdata.php', data, function(data) {
        if (data.feedback.length > 0) {
            jazzquiz.show_info(data.feedback);
        } else {
            jazzquiz.show_info(jazzquiz.text('wait_for_instructor'));
        }
        jazzquiz.quiz.question.is_saving = false;
        if (!jazzquiz.quiz.question.is_running) {
            return;
        }
        if (jazzquiz.quiz.question.is_vote_running !== undefined && jazzquiz.quiz.question.is_vote_running) {
            return;
        }
        jazzquiz.clear_question_box();
    }).fail(function() {
        jazzquiz.quiz.question.is_saving = false;
        jazzquiz.show_info('There was an error with your request');
    });
};

jazzquiz.save_vote = function() {
    this.post('quizdata.php', {
        action: 'save_vote',
        answer: this.vote_answer
    }, function(data) {
        const wait_for_instructor = jazzquiz.text('wait_for_instructor');
        switch (data.status) {
            case 'success':
                jazzquiz.show_info(wait_for_instructor);
                break;
            case 'alreadyvoted':
                jazzquiz.show_info('Sorry, but you have already voted. ' + wait_for_instructor);
                break;
            default:
                jazzquiz.show_info('An error has occurred. ' + wait_for_instructor);
                break;
        }
    }).fail(function() {
        jazzquiz.show_info('There was an error saving the vote.');
    });
};
