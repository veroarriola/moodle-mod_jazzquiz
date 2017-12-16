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

    this.is_new_state = (this.state !== state);
    this.state = state;

    jQuery('#inquizcontrols_state').html(state);
    jQuery('#region-main').find('ul.nav.nav-tabs').css('display', 'none');
    jQuery('#region-main-settings-menu').css('display', 'none');
    jQuery('.region_main_settings_menu_proxy').css('display', 'none');

    var $start_quiz = jQuery('#startquiz');

    this.show_controls();

    switch (state) {

        case 'notrunning':
            this.control_buttons([]);
            this.hide_controls();
            this.quiz.question.is_ended = false;
            this.quiz.total_students = data.students;
            var students_joined = 'No students have joined.';
            if (data.students === 1) {
                students_joined = '1 student has joined.';
            } else if (data.students > 1) {
                students_joined = data.students + ' students have joined.';
            }
            $start_quiz.next().html(students_joined);
            break;

        case 'preparing':
            this.control_buttons([
                'startimprovisedquestion',
                'jumptoquestion',
                'nextquestion',
                'showfullscreenresults',
                'closesession'
            ]);
            $start_quiz.parent().addClass('hidden');
            break;

        case 'running':
            this.control_buttons([
                'endquestion',
                'toggleresponses',
                'showfullscreenresults'
            ]);
            if (this.quiz.question.is_running) {
                // Update current responses and responded.
                this.gather_results();
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
                //'toggleresponses'
            ];
            if (!this.quiz.question.is_last) {
                enabled_buttons.push('nextquestion');
            }
            this.control_buttons(enabled_buttons);

            // For now, just always show responses while reviewing
            // In the future, there should be an additional toggle.
            if (this.is_new_state) {
                if (this.quiz.show_votes_upon_review) {
                    this.get_and_show_vote_results();
                    this.quiz.show_votes_upon_review = false;
                } else {
                    this.gather_results();
                }
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
            $start_quiz.parent().addClass('hidden');
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
/*
// This function is meant to align the side container with the question box.
// There is a problem where this causes the control separator to have a huge padding when changing questions.
// For now, probably best to not use this.
jazzquiz.align_side_container = function() {
    // Find the elements.
    var $question_slot = jQuery('#q' + this.quiz.current_question_slot + '_container');
    if ($question_slot.length === 0) {
        return;
    }
    var $side_container = jQuery('#jazzquiz_side_container');
    var $control_separator = jQuery('#jazzquiz_control_separator');
    // Update the height for the side container.
    $side_container.css('height', $question_slot.css('height'));
    // How far apart are side container and question box?
    var delta = Math.abs(parseInt($question_slot.offset().top - $side_container.offset().top));
    // How much height should the control separator compensate?
    var compensation = delta + parseInt($control_separator.css('height'));
    // Set the new compensation height.
    $control_separator.css('height', compensation + 'px');
};
*/

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
        this.quiz_info_responses('jazzquiz_responses_container', 'current_responses_wrapper', this.current_responses, this.qtype, this.quiz.current_question_slot);
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
    var $response_info_container = jQuery('#jazzquiz_response_info_container');
    if (!this.quiz.question.has_votes) {
        $response_info_container.addClass('hidden');
        return;
    }
    // Add button for instructor to change what to review
    if (this.state === 'reviewing') {
        var $show_normal_result  = jQuery('#review_show_normal_results');
        var $show_vote_result  = jQuery('#review_show_vote_results');
        $response_info_container.removeClass('hidden');
        if (name === 'vote_response') {
            if ($show_normal_result.length === 0) {
                $response_info_container.html('<h4 class="inline">Showing vote results</h4>');
                $response_info_container.append('<button id="review_show_normal_results" onclick="jazzquiz.gather_results();" class="btn btn-primary">Click to show original results</button><br>');
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
};

jazzquiz.create_response_bar_graph = function (responses, name, name_unique, target_id, slot) {
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

    name += name_unique;

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

jazzquiz.quiz_info_responses = function (wrapper_id, table_id, responses, qtype, slot) {
    if (responses === undefined) {
        console.log('Responses is undefined.');
        return;
    }

    // Check if any responses to show
    if (responses.length === 0) {
        jQuery('#jazzquiz_responded_container').removeClass('hidden').find('h4').html('0 / ' + this.quiz.total_students + ' responded');
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
    this.quiz.responded_count = 0;
    for (var i = 0; i < responses.length; i++) {

        var exists = false;
        var count = 1;
        if (responses[i].count !== undefined) {
            count = parseInt(responses[i].count);
        }

        this.quiz.responded_count += count;

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

    // Update responded container
    var $responded_container = jQuery('#jazzquiz_responded_container');
    if ($responded_container.length !== 0) {
        $responded_container.removeClass('hidden').find('h4').html(this.quiz.responded_count + ' / ' + this.quiz.total_students + ' responded');
    }

    // Should we show the responses?
    if (!this.options.show_responses && this.state !== 'reviewing') {
        jQuery('#jazzquiz_response_info_container').addClass('hidden').html('');
        jQuery('#jazzquiz_responses_container').addClass('hidden').html('');
        return;
    }

    // Make sure quiz info has the wrapper for the responses
    var wrapper_current_responses = document.getElementById(table_id); // wrapper_current_responses
    if (wrapper_current_responses === null) {
        jQuery('#' + wrapper_id).removeClass('hidden').html('<table id="' + table_id + '" class="jazzquiz-responses-overview"></table>', true);
        wrapper_current_responses = document.getElementById(table_id);

        // This should not happen, but check just in case quiz_info fails to set the html.
        if (wrapper_current_responses === null) {
            return;
        }
    }

    // Update HTML
    this.create_response_bar_graph(this.current_responses, 'current_response', slot, table_id, slot);
    this.sort_response_bar_graph(table_id);
};


jazzquiz.start_quiz = function () {
    var params = {
        action: 'startquiz'
    };
    this.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {
        jQuery('#inquizcontrols').removeClass('btn-hide');
    });
    jQuery('#startquiz').parent().addClass('hidden');
    jazzquiz.hide_instructions();
};

jazzquiz.handle_question = function (slot) {
    this.show_loading(this.text('gathering_results'));
    if (typeof tinyMCE !== 'undefined') {
        tinyMCE.triggerSave();
    }

    var qform = document.forms.namedItem('q' + slot);
    var params = new FormData(qform);
    params.append('action', 'savequestion');
    params.append('questionid', slot);

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
            'question': jazzquiz.quiz.current_question_slot
        };

        // make sure we end the question (on end_question function call this is re-doing what we just did)
        // but handle_request is also called on ending of the question timer in core.js
        jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {
            if (status === HTTP_STATUS.ERROR) {
                jQuery('#loadingbox').addClass('hidden');
                jQuery('#jazzquiz_info_container').removeClass('hidden').html('There was an error with your request');
                return;
            }
            jQuery('#jazzquiz_question_timer').html('').addClass('hidden');
            jazzquiz.show_question();
            jazzquiz.quiz.question.is_running = false;
            jazzquiz.control_buttons([]);
        });
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

    var params = {
        action: 'listdummyquestions'
    };

    this.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {
        if (status !== HTTP_STATUS.OK) {
            return;
        }

        var menu = jQuery('.improvise-menu');
        menu.html('').addClass('active');
        jQuery('#startimprovisedquestion').addClass('active');

        var questions = response.questions;
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

    // Should we show the results?
    if (!this.options.show_responses && this.state !== 'reviewing') {
        jQuery('#jazzquiz_response_info_container').addClass('hidden').html('');
        jQuery('#jazzquiz_responses_container').addClass('hidden').html('');
        return;
    }

    var params = {
        action: 'getvoteresults'
    };

    this.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {
        if (status !== HTTP_STATUS.OK) {
            jQuery('#jazzquiz_info_container').removeClass('hidden').html('There was an error getting the vote results.');
            return;
        }

        var answers = response.answers;
        var target_id = 'wrapper_vote_responses';
        var responses = [];

        jazzquiz.quiz.responded_count = 0;
        jazzquiz.quiz.total_students = parseInt(response.total_students);

        for (var i in answers) {
            responses.push({
                response: answers[i].attempt,
                count: answers[i].finalcount,
                qtype: answers[i].qtype,
                slot: answers[i].slot
            });
            jazzquiz.quiz.responded_count += parseInt(answers[i].finalcount);
        }

        jQuery('#jazzquiz_responded_container').removeClass('hidden').find('h4').html(jazzquiz.quiz.responded_count + ' / ' + jazzquiz.quiz.total_students + ' voted');

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

        jazzquiz.create_response_bar_graph(responses, 'vote_response', slot, target_id, slot);
        jazzquiz.sort_response_bar_graph(target_id);
    });
};

jazzquiz.run_voting = function () {
    var vote_options = this.get_selected_answers_for_vote();
    var questions_param = encodeURIComponent(JSON.stringify(vote_options));
    var params = {
        'action': 'runvoting',
        'questions': questions_param,
        'qtype': this.quiz.questions[this.quiz.current_question_slot].question.qtype
    };
    this.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {
        if (status !== HTTP_STATUS.OK) {
            jQuery('#jazzquiz_info_container').removeClass('hidden').html('There was an error starting the vote.');
        }
    });
};

jazzquiz.gather_results = function () {
    var params = {
        'action': 'getresults'
    };
    this.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {
        jazzquiz.hide_loading();
        if (status !== HTTP_STATUS.OK) {
            // Something went wrong
            jQuery('#jazzquiz_info_container').removeClass('hidden').html('There was an error getting current results.');
            return;
        }
        jazzquiz.quiz.question.has_votes = response.has_votes;
        jazzquiz.quiz.total_students = parseInt(response.total_students);
        jazzquiz.quiz_info_responses('jazzquiz_responses_container', 'current_responses_wrapper', response.responses, response.qtype, response.slot);
    });
};

jazzquiz.repoll_question = function () {
    this.hide_all_questions();
    this.hide_info();
    var params = {
        'action': 'repollquestion'
    };
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
    this.hide_all_questions();
    this.hide_info();
    var params = {
        'action': 'nextquestion'
    };
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
    var params = {
        'action': 'endquestion',
        'question': this.quiz.current_question_slot
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

        jQuery('#jazzquiz_question_timer').html('').addClass('hidden');

        jazzquiz.quiz.question.is_running = false;
        jazzquiz.quiz.question.is_ended = true;

        // After getting endquestion response, go through the normal handle_question flow
        jazzquiz.handle_question(jazzquiz.quiz.current_question_slot, true);
    };

    // Make sure we use the correct callback for the state.
    if (this.state === 'voting') {
        jazzquiz.quiz.show_votes_upon_review = true;
        callback = vote_callback;
    }

    this.ajax.create_request('/mod/jazzquiz/quizdata.php', params, callback);
};

jazzquiz.close_session = function () {
    this.show_loading(this.text('closing_session'));
    this.hide_instructions();
    this.hide_all_questions();
    this.hide_info();

    jQuery('#controlbox').addClass('hidden');

    var params = {
        'action': 'closesession'
    };
    this.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {
        jazzquiz.hide_loading();
        if (status !== HTTP_STATUS.OK) {
            jQuery('#jazzquiz_info_container').removeClass('hidden').html('There was an error with your request.');
            return;
        }
        jQuery('#jazzquiz_info_container').removeClass('hidden').html(jazzquiz.text('session_closed'));
    });
};

// keep_flow: if true, the "next question" won't change.
jazzquiz.submit_goto_question = function (qnum, keep_flow) {
    this.hide_all_questions();
    this.hide_info();

    // TODO: If two improvised in a row, make sure it still doesn't break the flow

    var params = {
        'action': 'gotoquestion',
        'qnum': qnum
    };
    if (keep_flow === true) {
        params['keepflow'] = 'true';
    }

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

jazzquiz.jump_question = function () {
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

        // We don't need to ask for the answer, so let's return.
        return;
    }

    this.show_loading(this.text('loading'));

    var params = {
        'action': 'getrightresponse'
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
        jazzquiz.hide_loading();
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
    this.gather_results();
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

/*jazzquiz.getnotresponded = function () {
    var params = {
        'action': 'getnotresponded'
    };
    this.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {
        var $responded_container = jQuery('#jazzquiz_responded_container');
        if (status !== HTTP_STATUS.OK) {
            $responded_container.removeClass('hidden').html('There was an error getting not responded students.');
            return;
        }
        $responded_container.removeClass('hidden').html(response.notresponded);
    });
};*/

/**
 * Function to automatically disable/enable buttons from the array passed.
 *
 * @param buttons An array of button ids to have enabled in the in quiz controls buttons
 */
jazzquiz.control_buttons = function (buttons) {
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

jazzquiz.show_fullscreen_view = function () {
    var $quiz_view = jQuery('#quizview');

    // Are we already in fullscreen mode?
    if ($quiz_view.hasClass('fullscreen-quizview')) {
        // Yes, let's close it instead.
        this.close_fullscreen_view();
        return;
    }

    // Hide the scrollbar - remember to always set back to auto when closing.
    document.documentElement.style.overflowY = 'hidden';

    // Sets the quiz view to an absolute position that covers the viewport.
    $quiz_view.addClass('fullscreen-quizview');
};

jazzquiz.close_fullscreen_view = function () {
    // Reset the overflow-y back to auto.
    document.documentElement.style.overflowY = 'auto';

    // Remove the fullscreen view.
    jQuery('#quizview').removeClass('fullscreen-quizview');
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
            this.jump_question();
            break;
        case 'nextquestion':
            this.next_question();
            break;
        case 'endquestion':
            this.end_question();
            break;
        case 'showfullscreenresults':
            this.show_fullscreen_view();
            break;
        case 'showcorrectanswer':
            this.show_correct_answer();
            break;
        case 'toggleresponses':
            this.toggle_responses();
            break;
        case 'exitquiz':
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
        jazzquiz.close_fullscreen_view();
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
    if (jazzquiz.state === 'reviewing') {
        if (e.target.classList.contains('bar')) {
            jazzquiz.start_response_merge(e.target.id);
        } else if (e.target.parentNode && e.target.parentNode.classList.contains('bar')) {
            jazzquiz.start_response_merge(e.target.parentNode.id);
        }
    }
});
