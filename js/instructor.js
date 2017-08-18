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

// ensure that the namespace is defined
var jazzquiz = jazzquiz || {};
jazzquiz.vars = jazzquiz.vars || {};

/**
 * The instructor's quiz state change handler
 *
 * This function works to maintain instructor state as well as to assist in getting student responses
 * while the question is still running.  There are 2 variables that are set/get which are important
 *
 * "inquestion" signifies that the jazzquiz is in a question, and is updated in other functions to signify
 *              the end of a question
 * "endquestion" This variable is needed to help to keep the "inquestion" variable from being overwritten on the
 *               interval this function defines.  It is also updated by other functions in conjunction with "inquestion"
 *
 */
jazzquiz.change_quiz_state = function (state, data) {

    jazzquiz.is_new_state = (jazzquiz.current_quiz_state !== state);

    jazzquiz.current_quiz_state = state;

    if (jazzquiz.get('showstudentresponses') === 'undefined') {
        jazzquiz.set('showstudentresponses', false);
    }

    if (jazzquiz.get('shownotresponded') === 'undefined') {
        jazzquiz.set('showstudentresponses', false);
    }

    jazzquiz.show_controls();

    switch (state) {

        case 'notrunning':
            jazzquiz.control_buttons([]);
            jazzquiz.hide_controls();
            jazzquiz.set('endquestion', 'false');
            break;

        case 'preparing':
            jazzquiz.control_buttons([
                'startimprovisedquestion',
                'jumptoquestion',
                'nextquestion',
                'showfullscreenresults',
                'closesession'
            ]);
            document.getElementById('startquiz').classList.add('hidden');
            break;

        case 'running':

            jazzquiz.control_buttons([
                'endquestion',
                'toggleresponses',
                'togglenotresponded',
                'showfullscreenresults'
            ]);

            if (jazzquiz.get('inquestion') === 'true') {

                // Gather the current results
                if (jazzquiz.get('showstudentresponses') === true) {
                    if (jazzquiz.get('delayrefreshresults') === 'undefined' || jazzquiz.get('delayrefreshresults') === 'false') {
                        jazzquiz.gather_current_results();
                    }
                }

                // Also get the students/groups not responded
                if (jazzquiz.get('shownotresponded') !== false) {
                    jazzquiz.getnotresponded();
                }

            } else {

                if (jazzquiz.get('endquestion') !== 'true') {

                    if (data.delay <= 0) {
                        // Only set in question if we're in it, not waiting for it to start
                        jazzquiz.set('inquestion', 'true');
                    }

                } else {

                    // Set endquestion to false as we're now "waiting" for a new question
                    jazzquiz.set('endquestion', 'false');

                }
            }

            break;

        case 'endquestion':
            jazzquiz.control_buttons([]);
            jazzquiz.set('inquestion', 'false');
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
            if (jazzquiz.get('lastquestion') !== 'true') {
                enabled_buttons.push('nextquestion');
            }
            jazzquiz.control_buttons(enabled_buttons);

            // For now, just always show responses while reviewing
            // In the future, there should be an additional toggle.
            if (jazzquiz.is_new_state) {
                jazzquiz.set('showstudentresponses', true);
                jazzquiz.gather_current_results();
                jazzquiz.set('showstudentresponses', false);
            }

            // No longer in question
            jazzquiz.set('inquestion', 'false');

            break;

        case 'voting':
            jazzquiz.control_buttons([
                'closesession',
                'showfullscreenresults',
                'showcorrectanswer',
                'toggleresponses',
                'endquestion'
            ]);
            jazzquiz.get_and_show_vote_results();
            document.getElementById('startquiz').classList.add('hidden');
            break;

        case 'sessionclosed':
            jazzquiz.control_buttons([]);
            jazzquiz.set('inquestion', 'false');
            break;

        default:
            jazzquiz.control_buttons([]);
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
        jazzquiz.end_response_merge();
        return;
    }

    if (row.classList.contains('merge-from')) {

        var into_row = jQuery('.merge-into')[0];

        jazzquiz.current_responses[into_row.dataset.response_i].count += parseInt(row.dataset.count);
        jazzquiz.current_responses.splice(row.dataset.response_i, 1);

        jazzquiz.quiz_info_responses(jazzquiz.current_responses, jazzquiz.qtype);
        jazzquiz.end_response_merge();

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

jazzquiz.create_response_bar_graph = function (responses, name, target_id) {
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

            if (responses[i].qtype === 'stack') {

                response_cell.innerHTML = '<span id="' + name + '_latex_' + row_i + '"></span>';
                jazzquiz.render_maxima_equation(responses[i].response, row_i, name + '_latex_');

            } else {

                response_cell.innerHTML = responses[i].response;

            }

            var opt_cell = row.insertCell(2);
            opt_cell.classList.add('options');
            opt_cell.innerHTML = '<button class="btn btn-primary" onclick="jazzquiz.start_response_merge(\'' + bar_cell.id + '\')"><i class="fa fa-compress"></i></button>';

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

jazzquiz.quiz_info_responses = function (responses, qtype) {

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
    jazzquiz.current_responses = [];
    jazzquiz.total_responses = responses.length;
    jazzquiz.qtype = qtype;
    for (var i = 0; i < responses.length; i++) {

        var exists = false;

        var count = 1;
        if (responses[i].count !== undefined) {
            count = parseInt(responses[i].count);
        }

        // Check if response is a duplicate
        for (var j = 0; j < jazzquiz.current_responses.length; j++) {
            if (jazzquiz.current_responses[j].response === responses[i].response) {
                jazzquiz.current_responses[j].count += count;
                exists = true;
                break;
            }
        }

        // Add element if not a duplicate
        if (!exists) {
            jazzquiz.current_responses.push({
                response: responses[i].response,
                count: count,
                qtype: qtype
            });
        }
    }

    // Make sure quiz info has the wrapper for the responses
    var wrapper_current_responses = document.getElementById('wrapper_current_responses');
    if (wrapper_current_responses === null) {
        jazzquiz.quiz_info('<table id="wrapper_current_responses" class="jazzquiz-responses-overview"></table>', true);
        wrapper_current_responses = document.getElementById('wrapper_current_responses');

        // This should not happen, but check just in case quiz_info fails to set the html.
        if (wrapper_current_responses === null) {
            return;
        }
    }

    // Update HTML
    jazzquiz.create_response_bar_graph(jazzquiz.current_responses, 'current_response', 'wrapper_current_responses');
    jazzquiz.sort_response_bar_graph('wrapper_current_responses');

};


jazzquiz.start_quiz = function () {

    var params = {
        'action': 'startquiz',
        'rtqid': jazzquiz.get('rtqid'),
        'sessionid': jazzquiz.get('sessionid'),
        'attemptid': jazzquiz.get('attemptid'),
        'sesskey': jazzquiz.get('sesskey')
    };

    this.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {
        var inquizcontrols = document.getElementById('inquizcontrols');
        inquizcontrols.classList.remove('btn-hide');
    });

    document.getElementById('startquiz').classList.add('btn-hide');
    jazzquiz.hide_instructions();

};


jazzquiz.handle_question = function (questionid) {

    this.loading(M.util.get_string('gatheringresults', 'jazzquiz'), 'show');

    if (typeof tinyMCE !== 'undefined') {
        tinyMCE.triggerSave();
    }

    // will only work on Modern browsers
    // of course the problem child is always IE...
    var qform = document.forms.namedItem('q' + questionid);
    var formdata = new FormData(qform);

    formdata.append('action', 'savequestion');
    formdata.append('rtqid', jazzquiz.get('rtqid'));
    formdata.append('sessionid', jazzquiz.get('sessionid'));
    formdata.append('attemptid', jazzquiz.get('attemptid'));
    formdata.append('sesskey', jazzquiz.get('sesskey'));
    formdata.append('questionid', questionid);

    // submit the form
    jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', formdata, function (status, response) {

        if (status == 500) {
            window.alert('there was an error with your request ... ' + response.error);
            return;
        }
        //update the sequence check for the question
        var sequencecheck = document.getElementsByName(response.seqcheckname);
        var field = sequencecheck[0];
        field.value = response.seqcheckval;


        // we don't really care about the response for instructors as we're going to set timeout
        // for gathering response

        jazzquiz.set('endquestion', 'true');
        jazzquiz.set('inquestion', 'false');

        var params = {
            'action': 'endquestion',
            'question': jazzquiz.get('currentquestion'),
            'rtqid': jazzquiz.get('rtqid'),
            'sessionid': jazzquiz.get('sessionid'),
            'attemptid': jazzquiz.get('attemptid'),
            'sesskey': jazzquiz.get('sesskey')
        };

        // make sure we end the question (on end_question function call this is re-doing what we just did)
        // but handle_request is also called on ending of the question timer in core.js
        jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

            if (status === 500) {
                var loadingbox = document.getElementById('loadingbox');
                loadingbox.classList.add('hidden');

                jazzquiz.quiz_info('There was an error with your request', true);
            } else if (status === 200) {

                var currentquestion = jazzquiz.get('currentquestion');
                var questiontimertext = document.getElementById('q' + currentquestion + '_questiontimetext');
                var questiontimer = document.getElementById('q' + currentquestion + '_questiontime');

                questiontimertext.innerHTML = '';
                questiontimer.innerHTML = '';

            }
        });

        // TODO: remove
        setTimeout(jazzquiz.gather_results, 3500);
        setTimeout(jazzquiz.getnotresponded, 3500);

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
        'rtqid': jazzquiz.get('rtqid'),
        'sessionid': jazzquiz.get('sessionid'),
        'attemptid': jazzquiz.get('attemptid'),
        'sesskey': jazzquiz.get('sesskey')
    };

    // Send request
    jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

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
            html += 'jazzquiz.chosen_improvisation_question = ' + questions[i].slot + ';';
            html += 'jazzquiz.start_improvised_question();';
            html += "jQuery('.improvise-menu').html('').removeClass('active');";
            html += "jQuery('#startimprovisedquestion').removeClass('active').attr('data-isclosed', 'yes');";
            html += '">' + questions[i].name + '</button>';
            menu.append(html);

        }


    });

};

