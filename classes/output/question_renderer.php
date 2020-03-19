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
 * The question_chooser renderable.
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

namespace mod_groupquiz\output;

defined('MOODLE_INTERNAL') || die();

//require_once($CFG->dirroot . '/question/engine/renderer.php');


class question_renderer extends \core_question_renderer{


    protected function response_history(\question_attempt $qa, \qbehaviour_renderer $behaviouroutput,
            \qtype_renderer $qtoutput, \question_display_options $options) {

        if (!$options->history) {
            return '';
        }

        $table = new \html_table();
        $table->head  = array (
            get_string('step', 'question'),
            get_string('time'),
            get_string('action', 'question'),
            get_string('state', 'question'),
        );
        if ($options->marks >= \question_display_options::MARK_AND_MAX) {
            $table->head[] = get_string('marks', 'question');
        }
        $table->head[] = 'User';

        foreach ($qa->get_full_step_iterator() as $i => $step) {
            $stepno = $i + 1;

            $rowclass = '';
            if ($stepno == $qa->get_num_steps()) {
                $rowclass = 'current';
            } else if (!empty($options->questionreviewlink)) {
                $url = new moodle_url($options->questionreviewlink,
                        array('slot' => $qa->get_slot(), 'step' => $i));
                $stepno = $this->output->action_link($url, $stepno,
                        new popup_action('click', $url, 'reviewquestion',
                                array('width' => 450, 'height' => 650)),
                        array('title' => get_string('reviewresponse', 'question')));
            }

            $restrictedqa = new \question_attempt_with_restricted_history($qa, $i, null);

            $user = new \stdClass();
            $user->id = $step->get_user_id();
            $row = array(
                $stepno,
                userdate($step->get_timecreated()),
                s($qa->summarise_action($step)),
                $restrictedqa->get_state_string($options->correctness),
            );

            if ($options->marks >= \question_display_options::MARK_AND_MAX) {
                $row[] = $qa->format_fraction_as_mark($step->get_fraction(), $options->markdp);
            }
            global $DB;
            $userobj =  $DB->get_record("user", array('id' => $user->id));
            $row[] = fullname($userobj);

            $table->rowclasses[] = $rowclass;
            $table->data[] = $row;
        }

        return \html_writer::tag('h4', get_string('responsehistory', 'question'),
                        array('class' => 'responsehistoryheader')) .
                $options->extrahistorycontent .
                \html_writer::tag('div', \html_writer::table($table, true),
                        array('class' => 'responsehistoryheader'));
    }

}
