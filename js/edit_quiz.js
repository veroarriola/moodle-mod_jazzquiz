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
 * Edit quiz javascript to implement drag and drop on the page
 *
 * @package    mod_jazzquiz
 * @author     John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright  2015 University of Wisconsin - Madison
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

var submit_question_order = function(order) {
    if (order.length === 0) {
        return;
    }
    jQuery.post('/mod/jazzquiz/edit.php', {
        id: jazzquiz.quiz.course_module_id,
        action: 'order',
        order: JSON.stringify(order)
    }, function () {
        // TODO: Correct locally instead, but for now just refresh.
        location.reload();
    });
};

var get_question_order = function() {
    var order = [];
    jQuery('.questionlist li').each(function() {
        order.push(jQuery(this).data('questionid'));
    });
    return order;
};

var offset_question = function(question_id, offset) {
    var order = get_question_order();
    var original_index = order.indexOf(question_id);
    if (original_index === -1) {
        return [];
    }
    for (var i = 0; i < order.length; i++) {
        if (i + offset === original_index) {
            order[original_index] = order[i];
            order[i] = question_id;
            break;
        }
    }
    return order;
};

window.addEventListener('load', function() {
    jazzquiz.decode_state();

    // TODO: Timeout because jQuery is not loaded yet when this runs. Modules should be used later on.
    setTimeout(function() {
        jQuery('.edit-question-action').on('click', function() {
            var action = jQuery(this).data('action');
            var question_id = jQuery(this).data('question-id');
            var order = [];
            switch (action) {
                case 'up':
                    order = offset_question(question_id, 1);
                    break;
                case 'down':
                    order = offset_question(question_id, -1);
                    break;
                case 'delete':
                    order = get_question_order();
                    var index = order.indexOf(question_id);
                    if (index !== -1) {
                        order.splice(index, 1);
                    }
                    break;
                default:
                    return;
            }
            submit_question_order(order);
        });
    }, 500);

    var questionlist = document.getElementsByClassName('questionlist')[0];

    var sorted = Sortable.create(questionlist, {
        handle: '.dragquestion',
        onSort: function (event) {
            var order = get_question_order();
            submit_question_order(order);
        }
    });
});
