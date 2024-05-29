/**
 * Copy ai reply to clipboard.
 * @param {*} element
 */
export const copyToClipboard = async(element) => {

    // Find the adjacent text container.
    const textElement = element.nextElementSibling;

    // Get the text content.
    const textToCopy = textElement.innerText || textElement.textContent;

    // Copy to clipboard using the Clipboard API.
    navigator.clipboard.writeText(textToCopy);

    // Briefly show toast.
    const toast = element.previousElementSibling;
    toast.style.visibility = 'visible';
    setTimeout(() => {
       toast.style.visibility = 'hidden'; 
    }, 750);

};

/**
 * Attach copy listener to all elements.
 */
export const attachCopyListenerLast = () => {
    const elements = document.querySelectorAll(".ai_interface_modal .copy");
    const last = elements[elements.length - 1];
    last.addEventListener('click', function() {
        copyToClipboard(last);
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
