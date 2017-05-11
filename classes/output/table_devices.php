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
 * Table user mobile devices for displaying user devices.
 *
 * @package    report_mobile
 * @copyright  2017 Juan Leyva
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_mobile\output;
defined('MOODLE_INTERNAL') || die;

use table_sql;

/**
 * Table user mobile devices class for displaying user devices.
 *
 * @package    report_mobile
 * @copyright  2017 Juan Leyva
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class table_devices extends table_sql {

    /** @var array sql query params */
    protected $params;

    /** @var string sql query */
    protected $devquery;

    /**
     * Sets up the table_devices parameters.
     *
     * @param string $uniqueid unique id of form.
     * @param stdClass $filterparams (optional) filter params.
     */
    public function __construct($uniqueid, $filterparams = null) {
        global $DB;

        parent::__construct($uniqueid);

        $this->set_attribute('class', 'reportuserdevicesreport generaltable generalbox');

        $where = '';
        $this->params = array();
        if ($filterparams->timestart && $filterparams->timeend) {
            $where = 'WHERE u.timecreated > :timestart AND u.timemodified < :timeend';
            $this->params['timestart'] = $filterparams->timestart;
            $this->params['timeend'] = $filterparams->timeend;
        }

        // Different query depending on the required device report.
        if ($filterparams->reporttype == devices_report::REPORT_PLATFORMS) {
            $cols = array('platform');
            $headers = array(get_string('platform', 'report_mobile'));

            $this->devquery = 'SELECT platform, COUNT(DISTINCT userid) AS totalcount
                                 FROM {user_devices} u ' . $where . '
                             GROUP BY platform
                             ORDER BY totalcount DESC';
        } else if ($filterparams->reporttype == devices_report::REPORT_MODELS) {
            $cols = array('model');
            $headers = array(get_string('model', 'report_mobile'));

            $this->devquery = 'SELECT model, COUNT(DISTINCT userid) AS totalcount
                                 FROM {user_devices} u ' . $where . '
                             GROUP BY model
                             ORDER BY totalcount DESC';
        } else if ($filterparams->reporttype == devices_report::REPORT_VERSIONS) {
            $cols = array('version');
            $headers = array(get_string('version', 'report_mobile'));

            $version = $DB->sql_concat('platform', "' '" , 'version');
            $this->devquery = 'SELECT ' . $version . ' as version, COUNT(DISTINCT userid) AS totalcount
                                 FROM {user_devices} u ' . $where . '
                             GROUP BY platform, version
                             ORDER BY totalcount DESC';
        }

        $cols[] = 'totalcount';
        $headers[] = get_string('total');

        $this->define_columns($cols);
        $this->define_headers($headers);
        $this->collapsible(false);
        $this->sortable(false);
        $this->pageable(false);
    }

    /**
     * Query the reader. Store results in the object for use by build_table.
     *
     * @param int $pagesize size of page for paginated displayed table.
     * @param bool $useinitialsbar do you want to use the initials bar.
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $DB;
        $this->rawdata = $DB->get_records_sql($this->devquery, $this->params);
    }
}
