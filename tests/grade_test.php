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
 * Unit tests for the mod_groupquiz grade utilities.
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

namespace mod_groupquiz\utils;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/groupquiz/locallib.php');

/**
 * Unit tests for the mod_groupquiz grade utilities.
 *
 * @package    mod_groupquiz
 * @category   test
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_groupquiz\utils\grade::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_groupquiz\utils\scaletypes::class)]
class grade_test extends \advanced_testcase {
    /**
     * Build a grader over an instance whose only relevant setting is the grading method.
     *
     * The grade utility only reaches back through getRTQ() for settings, so a stub keeps the grading
     * method under test independent of the page, renderer and question manager a real groupquiz needs.
     *
     * @param int $grademethod One of the scaletypes constants.
     * @return \mod_groupquiz\utils\grade
     */
    protected function grader_for(int $grademethod): grade {
        $rtq = $this->createStub(\mod_groupquiz\groupquiz::class);
        $rtq->method('getRTQ')->willReturn((object)['grademethod' => $grademethod]);

        return new grade($rtq);
    }

    /**
     * Call the protected grading method reducer.
     *
     * @param int $grademethod One of the scaletypes constants.
     * @param array $grades Attempt grades, in attempt order.
     * @return mixed The single grade the method selects.
     */
    protected function apply_grading_method(int $grademethod, array $grades) {
        $method = new \ReflectionMethod(grade::class, 'apply_grading_method');
        $method->setAccessible(true);

        return $method->invoke($this->grader_for($grademethod), $grades);
    }

    /**
     * Each grading method reduces a group's attempt grades to the one grade that reaches the gradebook.
     */
    public function test_apply_grading_method(): void {
        $grades = [30, 90, 60];

        $this->assertEquals(
            30,
            $this->apply_grading_method(scaletypes::groupquiz_FIRSTATTEMPT, $grades)
        );
        $this->assertEquals(
            60,
            $this->apply_grading_method(scaletypes::groupquiz_LASTATTEMPT, $grades)
        );
        $this->assertEquals(
            60,
            $this->apply_grading_method(scaletypes::groupquiz_ATTEMPTAVERAGE, $grades)
        );
        $this->assertEquals(
            90,
            $this->apply_grading_method(scaletypes::groupquiz_HIGHESTATTEMPTGRADE, $grades)
        );
    }

    /**
     * An unrecognised grading method is a misconfigured instance, not a zero grade.
     */
    public function test_apply_grading_method_rejects_an_unknown_method(): void {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid session grade method');

        $this->apply_grading_method(99, [30, 90]);
    }

    /**
     * A group with one closed attempt gets that attempt's grade, not the zero the highest-attempt search
     * starts from.
     */
    public function test_highest_attempt_with_a_single_grade(): void {
        $this->assertEquals(
            25,
            $this->apply_grading_method(scaletypes::groupquiz_HIGHESTATTEMPTGRADE, [25])
        );
    }

    /**
     * The grading methods offered by the form are the ones the grader can apply, and each has a string.
     */
    public function test_scaletypes(): void {
        $types = scaletypes::get_types();
        $this->assertSame([
            'firstattempt' => scaletypes::groupquiz_FIRSTATTEMPT,
            'lastattempt' => scaletypes::groupquiz_LASTATTEMPT,
            'average' => scaletypes::groupquiz_ATTEMPTAVERAGE,
            'highestgrade' => scaletypes::groupquiz_HIGHESTATTEMPTGRADE,
        ], $types);

        $displaytypes = scaletypes::get_display_types();
        $this->assertSame(array_values($types), array_keys($displaytypes));
        foreach ($displaytypes as $value => $label) {
            $this->assertNotSame('', trim($label));
            $this->assertStringNotContainsString('[[', $label, "grading method $value has no language string");
        }
    }

    /**
     * The gradebook reads a user's grade back out of the plugin's own grades table.
     */
    public function test_get_user_grade(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $instance = $generator->create_module('groupquiz', ['course' => $course->id]);

        /** @var \mod_groupquiz_generator $groupquizgenerator */
        $groupquizgenerator = $generator->get_plugin_generator('mod_groupquiz');
        $groupquizgenerator->create_grade($instance, $student, 42.5);

        $this->assertEquals(42.5, grade::get_user_grade($instance, $student->id));
    }

    /**
     * A user who has not been graded yet has no grade, which the gradebook stores as null.
     */
    public function test_get_user_grade_without_a_grade(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $instance = $generator->create_module('groupquiz', ['course' => $course->id]);

        $this->assertNull(grade::get_user_grade($instance, $student->id));
    }

    /**
     * Duplicate grade rows are a bug in whatever wrote them, but reading them must not end the request.
     */
    public function test_get_user_grade_with_duplicate_grades(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $instance = $generator->create_module('groupquiz', ['course' => $course->id]);

        /** @var \mod_groupquiz_generator $groupquizgenerator */
        $groupquizgenerator = $generator->get_plugin_generator('mod_groupquiz');
        $groupquizgenerator->create_grade($instance, $student, 10);
        $groupquizgenerator->create_grade($instance, $student, 30);

        $this->assertEquals(30, grade::get_user_grade($instance, $student->id));
        $this->assertDebuggingCalled(
            'get_user_grade found 2 grades for user ' . $student->id . ' in groupquiz ' . $instance->id
        );
    }
}