jazzquiz.start_improvised_question = function () {

    var qnum = jazzquiz.chosen_improvisation_question;

    jazzquiz.submit_goto_question(qnum, true);

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
        'rtqid': jazzquiz.get('rtqid'),
        'sessionid': jazzquiz.get('sessionid'),
        'attemptid': jazzquiz.get('attemptid'),
        'sesskey': jazzquiz.get('sesskey')
    };

    // Send request
    jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status !== HTTP_STATUS.OK) {
            jazzquiz.quiz_info('There was an error getting the vote results.', true);
            return;
        }

        var answers = JSON.parse(response.answers);

        var target_id = 'wrapper_vote_responses';

        var responses = [];
        for (var i in answers) {
            responses.push({
                response: answers[i].attempt,
                count: answers[i].finalcount,
                qtype: response.qtype
            });
        }

        var target = document.getElementById(target_id);
        if (target === null) {
            jazzquiz.quiz_info('<table id="' + target_id + '" class="jazzquiz-responses-overview"></table>', true);
            target = document.getElementById(target_id);

            // This should not happen, but check just in case quiz_info fails to set the html.
            if (target === null) {
                return;
            }
        }

        jazzquiz.create_response_bar_graph(responses, 'vote_response', target_id);
        jazzquiz.sort_response_bar_graph(target_id);


    });
};

