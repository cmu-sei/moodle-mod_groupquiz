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
 * Backup and restore of mod_groupquiz.
 *
 * @package    mod_groupquiz
 * @category   test
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

namespace mod_groupquiz;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/groupquiz/lib.php');

/**
 * Backup and restore of mod_groupquiz.
 *
 * @package    mod_groupquiz
 * @category   test
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\backup_groupquiz_activity_structure_step::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_groupquiz_activity_structure_step::class)]
final class backup_restore_test extends \advanced_testcase {

    /**
     * A restored quiz keeps its questions, in their order, registered with the question bank,
     * and points at the restored course's copy of its grouping.
     */
    public function test_restore_keeps_questions_order_and_grouping(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $grouping, $instance, $questions] = $this->create_quiz_with_questions();
        // Reverse the order so restoring in insertion order would show up.
        $slots = $DB->get_records_menu('groupquiz_questions', ['groupquizid' => $instance->id], 'id', 'questionid, id');
        $DB->set_field('groupquiz', 'questionorder',
            $slots[$questions[1]->id] . ',' . $slots[$questions[0]->id], ['id' => $instance->id]);

        $newcourseid = $this->backup_and_restore($course, false);

        $restored = $DB->get_record('groupquiz', ['course' => $newcourseid], '*', MUST_EXIST);
        $restoredcm = get_coursemodule_from_instance('groupquiz', $restored->id, $newcourseid, false, MUST_EXIST);

        $newgrouping = $DB->get_record('groupings', ['courseid' => $newcourseid, 'name' => $grouping->name], '*', MUST_EXIST);
        $this->assertEquals($newgrouping->id, $restored->grouping);

        $order = explode(',', $restored->questionorder);
        $this->assertCount(2, $order);
        $names = [];
        foreach ($order as $slotid) {
            $slot = $DB->get_record('groupquiz_questions', ['id' => $slotid, 'groupquizid' => $restored->id], '*', MUST_EXIST);
            $names[] = $DB->get_field('question', 'name', ['id' => $slot->questionid]);
            $this->assertEquals(1, $slot->points);
        }
        $this->assertSame([$questions[1]->name, $questions[0]->name], $names);

        $references = $DB->get_records('question_references',
            ['component' => 'mod_groupquiz', 'questionarea' => 'slot', 'itemid' => $order[0]]);
        $this->assertCount(1, $references);
        $reference = reset($references);
        $this->assertEquals(\context_module::instance($restoredcm->id)->id, $reference->usingcontextid);
        $this->assertEquals(1, $reference->version);
        $restoredquestionid = $DB->get_field('groupquiz_questions', 'questionid', ['id' => $order[0]]);
        $this->assertEquals(get_question_bank_entry($restoredquestionid)->id, $reference->questionbankentryid);
        $this->assertTrue(questions_in_use([$restoredquestionid]));

        // The source attempt and grade stay behind when the backup leaves out users.
        $this->assertFalse($DB->record_exists('groupquiz_attempts', ['groupquizid' => $restored->id]));
        $this->assertFalse($DB->record_exists('groupquiz_grades', ['groupquizid' => $restored->id]));
    }

    /**
     * Duplicate on the course page runs a backup and restore inside the same course, so the copy
     * keeps the original's grouping and gets questions of its own.
     */
    public function test_duplicate_keeps_grouping_and_questions(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $grouping, $instance, $questions] = $this->create_quiz_with_questions();

        // Moodle 5.2 moved duplication to cmactions and deprecated duplicate_module(); 5.0 only has the latter.
        if (method_exists(\core_courseformat\local\cmactions::class, 'duplicate')) {
            $newcm = (new \core_courseformat\local\cmactions($course))->duplicate($instance->cmid);
        } else {
            $newcm = duplicate_module($course, get_fast_modinfo($course)->get_cm($instance->cmid));
        }

        $copy = $DB->get_record('groupquiz', ['id' => $newcm->instance], '*', MUST_EXIST);
        $this->assertEquals($grouping->id, $copy->grouping);
        $order = explode(',', $copy->questionorder);
        $this->assertCount(2, $order);
        $copied = $DB->get_records_list('groupquiz_questions', 'id', $order);
        $this->assertCount(2, $copied);
        foreach ($copied as $slot) {
            $this->assertEquals($copy->id, $slot->groupquizid);
        }
        $this->assertEquals(2, $DB->count_records_select('question_references',
            "component = 'mod_groupquiz' AND usingcontextid = ?", [\context_module::instance($newcm->id)->id]));
        // The original keeps its own.
        $this->assertEquals(2, $DB->count_records('groupquiz_questions', ['groupquizid' => $instance->id]));
    }

    /**
     * With users included, attempts come across with their responses, group and grade.
     */
    public function test_restore_with_user_data(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $grouping, $instance, $questions, $student, $group] = $this->create_quiz_with_questions();
        $oldattempt = $DB->get_record('groupquiz_attempts', ['groupquizid' => $instance->id], '*', MUST_EXIST);

        $newcourseid = $this->backup_and_restore($course, true);

        $restored = $DB->get_record('groupquiz', ['course' => $newcourseid], '*', MUST_EXIST);
        $attempt = $DB->get_record('groupquiz_attempts', ['groupquizid' => $restored->id], '*', MUST_EXIST);
        $newgroup = $DB->get_record('groups', ['courseid' => $newcourseid, 'name' => $group->name], '*', MUST_EXIST);
        $this->assertEquals($student->id, $attempt->userid);
        $this->assertEquals($student->id, $attempt->userstart);
        $this->assertEquals($newgroup->id, $attempt->forgroupid);
        $this->assertEquals(\mod_groupquiz\groupquiz_attempt::FINISHED, $attempt->state);
        $this->assertEquals($oldattempt->layout, $attempt->layout);
        $this->assertNotEquals($oldattempt->uniqueid, $attempt->uniqueid);

        $quba = \question_engine::load_questions_usage_by_activity($attempt->uniqueid);
        $this->assertEquals('mod_groupquiz', $quba->get_owning_component());
        $this->assertSame([1], $quba->get_slots());
        $this->assertSame($questions[0]->name, $quba->get_question(1)->name);
        $this->assertEquals('True', $quba->get_response_summary(1));

        $grade = $DB->get_record('groupquiz_grades', ['groupquizid' => $restored->id], '*', MUST_EXIST);
        $this->assertEquals($student->id, $grade->userid);
        $this->assertEquals(75, $grade->grade);
    }

    /**
     * A course with a grouping, a grouped student and a groupquiz holding two bank questions, with
     * one finished attempt answering the first and a grade for the student.
     *
     * @return array [course, grouping, groupquiz, questions, student, group]
     */
    private function create_quiz_with_questions(): array {
        global $DB;

        $generator = $this->getDataGenerator();
        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $generator->get_plugin_generator('core_question');
        /** @var \mod_groupquiz_generator $quizgenerator */
        $quizgenerator = $generator->get_plugin_generator('mod_groupquiz');

        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $group = $generator->create_group(['courseid' => $course->id, 'name' => 'Blue team']);
        $grouping = $generator->create_grouping(['courseid' => $course->id, 'name' => 'Teams']);
        $generator->create_grouping_group(['groupingid' => $grouping->id, 'groupid' => $group->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $student->id]);

        $qbank = $generator->create_module('qbank', ['course' => $course->id]);
        $category = $questiongenerator->create_question_category(
            ['contextid' => \context_module::instance($qbank->cmid)->id]);
        $questions = [
            $questiongenerator->create_question('truefalse', null, ['category' => $category->id, 'name' => 'First']),
            $questiongenerator->create_question('truefalse', null, ['category' => $category->id, 'name' => 'Second']),
        ];

        $instance = $generator->create_module('groupquiz', ['course' => $course->id, 'grouping' => $grouping->id]);
        $context = \context_module::instance($instance->cmid);
        $slotids = [];
        foreach ($questions as $question) {
            $slotid = $DB->insert_record('groupquiz_questions', (object)[
                'groupquizid' => $instance->id,
                'questionid' => $question->id,
                'points' => 1,
            ]);
            groupquiz_add_question_reference($slotid, $question->id, $context->id);
            $slotids[] = $slotid;
        }
        $DB->set_field('groupquiz', 'questionorder', implode(',', $slotids), ['id' => $instance->id]);

        $quba = \question_engine::make_questions_usage_by_activity('mod_groupquiz', $context);
        $quba->set_preferred_behaviour('immediatefeedback');
        $slot = $quba->add_question(\question_bank::load_question($questions[0]->id), 1);
        $quba->start_all_questions();
        $quba->process_action($slot, ['answer' => 1, '-submit' => 1]);
        \question_engine::save_questions_usage_by_activity($quba);
        $quizgenerator->create_attempt($instance, $student, [
            'uniqueid' => $quba->get_id(),
            'forgroupid' => $group->id,
            'layout' => (string)$slot,
            'sumgrades' => 1,
            'state' => \mod_groupquiz\groupquiz_attempt::FINISHED,
            'timefinish' => time(),
        ]);
        $DB->insert_record('groupquiz_grades', (object)[
            'groupquizid' => $instance->id,
            'userid' => $student->id,
            'grade' => 75,
            'timemodified' => time(),
        ]);

        return [$course, $grouping, $instance, $questions, $student, $group];
    }

    /**
     * Back up a course and restore it as a new course.
     *
     * @param \stdClass $course
     * @param bool $users whether the backup includes user data
     * @return int the new course id
     */
    private function backup_and_restore(\stdClass $course, bool $users): int {
        global $CFG, $USER;

        $CFG->keeptempdirectoriesonbackup = true;
        $bc = new \backup_controller(\backup::TYPE_1COURSE, $course->id, \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $USER->id);
        $bc->get_plan()->get_setting('users')->set_value($users);
        $bc->execute_plan();
        $backupid = $bc->get_backupid();
        $bc->destroy();

        $newcourseid = \restore_dbops::create_new_course('Restored', 'RESTORED', $course->category);
        $rc = new \restore_controller($backupid, $newcourseid, \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL, $USER->id, \backup::TARGET_NEW_COURSE);
        $rc->get_plan()->get_setting('users')->set_value($users);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }
}
