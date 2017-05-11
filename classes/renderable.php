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
use core\log\manager;

/**
 * Report mobile renderable class.
 *
 * @package    report_mobile
 * @copyright  2017 Juan Leyva
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_mobile_renderable extends report_log_renderable {

    /** @var string origin to filter event origin */
    public $origin;

    /** @var bool whether the only services enabled are mobile ones */
    public $mobileonly;

    /**
     * Constructor.
     *
     * @param string $logreader (optional)reader pluginname from which logs will be fetched.
     * @param stdClass|int $course (optional) course record or id
     * @param int $userid (optional) id of user to filter records for.
     * @param int|string $modid (optional) module id or site_errors for filtering errors.
     * @param string $action (optional) action name to filter.
     * @param int $groupid (optional) groupid of user.
     * @param int $edulevel (optional) educational level.
     * @param bool $showcourses (optional) show courses.
     * @param bool $showusers (optional) show users.
     * @param bool $showreport (optional) show report.
     * @param bool $showselectorform (optional) show selector form.
     * @param moodle_url|string $url (optional) page url.
     * @param int $date date (optional) timestamp of start of the day for which logs will be displayed.
     * @param string $logformat log format.
     */
    public function __construct($logreader, $course = 0, $userid = 0, $modid = 0, $action = "", $groupid = 0, $edulevel = -1,
            $showcourses = false, $showusers = false, $showreport = true, $showselectorform = true, $url = "", $date = 0,
            $logformat='showashtml', $origin = '') {

        $this->origin = $origin;

        parent::__construct($logreader, $course, $userid, $modid, $action, $groupid, $edulevel,
            $showcourses, $showusers, $showreport, $showselectorform, $url, $date,
            $logformat, 0, '', 'timecreated ASC');
    }

    /**
     * Get a list of enabled sql_reader objects/name
     *
     * @param bool $nameonly if true only reader names will be returned.
     * @return array core\log\sql_reader object or name.
     */
    public function get_readers($nameonly = false) {
        if (!isset($this->logmanager)) {
            $this->logmanager = get_log_manager();
        }

        $readers = $this->logmanager->get_readers('core\log\sql_internal_table_reader');
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
    protected function check_mobile_services() {
        global $DB;

        if (isset($this->mobileonly)) {
            return $this->mobileonly;
        }
        // Check services to see if there is only Mobile one enabled.
        $this->mobileonly = true;
        $services = $DB->get_records('external_services');
        foreach ($services as $service) {
            if (!$service->enabled) {
                continue;
            }
            if ($service->shortname != MOODLE_OFFICIAL_MOBILE_SERVICE && $service->shortname != 'local_mobile') {
                $this->mobileonly = false;
                break;
            }
        }
    }

    /**
     * Return list of origins.
     *
     * @return array list of origins.
     */
    public function get_origin_options() {
        global $DB;

        $this->check_mobile_services();
        $ret = array();
        $ret[''] = get_string('allsources', 'report_mobile');
        $ret['ws'] = $this->mobileonly ? get_string('mobileapp', 'report_mobile') : get_string('web', 'report_mobile');
        $ret['web'] = get_string('web', 'report_mobile');
        return $ret;
    }

    /**
     * Setup table log.
     */
    public function setup_table() {
        $readers = $this->get_readers();
        $this->check_mobile_services();

        $filter = new \stdClass();
        if (!empty($this->course)) {
            $filter->courseid = $this->course->id;
        } else {
            $filter->courseid = 0;
        }

        $filter->userid = $this->userid;
        $filter->modid = $this->modid;
        $filter->groupid = $this->get_selected_group();
        $filter->logreader = $readers[$this->selectedlogreader];
        $filter->edulevel = $this->edulevel;
        $filter->action = $this->action;
        $filter->date = $this->date;
        $filter->orderby = $this->order;
        $filter->origin = $this->origin;
        $filter->mobileonly = $this->mobileonly;

        // If showing site_errors.
        if ('site_errors' === $this->modid) {
            $filter->siteerrors = true;
            $filter->modid = 0;
        }

        $this->tablelog = new report_mobile_table_log('report_mobile', $filter);
        $this->tablelog->define_baseurl($this->url);
        $this->tablelog->is_downloadable(true);
        $this->tablelog->show_download_buttons_at(array(TABLE_P_BOTTOM));
    }

    /**
     * Download logs in specified format.
     */
    public function download() {
        $filename = 'logs_' . userdate(time(), get_string('backupnameformat', 'langconfig'), 99, false);
        $this->tablelog->is_downloading($this->logformat, $filename);
        $this->tablelog->out($this->perpage, false);
    }
}
