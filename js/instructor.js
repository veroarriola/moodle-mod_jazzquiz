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

/**
 * The instructor's quiz state change handler
 *
 * This function works to maintain instructor state as well as to assist in getting student responses
 * while the question is still running. There are 2 variables that are set/get which are important
 *
 * "question.is_running" signifies that we are in a question, and is updated in other functions to signify the end of a question
 * "question.is_ended" This variable is needed to help to keep the "question.is_running" variable from being overwritten on the
 *               interval this function defines.  It is also updated by other functions in conjunction with "question.is_running"
 *
 */
jazzquiz.change_quiz_state = function (state, data) {

    this.is_new_state = (this.current_quiz_state !== state);
    this.current_quiz_state = state;

    jQuery('#inquizcontrols_state').html(state);
    jQuery('#region-main').find('ul.nav.nav-tabs').css('display', 'none');
    jQuery('#region-main-settings-menu').css('display', 'none');
    jQuery('.region_main_settings_menu_proxy').css('display', 'none');

    this.show_controls();

    switch (state) {

        case 'notrunning':
            this.control_buttons([]);
            this.hide_controls();
            this.quiz.question.is_ended = false;
            var num_students = 'No students';
            if (data.students === 1) {
                num_students = '1 student';
            } else if (data.students > 1) {
                num_students = data.students + ' students';
            }
            jQuery('#startquiz').next().html(num_students + ' have joined.');
            break;

        case 'preparing':
            this.control_buttons([
                'startimprovisedquestion',
                'jumptoquestion',
                'nextquestion',
                'showfullscreenresults',
                'closesession'
            ]);
            jQuery('#startquiz').addClass('hidden');
            break;

        case 'running':

            this.control_buttons([
                'endquestion',
                'toggleresponses',
                'togglenotresponded',
                'showfullscreenresults'
            ]);

            if (this.quiz.question.is_running) {

                // Gather the current results
                if (this.options.show_responses) {
                    this.gather_current_results();
                }

                // Also get the students/groups not responded
                if (this.options.show_not_responded) {
                    this.getnotresponded();
                }

            } else {

                if (this.quiz.question.is_ended) {

                    // Set to false since we're waiting for a new question
                    this.quiz.question.is_ended = false;

                } else {

                    if (data.delay <= 0) {
                        // Only set is_running if we're in it, not waiting for it to start
                        this.quiz.question.is_running = true;
                    }

                }
            }

            break;

        case 'endquestion':
            this.control_buttons([]);
            this.quiz.question.is_running = false;
            break;

        case 'reviewing':
            var enabled_buttons = [
                'showcorrectanswer',
                'runvoting',
                'reloadresults',
                'repollquestion',
                'jumptoquestion',
                'closesession',
                'showfullscreenresults',
                'startimprovisedquestion',
                // Temporarily disable this while in review mode. See below before the break.
                //'toggleresponses',
                'togglenotresponded'
            ];

            if (!this.quiz.question.is_last) {
                enabled_buttons.push('nextquestion');
            }

            this.control_buttons(enabled_buttons);

            // For now, just always show responses while reviewing
            // In the future, there should be an additional toggle.
            if (this.is_new_state) {
                this.options.show_responses = true;
                if (this.quiz.show_votes_upon_review) {
                    this.get_and_show_vote_results();
                    this.quiz.show_votes_upon_review = false;
                } else {
                    this.gather_current_results();
                }
                this.options.show_responses = false;
            }

            // No longer in question
            this.quiz.question.is_running = false;

            break;

        case 'voting':
            this.control_buttons([
                'closesession',
                'showfullscreenresults',
                'showcorrectanswer',
                'toggleresponses',
                'endquestion'
            ]);
            this.get_and_show_vote_results();
            jQuery('#startquiz').addClass('hidden');
            break;

        case 'sessionclosed':
            this.control_buttons([]);
            this.quiz.question.is_running = false;
            break;

        default:
            this.control_buttons([]);
            break;
    }

};

jazzquiz.end_response_merge = function() {
    jQuery('.merge-into').removeClass('merge-into');
    jQuery('.merge-from').removeClass('merge-from');
};

