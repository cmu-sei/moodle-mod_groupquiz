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
 * Crucible mod callbacks.
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

// This line protects the file from being accessed by a URL directly.
defined('MOODLE_INTERNAL') || die();

/**
 * List of features supported in groupquiz module
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function groupquiz_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_ARCHETYPE:           return MOD_ARCHETYPE_OTHER;
        case FEATURE_GROUPS:                  return false;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;

        default: return null;
    }
}
/**
 * Returns all other caps used in module
 * @return array
 */
function groupquiz_get_extra_capabilities() {
    return array('moodle/site:accessallgroups');
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function groupquiz_reset_userdata($data) {

    // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
    // See MDL-9367.

    return array();
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function groupquiz_get_post_actions() {
    return array('update', 'add');
}

/**
 * Add groupquiz instance.
 * @param object $groupquiz
 * @param object $mform
 * @return int new groupquiz instance id
 */
function groupquiz_add_instance($groupquiz, $mform) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/mod/groupquiz/locallib.php');

    $cmid = $groupquiz->coursemodule;

    $result = groupquiz_process_options($groupquiz);
    if ($result && is_string($result)) {
        return $result;
    }

    $groupquiz->created = time();
    $groupquiz->id = $DB->insert_record('groupquiz', $groupquiz);

    // Do the processing required after an add or an update.
    groupquiz_after_add_or_update($groupquiz);

    return $groupquiz->id;
}
/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * Update groupquiz instance.
 * @param object $groupquiz
 * @param object $mform
 * @return bool true
 */
function groupquiz_update_instance(stdClass $groupquiz, $mform) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/groupquiz/locallib.php');

    // Process the options from the form.
    $result = groupquiz_process_options($groupquiz);
    if ($result && is_string($result)) {
        return $result;
    }
    // Get the current value, so we can see what changed.
    $oldgroupquiz = $DB->get_record('groupquiz', array('id' => $groupquiz->instance));

    $groupquizdateschanged = $oldgroupquiz->timelimit   != $gouppquiz->timelimit
                     || $oldgroupquiz->timeclose   != $groupquiz->timeclose
                     || $oldgroupquiz->graceperiod != $groupquiz->graceperiod;
    if ($groupquizdateschanged) {
        //groupquiz_update_open_attempts(array('groupquizid' => $groupquiz->id));
    }


    // We need two values from the existing DB record that are not in the form,
    // in some of the function calls below.
    $groupquiz->sumgrades = $oldgroupquiz->sumgrades;
    $groupquiz->grade     = $oldgroupquiz->grade;

    // Update the database.
    $groupquiz->id = $groupquiz->instance;
    $DB->update_record('groupquiz', $groupquiz);

    // Do the processing required after an add or an update.
    groupquiz_after_add_or_update($groupquiz);

    // Do the processing required after an add or an update.
    return true;

}

function groupquiz_process_options($groupquiz) {
    global $CFG;
    require_once($CFG->libdir . '/questionlib.php');
    require_once($CFG->dirroot . '/mod/groupquiz/locallib.php');

    $groupquiz->timemodified = time();

    // Quiz name.
    if (!empty($groupquiz->name)) {
        $groupquiz->name = trim($groupquiz->name);
    }

    // Combing the individual settings into the review columns.
    $groupquiz->reviewattempt = groupquiz_review_option_form_to_db($groupquiz, 'attempt');
    $groupquiz->reviewcorrectness = groupquiz_review_option_form_to_db($groupquiz, 'correctness');
    $groupquiz->reviewmarks = groupquiz_review_option_form_to_db($groupquiz, 'marks');
    $groupquiz->reviewspecificfeedback = groupquiz_review_option_form_to_db($groupquiz, 'specificfeedback');
    $groupquiz->reviewgeneralfeedback = groupquiz_review_option_form_to_db($groupquiz, 'generalfeedback');
    $groupquiz->reviewrightanswer = groupquiz_review_option_form_to_db($groupquiz, 'rightanswer');
    $groupquiz->reviewoverallfeedback = groupquiz_review_option_form_to_db($groupquiz, 'overallfeedback');
    $groupquiz->reviewattempt |= mod_groupquiz_display_options::DURING;
    $groupquiz->reviewoverallfeedback &= ~mod_groupquiz_display_options::DURING;
    $groupquiz->reviewmanualcomment = groupquiz_review_option_form_to_db($groupquiz, 'manualcomment');

}

/**
 * Helper function for {@link groupquiz_process_options()}.
 * @param object $fromform the sumbitted form date.
 * @param string $field one of the review option field names.
 */
function groupquiz_review_option_form_to_db($fromform, $field) {
    static $times = array(
        'during' => mod_groupquiz_display_options::DURING,
        'immediately' => mod_groupquiz_display_options::IMMEDIATELY_AFTER,
        'open' => mod_groupquiz_display_options::LATER_WHILE_OPEN,
        'closed' => mod_groupquiz_display_options::AFTER_CLOSE,
    );

    $review = 0;
    foreach ($times as $whenname => $when) {
        $fieldname = $field . $whenname;
        if (isset($fromform->$fieldname)) {
            $review |= $when;
            unset($fromform->$fieldname);
        }
    }

    return $review;
}

