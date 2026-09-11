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
 * Unit tests for the mod_groupquiz helpers in locallib.php.
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

namespace mod_groupquiz;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/groupquiz/locallib.php');

/**
 * Unit tests for the mod_groupquiz helpers in locallib.php.
 *
 * @package    mod_groupquiz
 * @category   test
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('groupquiz_has_attempts')]
#[\PHPUnit\Framework\Attributes\CoversFunction('groupquiz_set_grade')]
class locallib_test extends \advanced_testcase {
    /**
     * Whether an instance has attempts gates the settings that cannot be changed after the fact.
     */
    public function test_groupquiz_has_attempts(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $instance = $generator->create_module('groupquiz', ['course' => $course->id]);

        // An unsaved instance has no id yet, and must not be reported as having attempts.
        $this->assertFalse(groupquiz_has_attempts(0));
        $this->assertFalse(groupquiz_has_attempts($instance->id));

        /** @var \mod_groupquiz_generator $groupquizgenerator */
        $groupquizgenerator = $generator->get_plugin_generator('mod_groupquiz');
        $groupquizgenerator->create_attempt($instance, $student);

        $this->assertTrue(groupquiz_has_attempts($instance->id));
    }

    /**
     * Lowering the max grade rescales the grades already stored, so a student's percentage is unchanged.
     */
    public function test_groupquiz_set_grade_rescales_stored_grades(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $first = $generator->create_and_enrol($course, 'student');
        $second = $generator->create_and_enrol($course, 'student');
        $instance = $generator->create_module('groupquiz', ['course' => $course->id, 'grade' => 100]);

        /** @var \mod_groupquiz_generator $groupquizgenerator */
        $groupquizgenerator = $generator->get_plugin_generator('mod_groupquiz');
        $firstgrade = $groupquizgenerator->create_grade($instance, $first, 40);
        $secondgrade = $groupquizgenerator->create_grade($instance, $second, 100);

        // Setting the grade writes the instance through ->instance, the form field name, and rescales
        // through ->id, so callers have to supply both.
        $formdata = clone $instance;
        $formdata->instance = $instance->id;

        $this->assertTrue(groupquiz_set_grade(50, $formdata));

        $this->assertEquals(50, $DB->get_field('groupquiz', 'grade', ['id' => $instance->id]));
        $this->assertEquals(20, $DB->get_field('groupquiz_grades', 'grade', ['id' => $firstgrade->id]));
        $this->assertEquals(50, $DB->get_field('groupquiz_grades', 'grade', ['id' => $secondgrade->id]));
    }

    /**
     * Saving the form without touching the max grade must not rewrite any grade rows.
     */
    public function test_groupquiz_set_grade_is_a_noop_for_an_unchanged_grade(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $instance = $generator->create_module('groupquiz', ['course' => $course->id, 'grade' => 100]);

        /** @var \mod_groupquiz_generator $groupquizgenerator */
        $groupquizgenerator = $generator->get_plugin_generator('mod_groupquiz');
        $graderecord = $groupquizgenerator->create_grade($instance, $student, 40);
        $before = $DB->get_record('groupquiz_grades', ['id' => $graderecord->id], '*', MUST_EXIST);

        $formdata = clone $instance;
        $formdata->instance = $instance->id;

        $this->assertTrue(groupquiz_set_grade(100, $formdata));

        $after = $DB->get_record('groupquiz_grades', ['id' => $graderecord->id], '*', MUST_EXIST);
        $this->assertEquals($before->grade, $after->grade);
        $this->assertEquals($before->timemodified, $after->timemodified);
    }

    /**
     * Raising the max grade from zero cannot rescale - there is no ratio to scale by - so the stored
     * grades are left for a regrade to recompute.
     */
    public function test_groupquiz_set_grade_from_zero_leaves_grades_for_a_regrade(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $instance = $generator->create_module('groupquiz', ['course' => $course->id, 'grade' => 0]);

        /** @var \mod_groupquiz_generator $groupquizgenerator */
        $groupquizgenerator = $generator->get_plugin_generator('mod_groupquiz');
        $graderecord = $groupquizgenerator->create_grade($instance, $student, 10);

        $formdata = clone $instance;
        $formdata->instance = $instance->id;

        $this->assertTrue(groupquiz_set_grade(50, $formdata));

        $this->assertEquals(50, $DB->get_field('groupquiz', 'grade', ['id' => $instance->id]));
        $this->assertEquals(10, $DB->get_field('groupquiz_grades', 'grade', ['id' => $graderecord->id]));
    }
}
