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
        e.target.scrollTo(0, e.target.scrollHeight);
    });

    // Attach listener to the ai button to call modal.
    let button = document.getElementById("ai_interface_button");
    button.addEventListener('mousedown', function() {
        showModal(params);
    });

    // Load conversations.
    getConversations();
};

/**
 * Show ai_interface modal.
 */
async function showModal() {

    // Show modal.
    modal.show();

    // Add listener for input submission.
    const textarea = document.getElementById('block_ai_interface-input-id');
    addTextareaListener(textarea);

    console.log(firstLoad);
    if (firstLoad) {
        // Show conversation.
        // Todo - firstload rewrite header, element is null.
        showConversation();

        // Add history to dropdownmenu.
        addToHistory(allConversations);

        // Add listeners for dropdownmenu.
        const btnNewDialog = document.getElementById('block_ai_interface_new_dialog');
        btnNewDialog.addEventListener('mousedown', () => {
            newDialog();
        });
        firstLoad = false;
    }

    // Wait for the modal to show and set focus.
    setTimeout(function() {
        focustextarea();
    }, 300);
}

/**
 * Send input to ai connector.
 * @param {*} question
 */
const enterQuestion = async(question) => {

    // Remove listener, so another question cant be triggered.
    const textarea = document.getElementById('block_ai_interface-input-id');
    textarea.removeEventListener('keydown', textareaOnKeydown);

    // Deny changing dialogs until answer present?

    // Add to conversation.
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

    if (requestresult.code != 200) {
        // Requestresult errorhandling.
        errorHandling();
        return;
    }

    // Write back answer.
    showReply(requestresult.result);

    // Attach copy listener.
    let copy = document.querySelector('.ai_interface_modal .awaitanswer .copy');
    copyToClipboard(copy);

    // Save new question and answer.
    saveConversationLocally(question, requestresult.result);

    // Readd textarea listener.
    addTextareaListener(textarea);


    // Scroll the modal content to the bottom.
    setTimeout(() => {
        let modalContent = document.querySelector('.ai_interface_modal .modal-body');
        modalContent.scrollTop = modalContent.scrollHeight;
    }, 100);
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
 */
const newDialog = () => {
    console.log("newDialog called");
    // Add current convo to history and local representation, if not already there.
    console.log(allConversations.find(x => x.id === conversation.id));
    if (allConversations.find(x => x.id === conversation.id) === 'undefined') {
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

/**
 * Show answer from local_ai_manager.
 * @param {*} text
 * @param {*} sender User or Ai
 * @param {*} answer Is answer in history
 */
const showMessage = (text, sender = '', answer = true) => {
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
    Templates.renderForPromise('block_ai_interface/message', templateData)
        // It returns a promise that needs to be resoved.
        .then(({html, js}) => {
        // Append results.
        Templates.appendNodeContents('.block_ai_interface-output', html, js);
            return true;
        })
        // Deal with this exception.
        .catch(ex => displayException(ex));
};


const showMessages = () => {
    console.log("showMessages called");
    conversation.messages.forEach((val) => {
        showMessage(val.message, val.sender);
    });

    // Scroll to bottom, when changing conversations.
    const modaldiv = document.querySelector('.ai_interface_modal');
    if (modaldiv !== null) {
        setTimeout(() => {
            modaldiv.scrollTo(0, modaldiv.scrollHeight);
        }, 5);
    }
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
    convos.forEach((convo) => {
        // Conditionally shorten menu title, skip system message.
        let title = convo.messages[1].message;
        if (convo.messages[1].message.length > 50) {
            title = convo.messages[1].message.substring(0, 50);
            title += ' ...';
        }
        console.log(convo);
        console.log(convo.id);

        // Add entry in menu.
        const templateData = {
            "title": title,
            "conversationid": convo.id,
        };
        Templates.renderForPromise('block_ai_interface/dropdownmenuitem', templateData)
            // It returns a promise that needs to be resoved.
            .then(({html, js}) => {
                // Append results.
                Templates.appendNodeContents('.block_ai_interface_action_menu .dropdown-menu', html, js);
                return true;
            })
            // Deal with this exception.
            .catch(ex => displayException(ex));
    });
};


/**
 * Function to set conversation.
 * @param {*} id
 */
const showConversation = (id = 0) => {
    // Change conversation or get last conversation.
    console.log("showConversation called");
    console.log(typeof allConversations[0]);
    if (id !== 0) {
        conversation = allConversations.find(x => x.id === id);
    } else if (typeof allConversations[0] !== 'undefined') {
        console.log("last item allconv");
        conversation = allConversations.at(0);
    }
    clearMessages();
    showMessages();
    // Wait till elements can be interacted with.
    setTimeout(() => {
        setModalHeader();
        attachCopyListener();
    }, 235);
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
            title = ' - ' + conversation.messages[0].message;
            if (conversation.messages[0].message.length > 50) {
                title = ' - ' + conversation.messages[0].message.substring(0, 50);
                title += ' ...';
            }
        }
        modalheader.innerHTML = modaltitle + title;
    }
};

/**
 * Focus textarea, also wait till element is visible.
 */
const focustextarea = () => {
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
    if (event.key === 'Enter') {
        enterQuestion(event.target.value);
        event.preventDefault();
        event.target.value = '';
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

const errorHandling = (code) => {
    // Replace spinner with error message.

}