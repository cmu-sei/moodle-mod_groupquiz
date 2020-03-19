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
 * @package   mod_groupquiz
 * @copyright 2020 Carnegie Mellon University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
Group Quiz Plugin for Moodle
Copyright 2020 Carnegie Mellon University.
NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS. CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO, WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL. CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Released under a GNU GPL 3.0-style license, please see license.txt or contact permission@sei.cmu.edu for full terms.
[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution.  Please see Copyright notice for non-US Government use and distribution.
This Software includes and/or makes use of the following Third-Party Software subject to its own license:
1. Moodle (https://docs.moodle.org/dev/License) Copyright 1999 Martin Dougiamas.
2. mod_activequiz (https://github.com/jhoopes/moodle-mod_activequiz/blob/master/README.md) Copyright 2014 John Hoopes and the University of Wisconsin.
DM20-0197
 */

// ensure that the namespace is defined
var groupquiz = groupquiz || {};
groupquiz.vars = groupquiz.vars || {};

/**
 * handles the question for the student
 *
 *
 * @param questionid the questionid to handle
 */
groupquiz.handle_question = function (questionid) {

    if (typeof tinyMCE !== 'undefined') {
        tinyMCE.triggerSave();
    }

    // will only work on Modern browsers
    // of course the problem child is always IE...
    var qform = document.forms.namedItem('q' + questionid);
    var formdata = new FormData(qform);

    formdata.append('action', 'savequestion');
    formdata.append('groupquizid', groupquiz.get('groupquizid'));
    formdata.append('attemptid', groupquiz.get('attemptid'));
    formdata.append('sesskey', groupquiz.get('sesskey'));
    formdata.append('questionid', questionid);

    // submit the form
    console.log("saving question");
    groupquiz.ajax.create_request('/mod/groupquiz/quizdata.php', formdata, function (status, response) {

        if (status == 500) {
            groupquiz.quiz_info('There was an error with your request', true);
            window.alert('there was an error with your request ... ');
            return;
        }

        //update the sequence check for the question
        //var sequencecheck = document.getElementsByName(response.seqcheckname);
        //var field = sequencecheck[0];
        //field.value = response.seqcheckval;

        groupquiz.set('submittedanswer', 'true');
    });

};

groupquiz.submit_quiz = function (attempt, groupquizid) {
    var attemptid = attempt.substring(1);
    var qform = document.forms.namedItem(attempt);
    var formdata = new FormData(qform);

    formdata.append('action', 'submitquiz');
    formdata.append('groupquizid', 'groupquizid');
    formdata.append('attemptid', groupquiz.get('attemptid'));
    formdata.append('sesskey', groupquiz.get('sesskey'));

    var params = {
	'action': 'submitquiz',
        'sesskey': groupquiz.get('sesskey'),
        'groupquizid': 'groupquizid',
        'attemptid': groupquiz.get('attemptid')
    };

    // submit the form
    console.log("submitting quiz");

    groupquiz.ajax.create_request('/mod/groupquiz/quizdata.php', params, function (status, response) {
        if (status == 500) {
            window.alert('there was an error with your request ... ');
            return;
        }
	
    });

};
    
