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
require_once($CFG->libdir . '/tablelib.php');

use report_mobile\form\usage_filter;
use report_mobile\output\usage_report;

$id       = optional_param('id', 0, PARAM_INT); // Course id.
$modid    = optional_param('modid', 0, PARAM_INT); // Course module id.
$download = optional_param('download', '', PARAM_ALPHA);


if (!empty($id)) {
    $course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
    require_login($course);
    $context = context_course::instance($course->id);
} else if (!empty($modid)) {
    $cm = get_coursemodule_from_id(null, $modid, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    require_login($course, false, $cm);
    $context = context_module::instance($cm->id);
} else {
    require_login();
    $course = $DB->get_record('course', array('id' => SITEID), '*', MUST_EXIST);
    $context = context_system::instance();
    admin_externalpage_setup('reportmobile', '', null, '', array('pagelayout' => 'report'));
}

require_capability('report/mobile:view', $context);
$PAGE->set_context($context);

$url = new moodle_url('/report/mobile/index.php', array('id' => $id, 'modid' => $modid));
$PAGE->set_url($url);
$reportname = get_string('pluginname', 'report_mobile');
$PAGE->set_title($reportname);
$PAGE->set_heading($reportname);
$PAGE->set_pagelayout('report');

$readers = usage_report::get_readers();

if (empty($readers)) {
    echo $output->header();
    echo $output->heading(get_string('nologreaderenabled', 'report_mobile'));
} else {
    $output = $PAGE->get_renderer('report_mobile');

    $form = new usage_filter($url->out(false));
    if ($form->is_cancelled()) {
        redirect($url);
    }
    // Filter data.
    $data = $form->get_data();

    $logreader = null;
    // Check if the filtering form was submitted.
    if ($data) {
        $timestart = $data->timestart;
        $timeend = $data->timeend;
        $logreader = $data->logreader;
        $origin = $data->origin;
        $modaction = $data->modaction;
    } else {
        // Try to retrieve timestart and timeend from the URL, will be set in the URL so pagination and download work.
        $timestart = optional_param('timestart', 0, PARAM_INT);
        $timeend = optional_param('timeend', 0, PARAM_INT);
        if ($timestart && $timeend) {
            $origin = required_param('origin', PARAM_ALPHA);
            $modaction = required_param('modaction', PARAM_ALPHA);
            $logreader = required_param('logreader', PARAM_PLUGIN);
        }
    }

    // Log reader set it means that we receive the form or the request for download the table.
    if ($logreader) {
        $url = new moodle_url('/report/mobile/index.php',
            array(
                'id' => $id,
                'modid' => $modid,
                'timestart' => $timestart,
                'timeend' => $timeend,
                'logreader' => $logreader,
                'origin' => $origin,
                'modaction' => $modaction,
            )
        );

        $usagereport = new usage_report($id, $modid, $logreader, $origin, $timestart, $timeend, $modaction, $url, $download);
        $usagereport->setup_table();
    }

    // Trigger a report viewed event.
    //$event = \report_mobile\event\usage_report_viewed::create(array('context' => $context, 'relateduserid' => $user,
    //    'other' => array('groupid' => $group, 'date' => $date, 'modid' => $modid, 'modaction' => $modaction,
    //    'logformat' => $logformat)));
    //$event->trigger();

    if (empty($download)) {
        echo $output->header();
        // Display date filters.
        $form->display();
        if ($logreader) {
            echo $output->render($usagereport);
        }
    } else {
        \core\session\manager::write_close();
        $usagereport->download();
        exit();
    }
}

echo $output->footer();