jazzquiz.start_response_merge = function(from_row_bar_id) {

    var bar_cell = document.getElementById(from_row_bar_id);
    var row = bar_cell.parentNode;

    if (row.classList.contains('merge-into')) {
        this.end_response_merge();
        return;
    }

    if (row.classList.contains('merge-from')) {

        var into_row = jQuery('.merge-into')[0];

        this.current_responses[into_row.dataset.response_i].count += parseInt(row.dataset.count);
        this.current_responses.splice(row.dataset.response_i, 1);

        this.quiz_info_responses(this.current_responses, this.qtype);
        this.end_response_merge();

        return;
    }

    row.classList.add('merge-into');

    var table = row.parentNode.parentNode;

    for (var i = 0; i < table.rows.length; i++) {
        if (table.rows[i].cells[1].id !== bar_cell.id) {
            table.rows[i].classList.add('merge-from');
        }
    }

};

jazzquiz.create_response_controls = function(name) {

    if (!this.quiz.question.has_votes) {
        return;
    }

    // Add button for instructor to change what to review
    if (jazzquiz.current_quiz_state === 'reviewing') {

        var $show_normal_result  = jQuery('#review_show_normal_results');
        var $show_vote_result  = jQuery('#review_show_vote_results');
        var $response_info_container = jQuery('#jazzquiz_response_info_container');

        if (name === 'vote_response') {

            if ($show_normal_result.length === 0) {

                $response_info_container.html('<h4 class="inline">Showing vote results</h4>');
                $response_info_container.append('<button id="review_show_normal_results" onclick="jazzquiz.gather_current_results();" class="btn btn-primary">Click to show original results</button><br>');
                $show_vote_result.remove();

            }

        } else if (name === 'current_response') {

            if ($show_vote_result.length === 0) {

                $response_info_container.html('<h4 class="inline">Showing original results</h4>');
                $response_info_container.append('<button id="review_show_vote_results" onclick="jazzquiz.get_and_show_vote_results();" class="btn btn-primary">Click to show vote results</button><br>');
                $show_normal_result.remove();

            }

        }
    }

}

jazzquiz.create_response_bar_graph = function (responses, name, target_id, slot) {

    var target = document.getElementById(target_id);
    if (target === null) {
        return;
    }
    var total = 0;
    for (var i = 0; i < responses.length; i++) {
        total += parseInt(responses[i].count); // in case count is a string
    }
    if (total === 0) {
        total = 1;
    }

    // Prune rows
    for (var i = 0; i < target.rows.length; i++) {
        var prune = true;
        for (var j = 0; j < responses.length; j++) {
            if (target.rows[i].dataset.response === responses[j].response) {
                prune = false;
                break;
            }
        }
        if (prune) {
            target.deleteRow(i);
            i--;
        }
    }

    this.create_response_controls(name);

    // Add rows
    for (var i = 0; i < responses.length; i++) {

        var percent = (parseInt(responses[i].count) / total) * 100;

        // Check if row with same response already exists
        var row_i = -1;
        var current_row_index = -1;
        for (var j = 0; j < target.rows.length; j++) {
            if (target.rows[j].dataset.response === responses[i].response) {
                row_i = target.rows[j].dataset.row_i;
                current_row_index = j;
                break;
            }
        }

        if (row_i === -1) {

            row_i = target.rows.length;

            var row = target.insertRow();
            row.dataset.response_i = i;
            row.dataset.response = responses[i].response;
            row.dataset.percent = percent;
            row.dataset.row_i = row_i;
            row.dataset.count = responses[i].count;
            row.classList.add('selected-vote-option');

            // TODO: Use classes instead of IDs for these elements. At the moment it's just easier to use an ID.

            var count_html = '<span id="' + name + '_count_' + row_i + '">' + responses[i].count + '</span>';

            var response_cell = row.insertCell(0);
            response_cell.onclick = function () {
                jQuery(this).parent().toggleClass('selected-vote-option');
            };

            var bar_cell = row.insertCell(1);
            bar_cell.classList.add('bar');
            bar_cell.id = name + '_bar_' + row_i;
            bar_cell.innerHTML = '<div style="width:' + percent + '%;">' + count_html + '</div>';

            var latex_id = name + '_latex_' + row_i;

            response_cell.innerHTML = '<span id="' + latex_id + '"></span>';
            this.add_mathjax_element(latex_id, responses[i].response);

            if (responses[i].qtype === 'stack') {
                this.render_maxima_equation(responses[i].response, latex_id, slot);
            }

            // Hide this for now. It might be redundant.
            //var opt_cell = row.insertCell(2);
            //opt_cell.classList.add('options');
            //opt_cell.innerHTML = '<button class="btn btn-primary" onclick="jazzquiz.start_response_merge(\'' + bar_cell.id + '\')"><i class="fa fa-compress"></i></button>';

        } else {

            target.rows[current_row_index].dataset.row_i = row_i;
            target.rows[current_row_index].dataset.response_i = i;
            target.rows[current_row_index].dataset.percent = percent;
            target.rows[current_row_index].dataset.count = responses[i].count;

            var count_element = document.getElementById(name + '_count_' + row_i);
            if (count_element !== null) {
                count_element.innerHTML = responses[i].count;
            }

            var bar_element = document.getElementById(name + '_bar_' + row_i);
            if (bar_element !== null) {
                bar_element.firstElementChild.style.width = percent + '%';
            }

        }
    }
};

