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
 * Unit tests for the mod_groupquiz callback controllers.
 *
 * @package    mod_groupquiz
 * @category   test
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

namespace mod_groupquiz;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for saving a comment on an attempt through the view attempt controller.
 *
 * @package    mod_groupquiz
 * @category   test
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(controllers\viewquizattempt::class)]
final class viewquizattempt_test extends \advanced_testcase {
    /**
     * Creates a course with a group quiz, and an attempt on it whose one essay question awaits a manual grade.
     *
     * @return array The course, the group quiz, the attempt record and the usage id of the attempt.
     */
    private function create_attempt_awaiting_grade(): array {
        global $CFG;
        require_once($CFG->dirroot . '/question/engine/lib.php');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $groupquiz = $generator->create_module('groupquiz', ['course' => $course->id]);
        $student = $generator->create_and_enrol($course, 'student');
        $cm = get_coursemodule_from_instance('groupquiz', $groupquiz->id, $course->id, false, MUST_EXIST);

        $questiongenerator = $generator->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category(['contextid' => \context_module::instance($cm->id)->id]);
        $question = $questiongenerator->create_question('essay', 'plain', ['category' => $category->id]);

        $quba = \question_engine::make_questions_usage_by_activity('mod_groupquiz', \context_module::instance($cm->id));
        $quba->set_preferred_behaviour('immediatefeedback');
        $slot = $quba->add_question(\question_bank::load_question($question->id), 1);
        $quba->start_all_questions();
        $quba->finish_all_questions();
        \question_engine::save_questions_usage_by_activity($quba);

        $attempt = $generator->get_plugin_generator('mod_groupquiz')->create_attempt($groupquiz, $student, [
            'uniqueid' => $quba->get_id(),
            'layout' => (string)$slot,
            'state' => groupquiz_attempt::FINISHED,
            'timefinish' => time(),
        ]);

        return [$course, $groupquiz, $attempt, $quba->get_id()];
    }

    /**
     * Sets the request the comment form of an attempt would send.
     *
     * @param \stdClass $groupquiz The group quiz.
     * @param int $attemptid The attempt to comment on.
     * @param int $usageid The question usage of the attempt.
     */
    private function post_comment(\stdClass $groupquiz, int $attemptid, int $usageid): void {
        $quba = \question_engine::load_questions_usage_by_activity($usageid);
        $prefix = $quba->get_field_prefix(1);
        $qa = $quba->get_question_attempt(1);

        $_POST = [
            'sesskey' => sesskey(),
            'action' => 'savecomment',
            'attemptid' => $attemptid,
            'groupquizid' => $groupquiz->id,
            'slot' => 1,
            'submit' => 'Save',
            $prefix . ':sequencecheck' => $qa->get_sequence_check_count(),
            $prefix . '-comment' => 'Well reasoned',
            $prefix . '-commentformat' => FORMAT_HTML,
            $prefix . '-mark' => '1',
            $prefix . '-maxmark' => '1',
        ];
        $_REQUEST = $_POST;
    }

    /**
     * Sends the comment request through the controller, as view attempt does.
     */
    private function save_comment(): void {
        $controller = new controllers\viewquizattempt();
        $controller->setup_page('/mod/groupquiz/viewquizattempt.php');
        ob_start();
        try {
            $controller->handle_request();
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Clears the request that a test set.
     */
    public function tearDown(): void {
        $_POST = [];
        $_GET = [];
        $_REQUEST = [];
        parent::tearDown();
    }

    /**
     * A user without the grade capability is refused, and nothing is written.
     */
    public function test_save_comment_requires_grade_capability(): void {
        global $DB;
        $this->resetAfterTest();

        [$course, $groupquiz, $attempt, $usageid] = $this->create_attempt_awaiting_grade();
        $student = $DB->get_record('user', ['id' => $attempt->userid], '*', MUST_EXIST);
        $this->setUser($student);
        $this->post_comment($groupquiz, $attempt->id, $usageid);

        $stepsbefore = $DB->count_records('question_attempt_steps');
        $sink = $this->redirectEvents();
        try {
            $this->save_comment();
            $this->fail('Expected the missing capability to be refused.');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }

        $this->assertSame($stepsbefore, $DB->count_records('question_attempt_steps'));
        $this->assertEmpty($sink->get_events());
        $sink->close();
        $qa = \question_engine::load_questions_usage_by_activity($usageid)->get_question_attempt(1);
        $this->assertNull($qa->get_current_manual_mark());
    }

    /**
     * A user with the grade capability saves the comment and the mark, and the event is logged.
     */
    public function test_save_comment_with_grade_capability(): void {
        $this->resetAfterTest();

        [$course, $groupquiz, $attempt, $usageid] = $this->create_attempt_awaiting_grade();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $this->post_comment($groupquiz, $attempt->id, $usageid);

        $sink = $this->redirectEvents();
        $this->save_comment();
        $events = $sink->get_events();
        $sink->close();

        $qa = \question_engine::load_questions_usage_by_activity($usageid)->get_question_attempt(1);
        $this->assertEquals(1, $qa->get_current_manual_mark());
        $this->assertStringContainsString('Well reasoned', $qa->get_manual_comment()[0]);

        $graded = array_values(array_filter($events, fn($e) => $e instanceof event\question_manually_graded));
        $this->assertCount(1, $graded);
        $this->assertEquals($attempt->id, $graded[0]->other['attemptid']);
        $this->assertEquals(1, $graded[0]->other['slot']);
    }

    /**
     * An attempt id that does not exist gives a not-found error, not a null dereference.
     */
    public function test_save_comment_for_missing_attempt(): void {
        global $DB;
        $this->resetAfterTest();

        [$course, $groupquiz, $attempt, $usageid] = $this->create_attempt_awaiting_grade();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $missing = (int)$DB->get_field_sql('SELECT MAX(id) FROM {groupquiz_attempts}') + 1000;
        $this->post_comment($groupquiz, $missing, $usageid);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('noattempt', 'groupquiz'));
        $this->save_comment();
    }

    /**
     * An attempt that belongs to another quiz is treated as not found, and nothing is written.
     */
    public function test_save_comment_for_attempt_of_another_quiz(): void {
        $this->resetAfterTest();

        [$course, $groupquiz, $attempt, $usageid] = $this->create_attempt_awaiting_grade();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $other = $this->getDataGenerator()->create_module('groupquiz', ['course' => $course->id]);
        $this->setUser($teacher);
        $this->post_comment($other, $attempt->id, $usageid);

        try {
            $this->save_comment();
            $this->fail('Expected the attempt of another quiz to be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('noattempt', $e->errorcode);
        }
        $qa = \question_engine::load_questions_usage_by_activity($usageid)->get_question_attempt(1);
        $this->assertNull($qa->get_current_manual_mark());
    }
}
