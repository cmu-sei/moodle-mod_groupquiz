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
 * Privacy provider tests for mod_groupquiz.
 *
 * @package    mod_groupquiz
 * @category   test
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
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

namespace mod_groupquiz\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/lib.php');

/**
 * Privacy provider tests for mod_groupquiz.
 *
 * The fixture is one group quiz with a group of three: opener opens the group attempt, closer submits
 * it, and answerer only saves a response in it. A loner outside the group has an ungrouped preview
 * attempt of their own. Everyone has a grade. A second activity holds data that must never be touched.
 *
 * @package    mod_groupquiz
 * @category   test
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass The activity under test. */
    private $groupquiz;

    /** @var \context_module Its context. */
    private $context;

    /** @var \stdClass A second activity whose data must survive. */
    private $other;

    /** @var \stdClass[] Users keyed opener, closer, answerer, loner, nobody. */
    private $users = [];

    /** @var \stdClass The shared group attempt. */
    private $groupattempt;

    /** @var \stdClass The loner's ungrouped attempt. */
    private $soloattempt;

    /** @var \stdClass The attempt in the second activity. */
    private $otherattempt;

    /** @var \stdClass A truefalse question every usage uses. */
    private $question;

    /**
     * Build the fixture described in the class docblock.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        /** @var \mod_groupquiz_generator $quizgenerator */
        $quizgenerator = $generator->get_plugin_generator('mod_groupquiz');
        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $generator->get_plugin_generator('core_question');

        $course = $generator->create_course();
        foreach (['opener', 'closer', 'answerer', 'loner', 'nobody'] as $name) {
            $this->users[$name] = $generator->create_and_enrol($course, 'student');
        }
        $group = $generator->create_group(['courseid' => $course->id]);
        foreach (['opener', 'closer', 'answerer'] as $name) {
            $generator->create_group_member(['groupid' => $group->id, 'userid' => $this->users[$name]->id]);
        }

        $qbank = $generator->create_module('qbank', ['course' => $course->id]);
        $category = $questiongenerator->create_question_category(
            ['contextid' => \context_module::instance($qbank->cmid)->id]
        );
        $this->question = $questiongenerator->create_question('truefalse', null, ['category' => $category->id]);

        $this->groupquiz = $generator->create_module('groupquiz', ['course' => $course->id]);
        $this->context = \context_module::instance($this->groupquiz->cmid);
        $this->other = $generator->create_module('groupquiz', ['course' => $course->id]);
        $othercontext = \context_module::instance($this->other->cmid);

        $opener = $this->users['opener'];
        $this->groupattempt = $quizgenerator->create_attempt($this->groupquiz, $opener, [
            'userstop' => $this->users['closer']->id,
            'forgroupid' => $group->id,
            'state' => \mod_groupquiz\groupquiz_attempt::FINISHED,
            'timefinish' => time(),
            'sumgrades' => 1,
            'uniqueid' => $this->make_usage($this->context, $opener, $this->users['answerer']),
        ]);
        $this->soloattempt = $quizgenerator->create_attempt($this->groupquiz, $this->users['loner'], [
            'preview' => 1,
            'uniqueid' => $this->make_usage($this->context, $this->users['loner'], $this->users['loner']),
        ]);
        $this->otherattempt = $quizgenerator->create_attempt($this->other, $opener, [
            'forgroupid' => $group->id,
            'uniqueid' => $this->make_usage($othercontext, $opener, $opener),
        ]);

        foreach (['opener', 'closer', 'answerer', 'loner'] as $name) {
            $quizgenerator->create_grade($this->groupquiz, $this->users[$name], 50);
        }
        $quizgenerator->create_grade($this->other, $opener, 75);

        $this->setUser(null);
    }

    /**
     * Make a question usage with one truefalse question, started by one user and answered by another.
     *
     * @param \context_module $context The activity context the usage belongs to.
     * @param \stdClass $starter The user who starts the usage.
     * @param \stdClass $responder The user who saves a response.
     * @return int The question usage id.
     */
    private function make_usage(\context_module $context, \stdClass $starter, \stdClass $responder): int {
        $this->setUser($starter);
        $quba = \question_engine::make_questions_usage_by_activity('mod_groupquiz', $context);
        $quba->set_preferred_behaviour('deferredfeedback');
        $slot = $quba->add_question(\question_bank::load_question($this->question->id));
        $quba->start_all_questions();
        \question_engine::save_questions_usage_by_activity($quba);

        $this->setUser($responder);
        $quba->process_action($slot, ['answer' => 1]);
        \question_engine::save_questions_usage_by_activity($quba);

        return $quba->get_id();
    }

    /**
     * Fetch the current attempt row.
     *
     * @param \stdClass $attempt The attempt as created.
     * @return \stdClass|false The row, or false once deleted.
     */
    private function reload(\stdClass $attempt) {
        global $DB;
        return $DB->get_record('groupquiz_attempts', ['id' => $attempt->id]);
    }

    /**
     * Whether a user still has a grade row in an activity.
     *
     * @param \stdClass $groupquiz The activity.
     * @param \stdClass $user The user.
     * @return bool
     */
    private function has_grade(\stdClass $groupquiz, \stdClass $user): bool {
        global $DB;
        return $DB->record_exists('groupquiz_grades', ['groupquizid' => $groupquiz->id, 'userid' => $user->id]);
    }

    /**
     * Whether a question usage still exists.
     *
     * @param int $qubaid The usage id.
     * @return bool
     */
    private function usage_exists(int $qubaid): bool {
        global $DB;
        return $DB->record_exists('question_usages', ['id' => $qubaid]);
    }

    /**
     * The metadata describes both tables and the question engine link.
     */
    public function test_get_metadata(): void {
        $items = provider::get_metadata(new collection('mod_groupquiz'))->get_collection();
        $this->assertCount(3, $items);

        $names = array_map(fn($item) => $item->get_name(), $items);
        $this->assertContains('groupquiz_attempts', $names);
        $this->assertContains('groupquiz_grades', $names);
        $this->assertContains('core_question', $names);
    }

    /**
     * Opener, closer, answerer and loner all have the context; a user with no data does not.
     */
    public function test_get_contexts_for_userid(): void {
        foreach (['opener', 'closer', 'answerer', 'loner'] as $name) {
            $contextids = provider::get_contexts_for_userid($this->users[$name]->id)->get_contextids();
            $this->assertContains((int)$this->context->id, array_map('intval', $contextids), $name);
        }
        $this->assertEmpty(provider::get_contexts_for_userid($this->users['nobody']->id)->get_contextids());
    }

    /**
     * A user whose only trace is a step in the usage is still found, with no grade or row reference.
     */
    public function test_get_contexts_for_userid_from_question_steps_alone(): void {
        global $DB;
        $DB->delete_records('groupquiz_grades', ['userid' => $this->users['answerer']->id]);

        $contextids = provider::get_contexts_for_userid($this->users['answerer']->id)->get_contextids();
        $this->assertEquals([$this->context->id], array_values($contextids));
    }

    /**
     * Every user with data in the context is listed, and nobody else.
     */
    public function test_get_users_in_context(): void {
        $userlist = new userlist($this->context, 'mod_groupquiz');
        provider::get_users_in_context($userlist);

        $expected = array_map(fn($name) => (int)$this->users[$name]->id, ['opener', 'closer', 'answerer', 'loner']);
        sort($expected);
        $actual = array_map('intval', $userlist->get_userids());
        sort($actual);
        $this->assertEquals($expected, $actual);
    }

    /**
     * A timed-out attempt's userstop of -1 is not reported as a user.
     */
    public function test_get_users_in_context_ignores_timed_out_marker(): void {
        global $DB;
        $DB->set_field('groupquiz_attempts', 'userstop', -1, ['id' => $this->groupattempt->id]);

        $userlist = new userlist($this->context, 'mod_groupquiz');
        provider::get_users_in_context($userlist);
        $this->assertNotContains(-1, array_map('intval', $userlist->get_userids()));
    }

    /**
     * The closer gets the group attempt, its responses and their grade; the loner's attempt is not theirs.
     */
    public function test_export_user_data_for_group_member(): void {
        global $DB;
        $closer = $this->users['closer'];
        $this->export_context_data_for_user($closer->id, $this->context, 'mod_groupquiz');

        $writer = writer::with_context($this->context);
        $this->assertTrue($writer->has_any_data());

        $attemptpath = [get_string('privacy:path:attempts', 'mod_groupquiz'), $this->groupattempt->id];
        $attempt = $writer->get_data($attemptpath);
        $this->assertNotEmpty($attempt);
        $this->assertEquals(get_string('yes'), $attempt->submittedbyyou);
        $this->assertEquals(get_string('no'), $attempt->openedbyyou);

        // The whole group usage, since a member can review it in the activity.
        $questionpath = array_merge($attemptpath, [get_string('questions', 'core_question'), 1]);
        $this->assertNotEmpty($writer->get_data($questionpath));

        $solopath = [get_string('privacy:path:attempts', 'mod_groupquiz'), $this->soloattempt->id];
        $this->assertEmpty($writer->get_data($solopath));

        $gradeid = $DB->get_field(
            'groupquiz_grades',
            'id',
            ['groupquizid' => $this->groupquiz->id, 'userid' => $closer->id],
            MUST_EXIST
        );
        $grade = $writer->get_data([get_string('privacy:path:grades', 'mod_groupquiz'), $gradeid]);
        $this->assertEquals(50, $grade->grade);
    }

    /**
     * A member who only saved a response still gets the group attempt.
     */
    public function test_export_user_data_for_answerer(): void {
        $this->export_context_data_for_user($this->users['answerer']->id, $this->context, 'mod_groupquiz');

        $writer = writer::with_context($this->context);
        $attemptpath = [get_string('privacy:path:attempts', 'mod_groupquiz'), $this->groupattempt->id];
        $this->assertNotEmpty($writer->get_data($attemptpath));
    }

    /**
     * The loner gets their own preview attempt, and not the group's.
     */
    public function test_export_user_data_for_loner(): void {
        $this->export_context_data_for_user($this->users['loner']->id, $this->context, 'mod_groupquiz');

        $writer = writer::with_context($this->context);
        $solo = $writer->get_data([get_string('privacy:path:attempts', 'mod_groupquiz'), $this->soloattempt->id]);
        $this->assertEquals(get_string('yes'), $solo->openedbyyou);
        $this->assertEquals(get_string('yes'), $solo->preview);
        $this->assertEmpty($writer->get_data(
            [get_string('privacy:path:attempts', 'mod_groupquiz'), $this->groupattempt->id]
        ));
    }

    /**
     * Wiping a context removes its attempts, usages and grades, and leaves the other activity alone.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        provider::delete_data_for_all_users_in_context($this->context);

        $this->assertEquals(0, $DB->count_records('groupquiz_attempts', ['groupquizid' => $this->groupquiz->id]));
        $this->assertEquals(0, $DB->count_records('groupquiz_grades', ['groupquizid' => $this->groupquiz->id]));
        $this->assertFalse($this->usage_exists($this->groupattempt->uniqueid));
        $this->assertFalse($this->usage_exists($this->soloattempt->uniqueid));

        $this->assertNotFalse($this->reload($this->otherattempt));
        $this->assertTrue($this->usage_exists($this->otherattempt->uniqueid));
        $this->assertTrue($this->has_grade($this->other, $this->users['opener']));
    }

    /**
     * Deleting the opener keeps the group attempt, scrubs them from it, and leaves the rest of the group.
     */
    public function test_delete_data_for_user_keeps_group_attempt(): void {
        $opener = $this->users['opener'];
        $contextlist = new approved_contextlist($opener, 'mod_groupquiz', [$this->context->id]);
        provider::delete_data_for_user($contextlist);

        $attempt = $this->reload($this->groupattempt);
        $this->assertNotFalse($attempt);
        $this->assertEquals(0, $attempt->userid);
        $this->assertEquals(0, $attempt->userstart);
        $this->assertEquals($this->users['closer']->id, $attempt->userstop);
        $this->assertTrue($this->usage_exists($this->groupattempt->uniqueid));

        $this->assertFalse($this->has_grade($this->groupquiz, $opener));
        $this->assertTrue($this->has_grade($this->groupquiz, $this->users['closer']));

        // The approved list named only this context.
        $this->assertEquals($opener->id, $this->reload($this->otherattempt)->userid);
        $this->assertTrue($this->has_grade($this->other, $opener));
    }

    /**
     * Deleting the loner removes their ungrouped attempt and its usage.
     */
    public function test_delete_data_for_user_removes_solo_attempt(): void {
        $loner = $this->users['loner'];
        $contextlist = new approved_contextlist($loner, 'mod_groupquiz', [$this->context->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertFalse($this->reload($this->soloattempt));
        $this->assertFalse($this->usage_exists($this->soloattempt->uniqueid));
        $this->assertFalse($this->has_grade($this->groupquiz, $loner));

        $this->assertNotFalse($this->reload($this->groupattempt));
        $this->assertTrue($this->usage_exists($this->groupattempt->uniqueid));
    }

    /**
     * Deleting closer and loner together leaves the opener's data intact.
     */
    public function test_delete_data_for_users(): void {
        $userlist = new approved_userlist(
            $this->context,
            'mod_groupquiz',
            [$this->users['closer']->id, $this->users['loner']->id]
        );
        provider::delete_data_for_users($userlist);

        $attempt = $this->reload($this->groupattempt);
        $this->assertEquals(0, $attempt->userstop);
        $this->assertEquals($this->users['opener']->id, $attempt->userid);
        $this->assertEquals($this->users['opener']->id, $attempt->userstart);
        $this->assertFalse($this->reload($this->soloattempt));

        $this->assertFalse($this->has_grade($this->groupquiz, $this->users['closer']));
        $this->assertFalse($this->has_grade($this->groupquiz, $this->users['loner']));
        $this->assertTrue($this->has_grade($this->groupquiz, $this->users['opener']));
        $this->assertTrue($this->has_grade($this->groupquiz, $this->users['answerer']));

        $userlist = new userlist($this->context, 'mod_groupquiz');
        provider::get_users_in_context($userlist);
        $this->assertNotContains((int)$this->users['closer']->id, array_map('intval', $userlist->get_userids()));
        $this->assertNotContains((int)$this->users['loner']->id, array_map('intval', $userlist->get_userids()));
    }
}