jazzquiz.sort_response_bar_graph = function (target_id) {
    var target = document.getElementById(target_id);
    if (target === null) {
        return;
    }
    var is_sorting = true;
    while (is_sorting) {
        is_sorting = false;
        for (var i = 0; i < (target.rows.length - 1); i++) {
            var current = parseInt(target.rows[i].dataset.percent);
            var next = parseInt(target.rows[i + 1].dataset.percent);
            if (current < next) {
                target.rows[i].parentNode.insertBefore(target.rows[i + 1], target.rows[i]);
                is_sorting = true;
                break;
            }
        }
    }
};

jazzquiz.quiz_info_responses = function (responses, qtype, slot) {

    if (responses === undefined) {
        console.log('Responses is undefined.');
        return;
    }

    // Check if any responses to show
    if (responses.length === 0) {
        return;
    }

    // Question type specific
    switch (qtype) {
        case 'shortanswer':
            for (var i = 0; i < responses.length; i++) {
                responses[i].response = responses[i].response.trim();
            }
            break;
        case 'stack':
            // Remove all spaces from responses
            for (var i = 0; i < responses.length; i++) {
                responses[i].response = responses[i].response.replace(/\s/g, '');
            }
            break;
        default:
            break;
    }

    // Update data
    this.current_responses = [];
    this.total_responses = responses.length;
    this.qtype = qtype;
    for (var i = 0; i < responses.length; i++) {

        var exists = false;

        var count = 1;
        if (responses[i].count !== undefined) {
            count = parseInt(responses[i].count);
        }

        // Check if response is a duplicate
        for (var j = 0; j < this.current_responses.length; j++) {
            if (this.current_responses[j].response === responses[i].response) {
                this.current_responses[j].count += count;
                exists = true;
                break;
            }
        }

        // Add element if not a duplicate
        if (!exists) {
            this.current_responses.push({
                response: responses[i].response,
                count: count,
                qtype: qtype
            });
        }
    }

    // Make sure quiz info has the wrapper for the responses
    var wrapper_current_responses = document.getElementById('wrapper_current_responses');
    if (wrapper_current_responses === null) {
        jQuery('#jazzquiz_responses_container').removeClass('hidden').html('<table id="wrapper_current_responses" class="jazzquiz-responses-overview"></table>', true);
        wrapper_current_responses = document.getElementById('wrapper_current_responses');

        // This should not happen, but check just in case quiz_info fails to set the html.
        if (wrapper_current_responses === null) {
            return;
        }
    }

    // Update HTML
    this.create_response_bar_graph(this.current_responses, 'current_response', 'wrapper_current_responses', slot);
    this.sort_response_bar_graph('wrapper_current_responses');

};


jazzquiz.start_quiz = function () {

    var params = {
        'action': 'startquiz',
        'attemptid': this.quiz.attempt_id
    };

    this.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {
        jQuery('#inquizcontrols').removeClass('btn-hide');
    });

    jQuery('#startquiz').addClass('btn-hide');
    jazzquiz.hide_instructions();

};


