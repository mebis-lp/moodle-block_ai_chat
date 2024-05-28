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

namespace block_ai_interface\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;
use objects;

/**
 * Class save_interaction.
 *
 * @package    block_ai_interface
 * @copyright  2024 Tobias Garske, ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_interaction extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'question' => new external_value(PARAM_TEXT, 'Question of user.', VALUE_REQUIRED),
            'reply' => new external_value(PARAM_TEXT, 'Reply of Ai.', VALUE_REQUIRED),
            'conversationid' => new external_value(PARAM_INT, 'Id of conversation.', VALUE_REQUIRED),
            'userid' => new external_value(PARAM_INT, 'Id of user.', VALUE_REQUIRED),
            'contextid' => new external_value(PARAM_INT, 'Course contextid.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Execute the service.
     *
     * @param string $question
     * @param string $reply
     * @param int $conversationid
     * @param int $userid
     * @param int $contextid
     * @return array
     * @throws invalid_parameter_exception
     * @throws dml_exception
     */
    public static function execute(string $question, string $reply, int $conversationid,  int $userid, int $contextid): array {
        global $DB;
        self::validate_parameters(self::execute_parameters(), [
            'question' => $question,
            'reply' => $reply,
            'conversationid' => $conversationid,
            'userid' => $userid,
            'contextid' => $contextid,
        ]);
        // TODO validate context fails for some reason.
        // self::validate_context(\context_course::instance($contextid));

        return [];
    }

    /**
     * Describes the return structure of the service..
     *
     * @return external_
     */
    public static function execute_returns() {
        return new external_single_structure([]);
    }
}

