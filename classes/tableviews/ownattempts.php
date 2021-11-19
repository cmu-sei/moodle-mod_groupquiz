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

namespace mod_groupquiz\tableviews;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

/**
 *
 * @package   mod_groupquiz
 * @copyright 2014 Carnegie Mellon University
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

class ownattempts extends \flexible_table {


    /** @var \mod_groupquiz\groupquiz $rtq */
    protected $rtq;

    /**
     * Contstruct this table class
     *
     * @param string                     $uniqueid The unique id for the table
     * @param \mod_groupquiz\groupquiz $rtq
     * @param \moodle_url                $pageurl
     */
    public function __construct($uniqueid, $rtq, $pageurl, $attempts) {

        $this->rtq = $rtq;
        $this->baseurl = $pageurl;
	$this->attempts = $attempts;

        parent::__construct($uniqueid);
    }


    /**
     * Setup the table, i.e. table headers
     *
     */
    public function setup() {
        // Set var for is downloading
        $isdownloading = $this->is_downloading();

        $this->set_attribute('cellspacing', '0');

        $state = $this->rtq->get_openclose_state();
        $reviewoptions = $this->rtq->get_review_options();
        $canreviewmarks = $this->rtq->canreviewmarks($reviewoptions, $state);
        $canreviewattempt = $this->rtq->canreviewattempt($reviewoptions, $state);

        $columns = array(
	    // user submitted name
	    'user'	 => 'Submitted By',
            'group'      => get_string('group'),
            'timestart'  => get_string('timestarted', 'groupquiz'),
            'timefinish' => get_string('timecompleted', 'groupquiz')
        );

	// only if canreviewmarks
        if ($canreviewmarks) {
            $columns['grade'] = get_string('grade');
        }

	// only if canreviewattempt
        if ($canreviewattempt) {
            $columns['attemptview'] = get_string('attemptview', 'groupquiz');
        }

        $this->define_columns(array_keys($columns));
        $this->define_headers(array_values($columns));

        //$this->sortable(true, 'timestart');
	$this->sortable(false);
        $this->collapsible(false);

        $this->set_attribute('cellspacing', '0');
        $this->set_attribute('cellpadding', '2');
        $this->set_attribute('id', 'attempts');
        $this->set_attribute('class', 'generaltable generalbox');
        $this->set_attribute('align', 'center');

        parent::setup();
    }


    /**
     * Sets the data to the table
     *
     */
    public function set_data() {
        global $CFG, $OUTPUT;

        $download = $this->is_downloading();
        $tabledata = $this->get_data();

	$state = $this->rtq->get_openclose_state();
	$reviewoptions = $this->rtq->get_review_options();
	$canreviewmarks = $this->rtq->canreviewmarks($reviewoptions, $state);
        $canreviewattempt = $this->rtq->canreviewattempt($reviewoptions, $state);

        foreach ($tabledata as $item) {

            $row = array();

	    $row[] = $item->userstop;
            $row[] = $item->group;
            $row[] = userdate($item->timestart);
            $row[] = userdate($item->timefinish);
	    // only if allowed
	    if ($canreviewmarks) {
	        $row[] = $item->grade;
	    }

	    // view attempt only if allowed
	    if ($canreviewattempt) {
                $viewattempturl = new \moodle_url('/mod/groupquiz/viewquizattempt.php');
                $viewattempturl->param('groupquizid', $this->rtq->getRTQ()->id);
                $viewattempturl->param('attemptid', $item->attemptid);

                $viewattemptpix = new \pix_icon('t/preview', 'preview');
	        //$params = array('fullscreen'=>1);
	        $params = array('width'=>1000,'height'=>600);
	        $popup = new \popup_action('click', $viewattempturl, 'viewquizattempt', $params);

                $actionlink = new \action_link($viewattempturl, '', $popup, array('target' => '_blank'), $viewattemptpix);

            	$row[] = $OUTPUT->render($actionlink);
	    }

            $this->add_data($row);
        }

    }


    /**
     * Gets the data for the table
     *
     * @return array $data The array of data to show
     */
    protected function get_data() {
        global $DB, $USER;

        $data = array();

        foreach ($this->attempts as $attempt) {
            $ditem = new \stdClass();
	    $user = $DB->get_record("user", array('id' => $attempt->userstop));
	    if ($user) {
	        $ditem->userstop = fullname($user);
            } else if (!$user && $attempt->timefinish) {
		$ditem->userstop = "Time Expired";
	    }
	    $ditem->attemptid  = $attempt->id;
            //$ditem->attemptnum = $attempt->attempnum;
            $ditem->group = $this->rtq->get_groupmanager()->get_group_name($attempt->forgroupid);
            $ditem->timestart = $attempt->timestart;
            $ditem->timefinish = $attempt->timefinish;
            //$ditem->grade = number_format($this->rtq->get_grader()->calculate_attempt_grade($attempt), 2);
	    $ditem->grade = number_format($attempt->sumgrades, 2);
            $ditem->totalgrade = number_format($this->rtq->getRTQ()->grade, 2);

            $data[ $attempt->id ] = $ditem;
        }

        return $data;
    }

}