jazzquiz.handle_question = function (slot) {

    this.loading(M.util.get_string('gatheringresults', 'jazzquiz'), 'show');

    if (typeof tinyMCE !== 'undefined') {
        tinyMCE.triggerSave();
    }

    // will only work on Modern browsers
    // of course the problem child is always IE...
    var qform = document.forms.namedItem('q' + slot);

    var params = new FormData(qform);
    params.append('action', 'savequestion');
    params.append('attemptid', this.quiz.attempt_id);
    params.append('questionid', slot);

    // Submit the form
    this.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status === HTTP_STATUS.ERROR) {
            window.alert('There was an error with your request ... ' + response.error);
            return;
        }

        // Update the sequence check for the question
        var sequence_check = document.getElementsByName(response.seqcheckname);
        var field = sequence_check[0];
        field.value = response.seqcheckval;

        // We don't really care about the response for instructors as we're going to set timeout for gathering response

        jazzquiz.quiz.question.is_ended = true;
        jazzquiz.quiz.question.is_running = false;

        var params = {
            'action': 'endquestion',
            'question': jazzquiz.quiz.current_question_slot,
            'attemptid': jazzquiz.quiz.attempt_id
        };

        // make sure we end the question (on end_question function call this is re-doing what we just did)
        // but handle_request is also called on ending of the question timer in core.js
        jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

            if (status === HTTP_STATUS.ERROR) {

                jQuery('#loadingbox').addClass('hidden');
                jQuery('#jazzquiz_info_container').removeClass('hidden').html('There was an error with your request');

            } else if (status === HTTP_STATUS.OK) {

                jQuery('#q' + jazzquiz.quiz.current_question_slot + '_questiontimetext').html('');
                jQuery('#q' + jazzquiz.quiz.current_question_slot + '_questiontime').html('');

                jazzquiz.gather_results();
                jazzquiz.getnotresponded();

            }
        });

        //setTimeout(jazzquiz.gather_results, 3500);
        //setTimeout(jazzquiz.getnotresponded, 3500);

    });
};

jazzquiz.show_improvised_question_setup = function () {

    var button = jQuery('#startimprovisedquestion');

    if (button.hasClass('active')) {
        // It's already open. Let's not send another request.
        return;
    }

    // The dropdown lies within the button, so we have to do this extra step
    // This attribute is set in the onclick function for one of the buttons in the dropdown
    // TODO: Redo the dropdown so we don't have to do this.
    if (button.attr('data-isclosed') === 'yes') {
        button.attr('data-isclosed', '');
        return;
    }

    // Setup parameters
    var params = {
        'action': 'listdummyquestions',
        'attemptid': this.quiz.attempt_id
    };

    // Send request
    this.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status !== HTTP_STATUS.OK) {
            return;
        }

        var questions = JSON.parse(response.questions);

        var menu = jQuery('.improvise-menu');
        menu.html('').addClass('active');
        jQuery('#startimprovisedquestion').addClass('active');

        for (var i in questions) {

            // TODO: This is a bit ugly. Redo the onclick event.
            var html = '<button class="btn" ';
            html += 'onclick="';
            html += 'jazzquiz.chosen_improvisation_question_slot = ' + questions[i].slot + ';';
            html += 'jazzquiz.start_improvised_question();';
            html += "jQuery('.improvise-menu').html('').removeClass('active');";
            html += "jQuery('#startimprovisedquestion').removeClass('active').attr('data-isclosed', 'yes');";
            html += '">' + questions[i].name + '</button>';
            menu.append(html);

        }

    });

};

jazzquiz.start_improvised_question = function () {
    this.submit_goto_question(this.chosen_improvisation_question_slot, true);
};

jazzquiz.get_selected_answers_for_vote = function () {

    var result = [];

    jQuery('.selected-vote-option').each(function (i, option) {
        result.push({
            text: option.dataset.response,
            count: option.dataset.count
        });
    });

    return result;
};

jazzquiz.get_and_show_vote_results = function () {

    // Setup parameters
    var params = {
        'action': 'getvoteresults',
        'attemptid': this.quiz.attempt_id
    };

    // Send request
    this.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status !== HTTP_STATUS.OK) {
            jQuery('#jazzquiz_info_container').removeClass('hidden').html('There was an error getting the vote results.');
            return;
        }

        var answers = JSON.parse(response.answers);

        var target_id = 'wrapper_vote_responses';

        var responses = [];
        for (var i in answers) {
            responses.push({
                response: answers[i].attempt,
                count: answers[i].finalcount,
                qtype: answers[i].qtype,
                slot: answers[i].slot
            });
        }

        var target = document.getElementById(target_id);
        if (target === null) {
            jQuery('#jazzquiz_responses_container').removeClass('hidden').html('<table id="' + target_id + '" class="jazzquiz-responses-overview"></table>', true);
            target = document.getElementById(target_id);

            // This should not happen, but check just in case quiz_info fails to set the html.
            if (target === null) {
                return;
            }
        }

        var slot = 0;
        if (responses.length > 0) {
            slot = responses[0].slot;
        }

        jazzquiz.create_response_bar_graph(responses, 'vote_response', target_id, slot);
        jazzquiz.sort_response_bar_graph(target_id);


    });
};

