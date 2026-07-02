<?php
// This file is part of the University of Nottingham Functions Library.
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
 * Adds an admin page for the plugin.
 *
 * @package    local_fullscreen
 * @author     Neill Magill <neill.magill@nottingham.ac.uk>
 * @copyright  2021 University of Nottingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_fullscreen\admin\setting_toggle;

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_fullscreen_settings',
        get_string('pluginsettings', 'local_fullscreen')
    );
    $ADMIN->add('localplugins', $settings);

    $settings->add(
        new setting_toggle(
           'local_fullscreen/toggle',
           get_string('toggle', 'local_fullscreen'),
           get_string('toggle_description', 'local_fullscreen'),
           'Ctrl + Alt + b'
       )
    );
}
