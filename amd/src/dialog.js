import DialogModal from 'block_ai_interface/dialog_modal';
import * as externalServices from 'block_ai_interface/webservices';
import Templates from 'core/templates';
import {exception as displayException} from 'core/notification';
import {makeRequest} from 'local_ai_manager/make_request';
import { getNewConversationId } from './webservices';

// Declare variables.
// Modal.
let modal = {};
let modaltitle = '';

// Current conversation.
let conversation = {
    id: 0,
    messages: [],
};
// All conversations.
let allConversations = [];
// Userid.
let userid = 0;
// Course context id.
let contextid = 0;
// First load.
let firstLoad = true;
// AI in process of answering.
let aiAtWork = false;

export const init = async(params) => {
    userid = params.userid;
    contextid = params.contextid;
    modaltitle = params.title;

    // Build modal.
    modal = await DialogModal.create({
        templateContext: {
            title: modaltitle,
            // history: history, // history dynamically added.
        },
    });

    // Add class for styling when modal is displayed.
    modal.getRoot().on('modal:shown', function(e) {
        e.target.classList.add("ai_interface_modal");
    });

    // Load conversations.
    await getConversations();

    // Attach listener to the ai button to call modal.
    let button = document.getElementById("ai_interface_button");
    button.addEventListener('mousedown', function() {
        showModal(params);
    });
};

/**
 * Show ai_interface modal.
 */
async function showModal() {

    // Show modal.
    await modal.show();

    // Add listener for input submission.
    const form = document.getElementById('block_ai_form');
    form.addEventListener("submit", (event) => {
        submitForm(event);
    });
    const textarea = document.getElementById('block_ai_interface-input-id');
    addTextareaListener(textarea);


    if (firstLoad) {
        // Show conversation.
        // Todo - Evtl. noch firstload verschönern, spinner für header und content z.b.
        showConversation();

        // Add history to dropdownmenu.
        addToHistory(allConversations);

        // Add listeners for dropdownmenu.
        const btnNewDialog = document.getElementById('block_ai_interface_new_dialog');
        btnNewDialog.addEventListener('mousedown', () => {
            newDialog();
        });
        const btnDeleteDialog = document.getElementById('block_ai_interface_delete_dialog');
        btnDeleteDialog.addEventListener('click', () => {
            deleteCurrentDialog();
        });
        firstLoad = false;
    }

    focustextarea();
}

/**
 * Send input to ai connector.
 * @param {*} question
 */
const enterQuestion = async(question) => {

    // Deny changing dialogs until answer present?

    // Add to conversation, answer not yet available.
    showMessage(question, 'self', false);

    // For first message, add a system message.
    if (conversation.messages.length === 0) {
        conversation.messages.push({
            'message': 'Answer in german',
            'sender': 'system',
        });
    }

    // Options, with conversation history.
    const options = {
        'component': 'block_ai_interface',
        'contextid': contextid,
        'conversationcontext': conversation.messages,
    };

    // For a new conversation, get an id.
    if (conversation.id === 0) {
        try {
            let idresult = await externalServices.getNewConversationId(contextid);
            conversation.id = idresult.id;
        } catch (error) {
            displayException(error);
        }
        options.forcenewitemid = true;
    }
    options.itemid = conversation.id;


    // Send to local_ai_manager.
    let requestresult = await askLocalAiManager('chat', question, options);
    // If code 409, conversationid is already taken, get new one.
    while (requestresult.code == 409) {
        // Todo test, sleep and falsify db entry so error is triggered and a new id is given.
        try {
            let idresult = await externalServices.getNewConversationId(contextid);
            conversation.id = idresult.id;
            options.itemid = conversation.id;
        } catch (error) {
            displayException(error);
        }
        // Retry with new id.
        requestresult = await askLocalAiManager('chat', question, options);
    }

    // Write back answer.
    showReply(requestresult.result);

    // Attach copy listener.
    let copy = document.querySelector('.ai_interface_modal .awaitanswer .copy');
    copyToClipboard(copy);

    // Save new question and answer.
    saveConversationLocally(question, requestresult.result);

    // Ai is done.
    aiAtWork = false;
};

/**
 * Render reply.
 * @param {string} text
 */
