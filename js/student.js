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

    this.is_new_state = (this.state !== state);
    this.state = state;

    switch (state) {

        case 'notrunning':
            break;

        case 'preparing':
            this.hide_instructions();
            jQuery('#jazzquiz_info_container').removeClass('hidden').html(this.text('wait_for_instructor'));
            break;

        case 'running':
            if (this.quiz.question.is_running) {
                break;
            }
            // Make sure the loading box hides (this is a catch for when the quiz is resuming)
            this.hide_loading();
            // Set this to true so that we don't keep calling this over and over
            this.quiz.question.is_running = true;
            // Set this to false if we're going to a new question
            this.quiz.question.is_ended = false;
            this.waitfor_question(data.slot, data.questiontime, data.delay);
            break;

        case 'reviewing':
            this.quiz.question.is_vote_running = false;
            this.quiz.question.is_running = false;
            this.hide_instructions();
            this.hide_all_questions();
            this.handle_question_ending();
            jQuery('#jazzquiz_info_container').removeClass('hidden').html(this.text('wait_for_instructor'));
            break;

        case 'sessionclosed':
            this.hide_instructions();
            this.hide_all_questions();
            jQuery('#jazzquiz_info_container').removeClass('hidden').html(this.text('session_closed'));
            break;

        case 'voting':
            if (this.quiz.question.is_vote_running === undefined || !this.quiz.question.is_vote_running) {
                var options = data.options;
                var html = '<div class="jazzquiz-vote">';
                for (var i = 0; i < options.length; i++) {
                    html += '<label>';
                    html += '<input type="radio" name="vote" value="' + options[i].id + '" onclick="jazzquiz.vote_answer = this.value;">';
                    html += '<span id="vote_answer_label' + i + '">' + options[i].text + '</span>';
                    html += '</label><br>';
                }
                html += '</div>';
                html += '<button class="btn" onclick="jazzquiz.save_vote(); return false;">Save</button>';
                jQuery('#jazzquiz_info_container').removeClass('hidden').html(html);
                for (var i = 0; i < options.length; i++) {
                    this.add_mathjax_element('vote_answer_label' + i, options[i].text);
                    if (options[i].qtype === 'stack') {
                        this.render_maxima_equation(options[i].text, 'vote_answer_label' + i, options[i].slot);
                    }
                }
                this.quiz.question.is_vote_running = true;
            }
            break;

        default:
            break;
    }
};

jazzquiz.handle_question_ending = function() {
    if (this.quiz.question.is_ended) {
        return;
    }
    if (this.quiz.question.is_vote_running !== undefined && this.quiz.question.is_vote_running) {
        return;
    }
    this.quiz.question.is_ended = true;

    // This line will autosubmit answers if timer runs out.
    // Should that happen or not? It will potentially add a lot of blank answers.
    // this.handle_question(this.quiz.current_question_slot, true);

    // Clear counter
    if (this.qcounter) {
        clearInterval(this.qcounter);
        this.qcounter = false;
        this.counter = false;
        jQuery('#q' + this.quiz.current_question_slot + '_questiontimetext').html('');
        jQuery('#q' + this.quiz.current_question_slot + '_questiontime').html('');
    }
};

/**
 * handles the question for the student
 *
 * @param question_slot the question slot to handle
 */
jazzquiz.handle_question = function (question_slot) {
    if (this.quiz.question.is_saving) {
        // Don't save twice
        return;
    }
    this.quiz.question.is_saving = true;
    jQuery('#loadingtext').html(this.text('gathering_results'));
    if (typeof tinyMCE !== 'undefined') {
        tinyMCE.triggerSave();
    }
    var params = {
        action: 'save_question',
        questionid: question_slot
    };
    this.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {
        jQuery('#loadingbox').addClass('hidden');
        if (status !== HTTP_STATUS.OK) {
            jazzquiz.quiz.question.is_saving = false;
            jQuery('#jazzquiz_info_container').removeClass('hidden').html('There was an error with your request');
            return;
        }

        // Update the sequence check for the question
        var sequence_check = document.getElementsByName(response.seqcheckname);
        var field = sequence_check[0];
        field.value = response.seqcheckval;

        // Show feedback to the students
        var feedback = response.feedback;
        var feedback_intro = document.createElement('div');
        feedback_intro.innerHTML = jazzquiz.text('wait_for_instructor');
        jQuery('#jazzquiz_info_container').removeClass('hidden').html(feedback_intro);

        if (feedback.length > 0) {
            var feedback_box = document.createElement('div');
            feedback_box.innerHTML = feedback;
            jQuery('#jazzquiz_info_container').removeClass('hidden').html(feedback_box);
        }

        jazzquiz.quiz.question.is_submitted = true;
        jazzquiz.quiz.question.is_saving = false;
        jazzquiz.handle_question_ending();
    });
};

jazzquiz.save_vote = function () {
    var params = {
        action: 'save_vote',
        answer: this.vote_answer
    };
    this.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {
        var $info_container = jQuery('#jazzquiz_info_container');
        if (status !== HTTP_STATUS.OK) {
            $info_container.removeClass('hidden').html('There was an error saving the vote.');
            return;
        }

        jazzquiz.hide_all_questions();
        var wait_for_instructor = jazzquiz.text('wait_for_instructor');

        // Output info depending on the status
        $info_container.removeClass('hidden');
        switch (response.status) {
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
    });
};
