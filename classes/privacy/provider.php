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
 * Privacy Subsystem implementation for mod_groupquiz.
 *
 * @package    mod_groupquiz
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
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem for mod_groupquiz.
 *
 * A group quiz attempt is shared by every member of the group named in forgroupid. The attempt row
 * records the member who opened it (userid and userstart, always the same person) and the member who
 * submitted it (userstop, or -1 when the time limit closed it). Each attempt owns a question usage, and
 * every member who saves a response adds steps to it. groupquiz_grades holds one row per member.
 *
 * Deleting one member must not destroy the group's attempt for everyone else, so a group attempt is
 * kept and the departing member's id is scrubbed from it. Only attempts that belong to nobody else, the
 * ones with no group, are deleted outright along with their question usage.
 *
 * A departing member's steps inside a kept usage still carry their userid: core has no API to anonymise
 * question_attempt_steps.userid short of deleting the usage. There is precedent on both sides: mod_assign
 * leaves group submissions (userid 0, groupid set) untouched on deletion and never exports them to
 * members, and mod_quiz leaves a grader's step userids in the students' attempts.
 *
 * @copyright  2020 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data this plugin stores.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('groupquiz_attempts', [
            'userid' => 'privacy:metadata:groupquiz_attempts:userid',
            'userstart' => 'privacy:metadata:groupquiz_attempts:userstart',
            'userstop' => 'privacy:metadata:groupquiz_attempts:userstop',
            'forgroupid' => 'privacy:metadata:groupquiz_attempts:forgroupid',
            'attemptnum' => 'privacy:metadata:groupquiz_attempts:attemptnum',
            'sumgrades' => 'privacy:metadata:groupquiz_attempts:sumgrades',
            'state' => 'privacy:metadata:groupquiz_attempts:state',
            'preview' => 'privacy:metadata:groupquiz_attempts:preview',
            'timestart' => 'privacy:metadata:groupquiz_attempts:timestart',
            'timefinish' => 'privacy:metadata:groupquiz_attempts:timefinish',
            'timemodified' => 'privacy:metadata:groupquiz_attempts:timemodified',
        ], 'privacy:metadata:groupquiz_attempts');

        $collection->add_database_table('groupquiz_grades', [
            'userid' => 'privacy:metadata:groupquiz_grades:userid',
            'grade' => 'privacy:metadata:groupquiz_grades:grade',
            'timemodified' => 'privacy:metadata:groupquiz_grades:timemodified',
        ], 'privacy:metadata:groupquiz_grades');

        // Each attempt owns a question usage, so the question engine holds the responses.
        $collection->add_subsystem_link('core_question', [], 'privacy:metadata:core_question');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * A user has data in a group quiz if they opened or submitted an attempt, have a grade, or took a
     * step in a question usage owned by one of its attempts. The last case covers group members who
     * saved responses in an attempt someone else opened, and teachers who manually graded.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $frommodule = "FROM {context} c
                       JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                       JOIN {modules} m ON m.id = cm.module AND m.name = 'groupquiz'
                       JOIN {groupquiz} gq ON gq.id = cm.instance";

        $sql = "SELECT c.id
                  $frommodule
                  JOIN {groupquiz_attempts} gqa ON gqa.groupquizid = gq.id
                 WHERE gqa.userid = :userid OR gqa.userstart = :userstart OR gqa.userstop = :userstop";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
            'userstart' => $userid,
            'userstop' => $userid,
        ]);

        $sql = "SELECT c.id
                  $frommodule
                  JOIN {groupquiz_grades} gqg ON gqg.groupquizid = gq.id
                 WHERE gqg.userid = :userid";
        $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_MODULE, 'userid' => $userid]);

        // Question usages the user took a step in, whoever opened the attempt.
        $qubaid = \core_question\privacy\provider::get_related_question_usages_for_user(
            'rel',
            'mod_groupquiz',
            'gqa.uniqueid',
            $userid
        );
        $sql = "SELECT c.id
                  $frommodule
                  JOIN {groupquiz_attempts} gqa ON gqa.groupquizid = gq.id
                  {$qubaid->from}
                 WHERE {$qubaid->where()}";
        $contextlist->add_from_sql($sql, array_merge(
            ['contextlevel' => CONTEXT_MODULE],
            $qubaid->from_where_params()
        ));

        return $contextlist;
    }

    /**
     * Get the list of users within a specific context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this
     *                           context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        $params = ['cmid' => $context->instanceid, 'modname' => 'groupquiz'];

        $frommodule = "FROM {course_modules} cm
                       JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                       JOIN {groupquiz} gq ON gq.id = cm.instance";

        // A scrubbed attempt carries 0, and a timed-out one carries userstop -1; neither is a user.
        foreach (['userid', 'userstart', 'userstop'] as $field) {
            $sql = "SELECT gqa.$field
                      $frommodule
                      JOIN {groupquiz_attempts} gqa ON gqa.groupquizid = gq.id
                     WHERE cm.id = :cmid AND gqa.$field > 0";
            $userlist->add_from_sql($field, $sql, $params);
        }

        $sql = "SELECT gqg.userid
                  $frommodule
                  JOIN {groupquiz_grades} gqg ON gqg.groupquizid = gq.id
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, $params);

        // Anyone who took a step in one of this activity's question usages: group members who saved a
        // response, and teachers who manually graded.
        $sql = "SELECT gqa.uniqueid
                  $frommodule
                  JOIN {groupquiz_attempts} gqa ON gqa.groupquizid = gq.id
                 WHERE cm.id = :cmid AND gqa.uniqueid <> 0";
        \core_question\privacy\provider::get_users_in_context_from_sql($userlist, 'qn', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }

        $user = $contextlist->get_user();
        $userid = (int)$user->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id('groupquiz', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $groupquiz = $DB->get_record('groupquiz', ['id' => $cm->instance]);
            if (!$groupquiz) {
                continue;
            }

            // The module's own settings, name and intro, which core expects every activity to write.
            $contextdata = helper::get_context_data($context, $user);
            helper::export_context_files($context, $user);
            writer::with_context($context)->export_data([], $contextdata);

            self::export_attempts($userid, $context, $groupquiz);
            self::export_grades($userid, $context, $groupquiz);
        }
    }

    /**
     * Export the attempts in one activity that the user opened, submitted, or saved a response in.
     *
     * A group attempt is the joint work of the group, and every member can review all of it in the
     * activity, so a member gets the whole question usage, not just their own steps. A teacher who only
     * graded is not a member and gets nothing from the question engine here, which is how mod_quiz
     * treats its graders too.
     *
     * Preview attempts are included: an instructor's preview is still that instructor's own data.
     *
     * @param int $userid The user being exported.
     * @param \context_module $context The activity context.
     * @param \stdClass $groupquiz The groupquiz activity record.
     */
    protected static function export_attempts(int $userid, \context_module $context, \stdClass $groupquiz) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/groupquiz/locallib.php');
        require_once($CFG->dirroot . '/lib/grouplib.php');

        // Usages in this activity that the user took a step in.
        $qubaid = \core_question\privacy\provider::get_related_question_usages_for_user(
            'rel',
            'mod_groupquiz',
            'gqa.uniqueid',
            $userid
        );
        $sql = "SELECT DISTINCT gqa.id
                  FROM {groupquiz_attempts} gqa
                  {$qubaid->from}
                 WHERE gqa.groupquizid = :groupquizid AND {$qubaid->where()}";
        $stepattemptids = $DB->get_fieldset_sql($sql, array_merge(
            ['groupquizid' => $groupquiz->id],
            $qubaid->from_where_params()
        ));
        $stepattemptids = array_map('intval', $stepattemptids);

        $options = self::get_export_display_options($groupquiz);

        $attempts = $DB->get_recordset('groupquiz_attempts', ['groupquizid' => $groupquiz->id], 'id ASC');
        foreach ($attempts as $attempt) {
            $opened = ((int)$attempt->userid === $userid || (int)$attempt->userstart === $userid);
            $submitted = ((int)$attempt->userstop === $userid);
            $tookstep = in_array((int)$attempt->id, $stepattemptids, true);
            $ismember = !empty($attempt->forgroupid) && groups_is_member($attempt->forgroupid, $userid);

            if (!$opened && !$submitted && !$tookstep) {
                continue;
            }

            $subcontext = [
                get_string('privacy:path:attempts', 'mod_groupquiz'),
                $attempt->id,
            ];

            if (!empty($attempt->uniqueid)) {
                \core_question\privacy\provider::export_question_usage(
                    $userid,
                    $context,
                    $subcontext,
                    $attempt->uniqueid,
                    $options,
                    $opened || $submitted || $ismember
                );
            }

            if (!$opened && !$submitted && !$ismember) {
                // A grader's steps, at most. The attempt row is the group's data, not theirs.
                continue;
            }

            $data = (object)[
                'openedbyyou' => transform::yesno($opened),
                'submittedbyyou' => transform::yesno($submitted),
                'state' => $attempt->state,
                'preview' => transform::yesno($attempt->preview),
                'timestart' => transform::datetime($attempt->timestart),
                'timemodified' => transform::datetime($attempt->timemodified),
            ];
            if (!empty($attempt->forgroupid)) {
                $data->group = groups_get_group_name($attempt->forgroupid);
            }
            if ($attempt->attemptnum !== null) {
                $data->attemptnum = $attempt->attemptnum;
            }
            if (!empty($attempt->timefinish)) {
                $data->timefinish = transform::datetime($attempt->timefinish);
            }
            if ($options->marks >= \question_display_options::MARK_AND_MAX && $attempt->sumgrades !== null) {
                $data->sumgrades = $attempt->sumgrades;
            }

            writer::with_context($context)->export_data($subcontext, $data);
        }
        $attempts->close();
    }

    /**
     * Build the display options the question engine applies to an export.
     *
     * AFTER_CLOSE, because a subject access request is by definition after the fact: the activity's
     * review settings still decide what is shown, but nothing is withheld merely because the quiz is
     * still open.
     *
     * @param \stdClass $groupquiz The groupquiz activity record.
     * @return \mod_groupquiz_display_options
     */
    protected static function get_export_display_options(\stdClass $groupquiz): \mod_groupquiz_display_options {
        // The make_from_groupquiz() helper was carried over from mod_quiz and still reads the quiz's decimal point
        // settings, which this activity has no columns for. Supply the question engine's default.
        $settings = clone $groupquiz;
        if (!isset($settings->questiondecimalpoints)) {
            $settings->questiondecimalpoints = -1;
        }
        if (!isset($settings->decimalpoints)) {
            $settings->decimalpoints = 2;
        }

        return \mod_groupquiz_display_options::make_from_groupquiz(
            $settings,
            \mod_groupquiz_display_options::AFTER_CLOSE
        );
    }

    /**
     * Export the user's stored grades for one activity.
     *
     * @param int $userid The user being exported.
     * @param \context_module $context The activity context.
     * @param \stdClass $groupquiz The groupquiz activity record.
     */
    protected static function export_grades(int $userid, \context_module $context, \stdClass $groupquiz) {
        global $DB;

        $grades = $DB->get_records('groupquiz_grades', ['groupquizid' => $groupquiz->id, 'userid' => $userid]);
        foreach ($grades as $grade) {
            writer::with_context($context)->export_data(
                [get_string('privacy:path:grades', 'mod_groupquiz'), $grade->id],
                (object)[
                    'grade' => $grade->grade,
                    'timemodified' => transform::datetime($grade->timemodified),
                ]
            );
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/question/engine/lib.php');

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('groupquiz', $context->instanceid);
        if (!$cm) {
            return;
        }

        // Question usages first: once the attempts are gone there is nothing left to find them by.
        \question_engine::delete_questions_usage_by_activities(new \qubaid_join(
            '{groupquiz_attempts} gqa',
            'gqa.uniqueid',
            'gqa.groupquizid = :groupquizid',
            ['groupquizid' => $cm->instance]
        ));

        $DB->delete_records('groupquiz_attempts', ['groupquizid' => $cm->instance]);
        $DB->delete_records('groupquiz_grades', ['groupquizid' => $cm->instance]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userid = (int)$contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('groupquiz', $context->instanceid);
            if (!$cm) {
                continue;
            }
            self::delete_users_in_activity((int)$cm->instance, [$userid]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('groupquiz', $context->instanceid);
        if (!$cm) {
            return;
        }
        self::delete_users_in_activity((int)$cm->instance, $userlist->get_userids());
    }

    /**
     * Remove the given users' data from one activity.
     *
     * Grades are per user and go. An attempt with no group belongs to its opener alone and goes with
     * its question usage. A group attempt is the whole group's, so it stays, and the users are scrubbed
     * from userid, userstart and userstop by setting them to 0.
     *
     * The users' steps inside a kept group attempt's question usage are left in place: the question
     * engine has no way to anonymise a step without deleting the usage.
     *
     * @param int $groupquizid The groupquiz activity id.
     * @param array $userids The users to remove.
     */
    protected static function delete_users_in_activity(int $groupquizid, array $userids) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/question/engine/lib.php');

        if (empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'usr');
        $params = array_merge(['groupquizid' => $groupquizid], $inparams);

        $DB->delete_records_select('groupquiz_grades', "groupquizid = :groupquizid AND userid $insql", $params);

        // Attempts without a group: question usages first, since they are found through the attempts.
        $soleowner = "groupquizid = :groupquizid AND (forgroupid IS NULL OR forgroupid = 0) AND userid $insql";
        [$qbinsql, $qbinparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'qbusr');
        \question_engine::delete_questions_usage_by_activities(new \qubaid_join(
            '{groupquiz_attempts} gqa',
            'gqa.uniqueid',
            "gqa.groupquizid = :qbgroupquizid AND (gqa.forgroupid IS NULL OR gqa.forgroupid = 0)
                AND gqa.userid $qbinsql",
            array_merge(['qbgroupquizid' => $groupquizid], $qbinparams)
        ));
        $DB->delete_records_select('groupquiz_attempts', $soleowner, $params);

        // Group attempts, and any ungrouped attempt someone else opened: scrub the references.
        foreach (['userid', 'userstart', 'userstop'] as $field) {
            $DB->set_field_select(
                'groupquiz_attempts',
                $field,
                0,
                "groupquizid = :groupquizid AND $field $insql",
                $params
            );
        }
    }
}
