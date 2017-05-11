<?php
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
 * Public API of the mobile report.
 *
 * Defines the APIs used by mobile reports
 *
 * @package    report_mobile
 * @copyright  2017 Juan Leyva
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * This function extends the navigation with the report items
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course to object for the report
 * @param stdClass $context The context of the course
 */
function report_mobile_extend_navigation_course($navigation, $course, $context) {
    global $CFG;

    if (empty($CFG->enablemobilewebservice)) {
        return;
    }

    if (has_capability('report/mobile:view', $context)) {
        $url = new moodle_url('/report/mobile/index.php', array('id' => $course->id));
        $name = get_string('pluginname', 'report_mobile');
        $navigation->add($name, $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', ''));
    }
}

/**
 * This function extends the module navigation with the report items
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $cm The course module object
 */
function report_mobile_extend_navigation_module($navigation, $cm) {
    global $CFG;

    if (empty($CFG->enablemobilewebservice)) {
        return;
    }

    if (has_capability('report/mobile:view', context_module::instance($cm->id))) {
        $url = new moodle_url('/report/mobile/index.php', array('modid' => $cm->id));
        $name = get_string('pluginname', 'report_mobile');
        $navigation->add($name, $url, navigation_node::TYPE_SETTING, null, null, null);
    }
}

/**
 * Callback to verify if the given instance of store is supported by this report or not.
 *
 * @param string $instance store instance.
 *
 * @return bool returns true if the store is supported by the report, false otherwise.
 */
function report_mobile_supports_logstore($instance) {
    if ($instance instanceof \core\log\sql_internal_table_reader) {
        return true;
    }
    return false;
}
