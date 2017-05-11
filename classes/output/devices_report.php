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

namespace report_mobile\output;
defined('MOODLE_INTERNAL') || die;

use renderable;
use moodle_url;

/**
 * Report mobile renderable class.
 *
 * @package    report_mobile
 * @copyright  2017 Juan Leyva
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class devices_report implements renderable {

     /** @var int to identify the type of report */
    const REPORT_PLATFORMS = 1;

    /** @var int to identify the type of report */
    const REPORT_MODELS = 2;

     /** @var int to identify the type of report */
    const REPORT_VERSIONS = 3;

    /** @var in type of report to display */
    public $reporttype;

    /** @var in time start to filter the report */
    protected $timestart;

    /** @var in time end to filter the report */
    protected $timeend;

    /**
     * Constructor.
     *
     * @param string $type type of report to display
     * @param int $timestart time start to filter the report
     * @param int $timeend time end to filter the report
     * @param string $downloadformat format for download
     */
    public function __construct($type, moodle_url $url, $timestart, $timeend, $downloadformat) {
        $this->reporttype = $type;
        $this->url = $url;
        $this->timestart = $timestart;
        $this->timeend = $timeend;
        $this->downloadformat = $downloadformat;
    }

    /**
     * Setup table log.
     */
    public function setup_table() {

        $filter = new \stdClass();
        $filter->reporttype = $this->reporttype;
        $filter->timestart = $this->timestart;
        $filter->timeend = $this->timeend;

        $this->devicestable = new table_devices('devices_table', $filter);
        $this->devicestable->define_baseurl($this->url);
        $this->devicestable->is_downloadable(true);
        $this->devicestable->show_download_buttons_at(array(TABLE_P_BOTTOM));
    }

    /**
     * Download logs in specified format.
     */
    public function download() {
        $filename = 'devices_' . userdate(time(), get_string('backupnameformat', 'langconfig'), 99, false);
        $this->devicestable->is_downloading($this->downloadformat, $filename);
        $this->devicestable->out(0, false);
    }
}
