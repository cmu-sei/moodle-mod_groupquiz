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

/**
 * The group quiz object: the quiz record with its course, course module, context, questions and attempts.
 */
class groupquiz {
    /**
     * @var array $review fields Static review fields to add as options
     */
    public static $reviewfields = [
        'attempt'          => ['theattempt', 'groupquiz'],
        'correctness'      => ['whethercorrect', 'question'],
        'marks'            => ['marks', 'groupquiz'],
        'specificfeedback' => ['specificfeedback', 'question'],
        'generalfeedback'  => ['generalfeedback', 'question'],
        'rightanswer'      => ['rightanswer', 'question'],
    'overallfeedback'  => ['overallfeedback', 'question'],
        'manualcomment'    => ['manualcomment', 'groupquiz'],
    ];

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


    /** @var groupquiz_attempt|bool|null The open attempt of the group, once it has been loaded. */
    public $openAttempt; // phpcs:ignore moodle.NamingConventions.ValidVariableName.MemberNameUnderscore

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
        foreach (self::$reviewfields as $field => $notused) {
            $reviewoptions->$field = $formreviewoptions[$field];
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
     * @param string $renderersubtype Renderer sub-type to load if requested
     *
     */
    public function __construct($cm, $course, $groupquiz, $pageurl, $pagevars = [], $renderersubtype = null) {
        global $CFG, $PAGE;

        $this->cm = $cm;
        $this->course = $course;
        $this->groupquiz = $groupquiz;
        $this->pagevars = $pagevars;

        $this->context = \context_module::instance($cm->id);
        $PAGE->set_context($this->context);

        $this->renderer = $PAGE->get_renderer('mod_groupquiz', $renderersubtype);
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
    public function getcm() {
        return $this->cm;
    }

    /**
     * Get the course instance
     *
     * @return object
     */
    public function getcourse() {
        return $this->course;
    }

    /**
     * Returns the reqltimequiz database record instance
     *
     * @return object
     */
    public function getrtq() {
        return $this->groupquiz;
    }

    /**
     * Saves the rtq instance to the database
     *
     * @return bool
     */
    public function savertq() {
        global $DB;

        return $DB->update_record('groupquiz', $this->groupquiz);
    }

    /**
     * Gets the context for this instance
     *
     * @return \context_module
     */
    public function getcontext() {
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
        $reviewoptions->reviewmanualcomment = $this->groupquiz->reviewmanualcomment;

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
                $questiontypes = [$question->getQuestion()->qtype];
            } else {
                $questiontypes = [];
            }
        } else {
            // we're going through all question types defined by the instance
            $questiontypes = [];
            $questions = $this->get_questionmanager()->get_questions();
            /** @var \mod_groupquiz\groupquiz_question $question */
            foreach ($questions as $question) {
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
                    $rmethod = $reflection->getMethod($action);
                    $fparams = array_slice($params, 2);

                    // next validate that we've gotten the right number of parameters for calling the action
                    if ($rmethod->getNumberOfRequiredParameters() != count($fparams)) {
                        throw new \moodle_exception('invalidnumberofparams', 'groupquiz', null, null, 'Invalid number of parameters passed to question modifiers call');
                    } else {
                        // now just call and return the method's return
                        $class = '\mod_groupquiz\questionmodifiers\\' . $type;
                        $typemodifier = new $class();
                        $return .= call_user_func_array([$typemodifier, $action], $fparams);
                    }
                }
            }
        }

