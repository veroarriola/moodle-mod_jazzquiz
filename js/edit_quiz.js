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
    jazzquiz.session_key = window.rtqinitinfo.sesskey;
    jazzquiz.siteroot = window.rtqinitinfo.siteroot;
    jazzquiz.cm_id = window.rtqinitinfo.cmid;

    var questionList = document.getElementsByClassName('questionlist')[0];

    // TODO: Timeout because jQuery is not loaded yet when this runs. Modules should be used later on.
    setTimeout(function() {
        jQuery('.edit-question-action').on('click', function () {
            var action = jQuery(this).attr('data-action');
            var question_id = jQuery(this).attr('data-question-id');
            jQuery.ajax({
                type: 'get',
                url: '/mod/jazzquiz/edit.php?id=' + jazzquiz.cm_id +'&action=' + action + '&questionid=' + question_id,
                success: function (response) {
                    location.reload();
                }
            });
        });
    }, 500);

    var sorted = Sortable.create(questionList, {
        handle: '.dragquestion',
        onSort: function (event) {
            var questionList = document.getElementsByClassName('questionlist')[0];
            var questionOrder = [];

            for (var x = 0; x < questionList.childNodes.length; x++) {
                var questionID = questionList.childNodes[x].getAttribute('data-questionid');
                questionOrder.push(questionID);
            }

            var params = {
                sesskey: jazzquiz.session_key,
                id: jazzquiz.cm_id,
                questionorder: questionOrder,
                action: 'dragdrop'
            };

            jazzquiz.ajax.create_request('/mod/jazzquiz/edit.php', params, function (status, response) {
                var editStatus = document.getElementById('editstatus');
                if (status === 500) {
                    editStatus.classList.remove('rtqhiddenstatus');
                    editStatus.classList.add('rtqerrorstatus');
                    editStatus.innerHTML = M.util.get_string('error', 'core');
                } else if (typeof response !== 'object') {
                    editStatus.classList.remove('rtqhiddenstatus');
                    editStatus.classList.add('rtqerrorstatus');
                    editStatus.innerHTML = response;
                } else {
                    editStatus.classList.remove('rtqhiddenstatus');
                    editStatus.classList.remove('rtqerrorstatus');
                    editStatus.classList.add('rtqsuccessstatus');
                    editStatus.innerHTML = M.util.get_string('success', 'core');
                }
                setTimeout(function () {
                    var editStatus = document.getElementById('editstatus');
                    editStatus.innerHTML = '&nbsp;';
                }, 2000);
            });
        }
    });
});
