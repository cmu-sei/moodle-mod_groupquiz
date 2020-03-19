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
 * The reports controller
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

class reports {

    /** @var \mod_groupquiz\groupquiz Active quiz class */
    protected $groupquiz;


    /** @var \moodle_url $pageurl The page url to base other calls on */
    protected $pageurl;

    /** @var array $this ->pagevars An array of page options for the page load */
    protected $pagevars;

    /** @var  \mod_groupquiz\output\report_renderer $renderer */
    protected $renderer;

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
        $quizid = optional_param('quizid', false, PARAM_INT);

        // get necessary records from the DB
        if ($id) {
            $cm = get_coursemodule_from_id('groupquiz', $id, 0, false, MUST_EXIST);
            $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
            $quiz = $DB->get_record('groupquiz', array('id' => $cm->instance), '*', MUST_EXIST);
        } else {
            $quiz = $DB->get_record('groupquiz', array('id' => $quizid), '*', MUST_EXIST);
            $course = $DB->get_record('course', array('id' => $quiz->course), '*', MUST_EXIST);
            $cm = get_coursemodule_from_instance('groupquiz', $quiz->id, $course->id, false, MUST_EXIST);
        }
        $this->get_parameters(); // get the rest of the parameters and set them in the class

        require_login($course->id, false, $cm);

        $this->pageurl->param('id', $cm->id);
        $this->pageurl->param('quizid', $quiz->id);
        $this->pageurl->param('report_type', $this->pagevars['report_type']);
        $this->pageurl->param('action', $this->pagevars['action']);
        $this->pageurl->param('tsort', $this->pagevars['tsort']);
        $this->pagevars['pageurl'] = $this->pageurl;

        $this->groupquiz = new \mod_groupquiz\groupquiz($cm, $course, $quiz, $this->pageurl, $this->pagevars, 'report');

        $this->groupquiz->require_capability('mod/groupquiz:manage');

        $this->renderer = $this->groupquiz->get_renderer();


        $PAGE->set_pagelayout('incourse');
        $PAGE->set_context($this->groupquiz->getContext());
        $PAGE->set_title(strip_tags($course->shortname . ': ' . get_string("modulename", "groupquiz") . ': ' .
            format_string($quiz->name, true)));
        $PAGE->set_heading($course->fullname);
        $PAGE->set_url($this->pageurl);
    }


    /**
     * Handles the page request
     *
     */
    public function handle_request() {
        global $DB, $PAGE;
            $report = $this->resolve_report_class();

            $this->renderer->report_header($this->pageurl, $this->pagevars);
            $report->handle_request($this->pageurl, $this->pagevars);
            $this->renderer->report_footer();

    }

    /**
     * Gets the extra parameters for the class
     *
     */
    protected function get_parameters() {

        $this->pagevars['report_type'] = optional_param('report_type', 'overview', PARAM_ALPHA);
        $this->pagevars['action'] = optional_param('action', '', PARAM_ALPHANUM);
        $this->pagevars['tsort'] = optional_param('tsort', '', PARAM_ALPHANUM);
    }

    /**
     * Returns an instance of report based on the report type.  All report classes must implement the ireport interface
     *
     * @return \mod_groupquiz\reports\ireport
     */
    protected function resolve_report_class() {

        if(class_exists('\\mod_groupquiz\\reports\\' . $this->pagevars['report_type'] . '\\report_' . $this->pagevars['report_type'])){
            $class = '\\mod_groupquiz\\reports\\' . $this->pagevars['report_type'] . '\\report_' . $this->pagevars['report_type'];
            return new $class($this->groupquiz);
        } else {
echo "Error"; exit;
	}

    }
}

