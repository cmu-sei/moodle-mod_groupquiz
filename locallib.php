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
 * Private crucible module utility functions
 *
 * @package    mod_groupquiz
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->dirroot/mod/groupquiz/lib.php");
require_once($CFG->libdir . '/questionlib.php');


/**
 * @var int We show no image when user selects No image from dropdown menu in quiz settings.
 */
define('QUIZ_SHOWIMAGE_NONE', 0);

/**
 * @var int We show small image when user selects small image from dropdown menu in quiz settings.
 */
define('QUIZ_SHOWIMAGE_SMALL', 1);

/**
 * An extension of question_display_options that includes the extra options used
 * by the quiz.
 *
 * @copyright  2010 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_groupquiz_display_options extends question_display_options {
    /**#@+
     * @var integer bits used to indicate various times in relation to a
     * quiz attempt.
     */
    const DURING =            0x10000;
    const IMMEDIATELY_AFTER = 0x01000;
    const LATER_WHILE_OPEN =  0x00100;
    const AFTER_CLOSE =       0x00010;
    /**#@-*/

    /**
     * @var boolean if this is false, then the student is not allowed to review
     * anything about the attempt.
     */
    public $attempt = true;

    /**
     * @var boolean if this is false, then the student is not allowed to review
     * anything about the attempt.
     */
    public $overallfeedback = self::VISIBLE;

    /**
     * Set up the various options from the quiz settings, and a time constant.
     * @param object $groupquiz the quiz settings.
     * @param int $one of the {@link DURING}, {@link IMMEDIATELY_AFTER},
     * {@link LATER_WHILE_OPEN} or {@link AFTER_CLOSE} constants.
     * @return mod_groupquiz_display_options set up appropriately.
     */
	// TODO remove if not used
    public static function make_from_groupquiz($groupquiz, $when) {
        $options = new self();

        $options->attempt = self::extract($groupquiz->reviewattempt, $when, true, false);
        $options->correctness = self::extract($groupquiz->reviewcorrectness, $when);
        $options->marks = self::extract($groupquiz->reviewmarks, $when,
                self::MARK_AND_MAX, self::MAX_ONLY);
        $options->feedback = self::extract($groupquiz->reviewspecificfeedback, $when);
        $options->generalfeedback = self::extract($groupquiz->reviewgeneralfeedback, $when);
        $options->rightanswer = self::extract($groupquiz->reviewrightanswer, $when);
        $options->overallfeedback = self::extract($groupquiz->reviewoverallfeedback, $when);
        $options->numpartscorrect = $options->feedback;
	$options->manualcomment = $options->feedback;
	//$options->manualcomment = self::extract($groupquiz->reviewmanualcomment, $when);

        if ($groupquiz->questiondecimalpoints != -1) {
            $options->markdp = $groupquiz->questiondecimalpoints;
        } else {
            $options->markdp = $groupquiz->decimalpoints;
        }

        return $options;
    }

    protected static function extract($bitmask, $bit,
            $whenset = self::VISIBLE, $whennotset = self::HIDDEN) {
        if ($bitmask & $bit) {
            return $whenset;
        } else {
            return $whennotset;
        }
    }
}

/**
 * @param int $groupquizid The quiz id.
 * @return bool whether this groupquiz has any (non-preview) attempts.
 */
function groupquiz_has_attempts($groupquizid) {
    global $DB;
    return $DB->record_exists('groupquiz_attempts', array('groupquizid' => $groupquizid));

}

/**
 * The quiz grade is the maximum that student's results are marked out of. When it
 * changes, the corresponding data in quiz_grades and quiz_feedback needs to be
 * rescaled. After calling this function, you probably need to call
 * quiz_update_all_attempt_sumgrades, quiz_update_all_final_grades and
 * quiz_update_grades.
 *
 * @param float $newgrade the new maximum grade for the quiz.
 * @param object $quiz the quiz we are updating. Passed by reference so its
 *      grade field can be updated too.
 * @return bool indicating success or failure.
 */