jazzquiz.run_voting = function () {

    var vote_options = jazzquiz.get_selected_answers_for_vote();
    var questions_param = encodeURIComponent(JSON.stringify(vote_options));

    // Setup parameters
    var params = {
        'action': 'runvoting',
        'rtqid': jazzquiz.get('rtqid'),
        'sessionid': jazzquiz.get('sessionid'),
        'attemptid': jazzquiz.get('attemptid'),
        'sesskey': jazzquiz.get('sesskey'),
        'questions': questions_param,
        'qtype': jazzquiz.vars.questions[jazzquiz.get('currentquestion')].question.qtype
    };

    // Send request
    jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status !== HTTP_STATUS.OK) {
            jazzquiz.quiz_info('There was an error starting the vote.', true);
            return;
        }

        // Hide unnecessary information
        jazzquiz.clear_and_hide_notresponded();
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
    if (jazzquiz.get('showstudentresponses') == false) {
        return;
    }

    // Setup parameters
    var params = {
        'action': 'getcurrentresults',
        'rtqid': jazzquiz.get('rtqid'),
        'sessionid': jazzquiz.get('sessionid'),
        'attemptid': jazzquiz.get('attemptid'),
        'sesskey': jazzquiz.get('sesskey')
    };

    // Send request
    jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status !== HTTP_STATUS.OK) {

            // Something went wrong
            jazzquiz.quiz_info('there was an error getting current results', true);
            return;
        }

        // Set responses
        jazzquiz.quiz_info_responses(response.responses, response.qtype);

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
        'rtqid': jazzquiz.get('rtqid'),
        'sessionid': jazzquiz.get('sessionid'),
        'attemptid': jazzquiz.get('attemptid'),
        'sesskey': jazzquiz.get('sesskey')
    };

    // Send request
    jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        jazzquiz.loading('', 'hide');

        var questionbox = document.getElementById('q' + jazzquiz.get('currentquestion') + '_container');
        questionbox.classList.remove('hidden');

        // Only put results into the screen if
        if (jazzquiz.get('showstudentresponses') !== false) {

            jazzquiz.clear_and_hide_qinfobox();

            jazzquiz.quiz_info_responses(response.responses, response.qtype);

            // After the responses have been inserted, we see if any question type javascript was added and evaluate
            if (document.getElementById(response.qtype + '_js') !== null) {
                eval(document.getElementById(response.qtype + '_js').innerHTML);
            }
        }
    });

};

