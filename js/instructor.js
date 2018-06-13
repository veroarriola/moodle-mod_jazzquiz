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
 * @param {string} state The new state of the quiz
 * @param {Object} data Quiz state data that was sent from the server
 */
jazzquiz.changeQuizState = function(state, data) {
    this.isNewState = (this.state !== state);
    this.state = state;

    jQuery('#jazzquiz_controls_state').html(state);
    jQuery('#region-main').find('ul.nav.nav-tabs').css('display', 'none');
    jQuery('#region-main-settings-menu').css('display', 'none');
    jQuery('.region_main_settings_menu_proxy').css('display', 'none');

    let $startQuiz = jQuery('#jazzquiz_control_startquiz');
    let $sideContainer = jQuery('#jazzquiz_side_container');

    this.showControls();
    $startQuiz.parent().addClass('hidden');

    let enabledButtons = [];

    switch (state) {
        case 'notrunning':
            $sideContainer.addClass('hidden');
            this.showInfo(this.text('instructions_for_instructor'));
            this.enableControls([]);
            this.hideControls();
            this.quiz.totalStudents = data.student_count;
            let studentsJoined = this.text('no_students_have_joined');
            if (data.student_count === 1) {
                studentsJoined = this.text('one_student_has_joined');
            } else if (data.student_count > 1) {
                studentsJoined = this.text('x_students_have_joined', 'jazzquiz', data.student_count);
            }
            $startQuiz.parent().removeClass('hidden');
            $startQuiz.next().html(studentsJoined);
            break;

        case 'preparing':
            $sideContainer.addClass('hidden');
            this.showInfo(this.text('instructions_for_instructor'));
            enabledButtons = [
                'improvise',
                'jump',
                'fullscreen',
                'quit'
            ];
            if (data.slot < this.quiz.totalQuestions) {
                enabledButtons.push('next');
            }
            this.enableControls(enabledButtons);
            break;

        case 'running':
            $sideContainer.removeClass('hidden');
            this.enableControls([
                'end',
                'responses',
                'fullscreen'
            ]);
            this.quiz.question.questionTime = data.questiontime;
            if (this.quiz.question.isRunning) {
                // Check if the question has already ended.
                // We need to do this because the state does not update unless an instructor is connected.
                if (data.questionTime > 0 && data.delay < -data.questiontime) {
                    this.endQuestion();
                }
                // Only rebuild results if we are not merging.
                const merging = (jQuery('.merge-from').length !== 0);
                this.getResults(!merging);
            } else {
                const started = this.startQuestionCountdown(data.questiontime, data.delay);
                if (started) {
                    this.quiz.question.isRunning = true;
                }
            }
            break;

        case 'reviewing':
            $sideContainer.removeClass('hidden');
            enabledButtons = [
                'answer',
                'vote',
                'repoll',
                'fullscreen',
                'improvise',
                'jump',
                'quit'
            ];
            if (data.slot < this.quiz.totalQuestions) {
                enabledButtons.push('next');
            }
            this.enableControls(enabledButtons);

            // In case page was refreshed, we should ensure the question is showing.
            if (jQuery('#jazzquiz_question_box').html() === '') {
                this.reloadQuestionBox();
            }

            // For now, just always show responses while reviewing.
            // In the future, there should be an additional toggle.
            if (this.isNewState) {
                if (this.quiz.showVotesUponReview) {
                    this.getAndShowVoteResults();
                    this.quiz.showVotesUponReview = false;
                } else {
                    this.getResults(false);
                }
            }
            // No longer in question.
            this.quiz.question.isRunning = false;
            break;

        case 'voting':
            $sideContainer.removeClass('hidden');
            this.enableControls([
                'quit',
                'fullscreen',
                'answer',
                'responses',
                'end'
            ]);
            this.getAndShowVoteResults();
            break;

        case 'sessionclosed':
            $sideContainer.addClass('hidden');
            this.enableControls([]);
            this.quiz.question.isRunning = false;
            break;

        default:
            this.enableControls([]);
            break;
    }
};

/*
// This function is meant to align the side container with the question box.
// There is a problem where this causes the control separator to have a huge padding when changing questions.
// For now, probably best to not use this.
jazzquiz.alignSideContainer = function() {
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

/**
 * End the response merge.
 */
