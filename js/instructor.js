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

jazzquiz.change_quiz_state = function (state) {

    jazzquiz.current_quiz_state = state;

    switch (state) {

        case 'notrunning':
            jazzquiz.control_buttons([
                'jumptoquestion',
                'closesession',
                'nextquestion',
                'startimprovisedquestion'
            ]);
            break;

        case 'running':
            jazzquiz.control_buttons([
                'endquestion',
                'toggleresponses',
                'togglenotresponded',
                'showfullscreenresults'
            ]);
            break;

        case 'endquestion':
            jazzquiz.control_buttons([]);
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
                'toggleresponses',
                'togglenotresponded'
            ];
            if (jazzquiz.get('lastquestion') != 'true') {
                enabled_buttons.push('nextquestion');
            }
            jazzquiz.control_buttons(enabled_buttons);
            break;

        case 'voting':
            jazzquiz.control_buttons([
                'closesession',
                'showfullscreenresults',
                'showcorrectanswer',
                'toggleresponses',
                'endquestion'
            ]);
            break;

        case 'sessionclosed':
        // Fall-through
        default:
            jazzquiz.control_buttons([
                // Intentionally left empty
            ]);
            break;
    }

};

/**
 * The instructor's getQuizInfo function
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
jazzquiz.getQuizInfo = function () {

    var params = {
        'sesskey': jazzquiz.get('sesskey'),
        'sessionid': jazzquiz.get('sessionid')
    };

    jazzquiz.ajax.create_request('/mod/jazzquiz/quizinfo.php', params, function (status, response) {

        if (status == 500) {
            alert('There was an error....' + response);
        } else if (status == 200) {

            jazzquiz.change_quiz_state(response.status);

            if (response.status == 'notrunning') {
                // do nothing as we're not running
                jazzquiz.set('endquestion', 'false');

            } else if (response.status == 'running' && jazzquiz.get('inquestion') != 'true' && jazzquiz.get('endquestion') != 'true') {

                if (response.delay <= 0) {
                    // only set in question if we're in it, not waiting for it to start
                    jazzquiz.set('inquestion', 'true');
                }

            } else if (response.status == 'running' && jazzquiz.get('inquestion') != 'true') {

                // set endquestion to false as we're now "waiting" for a new question
                jazzquiz.set('endquestion', 'false');

            } else if (response.status == 'running' && jazzquiz.get('inquestion') == 'true') {

                // gether the current results
                if (jazzquiz.get('delayrefreshresults') === 'undefined' || jazzquiz.get('delayrefreshresults') === 'false') {
                    jazzquiz.gather_current_results();
                }

                // also get the students/groups not responded
                if (jazzquiz.get('shownotresponded') !== false) {
                    jazzquiz.getnotresponded();
                }

            } else if (response.status == 'endquestion') {

                jazzquiz.set('inquestion', 'false');

            } else if (response.status == 'reviewing') {

                jazzquiz.set('inquestion', 'false');

            } else if (response.status == 'sessionclosed') {

                jazzquiz.set('inquestion', 'false');

            } else if (response.status == 'voting') {

                jazzquiz.get_and_show_vote_results();

            }
        }

        var time = 3000 + Math.floor(Math.random() * (100 + 100) - 100);
        setTimeout(jazzquiz.getQuizInfo, time);

    });

};

jazzquiz.create_response_bar_graph = function (responses, name, qtype, target_id) {
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
            row.classList.add('selected-vote-option');

            // TODO: Use classes instead of IDs for these elements. At the moment it's just easier to use an ID.

            var count_html = '<span id="' + name + '_count_' + row_i + '">' + responses[i].count + '</span>';

            var response_cell = row.insertCell(0);
            response_cell.onclick = function () {
                jQuery(this).parent().toggleClass('selected-vote-option');
            };

            var bar_cell = row.insertCell(1);
            bar_cell.id = name + '_bar_' + row_i;
            bar_cell.innerHTML = '<div style="width:' + percent + '%;">' + count_html + '</div>';

            if (qtype === 'stack') {

                response_cell.innerHTML = '<span id="' + name + '_latex_' + row_i + '"></span>';
                jazzquiz.render_maxima_equation(responses[i].response, row_i, name + '_latex_');

            } else {

                response_cell.innerHTML = responses[i].response;

            }

        } else {

            target.rows[current_row_index].dataset.percent = percent;

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

    // Update data
    jazzquiz.current_responses = [];
    jazzquiz.total_responses = responses.length;
    for (var i = 0; i < responses.length; i++) {

        var exists = false;

        // Check if response is a duplicate
        for (var j = 0; j < jazzquiz.current_responses.length; j++) {
            if (jazzquiz.current_responses[j].response === responses[i].response) {
                jazzquiz.current_responses[j].count++;
                exists = true;
                break;
            }
        }

        // Add element if not a duplicate
        if (!exists) {
            jazzquiz.current_responses.push({
                response: responses[i].response,
                count: 1,
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
    jazzquiz.create_response_bar_graph(jazzquiz.current_responses, 'current_response', qtype, 'wrapper_current_responses');
    jazzquiz.sort_response_bar_graph('wrapper_current_responses');

};


jazzquiz.start_quiz = function () {

    // make an ajax callback to quizdata to start the quiz

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

        // if there's only 1 question this will return true
        /*if (response.lastquestion == 'true') {
         // disable the next question button
         var nextquestionbtn = document.getElementById('nextquestion');
         nextquestionbtn.disabled = true;
         jazzquiz.set('lastquestion', 'true');
         }*/

        // jazzquiz.waitfor_question(response.questionid, response.questiontime, response.delay, response.nextstarttime);
    });

    var startquizbtn = document.getElementById('startquiz');
    startquizbtn.classList.add('btn-hide');
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

            if (status == 500) {
                var loadingbox = document.getElementById('loadingbox');
                loadingbox.classList.add('hidden');

                jazzquiz.quiz_info('There was an error with your request', true);
            } else if (status == 200) {

                var currentquestion = jazzquiz.get('currentquestion');
                var questiontimertext = document.getElementById('q' + currentquestion + '_questiontimetext');
                var questiontimer = document.getElementById('q' + currentquestion + '_questiontime');

                questiontimertext.innerHTML = '';
                questiontimer.innerHTML = '';

            }
        });

        setTimeout(jazzquiz.gather_results, 3500);
        setTimeout(jazzquiz.getnotresponded, 3500);

    });
};

