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
 * @author    John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright 2014 University of Wisconsin - Madison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

jazzquiz.change_quiz_state = function (state, data) {

    let $info_container = jQuery('#jazzquiz_info_container');

    this.is_new_state = (this.state !== state);
    this.state = state;

    switch (state) {

        case 'notrunning':
            $info_container.removeClass('hidden').html(this.text('instructions_for_student'));
            break;

        case 'preparing':
            $info_container.removeClass('hidden').html(this.text('wait_for_instructor'));
            break;

        case 'running':
            if (!this.quiz.question.is_running) {
                this.start_question_countdown(data.question_time, data.delay);
            }
            break;

        case 'reviewing':
            this.quiz.question.is_vote_running = false;
            this.quiz.question.is_running = false;
            this.hide_question_timer();
            $info_container.removeClass('hidden').html(this.text('wait_for_instructor'));
            break;

        case 'sessionclosed':
            $info_container.removeClass('hidden').html(this.text('session_closed'));
            break;

        case 'voting':
            if (this.quiz.question.is_vote_running === undefined || !this.quiz.question.is_vote_running) {
                const options = data.options;
                $info_container.removeClass('hidden').html(data.html);
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
    this.post('/mod/jazzquiz/quizdata.php', data, function(data) {
        let $info_container = jQuery('#jazzquiz_info_container');
        $info_container.removeClass('hidden');
        if (data.feedback.length > 0) {
            $info_container.html('<div>' + data.feedback + '</div>');
        } else {
            $info_container.html('<div>' + jazzquiz.text('wait_for_instructor') + '</div>');
        }
        jazzquiz.quiz.question.is_submitted = true;
        jazzquiz.quiz.question.is_saving = false;
        if (jazzquiz.quiz.question.is_ended) {
            return;
        }
        if (jazzquiz.quiz.question.is_vote_running !== undefined && jazzquiz.quiz.question.is_vote_running) {
            return;
        }
        jazzquiz.quiz.question.is_ended = true;
        jazzquiz.clear_question_box();
    }).fail(function() {
        jazzquiz.quiz.question.is_saving = false;
        jQuery('#jazzquiz_info_container').removeClass('hidden').html('There was an error with your request');
    });
};

jazzquiz.save_vote = function () {
    this.post('/mod/jazzquiz/quizdata.php', {
        action: 'save_vote',
        answer: this.vote_answer
    }, function (data) {
        const wait_for_instructor = jazzquiz.text('wait_for_instructor');
        let $info_container = jQuery('#jazzquiz_info_container');
        $info_container.removeClass('hidden');
        switch (data.status) {
            case 'success':
                $info_container.html(wait_for_instructor);
                break;
            case 'alreadyvoted':
                $info_container.html('Sorry, but you have already voted. ' + wait_for_instructor);
                break;
            default:
                $info_container.html('An error has occurred. ' + wait_for_instructor);
                break;
        }
    }).fail(function() {
        jQuery('#jazzquiz_info_container').removeClass('hidden').html('There was an error saving the vote.');
    });
};
