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
 * @package   mod_groupquiz
 * @copyright 2020 Carnegie Mellon Univiersity
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

// This line protects the file from being accessed by a URL directly.
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/groupquiz/locallib.php');

// Create the quiz settings page.
$pagetitle = get_string('modulename', 'groupquiz');
$groupsettings = new admin_settingpage('modsettinggroupquiz', $pagetitle, 'moodle/site:config');

if ($ADMIN->fulltree) {
    //--- general settings -----------------------------------------------------------------------------------

    // Introductory explanation that all the settings are defaults for the add quiz form.
    $groupsettings->add(new admin_setting_heading('groupquizintro', '', get_string('configintro', 'groupquiz')));

    // Time limit.
    $groupsettings->add(new admin_setting_configduration_with_advanced('groupquiz/timelimit',
            get_string('timelimit', 'groupquiz'), get_string('configtimelimitsec', 'groupquiz'),
            array('value' => '0', 'adv' => false), 60));

    // Shuffle within questions.
    $groupsettings->add(new admin_setting_configcheckbox_with_advanced('groupquiz/shuffleanswers',
            get_string('shufflewithin', 'groupquiz'), get_string('configshufflewithin', 'groupquiz'),
            array('value' => 1, 'adv' => false)));

    $choices = array();
    $defaults = array();
    foreach (question_bank::get_creatable_qtypes() as $qtypename => $qtype) {
        $fullpluginname = $qtype->plugin_name();
        $qtypepluginname = explode('_', $fullpluginname)[1];

        $choices[$qtypepluginname] = $qtype->menu_name();
        $defaults[$qtypepluginname] = 1;
    }
    $settings->add(new admin_setting_configmulticheckbox(
        'groupquiz/enabledqtypes',
        get_string('enabledquestiontypes', 'groupquiz'),
        get_string('enabledquestiontypes_info', 'groupquiz'),
        $defaults,
        $choices));

    $options = groupquiz_get_user_image_options();
    $settings->add(new admin_setting_configselect('groupquiz/showuserpicture',
        get_string('showuserpicture', 'groupquiz'),
	get_string('showuserpicture_help', 'groupquiz'),
	1, $options));

    // Review options.
    $settings->add(new admin_setting_heading('reviewheading',
            get_string('reviewoptionsheading', 'groupquiz'), ''));
    foreach (mod_groupquiz_admin_review_setting::fields() as $field => $name) {
        $default = mod_groupquiz_admin_review_setting::all_on();
        $forceduring = null;
        if ($field == 'attempt') {
            $forceduring = true;
        } else if ($field == 'overallfeedback') {
            $default = $default ^ mod_groupquiz_admin_review_setting::DURING;
            $forceduring = false;
        }
        $settings->add(new mod_groupquiz_admin_review_setting('groupquiz/review' . $field,
                $name, '', $default, $forceduring));
    }

}
