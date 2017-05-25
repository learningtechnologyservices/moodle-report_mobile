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

use core\log\manager;
use stdClass;
use renderable;

/**
 * Report mobile renderable class.
 *
 * @package    report_mobile
 * @copyright  2017 Juan Leyva
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usage_report implements renderable {

    /** @var int to identify the type of report */
    const REPORT_TIMELINE = 1;

    /** @var int to identify the type of report */
    const REPORT_MODULES = 2;

    public $id;
    public $modid;
    public $logreader;

    /** @var string origin to filter event origin */
    public $origin;
    public $timestart;
    public $timeend;
    public $modaction;
    public $url;
    public $download;

    /**
     * Constructor.
     *
     * @param int $report the report id
     * @param stdClass|int $course (optional) course record or id
     * @param int|string $modid (optional) module id or site_errors for filtering errors.
     * @param string $logreader (optional)reader pluginname from which logs will be fetched.
     * @param string $action (optional) action name to filter.
     * @param moodle_url|string $url (optional) page url.
     * @param string $logformat log format.
     */
    public function __construct($report, $id, $modid, $logreader, $origin, $timestart, $timeend, $modaction, $url, $download) {

        $this->report = $report;
        $this->id = $id;
        $this->modid = $modid;
        $this->logreader = $logreader;
        $this->origin = $origin;
        $this->timestart = $timestart;
        $this->timeend = $timeend;
        $this->modaction = $modaction;
        $this->url = $url;
        $this->download = $download;
    }

    /**
     * Get a list of enabled sql_reader objects/name
     *
     * @param bool $nameonly if true only reader names will be returned.
     * @return array core\log\sql_reader object or name.
     */
    public static function get_readers($nameonly = false) {
        $logmanager = get_log_manager();
        $readers = $logmanager->get_readers('core\log\sql_internal_table_reader');

        if ($nameonly) {
            foreach ($readers as $pluginname => $reader) {
                $readers[$pluginname] = $reader->get_name();
            }
        }
        return $readers;
    }

    /**
     * Check whether mobile services are the only ones enabled.
     */
    public static function check_mobile_services() {
        global $DB;

        // Check services to see if there is only Mobile one enabled.
        $mobileonly = true;
        $services = $DB->get_records('external_services');
        foreach ($services as $service) {
            if (!$service->enabled) {
                continue;
            }
            if ($service->shortname != MOODLE_OFFICIAL_MOBILE_SERVICE && $service->shortname != 'local_mobile') {
                $mobileonly = false;
                break;
            }
        }
        return $mobileonly;
    }

    /**
     * Return list of actions.
     *
     * @return array list of action options.
     */
    public static function get_action_options() {
        $actions = array(
            '' => get_string('allchanges'),
            'c' => get_string('create'),
            'r' => get_string('view'),
            'u' => get_string('update'),
            'd' => get_string('delete'),
        );
        return $actions;
    }

    /**
     * Return list of origins.
     *
     * @return array list of origins.
     */
    public static function get_origin_options() {

        $mobileonly = self::check_mobile_services();
        $ret = array();
        $ret[''] = get_string('allsources', 'report_mobile');
        $ret['ws'] = $mobileonly ? get_string('mobileapp', 'report_mobile') : get_string('web', 'report_mobile');
        $ret['web'] = get_string('web', 'report_mobile');
        return $ret;
    }

    /**
     * Setup table log.
     */
    public function setup_table() {

        $filter = new stdClass;
        $filter->courseid = $this->id;
        $filter->modid = $this->modid;
        $filter->logreader = $this->logreader;
        $filter->origin = $this->origin;
        $filter->timestart = $this->timestart;
        $filter->timeend = $this->timeend;
        $filter->action = $this->modaction;
        $filter->download = $this->download;
        $filter->mobileonly = self::check_mobile_services();

        if ($this->report == self::REPORT_TIMELINE) {
            $this->reporttable = new table_usage('report_mobile', $filter);
        } else {
            $this->reporttable = new table_usage_mod('report_mobile', $filter);
        }
        $this->reporttable->define_baseurl($this->url);
        $this->reporttable->is_downloadable(true);
        $this->reporttable->show_download_buttons_at(array(TABLE_P_BOTTOM));
    }

    /**
     * Download logs in specified format.
     */
    public function download() {
        $filename = 'mobile_usage_' . userdate(time(), get_string('backupnameformat', 'langconfig'), 99, false);
        $this->reporttable->is_downloading($this->download, $filename);
        $this->reporttable->out(0, false);
    }
}
