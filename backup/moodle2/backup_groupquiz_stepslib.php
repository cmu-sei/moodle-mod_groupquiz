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
class backup_groupquiz_activity_structure_step extends backup_questions_activity_structure_step {

    protected function define_structure() {

        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated
        $groupquiz = new backup_nested_element('groupquiz', array('id'), array(
            'name', 'intro', 'introformat', 'timeopen', 'timeclose', 'timelimit',
            'grade', 'grademethod', 'reviewattempt', 'reviewcorrectness', 'reviewmarks',
            'reviewspecificfeedback', 'reviewgeneralfeedback', 'reviewrightanswer',
            'reviewoverallfeedback', 'reviewmanualcomment', 'grouping', 'questionorder',
            'shuffleanswers', 'showuserpicture', 'requireallmemberssubmit',
            'timecreated', 'timemodified'));

        $qinstances = new backup_nested_element('question_instances');
        $qinstance = new backup_nested_element('question_instance', array('id'),
            array('questionid', 'points'));
        $this->add_question_references($qinstance, 'mod_groupquiz', 'slot');

        $grades = new backup_nested_element('grades');
        $grade = new backup_nested_element('grade', array('id'),
            array('userid', 'grade', 'timemodified'));

        $attempts = new backup_nested_element('attempts');
        $attempt = new backup_nested_element('attempt', array('id'), array(
            'userid', 'userstart', 'userstop', 'attemptnum', 'uniqueid', 'forgroupid',
            'layout', 'sumgrades', 'state', 'timestart', 'timefinish', 'timemodified',
            'preview'));
        // The question usage holding each attempt's responses.
        $this->add_question_usages($attempt, 'uniqueid');

        // Build the tree
        $groupquiz->add_child($qinstances);
        $qinstances->add_child($qinstance);
        $groupquiz->add_child($grades);
        $grades->add_child($grade);
        $groupquiz->add_child($attempts);
        $attempts->add_child($attempt);

        // Define sources
        $groupquiz->set_source_table('groupquiz', array('id' => backup::VAR_ACTIVITYID));
        $qinstance->set_source_table('groupquiz_questions',
            array('groupquizid' => backup::VAR_PARENTID), 'id ASC');
        if ($userinfo) {
            $grade->set_source_table('groupquiz_grades', array('groupquizid' => backup::VAR_PARENTID));
            $attempt->set_source_table('groupquiz_attempts',
                array('groupquizid' => backup::VAR_PARENTID), 'id ASC');
        }

        // Define id annotations
        $groupquiz->annotate_ids('grouping', 'grouping');
        $grade->annotate_ids('user', 'userid');
        $attempt->annotate_ids('user', 'userid');
        $attempt->annotate_ids('user', 'userstart');
        $attempt->annotate_ids('user', 'userstop');
        $attempt->annotate_ids('group', 'forgroupid');

        // Define file annotations
        $groupquiz->annotate_files('mod_groupquiz', 'intro', null); // This file area hasn't itemid

        // Return the root element (groupquiz), wrapped into standard activity structure
        return $this->prepare_activity_structure($groupquiz);

    }
}
