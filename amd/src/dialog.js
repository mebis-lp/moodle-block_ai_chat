import DialogModal from 'block_ai_chat/dialog_modal';
import * as externalServices from 'block_ai_chat/webservices';
import Templates from 'core/templates';
import {alert, exception as displayException} from 'core/notification';
import * as helper from 'block_ai_chat/helper';
import * as manager from 'block_ai_chat/ai_manager';
import {getString} from 'core/str';
import {marked} from 'block_ai_chat/vendor/marked.esm';
import {renderInfoBox} from 'local_ai_manager/render_infobox';

// Declare variables.
// Modal.
let modal = {};
let strHistory;
let strNewDialog;
let strToday;
let strYesterday;
let badge;

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
// Maximum history included in query.
let maxHistory = 5;
// Remember warnings for maximum history in this session.
let maxHistoryWarnings = new Set();

export const init = async(params) => {
    userid = params.userid;
    contextid = params.contextid;
    strNewDialog = params.new;
    strHistory = params.history;
    badge = params.badge;

    // Build modal.
    modal = await DialogModal.create({
        templateContext: {
            title: strNewDialog,
            badge: badge,
            // history: history, // history dynamically added.
        },
    });

    // Add class for styling when modal is displayed.
    modal.getRoot().on('modal:shown', function(e) {
        e.target.classList.add("ai_chat_modal");
    });

    // Load conversations.
    await getConversations();

    // Get conversationcontext message limit.
    let conversationcontextLimit = await externalServices.getConversationcontextLimit(contextid);
    maxHistory = conversationcontextLimit.limit;

    // Attach listener to the ai button to call modal.
    let button = document.getElementById('ai_chat_button');
    button.addEventListener('mousedown', async() => {
        showModal(params);
        await renderInfoBox('block_ai_chat', userid, '.ai_chat_modal_body [data-content="local_ai_manager_infobox"]');
    });

    // Get strings.
    strToday = await getString('today', 'core');
    strYesterday = await getString('yesterday', 'block_ai_chat');
};

/**
 * Show ai_chat modal.
 */
async function showModal() {

    // Show modal.
    await modal.show();

    // Add listener for input submission.
    const textarea = document.getElementById('block_ai_chat-input-id');
    addTextareaListener(textarea);
    const button = document.getElementById('block_ai_chat-submit-id');
    button.addEventListener("click", (event) => {
        clickSubmitButton(event);
    });

    if (firstLoad) {
        // Show conversation.
        // Todo - Evtl. noch firstload verschönern, spinner für header und content z.b.
        showConversation();

        // Add listeners for dropdownmenu.
        const btnNewDialog = document.getElementById('block_ai_chat_new_dialog');
        btnNewDialog.addEventListener('mousedown', () => {
            newDialog();
        });
        const btnDeleteDialog = document.getElementById('block_ai_chat_delete_dialog');
        btnDeleteDialog.addEventListener('click', () => {
            deleteCurrentDialog();
        });
        const btnShowHistory = document.getElementById('block_ai_chat_show_history');
        btnShowHistory.addEventListener('click', () => {
            showHistory();
        });
        firstLoad = false;
    }

    helper.focustextarea();
}


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
 * Function to set conversation.
 * @param {*} id
 */
const showConversation = (id = 0) => {
    console.log("showConversation called");
    // Dissallow changing conversations when question running.
    if (aiAtWork) {
        return;
    }
    // Change conversation or get last conversation.
    if (id !== 0) {
        // Set selected conversation.
        conversation = allConversations.find(x => x.id === id);
    } else if (typeof allConversations[0] !== 'undefined') {
        // Set last conversation.
        conversation = allConversations.at(0);
    } else if (allConversations.length === 0) {
        // Last conversation has been deleted.
        newDialog(true);
    }
    clearMessages();
    setModalHeader();
    showMessages();
};
// Make globally accessible since it is used to show history in dropdownmenuitem.mustache.
document.showConversation = showConversation;


/**
 * Send input to ai connector.
 * @param {*} question
 */
