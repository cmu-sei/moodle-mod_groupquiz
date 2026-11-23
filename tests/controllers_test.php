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
 * Unit tests for the mod_groupquiz callback controllers.
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
 * Unit tests for the mod_groupquiz callback controllers.
 *
 * @package    mod_groupquiz
 * @category   test
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(controllers\quizdata::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(controllers\quizinfo::class)]
final class controllers_test extends \advanced_testcase {
    /**
     * Provides the controllers whose setup_page() has a catch block for bad requests.
     *
     * @return array
     */
    public static function controller_provider(): array {
        return [
            'quizdata' => [controllers\quizdata::class],
            'quizinfo' => [controllers\quizinfo::class],
        ];
    }

    /**
     * With debugging on, a bad request rethrows the exception that was caught, not a new one built from it.
     *
     * @param string $class The controller class.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('controller_provider')]
    public function test_setup_page_rethrows_the_caught_exception(string $class): void {
        $this->resetAfterTest();
        set_debugging(DEBUG_DEVELOPER);

        // No groupquiz has this id, so the MUST_EXIST lookup throws.
        $_GET['groupquizid'] = '987654';
        $_GET['attemptid'] = '1';
        $_GET['action'] = 'noop';

        try {
            (new $class())->setup_page();
            $this->fail('Expected the missing record exception to reach the caller.');
        } catch (\dml_missing_record_exception $e) {
            // The original exception arrives, with the stack of the lookup that failed.
            $this->assertNotEmpty($e->getTrace());
        } finally {
            unset($_GET['groupquizid'], $_GET['attemptid'], $_GET['action']);
        }
    }
}
