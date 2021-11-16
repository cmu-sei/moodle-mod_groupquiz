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

namespace mod_groupquiz;

defined('MOODLE_INTERNAL') || die();

/**
 * Realtime quiz object.  This object contains a lot of dependencies
 * that work together that help to keep all of the dependencies in one
 * class instead of spreading them around to multiple classes
 *
 * @package     mod_groupquiz
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
2. mod_activequiz (https://github.com/jhoopes/moodle-mod_activequiz/blob/master/README.md) Copyright 2014 John Hoopes and the University of Wisconsin.
DM20-0197
 */

class groupquiz {

    /**
     * @var array $review fields Static review fields to add as options
     */
    public static $reviewfields = array(
        'attempt'          => array('theattempt', 'groupquiz'),
        'correctness'      => array('whethercorrect', 'question'),
        'marks'            => array('marks', 'groupquiz'),
        'specificfeedback' => array('specificfeedback', 'question'),
        'generalfeedback'  => array('generalfeedback', 'question'),
        'rightanswer'      => array('rightanswer', 'question'),
	'overallfeedback'  => array('overallfeedback', 'question'),
        'manualcomment'    => array('manualcomment', 'groupquiz')
    );

    /** @var \stdClass $cm */
    protected $cm;

    /** @var \stdClass $course */
    protected $course;

    /** @var \stdClass $groupquiz */
    protected $groupquiz;

    /** @var \context_module $context */
    protected $context;

    /** @var bool $isinstructor */
    protected $isinstructor;

    /** @var \mod_groupquiz\questionmanager $questionmanager */
    protected $questionmanager;

    /** @var \mod_groupquiz\utils\groupmanager $groupmanager */
    protected $groupmanager;

    /** @var \mod_groupquiz_renderer $renderer */
    protected $renderer;

    /** @var \mod_groupquiz\utils\grade $grader The grade utility class to perform gradding options */
    protected $grader;

    /** @var array $pagevars */
    protected $pagevars;


    public $openAttempt;

    /**
     * takes the realtime quiz object passed to add/update instance
     * and returns a stdClass of review options for the specified whenname
     *
     * @param \stdClass $formgroupquiz
     * @param string    $whenname
     *
     * @return \stdClass
     */
    public static function get_review_options_from_form($formgroupquiz, $whenname) {

        $formoptionsgrp = $whenname . 'optionsgrp';
        $formreviewoptions = $formgroupquiz->$formoptionsgrp;
        $reviewoptions = new \stdClass();
        foreach (\mod_groupquiz\groupquiz::$reviewfields as $field => $notused) {
            $reviewoptions->$field = $formreviewoptions[ $field ];
        }

        return $reviewoptions;
    }


    /**
     * Construct a rtq class
     *
     * @param object $cm The course module instance
     * @param object $course The course object the activity is contained in
     * @param object $quiz The specific real time quiz record for this activity
     * @param \moodle_url $pageurl The page url
     * @param array  $pagevars The variables and options for the page
     * @param string $renderer_subtype Renderer sub-type to load if requested
     *
     */
    public function __construct($cm, $course, $groupquiz, $pageurl, $pagevars = array(), $renderer_subtype = null) {
        global $CFG, $PAGE;

        $this->cm = $cm;
        $this->course = $course;
        $this->groupquiz = $groupquiz;
        $this->pagevars = $pagevars;

        $this->context = \context_module::instance($cm->id);
        $PAGE->set_context($this->context);

        $this->renderer = $PAGE->get_renderer('mod_groupquiz', $renderer_subtype);
        $this->questionmanager = new \mod_groupquiz\questionmanager($this, $this->renderer, $this->pagevars);
        $this->grader = new \mod_groupquiz\utils\grade($this);
        $this->groupmanager = new \mod_groupquiz\utils\groupmanager($this);

        $this->renderer->init($this, $pageurl, $pagevars);
    }

    /** Get functions */

    /**
     * Get the course module isntance
     *
     * @return object
     */
    public function getCM() {
        return $this->cm;
    }

    /**
     * Get the course instance
     *
     * @return object
     */
    public function getCourse() {
        return $this->course;
    }

    /**
     * Returns the reqltimequiz database record instance
     *
     * @return object
     */
    public function getRTQ() {
        return $this->groupquiz;
    }

    /**
     * Saves the rtq instance to the database
     *
     * @return bool
     */
    public function saveRTQ() {
        global $DB;

        return $DB->update_record('groupquiz', $this->groupquiz);
    }

