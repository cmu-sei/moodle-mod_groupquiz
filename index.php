<?php
//
// Capability definitions for the groupquiz module.
//
// The capabilities are loaded into the database table when the module is
// installed or updated. Whenever the capability definitions are updated,
// the module version number should be bumped up.
//
// The system has four possible values for a capability:
// CAP_ALLOW, CAP_PREVENT, CAP_PROHIBIT, and inherit (not set).
//
//
// CAPABILITY NAMING CONVENTION
//
// It is important that capability names are unique. The naming convention
// for capabilities that are specific to modules and blocks is as follows:
//   [mod/block]/<component_name>:<capabilityname>
//
// component_name should be the same as the directory name of the mod or block.
//
// Core moodle capabilities are defined thus:
//    moodle/<capabilityclass>:<capabilityname>
//
// Examples: mod/forum:viewpost
//           block/recent_activity:view
//           moodle/site:deleteuser
//
// The variable name for the capability definitions array follows the format
//   $<componenttype>_<component_name>_capabilities
//
// For the core capabilities, the variable is $moodle_capabilities.

/**
 * List of groupquizs in course
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

require_once("../../config.php");
require_once("lib.php");

$id = required_param('id', PARAM_INT);   // course

if (!$course = $DB->get_record('course', array('id' => $id))) {
    error("Course ID is incorrect");
}

$PAGE->set_url(new moodle_url('/mod/groupquiz/index.php', array('id' => $course->id)));
require_course_login($course);
$PAGE->set_pagelayout('incourse');


/// Get all required strings

$strgroupquizzes = get_string("modulenameplural", "groupquiz");
$strgroupquiz = get_string("modulename", "groupquiz");

$PAGE->navbar->add($strgroupquizzes);
$PAGE->set_title(strip_tags($course->shortname . ': ' . $strgroupquizzes));
//$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

/// Get all the appropriate data

if (!$groupquizs = get_all_instances_in_course("groupquiz", $course)) {
    notice("There are no groupquizes", "../../course/view.php?id=$course->id");
    die;
}

/// Print the list of instances (your module will probably extend this)

$timenow = time();
$strname = get_string("name");
$strweek = get_string("week");
$strtopic = get_string("topic");

$table = new html_table();

if ($course->format == "weeks") {
    $table->head = array($strweek, $strname);
    $table->align = array("center", "left");
} else if ($course->format == "topics") {
    $table->head = array($strtopic, $strname);
    $table->align = array("center", "left");
} else {
    $table->head = array($strname);
    $table->align = array("left", "left");
}

foreach ($groupquizs as $groupquiz) {
    $url = new moodle_url('/mod/groupquiz/view.php', array('id' => $groupquiz->coursemodule));
    if (!$groupquiz->visible) {
        //Show dimmed if the mod is hidden
        $link = '<a class="dimmed" href="' . $url . '">' . $groupquiz->name . '</a>';
    } else {
        //Show normal if the mod is visible
        $link = '<a href="' . $url . '">' . $groupquiz->name . '</a>';
    }

    if ($course->format == 'weeks' or $course->format == 'topics') {
        $table->data[] = array($groupquiz->section, $link);
    } else {
        $table->data[] = array($link);
    }
}

echo html_writer::table($table);

/// Finish the page

echo $OUTPUT->footer();

