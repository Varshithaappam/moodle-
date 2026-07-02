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
 * A javascript module that adds the fullscreen button to a page.
 *
 * @module     local_fullscreen/button
 * @copyright  2018 University of Nottingham
 * @author     Neill Magill <neill.magill@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Log from 'core/log';
import * as Templates from 'core/templates';
import Notification from 'core/notification';
import * as KeyParser from 'local_fullscreen/keyparser';
import {getStrings} from 'core/str';
import Pending from 'core/pending';
import {setUserPreference} from 'core_user/repository';

/**
 * Store of selectors for the fullscreen button.
 */
const SELECTORS = {
    /** The attachment point. */
    attach: '#topofscroll',
    attachSecondary: '#region-main',
    /** Check if we are in a Boost based theme. */
    boost: '.local-fullscreen-boost',
    /** The class of the fullscreen button. */
    button: '.local-fullscreen',
    /** The area that scrolls in Boost. */
    scrollBoost: '#page',
};

/**
 * Store of classes used by the fullscreen button.
 */
const CLASSES = {
    /** Added to the body tag to switch fullscreen mode on. */
    toggle: 'fullscreenmode',
    /** The button is fixed relative to it's container. */
    fixed: 'fixed',
    /** The button floats at the top of the browser window. */
    'float': 'float'
};

/**
 * Store of template names used by the fullscreen button.
 */
const TEMPLATES = {
    /** The name of the template that renders the fullscreen button. */
    button: 'local_fullscreen/button'
};

/**
 * Used to stop Fullscreen mode from toggling repeatedly if the keyboard combination is held down.
 *
 * @type Boolean
 */
let KEYPRESSED = false;

/**
 * The key combination used to toggle fullscreen mode.
 *
 * @type {String}
 */
let TOGGLE;

/**
 * The distance that the page should scroll before the button floats.
 *
 * @type {number}
 */
let SCROLL = 100;

/**
 * Adds the full screen button to the page.
 *
 * @param {boolean} fullscreen Should the button will be initialised in fullscreen mode.
 * @param {String} toggle The toggle combination for the button.
 * @returns {undefined}
 */
export const init = async(fullscreen, toggle) => {
    const pendingPromise = new Pending('local_fullscreen/setup');

    Log.debug('Adding fullscreen button to the page (fullscreen=' + fullscreen + ')', 'local_fullscreen/button');

    TOGGLE = toggle;

    // Get the user's fullscreen preference.
    let variables = {
        fullscreen: false,
        toggle: TOGGLE
    };
    if (fullscreen == true) {
        document.querySelector('body').classList.add(CLASSES.toggle);
        variables.fullscreen = true;
    }

    // Find the attachment point.
    let attachPoint = document.querySelector(SELECTORS.attach);

    if (!attachPoint) {
        // Try the secondary attachment point.
        attachPoint = document.querySelector(SELECTORS.attachSecondary);
        // We expect the button to be further down the page when this attachment point is used.
        SCROLL = 205;
    }

    if (!attachPoint) {
        Log.debug('The fullscreen button is not compatible with the current theme', 'local_fullscreen/button');
        pendingPromise.resolve();
        return;
    }

    // Attach the button to the page.
    let bottonString = await Templates.render(TEMPLATES.button, variables);
    let element = document.createRange().createContextualFragment(bottonString);
    attachPoint.prepend(element);

    let button = document.querySelector(SELECTORS.button);

    // Add handlers.
    button.addEventListener('click', toggleFullscreen);
    button.addEventListener('keydown', spaceDownHandler);
    button.addEventListener('keyup', spaceUpHandler);
    document.addEventListener('keydown', keyDownHandler);
    document.addEventListener('keyup', keyUpHandler);
    document.addEventListener('scroll', scrollHandler, {capture: true});

    pendingPromise.resolve();
};

/**
 * Stop the page scrolling when the user presses space.
 *
 * @param {KeyboardEvent} event
 * @returns {undefined}
 */
const spaceDownHandler = (event) => {
    if (event.code !== 'Space') {
        return;
    }
    event.preventDefault();
};

/**
 * Toggle fullscreen mode when space bar is used while focusing on the element.
 *
 * @param {KeyboardEvent} event
 * @returns {undefined}
 */
const spaceUpHandler = (event) => {
    if (event.code !== 'Space') {
        return;
    }
    event.preventDefault();
    toggleFullscreen();
};

/**
 * Toggle fullscreen mode when Ctrl+Alt+b is pressed.
 *
 * @param {KeyboardEvent} event
 * @returns {undefined}
 */
const keyDownHandler = (event) => {
    if (KEYPRESSED === true || KeyParser.parse(event) !== TOGGLE) {
        return;
    }
    KEYPRESSED = true;
    toggleFullscreen();
};

/**
 * Lets us know the keyboard toggle combination has stopped.
 *
 * @param {KeyboardEvent} event
 * @returns {undefined}
 */
const keyUpHandler = (event) => {
    if (KEYPRESSED === false || KeyParser.parse(event) !== TOGGLE) {
        return;
    }
    KEYPRESSED = false;
};

/**
 * Changes the mode of the fullscreen button to either be relative to an element,
 * or floating at the top of the page, depending on how far a user has scrolled.
 *
 * @returns {undefined}
 */
const scrollHandler = () => {
    let button = document.querySelector(SELECTORS.button);
    let boostArea = document.querySelector(SELECTORS.scrollBoost);
    if (window.scrollY > SCROLL || boostArea.scrollTop > SCROLL) {
        button.classList.add(CLASSES.float);
    } else {
        button.classList.remove(CLASSES.float);
    }
};

/**
 * Toggles the fullscreen mode.
 *
 * @returns {undefined}
 */
const toggleFullscreen = async() => {
    const pendingPromise = new Pending('local_fullscreen/toggle');

    let bodyelement = document.querySelector('body');
    let button = document.querySelector(SELECTORS.button);

    // We do this here so that both strings are cached in the browser the first time
    // the button is used, since it seems likely the other will be used at that point.
    let turnon;
    let turnoff;
    [turnon, turnoff] = await getStrings(
        [
            {key: 'turnon', component: 'local_fullscreen', param: TOGGLE},
            {key: 'turnoff', component: 'local_fullscreen', param: TOGGLE},
        ]
    );

    let preference;
    if (bodyelement.classList.contains(CLASSES.toggle)) {
        bodyelement.classList.remove(CLASSES.toggle);
        button.setAttribute('aria-checked', 'false');
        button.setAttribute('title', turnon);
        preference = false;
    } else {
        bodyelement.classList.add(CLASSES.toggle);
        button.setAttribute('aria-checked', 'true');
        button.setAttribute('title', turnoff);
        preference = true;
    }
    updateUserPreference(preference);

    pendingPromise.resolve();
};

/**
 * Updates the user's fullscreen preference.
 *
 * @param {boolean} fullscreen
 * @returns {Promise}
 */
const updateUserPreference = (fullscreen) => {
    let preferencePromise = setUserPreference('fullscreenmode', fullscreen);
    return preferencePromise.catch(Notification.exception);
};
