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
 * view controller class for the view page
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

class view {

    /** @var \mod_groupquiz\groupquiz Realtime quiz class */
    protected $RTQ;

    /** @var \mod_groupquiz\groupquiz_session $session The session class for the groupquiz view */
    protected $session;

    /** @var string $action The specified action to take */
    protected $action;

    /** @var object $context The specific context for this activity */
    protected $context;

    /** @var \question_edit_contexts $contexts and array of contexts that has all parent contexts from the RTQ context */
    protected $contexts;

    /** @var \moodle_url $pageurl The page url to base other calls on */
    protected $pageurl;

    /** @var array $this ->pagevars An array of page options for the page load */
    protected $pagevars;

    /**
     * set up the class for the view page
     *
     * @param string $baseurl the base url of the page
     */
    public function setup_page($baseurl) {
        global $PAGE, $CFG, $DB;

        $this->pagevars = array();

        $this->pageurl = new \moodle_url($baseurl);
        //$this->pageurl->remove_all_params();

        $id = optional_param('id', false, PARAM_INT);
        $groupquizid = optional_param('groupquizid', false, PARAM_INT);

        // get necessary records from the DB
        if ($id) {
            $cm = get_coursemodule_from_id('groupquiz', $id, 0, false, MUST_EXIST);
            $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
            $quiz = $DB->get_record('groupquiz', array('id' => $cm->instance), '*', MUST_EXIST);
        } else if ($groupquizid) {
            $quiz = $DB->get_record('groupquiz', array('id' => $groupquizid), '*', MUST_EXIST);
            $course = $DB->get_record('course', array('id' => $quiz->course), '*', MUST_EXIST);
            $cm = get_coursemodule_from_instance('groupquiz', $quiz->id, $course->id, false, MUST_EXIST);
        } else {
	    print_error('invalidquizid', 'groupquiz');

	}
        $this->get_parameters(); // get the rest of the parameters and set them in the class

        require_login($course->id, false, $cm);

        $this->pageurl->param('id', $cm->id);
        $this->pageurl->param('groupquizid', $quiz->id);
        $this->pageurl->param('action', $this->pagevars['action']);
        $this->pagevars['pageurl'] = $this->pageurl;

        $this->RTQ = new \mod_groupquiz\groupquiz($cm, $course, $quiz, $this->pageurl, $this->pagevars);
        $this->RTQ->require_capability('mod/groupquiz:attempt');
        $this->pagevars['isinstructor'] = $this->RTQ->is_instructor(); // set this up in the page vars so it can be passed to things like the renderer

        // finally set up the question manager and the possible groupquiz session
        //$this->session = new \mod_groupquiz\groupquiz_session($this->RTQ, $this->pageurl, $this->pagevars);

        $PAGE->set_pagelayout('incourse');
        $PAGE->set_context($this->RTQ->getContext());
        $PAGE->set_cm($this->RTQ->getCM());
        $PAGE->set_title(strip_tags($course->shortname . ': ' . get_string("modulename", "groupquiz") . ': ' .
            format_string($quiz->name, true)));
        $PAGE->set_heading($course->fullname);
        $PAGE->set_url($this->pageurl);

    }

