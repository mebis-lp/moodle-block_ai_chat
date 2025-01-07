import {makeRequest} from 'local_ai_manager/make_request';

/**
 * Get the async answer from the local_ai_manager.
 *
 * @param {string} purpose
 * @param {string} prompt
 * @param {number} contextid
 * @param {array} options
 * @returns {string}
 */
export const askLocalAiManager = async(purpose, prompt, contextid, options = []) => {
    let result = {};
    try {
        result = await makeRequest(purpose, prompt, 'block_ai_chat', contextid, options);
    } catch (error) {
        result.code = 'aiconnector';
        result.result = error.error + " " + error.message;
        // For devs.
        result.result += error.backtrace;
    }
    return result;
};