    /**
     * Gets the context for this instance
     *
     * @return \context_module
     */
    public function getContext() {
        return $this->context;
    }

    /**
     * Sets the question manager on this class
     *
     * @param \mod_groupquiz\questionmanager $questionmanager
     */
    public function set_questionmanager(\mod_groupquiz\questionmanager $questionmanager) {
        $this->questionmanager = $questionmanager;
    }

    /**
     * Returns the class instance of the question manager
     *
     * @return \mod_groupquiz\questionmanager
     */
    public function get_questionmanager() {
        return $this->questionmanager;
    }

    /**
     * Sets the renderer on this class
     *
     * @param \mod_groupquiz_renderer $renderer
     */
    public function set_renderer(\mod_groupquiz_renderer $renderer) {
        $this->renderer = $renderer;
    }

    /**
     * Returns the class instance of the renderer
     *
     * @return \mod_groupquiz_renderer
     */
    public function get_renderer() {
        return $this->renderer;
    }

    /**
     * Gets the grader utility class to perform grading actions
     *
     * @return \mod_groupquiz\utils\grade
     */
    public function get_grader() {
        return $this->grader;
    }

    /**
     * Gets the group manager utility class for group actions
     *
     * @return \mod_groupquiz\utils\groupmanager
     */
    public function get_groupmanager() {
        return $this->groupmanager;
    }

    /**
     * provides a wrapper of the require_capability to always provide the rtq context
     *
     * @param string $capability
     */
    public function require_capability($capability) {
        require_capability($capability, $this->context);

        // no return as require_capability will throw exception on error, or just continue
    }

    /**
     * Wrapper for the has_capability function to provide the rtq context
     *
     * @param string $capability
     * @param int    $userid
     *
     * @return bool Whether or not the current user has the capability
     */
    public function has_capability($capability, $userid = 0) {
        if ($userid !== 0) {
            // pass in userid if there is one
            return has_capability($capability, $this->context, $userid);
        } else {
            // just do standard check with current user
            return has_capability($capability, $this->context);
        }
    }

    /**
     * Quick function for whether or not the current user is the instructor/can control the quiz
     *
     * @return bool
     */
    public function is_instructor() {

        if (is_null($this->isinstructor)) {
            $this->isinstructor = $this->has_capability('mod/groupquiz:manage');
            return $this->isinstructor;
        } else {
            return $this->isinstructor;
        }
    }

    /**
     * Whether or not we're in group mode
     *
     * @return bool
     */
    public function group_mode() {
            return true;
    }

    /**
     * Gets the review options for the specified time
     *
     * @param string $whenname The review options time that we want to get the options for
     *
     * @return \stdClass A class of the options
     */
    public function get_review_options() {

        $reviewoptions = new \stdClass();
	$reviewoptions->reviewattempt = $this->groupquiz->reviewattempt;
        $reviewoptions->reviewcorrectness = $this->groupquiz->reviewcorrectness;
        $reviewoptions->reviewmarks = $this->groupquiz->reviewmarks;
        $reviewoptions->reviewspecificfeedback = $this->groupquiz->reviewspecificfeedback;
        $reviewoptions->reviewgeneralfeedback = $this->groupquiz->reviewgeneralfeedback;
        $reviewoptions->reviewrightanswer = $this->groupquiz->reviewrightanswer;
        $reviewoptions->reviewoverallfeedback = $this->groupquiz->reviewoverallfeedback;
        $reviewoptions->manualcomment = 0; //$this->groupquiz->manualcomment;

        return $reviewoptions;
    }

