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

window.addEventListener('load', function () {

    jazzquiz.decode_state();

    var questionList = document.getElementsByClassName('questionlist')[0];

    // TODO: Timeout because jQuery is not loaded yet when this runs. Modules should be used later on.
    setTimeout(function() {
        jQuery('.edit-question-action').on('click', function () {
            var action = jQuery(this).attr('data-action');
            var question_id = jQuery(this).attr('data-question-id');
            jQuery.ajax({
                type: 'get',
                url: '/mod/jazzquiz/edit.php?id=' + jazzquiz.quiz.course_module_id +'&action=' + action + '&questionid=' + question_id,
                success: function (response) {
                    location.reload();
                }
            });
        });
    }, 500);

    var sorted = Sortable.create(questionList, {
        handle: '.dragquestion',
        onSort: function (event) {
            var question_list = document.getElementsByClassName('questionlist')[0];
            var question_order = [];
            for (var i = 0; i < question_list.childNodes.length; i++) {
                var question_id = question_list.childNodes[i].getAttribute('data-questionid');
                question_order.push(question_id);
            }

            var params = {
                action: 'dragdrop',
                questionorder: question_order
            };

            jazzquiz.ajax.create_request('/mod/jazzquiz/edit.php', params, function (status, response) {
                if (status !== HTTP_STATUS.OK) {
                    var editStatus = document.getElementById('editstatus');
                    editStatus.classList.remove('rtqhiddenstatus');
                    editStatus.classList.add('rtqerrorstatus');
                    editStatus.innerHTML = M.util.get_string('error', 'core');
                }
            });
        }
    });
});