const enterQuestion = async(question) => {

    // Deny changing dialogs until answer present?
    if (question == '') {
        aiAtWork = false;
        return;
    }

    // Add to conversation, answer not yet available.
    showMessage(question, 'self', false);

    // For first message, add a system message.
    if (conversation.messages.length === 0) {
        conversation.messages.push({
            'message': 'Answer in german',
            'sender': 'system',
        });
    }

    // Ceck history for length limit.
    const convHistory = await checkMessageHistoryLengthLimit(conversation.messages);

    // Options, with conversation history.
    const options = {
        'component': 'block_ai_chat',
        'contextid': contextid,
        'conversationcontext': convHistory,
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

    // Pass itemid / conversationid.
    options.itemid = conversation.id;

    // Send to local_ai_manager.
    let requestresult = await manager.askLocalAiManager('chat', question, options);

    // Handle errors.
    if (requestresult.code != 200) {
        requestresult = await errorHandling(requestresult, question, options);
    }

    // Write back answer.
    showReply(requestresult.result);

    // Ai is done.
    aiAtWork = false;

    // Attach copy listener.
    let copy = document.querySelector('.ai_chat_modal .awaitanswer .copy');
    copy.addEventListener('mousedown', () => {
        helper.copyToClipboard(copy);
    });

    // Save new question and answer.
    saveConversationLocally(question, requestresult.result);
};

/**
 * Render reply.
 * @param {string} text
 */
const showReply = async (text) => {
    let field = document.querySelector('.ai_chat_modal .awaitanswer .text');
    field.innerHTML = marked.parse(text);
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
        "content": marked.parse(text),
        "answer": answer,
    };
    // Call the function to load and render our template.
    const {html, js} = await Templates.renderForPromise('block_ai_chat/message', templateData);
    Templates.appendNodeContents('.block_ai_chat-output', html, js);

    // Add copy listener for replys.
    if (sender === '') {
        helper.attachCopyListenerLast();
    }

    // Scroll the modal content to the bottom.
    helper.scrollToBottom();
};

/**
 * Create new / Reset dialog.
 * @param {bool} deleted
 */
const newDialog = (deleted = false) => {
    console.log("newDialog called");
    if (aiAtWork) {
        return;
    }
    // Add current convo local representation, if not already there.
    if (allConversations.find(x => x.id === conversation.id) === undefined && !deleted) {
        allConversations.push(conversation);
    }
    // Reset local conservation.
    conversation = {
        id: 0,
        messages: [],
    };
    clearMessages();
    setModalHeader(strNewDialog);
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
            }
        } catch (error) {
            displayException(error);
        }
    }
};

/**
 * Show conversation history.
 */
const showHistory = () => {
    console.log("showHistory called");
    // Change title and add backlink.
    let title = '<a href="#" id="block_ai_chat_backlink"><i class="icon fa fa-arrow-left"></i>' + strHistory + '</a>';
    clearMessages(true);
    setModalHeader(title);
    const btnBacklink = document.getElementById('block_ai_chat_backlink');
    btnBacklink.addEventListener('click', () => {
        showConversation(conversation.id);
        clearMessages();
        setModalHeader();
    });

    // Render history.
    let tmpDateString = '';
    allConversations.forEach(async(convo, key, arr) => {
        if (typeof convo.messages[1] !== 'undefined') {
            // Conditionally shorten menu title, skip system message.
            let title = convo.messages[1].message;
            if (convo.messages[1].message.length > 50) {
                title = convo.messages[1].message.substring(0, 50);
                title += ' ...';
            }

            // Get date and pass along if new.
            const now = new Date();
            const date = new Date(convo.timecreated * 1000);
            const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            const yesterday = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1);
            const twoWeeksAgo = new Date(now);
            twoWeeksAgo.setDate(now.getDate() - 14);

            const options = {weekday: 'long', day: '2-digit', month: '2-digit'};
            const monthOptions = {month: 'long', year: '2-digit'};

            let dateString = '';
            if (date >= today) {
                dateString = strToday;
            } else if (date >= yesterday) {
                dateString = strYesterday;
            } else if (date >= twoWeeksAgo) {
                dateString = date.toLocaleDateString(undefined, options);
            } else {
                dateString = date.toLocaleDateString(undefined, monthOptions);
            }
            if (dateString == tmpDateString) {
                // Dont show date, if it hasnt changed.
                dateString = '';
            } else {
                // Remember new latest date.
                tmpDateString = dateString;
            }

            // Add entry in history.
            const templateData = {
                "title": title,
                "conversationid": convo.id,
                "date": dateString,
            };

            // Add new Dialog button for the last element.
            if (Object.is(arr.length - 1, key)) {
                templateData.last = true;
            }

            // Render history item.
            const {html, js} = await Templates.renderForPromise('block_ai_chat/historyitem', templateData);
            Templates.appendNodeContents('.ai_chat_modal .block_ai_chat-output', html, js);

            // Add a listener for the new dialog button.
            if (Object.is(arr.length - 1, key)) {
                const btnNewDialog = document.getElementById('ai_chat_history_new_dialog');
                btnNewDialog.addEventListener('mousedown', () => {
                    newDialog();
                });
            }
        }
    });

};

