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
 * Hook listener callbacks.
 *
 * @package    block_ai_chat
 * @copyright  2024 ISB Bayern
 * @author     Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Add a checkbox to add a ai-chat block.
     *
     * @param after_form_definition $hook
     */
    public static function handle_after_form_definition(\core_course\hook\after_form_definition $hook): void {
        $tenant = \core\di::get(\local_ai_manager\local\tenant::class);
        if ($tenant->is_tenant_allowed()) {
            $mform = $hook->mform;
            $mform->addElement('checkbox', 'addaichat', get_string('addblockinstance', 'block_ai_chat'), 'add_block_ai_chat');
            $mform->addHelpButton('addaichat', 'addblockinstance', 'block_ai_chat');
            $mform->setDefaults('addaichat', 1);
        }
    }

    /**
     * Check for addaichat form setting and add/remove ai-chat blockk.
     *
     * @param after_form_submission $hook
     */
    public static function handle_after_form_submission(\core_course\hook\after_form_submission $hook): void {
        global $DB;
        // Get form data.
        $data = $hook->get_data();

        // Check if block_ai_chat instance is present.
        $courseid = $data->id;
        $blockinstance = \block_ai_chat\local\helper::check_block_present($courseid);

        if ($data->addaichat == '1') {
            if (!$blockinstance) {
                // Add block instance.
                $newinstance = new \stdClass;
                $newinstance->blockname = 'ai_chat';
                $newinstance->parentcontextid = \context_course::instance($courseid)->id;
                $newinstance->showinsubcontexts = 0;
                $newinstance->pagetypepattern = '*';
                $newinstance->subpagepattern = null;
                $newinstance->defaultregion = 'side-pre';
                $newinstance->defaultweight = 1;
                $newinstance->configdata = '';
                $newinstance->timecreated = time();
                $newinstance->timemodified = $newinstance->timecreated;
                $newinstance->id = $DB->insert_record('block_instances', $newinstance);
            }
        } else {
            if ($blockinstance) {
                // Remove block instance.
                blocks_delete_instance($blockinstance);
            }
        }
    }

    /**
     * Check if block instance is present and set addaichat form setting.
     *
     * @param after_form_submission $hook
     */
    public static function handle_after_form_definition_after_data(\core_course\hook\after_form_definition_after_data $hook): void {
        // Get form data.
        $mform = $hook->mform;
        $formwrapper = $hook->formwrapper;
        $courseid = $formwrapper->get_course()->id;

        $blockinstance = \block_ai_chat\local\helper::check_block_present($courseid);
        if ($blockinstance) {
            // Block present, so set checkbox accordingly.
            $mform->setDefault('addaichat', "checked");
        }
    }

}
