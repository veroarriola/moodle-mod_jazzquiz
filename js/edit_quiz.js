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
 * @author     Sebastian S. Gundersen <sebastsg@stud.ntnu.no>
 * @copyright  2015 University of Wisconsin - Madison
 * @copyright  2018 NTNU
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function submit_question_order(order) {
    jQuery.post('edit.php', {
        id: jazzquiz.quiz.course_module_id,
        action: 'order',
        order: JSON.stringify(order)
    }, function () {
        // TODO: Correct locally instead, but for now just refresh.
        location.reload();
    });
}

function get_question_order() {
    let order = [];
    jQuery('.questionlist li').each(function() {
        order.push(jQuery(this).data('question-id'));
    });
    return order;
}

function offset_question(question_id, offset) {
    let order = get_question_order();
    let original_index = order.indexOf(question_id);
    if (original_index === -1) {
        return order;
    }
    for (let i = 0; i < order.length; i++) {
        if (i + offset === original_index) {
            order[original_index] = order[i];
            order[i] = question_id;
            break;
        }
    }
    return order;
}

window.addEventListener('load', function() {
    jazzquiz.decode_state();

    // TODO: Timeout because jQuery is not loaded yet when this runs. Modules should be used later on.
    setTimeout(function() {
        jQuery('.edit-question-action').on('click', function() {
            const action = jQuery(this).data('action');
            const question_id = jQuery(this).data('question-id');
            let order = [];
            switch (action) {
                case 'up':
                    order = offset_question(question_id, 1);
                    break;
                case 'down':
                    order = offset_question(question_id, -1);
                    break;
                case 'delete':
                    order = get_question_order();
                    const index = order.indexOf(question_id);
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

    let questionlist = document.getElementsByClassName('questionlist')[0];
    Sortable.create(questionlist, {
        handle: '.dragquestion',
        onSort: function () {
            const order = get_question_order();
            submit_question_order(order);
        }
    });
});
