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

var jazzquiz = {
    jQueryErrors: 0,
    state: '',
    isNewState: false,
    isInstructor: false,
    siteroot: '',

    currentResponses: [],
    totalResponses: 0,

    questionCountdownInterval: 0,
    questionTimerInterval: 0,

    quiz: {
        courseModuleId: 0,
        activityId: 0,
        sessionId: 0,
        attemptId: 0,
        sessionKey: '',
        showVotesUponReview: false,
        respondedCount: 0,
        totalStudents: 0,
        totalQuestions: 0,

        question: {
            isRunning: false,
            isLast: false,
            isSaving: false,
            endTime: 0,
            isVoteRunning: false,
            hasVotes: false,
            countdownTimeLeft: 0,
            questionType: undefined,
            questionTime: 0
        }
    },

    options: {
        showResponses: false,
        showCorrectAnswer: false
    },

    latexCache: [],

    // Student temporary variables.
    voteAnswer: undefined
    
};

/**
 * Send a request using AJAX, with method specified.
 * @param {string} method Which HTTP method to use.
 * @param {string} url Relative to root of jazzquiz module. Does not start with /.
 * @param {Object} data Object with parameters as properties. Reserved: id, quizid, sessionid, attemptid, sesskey
 * @param {function} success Callback function for when the request was completed successfully.
 * @return {jqXHR} The jQuery XHR object
 */
jazzquiz.ajax = function(method, url, data, success) {
    data.id = this.quiz.courseModuleId;
    data.quizid = this.quiz.activityId;
    data.sessionid = this.quiz.sessionId;
    data.attemptid = this.quiz.attemptId;
    data.sesskey = this.quiz.sessionKey;
    return jQuery.ajax({
        type: method,
        url: url,
        data: data,
        dataType: 'json',
        success: success,
        error: function(xhr, status, error) {
            console.log('XHR Error: ' + error + '. Status: ' + status);
        }
    });
};

/**
 * Send a GET request using AJAX.
 * @param {string} action Which action to query.
 * @param {Object} data Object with parameters as properties. Reserved: id, quizid, sessionid, attemptid, sesskey
 * @param {function} success Callback function for when the request was completed successfully.
 * @return {jqXHR} The jQuery XHR object
 */
jazzquiz.get = function(action, data, success) {
    data.action = action;
    return this.ajax('get', 'ajax.php', data, success);
};

/**
 * Send a POST request using AJAX.
 * @param {string} action Which action to query.
 * @param {Object} data Object with parameters as properties. Reserved: id, quizid, sessionid, attemptid, sesskey
 * @param {function} success Callback function for when the request was completed successfully.
 * @return {jqXHR} The jQuery XHR object
 */
jazzquiz.post = function(action, data, success) {
    data.action = action;
    return this.ajax('post', 'ajax.php', data, success);
};

/**
 * Initiate the chained session info calls to ajax.php
 */
jazzquiz.requestQuizInfo = function() {
    jazzquiz.get('info', {}, function(data) {
        // Change the local state.
        jazzquiz.changeQuizState(data.status, data);
        // Schedule next update.
        // TODO: Remove this if statement, and rather have a time defined in the specific javascript files.
        /* The instructor has a higher update frequency since there is usually only one,
           but students might be in the hundreds, so we want to limit them to every couple seconds instead. */
        if (jazzquiz.isInstructor) {
            setTimeout(jazzquiz.requestQuizInfo, 500);
        } else {
            setTimeout(jazzquiz.requestQuizInfo, 2000);
        }
    });
};

/**
 * Cache a string to a latex output.
 * @param {string} input
 * @param {string} output
 */
jazzquiz.setLatex = function(input, output) {
    jazzquiz.latexCache[input] = output;
};

/**
 * Get the cached latex for a string.
 * @param {string} input
 * @returns {*}
 */
jazzquiz.getLatex = function(input) {
    return jazzquiz.latexCache[input];
};

/**
 * Retrieve a language string that was sent along with the page.
 * @param {string} key Which string in the language file we want.
 * @param {string} [from=jazzquiz] Which language file we want the string from. Default is jazzquiz.
 * @param [args] This is {$a} in the string for the key.
 */
jazzquiz.text = function(key, from, args) {
    from = (typeof from !== 'undefined') ? from : 'jazzquiz';
    args = (typeof args !== 'undefined') ? args : [];
    return M.util.get_string(key, from, args);
};

/**
 * Show the loading animation with some text.
 * @param {string} text
 */
jazzquiz.showLoading = function(text) {
    jQuery('#jazzquiz_loading').removeClass('hidden').children('p').html(text);
};

/**
 * Hide the loading animation.
 */
