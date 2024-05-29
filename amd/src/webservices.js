import {call as fetchMany} from 'core/ajax';

/**
 * Get all converstations a User can see.
 * @param {int} userid
 * @param {int} contextid
 * @returns {mixed}
 */
export const getAllConversations = (
    userid,
    contextid,
) => fetchMany([{
    methodname: 'block_ai_interface_get_all_conversations',
    args: {
        userid,
        contextid
}}])[0];

/**
 * Get all converstations a User can see.
 * @param {int} contextid
 * @returns {mixed}
 */
export const getNewConversationId = (
    contextid,
) => fetchMany([{
    methodname: 'block_ai_interface_get_new_conversation_id',
    args: {
        contextid,
}}])[0];

/**
 * Get all converstations a User can see.
 * @param {int} contextid
 * @param {int} userid
 * @param {int} conversationid
 * @returns {mixed}
 */
export const deleteConversation = (
    contextid,
    userid,
    conversationid,
) => fetchMany([{
    methodname: 'block_ai_interface_delete_conversation',
    args: {
        contextid,
        userid,
        conversationid,
}}])[0];