jazzquiz.run_voting = function () {

    var vote_options = this.get_selected_answers_for_vote();
    var questions_param = encodeURIComponent(JSON.stringify(vote_options));

    // Setup parameters
    var params = {
        'action': 'runvoting',
        'attemptid': this.quiz.attempt_id,
        'questions': questions_param,

        // TODO: currentquestion isn't always available at page load
        'qtype': this.quiz.questions[this.quiz.current_question_slot].question.qtype
    };

    // Send request
    this.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status !== HTTP_STATUS.OK) {
            jQuery('#jazzquiz_info_container').removeClass('hidden').html('There was an error starting the vote.');
            return;
        }

        // Hide unnecessary information
        jQuery('#jazzquiz_responded_container').addClass('hidden').html('');
        jazzquiz.hide_all_questionboxes();

    });
};

/**
 * This function is slightly different than gather results as it doesn't look to alter the state of the quiz, or the interface
 * but just get the results of the quesiton and display them in the quiz info box
 *
 */
jazzquiz.gather_current_results = function () {

    // Check if we are showing student responses
    if (!this.options.show_responses && this.current_quiz_state !== 'reviewing') {
        return;
    }

    // Setup parameters
    var params = {
        'action': 'getcurrentresults',
        'attemptid': this.quiz.attempt_id
    };

    // Send request
    this.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status !== HTTP_STATUS.OK) {

            // Something went wrong
            jQuery('#jazzquiz_info_container').removeClass('hidden').html('There was an error getting current results.');
            return;
        }

        jazzquiz.quiz.question.has_votes = response.has_votes;

        // Set responses
        jazzquiz.quiz_info_responses(response.responses, response.qtype, response.slot);

        // See if any question type javascript was added and evaluate
        if (document.getElementById(response.qtype + '_js') !== null) {
            eval(document.getElementById(response.qtype + '_js').innerHTML);
        }

    });

};

/**
 * This function will call the normal getresults case of quiz data.  This alters the quiz state to "reviewing", as well as
 * updates the instructor's interface with the buttons allowed for this state of the quiz
 *
 */
jazzquiz.gather_results = function () {

    // Setup parameters
    var params = {
        'action': 'getresults',
        'attemptid': this.quiz.attempt_id
    };

    // Send request
    this.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        jazzquiz.loading('', 'hide');

        jQuery('#q' + jazzquiz.quiz.current_question_slot + '_container').removeClass('hidden');

        // Only put results into the screen if
        if (jazzquiz.options.show_responses) {

            jQuery('#jazzquiz_responded_container').addClass('hidden').html('');
            jQuery('#jazzquiz_response_info_container').addClass('hidden').html('');
            jQuery('#jazzquiz_responses_container').addClass('hidden').html('');
            jQuery('#jazzquiz_info_container').addClass('hidden').html('');

            jazzquiz.quiz_info_responses(response.responses, response.qtype, response.slot);

            // After the responses have been inserted, we see if any question type javascript was added and evaluate
            if (document.getElementById(response.qtype + '_js') !== null) {
                eval(document.getElementById(response.qtype + '_js').innerHTML);
            }
        }
    });

};

jazzquiz.repoll_question = function () {

    // Hide all question boxes
    this.hide_all_questionboxes();
    jQuery('#jazzquiz_responded_container').addClass('hidden').html('');
    jQuery('#jazzquiz_response_info_container').addClass('hidden').html('');
    jQuery('#jazzquiz_responses_container').addClass('hidden').html('');
    jQuery('#jazzquiz_info_container').addClass('hidden').html('');

    // Setup parameters
    var params = {
        'action': 'repollquestion',
        'attemptid': this.quiz.attempt_id
    };

    // Send request
    this.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status !== HTTP_STATUS.OK) {

            jQuery('#loadingbox').addClass('hidden');
            jQuery('#jazzquiz_info_container').removeClass('hidden').html('There was an error with your request.');

            return;
        }

        jazzquiz.quiz.question.is_last = (response.lastquestion === 'true');

        jazzquiz.waitfor_question(response.questionid, response.questiontime, response.delay, response.nextstarttime);
    });

};

