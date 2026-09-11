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
 * Unit tests for the mod_groupquiz language strings.
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

/**
 * Unit tests for the mod_groupquiz language strings.
 *
 * @package    mod_groupquiz
 * @category   test
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
class lang_strings_test extends \basic_testcase {
    /**
     * Strings core requires of every activity module: without them the module chooser, the activity
     * header and the capability overview render placeholders.
     *
     * @param string $identifier String identifier in the groupquiz language file.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('required_strings_provider')]
    public function test_required_strings_exist(string $identifier): void {
        $this->assertTrue(
            get_string_manager()->string_exists($identifier, 'groupquiz'),
            "missing language string '$identifier'"
        );

        $string = get_string($identifier, 'groupquiz');
        $this->assertNotSame('', trim($string));
        $this->assertStringNotContainsString('[[', $string);
    }

    /**
     * Data provider for test_required_strings_exist.
     *
     * @return array
     */
    public static function required_strings_provider(): array {
        return [
            'pluginname' => ['pluginname'],
            'modulename' => ['modulename'],
            'modulenameplural' => ['modulenameplural'],
            'pluginadministration' => ['pluginadministration'],
            // One per grading method in scaletypes.
            'firstattempt' => ['firstattempt'],
            'lastattempt' => ['lastattempt'],
            'attemptaverage' => ['attemptaverage'],
            'highestattempt' => ['highestattempt'],
        ];
    }

    /**
     * Every capability the plugin defines needs a string, or the role editing screens list it as
     * "[[groupquiz:something]]". Read from db/access.php so a new capability is covered the day it lands.
     */
    public function test_every_capability_has_a_string(): void {
        global $CFG;

        $capabilities = [];
        require($CFG->dirroot . '/mod/groupquiz/db/access.php');
        $this->assertNotEmpty($capabilities);

        foreach (array_keys($capabilities) as $capability) {
            // A capability is declared as mod/groupquiz:view but its string is keyed groupquiz:view.
            $identifier = str_replace('mod/', '', $capability);
            $this->assertTrue(
                get_string_manager()->string_exists($identifier, 'groupquiz'),
                "capability $capability has no language string '$identifier'"
            );
            $this->assertNotSame('', trim(get_string($identifier, 'groupquiz')));
        }
    }
}
