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

namespace mod_groupquiz\utils;

defined('MOODLE_INTERNAL') || die();

/**
 * Class to define grade types for the module
 * Is used in multiple classes/functions
 *
 * @package     mod_groupquiz
 * @copyright   2014 Carnegie Mellon University
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
2. mod_activequiz (https://github.com/jhoopes/moodle-mod_activequiz/blob/master/README.md) Copyright 2014 John Hoopes and the University of Wisconsin.
DM20-0197
 */

class scaletypes {

    /** Define grading scale types */
    const groupquiz_FIRSTATTEMPT = 1;
    const groupquiz_LASTATTEMPT = 2;
    const groupquiz_ATTEMPTAVERAGE = 3;
    const groupquiz_HIGHESTATTEMPTGRADE = 4;


    /**
     * Return array of scale types keyed by the type name
     *
     * @return array
     */
    public static function get_types() {

        return array(
            'firstattempt' => self::groupquiz_FIRSTATTEMPT,
            'lastattempt'  => self::groupquiz_LASTATTEMPT,
            'average'      => self::groupquiz_ATTEMPTAVERAGE,
            'highestgrade' => self::groupquiz_HIGHESTATTEMPTGRADE,
        );
    }

    /**
     * Returns an array of scale types for display, i.e. a form
     * keyed by the values that each type is
     *
     * @return array
     */
    public static function get_display_types() {

        return array(
            self::groupquiz_FIRSTATTEMPT        => get_string('firstattempt', 'groupquiz'),
            self::groupquiz_LASTATTEMPT        => get_string('lastattempt', 'groupquiz'),
            self::groupquiz_ATTEMPTAVERAGE      => get_string('attemptaverage', 'groupquiz'),
            self::groupquiz_HIGHESTATTEMPTGRADE => get_string('highestattempt', 'groupquiz'),
        );
    }

}


