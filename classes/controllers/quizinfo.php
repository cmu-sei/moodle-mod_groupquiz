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

namespace mod_groupquiz\controllers;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/lib.php');

/**
 * The controller for handling quiz data callbacks from javascript
 *
 * @package     mod_groupquiz
 * @copyright   2020 Carnegie Mellon University
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

class quizinfo {

    /** @var \mod_groupquiz\groupquiz Realtime quiz class */
    protected $RTQ;

    /** @var string $action The specified action to take */
    protected $action;

    /** @var object $context The specific context for this activity */
    protected $context;

    /** @var \moodle_url $pageurl The page url to base other calls on */
    protected $pageurl;

    /** @var array $this ->pagevars An array of page options for the page load */
    protected $pagevars = array();

    /** @var \mod_groupquiz\utils\jsonlib $jsonlib The jsonlib for returning json */
    protected $jsonlib;

    /**
     * set up the class for the view page
     *
     * @throws \moodle_exception throws exception on error in setting up initial vars when debugging
     */
    public function setup_page() {
        global $DB, $PAGE, $USER;

        $jscurrentquestion = required_param('groupquizid', PARAM_INT);
        $jscurrentattempt = required_param('attemptid', PARAM_INT);

        // no page url as this is just a callback.
        $this->pageurl = null;
        $this->jsonlib = new \mod_groupquiz\utils\jsonlib();


        // first check if this is a jserror, if so, log it and end execution so we're not wasting time.
        $jserror = optional_param('jserror', '', PARAM_ALPHANUMEXT);
        if (!empty($jserror)) {
            // log the js error on the apache error logs
            error_log($jserror);

            // set a status and send it saying that we logged the error.
            $this->jsonlib->set('status', 'loggedjserror');
            $this->jsonlib->send_response();
        }

        // use try/catch in order to catch errors and not display them on a javascript callback.
        try {
            $groupquizid = required_param('groupquizid', PARAM_INT);
            $attemptid = required_param('attemptid', PARAM_INT);

            // only load things asked for, don't assume that we're loading whatever.
            $groupquiz = $DB->get_record('groupquiz', array('id' => $groupquizid), '*', MUST_EXIST);
            $course = $DB->get_record('course', array('id' => $groupquiz->course), '*', MUST_EXIST);
            $cm = get_coursemodule_from_instance('groupquiz', $groupquiz->id, $course->id, false, MUST_EXIST);

            require_login($course->id, false, $cm, false, true);
        } catch(\moodle_exception $e) {
            if (debugging()) { // if debugging throw error as normal.
                throw new $e;
            } else {
                $this->jsonlib->send_error('invalid request');
            }
            exit(); // stop execution.
        }

        $this->pagevars['pageurl'] = $this->pageurl;
        $this->pagevars['action'] = $this->action;
	//$this->pagevars['attemptid'] = $this->

        $this->RTQ = new \mod_groupquiz\groupquiz($cm, $course, $groupquiz, $this->pageurl, $this->pagevars);

	// TODO clean this up
        $groups = $this->RTQ->get_groupmanager()->get_user_groups();
        if (count($groups) != 1) {
            echo "error";
	    $this->jsonlib->send_error('get_user_groups');
            exit;
        }
        $groupid = array_values($groups)[0]->id;
        $this->RTQ->get_group_attempt($groupid);

	if (!$this->RTQ->openAttempt) {
	    //TODO this isnt 100% accurate
	    $this->jsonlib->send_error('attemptclosed');
        } else if ($this->RTQ->openAttempt->getState() != 'inprogress') {
            $this->jsonlib->send_error('invalidattempt');
        }

    }

    /**
     * Handles the incoming request
     *
     */
    public function handle_request() {

	global $DB;
	$questions = $this->RTQ->openAttempt->get_questions();
	$quba = $this->RTQ->openAttempt->get_quba();

        $responses = array();
	$attempt = $this->RTQ->openAttempt;
        foreach ($attempt->getSlots() as $slot) {
            // render question form.
            $response = new \stdClass();
            $response->qnum = $slot;
	    $response->html = $this->RTQ->get_renderer()->render_question_form($slot, $attempt);
            list($seqname, $seqvalue) = $attempt->get_sequence_check($slot);
            $response->seqcheckname = $seqname;
            $response->seqcheckval = $seqvalue;

            array_push($responses, $response);
        }

	// get openclose state
        $state = $this->RTQ->get_openclose_state();

        // get timeleft
        $timenow = time();
        $timeleft = $attempt->get_time_left_display($timenow, $this->RTQ->getRTQ());

	// check if we need to end quiz
	if ((!$timeleft) || ($state == 'closed')) {
            $this->RTQ->openAttempt->close_attempt($this->RTQ, false);
            $this->RTQ->get_grader()->calculate_attempt_grade($attempt);
            $this->RTQ->get_grader()->save_group_grade($attempt);

            $this->jsonlib->send_error('attemptclosed timeleft ' . $timeleft . 'state ' . $state);
	}

	if ($timeleft > 0) {
            $this->jsonlib->set('timeleft', json_encode($timeleft));
	}

        if ($this->RTQ->openAttempt) {
            $this->jsonlib->set('status', json_encode($responses));
	    $this->jsonlib->send_response();
	} else {
            $this->jsonlib->send_error('unknown');
	}
    }
}

