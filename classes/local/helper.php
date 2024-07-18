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

namespace block_ai_chat\local;

/**
 * Class helper
 *
 * @package    block_ai_chat
 * @copyright  2024 Tobias Garske, ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    public static function check_block_present($courseid) {
        global $DB;

        // Check if tenant is enabled for the school.
        $sql = "SELECT bi.id
                FROM {block_instances} bi
                JOIN {context} ctx ON bi.parentcontextid = ctx.id
                WHERE bi.blockname = :blockname AND ctx.contextlevel = :contextlevel
                AND ctx.instanceid = :courseid";

        $params = [
            'blockname' => 'ai_chat',
            'contextlevel' => CONTEXT_COURSE,
            'courseid' => $courseid,
        ];

        return $DB->get_record_sql($sql, $params);
    }
}