jazzquiz.endResponseMerge = function() {
    jQuery('.merge-into').removeClass('merge-into');
    jQuery('.merge-from').removeClass('merge-from');
};

/**
 * Undo the last response merge.
 */
jazzquiz.undoResponseMerge = function() {
    this.post('undo_merge', {}, function() {
        jazzquiz.getResults(true);
    });
};

/**
 * Merges responses based on response string.
 * @param {string} from
 * @param {string} into
 */
jazzquiz.mergeResponses = function(from, into) {
    this.post('merge_responses', {
        from: from,
        into: into
    }, function() {
        jazzquiz.getResults(false);
    });
};

/**
 * Start a merge between two responses.
 * @param {string} fromRowBarId
 */
jazzquiz.startResponseMerge = function(fromRowBarId) {
    const $barCell = jQuery('#' + fromRowBarId);
    let $row = $barCell.parent();
    if ($row.hasClass('merge-from')) {
        this.endResponseMerge();
        return;
    }
    if ($row.hasClass('merge-into')) {
        const $fromRow = jQuery('.merge-from');
        this.mergeResponses($fromRow.data('response'), $row.data('response'));
        this.endResponseMerge();
        return;
    }
    $row.addClass('merge-from');
    let $table = $row.parent().parent();
    $table.find('tr').each(function() {
        const $cells = jQuery(this).find('td');
        if ($cells[1].id !== $barCell.attr('id')) {
            jQuery(this).addClass('merge-into');
        }
    });
};

/**
 * Create controls to toggle between the responses of the actual question and the vote that followed.
 * @param {string} name Can be either 'vote_response' or 'current_response'
 */
jazzquiz.createResponseControls = function(name) {
    let $responseInfo = jQuery('#jazzquiz_response_info_container');
    if (!this.quiz.question.hasVotes) {
        $responseInfo.addClass('hidden');
        return;
    }
    // Add button for instructor to change what to review.
    if (this.state === 'reviewing') {
        let $showNormalResult = jQuery('#review_show_normal_results');
        let $showVoteResult = jQuery('#review_show_vote_results');
        $responseInfo.removeClass('hidden');
        if (name === 'vote_response') {
            if ($showNormalResult.length === 0) {
                const buttonText = this.text('click_to_show_original_results');
                $responseInfo.html('<h4 class="inline">' + this.text('showing_vote_results') + '</h4>');
                $responseInfo.append('<button id="review_show_normal_results" onclick="jazzquiz.getResults(false);" class="btn btn-primary">' + buttonText + '</button><br>');
                $showVoteResult.remove();
            }
        } else if (name === 'current_response') {
            if ($showVoteResult.length === 0) {
                const buttonText = this.text('click_to_show_vote_results');
                $responseInfo.html('<h4 class="inline">' + this.text('showing_original_results') + '</h4>');
                $responseInfo.append('<button id="review_show_vote_results" onclick="jazzquiz.getAndShowVoteResults();" class="btn btn-primary">' + buttonText + '</button><br>');
                $showNormalResult.remove();
            }
        }
    }
};

/**
 * Create a new and unsorted response bar graph.
 * @param {Array.<Object>} responses
 * @param {string} name
 * @param {string} targetId
 * @param {string} graphId
 * @param {boolean} rebuild If the table should be completely rebuilt or not
 */
