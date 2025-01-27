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
            'userinfo' => 'You are speaking to a helpful assistant. You can ask questions about anything.',
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $records[] = (object) [
            'userid' => 0,
            'name' => 'Persona 2',
            'prompt' => 'You are a unhelpful assistant.',
            'userinfo' => 'You are speaking to a unhelpful assistant. You can ask questions about anything.',
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        $DB->insert_records('block_ai_chat_personas', $records);
    }

    /**
     * Get current persona for blockinstance.
     * @param int $blockinstanceid
     * @return array
     */
    public static function get_current_persona($blockinstanceid): array {
        global $DB;

        $sql = "SELECT per.prompt, per.userinfo FROM {block_ai_chat_personas} per
                JOIN {block_ai_chat_personas_selected} sel ON sel.personasid = per.id
                WHERE sel.contextid = :contextid";
        $record = $DB->get_record_sql($sql, ['contextid' => $blockinstanceid]);
        if ($record) {
            return  [$record->prompt, $record->userinfo];
        } else {
            return ['', ''];
        }
    }

    /**
     * Get all relevant personas for this instance.
     * @return array
     */
    public static function get_all_personas(): array {
        global $DB, $USER;

        $names = [];
        // Add option "none".
        $names[0] = get_string('nopersona', 'block_ai_chat');
        $prompts = [];
        $userinfos = [];
        $sql = "SELECT per.id, per.userid, per.name, per.prompt, per.userinfo, sel.personasid FROM {block_ai_chat_personas} per
                        LEFT JOIN {block_ai_chat_personas_selected} sel ON sel.personasid = per.id
                        WHERE per.userid = 0 OR per.userid = :userid";
        $personas = $DB->get_records_sql($sql, ['userid' => $USER->id]);
        $templateids = [];
        foreach ($personas as $key => $persona) {
            // Add space for form select formatting.
            $names[$persona->id] = "\u{2002}" . $persona->name;
            $prompts[$persona->id] = $persona->prompt;
            $userinfos[$persona->id] = $persona->userinfo;
            // Get admintemplates with userid 0.
            if ($persona->userid == 0) {
                $templateids[] = $persona->id;
            }
        }

        return [$userinfos, $personas, $names, $prompts, $templateids];
    }
}
