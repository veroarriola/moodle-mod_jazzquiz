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

let jazzquiz = {

    state: '',
    is_new_state: false,
    is_instructor: false,
    siteroot: '',

    current_responses: [],
    total_responses: 0,
    chosen_question_id: 0,

    question_countdown_interval: 0,
    question_timer_interval: 0,

    jquery_errors: 0,

    quiz: {

        course_module_id: 0,
        activity_id: 0,
        session_id: 0,
        attempt_id: 0,
        session_key: '',
        show_votes_upon_review: false,
        responded_count: 0,
        total_students: 0,

        question: {
            is_running: false,
            is_last: false,
            is_saving: false,
            end_time: 0,
            is_vote_running: false,
            has_votes: false,
            countdown_time_left: 0,
            question_type: undefined,
            question_time: 0
        }

    },

    options: {
        show_responses: false,
        is_showing_correct_answer: false
    },

    // Student temporary variables
    vote_answer: undefined

};

jazzquiz.ajax = function(method, url, data, success) {
    data.id = this.quiz.course_module_id;
    data.quizid = this.quiz.activity_id;
    data.sessionid = this.quiz.session_id;
    data.attemptid = this.quiz.attempt_id;
    data.sesskey = this.quiz.session_key;
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

jazzquiz.get = function(url, data, success) {
    return this.ajax('get', url, data, success);
};

jazzquiz.post = function(url, data, success) {
    return this.ajax('post', url, data, success);
};

jazzquiz.request_quiz_info = function() {
    jazzquiz.get('quizinfo.php', {}, function(data) {
        // Change the local state
        jazzquiz.change_quiz_state(data.status, data);
        // Schedule next update
        // TODO: Remove this if statement, and rather have a time defined in the specific javascript files.
        // The instructor has a higher update frequency since there is usually only one,
        // but students might be in the hundreds, so we want to limit them to every couple seconds instead.
        if (jazzquiz.is_instructor) {
            setTimeout(jazzquiz.request_quiz_info, 500);
        } else {
            setTimeout(jazzquiz.request_quiz_info, 2000);
        }
    });
};

// Note: ES2015 supports default arguments. Update in the future.
jazzquiz.text = function(key, from, args) {
    from = (typeof from !== 'undefined') ? from : 'jazzquiz';
    args = (typeof args !== 'undefined') ? args : [];
    return M.util.get_string(key, from, args);
};

/**
 * Show the loading animation with some text.
 * @param {string} text
 */
jazzquiz.show_loading = function(text) {
    jQuery('#jazzquiz_loading').removeClass('hidden').children('p').html(text);
};

/**
 * Hide the loading animation.
 */
jazzquiz.hide_loading = function() {
    jQuery('#jazzquiz_loading').addClass('hidden');
};

// TODO: This should hide only the info.
jazzquiz.hide_info = function() {
    // TODO: Use a class on all the boxes to avoid this if
    if (this.is_instructor) {
        jQuery('#jazzquiz_responded_container').addClass('hidden').find('h4').html('');
        jQuery('#jazzquiz_response_info_container').addClass('hidden').html('');
        jQuery('#jazzquiz_responses_container').addClass('hidden').html('');
    }
    jQuery('#jazzquiz_question_timer').addClass('hidden').html('');
    jQuery('#jazzquiz_info_container').addClass('hidden').html('');
};

/**
 * @param {string} text
 */
jazzquiz.show_info = function(text) {
    jQuery('#jazzquiz_info_container').removeClass('hidden').html(text);
};

jazzquiz.render_all_mathjax = function() {
    Y.fire(M.core.event.FILTER_CONTENT_UPDATED, {
        nodes: new Y.NodeList(document.getElementsByClassName('jazzquiz-latex-wrapper'))
    });
};

jazzquiz.add_mathjax_element = function(id, latex) {
    jQuery('#' + id).html('<span class="jazzquiz-latex-wrapper"><span class="filter_mathjaxloader_equation">' + latex + '</span></span>');
    this.render_all_mathjax();
};

jazzquiz.render_maxima_equation = function(input, target_id) {
    const target = document.getElementById(target_id);
    if (target === null) {
        console.log('Target element #' + target_id + ' not found.');
        return;
    }
    this.get('stack.php', {
        input: encodeURIComponent(input)
    }, function(data) {
        jazzquiz.add_mathjax_element(target_id, data.latex);
    }).fail(function() {
        console.log('Failed to get LaTeX for #' + target_id);
    });
};

// TODO: Is there a more elegant way to do this?
jazzquiz.get_question_body_formatted = function() {
    const $original = jQuery('#jazzquiz_question_box');
    if (!$original.length) {
        return 'Not found';
    }
    let $question_box = $original.clone();
    $question_box.find('.info').remove();
    $question_box.find('.im-controls').remove();
    $question_box.find('.questiontestslink').remove();
    $question_box.find('input').remove();
    $question_box.find('label').remove(); // Some inputs have labels
    $question_box.find('.ablock.form-inline').remove();
    $question_box.find('.save_row').remove();
    return $question_box.html();
};

/**
 * Load data such as session key and quiz state.
 */
jazzquiz.decode_state = function() {
    for (let prop in jazzquiz_root_state) {
        if (jazzquiz_root_state.hasOwnProperty(prop)) {
            this[prop] = jazzquiz_root_state[prop];
        }
    }
    for (let prop in jazzquiz_quiz_state) {
        if (jazzquiz_quiz_state.hasOwnProperty(prop)) {
            this.quiz[prop] = jazzquiz_quiz_state[prop];
        }
    }
};

/**
 * Callback for when the quiz page is fully loaded
 */
jazzquiz.quiz_page_loaded = function() {
    // Wait for jQuery
    if (!window.jQuery) {
        console.log('Waiting for jQuery... Trying again in 50ms');
        this.jquery_errors++;
        if (this.jquery_errors > 50) {
            location.reload(true);
        }
        setTimeout(function() {
            jazzquiz.quiz_page_loaded();
        }, 50);
        return;
    }

    this.decode_state();
    this.hide_loading();

    if (this.is_instructor) {
        jQuery('#jazzquiz_controls_box').removeClass('hidden');
    }

    this.request_quiz_info();

    jQuery(document).on('submit', '#jazzquiz_question_form', function(event) {
        event.preventDefault();
        jazzquiz.submit_answer();
    });
};

jazzquiz.clear_question_box = function() {
    jQuery('#jazzquiz_question_box').html('').addClass('hidden');
};

/**
 * Request the current question form.
 */
jazzquiz.reload_question_box = function() {
    this.get('quizdata.php', {
        action: 'get_question_form'
    }, function(data) {
        jazzquiz.quiz.question.question_type = data.question_type;
        if (data.is_already_submitted) {
            jazzquiz.show_info(jazzquiz.text('wait_for_instructor'));
            return;
        }
        jQuery('#jazzquiz_question_box').html(data.html).removeClass('hidden');
        eval(data.js);
    }).fail(function() {
        this.show_info('Failed to load question.');
    });
};

/**
 * Hide the question "ending in" timer, and clears the interval.
 */
jazzquiz.hide_question_timer = function() {
    jQuery('#jazzquiz_question_timer').html('').addClass('hidden');
    if (this.question_timer_interval !== 0) {
        clearInterval(this.question_timer_interval);
        this.question_timer_interval = 0;
    }
};

/**
 * Set time remaining for the question.
 * @param {number} time_left in seconds.
 */
jazzquiz.set_question_timer_text = function(time_left) {
    let $timer = jQuery('#jazzquiz_question_timer');
    if (this.is_instructor) {
        $timer.html(time_left + 's left');
    } else {
        $timer.html(this.text('question_will_end_in_x_seconds', 'jazzquiz', time_left));
    }
    $timer.removeClass('hidden');
};

/**
 * Set time remaining until the question starts.
 * @param {number} time_left in seconds.
 */
jazzquiz.set_countdown_timer_text = function(time_left) {
    if (time_left !== 0) {
        this.show_info(this.text('question_will_start_in_x_seconds', 'jazzquiz', time_left));
    } else {
        this.show_info(this.text('question_will_start_now'));
    }
};

/**
 * Is called for every second of the question countdown.
 */
jazzquiz.on_question_countdown_tick = function(question_time) {
    this.quiz.question.countdown_time_left--;
    if (this.quiz.question.countdown_time_left <= 0) {
        clearInterval(this.question_countdown_interval);
        this.question_countdown_interval = 0;
        this.start_question_attempt(question_time);
    } else {
        this.set_countdown_timer_text(this.quiz.question.countdown_time_left);
    }
};

/**
 * Start a countdown for the question which will eventually start the question attempt.
 * The question attempt might start before this function return, depending on the arguments.
 * If a countdown has already been started, this call will return true and the current countdown will continue.
 * @param {number} question_time
 * @param {number} countdown_time_left
 * @return {boolean} true if countdown is active
 */
jazzquiz.start_question_countdown = function(question_time, countdown_time_left) {
    if (this.question_countdown_interval !== 0) {
        return true;
    }
    question_time = parseInt(question_time);
    countdown_time_left = parseInt(countdown_time_left);
    this.quiz.question.countdown_time_left = countdown_time_left;
    if (countdown_time_left < 1) {
        // Check if the question has already ended.
        if (countdown_time_left < -question_time) {
            return false;
        }
        // We want to show some text, as we must also request the question form from the server.
        this.set_countdown_timer_text(0);
        // No need to start the countdown. Just start the question.
        this.start_question_attempt(question_time + countdown_time_left);
        return true;
    }
    this.set_countdown_timer_text(countdown_time_left);
    this.question_countdown_interval = setInterval(function() {
        jazzquiz.on_question_countdown_tick(question_time);
    }, 1000);
    return true;
};

/**
 * When the question "ending in" timer reaches 0 seconds, this will be called.
 */
jazzquiz.on_question_timer_ending = function() {
    this.quiz.question.is_running = false;
    if (this.is_instructor) {
        this.end_question();
    }
};

/**
 * Is called for every second of the "ending in" timer.
 */
jazzquiz.on_question_timer_tick = function() {
    const current_time = new Date().getTime();
    if (current_time > this.quiz.question.end_time) {
        this.hide_question_timer();
        this.on_question_timer_ending();
    } else {
        const time_left = parseInt((this.quiz.question.end_time - current_time) / 1000);
        this.set_question_timer_text(time_left);
    }
};

/**
 * Request the current question from the server.
 * @param {number} question_time
 */
jazzquiz.start_question_attempt = function(question_time) {
    this.hide_info();
    this.reload_question_box();
    // Set this to true so that we don't keep calling this over and over
    this.quiz.question.is_running = true;
    if (question_time === 0) {
        // 0 means no timer.
        return;
    }
    this.set_question_timer_text(question_time);
    this.quiz.question.end_time = new Date().getTime() + question_time * 1000;
    this.question_timer_interval = setInterval(function() {
        jazzquiz.on_question_timer_tick();
    }, 1000);
};
