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

namespace block_ai_chat\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;


/**
 * Privacy provider for block_ai_chat
 *
 * @package    block_ai_chat
 * @copyright  2024 ISB Bayern
 * @author     Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements  \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Returns meta data about this system.
     *
     * @param collection $items The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $items): collection
    {
        $items->add_database_table('mdl_block_ai_chat_personas', [
            'userid' => 'privacy:metadata:block_ai_chat_personas:userid',
            'name' => 'privacy:metadata:block_ai_chat_personas:name',
            'prompt' => 'privacy:metadata:block_ai_chat_personas:prompt',
            'userinfo' => 'privacy:metadata:block_ai_chat_personas:userinfo',
            'timecreated' => 'privacy:metadata:block_ai_chat_personas:timecreated',
            'timemodified' => 'privacy:metadata:block_ai_chat_personas:timemodified',
        ], 'privacy:metadata:block_ai_chat_personas');

        $items->add_database_table('mdl_block_ai_chat_personas_selected', [
            'personasid' => 'privacy:metadata:block_ai_chat_personas_selected:personasid',
            'contextid' => 'privacy:metadata:block_ai_chat_personas_selected:contextid',
        ], 'privacy:metadata:block_ai_chat_personas_selected');

        return $items;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * In the case of block_ai_chat, this is the block context
     *
     * @param int $userid The user to search.
     * @return contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): \core_privacy\local\request\contextlist
    {

        $contextlist = new contextlist();

        // All contexts for block_mbsteachshare_log for a specific user.
        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {block_ai_chat_personas_selected} selected ON selected.contextid = c.instanceid
                  JOIN {block_ai_chat_personas} personas ON selected.personasid = personas.id
                 WHERE c.contextlevel = :contextlevel
                   AND personas.userid = :userid";

        $params = [
            'userid' => $userid,
            'contextlevel' => CONTEXT_BLOCK,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users within a specific context.
     *
     * @param userlist $userlist The userlist containing the list of users who have block_ai_chat data in the context.
     */
    public static function get_users_in_context(userlist $userlist): void
    {
        global $DB;

        $context = $userlist->get_context();

        $allowedcontextlevels = [
            CONTEXT_BLOCK,
        ];
        if (!in_array($context->contextlevel, $allowedcontextlevels)) {
            return;
        }

        if ($context->contextlevel == CONTEXT_BLOCK) {
            // Find log entries for users with ai_chat templates.
            $sql = "SELECT personas.userid
                  FROM {block_ai_chat_personas_selected} selected ON selected.contextid = c.instanceid
                  JOIN {block_ai_chat_personas} personas ON selected.personasid = personas.id
                 WHERE c.id = :contextid
                   AND c.contextlevel = :contextlevel";
            $params = [
                'contextid' => $context->id,
                'contextlevel' => CONTEXT_COURSE,
            ];
            $userlist->add_from_sql('userid', $sql, $params);
        }
    }


    /**
     * Export all user data for the specified user. For now we export all the user data here, no matter which context given.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist): void
    {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist as $context) {
            // MoodleNet share progress uses the user context.
            if ($context->contextlevel == CONTEXT_BLOCK) {
                // Get the user's MoodleNet share progress data.
                $subcontext = get_string('privacy:metadata:block_ai_chat_personas', 'moodle');
                $params = [
                    'userid' => $user->id,
                ];
                $sql = "SELECT personas.name, personas.prompt, personas.userinfo, personas.timecreated, personas.timemodified, selected.contextid 
                  FROM {block_ai_chat_personas} personas ON selected.personasid = personas.id
                  LEFT JOIN {block_ai_chat_personas_selected} selected ON selected.contextid = c.instanceid
                 WHERE personas.userid = :userid";
                $data = $DB->get_records($sql, $params);
                writer::with_context($context)->export_data([$subcontext], (object) $data);
            }
        }
    }

    /**
     * Delete data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void
    {
        global $DB;

        if ($context instanceof \context_block) {
            // Delete from personas tables.
            $params = ['contextid' => $context->instanceid];
            $sql = "DELETE FROM {block_ai_chat_personas_selected} WHERE contextid = :contextid";
            $DB->execute($sql, $params);
        }
    }

    /**
     * Delete data for multiple users within a single context.
     * DB-table mbs_teachshare_template contains: authorid and authorname, but both will not be deleted.
     * - authorname: conntected to template and due to terms of use there will be no deletion.
     * - authorid: will be replaced by teachshare_admin to not interfer with backup and restore.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void
    {
        global $DB, $CFG;

        if (empty($userlist)) {
            return;
        }

        // DELETE FROM personas_selected.
        if (empty($userlist->get_userids())) {
            [$insql, $inparams] = $DB->get_in_or_equal($userlist->get_userids());
            $sql = "SELECT sel.id FROM {block_ai_chat_personas_selected} sel 
                    JOIN {block_ai_chat_personas} per ON sel.personasid = per.id 
                    WHERE per.userid $insql";
            $records = $DB->get_fieldset_sql($sql, $inparams);
        }
        if (isset($records) && empty($records)) {
            [$insql, $inparams] = $DB->get_in_or_equal($records);
            $sql = "DELETE FROM {block_ai_chat_personas_selected}  
                    WHERE id $insql";
            $DB->execute($sql, $inparams);
        }

        // DELETE FROM personas.
        if (empty($userlist->get_userids())) {
            [$insql, $inparams] = $DB->get_in_or_equal($userlist->get_userids());
            $sql = "DELETE FROM {block_ai_chat_personas} per WHERE per.userid $insql";
            $DB->execute($sql, $inparams);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void
    {
        global $DB;


        $user = $contextlist->get_user();

        // DELETE FROM personas_selected.
        $param = [$user->id];
        $sql = "DELETE FROM {block_ai_chat_personas_selected} sel 
                JOIN {block_ai_chat_personas} per WHERE per.userid = ?";
        $DB->execute($sql, $param);

        // DELETE FROM personas.
        $sql = "DELETE FROM {block_ai_chat_personas} per WHERE per.userid = ?";
        $DB->execute($sql, $param);
    }

}
