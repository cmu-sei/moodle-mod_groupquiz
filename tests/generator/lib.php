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
 * Data generator for mod_groupquiz.
 *
 * @package    mod_groupquiz
 * @category   test
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
2. mod_activequiz (https://github.com/jhoopes/moodle-mod_activequiz/blob/master/README.md) Copyright 2014 John Hoopes and the University of Wisconsin.
DM20-0197
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Group Quiz module data generator class.
 *
 * @package    mod_groupquiz
 * @category   test
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_groupquiz_generator extends testing_module_generator {
    /**
     * Creates a new instance of mod_groupquiz.
     *
     * @param array|stdClass $record Record of properties for the new instance.
     * @param array|null $options General options for the course module.
     * @return stdClass The created instance.
     */
    public function create_instance($record = null, ?array $options = null) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/groupquiz/locallib.php');

        $record = (object)(array)$record;

        $defaults = [
            'timeopen' => 0,
            'timeclose' => 0,
            'timelimit' => 0,
            'grade' => 100,
            'grademethod' => \mod_groupquiz\utils\scaletypes::groupquiz_FIRSTATTEMPT,
            'grouping' => 0,
            'questionorder' => '',
            'shuffleanswers' => 0,
            'showuserpicture' => 0,
            'requireallmemberssubmit' => 0,
            'timecreated' => time(),
        ];
        foreach ($defaults as $field => $value) {
            if (!isset($record->$field)) {
                $record->$field = $value;
            }
        }

        // The review settings arrive from mod_form.php as one checkbox per field per time window, and
        // groupquiz_process_options() folds them into the review* bitmasks. Default to "visible once the
        // attempt is over", which is what the form offers; a test that cares passes its own flags.
        foreach (array_keys(\mod_groupquiz\groupquiz::$reviewfields) as $field) {
            foreach (['immediately', 'open', 'closed'] as $when) {
                $checkbox = $field . $when;
                if (!isset($record->$checkbox)) {
                    $record->$checkbox = 1;
                }
            }
        }

        return parent::create_instance($record, (array)$options);
    }

    /**
     * Creates an attempt for a groupquiz instance.
     *
     * @param stdClass $groupquiz The groupquiz instance.
     * @param stdClass $user The user the attempt belongs to.
     * @param array $record Additional properties for the attempt.
     * @return stdClass The created attempt record.
     */
    public function create_attempt($groupquiz, $user, array $record = []) {
        global $DB;

        $now = time();

        $attempt = new stdClass();
        $attempt->groupquizid = $groupquiz->id;
        $attempt->userid = $user->id;
        // The userstart column is NOT NULL with no default: it names the member who opened the attempt,
        // which is the attempt owner unless a test says otherwise.
        $attempt->userstart = $record['userstart'] ?? $user->id;
        $attempt->userstop = $record['userstop'] ?? null;
        $attempt->attemptnum = $record['attemptnum'] ?? 1;
        // The uniqueid column is a unique index onto question_usages, so every attempt needs a usage of
        // its own - the one groupquiz_attempt opens when a group starts an attempt.
        if (isset($record['uniqueid'])) {
            $attempt->uniqueid = $record['uniqueid'];
        } else {
            $cm = get_coursemodule_from_instance('groupquiz', $groupquiz->id, 0, false, MUST_EXIST);
            $quba = \question_engine::make_questions_usage_by_activity(
                'mod_groupquiz',
                \context_module::instance($cm->id)
            );
            $quba->set_preferred_behaviour('immediatefeedback');
            \question_engine::save_questions_usage_by_activity($quba);
            $attempt->uniqueid = $quba->get_id();
        }
        $attempt->forgroupid = $record['forgroupid'] ?? 0;
        $attempt->layout = $record['layout'] ?? '';
        $attempt->sumgrades = $record['sumgrades'] ?? null;
        $attempt->state = $record['state'] ?? \mod_groupquiz\groupquiz_attempt::INPROGRESS;
        $attempt->timestart = $record['timestart'] ?? $now;
        $attempt->timefinish = $record['timefinish'] ?? null;
        $attempt->timemodified = $record['timemodified'] ?? $now;
        $attempt->preview = $record['preview'] ?? 0;

        $attempt->id = $DB->insert_record('groupquiz_attempts', $attempt);

        return $attempt;
    }

    /**
     * Creates a grade record for a groupquiz instance.
     *
     * @param stdClass $groupquiz The groupquiz instance.
     * @param stdClass $user The user the grade belongs to.
     * @param float $grade The grade value.
     * @return stdClass The created grade record.
     */
    public function create_grade($groupquiz, $user, $grade) {
        global $DB;

        $graderecord = new stdClass();
        $graderecord->groupquizid = $groupquiz->id;
        $graderecord->userid = $user->id;
        $graderecord->grade = $grade;
        $graderecord->timemodified = time();

        $graderecord->id = $DB->insert_record('groupquiz_grades', $graderecord);

        return $graderecord;
    }
}
