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

$id          = optional_param('id', 0, PARAM_INT); // Course id.
$modid       = optional_param('modid', 0, PARAM_INT); // Course module id.
$group       = optional_param('group', 0, PARAM_INT); // Group to display.
$user        = optional_param('user', 0, PARAM_INT); // User to display.
$date        = optional_param('date', 0, PARAM_INT); // Date to display.
$modaction   = optional_param('modaction', '', PARAM_ALPHAEXT); // An action as recorded in the logs.
$showcourses = optional_param('showcourses', false, PARAM_BOOL); // Whether to show courses if we're over our limit.
$showusers   = optional_param('showusers', false, PARAM_BOOL); // Whether to show users if we're over our limit.
$chooselog   = optional_param('chooselog', false, PARAM_BOOL);
$logformat   = optional_param('download', '', PARAM_ALPHA);
$logreader   = optional_param('logreader', '', PARAM_COMPONENT); // Reader which will be used for displaying logs.
$edulevel    = optional_param('edulevel', -1, PARAM_INT); // Educational level.
$origin      = optional_param('origin', '', PARAM_TEXT); // Event origin.

$params = array();

if ($id !== 0) {
    $params['id'] = $id;
}
if ($modid !== 0) {
    $params['modid'] = $modid;
}
if ($group !== 0) {
    $params['group'] = $group;
}
if ($user !== 0) {
    $params['user'] = $user;
}
if ($date !== 0) {
    $params['date'] = $date;
}
if ($modaction !== '') {
    $params['modaction'] = $modaction;
}
if ($showcourses) {
    $params['showcourses'] = $showcourses;
}
if ($showusers) {
    $params['showusers'] = $showusers;
}
if ($chooselog) {
    $params['chooselog'] = $chooselog;
}
if ($logformat !== '') {
    $params['download'] = $logformat;
}
if ($logreader !== '') {
    $params['logreader'] = $logreader;
}
if (($edulevel != -1)) {
    $params['edulevel'] = $edulevel;
}

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

$url = new moodle_url('/report/mobile/index.php', $params);
$PAGE->set_url('/report/mobile/index.php', $params);
$reportname = get_string('pluginname', 'report_mobile');
$PAGE->set_title($reportname);
$PAGE->set_heading($reportname);
$PAGE->set_pagelayout('report');

$reportlog = new report_mobile_renderable($logreader, $course, $user, $modid, $modaction, $group, $edulevel, $showcourses, $showusers,
        $chooselog, true, $url, $date, $logformat, $origin);
$readers = $reportlog->get_readers();
$output = $PAGE->get_renderer('report_mobile');

if (empty($readers)) {
    echo $output->header();
    echo $output->heading(get_string('nologreaderenabled', 'report_mobile'));
} else {
    if (!empty($chooselog)) {
            // Trigger a report viewed event.
            $event = \report_mobile\event\report_viewed::create(array('context' => $context, 'relateduserid' => $user,
                'other' => array('groupid' => $group, 'date' => $date, 'modid' => $modid, 'modaction' => $modaction,
                'logformat' => $logformat)));
            $event->trigger();

        // Delay creation of table, till called by user with filter.
        $reportlog->setup_table();

        if (empty($logformat)) {
            echo $output->header();
            $userinfo = get_string('allparticipants');
            $dateinfo = get_string('alldays');

            if ($user) {
                $u = $DB->get_record('user', array('id' => $user, 'deleted' => 0), '*', MUST_EXIST);
                $userinfo = fullname($u, has_capability('moodle/site:viewfullnames', $context));
            }
            if ($date) {
                $dateinfo = userdate($date, get_string('strftimedaydate'));
            }
            if (!empty($course) && ($course->id != SITEID)) {
                $PAGE->navbar->add("$userinfo, $dateinfo");
            }
            echo $output->render($reportlog);
        } else {
            \core\session\manager::write_close();
            $reportlog->download();
            exit();
        }
    } else {
        echo $output->header();
        echo $output->heading(get_string('chooselogs') .':');
        echo $output->render($reportlog);
    }
}

echo $output->footer();
