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
use block_ai_chat\local\helper;

/**
 * Block class for block_ai_chat
 *
 * @package    block_ai_chat
 * @copyright  2024 ISB Bayern
 * @author     Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_ai_chat extends block_base {

    /**
     * Initialize block
     *
     * @return void
     * @throws coding_exception
     */
    public function init(): void {
        $this->title = get_string('ai_chat', 'block_ai_chat');
    }

    /**
     * Allow the block to have a configuration page
     *
     * @return bool
     */
    public function has_config(): bool {
        return true;
    }

    /**
     * Returns the block content. Content is cached for performance reasons.
     *
     * @return stdClass
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function get_content(): stdClass {
        global $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        $tenant = \core\di::get(\local_ai_manager\local\tenant::class);
        $configmanager = \core\di::get(\local_ai_manager\local\config_manager::class);
        $aiconfig = \local_ai_manager\ai_manager_utils::get_ai_config($USER);
        $chatconfig = array_values(array_filter($aiconfig['purposes'], fn($purpose) => $purpose['purpose'] === 'chat'))[0];
        if (!$tenant->is_tenant_allowed()) {
            return $this->content;
        }
        if ($tenant->is_tenant_allowed() && !$configmanager->is_tenant_enabled()) {
            if ($aiconfig['role'] === \local_ai_manager\local\userinfo::ROLE_EXTENDED) {
                // Notice for Role_extended users.
                $this->content->text = get_string('error_tenantdisabled', 'local_ai_manager');
            }
            return $this->content;
        }
        if (!$chatconfig['isconfigured']) {
            if ($aiconfig['role'] === \local_ai_manager\local\userinfo::ROLE_EXTENDED) {
                // Notice for Role_extended users.
                $this->content->text = get_string('error_purposenotconfigured', 'local_ai_manager');
            }
            return $this->content;
        }
        $context = \context_block::instance($this->instance->id);
        if (!has_capability('block/ai_chat:view', $context)) {
            return $this->content;
        }

        $this->content = new stdClass;

        /** @var block_ai_chat\output\renderer $aioutput */
        $aioutput = $this->page->get_renderer('block_ai_chat');
        $this->content->text = $aioutput->render_ai_chat_content($this);

        return $this->content;
    }

    /**
     * Returns false as there can be only one ai_chat block on one page to avoid collisions.
     *
     * @return bool
     */
    public function instance_allow_multiple(): bool {
        return false;
    }

    /**
     * Returns on which page formats this block can be used.
     *
     * @return array
     */
    public function applicable_formats(): array {
        return ['course-view' => true];
    }

    #[\Override]
    public function user_can_addto($page) {
        $tenant = \core\di::get(\local_ai_manager\local\tenant::class);
        // Add exception for site admin, otherwise we cannot add the block to the dashboard in the website administration.
        if (!$tenant->is_tenant_allowed() && !is_siteadmin()) {
            return false;
        }
        if (helper::show_global_block($page)) {
            // If a global block is being shown on a page, we do not allow the user to add an own block.
            return false;
        }
        return parent::user_can_addto($page);
    }

    #[\Override]
    public function instance_create() {
        global $DB;

        // For standard dashboard keep the standard.
        if (isset($this->page->context) && $this->page->context::instance()->id != SYSCONTEXTID) {
            return true;
        }

        // For courses set default to show on all pages.
        if ($this->context->get_parent_context()->contextlevel === CONTEXT_COURSE) {
            $DB->update_record('block_instances', ['id' => $this->instance->id, 'pagetypepattern' => '*']);
        }
        return true;
    }

}