jazzquiz.reload_results = function () {

    this.hide_all_questionboxes();
    this.clear_and_hide_qinfobox();

    this.loading(M.util.get_string('gatheringresults', 'jazzquiz'), 'show');

    this.gather_results();
};

jazzquiz.repoll_question = function () {

    // Hide all question boxes
    this.hide_all_questionboxes();
    this.clear_and_hide_qinfobox();

    // Setup parameters
    var params = {
        'action': 'repollquestion',
        'rtqid': jazzquiz.get('rtqid'),
        'sessionid': jazzquiz.get('sessionid'),
        'attemptid': jazzquiz.get('attemptid'),
        'sesskey': jazzquiz.get('sesskey')
    };

    // Send request
    jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status !== HTTP_STATUS.OK) {
            var loadingbox = document.getElementById('loadingbox');
            loadingbox.classList.add('hidden');
            jazzquiz.quiz_info('There was an error with your request', true);
            return;
        }

        // TODO: Is lastquestion always guaranteed to be either true or false? If so, why not directly set it?
        if (response.lastquestion === 'true') {
            jazzquiz.set('lastquestion', 'true');
        } else {
            jazzquiz.set('lastquestion', 'false');
        }

        jazzquiz.waitfor_question(response.questionid, response.questiontime, response.delay, response.nextstarttime);
    });

};

jazzquiz.next_question = function () {

    // Hide all question boxes
    this.hide_all_questionboxes();
    this.clear_and_hide_qinfobox();

    // Ensure that the previous question's form is hidden
    if (jazzquiz.get('currentquestion') !== 'undefined') {
        var qformbox = document.getElementById('q' + jazzquiz.get('currentquestion') + '_container');
        qformbox.classList.add('hidden');
    }

    // Setup parameters
    var params = {
        'action': 'nextquestion',
        'rtqid': jazzquiz.get('rtqid'),
        'sessionid': jazzquiz.get('sessionid'),
        'attemptid': jazzquiz.get('attemptid'),
        'sesskey': jazzquiz.get('sesskey')
    };

    // Send request
    jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status !== HTTP_STATUS.OK) {
            var loadingbox = document.getElementById('loadingbox');
            loadingbox.classList.add('hidden');
            jazzquiz.quiz_info('There was an error with your request', true);
            return;
        }

        // TODO: Is lastquestion always guaranteed to be either true or false? If so, why not directly set it?
        if (response.lastquestion === 'true') {
            jazzquiz.set('lastquestion', 'true');
        } else {
            jazzquiz.set('lastquestion', 'false');
        }

        jazzquiz.waitfor_question(response.questionid, response.questiontime, response.delay, response.nextstarttime);
    });
};