jazzquiz.next_question = function () {

    // Hide all question boxes
    this.hide_all_questionboxes();
    jQuery('#jazzquiz_responded_container').addClass('hidden').html('');
    jQuery('#jazzquiz_response_info_container').addClass('hidden').html('');
    jQuery('#jazzquiz_responses_container').addClass('hidden').html('');
    jQuery('#jazzquiz_info_container').addClass('hidden').html('');

    // Ensure that the previous question's form is hidden
    jQuery('#q' + this.quiz.current_question_slot + '_container').addClass('hidden');

    // Setup parameters
    var params = {
        'action': 'nextquestion',
        'attemptid': this.quiz.attempt_id
    };

    // Send request
    this.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status !== HTTP_STATUS.OK) {
            jQuery('#loadingbox').addClass('hidden');
            jQuery('#jazzquiz_info_container').removeClass('hidden').html('There was an error with your request.');
            return;
        }

        jazzquiz.quiz.question.is_last = (response.lastquestion === 'true');

        jazzquiz.waitfor_question(response.questionid, response.questiontime, response.delay, response.nextstarttime);
    });
};

jazzquiz.end_question = function () {

    // Setup parameters
    var params = {
        'action': 'endquestion',
        'question': this.quiz.current_question_slot,
        'attemptid': this.quiz.attempt_id
    };

    var vote_callback = function (status, response) {
        if (status === HTTP_STATUS.ERROR) {
            console.log('Failed to end vote.');
        }
    };

    var callback = function (status, response) {

        if (status !== HTTP_STATUS.OK) {
            jQuery('#loadingbox').addClass('hidden');
            jQuery('#jazzquiz_info_container').removeClass('hidden').html('There was an error with your request.');
            return;
        }

        // Clear the counter interval
        if (jazzquiz.qcounter) {
            clearInterval(jazzquiz.qcounter);
        }

        jQuery('#q' + jazzquiz.quiz.current_question_slot + '_questiontimetext').html('');
        jQuery('#q' + jazzquiz.quiz.current_question_slot + '_questiontime').html('');

        jazzquiz.quiz.question.is_running = false;
        jazzquiz.quiz.question.is_ended = true;

        // After getting endquestion response, go through the normal handle_question flow
        jazzquiz.handle_question(jazzquiz.quiz.current_question_slot);

    };

    // Make sure we use the correct callback for the state.
    if (this.current_quiz_state === 'voting') {
        jazzquiz.quiz.show_votes_upon_review = true;
        callback = vote_callback;
    }

    // Send request
    this.ajax.create_request('/mod/jazzquiz/quizdata.php', params, callback);

};

jazzquiz.close_session = function () {

    this.loading(M.util.get_string('closingsession', 'jazzquiz'), 'show');

    // Setup parameters
    var params = {
        'action': 'closesession',
        'attemptid': this.quiz.attempt_id
    };

    // Send request
    this.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status !== HTTP_STATUS.OK) {
            jQuery('#loadingbox').addClass('hidden');
            jQuery('#jazzquiz_info_container').removeClass('hidden').html('There was an error with your request.');
            return;
        }

        jazzquiz.hide_all_questionboxes();
        jQuery('#jazzquiz_responded_container').addClass('hidden').html('');
        jQuery('#jazzquiz_responses_container').addClass('hidden').html('');
        jQuery('#jazzquiz_info_container').addClass('hidden').html('');

        jQuery('#controlbox').addClass('hidden');

        jQuery('#jazzquiz_info_container').removeClass('hidden').html(M.util.get_string('sessionclosed', 'jazzquiz'));
        jazzquiz.loading(null, 'hide');

    });

};

// keep_flow: if true, the "next question" won't change.
jazzquiz.submit_goto_question = function (qnum, keep_flow) {

    this.hide_all_questionboxes();

    jQuery('#jazzquiz_responded_container').addClass('hidden').html('');
    jQuery('#jazzquiz_responses_container').addClass('hidden').html('');
    jQuery('#jazzquiz_info_container').addClass('hidden').html('');

    // TODO: If two improvised in a row, make sure it still doesn't break the flow

    // Setup parameters
    var params = {
        'action': 'gotoquestion',
        'qnum': qnum,
        'attemptid': this.quiz.attempt_id
    };

    if (keep_flow === true) {
        params['keepflow'] = 'true';
    }

    // Send request
    this.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status !== HTTP_STATUS.OK) {
            jQuery('#loadingbox').addClass('hidden');
            jQuery('#jazzquiz_info_container').removeClass('hidden').html('There was an error with your request.');
            return;
        }

        jazzquiz.quiz.question.is_last = (response.lastquestion === 'true');

        // Reset location.hash to nothing so that the modal dialog disappears
        window.location.hash = '';

        // Start question
        jazzquiz.waitfor_question(response.questionid, response.questiontime, response.delay, response.nextstarttime);

    });

};

