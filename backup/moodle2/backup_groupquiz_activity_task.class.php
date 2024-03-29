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
 * Defines backup_groupquiz_activity_task class
 *
 * @package     mod_groupquiz
 * @category    backup
 * @copyright   2020 Carnegie Mellon University
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
DM20-0197
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/mod/groupquiz/backup/moodle2/backup_groupquiz_stepslib.php');

/**
 * Provides all the settings and steps to perform one complete backup of the activity
 */
class backup_groupquiz_activity_task extends backup_activity_task {

    /**
     * No specific settings for this activity
     */
    protected function define_my_settings() {
    }

    /**
     * Defines a backup step to store the instance data in the groupquiz.xml file
     */
    protected function define_my_steps() {
        $this->add_step(new backup_groupquiz_activity_structure_step('groupquiz_structure', 'groupquiz.xml'));
    }

    /**
     * Code the transformations to perform in the activity in
     * order to get transportable (encoded) links
     */
    static public function encode_content_links($content) {
        global $CFG;

        $base = preg_quote ( $CFG->wwwroot, "/" );

        // Link to the list of GROUPQUIZ instances.
        $search = "/(" . $base . "\/mod\/groupquiz\/index.php\?id\=)([0-9]+)/";
        $content = preg_replace ( $search, '$@GROUPQUIZINDEX*$2@$', $content );

        // Link to GROUPQUIZ view by moduleid.
        $search = "/(" . $base . "\/mod\/groupquiz\/view.php\?id\=)([0-9]+)/";
        $content = preg_replace ( $search, '$@GROUPQUIZVIEWBYID*$2@$', $content );

        return $content;
    }

}