    /**
     * Handle's the page request
     *
     */
    public function handle_request() {
        global $DB, $USER, $PAGE;

        // first check if there are questions or not.  If there are no questions display that message instead,
        // regardless of action.
        if (count($this->RTQ->get_questionmanager()->get_questions()) === 0) {
            $this->pagevars['action'] = 'noquestions';
            $this->pageurl->param('action', ''); // remove the action
        }
	$groupid = $this->pagevars['groupid'];
	$attemptid = $this->pagevars['attemptid'];

	if ($groupid == 0) {
	    // TODO is this fatal?
	}

        switch ($this->pagevars['action']) {
            case 'noquestions':
                $this->RTQ->get_renderer()->view_header();
                $this->RTQ->get_renderer()->no_questions($this->RTQ->is_instructor());
                $this->RTQ->get_renderer()->view_footer();
                break;
            case 'continuequiz':
	        $this->RTQ->get_group_attempt($groupid);
                // load active attempt
                if ($this->RTQ->openAttempt) {
                    $this->RTQ->get_renderer()->view_header(true);
                    $this->RTQ->get_renderer()->render_quiz($this->RTQ->openAttempt);
                    $this->RTQ->get_renderer()->view_footer();
		} else {
		    echo "error - no open attempt";
                    $this->RTQ->get_renderer()->render_popup_error("error - no open attempt");
		    exit;
		}
		break;
            case 'startquiz':

                // case for the quiz start landing page
                // set the quiz view page to the base layout for 1 column layout
                $PAGE->set_pagelayout('base');
                // get the current attempt and initialize the head contributions
                $preview = 0;
                if ($this->RTQ->init_attempt($preview, $groupid)) {
                    // now show the quiz start landing page
                    $this->RTQ->get_renderer()->view_header(true);
                    $this->RTQ->get_renderer()->render_quiz($this->RTQ->openAttempt);
                    $this->RTQ->get_renderer()->view_footer();
		} else {
		    $this->RTQ->get_renderer()->render_popup_error("error - could not open attempt for $groupid");
exit;
		}
                break;
	    case 'submitquiz':
		// TODO maybe there should be js on the button that makes a popup to id
		// unanswered questions and confirm the users choice to submit
		// TODO this will end the attempt
                $this->RTQ->get_group_attempt($groupid);
		if ($this->RTQ->openAttempt) {
		    $attemptid = $this->RTQ->openAttempt->id;
		    $this->RTQ->openAttempt->close_attempt($this->RTQ);
		    $attempt = $this->RTQ->openAttempt;
		    $points = $this->RTQ->get_grader()->calculate_attempt_grade($attempt);
		    $this->RTQ->get_grader()->save_group_grade($attempt);

		    // TODO determine if we like this best
	            $viewattempturl = new \moodle_url('/mod/groupquiz/viewquizattempt.php');
	            $viewattempturl->param('id', $this->RTQ->getCM()->id);
	            $viewattempturl->param('quizid', $this->RTQ->getRTQ()->id);
	            $viewattempturl->param('attemptid', $attemptid);
		    redirect($viewattempturl, null, 0);

		} else {
                    // redirect to the quiz view page
		    // TODO isnt that just this page?
                    $quizstarturl = clone($this->pageurl);
                    $quizstarturl->param('action', '');
                    redirect($quizstarturl, null, 0);
		}
		break;
            default:
                // trigger event for course module viewed
                $event = \mod_groupquiz\event\course_module_viewed::create(array(
                    'objectid' => $PAGE->cm->instance,
                    'context'  => $PAGE->context,
                ));

                $event->add_record_snapshot('course', $this->RTQ->getCourse());
                $event->add_record_snapshot($PAGE->cm->modname, $this->RTQ->getRTQ());
                $event->trigger();

                // determine home display based on role
                if ($this->RTQ->is_instructor()) {
                        $this->RTQ->get_renderer()->setMessage('error','we should view a quiz preview here');
                        $this->RTQ->get_renderer()->view_header();
			$this->RTQ->get_renderer()->view_inst_home();
                        $this->RTQ->get_renderer()->view_footer();

/*
			// TODO maybe display active attempts, maybe allow preview
			// or better yet, just render all questions but dont start attempt?
                        // redirect to the quiz start
                        $quizstarturl = clone($this->pageurl);
                        $quizstarturl->param('action', 'quizstart');
                        redirect($quizstarturl, null, 0);
*/

                } else { /* student default view */
                    // TODO get a better check performed here
                    $groupid = $this->RTQ->get_groupmanager()->get_user_group();
                    if ($groupid == -1) {
                        $this->RTQ->get_renderer()->setMessage('error', get_string('usernotingroup', 'groupquiz'));
                    }
		    // display the form that says start/continue
                     $this->RTQ->get_renderer()->view_header();
                     $this->RTQ->get_renderer()->view_student_home();
                     $this->RTQ->get_renderer()->view_footer();
                }
                break;
        }
    }


    /**
     * Gets the extra parameters for the class
     *
     */
    protected function get_parameters() {

        $this->pagevars['action'] = optional_param('action', '', PARAM_ALPHANUM);
        $this->pagevars['groupid'] = optional_param('groupid', '0', PARAM_INT);
        $this->pagevars['attemptid'] = optional_param('attemptid', '0', PARAM_INT);
    }

}

