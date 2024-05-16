import DialogModal from 'block_ai_interface/dialog_modal';

export const init = async(params) => {
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
async function buildModal(params) {

    const history = get_history();
    const modal = await DialogModal.create({
        templateContext: {
            title: params.title,
            history: history,
        }
    });

    // Add class for styling when modal is displayed.
    modal.getRoot().on('modal:shown', function(e) {
        e.target.classList.add("ai_interface_modal");
        e.target.scrollTo(0, e.target.scrollHeight);
    });
    // Show modal.
    modal.show();
    // Wait for the modal to show and set focus.
    setTimeout(function() {
        focustextarea();
    }, 300);
}

/**
 * Send input to ai connector.
 * @param {*} params
 */
function send_input(params) {

}

/**
 * Get history from local cache.
 */
function get_history() {
    // Mock response.
    return [
        {"senderself": "1", "content": "Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua."},
        {"senderself": "", "content": "Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua."},
        {"senderself": "1", "content": "Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy."},
        {"senderself": "", "content": "Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua."},
        {"senderself": "1", "content": "Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua."},
        {"senderself": "", "content": "Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua.Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua."},
        {"senderself": "1", "content": "Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua."},
        {"senderself": "", "content": "Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua."},
        {"senderself": "1", "content": "Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy."},
        {"senderself": "", "content": "Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua."},
        {"senderself": "1", "content": "Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua."},
        {"senderself": "", "content": "Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua.Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua."},
    ];
}

/**
 * Save conversation to local cache.
 */
function cache_history(params) {

}

/**
 * Focus textarea, also wait till element is visible.
 */
function focustextarea() {
    let elapsed = 0;
    const interval = 25;
    const timeout = 2000;

    const checkInterval = setInterval(() => {
        // Check if the textarea exists.
        const textarea = document.getElementById('block_ai_interface-input-id');
        // textarea is a bad check, test with transition to be completed.
        if (textarea) {
            clearInterval(checkInterval);
            // To set focus multiple times, focus has to be reset.
            const rand = document.getElementsByTagName('input');
            rand[0].focus();
            textarea.focus();
        }

        // Increment elapsed time.
        elapsed += interval;

        // Check if the timeout has been reached.
        if (elapsed >= timeout) {
            clearInterval(checkInterval);
        }
    }, interval);
}
