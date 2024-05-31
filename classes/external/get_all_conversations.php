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

namespace block_ai_chat\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

/**
 * Class get_all_conversations, to retrieve all visible conversations.
 *
 * @package    block_ai_chat
 * @copyright  2024 Tobias Garske, ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_all_conversations extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'Id of user.', VALUE_REQUIRED),
            'contextid' => new external_value(PARAM_INT, 'Course contextid.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Execute the service.
     *
     * @param int $userid
     * @param int $contextid
     * @return array
     * @throws invalid_parameter_exception
     * @throws dml_exception
     */
    public static function execute(int $userid, int $contextid): array {
        global $DB, $USER;
        self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'contextid' => $contextid,
        ]);
        self::validate_context(\core\context_helper::instance_by_id($contextid));
        // Make sure the user has the proper capability.        
        // require_capability('tool/dataprivacy:managedatarequests', $context);

        if ($USER->id !== $userid ) {
            // Evtl. noch admincheck.
            // return;
        }

        // TODO read from local_ai_manager and get all own conversations.$re
        // As well as from pupils having a teacher role.

        $result = [];
        $response = \local_ai_manager\ai_manager_utils::get_log_entries('block_ai_chat', $contextid, $USER->id);
        // Get the latest log entries unique by itemid.
        foreach ($response as $value) {
            // Ignore values without itemid.
            if (empty($value->itemid)) {
                continue;
            }
            if (empty($result[$value->itemid])) {
                $result[$value->itemid] = $value;
            } else {
                if ($result[$value->itemid]->timecreated < $value->timecreated) {
                    $result[$value->itemid] = $value;
                }
            }
        }
        // Convert to expected format.
        foreach ($result as $value) {
            $messages = [
                [
                    'message' => $value->prompttext,
                    'sender' => 'user',
                ],
                [
                    'message' => $value->promptcompletion,
                    'sender' => 'ai',
                ],
            ];
            $messages = array_merge(json_decode($value->requestoptions, true)['conversationcontext'], $messages);
            $result[$value->itemid] = [
                'id' => $value->itemid,
                'messages' => $messages,
            ];
        }
        return $result;
    }

    /**
     * Describes the return structure of the service..
     *
     * @return external_
     */
    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'ID of conversation'),
                'messages' => new external_multiple_structure(
                    new external_single_structure([
                        'message' => new external_value(PARAM_TEXT, 'Text of conversation'),
                        'sender' => new external_value(PARAM_TEXT, 'Sent by user or ai'),
                    ])
                ),
            ]), 'Messages with conversationid.'
        );
    }
}

