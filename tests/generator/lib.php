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
 * Plugin generator block_ai_chat
 *
 * @package   block_ai_chat
 * @author    Tobias Garske
 * @copyright 2025, ISB Bayern
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_ai_chat_generator extends \component_generator_base {



    /**
     * Create a some data.
     *
     */
    public function create_personas($userid) {
        global $DB;

        $records = [];
        $records[] = (object) [
            'userid' => $userid,
            'name' => 'Persona 1',
            'prompt' => 'You are a helpful assistant.',
            'userinfo' => 'You are speaking to a helpful assistant. You can ask questions about anything.',
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $records[] = (object) [
            'userid' => $userid,
            'name' => 'Persona 2',
            'prompt' => 'You are a unhelpful assistant.',
            'userinfo' => 'You are speaking to a unhelpful assistant. You can ask questions about anything.',
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        $DB->insert_records('block_ai_chat_personas', $records);
    }

}
