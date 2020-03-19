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
 *
 * @package mod_groupquiz
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

namespace mod_groupquiz\reports\closed;

use mod_groupquiz\reports\ireport;
use mod_groupquiz\tableviews\overallgradesview;

class report_closed extends \mod_groupquiz\reports\groupquiz_report_base implements ireport {

    /**
     * The tableview for the current request.  is added by the handle request function.
     *
     * @var
     */
    protected $tableview;

    /**
     * @var \mod_groupquiz\output\report_closed_renderer $renderer
     */
    protected $renderer;

    /**
     * report_closed constructor.
     * @param \mod_groupquiz\groupquiz $groupquiz
     */
    public function __construct(\mod_groupquiz\groupquiz $groupquiz) {
        global $PAGE;

        $this->renderer = $PAGE->get_renderer('mod_groupquiz', 'report_closed');
        parent::__construct($groupquiz);
    }

    /**
     * Handle the request for this specific report
     *
     * @param \moodle_url $pageurl
     * @param array $pagevars
     * @return void
     */
    public function handle_request($pageurl, $pagevars) {

        $this->renderer->init($this->groupquiz, $pageurl, $pagevars);

        // switch the action
        switch($pagevars['action']) {
            case 'regradeall':

                if($this->groupquiz->get_grader()->save_all_grades(true)) {
                    $this->renderer->setMessage('success',  get_string('successregrade', 'groupquiz'));
                }else {
                    $this->renderer->setMessage('error',  get_string('errorregrade', 'groupquiz'));
                }

                $this->renderer->showMessage();
                //$this->renderer->select_session($sessions);
                $this->renderer->home();

                break;
            default:

                $this->renderer->show_all_attempts();

                break;
        }

    }



}
