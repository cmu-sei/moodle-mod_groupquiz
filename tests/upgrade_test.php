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
 * Unit tests for the mod_groupquiz upgrade steps.
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
require_once($CFG->libdir . '/upgradelib.php');
require_once($CFG->dirroot . '/mod/groupquiz/db/upgrade.php');

/**
 * Unit tests for the mod_groupquiz upgrade steps.
 *
 * @package    mod_groupquiz
 * @category   test
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('xmldb_groupquiz_upgrade')]
class upgrade_test extends \advanced_testcase {
    /**
     * The 2026060200 step moved attempt state from the numeric codes of the original schema to the string
     * values core quiz uses. Sites that skip it read every attempt as being in an unknown state.
     */
    public function test_state_migration_to_string_values(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $instance = $generator->create_module('groupquiz', ['course' => $course->id]);

        /** @var \mod_groupquiz_generator $groupquizgenerator */
        $groupquizgenerator = $generator->get_plugin_generator('mod_groupquiz');

        // Numeric codes as written by the pre-2026060200 schema: 0, 10, 20, 30.
        $legacy = [
            '0' => groupquiz_attempt::NOTSTARTED,
            '10' => groupquiz_attempt::INPROGRESS,
            '20' => groupquiz_attempt::ABANDONED,
            '30' => groupquiz_attempt::FINISHED,
        ];
        $attempts = [];
        foreach (array_keys($legacy) as $code) {
            $attempts[$code] = $groupquizgenerator->create_attempt($instance, $student, ['state' => $code]);
        }
        // An attempt already migrated by an earlier run must survive a re-run untouched.
        $alreadymigrated = $groupquizgenerator->create_attempt($instance, $student, [
            'state' => groupquiz_attempt::FINISHED,
        ]);

        // Savepoints refuse to move a plugin's stored version backwards, so pretend the site is one
        // version short of the step under test.
        set_config('version', 2026060199, 'mod_groupquiz');

        $this->assertTrue(xmldb_groupquiz_upgrade(2026060199));

        foreach ($legacy as $code => $expected) {
            $this->assertSame(
                $expected,
                $DB->get_field('groupquiz_attempts', 'state', ['id' => $attempts[$code]->id]),
                "attempt stored as state '$code' should migrate to '$expected'"
            );
        }
        $this->assertSame(
            groupquiz_attempt::FINISHED,
            $DB->get_field('groupquiz_attempts', 'state', ['id' => $alreadymigrated->id])
        );

        // The savepoint is what stops the step running twice.
        $this->assertEquals(2026060200, get_config('mod_groupquiz', 'version'));
    }
}
