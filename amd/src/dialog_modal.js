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
 * TODO describe module dialog_modal
 *
 * @module     block_ai_interface/dialog_modal
 * @copyright  2024 Tobias Garske, ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';

export default class DialogModal extends Modal {
    static TYPE = "block_ai_interface/dialog_modal";
    static TEMPLATE = "block_ai_interface/dialog_modal";

    configure(modalConfig) {
        // Show this modal on instantiation.
        modalConfig.show = false;

        // Remove from the DOM on close.
        modalConfig.removeOnClose = false;

        super.configure(modalConfig);

        // Accept our own custom arguments too.
        if (modalConfig.titletest) {
            this.setTitletest(modalConfig.titletest);
        }
    }

    setTitletest(value) {
        this.titletest = value;
    }
}
