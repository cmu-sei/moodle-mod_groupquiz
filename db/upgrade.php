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
 * groupquiz module upgrade code
 *
 * This file keeps track of upgrades to
 * the resource module
 *
 * Sometimes, changes between versions involve
 * alterations to database structures and other
 * major things that may break installations.
 *
 * The upgrade function in this file will attempt
 * to perform all the necessary actions to upgrade
 * your older installation to the current version.
 *
 * If there's something it cannot do itself, it
 * will tell you what you need to do.
 *
 * The commands in here will all be database-neutral,
 * using the methods of database_manager class
 *
 * Please do not forget to use upgrade_set_timeout()
 * before any action that may take longer time to finish.
 *
 * @package    mod_groupquiz
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

defined('MOODLE_INTERNAL') || die;

function xmldb_groupquiz_upgrade($oldversion) {
    global $CFG, $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2021111702) {

        // Define field reviewmanualcomment to be added to groupquiz.
        $table = new xmldb_table('groupquiz');
        $field = new xmldb_field('reviewmanualcomment', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '0', 'reviewoverallfeedback');

        // Conditionally launch add field reviewmanualcomment.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Groupquiz savepoint reached.
        upgrade_mod_savepoint(true, 2021111702, 'groupquiz');
    }

    if ($oldversion < 2021111805) {

        // Changing the default of field grademethod on table groupquiz to 1.
        $table = new xmldb_table('groupquiz');
        $field = new xmldb_field('grademethod', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'grade');

        // Launch change of default for field grademethod.
        $dbman->change_field_default($table, $field);

        // Groupquiz savepoint reached.
        upgrade_mod_savepoint(true, 2021111805, 'groupquiz');
    }


    return true;
}