jazzquiz.end_question = function () {

    // Setup parameters
    var params = {
        'action': 'endquestion',
        'question': jazzquiz.get('currentquestion'),
        'rtqid': jazzquiz.get('rtqid'),
        'sessionid': jazzquiz.get('sessionid'),
        'attemptid': jazzquiz.get('attemptid'),
        'sesskey': jazzquiz.get('sesskey')
    };

    var vote_callback = function (status, response) {
        if (status === 500) {
            console.log('Failed to end vote.');
        }
    };

    var callback = function (status, response) {

        if (status !== HTTP_STATUS.OK) {
            var loadingbox = document.getElementById('loadingbox');
            loadingbox.classList.add('hidden');
            jazzquiz.quiz_info('There was an error with your request', true);
            return;
        }

        // Clear the counter interval
        if (jazzquiz.qcounter) {
            clearInterval(jazzquiz.qcounter);
        }

        var currentquestion = jazzquiz.get('currentquestion');
        var questiontimertext = document.getElementById('q' + currentquestion + '_questiontimetext');
        var questiontimer = document.getElementById('q' + currentquestion + '_questiontime');

        questiontimertext.innerHTML = '';
        questiontimer.innerHTML = '';

        // Set inquestion to false as we've ended the question
        jazzquiz.set('inquestion', 'false');
        jazzquiz.set('endquestion', 'true');

        // After getting endquestion response, go through the normal handle_question flow
        jazzquiz.handle_question(jazzquiz.get('currentquestion'));

    };

    // Make sure we use the correct callback for the state.
    if (jazzquiz.current_quiz_state === 'voting') {
        callback = vote_callback;
    }

    // Send request
    jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, callback);

};

jazzquiz.close_session = function () {

    jazzquiz.loading(M.util.get_string('closingsession', 'jazzquiz'), 'show');

    // Setup parameters
    var params = {
        'action': 'closesession',
        'rtqid': jazzquiz.get('rtqid'),
        'sessionid': jazzquiz.get('sessionid'),
        'attemptid': jazzquiz.get('attemptid'),
        'sesskey': jazzquiz.get('sesskey')
    };

    // Send request
    jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status !== HTTP_STATUS.OK) {
            var loadingbox = document.getElementById('loadingbox');
            loadingbox.classList.add('hidden');
            jazzquiz.quiz_info('There was an error with your request', true);
            return;
        }

        jazzquiz.hide_all_questionboxes();
        jazzquiz.clear_and_hide_qinfobox();

        var controlsbox = document.getElementById('controlbox');
        controlsbox.classList.add('hidden');

        jazzquiz.quiz_info(M.util.get_string('sessionclosed', 'jazzquiz'));
        jazzquiz.loading(null, 'hide');

    });

};

// keep_flow: if true, the "next question" won't change.
jazzquiz.submit_goto_question = function (qnum, keep_flow) {

    this.hide_all_questionboxes();
    this.clear_and_hide_qinfobox();

    // TODO: If two improvised in a row, make sure it still doesn't break the flow

    // Setup parameters
    var params = {
        'action': 'gotoquestion',
        'qnum': qnum,
        'rtqid': jazzquiz.get('rtqid'),
        'sessionid': jazzquiz.get('sessionid'),
        'attemptid': jazzquiz.get('attemptid'),
        'sesskey': jazzquiz.get('sesskey')
    };

    if (keep_flow === true) {
        params['keepflow'] = 'true';
    }

    // Send request
    jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status !== HTTP_STATUS.OK) {
            var loadingbox = document.getElementById('loadingbox');
            loadingbox.classList.add('hidden');
            jazzquiz.quiz_info('There was an error with your request', true);
            return;
        }

        // TODO: Is lastquestion always guaranteed to be either true or false? If so, why not directly set it?
        if (response.lastquestion === 'true') {
            jazzquiz.set('lastquestion', 'true');
        } else {
            jazzquiz.set('lastquestion', 'false');
        }

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
        var qnum = select.options[select.selectedIndex].value;

        jazzquiz.submit_goto_question(qnum, false);

    } else {

        // Open the dialog
        window.location.hash = 'jumptoquestion-dialog';
    }

};