const showReply = (text) => {
    let field = document.querySelector('.ai_interface_modal .awaitanswer .text div');
    field.replaceWith(text);
};

/**
 * Create new / Reset dialog.
 * @param {bool} deleted
 */
const newDialog = (deleted = false) => {
    console.log("newDialog called");
    // Add current convo to history and local representation, if not already there.
    if (allConversations.find(x => x.id === conversation.id) === undefined && !deleted) {
        addToHistory([conversation]);
        allConversations.push(conversation);
    }
    // Reset local conservation.
    conversation = {
        id: 0,
        messages: [],
    };
    clearMessages();
    setModalHeader(true);
};

/**
 * Delete /hide current dialog.
 */
const deleteCurrentDialog = async() => {
    console.log("deleteCurrentDialog called");
    if (conversation.id !== 0) {
        try {
            const deleted = await externalServices.deleteConversation(contextid, userid, conversation.id);
            if (deleted) {
                removeFromHistory();
                showConversation();
                // newDialog(true);
            }
        } catch (error) {
            displayException(error);
        }
    }
};

/**
 * Get the async answer from the local_ai_manager.
 *
 * @param {string} purpose
 * @param {string} prompt
 * @param {array} options
 * @returns {string}
 */
const askLocalAiManager = async(purpose, prompt, options = []) => {
    let result;
    try {
        result = await makeRequest(purpose, prompt, JSON.stringify(options));
    } catch (error) {
        displayException(error);
    }
    return result;
};

const showMessages = () => {
    console.log("showMessages called");
    conversation.messages.forEach((val) => {
        showMessage(val.message, val.sender);
    });
};

/**
 * Show answer from local_ai_manager.
 * @param {*} text
 * @param {*} sender User or Ai
 * @param {*} answer Is answer in history
 */
const showMessage = async(text, sender = '', answer = true) => {
    // Skip if sender is system.
    if (sender === 'system') {
        return;
    }
    // Imitate bool for message.mustache logic {{#sender}}.
    if (sender === 'ai') {
        sender = '';
    }
    const templateData = {
        "sender": sender,
        "content": text,
        "answer": answer,
    };
    // Call the function to load and render our template.
    const {html, js} = await Templates.renderForPromise('block_ai_interface/message', templateData);
    Templates.appendNodeContents('.block_ai_interface-output', html, js);

    // Scroll the modal content to the bottom.
    scrollToBottom();
};

/**
 * Clear output div.
 */
const clearMessages = () => {
    console.log("clearMessages called");
    const output = document.querySelector('.block_ai_interface-output');
    output.innerHTML = '';
};

/**
 * Webservice Get all conversations.
 */
const getConversations = async() => {
    console.log("allConversations called");
    try {
        // Ist hier await nötig um in init auf den Button listener zu warten?
        allConversations = await externalServices.getAllConversations(userid, contextid);
    } catch (error) {
        displayException(error);
    }
};

/**
 * Add conversations to history.
 * @param {*} convos Conversations
 */
const addToHistory = (convos) => {
    convos.forEach(async(convo) => {
        // Conditionally shorten menu title, skip system message.
        let title = convo.messages[1].message;
        if (convo.messages[1].message.length > 50) {
            title = convo.messages[1].message.substring(0, 50);
            title += ' ...';
        }

        // Add entry in menu.
        const templateData = {
            "title": title,
            "conversationid": convo.id,
        };

        const {html, js} = await Templates.renderForPromise('block_ai_interface/dropdownmenuitem', templateData);
        Templates.appendNodeContents('.block_ai_interface_action_menu .dropdown-menu', html, js);

        // If we add only one item, it is a new item and should be on top of history.
        if (convos.length === 1) {
            console.log("move item to top called");
            // Make sure elements are in place to be worked with.
            const dropdown = document.querySelector('.block_ai_interface_action_menu .dropdown-menu');
            // Select the last element.
            const lastItem = dropdown.lastElementChild;
            // Get the reference element for the third position.
            const thirdChild = dropdown.children[2];
            // Remove the last item from its current position.
            dropdown.removeChild(lastItem);
            // Insert the last item at the new position (before the third child).
            dropdown.insertBefore(lastItem, thirdChild);                
        }
    });

    // If we have more than 9 items, add scrollbar to menu.
    if (convos.length > 9) {
        const dropdown = document.querySelector('.block_ai_interface_action_menu .dropdown-menu');
        dropdown.classList.add("addscroll");
    }
};

