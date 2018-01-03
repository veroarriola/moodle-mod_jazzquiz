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

/**
 * The instructor's quiz state change handler
 */
jazzquiz.change_quiz_state = function(state, data) {

    this.is_new_state = (this.state !== state);
    this.state = state;

    jQuery('#inquizcontrols_state').html(state);
    jQuery('#region-main').find('ul.nav.nav-tabs').css('display', 'none');
    jQuery('#region-main-settings-menu').css('display', 'none');
    jQuery('.region_main_settings_menu_proxy').css('display', 'none');

    let $start_quiz = jQuery('#startquiz');
    let $side_container = jQuery('#jazzquiz_side_container');

    this.show_controls();
    $start_quiz.parent().addClass('hidden');

    switch (state) {

        case 'notrunning':
            this.show_info(this.text('instructions_for_instructor'));
            $side_container.addClass('hidden');
            this.enable_controls([]);
            this.hide_controls();
            this.quiz.total_students = data.student_count;
            let students_joined = 'No students have joined.';
            if (data.student_count === 1) {
                students_joined = '1 student has joined.';
            } else if (data.student_count > 1) {
                students_joined = data.student_count + ' students have joined.';
            }
            $start_quiz.parent().removeClass('hidden');
            $start_quiz.next().html(students_joined);
            break;

        case 'preparing':
            this.show_info(this.text('instructions_for_instructor'));
            $side_container.addClass('hidden');
            this.enable_controls([
                'nextquestion',
                'startimprovisequestion',
                'startjumpquestion',
                'showfullscreenresults',
                'closesession'
            ]);
            break;

        case 'running':
            $side_container.removeClass('hidden');
            this.enable_controls([
                'endquestion',
                'toggleresponses',
                'showfullscreenresults'
            ]);
            this.quiz.question.question_time = data.question_time;
            if (this.quiz.question.is_running) {
                // Check if the question has already ended.
                if (data.delay < -data.question_time) {
                    this.end_question();
                }
                // Update current responses and responded.
                this.get_results();
            } else {
                const started = this.start_question_countdown(data.question_time, data.delay);
                if (started) {
                    this.quiz.question.is_running = true;
                }
            }
            break;

        case 'reviewing':
            $side_container.removeClass('hidden');
            let enabled_buttons = [
                'showcorrectanswer',
                'runvoting',
                'repollquestion',
                'showfullscreenresults',
                'startimprovisequestion',
                'startjumpquestion',
                'closesession'
            ];
            if (!this.quiz.question.is_last) {
                enabled_buttons.push('nextquestion');
            }
            this.enable_controls(enabled_buttons);

            // In case page was refreshed, we should ensure the question is showing.
            if (jQuery('#jazzquiz_question_box').html() === '') {
                this.reload_question_box();
            }

            // For now, just always show responses while reviewing
            // In the future, there should be an additional toggle.
            if (this.is_new_state) {
                if (this.quiz.show_votes_upon_review) {
                    this.get_and_show_vote_results();
                    this.quiz.show_votes_upon_review = false;
                } else {
                    this.get_results();
                }
            }
            // No longer in question
            this.quiz.question.is_running = false;
            break;

        case 'voting':
            $side_container.removeClass('hidden');
            this.enable_controls([
                'closesession',
                'showfullscreenresults',
                'showcorrectanswer',
                'toggleresponses',
                'endquestion'
            ]);
            this.get_and_show_vote_results();
            break;

        case 'sessionclosed':
            $side_container.addClass('hidden');
            this.enable_controls([]);
            this.quiz.question.is_running = false;
            break;

        default:
            this.enable_controls([]);
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
    const $bar_cell = jQuery('#' + from_row_bar_id);
    let $row = $bar_cell.parent();
    if ($row.hasClass('merge-into')) {
        this.end_response_merge();
        return;
    }
    if ($row.hasClass('merge-from')) {
        const $into_row = jQuery('.merge-into');
        this.current_responses[$into_row.data('response_i')].count += parseInt($row.data('count'));
        this.current_responses.splice($row.data('response_i'), 1);
        this.quiz_info_responses('jazzquiz_responses_container', 'current_responses_wrapper', this.current_responses, this.quiz.question.question_type, 'results');
        this.end_response_merge();
        return;
    }
    $row.addClass('merge-into');
    let $table = $row.parent().parent();
    $table.find('tr').each(function() {
        const $cells = jQuery(this).find('td');
        if ($cells[1].id !== $bar_cell.attr('id')) {
            jQuery(this).addClass('merge-from');
        }
    });
};

jazzquiz.create_response_controls = function(name) {
    let $response_info_container = jQuery('#jazzquiz_response_info_container');
    if (!this.quiz.question.has_votes) {
        $response_info_container.addClass('hidden');
        return;
    }
    // Add button for instructor to change what to review
    if (this.state === 'reviewing') {
        let $show_normal_result = jQuery('#review_show_normal_results');
        let $show_vote_result = jQuery('#review_show_vote_results');
        $response_info_container.removeClass('hidden');
        if (name === 'vote_response') {
            if ($show_normal_result.length === 0) {
                $response_info_container.html('<h4 class="inline">Showing vote results</h4>');
                $response_info_container.append('<button id="review_show_normal_results" onclick="jazzquiz.get_results();" class="btn btn-primary">Click to show original results</button><br>');
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

jazzquiz.create_response_bar_graph = function(responses, name, target_id, graph_id) {
    let target = document.getElementById(target_id);
    if (target === null) {
        return;
    }
    let total = 0;
    for (let i = 0; i < responses.length; i++) {
        total += parseInt(responses[i].count); // in case count is a string
    }
    if (total === 0) {
        total = 1;
    }

    // Prune rows
    for (let i = 0; i < target.rows.length; i++) {
        let prune = true;
        for (let j = 0; j < responses.length; j++) {
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

    name += graph_id;

    // Add rows
    for (let i = 0; i < responses.length; i++) {

        const percent = (parseInt(responses[i].count) / total) * 100;

        // Check if row with same response already exists
        let row_i = -1;
        let current_row_index = -1;
        for (let j = 0; j < target.rows.length; j++) {
            if (target.rows[j].dataset.response === responses[i].response) {
                row_i = target.rows[j].dataset.row_i;
                current_row_index = j;
                break;
            }
        }

        if (row_i === -1) {

            row_i = target.rows.length;

            let row = target.insertRow();
            row.dataset.response_i = i;
            row.dataset.response = responses[i].response;
            row.dataset.percent = percent;
            row.dataset.row_i = row_i;
            row.dataset.count = responses[i].count;
            row.classList.add('selected-vote-option');

            const count_html = '<span id="' + name + '_count_' + row_i + '">' + responses[i].count + '</span>';
            let response_cell = row.insertCell(0);
            response_cell.onclick = function() {
                jQuery(this).parent().toggleClass('selected-vote-option');
            };

            let bar_cell = row.insertCell(1);
            bar_cell.classList.add('bar');
            bar_cell.id = name + '_bar_' + row_i;
            bar_cell.innerHTML = '<div style="width:' + percent + '%;">' + count_html + '</div>';

            const latex_id = name + '_latex_' + row_i;
            response_cell.innerHTML = '<span id="' + latex_id + '"></span>';
            this.add_mathjax_element(latex_id, responses[i].response);

            if (responses[i].qtype === 'stack') {
                this.render_maxima_equation(responses[i].response, latex_id);
            }

        } else {
            target.rows[current_row_index].dataset.row_i = row_i;
            target.rows[current_row_index].dataset.response_i = i;
            target.rows[current_row_index].dataset.percent = percent;
            target.rows[current_row_index].dataset.count = responses[i].count;
            let count_element = document.getElementById(name + '_count_' + row_i);
            if (count_element !== null) {
                count_element.innerHTML = responses[i].count;
            }
            let bar_element = document.getElementById(name + '_bar_' + row_i);
            if (bar_element !== null) {
                bar_element.firstElementChild.style.width = percent + '%';
            }
        }
    }
};

jazzquiz.sort_response_bar_graph = function(target_id) {
    let target = document.getElementById(target_id);
    if (target === null) {
        return;
    }
    let is_sorting = true;
    while (is_sorting) {
        is_sorting = false;
        for (let i = 0; i < (target.rows.length - 1); i++) {
            const current = parseInt(target.rows[i].dataset.percent);
            const next = parseInt(target.rows[i + 1].dataset.percent);
            if (current < next) {
                target.rows[i].parentNode.insertBefore(target.rows[i + 1], target.rows[i]);
                is_sorting = true;
                break;
            }
        }
    }
};

jazzquiz.quiz_info_responses = function(wrapper_id, table_id, responses, qtype, graph_id) {
    if (responses === undefined) {
        console.log('Responses is undefined.');
        return;
    }

    let $responded_container = jQuery('#jazzquiz_responded_container');

    // Check if any responses to show
    if (responses.length === 0) {
        $responded_container.removeClass('hidden').find('h4').html('0 / ' + this.quiz.total_students + ' responded');
        return;
    }

    // Question type specific
    switch (qtype) {
        case 'shortanswer':
            for (let i = 0; i < responses.length; i++) {
                responses[i].response = responses[i].response.trim();
            }
            break;
        case 'stack':
            // Remove all spaces from responses
            for (let i = 0; i < responses.length; i++) {
                responses[i].response = responses[i].response.replace(/\s/g, '');
            }
            break;
        default:
            break;
    }

    // Update data
    this.current_responses = [];
    this.total_responses = responses.length;
    this.quiz.responded_count = 0;
    for (let i = 0; i < responses.length; i++) {

        let exists = false;
        let count = 1;
        if (responses[i].count !== undefined) {
            count = parseInt(responses[i].count);
        }
        this.quiz.responded_count += count;

        // Check if response is a duplicate
        for (let j = 0; j < this.current_responses.length; j++) {
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
    let wrapper_current_responses = document.getElementById(table_id); // wrapper_current_responses
    if (wrapper_current_responses === null) {
        jQuery('#' + wrapper_id).removeClass('hidden').html('<table id="' + table_id + '" class="jazzquiz-responses-overview"></table>', true);
        wrapper_current_responses = document.getElementById(table_id);
        // This should not happen, but check just in case quiz_info fails to set the html.
        if (wrapper_current_responses === null) {
            return;
        }
    }

    // Update HTML
    this.create_response_bar_graph(this.current_responses, 'current_response', table_id, graph_id);
    this.sort_response_bar_graph(table_id);
};

jazzquiz.start_quiz = function() {
    jQuery('#startquiz').parent().addClass('hidden');
    this.post('quizdata.php', {
        action: 'start_quiz'
    }, function() {
        jQuery('#inquizcontrols').removeClass('btn-hide');
    });
};

jazzquiz.end_question = function() {
    this.hide_question_timer();
    this.post('quizdata.php', {
        action: 'end_question'
    }, function() {
        if (jazzquiz.state === 'voting') {
            jazzquiz.quiz.show_votes_upon_review = true;
            return;
        }
        jazzquiz.quiz.question.is_running = false;
        jazzquiz.enable_controls([]);
    }).fail(function() {
        this.show_info('Failed to end the question.');
    });
};

jazzquiz.show_question_list_setup = function(name, action) {
    let $control_button = jQuery('#jazzquiz_' + name + '_button');
    if ($control_button.hasClass('active')) {
        // It's already open. Let's not send another request.
        return;
    }

    // The dropdown lies within the button, so we have to do this extra step
    // This attribute is set in the onclick function for one of the buttons in the dropdown
    // TODO: Redo the dropdown so we don't have to do this.
    if ($control_button.data('isclosed') === 'yes') {
        $control_button.data('isclosed', '');
        return;
    }

    this.get('quizdata.php', {
        action: action
    }, function(data) {
        let $menu = jQuery('#jazzquiz_' + name + '_menu');
        $menu.html('').addClass('active');
        $control_button.addClass('active');
        const questions = data.questions;
        for (let i in questions) {
            if (!questions.hasOwnProperty(i)) {
                continue;
            }
            let $question_button = jQuery('<button class="btn">' + questions[i].name + '</button>');
            $question_button.data({
                time: questions[i].time,
                'question-id': questions[i].question_id,
                'jazzquiz-question-id': questions[i].jazzquiz_question_id
            });
            $question_button.data('test', 1);
            $question_button.on('click', function() {
                jazzquiz.jump_question(jQuery(this).data('question-id'), jQuery(this).data('time'), jQuery(this).data('jazzquiz-question-id'));
                $menu.html('').removeClass('active');
                $control_button.removeClass('active').data('isclosed', 'yes');
            });
            $menu.append($question_button);
        }
    });
};

jazzquiz.show_improvise_question_setup = function() {
    this.show_question_list_setup('improvise', 'list_improvise_questions');
};

jazzquiz.show_jump_question_setup = function() {
    this.show_question_list_setup('jump', 'list_jump_questions');
};

jazzquiz.get_selected_answers_for_vote = function() {
    let result = [];
    jQuery('.selected-vote-option').each(function(i, option) {
        result.push({
            text: option.dataset.response,
            count: option.dataset.count
        });
    });
    return result;
};

jazzquiz.get_and_show_vote_results = function() {
    // Should we show the results?
    if (!this.options.show_responses && this.state !== 'reviewing') {
        jQuery('#jazzquiz_response_info_container').addClass('hidden').html('');
        jQuery('#jazzquiz_responses_container').addClass('hidden').html('');
        return;
    }
    this.get('quizdata.php', {
        action: 'get_vote_results'
    }, function(data) {
        const answers = data.answers;
        const target_id = 'wrapper_vote_responses';
        let responses = [];

        jazzquiz.quiz.responded_count = 0;
        jazzquiz.quiz.total_students = parseInt(data.total_students);

        for (let i in answers) {
            if (!answers.hasOwnProperty(i)) {
                continue;
            }
            responses.push({
                response: answers[i].attempt,
                count: answers[i].finalcount,
                qtype: answers[i].qtype,
                slot: answers[i].slot
            });
            jazzquiz.quiz.responded_count += parseInt(answers[i].finalcount);
        }

        jQuery('#jazzquiz_responded_container').removeClass('hidden').find('h4').html(jazzquiz.quiz.responded_count + ' / ' + jazzquiz.quiz.total_students + ' voted');

        let target = document.getElementById(target_id);
        if (target === null) {
            jQuery('#jazzquiz_responses_container').removeClass('hidden').html('<table id="' + target_id + '" class="jazzquiz-responses-overview"></table>', true);
            target = document.getElementById(target_id);
            // This should not happen, but check just in case quiz_info fails to set the html.
            if (target === null) {
                return;
            }
        }

        jazzquiz.create_response_bar_graph(responses, 'vote_response', target_id, 'vote');
        jazzquiz.sort_response_bar_graph(target_id);
    }).fail(function() {
        jazzquiz.show_info('There was an error getting the vote results.');
    });
};

jazzquiz.run_voting = function() {
    const vote_options = this.get_selected_answers_for_vote();
    const questions_param = encodeURIComponent(JSON.stringify(vote_options));
    this.post('quizdata.php', {
        action: 'run_voting',
        questions: questions_param
    }, function() {

    }).fail(function() {
        jazzquiz.show_info('There was an error starting the vote.');
    });
};

jazzquiz.get_results = function() {
    this.get('quizdata.php', {
        action: 'get_results'
    }, function(data) {
        jazzquiz.quiz.question.has_votes = data.has_votes;
        jazzquiz.quiz.total_students = parseInt(data.total_students);
        jazzquiz.quiz_info_responses('jazzquiz_responses_container', 'current_responses_wrapper', data.responses, data.question_type, 'results');
    }).fail(function() {
        jazzquiz.show_info('There was an error getting current results.');
    });
};

// TODO: Refactor these start question functions.
/**
 * Start a new question in this session.
 * @param {string} method
 * @param {number} question_id
 * @param {number} question_time
 * @param {number} jazzquiz_question_id
 */
jazzquiz.start_question = function(method, question_id, question_time, jazzquiz_question_id) {
    this.hide_info();
    this.post('quizdata.php', {
        action: 'start_question',
        method: method,
        question_id: question_id,
        question_time: question_time,
        jazzquiz_question_id: jazzquiz_question_id
    }, function(data) {
        jazzquiz.start_question_countdown(data.question_time, data.delay);
    }).fail(function() {
        jazzquiz.hide_loading();
        jazzquiz.show_info('There was an error with your request.');
    })
};

jazzquiz.jump_question = function(question_id, question_time, jazzquiz_question_id) {
    this.start_question('jump', question_id, question_time, jazzquiz_question_id);
};

jazzquiz.repoll_question = function() {
    this.start_question('repoll', 0, 0, 0);
};

jazzquiz.next_question = function() {
    this.start_question('next', 0, 0, 0);
};

jazzquiz.close_session = function() {
    this.show_loading(this.text('closing_session'));
    this.clear_question_box();
    this.hide_info();
    jQuery('#controlbox').addClass('hidden');
    this.post('quizdata.php', {
        action: 'close_session'
    }, function() {
        jazzquiz.hide_loading();
        jazzquiz.show_info(jazzquiz.text('session_closed'));
    }).fail(function() {
        jazzquiz.hide_loading();
        jazzquiz.show_info('There was an error with your request.');
    });
};

jazzquiz.show_correct_answer = function() {
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

    // Make sure we end the question (on end_question function call this is re-doing what we just did)
    // handle_request is also called on ending of the question timer in core.js
    this.show_loading(this.text('loading'));
    this.get('quizdata.php', {
        action: 'get_right_response'
    }, function(data) {
        jazzquiz.hide_loading();
        jQuery('#jazzquiz_correct_answer_container').removeClass('hidden').html('<span class="jazzquiz-latex-wrapper">' + data.right_answer + '</span>');
        jazzquiz.render_all_mathjax();
        jQuery('#showcorrectanswer').html('<i class="fa fa-check-square-o"></i> Answer');
        jazzquiz.options.is_showing_correct_answer = true;
    }).fail(function() {
        jazzquiz.hide_loading();
        jazzquiz.show_info('There was an error with your request.');
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
    this.get_results();
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
 * Disable/enable buttons from the array passed.
 * @param buttons An array of button ids to have enabled in the in quiz controls buttons
 */
jazzquiz.enable_controls = function(buttons) {
    // Let's find the direct child nodes.
    let children = jQuery('#inquizcontrols').find('.quiz-control-buttons').children();
    // Disable all the buttons that are not present in the "buttons" parameter.
    for (let i = 0; i < children.length; i++) {
        const id = children[i].getAttribute('id');
        children[i].disabled = (buttons.indexOf(id) === -1);
    }
};

jazzquiz.hide_controls = function() {
    jQuery('#inquizcontrols').find('.quiz-control-buttons').addClass('hidden');
};

jazzquiz.show_controls = function() {
    jQuery('#inquizcontrols').find('.quiz-control-buttons').removeClass('hidden');
};

jazzquiz.show_fullscreen_view = function() {
    let $quiz_view = jQuery('#quizview');
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

jazzquiz.close_fullscreen_view = function() {
    // Reset the overflow-y back to auto.
    document.documentElement.style.overflowY = 'auto';
    // Remove the fullscreen view.
    jQuery('#quizview').removeClass('fullscreen-quizview');
};

jazzquiz.execute_control_action = function(action) {
    // Prevent duplicate clicks
    // TODO: Find a better way to check if this is a direct action or not. Perhaps a class?
    if (action !== 'startimprovisequestion' && action !== 'startjumpquestion') {
        this.enable_controls([]);
    }
    // Execute action
    switch (action) {
        case 'repollquestion':
            this.repoll_question();
            break;
        case 'runvoting':
            this.run_voting();
            break;
        case 'startimprovisequestion':
            this.show_improvise_question_setup();
            break;
        case 'startjumpquestion':
            this.show_jump_question_setup();
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
document.addEventListener('keyup', function(e) {
    // Check if 'Escape' key was pressed
    if (e.keyCode === 27) {
        jazzquiz.close_fullscreen_view();
    }
});

jazzquiz.close_question_list_menu = function(e, name) {
    const menu_id = '#jazzquiz_' + name + '_menu';
    // Close the menu if the click was not inside.
    const menu = jQuery(e.target).closest(menu_id);
    if (!menu.length) {
        jQuery(menu_id).html('').removeClass('active');
        jQuery('#start' + name + 'question').removeClass('active');
    }
};

document.addEventListener('click', function(e) {
    jazzquiz.close_question_list_menu(e, 'improvise');
    jazzquiz.close_question_list_menu(e, 'jump');
    // Clicking a row to merge
    if (jazzquiz.state === 'reviewing') {
        if (e.target.classList.contains('bar')) {
            jazzquiz.start_response_merge(e.target.id);
        } else if (e.target.parentNode && e.target.parentNode.classList.contains('bar')) {
            jazzquiz.start_response_merge(e.target.parentNode.id);
        }
    }
});