jazzquiz.show_improvised_question_setup = function () {

    var params = {
        'action': 'listdummyquestions',
        'rtqid': jazzquiz.get('rtqid'),
        'sessionid': jazzquiz.get('sessionid'),
        'attemptid': jazzquiz.get('attemptid'),
        'sesskey': jazzquiz.get('sesskey')
    };

    jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status == '500') {
            jazzquiz.quiz_info('there was an error listing the improvised questions', true);
        } else if (status == 200) {

            var questions = JSON.parse(response.questions);

            var menu = jQuery('.improvise-menu');
            menu.html('').addClass('active');

            for (var i in questions) {

                // TODO: This is a bit ugly. Redo the onclick event.
                var html = '<button class="btn" ';
                html += 'onclick="';
                html += 'jazzquiz.chosen_improvisation_question = ' + questions[i].slot + ';';
                html += 'jazzquiz.start_improvised_question();';
                html += "jQuery('.improvise-menu').html('').removeClass('active');";
                html += '">' + questions[i].name + '</button>';
                menu.append(html);

            }

        }

    });

};

jazzquiz.start_improvised_question = function () {

    var qnum = jazzquiz.chosen_improvisation_question;

    jazzquiz.submit_goto_question(qnum, true);

};

jazzquiz.get_selected_answers_for_vote = function () {

    if (jazzquiz.current_responses === undefined) {
        return [];
    }

    var result = [];

    jQuery('.selected-vote-option').each(function (i, option) {
        var response = jazzquiz.current_responses[option.dataset.response_i];
        result.push({
            text: response.response,
            count: response.count
        });
    });

    return result;
};

jazzquiz.get_and_show_vote_results = function () {

    var params = {
        'action': 'getvoteresults',
        'rtqid': jazzquiz.get('rtqid'),
        'sessionid': jazzquiz.get('sessionid'),
        'attemptid': jazzquiz.get('attemptid'),
        'sesskey': jazzquiz.get('sesskey')
    };

    jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status == '500') {
            jazzquiz.quiz_info('there was an error getting the vote results', true);
        } else if (status == 200) {

            var answers = JSON.parse(response.answers);

            var target_id = 'wrapper_vote_responses';

            var responses = [];
            for (var i in answers) {
                responses.push({
                    response: answers[i].attempt,
                    count: answers[i].finalcount
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

            // TOOD: Not hardcode stack here...
            jazzquiz.create_response_bar_graph(responses, 'vote_response', 'stack', target_id);
            jazzquiz.sort_response_bar_graph(target_id);

        }

    });
};

