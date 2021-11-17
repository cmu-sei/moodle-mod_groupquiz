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

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/questionlib.php');

/**
 * Realtime quiz renderer
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

class mod_groupquiz_renderer extends plugin_renderer_base {

    /** @var array $pagevars Includes other page information needed for rendering functions */
    protected $pagevars;

    /** @var moodle_url $pageurl easy access to the pageurl */
    protected $pageurl;

    /** @var \mod_groupquiz\groupquiz $rtq */
    protected $rtq;

    /** @var array Message to display with the page, is array with the first param being the type of message
     *              the second param being the message
     */
    protected $pageMessage;

    //TODO:  eventually think about making page specific renderer helpers so that we can make static calls for standard
    //TODO:      rendering on things.  E.g. editrenderer::questionblock();

    /**
     * Initialize the renderer with some variables
     *
     * @param \mod_groupquiz\groupquiz $RTQ
     * @param moodle_url                 $pageurl Always require the page url
     * @param array                      $pagevars (optional)
     */
    public function init($RTQ, $pageurl, $pagevars = array()) {
        $this->pagevars = $pagevars;
        $this->pageurl = $pageurl;
        $this->rtq = $RTQ;
    }


    /**
     * Sets a page message to display when the page is loaded into view
     *
     * base_header() must be called for the message to appear
     *
     * @param string $type
     * @param string $message
     */
    public function setMessage($type, $message) {
        $this->pageMessage = array($type, $message);
    }

    /**
     * Base header function to do basic header rendering
     *
     * @param string $tab the current tab to show as group
     */
    public function base_header($tab = 'view') {
        echo $this->output->header();
        echo groupquiz_view_tabs($this->rtq, $tab);
        $this->showMessage(); // shows a message if there is one
    }

    /**
     * Base footer function to do basic footer rendering
     *
     */
    public function base_footer() {
        echo $this->output->footer();
    }

    /**
     * shows a message if there is one
     *
     */
    protected function showMessage() {

        if (empty($this->pageMessage)) {
            return; // return if there is no message
        }

        if (!is_array($this->pageMessage)) {
            return; // return if it's not an array
        }

        switch ($this->pageMessage[0]) {
            case 'error':
                echo $this->output->notification($this->pageMessage[1], 'notifiyproblem');
                break;
            case 'success':
                echo $this->output->notification($this->pageMessage[1], 'notifysuccess');
                break;
            case 'info':
                echo $this->output->notification($this->pageMessage[1], 'notifyinfo');
                break;
            default:
                // unrecognized notification type
                break;
        }
    }

    /**
     * Shows an error message with the popup layout
     *
     * @param string $message
     */
    public function render_popup_error($message) {

        $this->setMessage('error', $message);
        echo $this->output->header();
        $this->showMessage();
        $this->base_footer();
    }



    /** View page functions */

    /**
     * Basic header for the view page
     *
     * @param bool $renderingquiz
     */
    public function view_header($renderingquiz = false) {

        // if we're rendering the quiz check if any of the question modifiers need jquery
        if ($renderingquiz) {

            $this->rtq->call_question_modifiers('requires_jquery', null);
            $this->rtq->call_question_modifiers('add_css', null);
        }

        $this->base_header('view');
    }


    /**
     * Displays the home view for the instructor
     *
     * @param \moodleform    $sessionform
     * @param bool|\stdclass $sessionstarted is a standard class when there is a session
     */
    public function view_inst_home() {
        echo html_writer::start_div('groupquizbox');
        echo $this->quiz_intro();
        echo html_writer::end_div();
    }

    /**
     * Displays the view home.
     *
     * @param \mod_groupquiz\forms\view\student_start_form $studentstartform
     * @param \mod_groupquiz\groupquiz_session            $session The groupquiz session object to call methods on
     */
    public function view_student_home() {
        global $USER;
	$timenow = time();
	$timeopen = $this->rtq->getRTQ()->timeopen;
	$timeclose = $this->rtq->getRTQ()->timeclose;
	$timelimit = $this->rtq->getRTQ()->timelimit;
        $state = $this->rtq->get_openclose_state();

        echo html_writer::start_div('groupquizbox');

        echo $this->quiz_intro();

	if ($state == 'unopen') {
	    echo html_writer::tag('p', get_string('notopen', 'groupquiz') . userdate($timeopen), array('id' => 'quiz_notavailable'));
        } else if ($state == 'closed') {
	    echo html_writer::tag('p', get_string('closed', 'groupquiz'). userdate($timeclose), array('id' => 'quiz_notavailable'));
	} else {
	    $this->render_start_button();
	}

	echo html_writer::end_div();

        $reviewoptions = $this->rtq->get_review_options();

        // show attempts table if rtq is set up to show attempts in the after review options
        $canreviewattempt =  $this->rtq->canreviewattempt($reviewoptions, $state);
        $canreviewmarks = $this->rtq->canreviewmarks($reviewoptions, $state);

        $groupid = $this->rtq->get_groupmanager()->get_user_group();
        $attempts = $this->rtq->getall_attempts('closed', $groupid);

        // show overall grade
        if ($canreviewmarks && $attempts) {
            $this->render_grade();
	}

	if ($attempts) {
            echo html_writer::start_div('groupquizbox');
            echo html_writer::tag('h3', get_string('attempts', 'groupquiz'));

            $viewownattemptstable = new \mod_groupquiz\tableviews\ownattempts('viewownattempts', $this->rtq, $this->pageurl, $attempts);
            $viewownattemptstable->setup();
            $viewownattemptstable->set_data();
            $viewownattemptstable->finish_output();
            echo html_writer::end_div();
	}

    }

    public function render_start_button() {
	$output = '';

	$groupid = $this->rtq->get_groupmanager()->get_user_group();
	$this->rtq->get_group_attempt($groupid);

	if ($this->rtq->openAttempt) {
            $output .= html_writer::tag('p', get_string('continueinst', 'groupquiz'), array('id' => 'quizstartinst'));
	    $attemptid = $this->rtq->openAttempt->id;
            $params = array(
                'id' => $this->rtq->getCM()->id,
                'action' => 'continuequiz',
		'attemptid' => $attemptid,
		'groupid' => $groupid
            );
            $starturl = new moodle_url('/mod/groupquiz/view.php', $params);
            $output .= $this->output->single_button($starturl, 'Continue');
	} else {
            $output .= html_writer::tag('p', get_string('startinst', 'groupquiz'), array('id' => 'quizstartinst'));
            $params = array(
                'id' => $this->rtq->getCM()->id,
                'action' => 'startquiz',
		'groupid' => $groupid
            );
            $starturl = new moodle_url('/mod/groupquiz/view.php', $params);
            $output .= $this->output->single_button($starturl, 'Start');
	}
	echo $output;
    }

    /**
     * Renders the quiz to the page
     *
     * @param \mod_groupquiz\groupquiz_attempt $attempt
     */

    public function render_quiz(\mod_groupquiz\groupquiz_attempt $attempt) {

        $this->init_quiz_js($attempt);

        $output = '';

	$output .= html_writer::start_div('groupquizbox');
        $output .= $this->quiz_intro();
        $output .= html_writer::end_div();

        $output .= html_writer::start_div('', array('id'=>'quizview'));

        if ($this->rtq->is_instructor()) {
	    //TODO will instructor view it or not... support for preview?
            $instructions = get_string('instructorquizinst', 'groupquiz');
        } else {
            $instructions = get_string('studentquizinst', 'groupquiz');
        }
        $loadingpix = $this->output->pix_icon('i/loading', 'loading...');
        $output .= html_writer::start_div('groupquizloading', array('id' => 'loadingbox'));
        $output .= html_writer::tag('p', get_string('loading', 'groupquiz'), array('id' => 'loadingtext'));
        $output .= $loadingpix;
        $output .= html_writer::end_div();

	// quiz instructions and timer
        $output .= html_writer::start_div('groupquizbox', array('id' => 'instructionsbox'));
	$output .= $instructions;
	$output .= $this->countdown_timer($attempt);
        $output .= html_writer::end_div();

        foreach ($attempt->getSlots() as $slot) {
            // render question form.
            $output .= $this->render_question_form($slot, $attempt);
        }

        $params = array(
            'id' => $this->rtq->getCM()->id,
	    'attemptid' => $this->rtq->openAttempt->id,
	    'groupid' => $this->rtq->openAttempt->forgroupid,
	    'action' => 'submitquiz'
        );
        $endurl = new moodle_url('/mod/groupquiz/view.php', $params);
        $output .= $this->output->single_button($endurl, 'Submit Quiz');

        $output .= html_writer::end_div();
        echo $output;
    }

    /**
     * Render a specific question in its own form so it can be submitted
     * independently of the rest of the questions
     *
     * @param int                                $slot the id of the question we're rendering
     * @param \mod_groupquiz\groupquiz_attempt $attempt
     *
     * @return string HTML fragment of the question
     */
    public function render_question_form($slot, $attempt) {

        $output = '';
        $qnum = $attempt->get_question_number();
        // Start the form.
        $output .= html_writer::start_tag('div', array('class' => 'groupquizbox hidden', 'id' => 'q' . $qnum . '_container'));

        $output .= html_writer::start_tag('form',
            array('action'  => '', 'method' => 'post',
                  'enctype' => 'multipart/form-data', 'accept-charset' => 'utf-8',
                  'id'      => 'q' . $qnum, 'class' => 'groupquiz_question',
                  'name'    => 'q' . $qnum));


        $output .= $attempt->render_question($slot);

        $output .= html_writer::empty_tag('input', array('type'  => 'hidden', 'name' => 'slots',
                                                         'value' => $slot));

        $config = get_config('groupquiz');

        // TODO toggle based on quiz settings
	//if ($config->showliveresponses) {
	$output .= $this->render_user($qnum, $attempt);
	//}

        $savebtn = html_writer::tag('button', get_string('savequestion', 'groupquiz'), array(
                'class'   => 'btn btn-secondary',
                'id'      => 'q' . $qnum . '_save',
                'onclick' => 'groupquiz.save_question(\'q' . $qnum . '\'); return false;'
            )
        );

        // instructors don't need to save questions
        if (!$this->rtq->is_instructor()) {
            $savebtncont = html_writer::div($savebtn, 'question_save');
        } else {
            $savebtncont = '';
        }

        $output .= html_writer::div($savebtncont, 'save_row');


        // Finish the form.
        $output .= html_writer::end_tag('form');

        $output .= html_writer::end_tag('div');


        return $output;
    }

    public function render_user($qnum, $attempt) {

	// gather data
	$quba = $attempt->get_quba();
        $qa = $quba->get_question_attempt($qnum);
	$data = $qa->get_last_qt_data();

	if ((count($data) > 0) & (array_key_exists('answer', $data))) {
            global $DB, $OUTPUT;
            $last = $qa->get_last_step_with_qt_var('answer');
            $userid = $last->get_user_id();
	    $user = $DB->get_record("user", array('id' => $userid));
	    $avataroptions = array('link' => false, 'visibletoscreenreaders' => false);
	    $useravatar = $OUTPUT->user_picture($user, $avataroptions);
	    $username = fullname($user);
            $time = userdate($last->get_timecreated()) . " " . usertimezone();

	} else {
            $useravatar = '';
            $username = '';
            $time = '';
	}

	$output = '';

        $output .= html_writer::start_tag('div', array('class' => 'userinfo', 'id' => 'q' . $qnum . '_userinfo'));

        $output .= html_writer::start_tag('div', array('class' => 'usertitle', 'id' => 'q' . $qnum . '_usertitle'));
        $output .= "Last Response:";
        $output .= html_writer::end_tag('div'); // user title

        $output .= html_writer::start_tag('div', array('class' => 'userdetail', 'id' => 'q' . $qnum . '_userdetail'));
        if ($this->rtq->getRTQ()->showuserpicture) {
            $output .= html_writer::start_tag('span', array('class' => 'avatar current', 'id' => 'q' . $qnum . '_useravatar'));
	    $output .= $useravatar;
            $output .= html_writer::end_tag('span');
        }
        $output .= html_writer::start_tag('span', array('class' => 'username', 'id' => 'q' . $qnum . '_username'));
	$output .= $username;
        $output .= html_writer::end_tag('span');
        $output .= html_writer::start_tag('span', array('class' => 'usertime', 'id' => 'q' . $qnum . '_usertime'));
        $output .= $time;
        $output .= html_writer::end_tag('span'); // user time

        $output .= html_writer::end_tag('div'); // user detail


        $output .= html_writer::end_tag('div'); //user info

	return $output;
    }


    /**
     * Initializes quiz javascript and strings for javascript when on the
     * quiz view page, or the "quizstart" action
     *
     * @param \mod_groupquiz\groupquiz_attempt $attempt
     * @throws moodle_exception throws exception when invalid question on the attempt is found
     */

    public function init_quiz_js($attempt) {
        global $USER, $CFG;

        $this->page->requires->js('/mod/groupquiz/js/core.js');

        // add window.onload script manually to handle removing the loading mask
        echo html_writer::start_tag('script', array('type' => 'text/javascript'));
        echo <<<EOD
            (function preLoad(){
                window.addEventListener('load', function(){groupquiz.quiz_page_loaded();}, false);
            }());
EOD;
        echo html_writer::end_tag('script');

        if ($this->rtq->is_instructor()) {
	    // TODO will we ever need this
            $this->page->requires->js('/mod/groupquiz/js/instructor.js');
        } else {
            $this->page->requires->js('/mod/groupquiz/js/student.js');
        }

        // next set up a class to pass to js for js info
        $jsinfo = new stdClass();
        $jsinfo->sesskey = sesskey();
        $jsinfo->siteroot = $CFG->wwwroot;
        $jsinfo->groupquizid = $this->rtq->getRTQ()->id;
	if (!is_null($attempt->id)) {
            $jsinfo->attemptid = $attempt->id;
	}
        $jsinfo->slots = $attempt->getSlots();
        $jsinfo->isinstructor = ($this->rtq->is_instructor() ? 'true' : 'false');
	$jsinfo->id = $this->rtq->getCM()->id;
        // manually create the questions stdClass as we can't support JsonSerializable yet
        $questions = array();
        foreach ($attempt->get_questions() as $q) {

            $question = new stdClass();
            $question->id = $q->getId();
            $question->question = $q->getQuestion();
            $question->slot = $attempt->get_question_slot($q);

            // if the slot is false, throw exception for invalid question on quiz attempt
            if ($question->slot === false) {
                $a = new stdClass();
                $a->questionname = $q->getQuestion()->name;

                throw new moodle_exception('invalidquestionattempt', 'mod_groupquiz',
                    '', $a,
                    'invalid slot when building questions array on quiz renderer');
            }

            $questions[ $question->slot ] = $question;
        }
        $jsinfo->questions = $questions;

        // print jsinfo to javascript
        echo html_writer::start_tag('script', array('type' => 'text/javascript'));
        echo "rtqinitinfo = " . json_encode($jsinfo);
        echo html_writer::end_tag('script');

        // add strings for js
	// TODO remove if not needed
        $this->page->requires->strings_for_js(array(
            'hidestudentresponses',
            'showstudentresponses',
        ), 'groupquiz');

        $this->page->requires->strings_for_js(array('seconds'), 'moodle');


        // finally allow question modifiers to add their own css/js
        $this->rtq->call_question_modifiers('add_js', null);

    }

    /**
     * No questions view
     *
     * @param bool $isinstructor
     */
    public function no_questions($isinstructor) {

        echo $this->output->box_start('generalbox boxaligncenter groupquizbox');

        echo html_writer::tag('p', get_string('no_questions', 'groupquiz'));

        if ($isinstructor) {
            // show a button to edit the quiz
            $params = array(
                'cmid' => $this->rtq->getCM()->id
            );
            $editurl = new moodle_url('/mod/groupquiz/edit.php', $params);
            $editbutton = $this->output->single_button($editurl, get_string('edit', 'groupquiz'), 'get');
            echo html_writer::tag('p', $editbutton);
        }

        echo $this->output->box_end();
    }

    /**
     * Basic footer for the view page
     *
     */
    public function view_footer() {
        $this->base_footer();
    }


    /** End View page functions */


    /** Attempt view rendering **/


    /**
     * Render a specific attempt
     *
     * @param \mod_groupquiz\groupquiz_attempt $attempt
     */
    public function render_attempt($attempt) {

        //TODO what message?
        $this->showMessage();

        $timenow = time();
        $timeopen = $this->rtq->getRTQ()->timeopen;
        $timeclose = $this->rtq->getRTQ()->timeclose;
        $timelimit = $this->rtq->getRTQ()->timelimit;

	$state = $this->rtq->get_openclose_state();

        if ($state == 'unopen') {
            echo html_writer::start_div('groupquizbox');
            echo html_writer::tag('p', get_string('notopen', 'groupquiz') . userdate($timeopen), array('id' => 'quiz_notavailable'));
            echo html_writer::end_tag('div');

        } else if ($state == 'closed') {
            echo html_writer::start_div('groupquizbox');
            echo html_writer::tag('p', get_string('closed', 'groupquiz'). userdate($timeclose), array('id' => 'quiz_notavailable'));
            echo html_writer::end_tag('div');
        }

        $reviewoptions = $this->rtq->get_review_options();

	$canreviewattempt =  $this->rtq->canreviewattempt($reviewoptions, $state);
        $canreviewmarks = $this->rtq->canreviewmarks($reviewoptions, $state);

        // show overall grade
        if ($canreviewmarks && (!$this->rtq->is_instructor())) {
            $this->render_grade();
        }

        if ($attempt && ($canreviewattempt || $this->rtq->is_instructor())) {
            foreach ($attempt->getSlots() as $slot) {
                if ($this->rtq->is_instructor()) {
                    echo $this->render_edit_review_question($slot, $attempt);
                } else {
                    echo $this->render_review_question($slot, $attempt);
                }
            }
        } else if ($attempt && !$canreviewattempt) {
            echo html_writer::tag('p', get_string('noreview', 'groupquiz'), array('id' => 'review_notavailable'));
	}

	$this->render_return_button();
    }


    /**
     * Renders an individual question review
     *
     * This is the "edit" version that are for instructors/users who have the control capability
     *
     * @param int                                $slot
     * @param \mod_groupquiz\groupquiz_attempt $attempt
     *
     * @return string HTML fragment
     */
    public function render_edit_review_question($slot, $attempt) {

        $qnum = $attempt->get_question_number();
        $output = '';

        $output .= html_writer::start_div('groupquizbox', array('id' => 'q' . $qnum . '_container'));


        $action = clone($this->pageurl);

        $output .= html_writer::start_tag('form',
            array('action'  => '', 'method' => 'post',
                  'enctype' => 'multipart/form-data', 'accept-charset' => 'utf-8',
                  'id'      => 'q' . $qnum, 'class' => 'groupquiz_question',
                  'name'    => 'q' . $qnum));


        $output .= $attempt->render_question($slot, true, 'edit');

        $output .= html_writer::empty_tag('input', array('type'  => 'hidden', 'name' => 'slots',
                                                         'value' => $slot));
        $output .= html_writer::empty_tag('input', array('type'  => 'hidden', 'name' => 'slot',
                                                         'value' => $slot));
        $output .= html_writer::empty_tag('input', array('type'  => 'hidden', 'name' => 'action',
                                                         'value' => 'savecomment'));
        $output .= html_writer::empty_tag('input', array('type'  => 'hidden', 'name' => 'sesskey',
                                                         'value' => sesskey()));

        $savebtn = html_writer::empty_tag('input', array('type'  => 'submit', 'name' => 'submit',
                                                         'value' => get_string('savequestion', 'groupquiz'), 'class' => 'btn btn-secondary'));


        $mark = $attempt->get_slot_mark($slot);
        $maxmark = $attempt->get_slot_max_mark($slot);

	$output .= $this->render_user($qnum, $attempt);
        $output .= html_writer::start_tag('p');
        $output .= 'Marked ' . $mark . ' / ' . $maxmark;
        $output .= html_writer::end_tag('p');

	// only add save button if attempt is finished
	if ($attempt->getState() === 'finished') {
            $output .= html_writer::div($savebtn, 'save_row');
	}

        // Finish the form.
        $output .= html_writer::end_tag('form');
        $output .= html_writer::end_div();

        return $output;
    }

    /**
     * Render a review question with no editing capabilities.
     *
     * Reviewing will be based upon the after review options specified in module settings
     *
     * @param int                                $slot
     * @param \mod_groupquiz\groupquiz_attempt $attempt
     *
     * @return string HTML fragment for the question
     */
    public function render_review_question($slot, $attempt) {

        $qnum = $attempt->get_question_number();
	$when = $this->rtq->get_openclose_state();

        $output = '';

        $output .= html_writer::start_div('groupquizbox', array('id' => 'q' . $qnum . '_container'));

        $output .= $attempt->render_question($slot, true, $this->rtq->get_review_options(), $when);

	$output .= $this->render_user($qnum, $attempt);

        $output .= html_writer::end_div();

        return $output;
    }

    /** End attempt view rendering **/

    public function quiz_intro() {
    if (html_is_blank($this->rtq->getRTQ()->intro)) {
            //TODO render generic message such as "Press Start to begin the quiz"
            // or "Press Continue to joins your group's active quiz"
            return '';
        }
	// return plain html stored by atto editor in the intro field
	return $this->rtq->getRTQ()->intro;
    }

    public function render_grade() {
	global $USER;

        echo html_writer::start_div('groupquizbox');
        $a = new stdClass();
        $usergrades = \mod_groupquiz\utils\grade::get_user_grade($this->rtq->getRTQ(), $USER->id);

        // should only be 1 grade, but we'll always get end just in case
        if (!empty($usergrades)) {
            $usergrade = end($usergrades);
            echo html_writer::start_tag('h3');
            echo get_string('overallgrade', 'groupquiz', number_format($usergrade, 2));
            echo html_writer::end_tag('h3');
	} else {
            echo html_writer::start_tag('h3');
            echo get_string('overallgrade', 'groupquiz', '0');
            echo html_writer::end_tag('h3');
	}
        echo html_writer::end_div();
    }

    public function render_return_button() {
	// TODO display a message
	// maybe one informing the user that
	// the quiz was submitted or that review options
	// are not available at this time (in previous function)
	$output = '';
        $params = array(
            'id' => $this->rtq->getCM()->id,
            'action' => ''
        );
        $starturl = new moodle_url('/mod/groupquiz/view.php', $params);
        $output.= $this->output->single_button($starturl, 'Return');
        echo $output;
    }

    public function countdown_timer($attemptobj) {
	$output = '';

	// do not display timer if not set
        if (!$this->rtq->getRTQ()->timelimit) {
	    return;
	}

	$timenow = time();
        $timeleft = $attemptobj->get_time_left_display($timenow, $this->rtq->getRTQ());

        if ($timeleft !== false) {
            $timerstartvalue = $timeleft;
        } else {
	    return;
	}

	$output .= html_writer::start_div('timerbox hidden', array('id' => 'timerbox'));
        $output .= html_writer::start_tag('span', array('class' => 'timertext', 'id' => 'timertext'));
	$output .= 'Time Left: ';
        $output .= html_writer::end_tag('span');
	$output .= html_writer::start_tag('span', array('class' => 'timeleft', 'id' => 'timeleft'));
	$output .= $timeleft;
        $output .= html_writer::end_tag('span');
        $output .= html_writer::end_div();

	return $output;
    }

}