jazzquiz.createResponseBarGraph = function(responses, name, targetId, graphId, rebuild) {
    let target = document.getElementById(targetId);
    if (target === null) {
        return;
    }
    let total = 0;
    for (let i = 0; i < responses.length; i++) {
        total += parseInt(responses[i].count); // In case count is a string.
    }
    if (total === 0) {
        total = 1;
    }

    // Remove the rows if it should be rebuilt.
    if (rebuild) {
        target.innerHTML = '';
    }

    // Prune rows.
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

    this.createResponseControls(name);

    name += graphId;

    // Add rows.
    for (let i = 0; i < responses.length; i++) {
        const percent = (parseInt(responses[i].count) / total) * 100;

        // Check if row with same response already exists.
        let rowIndex = -1;
        let currentRowIndex = -1;
        for (let j = 0; j < target.rows.length; j++) {
            if (target.rows[j].dataset.response === responses[i].response) {
                rowIndex = target.rows[j].dataset.row_i;
                currentRowIndex = j;
                break;
            }
        }

        if (rowIndex === -1) {

            rowIndex = target.rows.length;

            let row = target.insertRow();
            row.dataset.response_i = i;
            row.dataset.response = responses[i].response;
            row.dataset.percent = percent;
            row.dataset.row_i = rowIndex;
            row.dataset.count = responses[i].count;
            row.classList.add('selected-vote-option');

            const countHtml = '<span id="' + name + '_count_' + rowIndex + '">' + responses[i].count + '</span>';
            let responseCell = row.insertCell(0);
            responseCell.onclick = function() {
                jQuery(this).parent().toggleClass('selected-vote-option');
            };

            let barCell = row.insertCell(1);
            barCell.classList.add('bar');
            barCell.id = name + '_bar_' + rowIndex;
            barCell.innerHTML = '<div style="width:' + percent + '%;">' + countHtml + '</div>';

            const latexId = name + '_latex_' + rowIndex;
            responseCell.innerHTML = '<span id="' + latexId + '"></span>';
            this.addMathjaxElement(latexId, responses[i].response);

            if (responses[i].qtype === 'stack') {
                this.renderMaximaEquation(responses[i].response, latexId);
            }

        } else {
            target.rows[currentRowIndex].dataset.row_i = rowIndex;
            target.rows[currentRowIndex].dataset.response_i = i;
            target.rows[currentRowIndex].dataset.percent = percent;
            target.rows[currentRowIndex].dataset.count = responses[i].count;
            let countElement = document.getElementById(name + '_count_' + rowIndex);
            if (countElement !== null) {
                countElement.innerHTML = responses[i].count;
            }
            let barElement = document.getElementById(name + '_bar_' + rowIndex);
            if (barElement !== null) {
                barElement.firstElementChild.style.width = percent + '%';
            }
        }
    }
};

/**
 * Sort the responses in the graph by how many had the same response.
 * @param {string} targetId
 */
jazzquiz.sortResponseBarGraph = function(targetId) {
    let target = document.getElementById(targetId);
    if (target === null) {
        return;
    }
    let isSorting = true;
    while (isSorting) {
        isSorting = false;
        for (let i = 0; i < (target.rows.length - 1); i++) {
            const current = parseInt(target.rows[i].dataset.percent);
            const next = parseInt(target.rows[i + 1].dataset.percent);
            if (current < next) {
                target.rows[i].parentNode.insertBefore(target.rows[i + 1], target.rows[i]);
                isSorting = true;
                break;
            }
        }
    }
};

/**
 * Create and sort a bar graph based on the responses passed.
 * @param {string} wrapperId
 * @param {string} tableId
 * @param {Array.<Object>} responses
 * @param {number|undefined} responded How many students responded to the question
 * @param {string} questionType
 * @param {string} graphId
 * @param {boolean} rebuild If the graph should be rebuilt or not.
 */