jazzquiz.run_voting = function () {

    var vote_options = jazzquiz.get_selected_answers_for_vote();
    var questions_param = encodeURIComponent(JSON.stringify(vote_options));

    var params = {
        'action': 'runvoting',
        'rtqid': jazzquiz.get('rtqid'),
        'sessionid': jazzquiz.get('sessionid'),
        'attemptid': jazzquiz.get('attemptid'),
        'sesskey': jazzquiz.get('sesskey'),
        'questions': questions_param
    };

    jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status == '500') {
            jazzquiz.quiz_info('there was an error starting the vote', true);
        } else if (status == 200) {

            // Hide unnecessary information
            jazzquiz.clear_and_hide_notresponded();
            jazzquiz.hide_all_questionboxes();

        }

    });
};

/**
 * This function is slightly different than gather results as it doesn't look to alter the state of the quiz, or the interface
 * but just get the results of the quesiton and display them in the quiz info box
 *
 */
jazzquiz.gather_current_results = function () {


    if (jazzquiz.get('showstudentresponses') === false) {
        return; // return if there we aren't showing student responses
    }

    var params = {
        'action': 'getcurrentresults',
        'rtqid': jazzquiz.get('rtqid'),
        'sessionid': jazzquiz.get('sessionid'),
        'attemptid': jazzquiz.get('attemptid'),
        'sesskey': jazzquiz.get('sesskey')
    };

    jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status == '500') {
            jazzquiz.quiz_info('there was an error getting current results', true);
        } else if (status == 200) {

            jazzquiz.quiz_info_responses(response.responses, response.qtype);

            // after the responses have been inserted, we see if any question type javascript was added and evaluate
            if (document.getElementById(response.qtype + '_js') !== null) {
                eval(document.getElementById(response.qtype + '_js').innerHTML);
            }
        }

    });
};

/**
 * This function will call the normal getresults case of quiz data.  This alters the quiz state to "reviewing", as well as
 * updates the instructor's interface with the buttons allowed for this state of the quiz
 *
 */
jazzquiz.gather_results = function () {

    var params = {
        'action': 'getresults',
        'rtqid': jazzquiz.get('rtqid'),
        'sessionid': jazzquiz.get('sessionid'),
        'attemptid': jazzquiz.get('attemptid'),
        'sesskey': jazzquiz.get('sesskey')
    };

    jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        jazzquiz.loading('', 'hide');

        var questionbox = document.getElementById('q' + jazzquiz.get('currentquestion') + '_container');
        questionbox.classList.remove('hidden');

        // only put results into the screen if
        if (jazzquiz.get('showstudentresponses') !== false) {

            jazzquiz.clear_and_hide_qinfobox();

            jazzquiz.quiz_info_responses(response.responses, response.qtype);

            // after the responses have been inserted, we see if any question type javascript was added and evaluate
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

    this.hide_all_questionboxes();
    this.clear_and_hide_qinfobox();
    this.control_buttons([]);

    // we want to send a request to re-poll the previous question, or the one we're reviewing now
    var params = {
        'action': 'repollquestion',
        'rtqid': jazzquiz.get('rtqid'),
        'sessionid': jazzquiz.get('sessionid'),
        'attemptid': jazzquiz.get('attemptid'),
        'sesskey': jazzquiz.get('sesskey')
    };

    jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status == 500) {
            var loadingbox = document.getElementById('loadingbox');
            loadingbox.classList.add('hidden');

            jazzquiz.quiz_info('There was an error with your request', true);

            window.alert('there was an error with your request ... ');
            return;
        }

        if (response.lastquestion == 'true') {
            // set a var to signify this is the last question
            jazzquiz.set('lastquestion', 'true');
        } else {
            jazzquiz.set('lastquestion', 'false');
        }
        jazzquiz.waitfor_question(response.questionid, response.questiontime, response.delay, response.nextstarttime);
    });

};

jazzquiz.next_question = function () {

    // hide all question boxes and disable certain buttons

    this.hide_all_questionboxes();
    this.clear_and_hide_qinfobox();
    this.control_buttons([]);

    // ensure that the previous question's form is hidden
    if (jazzquiz.get('currentquestion') != 'undefined') {
        var qformbox = document.getElementById('q' + jazzquiz.get('currentquestion') + '_container');
        qformbox.classList.add('hidden');
    }

    var params = {
        'action': 'nextquestion',
        'rtqid': jazzquiz.get('rtqid'),
        'sessionid': jazzquiz.get('sessionid'),
        'attemptid': jazzquiz.get('attemptid'),
        'sesskey': jazzquiz.get('sesskey')
    };

    jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status == 500) {
            var loadingbox = document.getElementById('loadingbox');
            loadingbox.classList.add('hidden');

            jazzquiz.quiz_info('There was an error with your request', true);

            window.alert('there was an error with your request ... ');
            return;
        }

        if (response.lastquestion == 'true') {
            // set a var to signify this is the last question
            jazzquiz.set('lastquestion', 'true');
        } else {
            jazzquiz.set('lastquestion', 'false');
        }
        jazzquiz.waitfor_question(response.questionid, response.questiontime, response.delay, response.nextstarttime);
    });
};

