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
 * Displays the Mobile devices report.
 *
 * @package    report_mobile
 * @copyright  2017 Juan Leyva
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

$report = optional_param('report', \report_mobile\output\devices_report::REPORT_PLATFORMS, PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);

require_login();
$context = context_system::instance();
admin_externalpage_setup('userdevicesreport', '', null, '', array('pagelayout' => 'report'));

require_capability('report/mobile:view', $context);
$PAGE->set_context($context);

$url = new moodle_url('/report/mobile/devices.php', array('report' => $report));
$PAGE->set_url($url);
$reportname = get_string('userdevicesreportdetailed', 'report_mobile');
$PAGE->set_title($reportname);
$PAGE->set_heading($reportname);
$PAGE->set_pagelayout('report');

$output = $PAGE->get_renderer('report_mobile');

// The form is the date filter.
$form = new \report_mobile\form\devices_filter($url->out(false));
if ($form->is_cancelled()) {
    redirect($url);
}
// Filter data.
$data = $form->get_data();

if ($data) {
    $timestart = $data->timestart;
    $timeend = $data->timeend;
} else {
    // Try to retrieve timestart and timeend from the URL, will be set in the URL so pagination and download work.
    $timestart = optional_param('timestart', 0, PARAM_INT);
    $timeend = optional_param('timeend', 0, PARAM_INT);
}

$url = new moodle_url('/report/mobile/devices.php', array('report' => $report, 'timestart' => $timestart, 'timeend' => $timeend));
$devicesreport = new \report_mobile\output\devices_report($report, $url, $timestart, $timeend, $download);

if (empty($download)) {
    echo $output->header();

    // Display tabs for the different reports.
    echo $output->devices_report_navigation($report);

    // Display date filters.
    $form->display();

    // Trigger a report viewed event.
    $event = \report_mobile\event\device_report_viewed::create(array('context' => $context, 'other' => array('report' => $report)));
    $event->trigger();
}

// Load the data for the report.
$devicesreport->setup_table();

if (empty($download)) {
    echo $output->render($devicesreport);
} else {
    \core\session\manager::write_close();
    $devicesreport->download();
    exit();
}

echo $output->footer();
