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
     * Returns true as block shouldn't be shown as block.
     *
     * @return bool
     */
    public function is_empty(): bool {
        return true;
    }

    /**
     * Adds the block content to the page header.
     *
     * @return void
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function specialization(): void {
        if (!empty($this->instance->visible)) {
            $this->get_content();
            $this->page->add_header_action($this->content->text);
        }
    }

    /**
     * Returns the block content. Content is cached for performance reasons.
     *
     * @return stdClass
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function get_content(): stdClass {
        global $OUTPUT;
        $dummy = new stdClass;
        $dummy->text = '';
        if ($this->content !== null) {
            return $dummy;
        }

        $this->content = new stdClass;
        $context = new stdClass;
        $context->sesskey = sesskey();

        $aioutput = $this->page->get_renderer('block_ai_chat');
        $this->content->text = $aioutput->render_ai_chat_content();

        $this->content->text = $OUTPUT->render_from_template('block_ai_chat/floatingbutton', $context);

        return $dummy;
    }

    /**
     * Returns false as there can be only one floating button block on one page to avoid collisions.
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
        return ['course-view' => true, 'mod' => true];
    }
}