jazzquiz.jumpto_question = function () {

    if (window.location.hash === '#jumptoquestion-dialog') {

        // Assume that we want to go to that the question in the select.
        // The x/close removes the hash and doesn't re-call this function.
        // It is only called on the "Jump to" button click when the dialog is open.
        var select = document.getElementById('jtq-selectquestion');
        var slot = select.options[select.selectedIndex].value;

        this.submit_goto_question(slot, false);

    } else {

        // Open the dialog
        window.location.hash = 'jumptoquestion-dialog';
    }

};

jazzquiz.show_correct_answer = function () {

    // Hide if already showing.
    if (this.options.is_showing_correct_answer) {

        // Make sure it's gone
        jQuery('#jazzquiz_correct_answer_container').addClass('hidden').html('');

        // Change button icon
        jQuery('#showcorrectanswer').html('<i class="fa fa-square-o"></i> Answer');

        this.options.is_showing_correct_answer = false;
        this.gather_results();

        // We don't need to ask for the answer, so let's return.
        return;
    }

    this.loading(M.util.get_string('loading', 'jazzquiz'), 'show');

    // Setup parameters
    var params = {
        'action': 'getrightresponse',
        'attemptid': this.quiz.attempt_id
    };

    // Make sure we end the question (on end_question function call this is re-doing what we just did)
    // handle_request is also called on ending of the question timer in core.js
    this.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status !== HTTP_STATUS.OK) {
            jQuery('#loadingbox').addClass('hidden');
            jQuery('#jazzquiz_info_container').removeClass('hidden').html('There was an error with your request.');
            return;
        }

        jQuery('#jazzquiz_correct_answer_container')
            .removeClass('hidden')
            .html('<span class="jazzquiz-latex-wrapper">' + response.rightanswer + '</span>');

        jazzquiz.render_all_mathjax();

        jQuery('#showcorrectanswer').html('<i class="fa fa-check-square-o"></i> Answer');

        jazzquiz.options.is_showing_correct_answer = true;
        jazzquiz.loading(null, 'hide');

    });

};

/**
 * Hides the responses
 */
jazzquiz.hide_responses = function() {
    this.options.show_responses = false;
    jQuery('#toggleresponses').html('<i class="fa fa-square-o"></i> Responses');
    jQuery('#jazzquiz_response_info_container').addClass('hidden').html('');
    jQuery('#jazzquiz_responses_container').addClass('hidden').html('');
};

/**
 * Shows the responses
 */
jazzquiz.show_responses = function() {
    this.options.show_responses = true;
    jQuery('#toggleresponses').html('<i class="fa fa-check-square-o"></i> Responses');
    this.gather_current_results();
};

/**
 * Toggle whether to show or hide the responses
 */
jazzquiz.toggle_responses = function() {
    if (this.options.show_responses) {
        this.hide_responses();
    } else {
        this.show_responses();
    }
};

/**
 * Toggles the "show not responded" variable
 */
jazzquiz.toggle_notresponded = function () {
    
    if (this.options.show_not_responded) {

        this.options.show_not_responded = false;

        jQuery('#togglenotresponded').html('<i class="fa fa-square-o"></i> Responded');
        jQuery('#jazzquiz_responded_container').addClass('hidden').html('');

    } else {

        this.options.show_not_responded = true;

        jQuery('#togglenotresponded').html('<i class="fa fa-check-square-o"></i> Responded');
        this.getnotresponded();
    }
};


jazzquiz.getnotresponded = function () {

    // Setup parametrs
    var params = {
        'action': 'getnotresponded',
        'attemptid': this.quiz.attempt_id
    };

    // Send request
    this.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        var $responded_container = jQuery('#jazzquiz_responded_container');

        if (status !== HTTP_STATUS.OK) {
            $responded_container.removeClass('hidden').html('There was an error getting not responded students.');
            return;
        }

        $responded_container.removeClass('hidden').html(response.notresponded);

    });

};

/**
 * Function to automatically disable/enable buttons from the array passed.
 *
 * @param buttons An array of button ids to have enabled in the in quiz controls buttons
 */
jazzquiz.control_buttons = function (buttons) {

    // This function requires jQuery.
    if (!window.jQuery) {
        console.log('jQuery not loaded. ' + this.current_quiz_state + ': Failed to activate the following buttons:');
        console.log(buttons);
        return;
    }

    // Let's find the direct child nodes.
    var children = jQuery('#inquizcontrols').find('.quiz-control-buttons').children();

    // Disable all the buttons that are not present in the "buttons" parameter.
    for (var i = 0; i < children.length; i++) {
        var id = children[i].getAttribute("id");
        children[i].disabled = (buttons.indexOf(id) === -1);
    }

};

