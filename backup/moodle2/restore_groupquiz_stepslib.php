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
 * @package   mod_groupquiz
 * @category  backup
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
DM20-0197
 */

/**
 * Define all the restore steps that will be used by the restore_groupquiz_activity_task
 */

/**
 * Structure step to restore one groupquiz activity
 */
class restore_groupquiz_activity_structure_step extends restore_questions_activity_structure_step {

    /** @var stdClass|null the attempt being restored, inserted once its question usage is. */
    protected $currentattempt = null;

    /** @var string the questionorder from the backup, remapped once the questions are restored. */
    protected $oldquestionorder = '';

    protected function define_structure() {

        $userinfo = $this->get_setting_value('userinfo');

        $paths = array();
        $paths[] = new restore_path_element('groupquiz', '/activity/groupquiz');

        $qinstance = new restore_path_element('groupquiz_question_instance',
            '/activity/groupquiz/question_instances/question_instance');
        $paths[] = $qinstance;
        $this->add_question_references($qinstance, $paths);

        if ($userinfo) {
            $paths[] = new restore_path_element('groupquiz_grade', '/activity/groupquiz/grades/grade');
            $attempt = new restore_path_element('groupquiz_attempt', '/activity/groupquiz/attempts/attempt');
            $paths[] = $attempt;
            $this->add_question_usages($attempt, $paths);
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_groupquiz($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
        // See MDL-9367.
        $data->timeopen = $this->apply_date_offset($data->timeopen);
        $data->timeclose = $this->apply_date_offset($data->timeclose);

        // A grouping that did not come across leaves the quiz without one, to be picked in its settings.
        $data->grouping = $this->get_mappingid('grouping', $data->grouping, 0);

        // The order lists groupquiz_questions ids, which change on restore; after_execute() remaps it.
        $this->oldquestionorder = (string)$data->questionorder;
        $data->questionorder = '';

        // insert the groupquiz record
        $newitemid = $DB->insert_record('groupquiz', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    }

    protected function process_groupquiz_question_instance($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->groupquizid = $this->get_new_parentid('groupquiz');
        // Point at the restored copy of the question, which is what its reference names.
        $data->questionid = $this->get_mappingid('question', $data->questionid, $data->questionid);

        $newitemid = $DB->insert_record('groupquiz_questions', $data);
        $this->set_mapping('groupquiz_question_instance', $oldid, $newitemid);
    }

    /**
     * Restore a question reference against the groupquiz_questions row it belongs to.
     *
     * Core's version assumes the parent is a quiz slot.
     *
     * @param array $data the data from the XML file.
     */
    public function process_question_reference($data) {
        global $DB;

        $data = (object)$data;
        $data->usingcontextid = $this->get_mappingid('context', $data->usingcontextid);
        $data->itemid = $this->get_new_parentid('groupquiz_question_instance');
        if ($entry = $this->get_mappingid('question_bank_entry', $data->questionbankentryid)) {
            $data->questionbankentryid = $entry;
        }
        $DB->insert_record('question_references', $data);
    }

    protected function process_groupquiz_grade($data) {
        global $DB;

        $data = (object)$data;
        $data->groupquizid = $this->get_new_parentid('groupquiz');
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        if (!$data->userid) {
            return;
        }
        $DB->insert_record('groupquiz_grades', $data);
    }

    protected function process_groupquiz_attempt($data) {
        $data = (object)$data;
        $data->groupquizid = $this->get_new_parentid('groupquiz');

        $olduserid = $data->userid;
        $data->userid = $this->get_mappingid('user', $olduserid, 0);
        if (!$data->userid) {
            $this->log('Mapped user ID not found for user ' . $olduserid . ', groupquiz ' . $data->groupquizid .
                ', attempt ' . $data->attemptnum . '. Skipping groupquiz attempt', backup::LOG_INFO);
            $this->currentattempt = null;
            return;
        }
        $data->userstart = $this->get_mappingid('user', $data->userstart, 0);
        if (!empty($data->userstop)) {
            $data->userstop = $this->get_mappingid('user', $data->userstop, 0);
        }
        $data->forgroupid = $this->get_mappingid('group', $data->forgroupid, 0);

        // The data is inserted in inform_new_usage_id(), once the question usage has its new id.
        $this->currentattempt = clone($data);
    }

    protected function inform_new_usage_id($newusageid) {
        global $DB;

        $data = $this->currentattempt;
        if ($data === null) {
            return;
        }

        $oldid = $data->id;
        $data->uniqueid = $newusageid;
        $newitemid = $DB->insert_record('groupquiz_attempts', $data);
        $this->set_mapping('groupquiz_attempt', $oldid, $newitemid);
    }

    protected function after_execute() {
        global $DB;

        parent::after_execute();
        // Add groupquiz related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_groupquiz', 'intro', null);

        $neworder = array();
        foreach (explode(',', $this->oldquestionorder) as $oldid) {
            if ($newid = $this->get_mappingid('groupquiz_question_instance', $oldid)) {
                $neworder[] = $newid;
            }
        }
        $DB->set_field('groupquiz', 'questionorder', implode(',', $neworder),
            array('id' => $this->get_new_parentid('groupquiz')));
    }
}