jazzquiz.show_correct_answer = function () {

    var hide = false;
    if (jazzquiz.get('showingcorrectanswer') !== "undefined") {
        if (jazzquiz.get('showingcorrectanswer') === 'true') {
            hide = true;
        }
    }

    if (hide) {

        // Make sure it's gone
        jazzquiz.quiz_info(null, '');

        // Change button icon
        jQuery('#showcorrectanswer').html('<i class="fa fa-eye"></i> Show answer');

        jazzquiz.set('showingcorrectanswer', 'false');
        this.reload_results();

        // We don't need to ask for the answer, so let's return.
        return;
    }

    jazzquiz.loading(M.util.get_string('loading', 'jazzquiz'), 'show');

    // Setup parameters
    var params = {
        'action': 'getrightresponse',
        'rtqid': jazzquiz.get('rtqid'),
        'sessionid': jazzquiz.get('sessionid'),
        'attemptid': jazzquiz.get('attemptid'),
        'sesskey': jazzquiz.get('sesskey')
    };

    // Make sure we end the question (on end_question function call this is re-doing what we just did)
    // handle_request is also called on ending of the question timer in core.js
    jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status !== HTTP_STATUS.OK) {
            var loadingbox = document.getElementById('loadingbox');
            loadingbox.classList.add('hidden');
            jazzquiz.quiz_info('There was an error with your request', true);
            return;
        }

        jazzquiz.hide_all_questionboxes();
        jazzquiz.clear_and_hide_qinfobox();

        jazzquiz.quiz_info(response.rightanswer, true);
        jazzquiz.render_all_mathjax();

        jQuery('#showcorrectanswer').html('<i class="fa fa-eye-slash"></i> Hide answer');

        jazzquiz.set('showingcorrectanswer', 'true');
        jazzquiz.loading(null, 'hide');

    });

};

/**
 * Hides the responses
 */
jazzquiz.hide_responses = function() {
    jazzquiz.set('showstudentresponses', false);
    jQuery('#toggleresponses').html('<i class="fa fa-square-o"></i> Responses');
    jazzquiz.clear_and_hide_qinfobox();
};

/**
 * Shows the responses
 */
jazzquiz.show_responses = function() {
    jazzquiz.set('showstudentresponses', true);
    jQuery('#toggleresponses').html('<i class="fa fa-check-square-o"></i> Responses');
    jazzquiz.gather_current_results();
};

/**
 * Toggle whether to show or hide the responses
 */
jazzquiz.toggle_responses = function() {
    if (jazzquiz.get('showstudentresponses') === true) {
        jazzquiz.hide_responses();
    } else {
        jazzquiz.show_responses();
    }
};

/**
 * Toggles the "show not responded" variable
 */
jazzquiz.toggle_notresponded = function () {

    var button = document.getElementById('togglenotresponded');

    if (jazzquiz.get('shownotresponded') == false) {

        // St it back to true for the student responses to show
        //button.innerHTML = M.util.get_string('hidenotresponded', 'jazzquiz');
        jQuery('#togglenotresponded').html('<i class="fa fa-check-square-o"></i> Not responded');

        jazzquiz.set('shownotresponded', true);
        jazzquiz.getnotresponded();

    } else {

        // Set it to false when this button is clicked
        //button.innerHTML = M.util.get_string('shownotresponded', 'jazzquiz');
        jQuery('#togglenotresponded').html('<i class="fa fa-square-o"></i> Not responded');

        jazzquiz.set('shownotresponded', false);
        jazzquiz.clear_and_hide_notresponded();
    }
};


jazzquiz.getnotresponded = function () {

    // Setup parametrs
    var params = {
        'action': 'getnotresponded',
        'rtqid': jazzquiz.get('rtqid'),
        'sessionid': jazzquiz.get('sessionid'),
        'attemptid': jazzquiz.get('attemptid'),
        'sesskey': jazzquiz.get('sesskey')
    };

    // Send request
    jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status !== HTTP_STATUS.OK) {
            jazzquiz.not_responded_info('There was an error getting not responded students', true);
            return;
        }

        jazzquiz.not_responded_info(response.notresponded, true);

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
        console.log('jQuery not loaded. ' + jazzquiz.current_quiz_state + ': Failed to activate the following buttons:');
        console.log(buttons);
        return;
    }

    // Let's find the direct child nodes.
    var children = jQuery('#inquizcontrols .quiz-control-buttons').children();

    // Disable all the buttons that are not present in the "buttons" parameter.
    for (var i = 0; i < children.length; i++) {
        var id = children[i].getAttribute("id");
        children[i].disabled = (buttons.indexOf(id) === -1);
    }

};

