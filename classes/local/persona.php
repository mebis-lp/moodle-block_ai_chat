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
 * @copyright  2025 Tobias Garske, ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class persona {
    /**
     * Fills personas table with default personas.
     * @return void
     */
    public static function install_default_personas(): void {
        global $DB;
        $records = [];
        $records[] = (object) [
            'userid' => 0,
            'name' => 'Persona 1',
            'prompt' => 'You are a helpful assistant.',
            'timecreated' => time(),
            'timemodified' => time()
        ];
        $records[] = (object) [
            'userid' => 0,
            'name' => 'Persona 2',
            'prompt' => 'You are a unhelpful assistant.',
            'timecreated' => time(),
            'timemodified' => time()
        ];

        $DB->insert_records('block_ai_chat_personas', $records);
    }

    /**
     * Get current persona for blockinstance.
     * @params int $blockinstanceid
     * @return string
     */
    public static function get_current_persona($blockinstanceid): string {
        global $DB;

        $sql = "SELECT per.prompt FROM {block_ai_chat_personas} per
                JOIN {block_ai_chat_personas_selected} sel ON sel.personasid = per.id
                WHERE sel.contextid = :contextid";
        $record = $DB->get_record_sql($sql, ['contextid' => $blockinstanceid]);
        if ($record) {
            return  $record->prompt;
        } else {
            return '';
        }
    }

    /**
     * Get all relevant personas for this instance.
     * @return array
     */
    public static function get_all_personas(): array {
        global $DB, $USER;

        $names = [];
        $prompts = [];
        $currentprompt = '';
        $sql = "SELECT per.id, per.userid, per.name, per.prompt  FROM {block_ai_chat_personas} per
                        LEFT JOIN {block_ai_chat_personas_selected} sel ON sel.personasid = per.id
                        WHERE per.userid = 0 OR per.userid = :userid";
        $personas = $DB->get_records_sql($sql, ['userid' => $USER->id]);
        foreach ($personas as $key => $persona) {
            $names[$persona->id] = $persona->name;
            $prompts[$persona->id] = $persona->prompt;
                // Get current persona.
            if ($persona->userid != 0) {
            $currentprompt = $persona->prompt;
            }
        }
        // Add option "none".
        $names[0] = get_string('nopersona', 'block_ai_chat');

        return [$currentprompt, $personas, $names, $prompts];
        }
}