/**
 * Remove currrent conversation from history.
 */
const removeFromHistory = () => {
    // Cant remove if new or not yet in history.
    if (conversation.id !== 0 && allConversations.find(x => x.id === conversation.id) !== undefined) {
        // Remove from dropdown.
        const element = document.querySelector('.block_ai_chat_action_menu [data-id="' + conversation.id + '"]');
        element.remove();
        // Build new allConversations array without deleted one.
        allConversations = allConversations.filter(obj => obj.id !== conversation.id);
    }
};

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
 * Clear output div.
 */
const clearMessages = (hideinput = false) => {
    console.log("clearMessages called");
    const output = document.querySelector('.block_ai_chat-output');
    output.innerHTML = '';
    // For showing history.
    let input = document.querySelector('.block_ai_chat-input');
    if (hideinput) {
        input.style.display = 'none';
    } else {
        input.style.display = 'flex';
    }
};

/**
 * Set modal header title.
 * @param {*} title
 */
const setModalHeader = (setTitle = '') => {
    let modalheader = document.querySelector('.ai_chat_modal .modal-title div');
    let title = '';
    if (modalheader !== null && (conversation.messages.length > 0 || setTitle.length)) {
        if (!setTitle.length) {
            title = conversation.messages[1].message;
            if (conversation.messages[1].message.length > 50) {
                title = conversation.messages[1].message.substring(0, 50);
                title += ' ...';
            }
        } else {
            title = setTitle;
        }
        modalheader.innerHTML = title;
    }
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
 */
const clickSubmitButton = () => {
    // Var aiAtWork to make it impossible to submit multiple questions at once.
    if (!aiAtWork) {
        aiAtWork = true;
        const textarea = document.getElementById('block_ai_chat-input-id');
        enterQuestion(textarea.value);
        textarea.value = '';
    }
};

/**
 * Handle error from local_ai_manager.
 * @param {*} requestresult
 * @param {*} question
 * @param {*} options
 * @returns {object}
 */
const errorHandling = async(requestresult, question, options) => {

    // If code 409, conversationid is already taken, try get new a one.
    if (requestresult.code == 409) {
        while (requestresult.code == 409) {
            try {
                let idresult = await externalServices.getNewConversationId(contextid);
                conversation.id = idresult.id;
                options.itemid = conversation.id;
            } catch (error) {
                displayException(error);
            }
            // Retry with new id.
            requestresult = await manager.askLocalAiManager('chat', question, options);
            return requestresult;
        }
    }

    // If any other errorcode, alert with errormessage.
    const errorString = await getString('errorwithcode', 'block_ai_chat', requestresult.code);
    await alert(errorString, requestresult.result);

    // Change answer styling to differentiate from ai.
    const answerdivs = document.querySelectorAll('.awaitanswer');
    const answerdiv = answerdivs[answerdivs.length - 1];
    const messagediv = answerdiv.closest('.message');
    messagediv.classList.add('text-danger');
    const senderdiv = messagediv.querySelector('.identity');
    senderdiv.textContent = 'System';

    // And write generic error message in chatbot.
    requestresult.result = await getString('error', 'block_ai_chat');

    return requestresult;
};

/**
 * Check historic messages for max length.
 * @param {array} messages
 * @returns {array}
 */
const checkMessageHistoryLengthLimit = async(messages) => {
    const length = messages.length;
    console.log("checkHistoryLengthLimit called");
    if (length > maxHistory) {
        // Cut history.
        let shortenedMessages = [messages[0], ...messages.slice(-maxHistory)];
        console.log(shortenedMessages);

        // Show warning once per session.
        if (!maxHistoryWarnings.has(conversation.id)) {
            const maxHistoryString = await getString('maxhistory', 'block_ai_chat', maxHistory);
            const warningErrorString = await getString('maxhistoryreached', 'block_ai_chat', maxHistory);
            await alert(maxHistoryString, warningErrorString);
            // Remember warning.
            maxHistoryWarnings.add(conversation.id);
        }
        return shortenedMessages;
    }
    // Limit not reached, return messages.
    return messages;
};