jazzquiz.setResponses = function(wrapperId, tableId, responses, responded, questionType, graphId, rebuild) {
    if (responses === undefined) {
        return;
    }

    let $responded = jQuery('#jazzquiz_responded_container');

    // Check if any responses to show.
    if (responses.length === 0) {
        $responded.removeClass('hidden');
        $responded.find('h4').html(this.text('a_out_of_b_responded', 'jazzquiz', {
            a: 0,
            b: this.quiz.totalStudents
        }));
        return;
    }

    // Question type specific.
    switch (questionType) {
        case 'shortanswer':
            for (let i = 0; i < responses.length; i++) {
                responses[i].response = responses[i].response.trim();
            }
            break;
        case 'stack':
            // Remove all spaces from responses.
            for (let i = 0; i < responses.length; i++) {
                responses[i].response = responses[i].response.replace(/\s/g, '');
            }
            break;
        default:
            break;
    }

    // Update data.
    this.currentResponses = [];
    this.totalResponses = responses.length;
    this.quiz.respondedCount = 0;
    for (let i = 0; i < responses.length; i++) {
        let exists = false;
        let count = 1;
        if (responses[i].count !== undefined) {
            count = parseInt(responses[i].count);
        }
        this.quiz.respondedCount += count;

        // Check if response is a duplicate.
        for (let j = 0; j < this.currentResponses.length; j++) {
            if (this.currentResponses[j].response === responses[i].response) {
                this.currentResponses[j].count += count;
                exists = true;
                break;
            }
        }

        // Add element if not a duplicate.
        if (!exists) {
            this.currentResponses.push({
                response: responses[i].response,
                count: count,
                qtype: questionType
            });
        }
    }

    // Update responded container.
    if ($responded.length !== 0 && responded !== undefined) {
        $responded.removeClass('hidden');
        $responded.find('h4').html(this.text('a_out_of_b_responded', 'jazzquiz', {
            a: responded,
            b: this.quiz.totalStudents
        }));
    }

    // Should we show the responses?
    if (!this.options.showResponses && this.state !== 'reviewing') {
        jQuery('#jazzquiz_response_info_container').addClass('hidden').html('');
        jQuery('#jazzquiz_responses_container').addClass('hidden').html('');
        return;
    }

    // Make sure quiz info has the wrapper for the responses.
    let wrapperCurrentResponses = document.getElementById(tableId);
    if (wrapperCurrentResponses === null) {
        jQuery('#' + wrapperId).removeClass('hidden').html('<table id="' + tableId + '" class="jazzquiz-responses-overview"></table>', true);
        wrapperCurrentResponses = document.getElementById(tableId);
        // This should not happen, but check just in case quiz_info fails to set the html.
        if (wrapperCurrentResponses === null) {
            return;
        }
    }

    // Update HTML.
    this.createResponseBarGraph(this.currentResponses, 'current_response', tableId, graphId, rebuild);
    this.sortResponseBarGraph(tableId);
};

/**
 * Start the quiz. Does not start any questions.
 */
jazzquiz.startQuiz = function() {
    jQuery('#jazzquiz_control_startquiz').parent().addClass('hidden');
    this.post('start_quiz', {}, function() {
        jQuery('#jazzquiz_controls').removeClass('btn-hide');
    });
};

/**
 * End the currently ongoing question or vote.
 */
jazzquiz.endQuestion = function() {
    this.hideQuestionTimer();
    this.post('end_question', {}, function() {
        if (jazzquiz.state === 'voting') {
            jazzquiz.quiz.showVotesUponReview = true;
            return;
        }
        jazzquiz.quiz.question.isRunning = false;
        jazzquiz.enableControls([]);
    }).fail(function() {
        jazzquiz.showInfo(jazzquiz.text('failed_to_end_question'));
    });
};

/**
 * Show a question list dropdown.
 * @param {string} name
 * @param {string} action The action for ajax.php
 */
jazzquiz.showQuestionListSetup = function(name, action) {
    let $controlButton = jQuery('#jazzquiz_control_' + name);
    if ($controlButton.hasClass('active')) {
        // It's already open. Let's not send another request.
        return;
    }

    // The dropdown lies within the button, so we have to do this extra step.
    // This attribute is set in the onclick function for one of the buttons in the dropdown.
    // TODO: Redo the dropdown so we don't have to do this.
    if ($controlButton.data('isclosed') === 'yes') {
        $controlButton.data('isclosed', '');
        return;
    }

    this.get(action, {}, function(data) {
        let $menu = jQuery('#jazzquiz_' + name + '_menu');
        const menuMargin = $controlButton.offset().left - $controlButton.parent().offset().left;
        $menu.html('').addClass('active').css('margin-left', menuMargin + 'px')
        $controlButton.addClass('active');
        const questions = data.questions;
        for (let i in questions) {
            if (!questions.hasOwnProperty(i)) {
                continue;
            }
            let $questionButton = jQuery('<button class="btn">' + questions[i].name + '</button>');
            $questionButton.data({
                'time': questions[i].time,
                'question-id': questions[i].questionid,
                'jazzquiz-question-id': questions[i].jazzquizquestionid
            });
            $questionButton.data('test', 1);
            $questionButton.on('click', function() {
                jazzquiz.jumpQuestion(jQuery(this).data('question-id'), jQuery(this).data('time'), jQuery(this).data('jazzquiz-question-id'));
                $menu.html('').removeClass('active');
                $controlButton.removeClass('active').data('isclosed', 'yes');
            });
            $menu.append($questionButton);
        }
    });
};

jazzquiz.showImproviseQuestionSetup = function() {
    this.showQuestionListSetup('improvise', 'list_improvise_questions');
};

jazzquiz.showJumpQuestionSetup = function() {
    this.showQuestionListSetup('jump', 'list_jump_questions');
};

