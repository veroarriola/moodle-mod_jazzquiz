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

jazzquiz.changeQuizState = function(state, data) {

    this.isNewState = (this.state !== state);
    this.state = state;

    switch (state) {

        case 'notrunning':
            this.showInfo(this.text('instructions_for_student'));
            break;

        case 'preparing':
            this.showInfo(this.text('wait_for_instructor'));
            break;

        case 'running':
            if (this.quiz.question.isRunning) {
                break;
            }
            const started = this.startQuestionCountdown(data.questiontime, data.delay);
            if (!started) {
                this.showInfo(this.text('wait_for_instructor'));
            }
            break;

        case 'reviewing':
            this.quiz.question.isVoteRunning = false;
            this.quiz.question.isRunning = false;
            this.hideQuestionTimer();
            this.clearQuestionBox();
            this.showInfo(this.text('wait_for_instructor'));
            break;

        case 'sessionclosed':
            this.showInfo(this.text('session_closed'));
            break;

        case 'voting':
            if (this.quiz.question.isVoteRunning) {
                break;
            }
            this.showInfo(data.html);
            const options = data.options;
            for (let i = 0; i < options.length; i++) {
                this.addMathjaxElement(options[i].content_id, options[i].text);
                if (options[i].question_type === 'stack') {
                    this.renderMaximaEquation(options[i].text, options[i].content_id);
                }
            }
            this.quiz.question.isVoteRunning = true;
            break;

        default:
            break;
    }
};

/**
 * Submit answer for the current question.
 */
jazzquiz.submitAnswer = function() {
    this.hideQuestionTimer();
    if (this.quiz.question.isSaving) {
        // Don't save twice.
        return;
    }
    this.quiz.question.isSaving = true;
    if (typeof tinyMCE !== 'undefined') {
        tinyMCE.triggerSave();
    }
    const serialized = jQuery('#jazzquiz_question_form').serializeArray();
    let data = {};
    for (let name in serialized) {
        if (serialized.hasOwnProperty(name)) {
            data[serialized[name].name] = serialized[name].value;
        }
    }
    this.post('save_question', data, function(data) {
        if (data.feedback.length > 0) {
            jazzquiz.showInfo(data.feedback);
        } else {
            jazzquiz.showInfo(jazzquiz.text('wait_for_instructor'));
        }
        jazzquiz.quiz.question.isSaving = false;
        if (!jazzquiz.quiz.question.isRunning) {
            return;
        }
        if (jazzquiz.quiz.question.isVoteRunning) {
            return;
        }
        jazzquiz.clearQuestionBox();
    }).fail(function() {
        jazzquiz.quiz.question.isSaving = false;
        jazzquiz.showInfo(jazzquiz.text('error_with_request'));
    });
};

jazzquiz.saveVote = function() {
    this.post('save_vote', {
        vote: this.voteAnswer
    }, function(data) {
        if (data.status === 'success') {
            jazzquiz.showInfo(jazzquiz.text('wait_for_instructor'));
        } else {
            jazzquiz.showInfo(jazzquiz.text('you_already_voted') + ' ' + jazzquiz.text('wait_for_instructor'));
        }
    }).fail(function() {
        jazzquiz.showInfo(jazzquiz.text('error_saving_vote'));
    });
};

/**
 * Add the event handlers.
 */
jazzquiz.addEventHandlers = function() {
    jQuery(document).on('submit', '#jazzquiz_question_form', function(event) {
        event.preventDefault();
        jazzquiz.submitAnswer();
    })
    .on('click', '#jazzquiz_save_vote', function() {
        jazzquiz.saveVote();
    })
    .on('click', '.jazzquiz-select-vote', function() {
        jazzquiz.voteAnswer = this.value;
    });
};
