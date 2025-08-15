<?php
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
 * Language file.
 *
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

// This line protects the file from being accessed by a URL directly.
defined('MOODLE_INTERNAL') || die();

$string['modulename'] = "Group Quiz";
$string['modulename_help'] = '<p>Group mode collabortive quiz.</p>
<p>All regular quiz question types can be used but types can be disabled by the administrator. Instructors can monitor participation. Students on the same group share the same attempt and will see responses from their group members in realtime.</p>
<p>Each quiz attempt is marked automatically like a regular quiz (with the exception of essay and PoodLL questions) and the grade is recorded in the gradebook.</p>
<p>The instructor has options to show hints, give feedback and show correct answers to students upon quiz completion.</p>
<p>Group Quizzes may be used as a vehicle for delivering Team Based Learning inside Moodle.</p>` ';
$string['modulename_link'] = 'mod/groupquiz/view';
$string['modulenameplural'] = 'Group Quizzes';
$string['pluginname'] = 'Group Quiz';
$string['timing'] = 'Timing';
$string['pluginadministration'] = 'Group Quiz administration';
$string['shuffleanswers'] = 'Shuffle answers';
$string['questionbehaviour'] = 'Question behaviour';

// Time options
$string['quizopen'] = 'Start Quiz';
$string['quizclose'] = 'Close the quiz';
$string['timelimit'] = 'Time limit';
$string['timelimit_help'] = 'If enabled, the time limit is stated on the initial quiz page and a countdown timer is displayed in the quiz navigation block.';

// Review options
$string['review'] = 'Review';
$string['reviewafter'] = 'Allow review after quiz is closed';
$string['reviewalways'] = 'Allow review at any time';
$string['reviewattempt'] = 'Review attempt';
$string['reviewbefore'] = 'Allow review while quiz is open';
$string['reviewclosed'] = 'After the quiz is closed';
$string['reviewduring'] = 'During the attempt';
$string['reviewimmediately'] = 'Immediately after the attempt';
$string['reviewnever'] = 'Never allow review';
$string['reviewofattempt'] = 'Review of attempt {$a}';
$string['reviewofpreview'] = 'Review of preview';
$string['reviewofquestion'] = 'Review of question {$a->question} in {$a->quiz} by {$a->user}';
$string['reviewopen'] = 'While the quiz is open';
$string['reviewoptions'] = 'Students may review';
$string['reviewoptionsheading'] = 'Review options';
$string['reviewoptionsheading_help'] = 'These options control what information students can see when they review a quiz attempt or look at the quiz reports.

**During the attempt** settings are only relevant for some behaviours, like \'interactive with multiple tries\', which may display feedback during the attempt.

**Immediately after the attempt** settings apply for the first two minutes after \'Submit all and finish\' is clicked.

**Later, while the quiz is still open** settings apply after this, and before the quiz close date.

**After the quiz is closed** settings apply after the quiz close date has passed. If the quiz does not have a close date, this state is never reached.';
$string['reviewoverallfeedback'] = 'Overall feedback';
$string['reviewoverallfeedback_help'] = 'The feedback given at the end of the attempt, depending on the student\'s total mark.';
$string['reviewresponse'] = 'Review response';
$string['reviewresponsetoq'] = 'Review response (question {$a})';
$string['reviewthisattempt'] = 'Review your responses to this attempt';
$string['everythingon'] = 'Everything on';

// activity settings
$string['configtimelimit'] = 'Default time limit for quizzes in minutes. 0 mean no time limit.';
$string['configtimelimitsec'] = 'Default time limit for quizzes in seconds. 0 mean no time limit.';
$string['shufflewithin'] = 'Shuffle within questions';
$string['shufflewithin_help'] = 'If enabled, the parts making up each question will be randomly shuffled each time a student attempts the quiz, provided the option is also enabled in the question settings. This setting only applies to questions that have multiple parts, such as multiple choice or matching questions.';
$string['configshufflewithin'] = 'If you enable this option, then the parts making up the individual questions will be randomly shuffled each time a student starts an attempt at this quiz, provided the option is also enabled in the question settings.';
$string['configintro'] = 'The values you set here define the default values that are used in the settings form when you create a new quiz. You can also configure which quiz settings are considered advanced.';
$string['introduction'] = 'Description';
$string['theattempt'] = 'The attempt';
$string['theattempt_help'] = 'Whether the student can review the attempt at all.';
$string['marks'] = 'Marks';
$string['marks_help'] = 'The numerical marks for each question, and the overall attempt score.';
$string['noquestions'] = 'No questions have been added yet';
$string['editgroupquiz'] = 'Edit quiz';
$string['view'] = 'View quiz';
$string['edit'] = 'Edit quiz';
$string['manualcomment'] = 'Manual Comment';
$string['manualcomment_help'] = 'The comment that instructors can add when grading an attempt';
$string['grouping'] = 'Grouping';
$string['grouping_help'] = 'The set of groups to be used for sharing attempts.';
$string['configshowuserpicture'] = 'Show the user\'s picture on screen during attempts.';
$string['showuserpicture'] = 'Show the user\'s picture';
$string['showuserpicture_help'] = 'If enabled, the student\'s name and picture will be shown on-screen during the attempt, and on the review screen, making it easier to identify the student that answered the question most recenttly.';
$string['enabledquestiontypes'] = 'Enable question types';
$string['enabledquestiontypes_info'] = 'Question types that are enabled for use within instances of the group quiz activity.';
$string['grademethod'] = 'Grading method';
$string['grademethod_help'] = 'The grading method defines how the grade for a single attempt of the activity is determined.';
$string['grademethoddesc'] = 'The grading method defines how the grade for a single attempt of the activity is determined.';
$string['firstattempt'] = 'First attempt';
$string['lastattempt'] = 'Last completed attempt';
$string['highestattempt'] = 'Highest attempt';
$string['attemptaverage'] = 'Average of all attempts';
$string['overallfeedback'] = 'Overall feedback';
$string['overallfeedback_help'] = 'Overall feedback is text that is shown after a quiz has been attempted. By specifying additional grade boundaries (as a percentage or as a number), the text shown can depend on the grade obtained.';

// edit page.
$string['questionlist'] = 'Question List';
$string['addselectedquestionstogroupquiz'] = 'Add Questions to Group Quiz';
$string['question'] = 'Question ';
$string['addquestion'] = 'Add question';
$string['questiondelete'] = 'Delete question {$a}';
$string['questionmovedown'] = 'Move question {$a} down';
$string['questionmoveup'] = 'Move question {$a} up';
$string['points'] = 'Question Points';
$string['points_help'] = 'The number of points you\'d like this question to be worth';
$string['qmovesuccess'] = 'Successfully moved question';
$string['qmoveerror'] = 'Couldn\'t move question';
$string['qdeletesucess'] = 'Successfully deleted question';
$string['qdeleteerror'] = 'Couldn\'t delete question';
$string['questionedit'] = 'Edit question';
$string['savequestion'] = 'Save question';
$string['cantaddquestiontwice'] = 'You can not add the same question more than once to a quiz';
$string['cannoteditafterattempts'] = 'You cannot add or remove questions because this quiz has been attempted.';
$string['invalid_points'] = 'Invalid point value';


// view page
$string['invalidquiz'] = 'Invalid groupquiz ID.';
$string['no_questions'] = 'There are no questions added to this quiz.';
$string['continuequiz'] = 'Continue Quiz';
$string['notingroup'] = 'User is not a member of this group.';
$string['showstudentresponses'] = 'Show responses';
$string['hidestudentresponses'] = 'Hide responses';
$string['loading'] = 'Initializing Quiz';
$string['studentquizinst'] = 'Click Save Question on each question to record or update your group\'s response. Your group\'s responses will be visible to all members of the group. Discuss the questions with your group while answering them. Ensure that your group members are in agreement before submitting the quiz. Once all questions have a saved response, one group member may submit the quiz by clicking the Submit Quiz button.';
$string['submitquiz'] = 'Submit Quiz';
$string['startquiz'] = 'Start Quiz';
$string['attempts'] = 'Attempts';
$string['responses'] = 'View responses';
$string['notopen'] = 'Quiz Opens at: ';
$string['closed'] = 'Quiz Closed at: ';
$string['overallgrade'] = 'Overall Grade: {$a}';
$string['eventattemptstarted'] = 'Group Quiz attempt started';
$string['eventattemptended'] = 'Group Quiz attempt ended';
$string['eventattemptviewed'] = 'Group Quiz attempt viewed';
$string['continueinst'] = 'Press Continue to join your group\'s active quiz attempt.';
$string['startinst'] = 'Press Start to begin your group\'s quiz attempt.';
$string['noattempts'] = 'No group attempts exist for this quiz.';
$string['savereminder'] = 'Ensure that each question has been saved before submitting the quiz.';
$string['usernotingroup'] = 'You must be assigned to a group to access this quiz.';
$string['previewmode'] = 'You are viewing this quiz in preview mode.';

// review page
$string['noreview'] = 'You are not able to review the quiz attempt at this time.';
$string['noattempt'] = 'attempid is invalid';

// attempts table.
$string['timestarted'] = 'Time Started';
$string['timecompleted'] = 'Time Completed';
$string['timemodified'] = 'Time Modified';
$string['attempt_grade'] = 'Attempt Grade';
// ownattempts table
$string['attemptview'] = 'Review Attempt';
$string['grade'] = 'Grade';

// allattempts table
$string['response_attempt_controls'] = 'Edit/View Attempt';

// roles
$string['groupquiz:manage'] = 'Manage Group Quizzes';
$string['groupquiz:view'] = 'View Group Quiz information';
$string['groupquiz:addinstance'] = 'Add a new Group Quiz';



