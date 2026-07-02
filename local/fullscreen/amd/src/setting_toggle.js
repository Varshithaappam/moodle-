// This file is part of the fullscreen button plugin.
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
 * A javascript module that helps the local_fullcreen/admin/setting_toggle
 * setting record a keyboard toggle.
 *
 * @module     local_fullscreen/setting_toggle
 * @copyright  2021 University of Nottingham
 * @author     Neill Magill <neill.magill@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Str from 'core/str';
import * as Toast from 'core/toast';
import * as KeyParser from 'local_fullscreen/keyparser';
import Pending from 'core/pending';

/**
 * The control button.
 *
 * @type {HTMLElement}
 */
let button;

/**
 * Sets up a setting toggle.
 *
 * @param {String} id The id of a setting_toggle.
 */
export const init = (id) => {
    button = document.getElementById(id + '-button');
    button.addEventListener('click', buttonToggle);
    button.addEventListener('keydown', buttonKeydown);
};

/**
 * Handles a click on the change setting button.
 *
 * It will start a popup that will be listening for key presses of letters and numbers.
 *
 * @param {Event} event
 */
const buttonToggle = (event) => {
    event.preventDefault();

    if (button.getAttribute('aria-checked') === 'false') {
        setButtonActive();
    } else {
        setButtonInactive(true);
    }
};

/**
 * Handles keyboard events on the toggle button.
 *
 * @param {KeyboardEvent} event
 */
const buttonKeydown = (event) => {
    if (event.code === 'Enter' || event.code === 'Space') {
        buttonToggle(event);
    }
};

/**
 * Sets the button to listening mode.
 */
const setButtonActive = async() => {
    const pendingPromise = new Pending('local_fullscreen/activatelistener');

    // Get the strings required.
    let label;
    let message;
    [label, message] = await Str.get_strings(
        [
            {key: 'settoggle:active', component: 'local_fullscreen'},
            {key: 'togglechange:start', component: 'local_fullscreen'},
        ]
    );

    // Change the button state.
    button.innerHTML = label;
    button.setAttribute('aria-checked', 'true');

    // Add a keyboard listener.
    document.addEventListener('keydown', keyboardListener);

    Toast.add(message, {delay: 8000});

    pendingPromise.resolve();
};

/**
 * Sets the button to inactive mode.
 *
 * @param {boolean} store If true the new keystoke is saved, if false the old keystroke is restored.
 */
const setButtonInactive = async(store) => {
    const pendingPromise = new Pending('local_fullscreen/deactivatelistener');

    // Stop listening.
    document.removeEventListener('keydown', keyboardListener);

    if (store) {
        // Store the new toggle combination.
        let newValue = getDisplayElement().getAttribute('value');
        getSettingElement().setAttribute('value', newValue);
    } else {
        // Restore the old toggle combination.
        let oldValue = getSettingElement().getAttribute('value');
        getDisplayElement().setAttribute('value', oldValue);
    }

    // Get the strings required.
    let label;
    let saved;
    let discarded;
    [label, saved, discarded] = await Str.get_strings(
        [
            {key: 'settoggle', component: 'local_fullscreen'},
            {key: 'togglechange:saved', component: 'local_fullscreen'},
            {key: 'togglechange:discarded', component: 'local_fullscreen'},
        ]
    );

    // Change the button state.
    button.innerText = label;
    button.setAttribute('aria-checked', 'false');

    if (store) {
        Toast.add(saved);
    } else {
        Toast.add(discarded);
    }

    pendingPromise.resolve();
};

/**
 * Gets the display part of setting element for the control.
 *
 * @returns {HTMLElement}
 */
const getDisplayElement = () => {
    let targetID = 'view-' + button.getAttribute('data-for');
    return document.getElementById(targetID);
};

/**
 * Gets the setting element for the control.
 *
 * @returns {HTMLElement}
 */
const getSettingElement = () => {
    let targetID = button.getAttribute('data-for');
    return document.getElementById(targetID);
};

/**
 * Records the last valid keypress.
 *
 * @param {KeyboardEvent} event
 */
const keyboardListener = (event) => {
    const pendingPromise = new Pending('local_fullscreen/listener');

    event.preventDefault();
    let combination = KeyParser.parse(event);
    if (combination !== '') {
        // Store the value in the display element.
        getDisplayElement().setAttribute('value', combination);
    } else if (event.code === 'Enter') {
        setButtonInactive(true);
    } else if (event.code === 'Escape') {
        setButtonInactive(false);
    }

    pendingPromise.resolve();
};