    /**
     * This is a method to invoke the question modifier classes
     *
     * * * while params not explicitly defined, the first two arguments are required
     * @param string                                   $action The function that will be called on the question modifier classes,
     *                          function must be defined in basequestionmodifier
     * @param \mod_groupquiz\groupquiz_question|null The question that we're going to modifiy.
     *                                                     If null, we'll use all questions defined for this instance
     *
     * Any parameters passed after the first 2 are passed to the action function
     *
     * @throws \moodle_exception Throws moodle exception on errors in invoking methods
     */
    public function call_question_modifiers() {

        $params = func_get_args();

        if (empty($params[0])) {
            throw new \moodle_exception('noaction', 'groupquiz', null, null, 'Invalid call to call_question_modifiers.  No Action');
        } else {
            $action = $params[0];
        }

        // next get the question types we're going to be invoking question modifiers for
        if (!empty($params[1])) {

            if ($params[1] instanceof \mod_groupquiz\groupquiz_question) {
                /** @var \mod_groupquiz\groupquiz_question $question */
                $question = $params[1];
                // we have a question defined, so we'll use it's question type
                $questiontypes = array($question->getQuestion()->qtype);
            } else {
                $questiontypes = array();
            }

        } else {
            // we're going through all question types defined by the instance
            $questiontypes = array();
            $questions = $this->get_questionmanager()->get_questions();
            foreach ($questions as $question) {
                /** @var \mod_groupquiz\groupquiz_question $question */
                $questiontypes[] = $question->getQuestion()->qtype;
            }
        }

        if (empty($questiontypes)) {
            throw new \moodle_exception('noquestiontypes', 'groupquiz', null, null, 'No question types defined for this call');
        }

        // next we'll try to invoke the methods
        $return = null;
        foreach ($questiontypes as $type) {

            // first check to make sure the class exists
            if (class_exists("\\mod_groupquiz\\questionmodifiers\\" . $type)) {

                // create reflection for it to validate action and params as well as implementing
                $reflection = new \ReflectionClass('\mod_groupquiz\questionmodifiers\\' . $type);
                if (!$reflection->implementsInterface('\mod_groupquiz\questionmodifiers\ibasequestionmodifier')) {
                    throw new \moodle_exception('invlidimplementation', 'groupquiz', null, null, 'You question modifier does not implement the base modifier interface... ' . $type);
                } else {
                    $rMethod = $reflection->getMethod($action);
                    $fparams = array_slice($params, 2);

                    // next validate that we've gotten the right number of parameters for calling the action
                    if ($rMethod->getNumberOfRequiredParameters() != count($fparams)) {
                        throw new \moodle_exception('invalidnumberofparams', 'groupquiz', null, null, 'Invalid number of parameters passed to question modifiers call');
                    } else {

                        // now just call and return the method's return
                        $class = '\mod_groupquiz\questionmodifiers\\' . $type;
                        $typemodifier = new $class();
                        $return .= call_user_func_array(array($typemodifier, $action), $fparams);
                    }
                }
            }
        }

        return $return;
    }


    /* returns an array of this user's groups that have no in progress attempts */
/*
    public function check_attempt_for_group() {
        global $USER, $DB;

        $groups = $this->get_groupmanager()->get_user_groups_name_array();
        $groups = array_keys($groups);

        $validgroups = array();

        // we need to loop through the groups in case a user is in multiple,
        // and then check if there is a possibility for them to create an attempt for that user
        foreach ($groups as $group) {
            list($sql, $params) = $DB->get_in_or_equal(array($group));
            $query = 'SELECT * FROM {groupquiz_attempts} WHERE forgroupid ' . $sql .
                ' AND state = ?';
            $params[] = \mod_groupquiz\groupquiz_attempt::INPROGRESS;
            $recs = $DB->get_records_sql($query, $params);
            if (count($recs) == 0) {
                $validgroups[] = $group;
            }
        }

        return $validgroups;

    }
*/

    public function getall_attempts($open = 'all', $groupid = null) {
        global $DB;

        $sqlparams = array();
        $where = array();

        $where[] = 'groupquizid = ?';
        $sqlparams[] = $this->groupquiz->id;

        switch ($open) {
            case 'open':
                $where[] = 'state = ?';
                $sqlparams[] = groupquiz_attempt::INPROGRESS;
                break;
            case 'closed':
                $where[] = 'state = ?';
                $sqlparams[] = groupquiz_attempt::FINISHED;
                break;
            default:
                // add no condition for state when 'all' or something other than open/closed
        }

        if (!is_null($groupid)) {
                $where[] = 'forgroupid = ?';
                $sqlparams[] = $groupid;
        }

        $wherestring = implode(' AND ', $where);

        $sql = "SELECT * FROM {groupquiz_attempts} WHERE $wherestring";
        $dbattempts = $DB->get_records_sql($sql, $sqlparams);

        $attempts = array();
        foreach ($dbattempts as $dbattempt) {
            $attempts[ $dbattempt->id ] = new groupquiz_attempt($this->get_questionmanager(), $dbattempt,
                $this->getContext());
        }
        return $attempts;

    }

