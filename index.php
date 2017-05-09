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
 * Displays the Mobile app usage report.
 *
 * @package    report_mobile
 * @copyright  2017 Juan Leyva
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);

$params = array();
if (!empty($courseid)) {
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    require_login($course);
    $context = context_course::instance($course->id);
    $params['courseid'] = $courseid;
} else if (!empty($cmid)) {
    $cm = get_coursemodule_from_id(null, $cmid, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    require_login($course, false, $cm);
    $context = context_module::instance($cm->id);
    $params['cmid'] = $cmid;
} else {
    require_login();
    $context = context_system::instance();
    admin_externalpage_setup('reportmobile', '', null, '', array('pagelayout' => 'report'));
}

require_capability('report/mobile:view', $context);
$PAGE->set_context($context);

$url = new moodle_url('/report/mobile/index.php', $params);
$PAGE->set_url('/report/mobile/index.php', $params);
$reportname = get_string('pluginname', 'report_mobile');
$PAGE->set_title($reportname);
$PAGE->set_heading($reportname);
$PAGE->set_pagelayout('report');

echo $OUTPUT->header();

echo $OUTPUT->footer();
