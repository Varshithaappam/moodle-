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

namespace local_fullscreen;

use core\hook\output\before_footer_html_generation;
use core\hook\output\before_http_headers;

/**
 * Callback handlers for the plugin.
 *
 * @package    local_fullscreen
 * @copyright  2025 University of Nottingham
 * @author     Neill Magill <neill.magill@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Adds some extra classes to help identify the mode that the fullscreen button should work in.
     *
     * @param before_http_headers $hook
     * @return void
     */
    public static function before_http_headers(before_http_headers $hook): void {
        global $PAGE;
        if (!self::add_to_page()) {
            return;
        }

        $class = 'local-fullscreen-boost';

        if ($PAGE->theme->name === 'classic' || in_array('classic', $PAGE->theme->parents)) {
            $class = 'local-fullscreen-classic';
        }

        $PAGE->add_body_class($class);
    }

    /**
     * Adds the full screen button to the page.
     *
     * @param before_footer_html_generation $hook
     * @return void
     */
    public static function before_footer_html_generation(before_footer_html_generation $hook): void {
        global $PAGE;
        if (!self::add_to_page()) {
            return;
        }
        $params = [
            'fullscreen' => get_user_preferences('fullscreenmode', false),
            'toggle' => get_config('local_fullscreen', 'toggle') ?? 'Ctrl + Alt + b',
        ];
        $PAGE->requires->js_call_amd('local_fullscreen/button', 'init', $params);
    }

    /**
     * Tests if the fullscreen button should be added to the page.
     *
     * @return bool
     */
    protected static function add_to_page(): bool {
        global $PAGE;

        if (during_initial_install()) {
            // Do not add during installation.
            return false;
        }

        if (!get_config('local_fullscreen', 'version')) {
            // Do not add if the plugin is not installed.
            return false;
        }

        // Page layouts that should not have the fullscreen button.
        $pagelayout = [
            'login',
            'embedded',
            'popup',
            'redirect',
            'frametop',
            'maintenance',
            'mydashboard',
            'mycourses',
        ];
        $excludelayout = in_array($PAGE->pagelayout, $pagelayout, true);

        // Page types that should not have the fullscreen button.
        $pagetype = [
            'mod-quiz-attempt',
            'mod-quiz-review',
            'mod-quiz-summary',
        ];
        $excludetype = in_array($PAGE->pagetype, $pagetype, true);

        if (CLI_SCRIPT || AJAX_SCRIPT || $excludelayout || $excludetype) {
            return false;
        }
        return true;
    }
}
