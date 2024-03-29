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
 * Define all the backup steps that will be used by the backup_groupquiz_activity_task
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
DM20-0197
 */

defined('MOODLE_INTERNAL') || die;

 /**
 * Define the complete groupquiz structure for backup, with file and id annotations
 */
class backup_groupquiz_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // Define each element separated
        $groupquiz = new backup_nested_element('groupquiz', array('id'), array(
            'name', 'intro', 'introformat', 'timeopen', 'timeclose', 'timelimit',
	    'grade', 'grademethod', 'reviewattempt', 'reviewcorrectness', 'reviewmarks',
	    'reviewspecificfeedback', 'reviewgeneralfeedback', 'reviewrightanswer',
	    'reviewoverallfeedback', 'reviewmanualcomment', 'grouping', 'questionorder',
	    'shuffleanswers', 'showuserpicture', 'requireallmemberssubmit',
            'timecreated', 'timemodified'));

        // Build the tree
        //nothing here for groupquizs

        // Define sources
        $groupquiz->set_source_table('groupquiz', array('id' => backup::VAR_ACTIVITYID));

        // Define id annotations
        //module has no id annotations

        // Define file annotations
        $groupquiz->annotate_files('mod_groupquiz', 'intro', null); // This file area hasn't itemid

        // Return the root element (groupquiz), wrapped into standard activity structure
        return $this->prepare_activity_structure($groupquiz);

    }
}
