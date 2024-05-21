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
 * Renderer for content of block ai_interface.
 *
 * @package    block_ai_interface
 * @copyright  2024 Tobias Garske, ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_ai_interface\output;

use plugin_renderer_base;
use block_ai_interface\permissions;

class renderer extends plugin_renderer_base {
    /**
     * Defer to template.
     * @return string html for the page
     */
    public function render_ai_interface_content() {
        global $USER, $COURSE;

        // Do permissioncheck in renderer since renderer is called by fake block in drawers.php.
        if (!permissions::can_view_ai()) {
            return '';
        }

        $params = new \stdClass;
        $params->title = get_string('dialog', 'block_ai_interface');
        $params->userid = $USER->id;
        $params->contextid = \context_course::instance($COURSE->id)->id;
        $this->page->requires->js_call_amd(
            'block_ai_interface/dialog',
            'init',
            [$params]
        );

        return  parent::render_from_template('block_ai_interface/floatingbutton', $params);
    }
}