jazzquiz.end_question = function () {

    // we want to send a request to re-poll the previous question, or the one we're reviewing now
    var params = {
        'action': 'endquestion',
        'question': jazzquiz.get('currentquestion'),
        'rtqid': jazzquiz.get('rtqid'),
        'sessionid': jazzquiz.get('sessionid'),
        'attemptid': jazzquiz.get('attemptid'),
        'sesskey': jazzquiz.get('sesskey')
    };

    jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status == 500) {
            var loadingbox = document.getElementById('loadingbox');
            loadingbox.classList.add('hidden');

            jazzquiz.quiz_info('There was an error with your request', true);

            window.alert('there was an error with your request ... ');
            return;
        }

        // clear the jazzquiz counter interval
        if (jazzquiz.qcounter) {
            clearInterval(jazzquiz.qcounter);
        }
        var currentquestion = jazzquiz.get('currentquestion');
        var questiontimertext = document.getElementById('q' + currentquestion + '_questiontimetext');
        var questiontimer = document.getElementById('q' + currentquestion + '_questiontime');

        questiontimertext.innerHTML = '';
        questiontimer.innerHTML = '';

        jazzquiz.set('inquestion', 'false'); // set inquestion to false as we've ended the question
        jazzquiz.set('endquestion', 'true');

        // after getting endquestion response, go through the normal handle_question flow
        jazzquiz.handle_question(jazzquiz.get('currentquestion'));
    });
};

jazzquiz.close_session = function () {

    jazzquiz.loading(M.util.get_string('closingsession', 'jazzquiz'), 'show');

    var params = {
        'action': 'closesession',
        'rtqid': jazzquiz.get('rtqid'),
        'sessionid': jazzquiz.get('sessionid'),
        'attemptid': jazzquiz.get('attemptid'),
        'sesskey': jazzquiz.get('sesskey')
    };

    jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status == 500) {
            var loadingbox = document.getElementById('loadingbox');
            loadingbox.classList.add('hidden');

            jazzquiz.quiz_info('There was an error with your request', true);

            window.alert('there was an error with your request ... ');
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
    this.control_buttons([]);

    // TODO: If two improvised in a row, make sure it still doesn't break the flow

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

    jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status == 500) {
            var loadingbox = document.getElementById('loadingbox');
            loadingbox.classList.add('hidden');

            jazzquiz.quiz_info('There was an error with your request', true);

            window.alert('there was an error with your request ... ');
            return;
        }

        if (response.lastquestion == 'true') {
            // set a var to signify this is the last question
            jazzquiz.set('lastquestion', 'true');
        } else {
            jazzquiz.set('lastquestion', 'false');
        }

        // reset location.hash to nothing so that the modal dialog disappears
        window.location.hash = '';

        // now go to the question
        jazzquiz.waitfor_question(response.questionid, response.questiontime, response.delay, response.nextstarttime);
    });

};

jazzquiz.jumpto_question = function () {

    if (window.location.hash === '#jumptoquestion-dialog') {
        // if the dialog is open, assume that we want to go to that the question in the select (as the x/close removes the hash and doesn't re-call this function)
        // it is only called on "go to question" button click when dialog is open

        var select = document.getElementById('jtq-selectquestion');
        var qnum = select.options[select.selectedIndex].value;

        jazzquiz.submit_goto_question(qnum, false);

    } else { // otherwise open the dialog
        window.location.hash = 'jumptoquestion-dialog';
    }

};