jazzquiz.hideLoading = function() {
    jQuery('#jazzquiz_loading').addClass('hidden');
};

/**
 * Hide the quiz state information.
 * @todo This should hide only the info, and not all the other containers.
 */
jazzquiz.hideInfo = function() {
    if (this.isInstructor) {
        jQuery('#jazzquiz_responded_container').addClass('hidden').find('h4').html('');
        jQuery('#jazzquiz_response_info_container').addClass('hidden').html('');
        jQuery('#jazzquiz_responses_container').addClass('hidden').html('');
    }
    jQuery('#jazzquiz_question_timer').addClass('hidden').html('');
    jQuery('#jazzquiz_info_container').addClass('hidden').html('');
};

/**
 * Show information that is relevant to the current state of the quiz.
 * @param {string} text
 */
jazzquiz.showInfo = function(text) {
    jQuery('#jazzquiz_info_container').removeClass('hidden').html(text);
};

/**
 * Triggers a dynamic content update event, which MathJax listens to.
 */
jazzquiz.renderAllMathjax = function() {
    Y.fire(M.core.event.FILTER_CONTENT_UPDATED, {
        nodes: new Y.NodeList(document.getElementsByClassName('jazzquiz-latex-wrapper'))
    });
};

/**
 * Sets the body of the target, and triggers an event letting MathJax know about the element.
 * @param {string} targetId
 * @param {string} latex
 */
jazzquiz.addMathjaxElement = function(targetId, latex) {
    jQuery('#' + targetId).html('<span class="jazzquiz-latex-wrapper"><span class="filter_mathjaxloader_equation">' + latex + '</span></span>');
    this.renderAllMathjax();
};

/**
 * Converts the input to LaTeX and renders it to the target with MathJax.
 * @param {string} input
 * @param {string} targetId
 */
jazzquiz.renderMaximaEquation = function(input, targetId) {
    const target = document.getElementById(targetId);
    if (target === null) {
        console.log('Target element #' + targetId + ' not found.');
        return;
    }
    const cachedLatex = this.getLatex(input);
    if (cachedLatex !== undefined) {
        jazzquiz.addMathjaxElement(targetId, cachedLatex);
        return;
    }
    this.get('stack', {
        input: encodeURIComponent(input)
    }, function(data) {
        jazzquiz.setLatex(data.original, data.latex);
        jazzquiz.addMathjaxElement(targetId, data.latex);
    }).fail(function() {
        console.log('Failed to get LaTeX for #' + targetId);
    });
};

/**
 * @todo Is there a more elegant way to do this?
 * @returns {string} The question body HTML with some elements removed.
 */
jazzquiz.getQuestionBodyFormatted = function() {
    const $original = jQuery('#jazzquiz_question_box');
    if (!$original.length) {
        return 'Not found';
    }
    let $questionBox = $original.clone();
    $questionBox.find('.info').remove();
    $questionBox.find('.im-controls').remove();
    $questionBox.find('.questiontestslink').remove();
    $questionBox.find('input').remove();
    $questionBox.find('label').remove(); // Some inputs have labels
    $questionBox.find('.ablock.form-inline').remove();
    $questionBox.find('.save_row').remove();
    return $questionBox.html();
};

/**
 * Load data such as session key and quiz state.
 */
jazzquiz.decodeState = function() {
    for (let prop in jazzquizRootState) {
        if (jazzquizRootState.hasOwnProperty(prop)) {
            this[prop] = jazzquizRootState[prop];
        }
    }
    for (let prop in jazzquizQuizState) {
        if (jazzquizQuizState.hasOwnProperty(prop)) {
            this.quiz[prop] = jazzquizQuizState[prop];
        }
    }
};

/**
 * Callback for when the quiz page is fully loaded
 */
jazzquiz.initialize = function() {
    // Wait for jQuery
    if (!window.jQuery) {
        console.log('Waiting for jQuery... Trying again in 50ms');
        this.jQueryErrors++;
        if (this.jQueryErrors > 50) {
            location.reload(true);
        }
        setTimeout(function() {
            jazzquiz.initialize();
        }, 50);
        return;
    }
    this.hideLoading();
    this.decodeState();
    this.requestQuizInfo();
    this.addEventHandlers();
};

jazzquiz.clearQuestionBox = function() {
    jQuery('#jazzquiz_question_box').html('').addClass('hidden');
};

/**
 * Request the current question form.
 */
jazzquiz.reloadQuestionBox = function() {
    this.get('get_question_form', {}, function(data) {
        jazzquiz.quiz.question.questionType = data.question_type;
        if (data.is_already_submitted) {
            jazzquiz.showInfo(jazzquiz.text('wait_for_instructor'));
            return;
        }
        jQuery('#jazzquiz_question_box').html(data.html).removeClass('hidden');
        eval(data.js);
    }).fail(function() {
        jazzquiz.showInfo('Failed to load question.');
    });
};