/**
 * Get the selected responses.
 * @returns {Array.<Object>} Vote options
 */
jazzquiz.getSelectedAnswersForVote = function() {
    let result = [];
    jQuery('.selected-vote-option').each(function(i, option) {
        result.push({
            text: option.dataset.response,
            count: option.dataset.count
        });
    });
    return result;
};

/**
 * getResults() equivalent for votes.
 */
jazzquiz.getAndShowVoteResults = function() {
    // Should we show the results?
    if (!this.options.showResponses && this.state !== 'reviewing') {
        jQuery('#jazzquiz_response_info_container').addClass('hidden').html('');
        jQuery('#jazzquiz_responses_container').addClass('hidden').html('');
        return;
    }
    this.get('get_vote_results', {}, function(data) {
        const answers = data.answers;
        const targetId = 'wrapper_vote_responses';
        let responses = [];

        jazzquiz.quiz.respondedCount = 0;
        jazzquiz.quiz.totalStudents = parseInt(data.total_students);

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
            jazzquiz.quiz.respondedCount += parseInt(answers[i].finalcount);
        }

        let $responded = jQuery('#jazzquiz_responded_container');
        $responded.removeClass('hidden');
        $responded.find('h4').html(jazzquiz.text('a_out_of_b_voted', 'jazzquiz', {
            a: jazzquiz.quiz.respondedCount,
            b: jazzquiz.quiz.totalStudents
        }));

        let target = document.getElementById(targetId);
        if (target === null) {
            jQuery('#jazzquiz_responses_container').removeClass('hidden').html('<table id="' + targetId + '" class="jazzquiz-responses-overview"></table>', true);
            target = document.getElementById(targetId);
            // This should not happen, but check just in case quiz_info fails to set the html.
            if (target === null) {
                return;
            }
        }

        jazzquiz.createResponseBarGraph(responses, 'vote_response', targetId, 'vote', false);
        jazzquiz.sortResponseBarGraph(targetId);
    }).fail(function() {
        jazzquiz.showInfo(jazzquiz.text('error_getting_vote_results'));
    });
};

/**
 * Start a vote with the responses that are currently selected.
 */
jazzquiz.runVoting = function() {
    const options = this.getSelectedAnswersForVote();
    const questions = encodeURIComponent(JSON.stringify(options));
    this.post('run_voting', {
        questions: questions
    }, function() {

    }).fail(function() {
        jazzquiz.showInfo(jazzquiz.text('error_starting_vote'));
    });
};

/**
 * Fetch and show results for the ongoing or previous question.
 * @param {boolean} rebuild If the response graph should be rebuilt or not.
 */
jazzquiz.getResults = function(rebuild) {
    this.get('get_results', {}, function(data) {
        jazzquiz.quiz.question.hasVotes = data.has_votes;
        jazzquiz.quiz.totalStudents = parseInt(data.total_students);

        jazzquiz.setResponses('jazzquiz_responses_container', 'current_responses_wrapper',
            data.responses, data.responded, data.question_type, 'results', rebuild);

        if (data.merge_count > 0) {
            jQuery('#jazzquiz_undo_merge').removeClass('hidden');
        } else {
            jQuery('#jazzquiz_undo_merge').addClass('hidden');
        }
    }).fail(function() {
        jazzquiz.showInfo(jazzquiz.text('error_getting_current_results'));
    });
};

// TODO: Refactor these start question functions.
/**
 * Start a new question in this session.
 * @param {string} method
 * @param {number} questionId
 * @param {number} questionTime
 * @param {number} jazzquizQuestionId
 */
jazzquiz.startQuestion = function(method, questionId, questionTime, jazzquizQuestionId) {
    this.hideInfo();
    this.post('start_question', {
        method: method,
        questionid: questionId,
        questiontime: questionTime,
        jazzquizquestionid: jazzquizQuestionId
    }, function(data) {
        jazzquiz.startQuestionCountdown(data.questiontime, data.delay);
    }).fail(function() {
        jazzquiz.showInfo(jazzquiz.text('error_with_request'));
    });
};

/**
 * Jump to a planned question in the quiz.
 * @param {number} questionId
 * @param {number} questionTime
 * @param {number} jazzquizQuestionId
 */
