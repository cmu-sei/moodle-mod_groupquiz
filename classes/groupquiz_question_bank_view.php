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

use mod_groupquiz\qbanktypes\question_bank_add_to_rtq_action_column;

/**
 * Subclass of the question bank view class to change the way it works/looks
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

class groupquiz_question_bank_view extends \core_question\bank\view {


    /**
     * Define the columns we want to be displayed on the question bank
     *
     * @return array
     */
    protected function wanted_columns() {

        $defaultqbankcolums = array(
            'question_bank_add_to_rtq_action_column',
            'checkbox_column',
            'question_type_column',
            'question_name_column',
            'preview_action_column',
        );

        foreach ($defaultqbankcolums as $fullname) {
            if (!class_exists($fullname)) {
                if (class_exists('mod_groupquiz\\qbanktypes\\' . $fullname)) {
                    $fullname = 'mod_groupquiz\\qbanktypes\\' . $fullname;
                } else if (class_exists('core_question\\bank\\' . $fullname)) {
                    $fullname = 'core_question\\bank\\' . $fullname;
                } else if (class_exists('question_bank_' . $fullname)) {
                    // debugging('Legacy question bank column class question_bank_' .
                    //    $fullname . ' should be renamed to mod_groupquiz\\qbanktypes\\' .
                    //    $fullname, DEBUG_DEVELOPER);
                    $fullname = 'question_bank_' . $fullname;
                } else {
                    throw new coding_exception("No such class exists: $fullname");
                }
            }
            $this->requiredcolumns[ $fullname ] = new $fullname($this);
        }

        return $this->requiredcolumns;
    }


    /**
     * Shows the question bank editing interface.
     *
     * The function also processes a number of actions:
     *
     * Actions affecting the question pool:
     * move           Moves a question to a different category
     * deleteselected Deletes the selected questions from the category
     * Other actions:
     * category      Chooses the category
     * displayoptions Sets display options
     */
    public function display($tabname, $page, $perpage, $cat,
                            $recurse, $showhidden, $showquestiontext, $tagids = array()) {
        global $PAGE, $OUTPUT;

        if ($this->process_actions_needing_ui()) {
            return;
        }
        $editcontexts = $this->contexts->having_one_edit_tab_cap($tabname);
        // Category selection form.
        echo $OUTPUT->heading(get_string('questionbank', 'question'), 2);
        array_unshift($this->searchconditions, new \core_question\bank\search\hidden_condition(!$showhidden));
        array_unshift($this->searchconditions, new \core_question\bank\search\category_condition(
            $cat, $recurse, $editcontexts, $this->baseurl, $this->course));
        array_unshift($this->searchconditions, new groupquiz_disabled_condition());
        $this->display_options_form($showquestiontext, '/mod/groupquiz/edit.php');

        // Continues with list of questions.
        $this->display_question_list($this->contexts->having_one_edit_tab_cap($tabname),
            $this->baseurl, $cat, $this->cm,
            null, $page, $perpage, $showhidden, $showquestiontext,
            $this->contexts->having_cap('moodle/question:add'));
    }


    /**
     * generate an add to realtime quiz url so that when clicked the question will be added to the quiz
     *
     * @param int $questionid
     *
     * @return \moodle_url Moodle url to add the question
     */
    public function add_to_rtq_url($questionid) {

        global $CFG;
        $params = $this->baseurl->params();
        $params['questionid'] = $questionid;
        $params['action'] = 'addquestion';
        $params['sesskey'] = sesskey();

        return new \moodle_url('/mod/groupquiz/edit.php', $params);

    }

    /*
     * This has been taken from the base class to allow us to call our own version of
     * create_new_question_button.
     *
     * @param $category
     * @param $canadd
     * @throws \coding_exception
     */
    protected function create_new_question_form($category, $canadd) {
        global $CFG;
        echo '<div class="createnewquestion">';
        if ($canadd) {
            $this->create_new_question_button($category->id, $this->editquestionurl->params(),
                get_string('createnewquestion', 'question'));
        } else {
            print_string('nopermissionadd', 'question');
        }
        echo '</div>';
    }

    /**
     * Print a button for creating a new question. This will open question/addquestion.php,
     * which in turn goes to question/question.php before getting back to $params['returnurl']
     * (by default the question bank screen).
     *
     * This has been taken from question/editlib.php and adapted to allow us to use the $allowedqtypes
     * param on print_choose_qtype_to_add_form
     *
     * @param int $categoryid The id of the category that the new question should be added to.
     * @param array $params Other paramters to add to the URL. You need either $params['cmid'] or
     *      $params['courseid'], and you should probably set $params['returnurl']
     * @param string $caption the text to display on the button.
     * @param string $tooltip a tooltip to add to the button (optional).
     * @param bool $disabled if true, the button will be disabled.
     */
    private function create_new_question_button($categoryid, $params, $caption, $tooltip = '', $disabled = false) {
        global $CFG, $PAGE, $OUTPUT;
        static $choiceformprinted = false;

        $config = get_config('groupquiz');
        $enabledtypes = explode(',', $config->enabledqtypes);

        $params['category'] = $categoryid;
        $url = new \moodle_url('/question/addquestion.php', $params);
        echo $OUTPUT->single_button($url, $caption, 'get', array('disabled'=>$disabled, 'title'=>$tooltip));

        if (!$choiceformprinted) {
            echo '<div id="qtypechoicecontainer">';
            echo print_choose_qtype_to_add_form(array(), $enabledtypes);
            echo "</div>\n";
            $choiceformprinted = true;
        }
    }
}
