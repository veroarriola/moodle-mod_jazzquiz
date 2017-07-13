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


jazzquiz.getQuizInfo = function () {

    var params = {
        'sesskey': jazzquiz.get('sesskey'),
        'sessionid': jazzquiz.get('sessionid')
    };

    jazzquiz.ajax.create_request('/mod/jazzquiz/quizinfo.php', params, function (status, response) {

        if (status == 500) {
            window.alert('There was an error....' + response);
        } else if (status == 200) {

            jazzquiz.current_quiz_state = response.status;

            if (response.status == 'notrunning') {



            } else if (response.status == 'running' && jazzquiz.get('inquestion') != 'true') {

                jazzquiz.loading(null, 'hide'); // make sure the loading box hides (this is a catch for when the quiz is resuming)
                jazzquiz.set('inquestion', 'true'); // set this to true so that we don't keep calling this over and over
                jazzquiz.set('endedquestion', 'false'); // set this to false if we're going to a new question
                jazzquiz.waitfor_question(response.currentquestion, response.questiontime, response.delay);

            } else if (response.status == 'endquestion' && jazzquiz.get('endedquestion') != 'true') {

                if (jazzquiz.is_voting_running === undefined || !jazzquiz.is_voting_running) {

                    var currentquestion = jazzquiz.get('currentquestion');

                    jazzquiz.handle_question(currentquestion);
                    if (jazzquiz.qcounter) {
                        clearInterval(jazzquiz.qcounter);
                        var questiontimertext = document.getElementById('q' + currentquestion + '_questiontimetext');
                        var questiontimer = document.getElementById('q' + currentquestion + '_questiontime');

                        // reset variables.
                        jazzquiz.qcounter = false;
                        jazzquiz.counter = false;
                        questiontimertext.innerHTML = '';
                        questiontimer.innerHTML = '';
                    }

                    jazzquiz.set('endedquestion', 'true');

                }

            } else if (response.status === 'reviewing') {

                jazzquiz.hide_instructions();

                jazzquiz.is_voting_running = false;

                jazzquiz.quiz_info(M.util.get_string('waitforinstructor', 'jazzquiz'), true);

                jazzquiz.set('inquestion', 'false');

            } else if (response.status === 'sessionclosed') {

                jazzquiz.hide_all_questionboxes();
                jazzquiz.quiz_info(M.util.get_string('sessionclosed', 'jazzquiz'));

            } else if (response.status === 'voting') {

                if (jazzquiz.is_voting_running === undefined || !jazzquiz.is_voting_running) {

                    var options = JSON.parse(response.options);

                    var html = '<div class="jazzquiz-vote">';
                    for (var i = 0; i < options.length; i++) {
                        html += '<label>';
                        html += '<input type="radio" name="vote" value="' + options[i].id + '" onclick="jazzquiz.vote_answer = this.value;">';
                        html += '<span id="vote_answer_label' + i + '">' + options[i].text + '</span>';
                        html += '</label><br>';
                    }
                    html += '</div>';
                    html += '<button class="btn" onclick="jazzquiz.save_vote(); return false;">Save</button>';

                    jazzquiz.quiz_info(html);

                    for (var i = 0; i < options.length; i++) {
                        jazzquiz.render_maxima_equation(options[i].text, i, 'vote_answer_label');
                    }

                    jazzquiz.is_voting_running = true;
                }

            } else if (response.status === 'preparing') {

                jazzquiz.hide_instructions();

                jazzquiz.quiz_info(M.util.get_string('waitforinstructor', 'jazzquiz'), true);

            }
        }

        setTimeout(jazzquiz.getQuizInfo, 3000);

    });
};

/**
 * handles the question for the student
 *
 *
 * @param questionid the questionid to handle
 * @param hide is used to determine if we should hide the question container.  is true by default
 */
jazzquiz.handle_question = function (questionid, hide) {

    var alreadysaving = jazzquiz.get('savingquestion');
    if (alreadysaving == 'undefined') {
        jazzquiz.set('savingquestion', 'saving');
    } else if (alreadysaving === 'saving') {
        // Don't try and save again
        return;
    } else {
        jazzquiz.set('savingquestion', 'saving');
    }

    hide = typeof hide !== 'undefined' ? hide : true;

    var loadingbox = document.getElementById('loadingbox');
    var loadingtext = document.getElementById('loadingtext');
    loadingtext.innerHTML = M.util.get_string('gatheringresults', 'jazzquiz');

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
    formdata.append('rtqid', jazzquiz.get('rtqid'));
    formdata.append('sessionid', jazzquiz.get('sessionid'));
    formdata.append('attemptid', jazzquiz.get('attemptid'));
    formdata.append('sesskey', jazzquiz.get('sesskey'));
    formdata.append('questionid', questionid);

    // submit the form
    jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', formdata, function (status, response) {

        if (status === 500) {
            jazzquiz.set('savingquestion', 'done');
            var loadingbox = document.getElementById('loadingbox');
            loadingbox.classList.add('hidden');

            jazzquiz.quiz_info('There was an error with your request', true);

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
        feedbackintro.innerHTML = M.util.get_string('waitforinstructor', 'jazzquiz');
        jazzquiz.quiz_info(feedbackintro, true);

        if (feedback.length > 0) {
            var feedbackbox = document.createElement('div');
            feedbackbox.innerHTML = feedback;
            jazzquiz.quiz_info(feedbackbox);
        }

        var loadingbox = document.getElementById('loadingbox');
        loadingbox.classList.add('hidden');
        quizinfobox.classList.remove('hidden');

        jazzquiz.set('submittedanswer', 'true');
        jazzquiz.set('savingquestion', 'done');
    });

};


jazzquiz.save_vote = function() {

    var params = {
        'action': 'savevote',
        'rtqid': jazzquiz.get('rtqid'),
        'sessionid': jazzquiz.get('sessionid'),
        'attemptid': jazzquiz.get('attemptid'),
        'sesskey': jazzquiz.get('sesskey'),
        'answer': jazzquiz.vote_answer
    };

    jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {
        if (status == 500) {
            jazzquiz.quiz_info('There was an error saving the vote.', true);
        } else if (status === 200) {
            jazzquiz.hide_all_questionboxes();
            var waitforinstructor = M.util.get_string('waitforinstructor', 'jazzquiz');
            if (response.status === 'success') {
                jazzquiz.quiz_info(waitforinstructor);
            } else if (response.status === 'alreadyvoted') {
                jazzquiz.quiz_info('Sorry, but you have already voted. ' + waitforinstructor);
            } else {
                jazzquiz.quiz_info('An error has occurred. ' + waitforinstructor);
            }
        }

    });
};