jazzquiz.jumpQuestion = function(questionId, questionTime, jazzquizQuestionId) {
    this.startQuestion('jump', questionId, questionTime, jazzquizQuestionId);
};

/**
 * Repoll the previously asked question.
 */
jazzquiz.repollQuestion = function() {
    this.startQuestion('repoll', 0, 0, 0);
};

/**
 * Continue on to the next preplanned question.
 */
jazzquiz.nextQuestion = function() {
    this.startQuestion('next', 0, 0, 0);
};

/**
 * Close the current session.
 */
jazzquiz.closeSession = function() {
    this.clearQuestionBox();
    this.showInfo(this.text('closing_session'));
    jQuery('#jazzquiz_controls_box').addClass('hidden');
    this.post('close_session', {}, function() {
        jazzquiz.showInfo(jazzquiz.text('session_closed'));
    }).fail(function() {
        jazzquiz.showInfo(jazzquiz.text('error_with_request'));
    });
};

/**
 * Request and show the correct answer for the ongoing or previous question.
 */
jazzquiz.showCorrectAnswer = function() {
    // Hide if already showing.
    if (this.options.showCorrectAnswer) {
        // Make sure it's gone.
        jQuery('#jazzquiz_correct_answer_container').addClass('hidden').html('');
        // Change button icon.
        jQuery('#jazzquiz_control_answer').html('<i class="fa fa-square-o"></i> ' + this.text('answer'));
        this.options.showCorrectAnswer = false;
        // We don't need to ask for the answer, so let's return.
        return;
    }
    this.get('get_right_response', {}, function(data) {
        jQuery('#jazzquiz_correct_answer_container').removeClass('hidden').html('<span class="jazzquiz-latex-wrapper">' + data.right_answer + '</span>');
        jazzquiz.renderAllMathjax();
        jQuery('#jazzquiz_control_answer').html('<i class="fa fa-check-square-o"></i> ' + jazzquiz.text('answer'));
        jazzquiz.options.showCorrectAnswer = true;
    }).fail(function() {
        jazzquiz.showInfo(jazzquiz.text('error_with_request'));
    });
};

/**
 * Hides the responses
 */
jazzquiz.hideResponses = function() {
    this.options.showResponses = false;
    jQuery('#toggleresponses').html('<i class="fa fa-square-o"></i> ' + this.text('responses'));
    jQuery('#jazzquiz_response_info_container').addClass('hidden').html('');
    jQuery('#jazzquiz_responses_container').addClass('hidden').html('');
};

/**
 * Shows the responses
 */
jazzquiz.showResponses = function() {
    this.options.showResponses = true;
    jQuery('#jazzquiz_control_responses').html('<i class="fa fa-check-square-o"></i> ' + this.text('responses'));
    this.getResults(false);
};

/**
 * Toggle whether to show or hide the responses
 */
jazzquiz.toggleResponses = function() {
    if (this.options.showResponses) {
        this.hideResponses();
    } else {
        this.showResponses();
    }
};

/**
 * Enables all buttons passed in arguments, but disables all others.
 * @param {Array.<string>} buttons The unique part of the IDs of the buttons to be enabled.
 */
jazzquiz.enableControls = function(buttons) {
    // Let's find the direct child nodes.
    let children = jQuery('#jazzquiz_controls').find('.quiz-control-buttons').children();
    // Disable all the buttons that are not present in the "buttons" parameter.
    for (let i = 0; i < children.length; i++) {
        const id = children[i].getAttribute('id').replace('jazzquiz_control_', '');
        children[i].disabled = (buttons.indexOf(id) === -1);
    }
};

/**
 * Hide the instructor controls.
 */
jazzquiz.hideControls = function() {
    jQuery('#jazzquiz_controls').find('.quiz-control-buttons').addClass('hidden');
};

/**
 * Show the instructor controls.
 */
jazzquiz.showControls = function() {
    jQuery('#jazzquiz_controls').find('.quiz-control-buttons').removeClass('hidden');
};

/**
 * Enter fullscreen mode for better use with projectors.
 */
