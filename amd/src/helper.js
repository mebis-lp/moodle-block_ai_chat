/**
 * Copy ai reply to clipboard.
 * @param {*} element
 */
export const copyToClipboard = (element) => {

    // Find the adjacent text container.
    const textElement = element.nextElementSibling;

    // Get the text content.
    const textToCopy = textElement.innerText || textElement.textContent;

    // Copy to clipboard using the Clipboard API.
    navigator.clipboard.writeText(textToCopy);
};

/**
 * Attach copy listener to all elements.
 */
export const attachCopyListener = () => {
    const elements = document.querySelectorAll(".ai_interface_modal .copy");
    elements.forEach((element) => {
        element.addEventListener('mousedown', function() {
            copyToClipboard(element);
        });
    });
};


/**
 * Focus textarea.
 */
export const focustextarea = () => {
    const textarea = document.getElementById('block_ai_interface-input-id');
    textarea.focus();
};


/**
 * Scroll to bottom of modal body.
 */
export const scrollToBottom = () => {
    console.log("scroll to bottom called");
    const modalContent = document.querySelector('.ai_interface_modal .modal-body');
    modalContent.scrollTop = modalContent.scrollHeight;
};
