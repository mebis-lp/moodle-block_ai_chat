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
 * External functions and service declaration for AI Interface
 *
 * Documentation: {@link https://moodledev.io/docs/apis/subsystems/external/description}
 *
 * @package    block_ai_interface
 * @category   webservice
 * @copyright  2024 Tobias Garske, ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_ai_interface_get_all_conversations' => [
        'classname'     => 'block_ai_interface\external\get_all_conversations',
        'methodname'    => 'execute',
        'description'   => 'Get all conversations.',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => '', // TODO add cap
    ],
    'block_ai_interface_save_interaction' => [
        'classname'     => 'block_ai_interface\external\save_interaction',
        'methodname'    => 'execute',
        'description'   => 'Save question and reply.',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => '', // TODO add cap
    ],
];