jazzquiz.showFullscreenView = function() {
    let $jazzquiz = jQuery('#jazzquiz');
    // Are we already in fullscreen mode?
    if ($jazzquiz.hasClass('jazzquiz-fullscreen')) {
        // Yes, let's close it instead.
        this.closeFullscreenView();
        return;
    }
    // Hide the scrollbar - remember to always set back to auto when closing.
    document.documentElement.style.overflowY = 'hidden';
    // Sets the quiz view to an absolute position that covers the viewport.
    $jazzquiz.addClass('jazzquiz-fullscreen');
};

/**
 * Exit the fullscreen mode.
 */
jazzquiz.closeFullscreenView = function() {
    // Reset the overflow-y back to auto.
    document.documentElement.style.overflowY = 'auto';
    // Remove the fullscreen view.
    jQuery('#jazzquiz').removeClass('jazzquiz-fullscreen');
};

/**
 * Close the dropdown menu for choosing a question.
 * @param {Event} event
 * @param {string} name
 */
jazzquiz.closeQuestionListMenu = function(event, name) {
    const menuId = '#jazzquiz_' + name + '_menu';
    // Close the menu if the click was not inside.
    const menu = jQuery(event.target).closest(menuId);
    if (!menu.length) {
        jQuery(menuId).html('').removeClass('active');
        jQuery('#jazzquiz_control_' + name).removeClass('active');
    }
};

/**
 * Add the event handlers.
 */
jazzquiz.addEventHandlers = function() {
    // Listens for key event to remove the projector view container.
    jQuery(document).on('keyup', function(event) {
        // Check if 'Escape' key was pressed.
        if (event.keyCode === 27) {
            jazzquiz.closeFullscreenView();
        }
    });

    jQuery(document).on('click', function(event) {
        jazzquiz.closeQuestionListMenu(event, 'improvise');
        jazzquiz.closeQuestionListMenu(event, 'jump');
        // Clicking a row to merge.
        if (event.target.classList.contains('bar')) {
            jazzquiz.startResponseMerge(event.target.id);
        } else if (event.target.parentNode && event.target.parentNode.classList.contains('bar')) {
            jazzquiz.startResponseMerge(event.target.parentNode.id);
        }
    });

    // Add control button events.
    jQuery(document).on('click', '#jazzquiz_control_repoll', function() {
        jazzquiz.enableControls([]);
        jazzquiz.repollQuestion();
    })
    .on('click', '#jazzquiz_control_vote', function() {
        jazzquiz.enableControls([]);
        jazzquiz.runVoting();
    })
    .on('click', '#jazzquiz_control_improvise', function() {
        jazzquiz.showImproviseQuestionSetup();
    })
    .on('click', '#jazzquiz_control_jump', function() {
        jazzquiz.showJumpQuestionSetup();
    })
    .on('click', '#jazzquiz_control_next', function() {
        jazzquiz.enableControls([]);
        jazzquiz.nextQuestion();
    })
    .on('click', '#jazzquiz_control_end', function() {
        jazzquiz.enableControls([]);
        jazzquiz.endQuestion();
    })
    .on('click', '#jazzquiz_control_fullscreen', function() {
        jazzquiz.enableControls([]);
        jazzquiz.showFullscreenView();
    })
    .on('click', '#jazzquiz_control_answer', function() {
        jazzquiz.enableControls([]);
        jazzquiz.showCorrectAnswer();
    })
    .on('click', '#jazzquiz_control_responses', function() {
        jazzquiz.enableControls([]);
        jazzquiz.toggleResponses();
    })
    .on('click', '#jazzquiz_control_exit', function() {
        jazzquiz.enableControls([]);
        jazzquiz.closeSession();
    })
    .on('click', '#jazzquiz_control_quit', function() {
        jazzquiz.enableControls([]);
        jazzquiz.closeSession();
    })
    .on('click', '#jazzquiz_control_startquiz', function() {
        jazzquiz.enableControls([]);
        jazzquiz.startQuiz();
    })
    .on('click', '#jazzquiz_undo_merge', function() {
        jazzquiz.undoResponseMerge();
    });
};

jazzquiz.addReportEventHandlers = function() {
    require(['jquery'], function($) {
        $(document).on('click', '#report_overview_controls button', function () {
            const action = $(this).data('action');
            if (action === 'attendance') {
                $('#report_overview_responded').fadeIn();
                $('#report_overview_responses').fadeOut();
            } else if (action === 'responses') {
                $('#report_overview_responses').fadeIn();
                $('#report_overview_responded').fadeOut();
            }
        });
    });
};
