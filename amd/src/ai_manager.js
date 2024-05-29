import {makeRequest} from 'local_ai_manager/make_request';
import {exception as displayException} from 'core/notification';

/**
 * Get the async answer from the local_ai_manager.
 *
 * @param {string} purpose
 * @param {string} prompt
 * @param {array} options
 * @returns {string}
 */
export const askLocalAiManager = async (purpose, prompt, options = []) => {
    let result;
    try {
        result = await makeRequest(purpose, prompt, JSON.stringify(options));
    } catch (error) {
        displayException(error);
    }
    return result;
};