jazzquiz.hide_controls = function () {
    jQuery('#inquizcontrols').find('.quiz-control-buttons').addClass('hidden');
};

jazzquiz.show_controls = function () {
    jQuery('#inquizcontrols').find('.quiz-control-buttons').removeClass('hidden');
};

// Create a container with fixed position that fills the entire screen
// Grabs the already existing question text and bar graph and shows it in a minimalistic style.
jazzquiz.show_fullscreen_results_view = function () {

    // Hide the scrollbar - remember to always set back to auto when closing
    document.documentElement.style.overflowY = 'hidden';

    // Does the container already exist?
    var container = document.getElementById('fullscreen_results_container');
    if (container === null) {

        // Create the container
        container = document.createElement('div');
        container.id = 'fullscreen_results_container';
        document.body.appendChild(container);

    }

    // Set question text
    container.innerHTML = this.get_question_body_formatted(this.quiz.current_question_slot);

    // Do we want to show the results?
    if (this.current_quiz_state !== 'running') {

        // Add bar graph
        var responses_container = document.getElementById('jazzquiz_responses_container');
        if (responses_container !== null && responses_container.children.length > 0) {
            // NOTE: Always assumes first child of responses_container is a table
            container.innerHTML += '<table class="jazzquiz-responses-overview">' + responses_container.children[0].innerHTML + '</table>';
        }

    }

    // Let's update the view every second
    if (this.fullscreen_interval_handle === undefined) {
        this.fullscreen_interval_handle = setInterval(function () {
            jazzquiz.show_fullscreen_results_view();
        }, 1000);
    }
};

// Checks if the view currently exists, and removes it if so.
jazzquiz.close_fullscreen_results_view = function () {

    // Stop the interval
    clearInterval(this.fullscreen_interval_handle);
    this.fullscreen_interval_handle = undefined;

    // Does the container exist?
    var container = document.getElementById('fullscreen_results_container');
    if (container !== null) {

        // Remove the container entirely
        container.parentNode.removeChild(container);

        // Reset the overflow-y back to auto
        document.documentElement.style.overflowY = 'auto';
    }
};

jazzquiz.execute_control_action = function (action) {

    // Prevent duplicate clicks
    // TODO: Find a better way to check if this is a direct action or not. Perhaps a class?
    if (action !== 'startimprovisedquestion') {
        this.control_buttons([]);
    }

    // Execute action
    switch (action) {
        case 'repollquestion':
            this.repoll_question();
            break;
        case 'runvoting':
            this.run_voting();
            break;
        case 'startimprovisedquestion':
            this.show_improvised_question_setup();
            break;
        case 'jumptoquestion':
            this.jumpto_question();
            break;
        case 'nextquestion':
            this.next_question();
            break;
        case 'endquestion':
            this.end_question();
            break;
        case 'showfullscreenresults':
            this.show_fullscreen_results_view();
            break;
        case 'showcorrectanswer':
            this.show_correct_answer();
            break;
        case 'toggleresponses':
            this.toggle_responses();
            break;
        case 'togglenotresponded':
            this.toggle_notresponded();
            break;
        case 'closesession':
            this.close_session();
            break;
        case 'startquiz':
            this.start_quiz();
            break;
        default:
            console.log('Unknown action ' + action);
            break;
    }

};

// Listens for key event to remove the projector view container
document.addEventListener('keyup', function (e) {
    // Check if 'Escape' key was pressed
    if (e.keyCode === 27) {
        jazzquiz.close_fullscreen_results_view();
    }
});

// Listens for click events to hide the improvise menu when there is an outside click
document.addEventListener('click', function (e) {

    // Clicking on improvisation menu
    var menu = jQuery(e.target).closest('.improvise-menu');
    if (!menu.length) {
        jQuery('.improvise-menu').html('').removeClass('active');
        jQuery('#startimprovisedquestion').removeClass('active');
    }

    // Clicking a row to merge
    if (jazzquiz.current_quiz_state === 'reviewing') {
        if (e.target.classList.contains('bar')) {
            jazzquiz.start_response_merge(e.target.id);
        } else if (e.target.parentNode && e.target.parentNode.classList.contains('bar')) {
            jazzquiz.start_response_merge(e.target.parentNode.id);
        }
    }

});
