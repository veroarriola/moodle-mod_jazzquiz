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

// Ensure that the namespace is defined
var jazzquiz = jazzquiz || {};
jazzquiz.vars = jazzquiz.vars || {};

jazzquiz.change_quiz_state = function(state, data) {

    jazzquiz.current_quiz_state = state;

    switch (state) {

        case 'notrunning':
            break;

        case 'preparing':
            jazzquiz.hide_instructions();
            jazzquiz.quiz_info(M.util.get_string('waitforinstructor', 'jazzquiz'), true);
            break;

        case 'running':
            if (jazzquiz.get('inquestion') != 'true') {
                // Make sure the loading box hides (this is a catch for when the quiz is resuming)
                jazzquiz.loading(null, 'hide');

                // Set this to true so that we don't keep calling this over and over
                jazzquiz.set('inquestion', 'true');

                // Set this to false if we're going to a new question
                jazzquiz.set('endedquestion', 'false');

                jazzquiz.waitfor_question(data.currentquestion, data.questiontime, data.delay);
            }
            break;

        case 'endquestion':
            if (jazzquiz.get('endedquestion') != 'true' && (jazzquiz.is_voting_running === undefined || !jazzquiz.is_voting_running)) {

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
            break;

        case 'reviewing':
            jazzquiz.is_voting_running = false;
            jazzquiz.hide_instructions();
            jazzquiz.quiz_info(M.util.get_string('waitforinstructor', 'jazzquiz'), true);
            jazzquiz.set('inquestion', 'false');

            break;

        case 'sessionclosed':
            jazzquiz.hide_all_questionboxes();
            jazzquiz.quiz_info(M.util.get_string('sessionclosed', 'jazzquiz'));
            break;

        case 'voting':
            if (jazzquiz.is_voting_running === undefined || !jazzquiz.is_voting_running) {

                var options = JSON.parse(data.options);

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
                    if (options[i].qtype === 'stack') {
                        jazzquiz.render_maxima_equation(options[i].text, i, 'vote_answer_label');
                    }
                }

                jazzquiz.is_voting_running = true;
            }
            break;

        default:
            break;

    }

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

    // If there are multiple tries for this question then don't hide the question container
    if (hide) {
        var qbox = document.getElementById('q' + questionid + '_container');
        if (qbox !== null) {
            qbox.classList.add('hidden');
        }
    }

    if (typeof tinyMCE !== 'undefined') {
        tinyMCE.triggerSave();
    }

    var qform = document.forms.namedItem('q' + questionid);
    var formdata = new FormData(qform);

    formdata.append('action', 'savequestion');
    formdata.append('rtqid', jazzquiz.get('rtqid'));
    formdata.append('sessionid', jazzquiz.get('sessionid'));
    formdata.append('attemptid', jazzquiz.get('attemptid'));
    formdata.append('sesskey', jazzquiz.get('sesskey'));
    formdata.append('questionid', questionid);

    // Send request
    jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', formdata, function (status, response) {

        if (status !== HTTP_STATUS.OK) {

            jazzquiz.set('savingquestion', 'done');

            var loadingbox = document.getElementById('loadingbox');
            loadingbox.classList.add('hidden');

            jazzquiz.quiz_info('There was an error with your request', true);

            return;
        }

        // Update the sequence check for the question
        var sequencecheck = document.getElementsByName(response.seqcheckname);
        var field = sequencecheck[0];
        field.value = response.seqcheckval;

        // Show feedback to the students
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


jazzquiz.save_vote = function () {

    // Setup parameters
    var params = {
        'action': 'savevote',
        'rtqid': jazzquiz.get('rtqid'),
        'sessionid': jazzquiz.get('sessionid'),
        'attemptid': jazzquiz.get('attemptid'),
        'sesskey': jazzquiz.get('sesskey'),
        'answer': jazzquiz.vote_answer
    };

    // Send request
    jazzquiz.ajax.create_request('/mod/jazzquiz/quizdata.php', params, function (status, response) {

        if (status !== HTTP_STATUS.OK) {
            jazzquiz.quiz_info('There was an error saving the vote.', true);
            return;
        }

        jazzquiz.hide_all_questionboxes();
        var waitforinstructor = M.util.get_string('waitforinstructor', 'jazzquiz');

        // Output info depending on the status
        switch (response.status) {
            case 'success':
                jazzquiz.quiz_info(waitforinstructor);
                break;
            case 'alreadyvoted':
                jazzquiz.quiz_info('Sorry, but you have already voted. ' + waitforinstructor);
                break;
            default:
                jazzquiz.quiz_info('An error has occurred. ' + waitforinstructor);
                break;
        }

    });
};