    public function get_open_attempt_for_group($groupid) {

        // use the getall attempts with the specified options
        $attempts = $this->getall_attempts('all', $groupid);
        $openAttempt = false;
        foreach ($attempts as $attempt) {
            /** @var groupquiz_attempt $attempt doc comment for type hinting */
            if ($attempt->getState() == 'inprogress' || $attempt->getState() == 'notstarted') {
                $openAttempt = $attempt;
            }
        }
	// returns most recent one?
        return $openAttempt;

    }

    public function get_group_attempt($group) {
        if (is_null($group)) {
            return false;
        }
        $openAttempt = $this->get_open_attempt_for_group($group);
        if (!is_null($openAttempt)) {
            $this->openAttempt = $openAttempt;
	    return true;
        }
        return false;
    }

    public function init_attempt($preview, $group) {
        global $DB, $USER;
	// TODO handle preview mode
        if (is_null($group) || ($group == 0)) {
	    return false;
	}
        $openAttempt = $this->get_open_attempt_for_group($group);
        if ($openAttempt !== false) {
            $this->openAttempt = $openAttempt;
	    return true;
        }

        // create a new attempt
        $attempt = new \mod_groupquiz\groupquiz_attempt($this->get_questionmanager());
        $attempt->userid = $USER->id;
	$attempt->userstart = $USER->id;
        $attempt->forgroupid =  $group;
        $attempt->state = \mod_groupquiz\groupquiz_attempt::NOTSTARTED;
        $attempt->timemodified = time();
        $attempt->timestart = time();
        $attempt->timefinish = null;
        $attempt->groupquizid = $this->getRTQ()->id;
        $attempt->get_html_head_contributions();
        $attempt->setState('inprogress');
	$attempt->attemptnum = null;
	$attempt->userstop = null;
	$attempt->sumgrades = 0;

        if ($attempt->save()) {
            $this->openAttempt = $attempt;
	} else {
	    return false;
	}

        $params = array(
            'objectid'      => $this->groupquiz->id,
            'context'       => $this->context,
            'relateduserid' => $USER->id
        );
        $event = \mod_groupquiz\event\attempt_started::create($params);
	// TODO figure out what its sedning a null object
        $event->add_record_snapshot('groupquiz_attempts', $this->openAttempt->get_attempt());
        $event->trigger();

        return true; // return true if we get to here
    }

    /**
     * gets a specific attempt from the DB
     *
     * @param int $attemptid
     *
     * @return \mod_activequiz\activequiz_attempt
     */
    public function get_user_attempt($attemptid) {
        global $DB;
        if ($DB->record_exists('groupquiz_attempts', array('id' => $attemptid))) {
            $dbattempt = $DB->get_record('groupquiz_attempts', array('id' => $attemptid));
            return new \mod_groupquiz\groupquiz_attempt($this->get_questionmanager(), $dbattempt, $this->getContext());
        } else {
            return null;
        }
    }

    public function get_intro($attemptid) {
	return $this->intro;
    }

    public function get_openclose_state() {
	$state = 'open';
	$timenow = time();
	if ($this->groupquiz->timeopen && ($timenow < $this->groupquiz->timeopen)) {
	    $state = 'unopen';
        } else if ($this->groupquiz->timeclose && ($timenow > $this->groupquiz->timeclose)) {
	    $state = 'closed';
        }

	return $state;
    }

    public function canreviewmarks($reviewoptions, $state) {
	$canreviewmarks = false;
        if ($state == 'open') {
            if ($reviewoptions->reviewmarks & \mod_groupquiz_display_options::LATER_WHILE_OPEN) {
                $canreviewmarks = true;
            }
        } else if ($state == 'closed') {
            if ($reviewoptions->reviewmarks & \mod_groupquiz_display_options::AFTER_CLOSE) {
                $canreviewmarks = true;
            }
        }
	return  $canreviewmarks;
    }

    public function canreviewattempt($reviewoptions, $state) {
        $canreviewattempt = false;
        if ($state == 'open') {
            if ($reviewoptions->reviewattempt & \mod_groupquiz_display_options::LATER_WHILE_OPEN) {
                $canreviewattempt = true;
            }
        } else if ($state == 'closed') {
            if ($reviewoptions->reviewattempt & \mod_groupquiz_display_options::AFTER_CLOSE) {
                $canreviewattempt = true;
            }
        }
        return  $canreviewattempt;
    }

}


