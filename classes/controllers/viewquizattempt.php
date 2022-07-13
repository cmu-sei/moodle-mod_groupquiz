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
 * view quiz attempt controller
 *
 * @package     mod_groupquiz
 * @copyright   2020 Carnegie Mellon Univeristy
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

class viewquizattempt {

    /** @var \mod_groupquiz\groupquiz Realtime quiz class */
    protected $RTQ;

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
        $this->pageurl->remove_all_params();

        $id = optional_param('id', false, PARAM_INT);
        $groupquizid = optional_param('groupquizid', false, PARAM_INT);

        // get necessary records from the DB
        if ($id) {
            $cm = get_coursemodule_from_id('groupquiz', $id, 0, false, MUST_EXIST);
            $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
            $groupquiz = $DB->get_record('groupquiz', array('id' => $cm->instance), '*', MUST_EXIST);
        } else {
            $groupquiz = $DB->get_record('groupquiz', array('id' => $groupquizid), '*', MUST_EXIST);
            $course = $DB->get_record('course', array('id' => $groupquiz->course), '*', MUST_EXIST);
            $cm = get_coursemodule_from_instance('groupquiz', $groupquiz->id, $course->id, false, MUST_EXIST);
        }

        $this->get_parameters(); // get the rest of the parameters and set them in the class

        require_login($course->id, false, $cm);

        $this->pageurl->param('id', $cm->id);
        $this->pageurl->param('groupquizid', $groupquiz->id);
        $this->pageurl->params($this->pagevars); // add the page vars variable to the url
        $this->pagevars['pageurl'] = $this->pageurl;

        $this->RTQ = new \mod_groupquiz\groupquiz($cm, $course, $groupquiz, $this->pageurl, $this->pagevars);

        //$this->RTQ->require_capability('mod/groupquiz:viewownattempts');

        $PAGE->set_pagelayout('popup');
        $PAGE->set_context($this->RTQ->getContext());
        $PAGE->set_title(strip_tags($course->shortname . ': ' . get_string("modulename", "groupquiz") . ': ' .
            format_string($groupquiz->name, true)));
        $PAGE->set_heading($course->fullname);
        $PAGE->set_url($this->pageurl);
    }


    /**
     * handle the attempt action
     *
     */
    public function handle_request() {
        global $OUTPUT, $USER, $PAGE;

        switch ($this->pagevars['action']) {

            // TODO who can add a comment?
            case 'savecomment':
                // save a comment for a particular attempt

                $attempt = $this->RTQ->get_user_attempt($this->pagevars['attemptid']);
                $success = $attempt->process_comment($this->RTQ, $this->pagevars['slot']);
                if ($success) {
                    // if successful recalculate the grade for the attempt's userid as the grader can update grades on the questions
                    $PAGE->set_pagelayout('base');
                    $this->RTQ->get_renderer()->view_header(true);
                    $this->RTQ->get_grader()->calculate_attempt_grade($attempt);
		    $this->RTQ->get_grader()->save_group_grade($attempt);
                    $this->RTQ->get_renderer()->setMessage('success', 'Successfully saved comment/grade');
                    $this->RTQ->get_renderer()->render_attempt($attempt);
                } else {
                    $this->RTQ->get_renderer()->setMessage('error', 'Couldn\'t save comment/grade');
                    $this->RTQ->get_renderer()->render_attempt($attempt);
                }

                break;
            default:

                // default is to show the attempt
                $attempt = $this->RTQ->get_user_attempt($this->pagevars['attemptid']);
		if (!$attempt) {
                    global $PAGE;
                    $PAGE->set_pagelayout('base');
                    $this->RTQ->get_renderer()->view_header(true);
                    $this->RTQ->get_renderer()->setMessage('error', get_string('noattempt', 'groupquiz'));
                    $this->RTQ->get_renderer()->render_attempt(null);
                    $this->RTQ->get_renderer()->view_footer();
                    break;
		}

                $hascapability = true;

                if ($hascapability) {
                    $params = array(
                        'relateduserid' => $USER->id,
			            'objectid'      => $this->pagevars['id'],
                        'context'       => $this->RTQ->getContext(),
                        'other'         => array(
                        'groupquizid'   => $this->RTQ->getRTQ()->id
                        )
                    );
                    $event = \mod_groupquiz\event\attempt_viewed::create($params);
                    $event->add_record_snapshot('groupquiz_attempts', $attempt->get_attempt());
                    $event->trigger();

                    global $PAGE;
                    $PAGE->set_pagelayout('base');
                    $this->RTQ->get_renderer()->view_header(true);
                    $this->RTQ->get_renderer()->render_attempt($attempt);
                    $this->RTQ->get_renderer()->view_footer();
                }
                break;
        }

    }

    /**
     * Gets other parameters and adding them to the pagevars array
     *
     */
    public function get_parameters() {

        $this->pagevars['action'] = optional_param('action', '', PARAM_ALPHAEXT);
        $this->pagevars['attemptid'] = required_param('attemptid', PARAM_INT);
        $this->pagevars['slot'] = optional_param('slot', '', PARAM_INT);
	$this->pagevars['id'] = optional_param('id', '', PARAM_INT);

    }


}

