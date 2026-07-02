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
 * Parses keyboard events for combinations.
 *
 * @module     local_fullscreen/keyparser
 * @copyright  2021 University of Nottingham
 * @author     Neill Magill <neill.magill@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Parses potentially valid toggle keystrokes.
 *
 * It converts them into a string that lists the modifier keys followed by the
 * letter, each key is separated by a plus sign, for example: Ctrl + Alt + b
 *
 * We require at least one modifier key and one letter for a valid combination.
 *
 * @param {KeyboardEvent} event
 * @returns {string} The string of the combination or an empty string if it is invalid.
 */
export const parse = (event) => {
    let toggle = [];

    // Check for modifier keys.
    if (event.ctrlKey) {
        toggle.push('Ctrl');
    }
    if (event.altKey) {
        toggle.push('Alt');
    }
    if (event.shiftKey) {
        toggle.push('Shift');
    }
    if (event.metaKey) {
        toggle.push('Meta');
    }

    if (toggle.length === 0) {
        // No modifier key pressed, so ignore.
        return '';
    }

    if (event.code && event.code.slice(0, 3) === 'Key') {
        // Get the key pressed.
        toggle.push(event.code.slice(3).toLowerCase());

        // Convert it to a string.
        let toggleString = toggle.toString();
        return toggleString.replaceAll(',', ' + ');
    }

    // Not a valid key combination.
    return '';
};
