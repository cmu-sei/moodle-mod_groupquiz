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
 * Unit tests for mod_groupquiz\groupquiz.
 *
 * @package    mod_groupquiz
 * @category   test
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/*
Group Quiz Plugin for Moodle
Copyright 2020 Carnegie Mellon University.
NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS"
BASIS. CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER
INCLUDING, BUT NOT LIMITED TO, WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM
USE OF THE MATERIAL. CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM
PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Released under a GNU GPL 3.0-style license, please see license.txt or contact permission@sei.cmu.edu for full terms.
[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution.  Please see
Copyright notice for non-US Government use and distribution.
This Software includes and/or makes use of the following Third-Party Software subject to its own license:
1. Moodle (https://docs.moodle.org/dev/License) Copyright 1999 Martin Dougiamas.
2. mod_activequiz (https://github.com/jhoopes/moodle-mod_activequiz/blob/master/README.md) Copyright 2014 John Hoopes
and the University of Wisconsin.
DM20-0197
 */

namespace mod_groupquiz;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for mod_groupquiz\groupquiz.
 *
 * @package    mod_groupquiz
 * @category   test
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(groupquiz::class)]
final class groupquiz_test extends \advanced_testcase {
    /**
     * Builds the groupquiz class for a new instance.
     *
     * @return array The groupquiz object, the instance record, and the generator.
     */
    private function make_groupquiz(): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instance = $generator->create_module('groupquiz', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('groupquiz', $instance->id, $course->id, false, MUST_EXIST);
        $rtq = new groupquiz($cm, $course, $instance, new \moodle_url('/mod/groupquiz/view.php'));
        return [$rtq, $instance, $generator];
    }

    /**
     * A group with no open attempt is reported as not found, and the open attempt is left alone.
     */
    public function test_get_group_attempt_without_an_attempt(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$rtq] = $this->make_groupquiz();

        $this->assertFalse($rtq->get_group_attempt(null));
        $this->assertFalse($rtq->get_group_attempt(123));
        $this->assertEmpty($rtq->openAttempt);
    }

    /**
     * A finished attempt is not an open one.
     */
    public function test_get_group_attempt_ignores_finished_attempts(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$rtq, $instance, $generator] = $this->make_groupquiz();
        $student = $generator->create_user();
        $generator->get_plugin_generator('mod_groupquiz')->create_attempt($instance, $student, [
            'forgroupid' => 123,
            'state' => groupquiz_attempt::FINISHED,
        ]);

        $this->assertFalse($rtq->get_group_attempt(123));
        $this->assertEmpty($rtq->openAttempt);
    }

    /**
     * A group with an open attempt gets it loaded.
     */
    public function test_get_group_attempt_with_an_open_attempt(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$rtq, $instance, $generator] = $this->make_groupquiz();
        $student = $generator->create_user();
        $attempt = $generator->get_plugin_generator('mod_groupquiz')->create_attempt($instance, $student, [
            'forgroupid' => 123,
        ]);

        $this->assertTrue($rtq->get_group_attempt(123));
        $this->assertInstanceOf(groupquiz_attempt::class, $rtq->openAttempt);
        $this->assertEquals($attempt->id, $rtq->openAttempt->id);
    }

    /**
     * An attempt that has not started yet is open too.
     */
    public function test_get_group_attempt_with_a_notstarted_attempt(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$rtq, $instance, $generator] = $this->make_groupquiz();
        $student = $generator->create_user();
        $attempt = $generator->get_plugin_generator('mod_groupquiz')->create_attempt($instance, $student, [
            'forgroupid' => 123,
            'state' => groupquiz_attempt::NOTSTARTED,
        ]);

        $this->assertTrue($rtq->get_group_attempt(123));
        $this->assertEquals($attempt->id, $rtq->openAttempt->id);
    }

    /**
     * Looking up a group with no open attempt leaves an attempt that was already loaded in place.
     */
    public function test_get_group_attempt_keeps_the_loaded_attempt_when_none_is_found(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$rtq, $instance, $generator] = $this->make_groupquiz();
        $student = $generator->create_user();
        $attempt = $generator->get_plugin_generator('mod_groupquiz')->create_attempt($instance, $student, [
            'forgroupid' => 123,
        ]);

        $this->assertTrue($rtq->get_group_attempt(123));
        $this->assertFalse($rtq->get_group_attempt(456));
        $this->assertEquals($attempt->id, $rtq->openAttempt->id);
    }

    /**
     * Only graders, the attempt's user and the members of its group may view an attempt, and only one of this quiz.
     */
    public function test_can_view_attempt(): void {
        global $DB;
        $this->resetAfterTest();
        [$rtq, $instance, $generator] = $this->make_groupquiz();
        $course = $DB->get_record('course', ['id' => $instance->course], '*', MUST_EXIST);
        $owner = $generator->create_and_enrol($course, 'student');
        $teammate = $generator->create_and_enrol($course, 'student');
        $outsider = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $group = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $owner->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $teammate->id]);

        $plugin = $generator->get_plugin_generator('mod_groupquiz');
        $attempt = $plugin->create_attempt($instance, $owner, ['forgroupid' => $group->id]);
        $ungrouped = $plugin->create_attempt($instance, $owner);

        $this->assertTrue($rtq->can_view_attempt($attempt, $owner->id));
        $this->assertTrue($rtq->can_view_attempt($attempt, $teammate->id));
        $this->assertTrue($rtq->can_view_attempt($attempt, $teacher->id));
        $this->assertFalse($rtq->can_view_attempt($attempt, $outsider->id));
        $this->assertFalse($rtq->can_view_attempt($ungrouped, $teammate->id));
        $this->assertTrue($rtq->can_view_attempt($ungrouped, $owner->id));

        // The current user is used when none is given.
        $this->setUser($outsider);
        $this->assertFalse($rtq->can_view_attempt($attempt));
        $this->setUser($teammate);
        $this->assertTrue($rtq->can_view_attempt($attempt));

        // An attempt of another quiz is refused even to a grader.
        $other = $generator->create_module('groupquiz', ['course' => $course->id]);
        $foreign = $plugin->create_attempt($other, $owner);
        $this->assertFalse($rtq->can_view_attempt($foreign, $teacher->id));
        $this->assertFalse($rtq->can_view_attempt($foreign, $owner->id));
    }
}
