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
 * Unit tests for the mod_groupquiz events.
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
namespace mod_groupquiz;

/**
 * Unit tests for the mod_groupquiz events.
 *
 * @package    mod_groupquiz
 * @category   test
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(event\question_manually_graded::class)]
final class event_test extends \advanced_testcase {
    /**
     * The manually graded event links to the page that saves the comment, using the keys that page reads.
     */
    public function test_question_manually_graded_url(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instance = $generator->create_module('groupquiz', ['course' => $course->id]);
        $context = \context_module::instance($instance->cmid);

        // Built the way groupquiz_attempt::process_comment() builds it.
        $event = event\question_manually_graded::create([
            'objectid' => 5,
            'courseid' => $course->id,
            'context' => $context,
            'other' => ['groupquizid' => $instance->id, 'attemptid' => 7, 'slot' => 2],
        ]);

        $url = $event->get_url();
        $this->assertStringEndsWith('/mod/groupquiz/viewquizattempt.php', $url->get_path());
        $this->assertEquals($instance->cmid, $url->get_param('id'));
        $this->assertEquals($instance->id, $url->get_param('groupquizid'));
        $this->assertEquals(7, $url->get_param('attemptid'));
        $this->assertEquals(2, $url->get_param('slot'));
        $this->assertSame('savecomment', $url->get_param('action'));
        $this->assertNull($url->get_param('sessionid'));
        $this->assertNull($url->get_param('attempt'));
    }

    /**
     * The event refuses to be created without the values its URL and description need.
     */
    public function test_question_manually_graded_requires_other_data(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instance = $generator->create_module('groupquiz', ['course' => $course->id]);
        $context = \context_module::instance($instance->cmid);
        $other = ['groupquizid' => $instance->id, 'attemptid' => 7, 'slot' => 2];

        foreach (array_keys($other) as $missing) {
            $partial = $other;
            unset($partial[$missing]);
            try {
                event\question_manually_graded::create([
                    'objectid' => 5,
                    'courseid' => $course->id,
                    'context' => $context,
                    'other' => $partial,
                ]);
                $this->fail("Expected an exception when '$missing' is missing.");
            } catch (\coding_exception $e) {
                $this->assertStringContainsString($missing, $e->getMessage());
            }
        }
    }
}
