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


activequiz.getQuizInfo = function () {

    var params = {
        'sesskey': activequiz.get('sesskey'),
        'sessionid': activequiz.get('sessionid')
    };

    activequiz.ajax.create_request('/mod/activequiz/quizinfo.php', params, function (status, response) {

        if (status == 500) {
            window.alert('There was an error....' + response);
        } else if (status == 200) {

            activequiz.current_quiz_state = response.status;

            if (response.status == 'notrunning') {

                activequiz.quiz_info(M.util.get_string('waitforinstructor', 'activequiz'), true);

            } else if (response.status == 'running' && activequiz.get('inquestion') != 'true') {

                activequiz.loading(null, 'hide'); // make sure the loading box hides (this is a catch for when the quiz is resuming)
                activequiz.set('inquestion', 'true'); // set this to true so that we don't keep calling this over and over
                activequiz.set('endedquestion', 'false'); // set this to false if we're going to a new question
                activequiz.waitfor_question(response.currentquestion, response.questiontime, response.delay);

            } else if (response.status == 'endquestion' && activequiz.get('endedquestion') != 'true') {

                if (activequiz.is_voting_running === undefined || !activequiz.is_voting_running) {

                    var currentquestion = activequiz.get('currentquestion');

                    activequiz.handle_question(currentquestion);
                    if (activequiz.qcounter) {
                        clearInterval(activequiz.qcounter);
                        var questiontimertext = document.getElementById('q' + currentquestion + '_questiontimetext');
                        var questiontimer = document.getElementById('q' + currentquestion + '_questiontime');

                        // reset variables.
                        activequiz.qcounter = false;
                        activequiz.counter = false;
                        questiontimertext.innerHTML = '';
                        questiontimer.innerHTML = '';
                    }

                    activequiz.set('endedquestion', 'true');

                }

            } else if (response.status == 'reviewing') {

                activequiz.is_voting_running = false;

                activequiz.quiz_info(M.util.get_string('waitforinstructor', 'activequiz'), true);

                activequiz.set('inquestion', 'false');

            } else if (response.status == 'sessionclosed') {

                activequiz.hide_all_questionboxes();
                activequiz.quiz_info(M.util.get_string('sessionclosed', 'activequiz'));

            } else if (response.status == 'voting') {

                if (activequiz.is_voting_running === undefined || !activequiz.is_voting_running) {

                    var options = JSON.parse(response.options);

                    var html = '<div class="activequiz-vote">';
                    for (var i = 0; i < options.length; i++) {
                        html += '<label>';
                        html += '<input type="radio" name="vote" value="' + options[i].id + '" onclick="activequiz.vote_answer = this.value;">';
                        html += '<span id="vote_answer_label' + i + '">' + options[i].text + '</span>';
                        html += '</label><br>';
                    }
                    html += '</div>';
                    html += '<button class="btn" onclick="activequiz.save_vote(); return false;">Save</button>';

                    activequiz.quiz_info(html);

                    for (var i = 0; i < options.length; i++) {
                        activequiz.render_maxima_equation(options[i].text, i, 'vote_answer_label');
                    }

                    activequiz.is_voting_running = true;
                }

            }
        }

        var time = 3000 + Math.floor(Math.random() * (100 + 100) - 100);
        setTimeout(activequiz.getQuizInfo, time);

    });
};

/**
 * handles the question for the student
 *
 *
 * @param questionid the questionid to handle
 * @param hide is used to determine if we should hide the question container.  is true by default
 */
activequiz.handle_question = function (questionid, hide) {

    var alreadysaving = activequiz.get('savingquestion');
    if (alreadysaving == 'undefined') {
        activequiz.set('savingquestion', 'saving');
    } else if (alreadysaving == "saving") {
        return; // don't try and save again
    } else {
        activequiz.set('savingquestion', 'saving');
    }

    hide = typeof hide !== 'undefined' ? hide : true;

    var loadingbox = document.getElementById('loadingbox');
    var loadingtext = document.getElementById('loadingtext');
    loadingtext.innerHTML = M.util.get_string('gatheringresults', 'activequiz');

    // if there are multiple tries for this question then don't hide the question container
    if (hide) {
        var qbox = document.getElementById('q' + questionid + '_container');
        if (qbox !== null) {
            qbox.classList.add('hidden');
        }
    }

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
            activequiz.set('savingquestion', 'done');
            var loadingbox = document.getElementById('loadingbox');
            loadingbox.classList.add('hidden');

            activequiz.quiz_info('There was an error with your request', true);

            window.alert('there was an error with your request ... ');
            return;
        }
        //update the sequence check for the question
        var sequencecheck = document.getElementsByName(response.seqcheckname);
        var field = sequencecheck[0];
        field.value = response.seqcheckval;

        // show feedback to the students
        var quizinfobox = document.getElementById('quizinfobox');

        var feedback = response.feedback;

        var feedbackintro = document.createElement('div');
        feedbackintro.innerHTML = M.util.get_string('waitforinstructor', 'activequiz');
        activequiz.quiz_info(feedbackintro, true);

        if (feedback.length > 0) {
            var feedbackbox = document.createElement('div');
            feedbackbox.innerHTML = feedback;
            activequiz.quiz_info(feedbackbox);
        }

        var loadingbox = document.getElementById('loadingbox');
        loadingbox.classList.add('hidden');
        quizinfobox.classList.remove('hidden');

        activequiz.set('submittedanswer', 'true');
        activequiz.set('savingquestion', 'done');
    });

};


activequiz.save_vote = function() {

    var params = {
        'action': 'savevote',
        'rtqid': activequiz.get('rtqid'),
        'sessionid': activequiz.get('sessionid'),
        'attemptid': activequiz.get('attemptid'),
        'sesskey': activequiz.get('sesskey'),
        'answer': activequiz.vote_answer
    };

    activequiz.ajax.create_request('/mod/activequiz/quizdata.php', params, function (status, response) {
        if (status == '500') {
            activequiz.quiz_info('There was an error saving the vote.', true);
        } else if (status == 200) {
            activequiz.hide_all_questionboxes();
            var waitforinstructor = M.util.get_string('waitforinstructor', 'activequiz');
            if (response.status === 'success') {
                activequiz.quiz_info(waitforinstructor);
            } else if (response.status === 'alreadyvoted') {
                activequiz.quiz_info('Sorry, but you have already voted. ' + waitforinstructor);
            } else {
                activequiz.quiz_info('An error has occurred. ' + waitforinstructor);
            }
        }

    });
};
