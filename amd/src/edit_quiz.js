// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Drag and drop reordering of the questions on the edit quiz page.
 *
 * @module     mod_groupquiz/edit_quiz
 * @copyright  2026 Carnegie Mellon University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import SortableList from 'core/sortable_list';
import Pending from 'core/pending';
import {getString} from 'core/str';

const SELECTORS = {
    list: '.groupquizbox .questionlist',
    handle: '.dragquestion',
    // The list holds a copy of the dragged question until the drop has been handled.
    questions: ':scope > li[data-questionid]:not(.sortable-list-is-dragged)',
    status: '#editstatus',
};

/**
 * Show the outcome of a save in the status box, then hide it again.
 *
 * @param {Boolean} success Whether the new order was saved
 * @param {String} message The text to show
 */
const showStatus = (success, message) => {
    const status = document.querySelector(SELECTORS.status);
    if (!status) {
        return;
    }
    status.textContent = message;
    status.classList.remove('rtqhiddenstatus', 'rtqsuccessstatus', 'rtqerrorstatus');
    status.classList.add(success ? 'rtqsuccessstatus' : 'rtqerrorstatus');
    setTimeout(() => {
        status.classList.remove('rtqsuccessstatus', 'rtqerrorstatus');
        status.classList.add('rtqhiddenstatus');
    }, 2000);
};

/**
 * Post the current question order to edit.php.
 *
 * @param {Number} cmid The course module id
 * @param {HTMLElement} list The question list
 */
const saveOrder = async(cmid, list) => {
    const pending = new Pending('mod_groupquiz/edit_quiz:saveorder');
    const questionorder = Array.from(list.querySelectorAll(SELECTORS.questions)).map((item) => item.dataset.questionid);
    const params = new URLSearchParams({
        sesskey: M.cfg.sesskey,
        cmid: cmid,
        questionorder: questionorder.join(','),
        action: 'dragdrop',
    });
    let success = false;
    try {
        const response = await fetch(M.cfg.wwwroot + '/mod/groupquiz/edit.php', {method: 'POST', body: params});
        const result = await response.json();
        success = response.ok && result.success === 'true';
    } catch (error) {
        success = false;
    }
    showStatus(success, await getString(success ? 'success' : 'error', 'core'));
    pending.resolve();
};

/**
 * Make the questions on the edit quiz page sortable by dragging their handle.
 *
 * @param {Number} cmid The course module id
 */
export const init = (cmid) => {
    const list = document.querySelector(SELECTORS.list);
    if (!list) {
        return;
    }
    new SortableList(list, {moveHandlerSelector: SELECTORS.handle});
    list.addEventListener(SortableList.EVENTS.elementDrop, (e) => {
        if (e.detail.positionChanged) {
            saveOrder(cmid, list);
        }
    });
};
