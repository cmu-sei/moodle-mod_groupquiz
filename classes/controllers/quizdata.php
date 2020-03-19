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

class quizdata {

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
            $this->action = required_param('action', PARAM_ALPHANUMEXT);
            $this->pagevars['inquesetion'] = optional_param('inquestion', '', PARAM_ALPHAEXT);

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

        $this->RTQ = new \mod_groupquiz\groupquiz($cm, $course, $groupquiz, $this->pageurl, $this->pagevars);

	// TODO clean this up
        $groups = $this->RTQ->get_groupmanager()->get_user_groups();
        if (count($groups) != 1) {
            echo "error";
            exit;
        }
        $groupid = array_values($groups)[0]->id;
        $this->RTQ->get_group_attempt($groupid);

        if ($this->RTQ->openAttempt->getState() != 'inprogress') {
            $this->jsonlib->send_error('invalidattempt');
        }

    }

    /**
     * Handles the incoming request
     *
     */
    public function handle_request() {
        $jscurrentattempt = required_param('attemptid', PARAM_INT);

        switch ($this->action) {
            case 'startquiz':
                break;
            case 'savequestion':
                $jscurrentquestion = required_param('questionid', PARAM_INT);

                // if we pass attempt to save the question
                $qattempt = $this->RTQ->openAttempt;

                if ($qattempt->save_question()) {
                    $this->jsonlib->set('status', 'successfully saved ');
                    // next we need to send back the updated sequence check for javascript to update
                    // the sequence check on the question form.  this allows the question to be resubmitted again
                    list($seqname, $seqvalue) = $qattempt->get_sequence_check($jscurrentquestion);

                    $this->jsonlib->set('seqcheckname', $seqname);
                    $this->jsonlib->set('seqcheckval', $seqvalue);
                    $this->jsonlib->send_response();
                } else {
                    $this->jsonlib->send_error('unable to save question');
                }

                break;
            case 'submitquiz':
		// TODO this will complete the attempt 
		echo "this will end the attempt";
                //$this->jsonlib->send_error('not implemented');//

                $studentstartformparams = array('rtq' => $this->RTQ, 'group' => $groupid);
                $studentstartform = new \mod_groupquiz\forms\view\student_start_form($this->pageurl, $studentstartformparams);

                $this->RTQ->openAttempt->close_attempt($this->RTQ);
                $this->RTQ->get_renderer()->view_header();
                $this->RTQ->get_renderer()->view_student_home($studentstartform);
                $this->RTQ->get_renderer()->view_footer();

                break;
            default:
                $this->jsonlib->send_error('invalidaction');
                break;
        }
    }
}

