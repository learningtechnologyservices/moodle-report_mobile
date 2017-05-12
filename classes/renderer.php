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
     * @param \report_mobile\output\usage_report $reportlog object of report_mobile.
     */
    protected function render_usage_report(\report_mobile\output\usage_report $report) {
        $report->reporttable->display_chart_and_table(0, false, '', $this);
    }

    /**
     * Render devices report.
     *
     * @param \report_mobile\output\usage_report $reportlog object of report_mobile.
     */
    protected function render_devices_report(\report_mobile\output\devices_report $report) {
        $report->devicestable->display_chart_and_table(0, false, '', $this);
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
}

