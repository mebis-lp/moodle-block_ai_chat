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

namespace block_ai_chat\form;

use core_form\dynamic_form;
use context;
use function DI\get;

/**
 * Class base_form
 *
 * @package    block_ai_chat
 * @copyright  2025 Tobias Garske, ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class persona_form extends dynamic_form {
    /** @var array $personas */
    protected array $personas;
    /** @var bool|object $personaselected */
    protected bool|object $personaselected;
    /** @var int $blockcontextid */
    protected int $blockcontextid;

    /**
     * Form definition.
     */
    public function definition() {
        global $USER, $DB;

        // Load default and user personas here since select options need to be inserted.
        $names = [];
        $prompts = [];
        $currentprompt = '';
        $sql = "SELECT per.id, per.userid, per.name, per.prompt  FROM {block_ai_chat_personas} per
                LEFT JOIN {block_ai_chat_personas_selected} sel ON sel.personasid = per.id
                WHERE per.userid = 0 OR per.userid = :userid";
        $this->personas = $DB->get_records_sql($sql, ['userid' => $USER->id]);
        foreach ($this->personas as $key => $persona) {
            $names[$persona->id] = $persona->name;
            $prompts[$persona->id] = $persona->prompt;
            // Get current persona.
            if ($persona->userid != 0) {
                $currentprompt = $persona->prompt;
            }
        }
        // Add option "none".
        $names[0] = get_string('nopersona', 'block_ai_chat');
        // Stringify.
        $prompts = json_encode($prompts);

        $mform =& $this->_form;

        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);
        $mform->setDefault('contextid', $this->optional_param('contextid', null, PARAM_INT));

        $mform->addElement('hidden', 'prompts');
        $mform->setType('prompts', PARAM_TEXT);
        $mform->setDefault('prompts', $prompts);

        $mform->addElement('select', 'name', get_string('name', 'block_ai_chat'), $names);
        $mform->setType('name', PARAM_ALPHANUM);

        $mform->addElement('textarea', 'prompt', get_string('prompt', 'block_ai_chat'));
        $mform->setType('prompt', PARAM_TEXT);
        $mform->setDefault('prompt', $currentprompt);
    }

    /**
     * Returns the user context
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        // When modal is built, contextid is passed as optional_param. For submission it is accessed via formdata.
        if (!isset($this->blockcontextid)) {
            $this->blockcontextid = $this->optional_param('contextid', null, PARAM_INT);
        }
        return \context::instance_by_id($this->blockcontextid);
    }

    /**
     *
     * Checks if current user has sufficient permissions, otherwise throws exception
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('block/ai_chat:addinstance', \context_system::instance());
    }

    /**
     * Form validation.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files) {
        $errors = [];
        // $data['name'] = "0" is the option to delete the current persona.
        if (empty($data['name']) && $data['name'] !== "0") {
            $errors['name'] = get_string('errorname', 'block_ai_chat');
        }
        if (empty($data['prompt']) && $data['name'] !== "0") {
            $errors['prompt'] = get_string('errorprompt', 'block_ai_chat');
        }
        return $errors;
    }

    /**
     * Process the form submission, used if form was submitted via AJAX
     *
     * @return array Returns whether a new source was created.
     */
    public function process_dynamic_submission(): array {
        global $USER, $DB;

        $formdata = $this->get_data();

        $formdata->timemodified = time();

        $context = $this->get_context_for_dynamic_submission();

        // Delete connected persona entries.
        if ($formdata->name == 0) {
            $params = ['id' => $this->personaselected->personasid];
            $DB->delete_records('block_ai_chat_personas', $params);
            $params = ['contextid' => $formdata->contextid, 'personasid' => $this->personaselected->personasid];
            $DB->delete_records('block_ai_chat_personas_selected', $params);
            return [
                'update' => true,
            ];
        }

        // Check if persona is selected for this instance.
        if ($this->personaselected) {
            // Update personas_selected.
            // This is one record per instance, where the current prompt is saved.
            $record = new \stdClass();
            $record->id = $this->personaselected->personasid;
            $record->userid = $USER->id;
            $record->prompt = $formdata->prompt;
            $record->timemodified = time();
            $result1 = $DB->update_record('block_ai_chat_personas', $record);

        } else {
            // Insert new records.
            // This is to allow custom prompts.
            $record = new \stdClass();
            $record->userid = $USER->id;
            $record->name = get_string('chosenpersona', 'block_ai_chat');
            $record->prompt = $formdata->prompt;
            $record->timecreated = time();
            $record->timemodified = time();
            $entry = $DB->insert_record('block_ai_chat_personas', $record);

            // Insert to personas_selected.
            $record = new \stdClass();
            $record->personasid = $entry;
            $record->contextid = $formdata->contextid;
            $result2 = $DB->insert_record('block_ai_chat_personas_selected', $record);
        }

        // If no persona selected delete entry.
        // TODO

        return [
            'update' => true,
        ];
    }

    /**
     * Load in existing data as form defaults
     */
    public function set_data_for_dynamic_submission(): void {
        global $DB;

        $this->get_context_for_dynamic_submission();

        // Check if a persona is selected for this instance.
        $param = [$this->blockcontextid];
        $this->personaselected = $DB->get_record_select('block_ai_chat_personas_selected', 'contextid = ?', $param);
        if ($this->personaselected) {
            $data = [
                'name' => $this->personaselected->personasid,
                'prompt' => $this->personas[$this->personaselected->personasid]->prompt,
            ];
        } else {
            $data = [];
        }

        $this->set_data($data);
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/block_ai_chat_persona_dummy.php');
    }
}
