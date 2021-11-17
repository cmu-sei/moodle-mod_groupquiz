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

namespace mod_groupquiz\utils;

defined('MOODLE_INTERNAL') || die();

/**
 * Grading utility class to handle grading functionality
 *
 * @package     mod_groupquiz
 * @copyright   2014 Carnegie Mellon University
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

class grade {

    /** @var \mod_groupquiz\groupquiz */
    protected $rtq;

    /**
     * Gets the user grade, userid can be 0, which will return all grades for the groupquiz
     *
     * @param $groupquiz
     * @param $userid
     * @return array
     */
    public static function get_user_grade($groupquiz, $userid = 0) {
        global $DB;
        $recs = $DB->get_records_select('groupquiz_grades', 'userid = ? AND groupquizid = ?',
	        array($userid, $groupquiz->id), 'grade');
	$grades = array();
	foreach ($recs as $rec) {
	    array_push($grades, $rec->grade);
	}
	return $grades;
/*
        $params = array($groupquiz->id);
        $usertest = '';

        if (is_array($userid)) {
            // we have a group of userids
            if (count($userid) > 0) {
                list($usertest, $uparams) = $DB->get_in_or_equal($userid);
                $params = array_merge($params, $uparams);
                $usertest = 'AND u.id ' . $usertest;
            }
        } else if ($userid) {
            $params[] = $userid;
            $usertest = 'AND u.id = ?';
        }

        return $DB->get_records_sql("
            SELECT
                u.id,
                u.id AS userid,
                rtqg.grade AS rawgrade,
                rtqg.timemodified AS dategraded,
                MAX(rtqa.timefinish) AS datesubmitted

            FROM {user} u
            JOIN {groupquiz_grades} rtqg ON u.id = rtqg.userid
            JOIN {groupquiz_attempts} rtqa ON rtqa.rtqg.groupquizid = rtqg.groupquizid

            WHERE rtqg.groupquizid = ?
            $usertest
            GROUP BY u.id, rtqg.grade, rtqg.timemodified", $params);
 */
    }


    /**
     * Construct for the grade utility class
     *
     * @param \mod_groupquiz\groupquiz $groupquiz
     */
    public function __construct($groupquiz) {
        $this->rtq = $groupquiz;
    }

    /**
     * Save and (re)calculate grades for this RTQ
     *
     * @param bool $regrade_attempts Regrade the question attempts themselves through the question engine
     * @return bool
     */
    public function save_all_grades($regrade_attempts = false, $groupid = null) {

        if (empty($this->rtq->getRTQ()->grouping)) {
            echo "error = cannot find groups";
            return;
	}

        $groups = groups_get_all_groups($this->rtq->getCourse()->id, null, $this->rtq->getRTQ()->grouping);

	foreach ($groups as $group) {
	    $groupid = $group->id;
	    $attempts = $this->rtq->getall_attempts($open = 'closed', $groupid);

	    foreach ($attempts as $attempt) {
                // If we're regrading attempts, send them off to be re-graded before processing all sessions.
                if ($regrade_attempts) {
                    $this->process_attempt_regrade($attempt);
                }
	        $this->save_group_grade($attempt);
	    }
	}
	return true;
    }

    /**
     * Process grades for a specific user
     *
     * @param int $userid
     *
     * @return bool
     */
    public function save_group_grade($attempt) {

	// update gradebook
        $attempts = $this->rtq->getall_attempts($open = 'closed', $attempt->forgroupid);

        // check grading methods
        if ($this->rtq->getRTQ()->grademethod == \mod_groupquiz\utils\scaletypes::groupquiz_FIRSTATTEMPT) {
            // only grading first attempts.
            return $this->process_attempts(array(reset($attempts)), $attempt->forgroupid);
        } else if ($this->rtq->getRTQ()->grademethod == \mod_groupquiz\utils\scaletypes::groupquiz_LASTATTEMPT) {
            // only grading last attempts.

            return $this->process_attempts(array(end($attempts)), $attempt->forgroupid);
        } else {
            // otherwise do all attempts.
            // other grading methods are processed later.

            return $this->process_attempts($attempts, $attempt->forgroupid);
        }

    }

    /**
     * Regrade the question usage attempt for the given attempt.
     *
     * @param $attempt
     */
    public function process_attempt_regrade($attempt) {

        // regrade all questions for the question usage for this attempt.
        $attempt->get_quba()->regrade_all_questions();

	$this->calculate_attempt_grade($attempt);

        $attempt->save();

    }

    /**
     * Separated function to process grading for the provided attempts 
     *
     * @param array $attempts
     * @param int   $userid If specified will only process grading with that particular user
     *
     * @return bool true on success
     */
    protected function process_attempts($attempts, $groupid) {
        global $DB;
        $grades = array();
	$attemptsgrades = array();
        foreach ($attempts as $attempt) {
	    if (is_null($attempt)) {
		continue;
	    }
	    // check attempt state
            if ($attempt->getState() !== 'finished') {
                continue; // don't calculate grades based on open attempts.
            }

	    array_push($attemptsgrades, $attempt->sumgrades);
        }
	// TODO test
	$grade = $this->apply_grading_method($attemptsgrades);

        foreach (groups_get_members($groupid) as $user) {
            $uid = $user->id;
            $grades[ $uid ] = $grade;
        }

        // run the whole thing on a transaction (persisting to our table and gradebook updates).
        $transaction = $DB->start_delegated_transaction();

        // now that we have the final grades persist the grades to groupquiz grades table.
        $this->persist_grades($grades, $transaction);

        // update grades to gradebookapi.
        $updated = groupquiz_update_grades($this->rtq->getRTQ(), array_keys($grades));

        if ($updated === GRADE_UPDATE_FAILED) {
            $transaction->rollback(new \Exception('Unable to save grades to gradebook'));
        }

        // Allow commit if we get here
        $transaction->allow_commit();

        // if everything passes to here return true
        return true;
    }


    /**
     * Get the attempt's grade
     *
     * For now this will always be the last attempt for the user
     *
     * @param \mod_groupquiz\groupquiz_attempt $attempt
     * @param int                                $userid The userid to get the grade for
     * @return array($forgroupid, $number)
     */
    protected function get_attempt_grade($attempt) {
        return array($attempt->forgroupid, $this->calculate_attempt_grade($attempt));
    }


    /**
     * Applies the grading method chosen
     *
     * @param array $grades The grades for each session for a particular user
     * @return number
     * @throws \Exception When there is no valid scaletype throws new exception
     */
    protected function apply_grading_method($grades) {
        switch ($this->rtq->getRTQ()->grademethod) {
            case \mod_groupquiz\utils\scaletypes::groupquiz_FIRSTATTEMPT:
                // take the first record (as there should only be one since it was filtered out earlier)
                reset($grades);
                return current($grades);

                break;
            case \mod_groupquiz\utils\scaletypes::groupquiz_LASTATTEMPT:
                // take the last grade (there should only be one, as the last session was filtered out earlier)
                return end($grades);

                break;
            case \mod_groupquiz\utils\scaletypes::groupquiz_ATTEMPTAVERAGE:
                // average the grades
                $gradecount = count($grades);
                $gradetotal = 0;
                foreach ($grades as $grade) {
                    $gradetotal = $gradetotal + $grade;
                }
                return $gradetotal / $gradecount;

                break;
            case \mod_groupquiz\utils\scaletypes::groupquiz_HIGHESTATTEMPTGRADE:
                // find the highest grade
                $highestgrade = 0;
                foreach ($grades as $grade) {
                    if ($grade > $highestgrade) {
                        $highestgrade = $grade;
                    }
                }
                return $highestgrade;

                break;
            default:
                throw new \Exception('Invalid session grade method');
                break;
        }
    }

    /**
     * Calculate the grade for attempt passed in
     *
     * This function does the scaling down to what was desired in the groupquiz settings
     * from what the quiz was actually set up with
     *
     * Is public function so that tableviews can get an attempt calculated grade
     *
     * @param \mod_groupquiz\groupquiz_attempt $attempt
     * @return number The grade to save
     */
    public function calculate_attempt_grade($attempt) {

        $quba = $attempt->get_quba();

        $totalpoints = 0;
        $totalslotpoints = 0;
        foreach ($attempt->getSlots() as $slot) {
            $totalpoints = $totalpoints + $quba->get_question_max_mark($slot);
            $slotpoints = $quba->get_question_mark($slot);
            if (!empty($slotpoints)) {
                $totalslotpoints = $totalslotpoints + $slotpoints;
            }
        }
        //$attempt->sumgrades = $totalslotpoints;

	$scaledpoints = ($totalslotpoints / $totalpoints) *  $this->rtq->getRTQ()->grade;

        // TODO maybe dont save here
        $attempt->sumgrades = $scaledpoints;
        $attempt->save();
	return $scaledpoints;

        // use cross multiplication to scale to the desired points
        //$scaledpoints = ($totalslotpoints * $this->rtq->getRTQ()->sumgrades) / $totalpoints;
	//var_dump($totalslotpoints);
	//var_dump($totalpoints);
	//var_dump($this->rtq->getRTQ()->sumgrades);
	//var_dump($scaledpoints);
        //return $scaledpoints;

    }

    /**
     * Persist the passed in grades (keyed by userid) to the database
     *
     * @param array               $grades
     * @param \moodle_transaction $transaction
     *
     * @return bool
     */
    protected function persist_grades($grades, \moodle_transaction $transaction) {
        global $DB;

        foreach ($grades as $userid => $grade) {

            if ($usergrade = $DB->get_record('groupquiz_grades', array('userid' => $userid, 'groupquizid' => $this->rtq->getRTQ()->id))) {
                // we're updating

                $usergrade->grade = $grade;
                $usergrade->timemodified = time();

                if (!$DB->update_record('groupquiz_grades', $usergrade)) {
                    $transaction->rollback(new \Exception('Can\'t update user grades'));
                }
            } else {
                // we're adding

                $usergrade = new \stdClass();
                $usergrade->groupquizid = $this->rtq->getRTQ()->id;
                $usergrade->userid = $userid;
                $usergrade->grade = $grade;
                $usergrade->timemodified = time();

                if (!$DB->insert_record('groupquiz_grades', $usergrade)) {
                    $transaction->rollback(new \Exception('Can\'t insert user grades'));
                }

            }
        }

        return true;
    }


    /**
     * Get the last attempt of an attempts array.  Will be sorted by time finish first
     *
     * @param array $attempts
     *
     * @return \mod_groupquiz\groupquiz_attempt
     */
    protected function get_last_attempt($attempts) {

        // sort attempts by time finish
        usort($attempts, array('\mod_groupquiz\groupquiz_attempt', 'sortby_timefinish'));

        return end($attempts);

    }

    /**
     * Figures out the grades for group members if the quiz was taken in group mode
     *
     * IMPORTANT: IF A USER IS IN MORE THAN 1 GROUP FOR THE SPECIFIED GROUPING, THIS FUNCTION
     * WILL TAKE THE HIGHER OF THE 2 GRADES THAT WILL BE GIVEN TO THEM
     *
     * @param array  $attemptsgrades Array of session grades being built for user
     * @param int    $forgroupid
     * @param number $grade
     * @param int    $uid
     * @param int    $attemptid
     */
    protected function calculate_group_grades(&$attemptsgrades, $forgroupid, $grade, $uid, $attemptid) {

        if (empty($forgroupid)) {
            // just add the grade for the userid to the sessiongrades
            $attemptsgrades[ $uid ][ $attemptid ] = $grade;

            return;
        }
        $groupusers = $this->rtq->get_groupmanager()->get_group_members($forgroupid);
        foreach ($groupusers as $guser) {
            $this->add_group_grade($attemptsgrades, $guser->id, $attemptid, $grade);
        }
    }

    /**
     * Figure out the grade to add to the particular group user.
     *
     * If they already have a grade for the session, check to see if the new grade is higher and,
     * if so, make that grade their grade instead of the lower grade.  This is a "fix" for the possibility
     * that a user is in more than 1 group that attempted the quiz
     *
     * @param array  $attemptsgrades
     * @param int    $guserid
     * @param int    $attemptid
     * @param number $grade
     */
    protected function add_group_grade(&$attemptsgrades, $guserid, $attemptid, $grade) {
        // check to see if a session grade already exists for the userid
        // this could happen if the user is a part of more than 1 group
        if (isset($attemptsgrades[ $guserid ][ $attemptid ])) {
            // if it does, then only replace the grade with the new grade if its higher
            if ($attemptsgrades[ $guserid ][ $attemptid ] < $grade) {
                $attemptsgrades[ $guserid ][ $attemptid ] = $grade;
            }
        } else { // if no grade is present for this user and session add the grade for the user
            if (!isset($attemptsgrades[ $guserid ])) { // this is for a group member who hasn't gotten a grade
                $attemptsgrades[ $guserid ] = array();
            }
            $attemptsgrades[ $guserid ][ $attemptid ] = $grade;
        }
    }

    public function update_gradebook_for_group($attempt) {
	$forgroupid = $attempt->forgroupid;
	$groupusers = $this->rtq->get_groupmanager()->get_group_members($forgroupid);
        foreach ($groupusers as $guser) {
            $this->add_group_grade($attemptsgrades, $guser->id, $attemptid, $grade);
	    $this->persist_grades($grades);
	    
        }
	$this->persist_grades($grades);

    }
}

