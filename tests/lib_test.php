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
 * Unit tests for the mod_groupquiz callbacks in lib.php.
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
require_once($CFG->dirroot . '/calendar/lib.php');
require_once($CFG->dirroot . '/mod/groupquiz/lib.php');
require_once($CFG->dirroot . '/mod/groupquiz/locallib.php');

/**
 * Unit tests for the mod_groupquiz callbacks in lib.php.
 *
 * @package    mod_groupquiz
 * @category   test
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('groupquiz_supports')]
#[\PHPUnit\Framework\Attributes\CoversFunction('groupquiz_get_extra_capabilities')]
#[\PHPUnit\Framework\Attributes\CoversFunction('groupquiz_add_instance')]
#[\PHPUnit\Framework\Attributes\CoversFunction('groupquiz_update_instance')]
#[\PHPUnit\Framework\Attributes\CoversFunction('groupquiz_delete_instance')]
#[\PHPUnit\Framework\Attributes\CoversFunction('groupquiz_review_option_form_to_db')]
#[\PHPUnit\Framework\Attributes\CoversFunction('groupquiz_process_options')]
#[\PHPUnit\Framework\Attributes\CoversFunction('groupquiz_grade_item_update')]
#[\PHPUnit\Framework\Attributes\CoversFunction('mod_groupquiz_core_calendar_provide_event_action')]
#[\PHPUnit\Framework\Attributes\CoversFunction('groupquiz_update_grades')]
#[\PHPUnit\Framework\Attributes\CoversFunction('groupquiz_add_question_reference')]
#[\PHPUnit\Framework\Attributes\CoversFunction('groupquiz_delete_question_references')]
class lib_test extends \advanced_testcase {
    /**
     * Feature support is what the course module chooser and the gradebook read; a flipped answer here
     * silently changes how the activity behaves everywhere.
     *
     * @param string $feature FEATURE_xx constant.
     * @param mixed $expected Expected answer.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('supports_provider')]
    public function test_groupquiz_supports($feature, $expected): void {
        $this->assertSame($expected, groupquiz_supports($feature));
    }

    /**
     * Data provider for test_groupquiz_supports.
     *
     * @return array
     */
    public static function supports_provider(): array {
        return [
            'MOD_ARCHETYPE' => [FEATURE_MOD_ARCHETYPE, MOD_ARCHETYPE_OTHER],
            // Group Quiz does its own grouping handling, so core group mode stays off while groupings are on.
            'GROUPS' => [FEATURE_GROUPS, false],
            'GROUPINGS' => [FEATURE_GROUPINGS, true],
            'MOD_INTRO' => [FEATURE_MOD_INTRO, true],
            'COMPLETION_TRACKS_VIEWS' => [FEATURE_COMPLETION_TRACKS_VIEWS, true],
            'GRADE_HAS_GRADE' => [FEATURE_GRADE_HAS_GRADE, true],
            'GRADE_OUTCOMES' => [FEATURE_GRADE_OUTCOMES, false],
            'BACKUP_MOODLE2' => [FEATURE_BACKUP_MOODLE2, true],
            'SHOW_DESCRIPTION' => [FEATURE_SHOW_DESCRIPTION, true],
            // Core asks this before treating the activity's questions as in use.
            'USES_QUESTIONS' => [FEATURE_USES_QUESTIONS, true],
            'UNKNOWN_FEATURE' => ['unknown_feature', null],
        ];
    }

    /**
     * Grading a group attempt reads members of groups the grader may not belong to.
     */
    public function test_groupquiz_get_extra_capabilities(): void {
        $this->assertSame(['moodle/site:accessallgroups'], groupquiz_get_extra_capabilities());
    }

    /**
     * Adding an instance stores the settings and registers a gradebook item scaled to the max grade.
     */
    public function test_groupquiz_add_instance(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('groupquiz', [
            'course' => $course->id,
            'name' => 'Add instance quiz',
            'grade' => 80,
            'grademethod' => \mod_groupquiz\utils\scaletypes::groupquiz_HIGHESTATTEMPTGRADE,
        ]);

        $record = $DB->get_record('groupquiz', ['id' => $instance->id], '*', MUST_EXIST);
        $this->assertSame('Add instance quiz', $record->name);
        $this->assertEquals(80, $record->grade);
        $this->assertEquals(\mod_groupquiz\utils\scaletypes::groupquiz_HIGHESTATTEMPTGRADE, $record->grademethod);
        $this->assertNotEquals(0, $record->timemodified);
        $this->assertNotEquals(0, $record->timecreated);

        $gradeitem = $DB->get_record('grade_items', [
            'itemtype' => 'mod',
            'itemmodule' => 'groupquiz',
            'iteminstance' => $instance->id,
        ], '*', MUST_EXIST);
        $this->assertEquals(GRADE_TYPE_VALUE, $gradeitem->gradetype);
        $this->assertEquals(80, $gradeitem->grademax);
        $this->assertEquals(0, $gradeitem->grademin);
    }

    /**
     * A max grade of zero means the activity is not graded, so it gets no gradebook item at all.
     */
    public function test_groupquiz_add_instance_without_a_grade(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('groupquiz', [
            'course' => $course->id,
            'grade' => 0,
        ]);

        $this->assertFalse($DB->record_exists('grade_items', [
            'itemtype' => 'mod',
            'itemmodule' => 'groupquiz',
            'iteminstance' => $instance->id,
        ]));
    }

    /**
     * The per-window review checkboxes are folded into one bitmask per review field.
     */
    public function test_groupquiz_review_option_form_to_db(): void {
        $fromform = (object)[
            'marksimmediately' => 1,
            'marksclosed' => 1,
            'correctnessduring' => 1,
        ];

        $marks = groupquiz_review_option_form_to_db($fromform, 'marks');
        $this->assertSame(
            \mod_groupquiz_display_options::IMMEDIATELY_AFTER | \mod_groupquiz_display_options::AFTER_CLOSE,
            $marks
        );

        // A field with no checkboxes set is hidden in every window, not defaulted on.
        $this->assertSame(0, groupquiz_review_option_form_to_db($fromform, 'rightanswer'));

        // Consumed checkboxes are removed so they cannot reach the groupquiz table as stray columns.
        $this->assertObjectNotHasProperty('marksimmediately', $fromform);
        $this->assertObjectHasProperty('correctnessduring', $fromform);
    }

    /**
     * Two review windows are not up to the form: students may always see the attempt itself while it is
     * open, and overall feedback is never shown mid-attempt.
     */
    public function test_groupquiz_process_options_overrides_the_during_window(): void {
        $fromform = (object)[
            'name' => '  Padded name  ',
            'attemptclosed' => 1,
            'overallfeedbackduring' => 1,
            'overallfeedbackclosed' => 1,
        ];

        groupquiz_process_options($fromform);

        $this->assertSame('Padded name', $fromform->name);
        $this->assertNotEquals(0, $fromform->reviewattempt & \mod_groupquiz_display_options::DURING);
        $this->assertSame(0, $fromform->reviewoverallfeedback & \mod_groupquiz_display_options::DURING);
        $this->assertNotEquals(0, $fromform->reviewoverallfeedback & \mod_groupquiz_display_options::AFTER_CLOSE);
        $this->assertNotEquals(0, $fromform->timemodified);
    }

    /**
     * Updating an instance writes the new settings and leaves the max grade alone: the grade lives on the
     * gradebook side of the form, so update_instance deliberately restores the stored value.
     */
    public function test_groupquiz_update_instance(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $grouping = $this->getDataGenerator()->create_grouping(['courseid' => $course->id]);
        $instance = $this->getDataGenerator()->create_module('groupquiz', [
            'course' => $course->id,
            'name' => 'Before update',
            'grade' => 70,
            'grouping' => $grouping->id,
        ]);

        $formdata = clone $instance;
        $formdata->instance = $instance->id;
        $formdata->coursemodule = $instance->cmid;
        $formdata->name = 'After update';
        $formdata->timeclose = time() + DAYSECS;
        $formdata->timelimit = 600;
        // The form posts the gradebook max grade separately; whatever arrives here must not win.
        $formdata->grade = 5;

        $this->assertTrue(groupquiz_update_instance($formdata, null));

        $record = $DB->get_record('groupquiz', ['id' => $instance->id], '*', MUST_EXIST);
        $this->assertSame('After update', $record->name);
        $this->assertEquals(600, $record->timelimit);
        $this->assertEquals($formdata->timeclose, $record->timeclose);
        $this->assertEquals(70, $record->grade);
    }

    /**
     * Deleting an instance takes everything it owns with it: attempts and their question usages, the
     * question list, the grades it kept for itself and its gradebook item.
     */
    public function test_groupquiz_delete_instance(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $question = $this->create_question($course);
        $instance = $generator->create_module('groupquiz', ['course' => $course->id]);
        $other = $generator->create_module('groupquiz', ['course' => $course->id]);

        /** @var \mod_groupquiz_generator $groupquizgenerator */
        $groupquizgenerator = $generator->get_plugin_generator('mod_groupquiz');
        $attempt = $groupquizgenerator->create_attempt($instance, $student);
        $surviving = $groupquizgenerator->create_attempt($other, $student);
        $groupquizgenerator->create_grade($instance, $student, 30);
        $survivinggrade = $groupquizgenerator->create_grade($other, $student, 40);
        $this->add_groupquiz_question($instance, $question);
        $survivingslot = $this->add_groupquiz_question($other, $question);

        $this->assertTrue(groupquiz_delete_instance($instance->id));

        $this->assertFalse($DB->record_exists('groupquiz', ['id' => $instance->id]));
        $this->assertFalse($DB->record_exists('groupquiz_attempts', ['groupquizid' => $instance->id]));
        $this->assertFalse($DB->record_exists('groupquiz_questions', ['groupquizid' => $instance->id]));
        $this->assertFalse($DB->record_exists('groupquiz_grades', ['groupquizid' => $instance->id]));
        $this->assertFalse($DB->record_exists('question_usages', ['id' => $attempt->uniqueid]));
        $this->assertFalse($DB->record_exists('grade_items', [
            'itemtype' => 'mod',
            'itemmodule' => 'groupquiz',
            'iteminstance' => $instance->id,
        ]));

        // The sibling instance is untouched.
        $this->assertTrue($DB->record_exists('groupquiz', ['id' => $other->id]));
        $this->assertTrue($DB->record_exists('groupquiz_attempts', ['id' => $surviving->id]));
        $this->assertTrue($DB->record_exists('groupquiz_grades', ['id' => $survivinggrade->id]));
        $this->assertTrue($DB->record_exists('question_usages', ['id' => $surviving->uniqueid]));
        $this->assertEquals(['mod_groupquiz' => [$survivingslot]], $this->references_for($question));
    }

    /**
     * Adding a question registers it with the question bank, pinned to the version the activity points at,
     * so the bank counts it as used and will not let it be deleted out from under the quiz.
     */
    public function test_groupquiz_add_question_reference(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('groupquiz', ['course' => $course->id]);
        $question = $this->create_question($course);
        // A later version must not move the reference: attempts are built from the pinned one.
        $this->getDataGenerator()->get_plugin_generator('core_question')->update_question($question);

        $this->assertFalse(questions_in_use([$question->id]));

        $slotid = $this->add_groupquiz_question($instance, $question);

        $reference = $DB->get_record('question_references', ['component' => 'mod_groupquiz', 'itemid' => $slotid]);
        $this->assertEquals(\context_module::instance($instance->cmid)->id, $reference->usingcontextid);
        $this->assertEquals('slot', $reference->questionarea);
        $this->assertEquals(get_question_bank_entry($question->id)->id, $reference->questionbankentryid);
        $this->assertEquals(1, $reference->version);
        $this->assertTrue(questions_in_use([$question->id]));
    }

    /**
     * Removing one question from a groupquiz drops only that question's reference.
     */
    public function test_groupquiz_delete_question_references_for_one_question(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('groupquiz', ['course' => $course->id]);
        $removed = $this->create_question($course);
        $kept = $this->create_question($course);
        $removedslot = $this->add_groupquiz_question($instance, $removed);
        $keptslot = $this->add_groupquiz_question($instance, $kept);

        groupquiz_delete_question_references($instance->id, $removedslot);

        $this->assertEquals([], $this->references_for($removed));
        $this->assertEquals(['mod_groupquiz' => [$keptslot]], $this->references_for($kept));
        $this->assertFalse(questions_in_use([$removed->id]));
    }

    /**
     * Create a question in a course-level question bank.
     *
     * @param \stdClass $course
     * @return \stdClass the question
     */
    private function create_question(\stdClass $course): \stdClass {
        /** @var \core_question_generator $questiongenerator */
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $qbank = $this->getDataGenerator()->create_module('qbank', ['course' => $course->id]);
        $category = $questiongenerator->create_question_category(['contextid' => \context_module::instance($qbank->cmid)->id]);
        return $questiongenerator->create_question('truefalse', null, ['category' => $category->id]);
    }

    /**
     * Add a question to a groupquiz the way the edit page does.
     *
     * @param \stdClass $instance the groupquiz
     * @param \stdClass $question
     * @return int the groupquiz_questions id
     */
    private function add_groupquiz_question(\stdClass $instance, \stdClass $question): int {
        global $DB;

        $slotid = $DB->insert_record('groupquiz_questions', (object)[
            'groupquizid' => $instance->id,
            'questionid' => $question->id,
            'points' => 1,
        ]);
        groupquiz_add_question_reference($slotid, $question->id, \context_module::instance($instance->cmid)->id);
        return $slotid;
    }

    /**
     * The groupquiz_questions ids that reference a question, grouped by component.
     *
     * @param \stdClass $question
     * @return array
     */
    private function references_for(\stdClass $question): array {
        global $DB;

        $references = [];
        $entryid = get_question_bank_entry($question->id)->id;
        $records = $DB->get_records('question_references', ['questionbankentryid' => $entryid], 'itemid');
        foreach ($records as $record) {
            $references[$record->component][] = (int)$record->itemid;
        }
        return $references;
    }

    /**
     * The Timeline block and the course overview turn a calendar event into an action through this
     * callback, so it has to hand back a link to the activity.
     */
    public function test_mod_groupquiz_core_calendar_provide_event_action(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $instance = $generator->create_module('groupquiz', [
            'course' => $course->id,
            'timeclose' => time() + DAYSECS,
        ]);
        $event = $this->create_action_event($course->id, $instance->id, 'close');

        $this->setUser($student);
        $action = mod_groupquiz_core_calendar_provide_event_action($event, new \core_calendar\action_factory());

        $this->assertInstanceOf(\core_calendar\local\event\value_objects\action::class, $action);
        $this->assertSame(get_string('view'), $action->get_name());
        $this->assertSame(
            (new \moodle_url('/mod/groupquiz/view.php', ['id' => $instance->cmid]))->out(false),
            $action->get_url()->out(false)
        );
        $this->assertEquals(1, $action->get_item_count());
        $this->assertTrue($action->is_actionable());
    }

    /**
     * An activity the student has already completed drops off the Timeline.
     */
    public function test_mod_groupquiz_core_calendar_provide_event_action_when_already_complete(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $CFG->enablecompletion = 1;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $student = $generator->create_and_enrol($course, 'student');
        $instance = $generator->create_module('groupquiz', [
            'course' => $course->id,
            'timeclose' => time() + DAYSECS,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $event = $this->create_action_event($course->id, $instance->id, 'close');

        $cm = get_coursemodule_from_instance('groupquiz', $instance->id, $course->id, false, MUST_EXIST);
        $completion = new \completion_info($course);
        $completion->update_state($cm, COMPLETION_COMPLETE, $student->id);

        $this->setUser($student);
        $this->assertNull(
            mod_groupquiz_core_calendar_provide_event_action($event, new \core_calendar\action_factory())
        );
    }

    /**
     * The gradebook asks the plugin to push one user's grade, passing a single user id.
     */
    public function test_groupquiz_update_grades_for_one_user(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $other = $generator->create_and_enrol($course, 'student');
        $instance = $generator->create_module('groupquiz', ['course' => $course->id, 'grade' => 100]);

        /** @var \mod_groupquiz_generator $groupquizgenerator */
        $groupquizgenerator = $generator->get_plugin_generator('mod_groupquiz');
        $groupquizgenerator->create_grade($instance, $student, 65);
        $groupquizgenerator->create_grade($instance, $other, 20);

        $this->assertEquals(GRADE_UPDATE_OK, groupquiz_update_grades($instance, $student->id));

        $this->assertEquals(65, $this->gradebook_grade($course, $instance, $student->id));
        // Only the user asked for is pushed.
        $this->assertNull($this->gradebook_grade($course, $instance, $other->id));
    }

    /**
     * With no user given, everyone who has a grade is pushed - which is what a course regrade relies on.
     */
    public function test_groupquiz_update_grades_for_all_users(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $first = $generator->create_and_enrol($course, 'student');
        $second = $generator->create_and_enrol($course, 'student');
        $instance = $generator->create_module('groupquiz', ['course' => $course->id, 'grade' => 100]);

        /** @var \mod_groupquiz_generator $groupquizgenerator */
        $groupquizgenerator = $generator->get_plugin_generator('mod_groupquiz');
        $groupquizgenerator->create_grade($instance, $first, 65);
        $groupquizgenerator->create_grade($instance, $second, 20);

        $this->assertEquals(GRADE_UPDATE_OK, groupquiz_update_grades($instance));

        $this->assertEquals(65, $this->gradebook_grade($course, $instance, $first->id));
        $this->assertEquals(20, $this->gradebook_grade($course, $instance, $second->id));
    }

    /**
     * A user who has not been graded yet gets a null grade rather than an error.
     */
    public function test_groupquiz_update_grades_for_an_ungraded_user(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $instance = $generator->create_module('groupquiz', ['course' => $course->id, 'grade' => 100]);

        $this->assertEquals(GRADE_UPDATE_OK, groupquiz_update_grades($instance, $student->id));
        $this->assertNull($this->gradebook_grade($course, $instance, $student->id));

        // With $nullifnone off there is nothing to send, and the grade item is still kept in step.
        $this->assertEquals(GRADE_UPDATE_OK, groupquiz_update_grades($instance, $student->id, false));
        $this->assertNull($this->gradebook_grade($course, $instance, $student->id));
    }

    /**
     * Changing the grading method regrades the closed attempts; saving the form without changing it must
     * not, or every save silently rewrites the grades of everyone who has attempted the activity.
     *
     * The instance deliberately has no grouping, so a regrade is observable: save_all_grades() has no
     * groups to walk and says so through debugging().
     */
    public function test_groupquiz_update_instance_regrades_only_when_the_grade_method_changes(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('groupquiz', [
            'course' => $course->id,
            'grademethod' => \mod_groupquiz\utils\scaletypes::groupquiz_FIRSTATTEMPT,
        ]);

        $formdata = clone $instance;
        $formdata->instance = $instance->id;
        $formdata->coursemodule = $instance->cmid;
        $formdata->name = 'Renamed, same grading method';
        // The form posts the grading method as an int, while the stored value comes back as a string.
        $formdata->grademethod = (int)$instance->grademethod;

        $this->assertTrue(groupquiz_update_instance($formdata, null));
        $this->assertDebuggingNotCalled();

        $formdata->grademethod = \mod_groupquiz\utils\scaletypes::groupquiz_HIGHESTATTEMPTGRADE;
        $this->assertTrue(groupquiz_update_instance($formdata, null));
        $this->assertDebuggingCalled('cannot find group');
    }

    /**
     * Read a user's grade for an instance back out of the gradebook.
     *
     * @param \stdClass $course The course the instance is in.
     * @param \stdClass $instance The groupquiz instance.
     * @param int $userid The user to look up.
     * @return float|null The gradebook grade, or null if the user has none.
     */
    protected function gradebook_grade($course, $instance, int $userid) {
        $grades = grade_get_grades($course->id, 'mod', 'groupquiz', $instance->id, $userid);

        return $grades->items[0]->grades[$userid]->grade;
    }

    /**
     * Create the kind of calendar event the callback is handed.
     *
     * @param int $courseid Course the event belongs to.
     * @param int $instanceid Group Quiz instance the event belongs to.
     * @param string $eventtype Event type, as stored on the event.
     * @return \calendar_event
     */
    protected function create_action_event(int $courseid, int $instanceid, string $eventtype): \calendar_event {
        return \calendar_event::create((object)[
            'name' => 'Calendar event',
            'modulename' => 'groupquiz',
            'courseid' => $courseid,
            'instance' => $instanceid,
            'type' => CALENDAR_EVENT_TYPE_ACTION,
            'eventtype' => $eventtype,
            'timestart' => time(),
        ]);
    }
}
