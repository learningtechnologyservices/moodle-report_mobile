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
 * Mobile report renderer.
 *
 * @package    report_mobile
 * @copyright  2017 Juan Leyva
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

use report_mobile\output\devices_report;

/**
 * Report mobile renderer's for printing reports.
 *
 * @package    report_mobile
 * @copyright  2017 Juan Leyva
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_mobile_renderer extends plugin_renderer_base {

    /**
     * Renders a bar chart.
     *
     * @param \report_mobile\chartjs\chart_bar $chart The chart.
     * @return string.
     */
    public function render_chart_bar(\report_mobile\chartjs\chart_bar $chart) {
        return $this->render_chart($chart);
    }

    /**
     * Renders a line chart.
     *
     * @param \report_mobile\chartjs\chart_line $chart The chart.
     * @return string.
     */
    public function render_chart_line(\report_mobile\chartjs\chart_line $chart) {
        return $this->render_chart($chart);
    }

    /**
     * Renders a pie chart.
     *
     * @param \report_mobile\chartjs\chart_pie $chart The chart.
     * @return string.
     */
    public function render_chart_pie(\report_mobile\chartjs\chart_pie $chart) {
        return $this->render_chart($chart);
    }

    /**
     * Renders a chart.
     *
     * @param \report_mobile\chartjs\chart_base $chart The chart.
     * @param bool $withtable Whether to include a data table with the chart.
     * @return string.
     */
    public function render_chart(\report_mobile\chartjs\chart_base $chart, $withtable = true) {
        $id = 'chart' . uniqid();
        // TODO Handle the canvas in the output module rather than here.
        $canvas = html_writer::tag('canvas', '', ['id' => $id]);
        $js = "require(['report_mobile/chart_builder', 'report_mobile/chart_output'], function(Builder, Output) {
            Builder.make(" . json_encode($chart) . ").then(function(ChartInst) {
                new Output('#" . $id . "', ChartInst);
            });
        });";
        $this->page->requires->js_init_code($js, true);
        return $canvas;
    }

    /**
     * Render log report page.
     *
     * @param report_mobile_renderable $reportlog object of report_mobile.
     */
    protected function render_report_mobile(report_mobile_renderable $reportlog) {
        if (empty($reportlog->selectedlogreader)) {
            echo $this->output->notification(get_string('nologreaderenabled', 'report_mobile'), 'notifyproblem');
            return;
        }
        if ($reportlog->showselectorform) {
            $this->report_selector_form($reportlog);
        }

        if ($reportlog->showreport) {
            $reportlog->tablelog->out($reportlog->perpage, true);
        }
    }

    /**
     * Render devices report.
     *
     * @param report_mobile_renderable $reportlog object of report_mobile.
     */
    protected function render_devices_report(\report_mobile\output\devices_report $report) {
        $report->devicestable->out(0, true);
    }

    /**
     * Render the navigation tabs for the completion page.
     *
     * @param int|stdClass $courseorid the course object or id.
     * @param String $page the tab to focus.
     * @return string html
     */
    public function devices_report_navigation($report) {
        $tabs = array();
        $tabs[] = new tabobject(
            devices_report::REPORT_PLATFORMS,
            new moodle_url('/report/mobile/devices.php', ['report' => devices_report::REPORT_PLATFORMS]),
            new lang_string('platform', 'report_mobile')
        );

        $tabs[] = new tabobject(
            devices_report::REPORT_MODELS,
            new moodle_url('/report/mobile/devices.php', ['report' => devices_report::REPORT_MODELS]),
            new lang_string('model', 'report_mobile')
        );

        $tabs[] = new tabobject(
            devices_report::REPORT_VERSIONS,
            new moodle_url('/report/mobile/devices.php', ['report' => devices_report::REPORT_VERSIONS]),
            new lang_string('version', 'report_mobile')
        );
        return $this->tabtree($tabs, $report);
    }

    /**
     * This function is used to generate and display selector form
     *
     * @param report_mobile_renderable $reportlog log report.
     */
    public function report_selector_form(report_mobile_renderable $reportlog) {
        echo html_writer::start_tag('form', array('class' => 'logselecform', 'action' => $reportlog->url, 'method' => 'get'));
        echo html_writer::start_div();
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'chooselog', 'value' => '1'));
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'showusers', 'value' => $reportlog->showusers));
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'showcourses',
            'value' => $reportlog->showcourses));

        $readers = $reportlog->get_readers(true);
        if (empty($readers)) {
            $readers = array(get_string('nologreaderenabled', 'report_mobile'));
        }
        $url = fullclone ($reportlog->url);
        $url->remove_params(array('logreader'));
        $select = new single_select($url, 'logreader', $readers, $reportlog->selectedlogreader, null);
        $select->set_label(get_string('selectlogreader', 'report_log'));
        echo $this->output->render($select);

        $selectedcourseid = empty($reportlog->course) ? 0 : $reportlog->course->id;

        // Add course selector.
        $sitecontext = context_system::instance();
        $courses = $reportlog->get_course_list();
        if (!empty($courses) && $reportlog->showcourses) {
            echo html_writer::label(get_string('selectacourse'), 'menuid', false, array('class' => 'accesshide'));
            echo html_writer::select($courses, "id", $selectedcourseid, null);
        } else {
            $courses = array();
            $courses[$selectedcourseid] = get_course_display_name_for_list($reportlog->course) . (($selectedcourseid == SITEID) ?
                ' (' . get_string('site') . ') ' : '');
            echo html_writer::label(get_string('selectacourse'), 'menuid', false, array('class' => 'accesshide'));
            echo html_writer::select($courses, "id", $selectedcourseid, false);
            // Check if user is admin and this came because of limitation on number of courses to show in dropdown.
            if (has_capability('report/log:view', $sitecontext)) {
                $a = new stdClass();
                $a->url = new moodle_url('/report/log/index.php', array('chooselog' => 0,
                    'group' => $reportlog->get_selected_group(), 'user' => $reportlog->userid,
                    'id' => $selectedcourseid, 'date' => $reportlog->date, 'modid' => $reportlog->modid,
                    'showcourses' => 1, 'showusers' => $reportlog->showusers));
                $a->url = $a->url->out(false);
                print_string('logtoomanycourses', 'moodle', $a);
            }
        }

        // Add origin.
        $origin = $reportlog->get_origin_options();
        echo html_writer::label(get_string('origin', 'report_mobile'), 'menuorigin', false, array('class' => 'accesshide'));
        echo html_writer::select($origin, 'origin', $reportlog->origin, false);

        // Add group selector.
        $groups = $reportlog->get_group_list();
        if (!empty($groups)) {
            echo html_writer::label(get_string('selectagroup'), 'menugroup', false, array('class' => 'accesshide'));
            echo html_writer::select($groups, "group", $reportlog->groupid, get_string("allgroups"));
        }

        // Add user selector.
        $users = $reportlog->get_user_list();

        if ($reportlog->showusers) {
            echo html_writer::label(get_string('selctauser'), 'menuuser', false, array('class' => 'accesshide'));
            echo html_writer::select($users, "user", $reportlog->userid, get_string("allparticipants"));
        } else {
            $users = array();
            if (!empty($reportlog->userid)) {
                $users[$reportlog->userid] = $reportlog->get_selected_user_fullname();
            } else {
                $users[0] = get_string('allparticipants');
            }
            echo html_writer::label(get_string('selctauser'), 'menuuser', false, array('class' => 'accesshide'));
            echo html_writer::select($users, "user", $reportlog->userid, false);
            $a = new stdClass();
            $a->url = new moodle_url('/report/log/index.php', array('chooselog' => 0,
                'group' => $reportlog->get_selected_group(), 'user' => $reportlog->userid,
                'id' => $selectedcourseid, 'date' => $reportlog->date, 'modid' => $reportlog->modid,
                'showusers' => 1, 'showcourses' => $reportlog->showcourses));
            $a->url = $a->url->out(false);
            print_string('logtoomanyusers', 'moodle', $a);
        }

        // Add date selector.
        $dates = $reportlog->get_date_options();
        echo html_writer::label(get_string('date'), 'menudate', false, array('class' => 'accesshide'));
        echo html_writer::select($dates, "date", $reportlog->date, get_string("alldays"));

        // Add activity selector.
        $activities = $reportlog->get_activities_list();
        echo html_writer::label(get_string('activities'), 'menumodid', false, array('class' => 'accesshide'));
        echo html_writer::select($activities, "modid", $reportlog->modid, get_string("allactivities"));

        // Add actions selector.
        echo html_writer::label(get_string('actions'), 'menumodaction', false, array('class' => 'accesshide'));
        echo html_writer::select($reportlog->get_actions(), 'modaction', $reportlog->action, get_string("allactions"));

        // Add edulevel.
        $edulevel = $reportlog->get_edulevel_options();
        echo html_writer::label(get_string('edulevel'), 'menuedulevel', false, array('class' => 'accesshide'));
        echo html_writer::select($edulevel, 'edulevel', $reportlog->edulevel, false);

        // Add reader option.
        // If there is some reader available then only show submit button.
        $readers = $reportlog->get_readers(true);
        if (!empty($readers)) {
            if (count($readers) == 1) {
                $attributes = array('type' => 'hidden', 'name' => 'logreader', 'value' => key($readers));
                echo html_writer::empty_tag('input', $attributes);
            } else {
                echo html_writer::label(get_string('selectlogreader', 'report_mobile'), 'menureader', false,
                        array('class' => 'accesshide'));
                echo html_writer::select($readers, 'logreader', $reportlog->selectedlogreader, false);
            }
            echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('gettheselogs')));
        }
        echo html_writer::end_div();
        echo html_writer::end_tag('form');
    }
}