jazzquiz.show_correct_answer = function () {

    var hide = false;
    if (jazzquiz.get('showingcorrectanswer') != "undefined") {
        if (jazzquiz.get('showingcorrectanswer') == 'true') {
            hide = true;
        }
    }

    if (hide) {
        jazzquiz.quiz_info(null, '');
        // change button text
        var scaBtn = document.getElementById('showcorrectanswer');
        scaBtn.innerHTML = M.util.get_string('show_correct_answer', 'jazzquiz');
        jazzquiz.set('showingcorrectanswer', 'false');
        this.reload_results();
    } else {
        jazzquiz.loading(M.util.get_string('loading', 'jazzquiz'), 'show');

        var params = {
            'action': 'getrightresponse',
            'rtqid': jazzquiz.get('rtqid'),
            'sessionid': jazzquiz.get('sessionid'),
            'attemptid': jazzquiz.get('attemptid'),
            'sesskey': jazzquiz.get('sesskey')
        };

        // make sure we end the question (on end_question function call this is re-doing what we just did)
        // but handle_request is also called on ending of the question timer in core.js
        jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

            if (status == 500) {
                var loadingbox = document.getElementById('loadingbox');
                loadingbox.classList.add('hidden');

                jazzquiz.quiz_info('There was an error with your request', true);

                window.alert('there was an error with your request ... ');
                return;
            }

            jazzquiz.hide_all_questionboxes();
            jazzquiz.clear_and_hide_qinfobox();

            jazzquiz.quiz_info(response.rightanswer, true);
            jazzquiz.render_all_mathjax();

            // change button text
            var scaBtn = document.getElementById('showcorrectanswer');
            scaBtn.innerHTML = M.util.get_string('hide_correct_answer', 'jazzquiz');

            jazzquiz.set('showingcorrectanswer', 'true');

            jazzquiz.loading(null, 'hide');

        });
    }
};

/**
 * Toggles the "show student responses" variable
 */
jazzquiz.toggle_responses = function () {

    var toggleresponsesBtn = document.getElementById('toggleresponses');

    if (jazzquiz.get('showstudentresponses') === false) { // if it is false, set it back to true for the student responses to show

        toggleresponsesBtn.innerHTML = M.util.get_string('hidestudentresponses', 'jazzquiz');

        jazzquiz.set('showstudentresponses', true);
        jazzquiz.gather_current_results();
    } else { // if it's set to true, or not set at all, then set it to false when this button is clicked

        toggleresponsesBtn.innerHTML = M.util.get_string('showstudentresponses', 'jazzquiz');
        jazzquiz.set('showstudentresponses', false);
        jazzquiz.clear_and_hide_qinfobox();
    }
};


/**
 * Toggles the "show not responded" variable
 */
jazzquiz.toggle_notresponded = function () {

    var togglenotrespondedBtn = document.getElementById('togglenotresponded');

    if (jazzquiz.get('shownotresponded') === false) { // if it is false, set it back to true for the student responses to show

        togglenotrespondedBtn.innerHTML = M.util.get_string('hidenotresponded', 'jazzquiz');

        jazzquiz.set('shownotresponded', true);
        jazzquiz.getnotresponded();
    } else { // if it's set to true, or not set at all, then set it to false when this button is clicked

        togglenotrespondedBtn.innerHTML = M.util.get_string('shownotresponded', 'jazzquiz');
        jazzquiz.set('shownotresponded', false);
        jazzquiz.clear_and_hide_notresponded();
    }
};


jazzquiz.getnotresponded = function () {

    var params = {
        'action': 'getnotresponded',
        'rtqid': jazzquiz.get('rtqid'),
        'sessionid': jazzquiz.get('sessionid'),
        'attemptid': jazzquiz.get('attemptid'),
        'sesskey': jazzquiz.get('sesskey')
    };

    jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status == '500') {
            jazzquiz.not_responded_info('there was an error getting not responded students', true);
        } else if (status == 200) {
            jazzquiz.not_responded_info(response.notresponded, true);
        }

    });

};


/**
 * Function to automatically disable/enable buttons from the array passed.
 *
 * @param buttons An array of button ids to have enabled in the in quiz controls buttons
 */
jazzquiz.control_buttons = function (buttons) {

    var children = jQuery('#inquizcontrols .list-controls').children();

    for (var i = 0; i < children.length; i++) {
        var id = children[i].getAttribute("id");
        children[i].disabled = (buttons.indexOf(id) === -1);
    }
};


jazzquiz.not_responded_info = function (notresponded, clear) {

    var notrespondedbox = document.getElementById('notrespondedbox');

    // if clear, make the quizinfobox be empty
    if (clear) {
        notrespondedbox.innerHTML = '';
    }

    if (notresponded == null) {
        notresponded = '';
    }

    if (notresponded == '') {
        return; // return if there is nothing to display
    }

    if (typeof notresponded == 'object') {
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

// Listens for key event to remove the projector view container
document.addEventListener('keyup', function (e) {
    // Check if 'Escape' key was pressed
    if (e.keyCode == 27) {
        jazzquiz.close_fullscreen_results_view();
    }
});

// Listens for click events to hide the improvise menu when there is an outside click
document.addEventListener('click', function (e) {
    var menu = jQuery(e.target).closest('.improvise-menu');
    if (!menu.length) {
        jQuery('.improvise-menu').html('').removeClass('active');
    }
});
