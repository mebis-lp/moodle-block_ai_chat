import {exception as displayException} from 'core/notification';
import Templates from 'core/templates';


export const init = (params) => {

    // Attach click listener to the ai button.
    let button = document.getElementById("ai_interface_button");
    button.addEventListener('mousedown', function() {
        buildModal(params);
    });
};

/**
 * Build modal for ai_interface.
 * @param {*} params
 */
function buildModal(params) {
    // Build iconpicker modal with moodle modal factory
    Templates.renderForPromise('block_ai_interface/dialog', {params})
        .then(({html}) => {
            require(['jquery', 'core/modal_factory'], function($, ModalFactory) {
                var trigger = $('#create-modal');
                ModalFactory.create({
                    title: params.title,
                    body: html,
                    footer: '',
                }, trigger)
                .done(function(modal) {
                    modal.getRoot().on('modal:shown', function(e) {
                        $(e.target).addClass('ai_interface_modal');
                    });
                    modal.show();
                    // Wait for the modal to show and set focus.
                    setTimeout(function() {
                        const textarea = document.getElementById('block_ai_interface-input-id');
                        textarea.focus();
                    }, 175);
                });
            });
            return true;
        }).catch(ex => displayException(ex));
}

/**
 * Send input to ai connector.
 */
function send_input(params) {
    
}

/**
 * Get history from local cache.
 */
function get_history(params) {
    
}

/**
 * Save conversation to local cache.
 */
function cache_history(params) {
    
}