jazzquiz.hide_controls = function () {
    var inquizcontrols = document.getElementById('inquizcontrols');
    var controls = inquizcontrols.getElementsByClassName('quiz-control-buttons');
    controls[0].classList.add('hidden');
};

jazzquiz.show_controls = function () {
    var inquizcontrols = document.getElementById('inquizcontrols');
    var controls = inquizcontrols.getElementsByClassName('quiz-control-buttons');
    controls[0].classList.remove('hidden');
};


jazzquiz.not_responded_info = function (notresponded, clear) {

    var notrespondedbox = document.getElementById('notrespondedbox');

    // if clear, make the quizinfobox be empty
    if (clear) {
        notrespondedbox.innerHTML = '';
    }

    if (notresponded === null) {
        notresponded = '';
    }

    if (notresponded === '') {
        return; // return if there is nothing to display
    }

    if (typeof notresponded === 'object') {
        notrespondedbox.appendChild(notresponded);
    } else {
        notrespondedbox.innerHTML = notresponded;
    }

    // if it's hidden remove the hidden class
    if (notrespondedbox.classList.contains('hidden')) {
        notrespondedbox.classList.remove('hidden');
    }
};

jazzquiz.clear_and_hide_notresponded = function () {

    var notrespondedbox = document.getElementById('notrespondedbox');

    notrespondedbox.innerHTML = '';

    if (!notrespondedbox.classList.contains('hidden')) {
        notrespondedbox.classList.add('hidden');
    }

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
    container.innerHTML = jazzquiz.get_question_body_formatted(jazzquiz.get('currentquestion'));

    // Do we want to show the results?
    var show_results = (jazzquiz.current_quiz_state !== 'running');

    if (show_results) {

        // Add bar graph
        var quizinfobox = document.getElementById('quizinfobox');
        if (quizinfobox !== null && quizinfobox.children.length > 0) {
            // NOTE: Always assumes first child of quizinfobox is a table
            container.innerHTML += '<table class="jazzquiz-responses-overview">' + quizinfobox.children[0].innerHTML + '</table>';
        }

    }

    // Let's update the view every second
    if (jazzquiz.fullscreen_interval_handle === undefined) {
        jazzquiz.fullscreen_interval_handle = setInterval(function () {
            jazzquiz.show_fullscreen_results_view();
        }, 1000);
    }
};

// Checks if the view currently exists, and removes it if so.
jazzquiz.close_fullscreen_results_view = function () {

    // Stop the interval
    clearInterval(jazzquiz.fullscreen_interval_handle);
    jazzquiz.fullscreen_interval_handle = undefined;

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
        jazzquiz.control_buttons([]);
    }

    // Execute action
    switch (action) {
        case 'repollquestion':
            jazzquiz.repoll_question();
            break;
        case 'runvoting':
            jazzquiz.run_voting();
            break;
        case 'startimprovisedquestion':
            jazzquiz.show_improvised_question_setup();
            break;
        case 'jumptoquestion':
            jazzquiz.jumpto_question();
            break;
        case 'nextquestion':
            jazzquiz.next_question();
            break;
        case 'endquestion':
            jazzquiz.end_question();
            break;
        case 'showfullscreenresults':
            jazzquiz.show_fullscreen_results_view();
            break;
        case 'showcorrectanswer':
            jazzquiz.show_correct_answer();
            break;
        case 'toggleresponses':
            jazzquiz.toggle_responses();
            break;
        case 'togglenotresponded':
            jazzquiz.toggle_notresponded();
            break;
        case 'closesession':
            jazzquiz.close_session();
            break;
        case 'startquiz':
            jazzquiz.start_quiz();
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
    if (e.target.classList.contains('bar')) {
        jazzquiz.start_response_merge(e.target.id);
    } else if (e.target.parentNode.classList.contains('bar')) {
        jazzquiz.start_response_merge(e.target.parentNode.id);
    }

});