/**
 * Delete groupquiz instance.
 * @param int $id
 * @return bool true
 */
function groupquiz_delete_instance($id) {
    global $DB;
    $groupquiz = $DB->get_record('groupquiz', array('id' => $id), '*', MUST_EXIST);

    // delete calander events
    $events = $DB->get_records('event', array('modulename' => 'groupquiz', 'instance' => $groupquiz->id));
    foreach ($events as $event) {
        $event = calendar_event::load($event);
        $event->delete();
    }

    // delete all attempts for this groupquiz
    $DB->delete_records('groupquiz_attempts', array('groupquizid' => $groupquiz->id));

    // delete all questions for this groupquiz
    $DB->delete_records('groupquiz_questions', array('groupquizid' => $groupquiz->id));

    // finally delete the groupquiz object
    $DB->delete_records('groupquiz', array('id' => $groupquiz->id));


    // delete grade from database
    groupquiz_grade_item_delete($groupquiz);



    // note: all context files are deleted automatically

    $DB->delete_records('groupquiz', array('id'=>$groupquiz->id));

    return true;
}

/**
 * Given a course_module object, this function returns any
 * "extra" information that may be needed when printing
 * this activity in a course listing.
 *
 * See {@link get_array_of_activities()} in course/lib.php
 *
 * @param object $coursemodule
 * @return cached_cm_info info
 */
function groupquiz_get_coursemodule_info($coursemodule) {
}

/*
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $groupquiz        groupquiz object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 * @since Moodle 3.0
 */
function groupquiz_view($groupquiz, $course, $cm, $context) {

    // Trigger course_module_viewed event.
    $params = array(
        'context' => $context,
        'objectid' => $groupquiz->id
    );

    $event = \mod_groupquiz\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('groupquiz', $groupquiz);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.2
 */
function groupquiz_check_updates_since(cm_info $cm, $from, $filter = array()) {
    $updates = course_check_module_updates_since($cm, $from, array('content'), $filter);
    return $updates;
}
/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_groupquiz_core_calendar_provide_event_action(calendar_event $event,
                                                       \core_calendar\action_factory $factory) {
    $cm = get_fast_modinfo($event->courseid)->instances['groupquiz'][$event->instance];

    $completion = new \completion_info($cm->get_course());

    $completiondata = $completion->get_data($cm, false);

    if ($completiondata->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }

    return $factory->create_instance(
        get_string('view'),
        new \moodle_groupquiz('/mod/groupquiz/view.php', ['id' => $cm->id]),
        1,
        true
    );
}

/**
 * Update grades in central gradebook
 *
 * @category grade
 * @param object $groupquiz the groupquiz settings.
 * @param int $userid specific user only, 0 means all users.
 * @param bool $nullifnone If a single user is specified and $nullifnone is true a grade item with a null rawgrade will be inserted
 */
function groupquiz_update_grades($groupquiz, $userid = 0, $nullifnone = true) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    $grades = array();
    foreach ($userid as $user) {
	debugging("user id:" . $user, DEBUG_DEVELOPER);
	$rawgrade = \mod_groupquiz\utils\grade::get_user_grade($groupquiz, $user);
	debugging("user grade: " . $rawgrade, DEBUG_DEVELOPER);
        $grade = new stdClass();
        $grade->userid   = $user;
        $grade->rawgrade = $rawgrade;
        $grades[$user] = $grade;
    }
    return groupquiz_grade_item_update($groupquiz, $grades);
}

/**
 * This function is called at the end of groupquiz_add_instance
 * and groupquiz_update_instance, to do the common processing.
 *
 * @param object $groupquiz the groupquiz object.
 */
function groupquiz_after_add_or_update($groupquiz) {
    global $DB;
    $cmid = $groupquiz->coursemodule;

    // We need to use context now, so we need to make sure all needed info is already in db.
    $DB->set_field('course_modules', 'instance', $groupquiz->id, array('id'=>$cmid));
    $context = context_module::instance($cmid);

    // Update related grade item.
    groupquiz_grade_item_update($groupquiz);
}


/**
 * Create or update the grade item for given lab
 *
 * @category grade
 * @param object $groupquiz object with extra cmidnumber
 * @param mixed $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function groupquiz_grade_item_update($groupquiz, $grades = null) {
    global $CFG;
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir . '/gradelib.php');
    }

    if (array_key_exists('cmidnumber', $groupquiz)) { // May not be always present.
        $params = array('itemname' => $groupquiz->name, 'idnumber' => $groupquiz->cmidnumber);
    } else {
        $params = array('itemname' => $groupquiz->name);
    }

    if ($groupquiz->grade == 0) {
        $params['gradetype'] = GRADE_TYPE_NONE;

    } else if ($groupquiz->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = $groupquiz->grade;
        $params['grademin'] = 0;

    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/groupquiz', $groupquiz->course, 'mod', 'groupquiz', $groupquiz->id, 0, $grades, $params);
}


/**
 * Delete grade item for given lab
 *
 * @category grade
 * @param object $groupquiz object
 * @return object groupquiz
 */
// TODO remove
function groupquiz_grade_item_delete($groupquiz) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update('mod/groupquiz', $groupquiz->course, 'mod', 'groupquiz', $groupquiz->id, 0,
            null, array('deleted' => 1));
}