/**
 * Remove currrent conversation from history.
 */
const removeFromHistory = () => {
    if (conversation.id !== 0) {
        // Remove from dropdown.
        const element = document.querySelector('.block_ai_interface_action_menu [data-id="' + conversation.id + '"]');
        element.remove();
        // Remove from allConversations array.
        allConversations = allConversations.filter(obj => obj.id !== conversation.id);
    }
};

/**
 * Function to set conversation.
 * @param {*} id
 */
const showConversation = (id = 0) => {
    // Change conversation or get last conversation.
    console.log("showConversation called");
    if (id !== 0) {
        conversation = allConversations.find(x => x.id === id);
    } else if (typeof allConversations[0] !== 'undefined') {
        console.log("last item allconv");
        conversation = allConversations.at(0);
    }
    clearMessages();
    showMessages();
    setModalHeader();
    attachCopyListener();
};
// Make globally accessible since it is used to show history in dropdownmenuitem.mustache.
document.showConversation = showConversation;

/**
 * Webservice Save conversation.
 * @param {*} question
 * @param {*} reply
 */
const saveConversationLocally = (question, reply) => {
    // Add to local representation.
    let message = {'message': question, 'sender': 'user'};
    conversation.messages.push(message);
    message = {'message': reply, 'sender': 'ai'};
    conversation.messages.push(message);
};

/**
 * Set modal header title.
 * @param {*} empty
 */
const setModalHeader = (empty = false) => {
    let modalheader = document.querySelector('.ai_interface_modal .modal-title div');
    if (modalheader !== null && (conversation.messages.length > 0 || empty)) {
        let title = '';
        if (!empty) {
            title = ' - ' + conversation.messages[1].message;
            if (conversation.messages[1].message.length > 50) {
                title = ' - ' + conversation.messages[1].message.substring(0, 50);
                title += ' ...';
            }
        }
        modalheader.innerHTML = modaltitle + title;
    }
};

/**
 * Focus textarea.
 */
const focustextarea = () => {
    const textarea = document.getElementById('block_ai_interface-input-id');
    textarea.focus();
};

/**
 * Scroll to bottom of modal body.
 */
const scrollToBottom = () => {
    console.log("scroll to bottom called");
    const modalContent = document.querySelector('.ai_interface_modal .modal-body');
    modalContent.scrollTop = modalContent.scrollHeight;
};

/**
 * Attach event listener.
 * @param {*} textarea
 */
const addTextareaListener = (textarea) => {
    textarea.addEventListener('keydown', textareaOnKeydown);
};

/**
 * Action for textarea submission.
 * @param {*} event
 */
const textareaOnKeydown = (event) => {
    // TODO check for mobile devices.
    if (event.key === 'Enter' && !aiAtWork && !event.shiftKey) {
        aiAtWork = true;
        enterQuestion(event.target.value);
        event.preventDefault();
        event.target.value = '';
    }
};

/**
 * Submit form.
 * @param {*} event
 */
const submitForm = (event) => {
    event.preventDefault();
    // Var aiAtWork to make it impossible to submit multiple questions at once.
    if (!aiAtWork) {
        aiAtWork = true;
        const textarea = document.getElementById('block_ai_interface-input-id');
        enterQuestion(textarea.value);
        textarea.value = '';
    }
};

/**
 * Attach copy listener to all elements.
 */
const attachCopyListener = () => {
    const elements = document.querySelectorAll(".ai_interface_modal .copy");
    elements.forEach((element) => {
        element.addEventListener('mousedown', function() {
            copyToClipboard(element);
        });
    });
}

/**
 * Copy ai reply to clipboard.
 * @param {*} element
 */
const copyToClipboard = (element) => {

    // Find the adjacent text container.
    const textElement = element.nextElementSibling;

    // Get the text content.
    const textToCopy = textElement.innerText || textElement.textContent;

    // Copy to clipboard using the Clipboard API.
    navigator.clipboard.writeText(textToCopy);
};