function groupquiz_set_grade($newgrade, $groupquiz) {
    global $DB;
    // This is potentially expensive, so only do it if necessary.
    if (abs($groupquiz->grade - $newgrade) < 1e-7) {
        // Nothing to do.
        return true;
    }

    $oldgrade = $groupquiz->grade;
    $groupquiz->grade = $newgrade;

    // Use a transaction, so that on those databases that support it, this is safer.
    $transaction = $DB->start_delegated_transaction();

    // Update the quiz table.
    $DB->set_field('groupquiz', 'grade', $newgrade, array('id' => $groupquiz->instance));

    if ($oldgrade < 1) {
        // If the old grade was zero, we cannot rescale, we have to recompute.
        // We also recompute if the old grade was too small to avoid underflow problems.
        //quiz_update_all_final_grades($quiz);

    } else {
        // We can rescale the grades efficiently.
        $timemodified = time();
        $DB->execute("
                UPDATE {groupquiz_grades}
                SET grade = ? * grade, timemodified = ?
                WHERE groupquizid = ?
        ", array($newgrade/$oldgrade, $timemodified, $groupquiz->id));
    }

    // Update grade item and send all grades to gradebook.
    //groupquiz_grade_item_update($quiz);
    //groupquiz_update_grades($quiz);

    $transaction->allow_commit();
    return true;
}


/**
 * Prints local lib tabs
 *
 * @param \mod_groupquiz\groupquiz $RTQ Realtime quiz class
 * @param                            $currenttab
 *
 * @return string HTML string of the tabs
 */
function groupquiz_view_tabs($RTQ, $currenttab) {
    $tabs = array();
    $row = array();
    $ingroup = array();
    $activated = array();

    if ($RTQ->has_capability('mod/groupquiz:attempt')) {
        $row[] = new tabobject('view', new moodle_url('/mod/groupquiz/view.php', array('id' => $RTQ->getCM()->id)), get_string('view', 'groupquiz'));
    }
    if ($RTQ->has_capability('mod/groupquiz:editquestions')) {
        $row[] = new tabobject('edit', new moodle_url('/mod/groupquiz/edit.php', array('cmid' => $RTQ->getCM()->id)), get_string('edit', 'groupquiz'));
    }
    if ($RTQ->has_capability('mod/groupquiz:manage')) {
        $row[] = new tabobject('reports', new moodle_url('/mod/groupquiz/reports.php', array('id' => $RTQ->getCM()->id)), get_string('responses', 'groupquiz'));
    }

    if ($currenttab == 'view' && count($row) == 1) {
        // No tabs for students
        echo '<br />';
    } else {
        $tabs[] = $row;
    }

    if ($currenttab == 'reports') {
        $activated[] = 'reports';
    }

    if ($currenttab == 'edit') {
        $activated[] = 'edit';
    }

    if ($currenttab == 'view') {
        $activated[] = 'view';
    }

    return print_tabs($tabs, $currenttab, $ingroup, $activated, true);
}

/**
 * Get the choices for what size user picture to show.
 * @return array string => lang string the options for whether to display the user's picture.
 */
function groupquiz_get_user_image_options() {
    return array(
        QUIZ_SHOWIMAGE_NONE  => get_string('shownoimage', 'quiz'),
        QUIZ_SHOWIMAGE_SMALL => get_string('showsmallimage', 'quiz'),
    );
}

/**
 * Get the information about the standard quiz JavaScript module.
 * @return array a standard jsmodule structure.
 */
function groupquiz_get_js_module() {
    global $PAGE;

    return array(
        'name' => 'mod_groupquiz',
        'fullpath' => '/mod/groupquiz/module.js',
        'requires' => array('base', 'dom', 'event-delegate', 'event-key',
                'core_question_engine', 'moodle-core-formchangechecker'),
        'strings' => array(
            array('cancel', 'moodle'),
            array('flagged', 'question'),
            array('functiondisabledbysecuremode', 'groupquiz'),
            array('startattempt', 'groupquiz'),
            array('timesup', 'groupquiz'),
            array('changesmadereallygoaway', 'moodle'),
        ),
    );
}

