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
global $CFG;
require_once($CFG->libdir . '/tablelib.php');

/**
 * Table lib subclass for showing attempts
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

class allattempts extends \flexible_table implements \renderable {


    /** @var \mod_groupquiz\groupquiz $rtq */
    protected $rtq;


    /**
     * Contstruct this table class
     *
     * @param string                             $uniqueid The unique id for the table
     * @param \mod_groupquiz\groupquiz         $rtq
     * @param \moodle_url                        $pageurl
     */
    public function __construct($uniqueid, $rtq, $pageurl) {

        $this->rtq = $rtq;
        $this->baseurl = $pageurl;

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

        $columns = array(
            'fullname'     => 'Submitted By',
	    'groupname'    => get_string('group'),
            'timestart'    => get_string('timestarted', 'groupquiz'),
            'timefinish'   => get_string('timecompleted', 'groupquiz'),
            'timemodified' => get_string('timemodified', 'groupquiz'),
            'state'       => get_string('status'),
            'attemptgrade' => get_string('attempt_grade', 'groupquiz'),
        );

        if (!$isdownloading) {
            $columns['edit'] = get_string('response_attempt_controls', 'groupquiz');
        }

        $this->define_columns(array_keys($columns));
        $this->define_headers(array_values($columns));

        //$this->sortable(true, 'timefinish', SORT_DESC);
	$this->sortable(false);
        $this->collapsible(false);

        //$this->column_class('fullname', 'bold');
        //$this->column_class('sumgrades', 'bold');

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
    public function set_data($type) {
        global $CFG, $OUTPUT;

        $download = $this->is_downloading();
        $tabledata = $this->get_data($type);

        foreach ($tabledata as $item) {

            $row = array();

	    $row[] = $item->userstop;
	    $row[] = $item->groupname;
            //$row[] = $item->attemptno;
            //$row[] = $item->preview;
            $row[] = userdate($item->timestart);
            if (!empty($item->timefinish)) {
                $row[] = userdate($item->timefinish);
            } else {
                $row[] = ' - ';
            }
            $row[] = userdate($item->timemodified);
            $row[] = $item->state;

            if (is_null($item->grade)) {
                $totalmark = ' - ';
            } else {
                $totalmark = $item->grade;// . ' / ' . $item->totalgrade;
            }
            $row[] = $totalmark;

            // view attempt
            $viewattempturl = new \moodle_url('/mod/groupquiz/viewquizattempt.php');
            $viewattempturl->param('quizid', $this->rtq->getRTQ()->id);
            $viewattempturl->param('attemptid', $item->attemptid);

            $viewattemptpix = new \pix_icon('t/preview', 'preview');
            //$params = array('fullscreen'=>1);
            $params = array('width'=>1000,'height'=>600);
            $popup = new \popup_action('click', $viewattempturl, 'viewquizattempt', $params);

            $actionlink = new \action_link($viewattempturl, '', $popup, array('target' => '_blank'), $viewattemptpix);

            $row[] = $OUTPUT->render($actionlink);

            $this->add_data($row);
        }

    }


    /**
     * Gets the data for the table
     *
     * @return array $data The array of data to show
     */
    protected function get_data($type) {
        global $DB;

        $data = array();
        $attempts = $this->rtq->getall_attempts($type);
        $groupids = array();
        foreach ($attempts as $attempt) {
            if ($attempt->forgroupid > 0) {
                $groupids[] = $attempt->forgroupid;
            }
        }

        foreach ($attempts as $attempt) {
            /** @var \mod_groupquiz\groupquiz_attempt $attempt */
            $ditem = new \stdClass();
            $ditem->attemptid = $attempt->id;

            //$name = fullname($userrecs[$attempt->userid]);
            $groupid = $attempt->forgroupid;

            $ditem->groupid = $groupid;
            $user = $DB->get_record("user", array('id' => $attempt->userstop));
            if ($user) {
                $ditem->userstop = fullname($user);
            } else if (!$user && $attempt->timefinish) {
                $ditem->userstop = "Time Expired";
            }

            $ditem->groupname = $this->rtq->get_groupmanager()->get_group_name($attempt->forgroupid);
            //$ditem->attemptno = $attempt->attemptnum;
            //$ditem->preview = $attempt->preview;
            $ditem->state = $attempt->getState();
            $ditem->timestart = $attempt->timestart;
            $ditem->timefinish = $attempt->timefinish;
            $ditem->timemodified = $attempt->timemodified;
            //$ditem->grade = number_format($this->rtq->get_grader()->calculate_attempt_grade($attempt), 2);
	    $ditem->grade = number_format($attempt->sumgrades, 2);
            $ditem->totalgrade = number_format($this->rtq->getRTQ()->grade, 2);
            $data[ $attempt->id ] = $ditem;
        }

        return $data;
    }

}

