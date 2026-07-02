<?php
// This file is part of the local fullscreen plugin for Moodle
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

namespace local_fullscreen\admin;

/**
 * An admin control that can be used to record and store a keyboard combination.
 *
 * It is used to allow admins to customise the keyboard toggle for the fullscreen button.
 *
 * @package    local_fullscreen
 * @copyright  2021 University of Nottingham
 * @author     Neill Magill <neill.magill@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_toggle extends \admin_setting {
    /**
     * Returns the current value of this setting.
     *
     * @return mixed array or string depending on instance, NULL means not set yet
     */
    public function get_setting() {
        return $this->config_read($this->name);
    }

    /**
     * Store the new setting.
     *
     * @param mixed $data string or array, must not be NULL
     * @return string empty string if ok, string error message otherwise
     */
    public function write_setting($data) {
        $validated = $this->validate($data);
        if ($validated !== true) {
            return $validated;
        }
        return ($this->config_write($this->name, $data) ? '' : get_string('errorsetting', 'admin'));
    }

    /**
     * Validates the value of the setting.
     *
     * @param string $data
     * @return mixed true if ok string if error found
     */
    public function validate($data) {
        $parts = explode(' + ', $data);
        if (count($parts) < 2) {
            // We expect at least 1 modifier and 1 Key press.
            return get_string('validateerror', 'admin');
        }

        // The values for the keyboard modifier keys.
        $modifiers = ['Alt', 'Ctrl', 'Shift', 'Meta'];

        for ($i = 0; $i < count($parts) - 1; $i++) {
            if (!in_array($parts[$i], $modifiers)) {
                // Not a valid modifier key.
                return get_string('validateerror', 'admin');
            }
        }

        $key = array_pop($parts);

        if (!preg_match('/^[a-z]$/', $key)) {
            // The key is not a lowercase letter.
            return get_string('validateerror', 'admin');
        }

        return true;
    }

    /**
     * Return part of form with setting.
     *
     * @param mixed $data array or string depending on setting
     * @param string $query
     * @return string
     */
    public function output_html($data, $query='') {
        global $OUTPUT;

        $default = $this->get_defaultsetting();
        $context = (object) [
            'size' => 25,
            'id' => $this->get_id(),
            'name' => $this->get_full_name(),
            'readonly' => $this->is_readonly(),
            'value' => $data,
        ];
        $element = $OUTPUT->render_from_template('local_fullscreen/setting_toggle', $context);

        return format_admin_setting($this, $this->visiblename, $element, $this->description, true, '', $default, $query);
    }
}