/**
 * Hide the question "ending in" timer, and clears the interval.
 */
jazzquiz.hideQuestionTimer = function() {
    jQuery('#jazzquiz_question_timer').html('').addClass('hidden');
    if (this.questionTimerInterval !== 0) {
        clearInterval(this.questionTimerInterval);
        this.questionTimerInterval = 0;
    }
};

/**
 * Set time remaining for the question.
 * @param {number} timeLeft in seconds.
 */
jazzquiz.setQuestionTimerText = function(timeLeft) {
    let $timer = jQuery('#jazzquiz_question_timer');
    if (this.isInstructor) {
        $timer.html(this.text('x_seconds_left', 'jazzquiz', timeLeft));
    } else {
        $timer.html(this.text('question_will_end_in_x_seconds', 'jazzquiz', timeLeft));
    }
    $timer.removeClass('hidden');
};

/**
 * Set time remaining until the question starts.
 * @param {number} timeLeft in seconds.
 */
jazzquiz.setCountdownTimerText = function(timeLeft) {
    if (timeLeft !== 0) {
        this.showInfo(this.text('question_will_start_in_x_seconds', 'jazzquiz', timeLeft));
    } else {
        this.showInfo(this.text('question_will_start_now'));
    }
};

/**
 * Is called for every second of the question countdown.
 * @param {number} questionTime in seconds
 */
jazzquiz.onQuestionCountdownTick = function(questionTime) {
    this.quiz.question.countdownTimeLeft--;
    if (this.quiz.question.countdownTimeLeft <= 0) {
        clearInterval(this.questionCountdownInterval);
        this.questionCountdownInterval = 0;
        this.startQuestionAttempt(questionTime);
    } else {
        this.setCountdownTimerText(this.quiz.question.countdownTimeLeft);
    }
};

/**
 * Start a countdown for the question which will eventually start the question attempt.
 * The question attempt might start before this function return, depending on the arguments.
 * If a countdown has already been started, this call will return true and the current countdown will continue.
 * @param {number} questionTime
 * @param {number} countdownTimeLeft
 * @return {boolean} true if countdown is active
 */
jazzquiz.startQuestionCountdown = function(questionTime, countdownTimeLeft) {
    if (this.questionCountdownInterval !== 0) {
        return true;
    }
    questionTime = parseInt(questionTime);
    countdownTimeLeft = parseInt(countdownTimeLeft);
    this.quiz.question.countdownTimeLeft = countdownTimeLeft;
    if (countdownTimeLeft < 1) {
        // Check if the question has already ended.
        if (questionTime > 0 && countdownTimeLeft < -questionTime) {
            return false;
        }
        // We want to show some text, as we must also request the question form from the server.
        this.setCountdownTimerText(0);
        // No need to start the countdown. Just start the question.
        if (questionTime > 1) {
            this.startQuestionAttempt(questionTime + countdownTimeLeft);
        } else {
            this.startQuestionAttempt(0);
        }
        return true;
    }
    this.setCountdownTimerText(countdownTimeLeft);
    this.questionCountdownInterval = setInterval(function() {
        jazzquiz.onQuestionCountdownTick(questionTime);
    }, 1000);
    return true;
};

/**
 * When the question "ending in" timer reaches 0 seconds, this will be called.
 */
jazzquiz.onQuestionTimerEnding = function() {
    this.quiz.question.isRunning = false;
    if (this.isInstructor) {
        this.endQuestion();
    }
};

/**
 * Is called for every second of the "ending in" timer.
 */
jazzquiz.onQuestionTimerTick = function() {
    const currentTime = new Date().getTime();
    if (currentTime > this.quiz.question.endTime) {
        this.hideQuestionTimer();
        this.onQuestionTimerEnding();
    } else {
        const timeLeft = parseInt((this.quiz.question.endTime - currentTime) / 1000);
        this.setQuestionTimerText(timeLeft);
    }
};

/**
 * Request the current question from the server.
 * @param {number} questionTime
 */
jazzquiz.startQuestionAttempt = function(questionTime) {
    this.hideInfo();
    this.reloadQuestionBox();
    // Set this to true so that we don't keep calling this over and over.
    this.quiz.question.isRunning = true;
    questionTime = parseInt(questionTime);
    if (questionTime === 0) {
        // 0 means no timer.
        return;
    }
    this.setQuestionTimerText(questionTime);
    this.quiz.question.endTime = new Date().getTime() + questionTime * 1000;
    this.questionTimerInterval = setInterval(function() {
        jazzquiz.onQuestionTimerTick();
    }, 1000);
};
