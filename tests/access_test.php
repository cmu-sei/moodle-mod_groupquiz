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
 * Unit tests for the capability definitions in db/access.php.
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
 * Unit tests for the capability definitions in db/access.php.
 *
 * @package    mod_groupquiz
 * @category   test
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class access_test extends \advanced_testcase {
    /**
     * Every capability names its default roles through the archetypes key, not the 1.9 legacy key.
     */
    public function test_capabilities_use_archetypes(): void {
        global $CFG;

        $capabilities = [];
        require($CFG->dirroot . '/mod/groupquiz/db/access.php');

        $this->assertNotEmpty($capabilities);
        foreach ($capabilities as $name => $definition) {
            $this->assertArrayNotHasKey('legacy', $definition, $name);
            $this->assertNotEmpty($definition['archetypes'], $name);
        }
    }

    /**
     * Roles get the capabilities they always got by default.
     */
    public function test_default_roles_for_capabilities(): void {
        $this->resetAfterTest();

        $student = get_default_capabilities('student');
        $this->assertEquals(CAP_ALLOW, $student['mod/groupquiz:attempt']);
        $this->assertArrayNotHasKey('mod/groupquiz:editquestions', $student);

        foreach (['editingteacher', 'manager'] as $archetype) {
            $defaults = get_default_capabilities($archetype);
            $this->assertEquals(CAP_ALLOW, $defaults['mod/groupquiz:editquestions'], $archetype);
            $this->assertEquals(CAP_ALLOW, $defaults['mod/groupquiz:attempt'], $archetype);
        }

        $teacher = get_default_capabilities('teacher');
        $this->assertEquals(CAP_ALLOW, $teacher['mod/groupquiz:attempt']);
        $this->assertArrayNotHasKey('mod/groupquiz:editquestions', $teacher);
    }
}
