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
 * groupquiz configuration form
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

require_once ($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/groupquiz/locallib.php');

class mod_groupquiz_mod_form extends moodleform_mod {
    /** @var array options to be used with date_time_selector fields in the quiz. */
    public static $datefieldoptions = array('optional' => true);
    protected static $reviewfields = array(); // Initialised in the constructor.

    public function __construct($current, $section, $cm, $course) {
        self::$reviewfields = array(
            'attempt'          => array('theattempt', 'groupquiz'),
            'correctness'      => array('whethercorrect', 'question'),
            'marks'            => array('marks', 'groupquiz'),
            'specificfeedback' => array('specificfeedback', 'question'),
            'generalfeedback'  => array('generalfeedback', 'question'),
            'rightanswer'      => array('rightanswer', 'question'),
            'overallfeedback'  => array('reviewoverallfeedback', 'groupquiz'),
            'manualcomment'    => array('manualcomment', 'groupquiz')
        );
        parent::__construct($current, $section, $cm, $course);
    }

    protected function definition() {
        global $COURSE, $CFG, $DB, $PAGE;
        $groupquizconfig = get_config('groupquiz');
        $mform = $this->_form;

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Name.
        $mform->addElement('text', 'name', get_string('name'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Introduction.
        $this->standard_intro_elements(get_string('introduction', 'groupquiz'));

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'timing', get_string('timing', 'groupquiz'));

        // Open and close dates.
        $mform->addElement('date_time_selector', 'timeopen', get_string('quizopen', 'groupquiz'),
                self::$datefieldoptions);
        $mform->addHelpButton('timeopen', 'quizopenclose', 'quiz');

        $mform->addElement('date_time_selector', 'timeclose', get_string('quizclose', 'groupquiz'),
                self::$datefieldoptions);

        // Time limit.
        $mform->addElement('duration', 'timelimit', get_string('timelimit', 'groupquiz'),
                array('optional' => true));
        $mform->addHelpButton('timelimit', 'timelimit', 'groupquiz');
        $mform->setAdvanced('timelimit', $groupquizconfig->timelimit_adv);
        $mform->setDefault('timelimit', $groupquizconfig->timelimit);

        // -------------------------------------------------------------------------------
        // Grade settings.
        $this->standard_grading_coursemodule_elements();
        $mform->removeElement('grade');
        if (property_exists($this->current, 'grade')) {
            $currentgrade = $this->current->grade;
        } else {
            $currentgrade = $groupquizconfig->maximumgrade;
        }
        $mform->addElement('hidden', 'grade', $currentgrade);
        $mform->setType('grade', PARAM_FLOAT);

        $mform->addElement('select', 'grademethod',
            get_string('grademethod', 'groupquiz'),
            \mod_groupquiz\utils\scaletypes::get_display_types());
        $mform->setType('grademethod', PARAM_INT);
        $mform->addHelpButton('grademethod', 'grademethod', 'groupquiz');


        // -------------------------------------------------------------------------------
        // Group settings
        $mform->addElement('header', 'groupsubmissionsettings', get_string('groupsubmissionsettings', 'assign'));

	// TODO not implemented, we might need a new table to store this
/*
        $name = get_string('requireallteammemberssubmit', 'assign');
        $mform->addElement('selectyesno', 'requireallteammemberssubmit', $name);
        $mform->addHelpButton('requireallteammemberssubmit', 'requireallteammemberssubmit', 'assign');
        //`$mform->hideIf('requireallteammemberssubmit', 'teamsubmission', 'eq', 0);
        $mform->disabledIf('requireallteammemberssubmit', 'submissiondrafts', 'eq', 0);
*/

        $groupings = groups_get_all_groupings($COURSE->id);
        $options = array();
        foreach ($groupings as $grouping) {
            $options[$grouping->id] = $grouping->name;
        }

        $name = get_string('grouping', 'groupquiz');
        $mform->addElement('select', 'grouping', $name, $options);
        $mform->addHelpButton('grouping', 'grouping', 'groupquiz');
        if (groupquiz_has_attempts($groupquiz->id)) {
            $mform->freeze('grouping');
        }
	// is there a default set of groups?
        $mform->addRule('grouping', null, 'required', null, 'client');

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'interactionhdr', get_string('questionbehaviour', 'groupquiz'));

        // Shuffle within questions.
        $mform->addElement('selectyesno', 'shuffleanswers', get_string('shufflewithin', 'groupquiz'));
        $mform->addHelpButton('shuffleanswers', 'shufflewithin', 'groupquiz');
        $mform->setAdvanced('shuffleanswers', $groupquizconfig->shuffleanswers_adv);
        $mform->setDefault('shuffleanswers', $groupquizconfig->shuffleanswers);

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'reviewoptionshdr',
                get_string('reviewoptionsheading', 'groupquiz'));
        $mform->addHelpButton('reviewoptionshdr', 'reviewoptionsheading', 'groupquiz');
        // Review options.
//        $this->add_review_options_group($mform, $groupquizconfig, 'during',
//                mod_groupquiz_display_options::DURING, true);
//        $this->add_review_options_group($mform, $groupquizconfig, 'immediately',
//                mod_groupquiz_display_options::IMMEDIATELY_AFTER);
        $this->add_review_options_group($mform, $groupquizconfig, 'open',
                mod_groupquiz_display_options::LATER_WHILE_OPEN);
        $this->add_review_options_group($mform, $groupquizconfig, 'closed',
                mod_groupquiz_display_options::AFTER_CLOSE);


	foreach (self::$reviewfields as $field => $notused) {
            $mform->disabledIf($field . 'closed', 'timeclose[enabled]');
        }

        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'display', get_string('appearance'));

        // Show user picture.
        $mform->addElement('select', 'showuserpicture', get_string('showuserpicture', 'groupquiz'),
                groupquiz_get_user_image_options());
        $mform->addHelpButton('showuserpicture', 'showuserpicture', 'groupquiz');
        $mform->setAdvanced('showuserpicture', $groupquizconfig->showuserpicture_adv);
        $mform->setDefault('showuserpicture', $groupquizconfig->showuserpicture);

        // -------------------------------------------------------------------------------
        $this->standard_coursemodule_elements();

        // -------------------------------------------------------------------------------
        $this->add_action_buttons();

    }

    /**
     * Adapted from the quiz module's review options group function
     *
     * @param      $mform
     * @param      $whenname
     * @param bool $withhelp
     */
    protected function add_review_options_group($mform, $groupquizconfig, $whenname,
            $when, $withhelp = false) {
        global $OUTPUT;

        $group = array();
        foreach (self::$reviewfields as $field => $string) {
            list($identifier, $component) = $string;

            $label = get_string($identifier, $component);
            if ($withhelp) {
                $label .= ' ' . $OUTPUT->help_icon($identifier, $component);
            }

            $group[] = $mform->createElement('checkbox', $field . $whenname, '', $label);
        }
        $mform->addGroup($group, $whenname . 'optionsgrp',
                get_string('review' . $whenname, 'groupquiz'), null, false);

        foreach (self::$reviewfields as $field => $notused) {
            $cfgfield = 'review' . $field;
            if ($groupquizconfig->$cfgfield & $when) {
                $mform->setDefault($field . $whenname, 1);
            } else {
                $mform->setDefault($field . $whenname, 0);
            }
        }

        if ($whenname != 'during') {
            $mform->disabledIf('correctness' . $whenname, 'attempt' . $whenname);
            $mform->disabledIf('specificfeedback' . $whenname, 'attempt' . $whenname);
            $mform->disabledIf('generalfeedback' . $whenname, 'attempt' . $whenname);
            $mform->disabledIf('rightanswer' . $whenname, 'attempt' . $whenname);
        }
    }


    protected function preprocessing_review_settings(&$toform, $whenname, $when) {
        foreach (self::$reviewfields as $field => $notused) {
            $fieldname = 'review' . $field;
            if (array_key_exists($fieldname, $toform)) {
                $toform[$field . $whenname] = $toform[$fieldname] & $when;
            }
        }
    }

    function data_preprocessing(&$toform) {
        if (isset($toform['grade'])) {
            // Convert to a real number, so we don't get 0.0000.
            $toform['grade'] = $toform['grade'] + 0;
        }

        if (count($this->_feedbacks)) {
            $key = 0;
            foreach ($this->_feedbacks as $feedback) {
                $draftid = file_get_submitted_draft_itemid('feedbacktext['.$key.']');
                $toform['feedbacktext['.$key.']']['text'] = file_prepare_draft_area(
                    $draftid,               // Draftid.
                    $this->context->id,     // Context.
                    'mod_groupquiz',             // Component.
                    'feedback',             // Filarea.
                    !empty($feedback->id) ? (int) $feedback->id : null, // Itemid.
                    null,
                    $feedback->feedbacktext // Text.
                );
                $toform['feedbacktext['.$key.']']['format'] = $feedback->feedbacktextformat;
                $toform['feedbacktext['.$key.']']['itemid'] = $draftid;

                if ($toform['grade'] == 0) {
                    // When a quiz is un-graded, there can only be one lot of
                    // feedback. If the quiz previously had a maximum grade and
                    // several lots of feedback, we must now avoid putting text
                    // into input boxes that are disabled, but which the
                    // validation will insist are blank.
                    break;
                }

                if ($feedback->mingrade > 0) {
                    $toform['feedbackboundaries['.$key.']'] =
                            round(100.0 * $feedback->mingrade / $toform['grade'], 6) . '%';
                }
                $key++;
            }
        }
/*
        if (isset($toform['timelimit'])) {
            $toform['timelimitenable'] = $toform['timelimit'] > 0;
        }
*/
        $this->preprocessing_review_settings($toform, 'during',
                mod_groupquiz_display_options::DURING);
        $this->preprocessing_review_settings($toform, 'immediately',
                mod_groupquiz_display_options::IMMEDIATELY_AFTER);
        $this->preprocessing_review_settings($toform, 'open',
                mod_groupquiz_display_options::LATER_WHILE_OPEN);
        $this->preprocessing_review_settings($toform, 'closed',
                mod_groupquiz_display_options::AFTER_CLOSE);
        $toform['attemptduring'] = true;
        $toform['overallfeedbackduring'] = false;

        // Completion settings check.
        if (empty($toform['completionusegrade'])) {
            $toform['completionpass'] = 0; // Forced unchecked.
        }

    }

    function data_postprocessing($data) {
    }

}
