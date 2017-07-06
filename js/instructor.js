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
 * @package   mod_activequiz
 * @author    John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright 2014 University of Wisconsin - Madison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// ensure that the namespace is defined
var activequiz = activequiz || {};
activequiz.vars = activequiz.vars || {};

activequiz.change_quiz_state = function(state) {

    activequiz.current_quiz_state = state;

    switch (state) {

        case 'notrunning':
            activequiz.control_buttons([
                'jumptoquestion',
                'closesession',
                'nextquestion',
                'startimprovisedquestion'
            ]);
            break;

        case 'running':
            activequiz.control_buttons([
                'endquestion',
                'toggleresponses',
                'togglenotresponded',
                'showfullscreenresults'
            ]);
            break;

        case 'endquestion':
            activequiz.control_buttons([

            ]);
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
            if (activequiz.get('lastquestion') != 'true') {
                enabled_buttons.push('nextquestion');
            }
            activequiz.control_buttons(enabled_buttons);
            break;

        case 'voting':
            activequiz.control_buttons([
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
            activequiz.control_buttons([
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
 * "inquestion" signifies that the activequiz is in a question, and is updated in other functions to signify
 *              the end of a question
 * "endquestion" This variable is needed to help to keep the "inquestion" variable from being overwritten on the
 *               interval this function defines.  It is also updated by other functions in conjunction with "inquestion"
 *
 */
activequiz.getQuizInfo = function () {

    var params = {
        'sesskey': activequiz.get('sesskey'),
        'sessionid': activequiz.get('sessionid')
    };

    activequiz.ajax.create_request('/mod/activequiz/quizinfo.php', params, function (status, response) {

        if (status == 500) {
            alert('There was an error....' + response);
        } else if (status == 200) {

            activequiz.change_quiz_state(response.status);

            if (response.status == 'notrunning') {
                // do nothing as we're not running
                activequiz.set('endquestion', 'false');

            } else if (response.status == 'running' && activequiz.get('inquestion') != 'true' && activequiz.get('endquestion') != 'true') {

                if (response.delay <= 0) {
                    // only set in question if we're in it, not waiting for it to start
                    activequiz.set('inquestion', 'true');
                }

            } else if (response.status == 'running' && activequiz.get('inquestion') != 'true') {

                // set endquestion to false as we're now "waiting" for a new question
                activequiz.set('endquestion', 'false');

            } else if (response.status == 'running' && activequiz.get('inquestion') == 'true') {

                // gether the current results
                if (activequiz.get('delayrefreshresults') === 'undefined' || activequiz.get('delayrefreshresults') === 'false') {
                    activequiz.gather_current_results();
                }

                // also get the students/groups not responded
                if (activequiz.get('shownotresponded') !== false) {
                    activequiz.getnotresponded();
                }

            } else if (response.status == 'endquestion') {

                activequiz.set('inquestion', 'false');

            } else if (response.status == 'reviewing') {

                activequiz.set('inquestion', 'false');

            } else if (response.status == 'sessionclosed') {

                activequiz.set('inquestion', 'false');

            } else if (response.status == 'voting') {

                activequiz.get_and_show_vote_results();

            }
        }

        var time = 3000 + Math.floor(Math.random() * (100 + 100) - 100);
        setTimeout(activequiz.getQuizInfo, time);

    });

};

activequiz.create_response_bar_graph = function (responses, name, qtype, target_id) {
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
            row.dataset.response = responses[i].response;
            row.dataset.percent = percent;
            row.dataset.row_i = row_i;

            // TODO: Use classes instead of IDs for these elements. At the moment it's just easier to use an ID.

            var count_html = '<span id="' + name + '_count_' + row_i + '">' + responses[i].count + '</span>';

            var response_cell = row.insertCell(0);

            var bar_cell = row.insertCell(1);
            bar_cell.id = name + '_bar_' + row_i;
            bar_cell.innerHTML = '<div style="width:' + percent + '%;">' + count_html + '</div>';

            if (qtype === 'stack') {

                response_cell.innerHTML = '<span id="' + name + '_latex_' + row_i + '"></span>';
                activequiz.render_maxima_equation(responses[i].response, row_i, name + '_latex_');

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

activequiz.sort_response_bar_graph = function(target_id) {
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

activequiz.quiz_info_responses = function (responses, qtype) {

    if (responses === undefined) {
        console.log('Responses is undefined.');
        return;
    }

    // Check if any responses to show
    if (responses.length === 0) {
        return;
    }

    // Update data
    activequiz.current_responses = [];
    activequiz.total_responses = responses.length;
    for (var i = 0; i < responses.length; i++) {

        var exists = false;

        // Check if response is a duplicate
        for (var j = 0; j < activequiz.current_responses.length; j++) {
            if (activequiz.current_responses[j].response === responses[i].response) {
                activequiz.current_responses[j].count++;
                exists = true;
                break;
            }
        }

        // Add element if not a duplicate
        if (!exists) {
            activequiz.current_responses.push({
                response: responses[i].response,
                count: 1,
                qtype: qtype
            });
        }
    }

    // Make sure quiz info has the wrapper for the responses
    var wrapper_current_responses = document.getElementById('wrapper_current_responses');
    if (wrapper_current_responses === null) {
        activequiz.quiz_info('<table id="wrapper_current_responses" class="activequiz-responses-overview"></table>', true);
        wrapper_current_responses = document.getElementById('wrapper_current_responses');

        // This should not happen, but check just in case quiz_info fails to set the html.
        if (wrapper_current_responses === null) {
            return;
        }
    }

    // Update HTML
    activequiz.create_response_bar_graph(activequiz.current_responses, 'current_response', qtype, 'wrapper_current_responses');
    activequiz.sort_response_bar_graph('wrapper_current_responses');

};


activequiz.start_quiz = function () {

    // make an ajax callback to quizdata to start the quiz

    var params = {
        'action': 'startquiz',
        'rtqid': activequiz.get('rtqid'),
        'sessionid': activequiz.get('sessionid'),
        'attemptid': activequiz.get('attemptid'),
        'sesskey': activequiz.get('sesskey')
    };

    this.ajax.create_request('/mod/activequiz/quizdata.php', params, function (status, response) {

        var inquizcontrols = document.getElementById('inquizcontrols');
        inquizcontrols.classList.remove('btn-hide');

        // if there's only 1 question this will return true
        /*if (response.lastquestion == 'true') {
            // disable the next question button
            var nextquestionbtn = document.getElementById('nextquestion');
            nextquestionbtn.disabled = true;
            activequiz.set('lastquestion', 'true');
        }*/

       // activequiz.waitfor_question(response.questionid, response.questiontime, response.delay, response.nextstarttime);
    });

    var startquizbtn = document.getElementById('startquiz');
    startquizbtn.classList.add('btn-hide');
};


activequiz.handle_question = function (questionid) {

    this.loading(M.util.get_string('gatheringresults', 'activequiz'), 'show');

    if (typeof tinyMCE !== 'undefined') {
        tinyMCE.triggerSave();
    }

    // will only work on Modern browsers
    // of course the problem child is always IE...
    var qform = document.forms.namedItem('q' + questionid);
    var formdata = new FormData(qform);

    formdata.append('action', 'savequestion');
    formdata.append('rtqid', activequiz.get('rtqid'));
    formdata.append('sessionid', activequiz.get('sessionid'));
    formdata.append('attemptid', activequiz.get('attemptid'));
    formdata.append('sesskey', activequiz.get('sesskey'));
    formdata.append('questionid', questionid);

    // submit the form
    activequiz.ajax.create_request('/mod/activequiz/quizdata.php', formdata, function (status, response) {

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

        activequiz.set('endquestion', 'true');
        activequiz.set('inquestion', 'false');

        var params = {
            'action': 'endquestion',
            'question': activequiz.get('currentquestion'),
            'rtqid': activequiz.get('rtqid'),
            'sessionid': activequiz.get('sessionid'),
            'attemptid': activequiz.get('attemptid'),
            'sesskey': activequiz.get('sesskey')
        };

        // make sure we end the question (on end_question function call this is re-doing what we just did)
        // but handle_request is also called on ending of the question timer in core.js
        activequiz.ajax.create_request('/mod/activequiz/quizdata.php', params, function (status, response) {

            if (status == 500) {
                var loadingbox = document.getElementById('loadingbox');
                loadingbox.classList.add('hidden');

                activequiz.quiz_info('There was an error with your request', true);
            } else if (status == 200) {

                var currentquestion = activequiz.get('currentquestion');
                var questiontimertext = document.getElementById('q' + currentquestion + '_questiontimetext');
                var questiontimer = document.getElementById('q' + currentquestion + '_questiontime');

                questiontimertext.innerHTML = '';
                questiontimer.innerHTML = '';

            }
        });

        setTimeout(activequiz.gather_results, 3500);
        setTimeout(activequiz.getnotresponded, 3500);

    });
};

activequiz.show_improvised_question_setup = function() {

    var params = {
        'action': 'listdummyquestions',
        'rtqid': activequiz.get('rtqid'),
        'sessionid': activequiz.get('sessionid'),
        'attemptid': activequiz.get('attemptid'),
        'sesskey': activequiz.get('sesskey')
    };

    activequiz.ajax.create_request('/mod/activequiz/quizdata.php', params, function (status, response) {

        if (status == '500') {
            activequiz.quiz_info('there was an error listing the improvised questions', true);
        } else if (status == 200) {

            var questions = JSON.parse(response.questions);

            var html = '';

            // TODO: Submit button for each option instead of radio buttons

            for (var i in questions) {
                if (activequiz.chosen_improvisation_question === undefined) {
                    activequiz.chosen_improvisation_question = questions[i].slot;
                }
                html += '<label>';
                html += '<input type="radio" name="chosenimprov" value="' + questions[i].slot + '" onclick="activequiz.chosen_improvisation_question = this.value;"> ';
                html += questions[i].name;
                html += '</label><br>';
            }

            html += '<hr>';
            html += '<button onclick="activequiz.start_improvised_question();">Start improvised question</button>';

            activequiz.quiz_info(html);

        }

    });

};

activequiz.start_improvised_question = function() {

    var qnum = activequiz.chosen_improvisation_question;

    activequiz.submit_goto_question(qnum, true);

};

activequiz.get_selected_answers_for_vote = function() {

    if (activequiz.current_responses === undefined) {
        return [];
    }

    var result = [];

    jQuery('.selected-vote-option').each(function(i, option) {
        var response = activequiz.current_responses[option.dataset.response];
        result.push({
            text: response.response,
            count: response.count
        });
    });

    return result;

    /*

    // At the moment, just add all the attempts to the vote:
    if (activequiz.current_responses === undefined) {
        return [];
    }
    var result = [];
    for (var i = 0; i < activequiz.current_responses.length; i++) {
        result.push({ text: activequiz.current_responses[i].response, count: activequiz.current_responses[i].count });
    }
    return result;*/
};

activequiz.get_and_show_vote_results = function() {

    var params = {
        'action': 'getvoteresults',
        'rtqid': activequiz.get('rtqid'),
        'sessionid': activequiz.get('sessionid'),
        'attemptid': activequiz.get('attemptid'),
        'sesskey': activequiz.get('sesskey')
    };

    activequiz.ajax.create_request('/mod/activequiz/quizdata.php', params, function (status, response) {

        if (status == '500') {
            activequiz.quiz_info('there was an error getting the vote results', true);
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
                activequiz.quiz_info('<table id="' + target_id + '" class="activequiz-responses-overview"></table>', true);
                target = document.getElementById(target_id);

                // This should not happen, but check just in case quiz_info fails to set the html.
                if (target === null) {
                    return;
                }
            }

            // TOOD: Not hardcode stack here...
            activequiz.create_response_bar_graph(responses, 'vote_response', 'stack', target_id);
            activequiz.sort_response_bar_graph(target_id);

        }

    });
};

activequiz.run_voting = function () {

    var vote_options = activequiz.get_selected_answers_for_vote();
    var questions_param = encodeURIComponent(JSON.stringify(vote_options));

    var params = {
        'action': 'runvoting',
        'rtqid': activequiz.get('rtqid'),
        'sessionid': activequiz.get('sessionid'),
        'attemptid': activequiz.get('attemptid'),
        'sesskey': activequiz.get('sesskey'),
        'questions': questions_param
    };

    activequiz.ajax.create_request('/mod/activequiz/quizdata.php', params, function (status, response) {

        if (status == '500') {
            activequiz.quiz_info('there was an error starting the vote', true);
        } else if (status == 200) {

            // Hide unnecessary information
            activequiz.clear_and_hide_notresponded();
            activequiz.hide_all_questionboxes();

        }

    });
};

/**
 * This function is slightly different than gather results as it doesn't look to alter the state of the quiz, or the interface
 * but just get the results of the quesiton and display them in the quiz info box
 *
 */
activequiz.gather_current_results = function () {


    if (activequiz.get('showstudentresponses') === false) {
        return; // return if there we aren't showing student responses
    }

    var params = {
        'action': 'getcurrentresults',
        'rtqid': activequiz.get('rtqid'),
        'sessionid': activequiz.get('sessionid'),
        'attemptid': activequiz.get('attemptid'),
        'sesskey': activequiz.get('sesskey')
    };

    activequiz.ajax.create_request('/mod/activequiz/quizdata.php', params, function (status, response) {

        if (status == '500') {
            activequiz.quiz_info('there was an error getting current results', true);
        } else if (status == 200) {

            activequiz.quiz_info_responses(response.responses, response.qtype);

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
activequiz.gather_results = function () {

    var params = {
        'action': 'getresults',
        'rtqid': activequiz.get('rtqid'),
        'sessionid': activequiz.get('sessionid'),
        'attemptid': activequiz.get('attemptid'),
        'sesskey': activequiz.get('sesskey'),
    };

    activequiz.ajax.create_request('/mod/activequiz/quizdata.php', params, function (status, response) {

        activequiz.loading('', 'hide');

        var questionbox = document.getElementById('q' + activequiz.get('currentquestion') + '_container');
        questionbox.classList.remove('hidden');

        // only put results into the screen if
        if (activequiz.get('showstudentresponses') !== false) {

            activequiz.quiz_info_responses(response.responses, response.qtype);

            // after the responses have been inserted, we see if any question type javascript was added and evaluate
            if (document.getElementById(response.qtype + '_js') !== null) {
                eval(document.getElementById(response.qtype + '_js').innerHTML);
            }
        }
    });

};

activequiz.reload_results = function () {

    this.hide_all_questionboxes();
    this.clear_and_hide_qinfobox();

    this.loading(M.util.get_string('gatheringresults', 'activequiz'), 'show');

    this.gather_results();
};

activequiz.repoll_question = function () {

    this.hide_all_questionboxes();
    this.clear_and_hide_qinfobox();
    this.control_buttons([]);

    // we want to send a request to re-poll the previous question, or the one we're reviewing now
    var params = {
        'action': 'repollquestion',
        'rtqid': activequiz.get('rtqid'),
        'sessionid': activequiz.get('sessionid'),
        'attemptid': activequiz.get('attemptid'),
        'sesskey': activequiz.get('sesskey')
    };

    activequiz.ajax.create_request('/mod/activequiz/quizdata.php', params, function (status, response) {

        if (status == 500) {
            var loadingbox = document.getElementById('loadingbox');
            loadingbox.classList.add('hidden');

            activequiz.quiz_info('There was an error with your request', true);

            window.alert('there was an error with your request ... ');
            return;
        }

        if (response.lastquestion == 'true') {
            // set a var to signify this is the last question
            activequiz.set('lastquestion', 'true');
        } else {
            activequiz.set('lastquestion', 'false');
        }
        activequiz.waitfor_question(response.questionid, response.questiontime, response.delay, response.nextstarttime);
    });

};

activequiz.next_question = function () {

    // hide all question boxes and disable certain buttons

    this.hide_all_questionboxes();
    this.clear_and_hide_qinfobox();
    this.control_buttons([]);

    // ensure that the previous question's form is hidden
    if (activequiz.get('currentquestion') != 'undefined') {
        var qformbox = document.getElementById('q' + activequiz.get('currentquestion') + '_container');
        qformbox.classList.add('hidden');
    }

    var params = {
        'action': 'nextquestion',
        'rtqid': activequiz.get('rtqid'),
        'sessionid': activequiz.get('sessionid'),
        'attemptid': activequiz.get('attemptid'),
        'sesskey': activequiz.get('sesskey')
    };

    activequiz.ajax.create_request('/mod/activequiz/quizdata.php', params, function (status, response) {

        if (status == 500) {
            var loadingbox = document.getElementById('loadingbox');
            loadingbox.classList.add('hidden');

            activequiz.quiz_info('There was an error with your request', true);

            window.alert('there was an error with your request ... ');
            return;
        }

        if (response.lastquestion == 'true') {
            // set a var to signify this is the last question
            activequiz.set('lastquestion', 'true');
        } else {
            activequiz.set('lastquestion', 'false');
        }
        activequiz.waitfor_question(response.questionid, response.questiontime, response.delay, response.nextstarttime);
    });
};

activequiz.end_question = function () {

    // we want to send a request to re-poll the previous question, or the one we're reviewing now
    var params = {
        'action': 'endquestion',
        'question': activequiz.get('currentquestion'),
        'rtqid': activequiz.get('rtqid'),
        'sessionid': activequiz.get('sessionid'),
        'attemptid': activequiz.get('attemptid'),
        'sesskey': activequiz.get('sesskey')
    };

    activequiz.ajax.create_request('/mod/activequiz/quizdata.php', params, function (status, response) {

        if (status == 500) {
            var loadingbox = document.getElementById('loadingbox');
            loadingbox.classList.add('hidden');

            activequiz.quiz_info('There was an error with your request', true);

            window.alert('there was an error with your request ... ');
            return;
        }

        // clear the activequiz counter interval
        if (activequiz.qcounter) {
            clearInterval(activequiz.qcounter);
        }
        var currentquestion = activequiz.get('currentquestion');
        var questiontimertext = document.getElementById('q' + currentquestion + '_questiontimetext');
        var questiontimer = document.getElementById('q' + currentquestion + '_questiontime');

        questiontimertext.innerHTML = '';
        questiontimer.innerHTML = '';

        activequiz.set('inquestion', 'false'); // set inquestion to false as we've ended the question
        activequiz.set('endquestion', 'true');

        // after getting endquestion response, go through the normal handle_question flow
        activequiz.handle_question(activequiz.get('currentquestion'));
    });
};

activequiz.close_session = function () {

    activequiz.loading(M.util.get_string('closingsession', 'activequiz'), 'show');

    var params = {
        'action': 'closesession',
        'rtqid': activequiz.get('rtqid'),
        'sessionid': activequiz.get('sessionid'),
        'attemptid': activequiz.get('attemptid'),
        'sesskey': activequiz.get('sesskey')
    };

    activequiz.ajax.create_request('/mod/activequiz/quizdata.php', params, function (status, response) {

        if (status == 500) {
            var loadingbox = document.getElementById('loadingbox');
            loadingbox.classList.add('hidden');

            activequiz.quiz_info('There was an error with your request', true);

            window.alert('there was an error with your request ... ');
            return;
        }

        activequiz.hide_all_questionboxes();
        activequiz.clear_and_hide_qinfobox();

        var controlsbox = document.getElementById('controlbox');
        controlsbox.classList.add('hidden');

        activequiz.quiz_info(M.util.get_string('sessionclosed', 'activequiz'));
        activequiz.loading(null, 'hide');

    });


};

// keep_flow: if true, the "next question" won't change.
activequiz.submit_goto_question = function(qnum, keep_flow) {

    this.hide_all_questionboxes();
    this.clear_and_hide_qinfobox();
    this.control_buttons([]);

    // TODO: If two improvised in a row, make sure it still doesn't break the flow

    var params = {
        'action': 'gotoquestion',
        'qnum': qnum,
        'rtqid': activequiz.get('rtqid'),
        'sessionid': activequiz.get('sessionid'),
        'attemptid': activequiz.get('attemptid'),
        'sesskey': activequiz.get('sesskey')
    };

    if (keep_flow === true) {
        params['keepflow'] = 'true';
    }

    activequiz.ajax.create_request('/mod/activequiz/quizdata.php', params, function (status, response) {

        if (status == 500) {
            var loadingbox = document.getElementById('loadingbox');
            loadingbox.classList.add('hidden');

            activequiz.quiz_info('There was an error with your request', true);

            window.alert('there was an error with your request ... ');
            return;
        }

        if (response.lastquestion == 'true') {
            // set a var to signify this is the last question
            activequiz.set('lastquestion', 'true');
        } else {
            activequiz.set('lastquestion', 'false');
        }

        // reset location.hash to nothing so that the modal dialog disappears
        window.location.hash = '';

        // now go to the question
        activequiz.waitfor_question(response.questionid, response.questiontime, response.delay, response.nextstarttime);
    });

};

activequiz.jumpto_question = function () {

    if (window.location.hash === '#jumptoquestion-dialog') {
        // if the dialog is open, assume that we want to go to that the question in the select (as the x/close removes the hash and doesn't re-call this function)
        // it is only called on "go to question" button click when dialog is open

        var select = document.getElementById('jtq-selectquestion');
        var qnum = select.options[select.selectedIndex].value;

        activequiz.submit_goto_question(qnum, false);

    } else { // otherwise open the dialog
        window.location.hash = 'jumptoquestion-dialog';
    }

};

activequiz.show_correct_answer = function () {

    var hide = false;
    if (activequiz.get('showingcorrectanswer') != "undefined") {
        if (activequiz.get('showingcorrectanswer') == 'true') {
            hide = true;
        }
    }

    if (hide) {
        activequiz.quiz_info(null, '');
        // change button text
        var scaBtn = document.getElementById('showcorrectanswer');
        scaBtn.innerHTML = M.util.get_string('show_correct_answer', 'activequiz');
        activequiz.set('showingcorrectanswer', 'false');
        this.reload_results();
    } else {
        activequiz.loading(M.util.get_string('loading', 'activequiz'), 'show');

        var params = {
            'action': 'getrightresponse',
            'rtqid': activequiz.get('rtqid'),
            'sessionid': activequiz.get('sessionid'),
            'attemptid': activequiz.get('attemptid'),
            'sesskey': activequiz.get('sesskey')
        };

        // make sure we end the question (on end_question function call this is re-doing what we just did)
        // but handle_request is also called on ending of the question timer in core.js
        activequiz.ajax.create_request('/mod/activequiz/quizdata.php', params, function (status, response) {

            if (status == 500) {
                var loadingbox = document.getElementById('loadingbox');
                loadingbox.classList.add('hidden');

                activequiz.quiz_info('There was an error with your request', true);

                window.alert('there was an error with your request ... ');
                return;
            }

            activequiz.hide_all_questionboxes();
            activequiz.clear_and_hide_qinfobox();

            activequiz.quiz_info(response.rightanswer, true);
            activequiz.render_all_mathjax();

            // change button text
            var scaBtn = document.getElementById('showcorrectanswer');
            scaBtn.innerHTML = M.util.get_string('hide_correct_answer', 'activequiz');

            activequiz.set('showingcorrectanswer', 'true');

            activequiz.loading(null, 'hide');

        });
    }
};

/**
 * Toggles the "show student responses" variable
 */
activequiz.toggle_responses = function () {

    var toggleresponsesBtn = document.getElementById('toggleresponses');

    if (activequiz.get('showstudentresponses') === false) { // if it is false, set it back to true for the student responses to show

        toggleresponsesBtn.innerHTML = M.util.get_string('hidestudentresponses', 'activequiz');

        activequiz.set('showstudentresponses', true);
        activequiz.gather_current_results();
    } else { // if it's set to true, or not set at all, then set it to false when this button is clicked

        toggleresponsesBtn.innerHTML = M.util.get_string('showstudentresponses', 'activequiz');
        activequiz.set('showstudentresponses', false);
        activequiz.clear_and_hide_qinfobox();
    }
};


/**
 * Toggles the "show not responded" variable
 */
activequiz.toggle_notresponded = function () {

    var togglenotrespondedBtn = document.getElementById('togglenotresponded');

    if (activequiz.get('shownotresponded') === false) { // if it is false, set it back to true for the student responses to show

        togglenotrespondedBtn.innerHTML = M.util.get_string('hidenotresponded', 'activequiz');

        activequiz.set('shownotresponded', true);
        activequiz.getnotresponded();
    } else { // if it's set to true, or not set at all, then set it to false when this button is clicked

        togglenotrespondedBtn.innerHTML = M.util.get_string('shownotresponded', 'activequiz');
        activequiz.set('shownotresponded', false);
        activequiz.clear_and_hide_notresponded();
    }
};


activequiz.getnotresponded = function () {

    var params = {
        'action': 'getnotresponded',
        'rtqid': activequiz.get('rtqid'),
        'sessionid': activequiz.get('sessionid'),
        'attemptid': activequiz.get('attemptid'),
        'sesskey': activequiz.get('sesskey')
    };

    activequiz.ajax.create_request('/mod/activequiz/quizdata.php', params, function (status, response) {

        if (status == '500') {
            activequiz.not_responded_info('there was an error getting not responded students', true);
        } else if (status == 200) {
            activequiz.not_responded_info(response.notresponded, true);
        }

    });

};


/**
 * Function to automatically disable/enable buttons from the array passed.
 *
 * @param buttons An array of button ids to have enabled in the in quiz controls buttons
 */
activequiz.control_buttons = function (buttons) {

    var btns = document.getElementById('inquizcontrols').getElementsByClassName('btn');

    // loop through the btns array and find if their id is in the requested buttons
    for (var i = 0; i < btns.length; i++) {
        var elemid = btns[i].getAttribute("id");

        if (buttons.indexOf(elemid) === -1) {
            // it's not in our buttons array
            btns[i].disabled = true;
        } else {
            btns[i].disabled = false;
        }
    }
};


activequiz.not_responded_info = function (notresponded, clear) {

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

activequiz.clear_and_hide_notresponded = function () {

    var notrespondedbox = document.getElementById('notrespondedbox');

    notrespondedbox.innerHTML = '';

    if (!notrespondedbox.classList.contains('hidden')) {
        notrespondedbox.classList.add('hidden');
    }

};

// Create a container with fixed position that fills the entire screen
// Grabs the already existing question text and bar graph and shows it in a minimalistic style.
activequiz.show_fullscreen_results_view = function() {

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
    container.innerHTML = activequiz.get_question_body_formatted(activequiz.get('currentquestion'));

    // Do we want to show the results?
    var show_results = (activequiz.current_quiz_state !== 'running');

    if (show_results) {

        // Add bar graph
        var quizinfobox = document.getElementById('quizinfobox');
        if (quizinfobox !== null && quizinfobox.children.length > 0) {
            // NOTE: Always assumes first child of quizinfobox is a table
            container.innerHTML += '<table class="activequiz-responses-overview">' + quizinfobox.children[0].innerHTML + '</table>';
        }

    }

    // Let's update the view every second
    if (activequiz.fullscreen_interval_handle === undefined) {
        activequiz.fullscreen_interval_handle = setInterval(function () {
            activequiz.show_fullscreen_results_view();
        }, 1000);
    }
};

// Checks if the view currently exists, and removes it if so.
activequiz.close_fullscreen_results_view = function() {

    // Stop the interval
    clearInterval(activequiz.fullscreen_interval_handle);
    activequiz.fullscreen_interval_handle = undefined;

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
document.addEventListener('keyup', function(e) {
    // Check if 'Escape' key was pressed
    if (e.keyCode == 27) {
        activequiz.close_fullscreen_results_view();
    }
});