        return $return;
    }

    /**
     * Get the attempts of the quiz.
     *
     * @param string $open "open", "closed" or "all"; anything else counts as "all".
     * @param int|null $groupid Only the attempts of this group, or null for every group.
     * @return groupquiz_attempt[] The attempts keyed by attempt id.
     */
    public function getall_attempts($open = 'all', $groupid = null) {
        global $DB;

        $sqlparams = [];
        $where = [];

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

        $sql = "SELECT * FROM {groupquiz_attempts} WHERE $wherestring ORDER BY timefinish ASC";
        $dbattempts = $DB->get_records_sql($sql, $sqlparams);

        $attempts = [];
        foreach ($dbattempts as $dbattempt) {
            $attempts[$dbattempt->id] = new groupquiz_attempt(
                $this->get_questionmanager(),
                $dbattempt,
                $this->getContext()
            );
        }
        return $attempts;
    }

    /**
     * Get the open attempt of a group.
     *
     * @param int $groupid The group id.
     * @return groupquiz_attempt|bool The latest attempt that is in progress or not started, or false when there is none.
     */
    public function get_open_attempt_for_group($groupid) {

        // use the getall attempts with the specified options
        $attempts = $this->getall_attempts('all', $groupid);
        $openattempt = false;
        /** @var groupquiz_attempt $attempt */
        foreach ($attempts as $attempt) {
            if ($attempt->getState() == 'inprogress' || $attempt->getState() == 'notstarted') {
                $openattempt = $attempt;
            }
        }
        // returns most recent one?
        return $openattempt;
    }

    /**
     * Load the open attempt of a group into this object.
     *
     * @param int|null $group The group id.
     * @return bool True when the group has an open attempt, which is then set as the open attempt.
     */
    public function get_group_attempt($group) {
        if (is_null($group)) {
            return false;
        }
        $openattempt = $this->get_open_attempt_for_group($group);
        if (!is_null($openattempt)) {
            $this->openAttempt = $openattempt;
            return true;
        }
        return false;
    }

    /**
     * Load the open attempt of a group, or create a new attempt when there is none.
     *
     * @param bool $preview Whether this is a preview.
     * @param int|null $group The group id.
     * @return bool True when an attempt is loaded or created, false otherwise.
     */
    public function init_attempt($preview, $group) {
        global $DB, $USER;

        if (!$preview && (is_null($group) || $group == 0)) {
            return false;
        }

        if (!$preview && !is_null($group) && $group != 0) {
            $openattempt = $this->get_open_attempt_for_group($group);
            if ($openattempt !== false) {
                $this->openAttempt = $openattempt;
                return true;
            }
        }

        if ($preview) {
            if (is_null($group)) {
                $group = 0;
            }

            $openattempt = $this->get_open_attempt_for_group($group);
            if ($openattempt !== false) {
                $this->openAttempt = $openattempt;
                return true;
            }
        }

        // create a new attempt
        $attempt = new \mod_groupquiz\groupquiz_attempt($this->get_questionmanager());
        $attempt->userid = $USER->id;
        $attempt->userstart = $USER->id;
        $attempt->forgroupid  = $group;
        $attempt->state = \mod_groupquiz\groupquiz_attempt::NOTSTARTED;
        $attempt->timemodified = time();
        $attempt->timestart = time();
        $attempt->timefinish = null;
        $attempt->groupquizid = $this->getRTQ()->id;
        $attempt->get_html_head_contributions();
        $attempt->setState('inprogress');
        // TODO get previous attempt count and update
        $attempt->attemptnum = null;
        $attempt->userstop = null;
        $attempt->sumgrades = 0;
        $attempt->preview = $preview;

        if ($attempt->save()) {
            $this->openAttempt = $attempt;
        } else {
            return false;
        }

        $params = [
            'objectid'      => $this->groupquiz->id,
            'context'       => $this->context,
            'relateduserid' => $USER->id,
        ];
        $event = \mod_groupquiz\event\attempt_started::create($params);
        // TODO figure out what is sending a null object
        $event->add_record_snapshot('groupquiz_attempts', $this->openAttempt->get_attempt());
        $event->trigger();

        return true;
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
        if ($DB->record_exists('groupquiz_attempts', ['id' => $attemptid])) {
            $dbattempt = $DB->get_record('groupquiz_attempts', ['id' => $attemptid]);
            return new \mod_groupquiz\groupquiz_attempt($this->get_questionmanager(), $dbattempt, $this->getContext());
        } else {
            return null;
        }
    }

    /**
     * Get the quiz introduction.
     *
     * @param int $attemptid The attempt id; not used.
     * @return string The introduction.
     */
    public function get_intro($attemptid) {
        return $this->intro;
    }

    /**
     * Get whether the quiz is open, based on its open and close times.
     *
     * @return string "unopen", "open" or "closed".
     */
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

    /**
     * Check whether marks can be reviewed in the given state.
     *
     * @param object $reviewoptions The quiz review options.
     * @param string $state The open or closed state of the quiz.
     * @return bool True when marks can be reviewed.
     */
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

    /**
     * Check whether the attempt can be reviewed in the given state.
     *
     * @param object $reviewoptions The quiz review options.
     * @param string $state The open or closed state of the quiz.
     * @return bool True when the attempt can be reviewed.
     */
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
