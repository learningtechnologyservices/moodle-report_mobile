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
 * Table mobile for displaying logs.
 *
 * @package    report_mobile
 * @copyright  2017 Juan Leyva
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_mobile\output;
defined('MOODLE_INTERNAL') || die;

use table_sql;

/**
 * Table mobile class for displaying logs.
 *
 * @package    report_mobile
 * @copyright  2017 Juan Leyva
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class table_usage extends table_sql {

    /** @var array list of user fullnames shown in report */
    private $userfullnames = array();

    /**
     * @deprecated since Moodle 2.9 MDL-48595 - please do not use this argument any more.
     * @todo MDL-49291 This will be deleted in 3.1
     * @var array list of course short names shown in report.
     */
    private $courseshortnames = array();

    /** @var array list of context name shown in report */
    private $contextname = array();

    /** @var stdClass filters parameters */
    private $filterparams;

    /**
     * Sets up the table_log parameters.
     *
     * @param string $uniqueid unique id of form.
     * @param stdClass $filterparams (optional) filter params.
     */
    public function __construct($uniqueid, $filterparams = null) {
        parent::__construct($uniqueid);

        $this->set_attribute('class', 'reportlog generaltable generalbox');
        $this->filterparams = $filterparams;

        $cols = array('timeperiod');
        $headers = array(get_string('date'));

        if (empty($filterparams->origin) || $filterparams->origin == 'web') {
            $cols[] = 'web';
            $headers[] = get_string('web', 'report_mobile');
        }
        if (empty($filterparams->origin) || $filterparams->origin == 'ws') {
            $cols[] = 'ws';
            $str = $filterparams->mobileonly ? get_string('mobileapp', 'report_mobile') : get_string('ws', 'report_mobile');
            $headers[] = $str;
        }

        $this->filterparams->timestart = usergetmidnight($this->filterparams->timestart);
        $this->filterparams->timeend = usergetmidnight($this->filterparams->timeend) + DAYSECS - 1;

        $this->define_columns($cols);
        $this->define_headers($headers);
        $this->collapsible(false);
        $this->sortable(false);
        $this->pageable(false);
    }

    /**
     * Helper function which is used by build logs to get action sql and param.
     *
     * @return array sql and param for action.
     */
    public function get_action_sql() {
        global $DB;

        if (!empty($this->filterparams->action)) {
            $sql = "crud = :crud";
            $params['crud'] = $this->filterparams->action;
        } else {
            // Add condition for all possible values of crud (to use db index).
            list($sql, $params) = $DB->get_in_or_equal(array('c', 'r', 'u', 'd'),
                    SQL_PARAMS_NAMED, 'crud');
            $sql = "crud ".$sql;
        }
        return array($sql, $params);
    }

    /**
     * Helper function which is used by build logs to get course module sql and param.
     *
     * @return array sql and param for action.
     */
    public function get_cm_sql() {
        $joins = array();
        $params = array();

        $joins[] = "contextinstanceid = :contextinstanceid";
        $joins[] = "contextlevel = :contextmodule";
        $params['contextinstanceid'] = $this->filterparams->modid;
        $params['contextmodule'] = CONTEXT_MODULE;

        $sql = implode(' AND ', $joins);
        return array($sql, $params);
    }

    /**
     * Query the reader. Store results in the object for use by build_table.
     *
     * @param int $pagesize size of page for paginated displayed table.
     * @param bool $useinitialsbar do you want to use the initials bar.
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $DB;

        $joins = array();
        $params = array();

        // If we filter by userid and module id we also need to filter by crud and edulevel to ensure DB index is engaged.
        $useextendeddbindex = !empty($this->filterparams->userid) && !empty($this->filterparams->modid);

        if (!empty($this->filterparams->courseid) && $this->filterparams->courseid != SITEID) {
            $joins[] = "courseid = :courseid";
            $params['courseid'] = $this->filterparams->courseid;
        }

        if (!empty($this->filterparams->modid)) {
            list($actionsql, $actionparams) = $this->get_cm_sql();
            $joins[] = $actionsql;
            $params = array_merge($params, $actionparams);
        }

        if (!empty($this->filterparams->action) || $useextendeddbindex) {
            list($actionsql, $actionparams) = $this->get_action_sql();
            $joins[] = $actionsql;
            $params = array_merge($params, $actionparams);
        }

        if (!empty($this->filterparams->userid)) {
            $joins[] = "userid = :userid";
            $params['userid'] = $this->filterparams->userid;
        }

        // Origin.
        $groupby = "";
        if (!empty($this->filterparams->origin)) {
            $joins[] = "origin = :origin";
            $params['origin'] = $this->filterparams->origin;
        } else {
            $joins[] = "(origin = :origin1 OR origin = :origin2)";
            $params['origin1'] = 'web';
            $params['origin2'] = 'ws';
            $groupby = 'GROUP BY origin';
        }

        $joins[] = "timecreated > :timestart AND timecreated < :timeend";
        $joins[] = "anonymous = 0";
        $selector = implode(' AND ', $joins);

        // Get log table.
        $logmanager = get_log_manager();
        $readers = $logmanager->get_readers();
        $reader = $readers[$this->filterparams->logreader];
        $logtable = $reader->get_internal_log_table_name();

        $timerange = $this->filterparams->timeend - $this->filterparams->timestart;

        $ranges = array();
        $lasttimeend = $this->filterparams->timestart - 1;

        if ($timerange > YEARSECS) {
            $timeformat = 'strftimedatefullshort';
            $rangetype = get_string('year');
            $rangeinc = YEARSECS;
        } else if ($timerange > (WEEKSECS * 4)) {
            $timeformat = 'strftimedatefullshort';
            $rangetype = get_string('month');
            $rangeinc = WEEKSECS * 4;
        } else if ($timerange > WEEKSECS) {
            $timeformat = 'strftimedatefullshort';
            $rangetype = get_string('week');
            $rangeinc = WEEKSECS;
        } else if ($timerange > DAYSECS) { // Daily range.
            $timeformat = 'strftimedayshort';
            $rangetype = get_string('day');
            $rangeinc = DAYSECS;
        } else {    // Hourly range.
            $timeformat = 'strftimedaytime';
            $rangetype = get_string('hour');
            $rangeinc = HOURSECS * 2;
        }

        while ($this->filterparams->timeend > $lasttimeend) {
            $lasttimestart = $lasttimeend + 1;
            $lasttimeend = $lasttimestart + $rangeinc;
            if ($lasttimeend > $this->filterparams->timeend) {
                $lasttimeend = $this->filterparams->timeend;
            }
            $ranges[] = array(
                'timestart' => $lasttimestart,
                'timeend' => $lasttimeend,
            );
        }

        $this->rawdata = array();
        foreach ($ranges as $i => $range) {
            $params['timestart'] = $range['timestart'];
            $params['timeend'] = $range['timeend'];

            $sql = "SELECT origin, COUNT('x') as totalcount
                      FROM {{$logtable}}
                     WHERE $selector
                     $groupby
            ";
            $records = $DB->get_records_sql($sql, $params);

            $timeperiod = userdate($range['timeend'] - HOURSECS, get_string($timeformat, 'langconfig'));
            $web = isset($records['web']) ? $records['web']->totalcount : 0;
            $ws = isset($records['ws']) ? $records['ws']->totalcount : 0;
            $this->rawdata[] = (object) ['timeperiod' => $timeperiod, 'web' => $web, 'ws' => $ws];
        }
    }

    /**
     * Convenience method to call a number of methods for you to display the
     * table.
     */
    function display_chart_and_table($pagesize, $useinitialsbar, $downloadhelpbutton='', $output) {
        global $DB;
        if (!$this->columns) {
            $onerow = $DB->get_record_sql("SELECT {$this->sql->fields} FROM {$this->sql->from} WHERE {$this->sql->where}", $this->sql->params);
            //if columns is not set then define columns as the keys of the rows returned
            //from the db.
            $this->define_columns(array_keys((array)$onerow));
            $this->define_headers(array_keys((array)$onerow));
        }
        $this->setup();
        $this->query_db($pagesize, $useinitialsbar);

        $labels = array();
        $labelwithcount = array();
        $series = array('ws' => array(), 'web' => array());

        reset($this->columns);
        $key = key($this->columns);

        $totalothers = 0;
        $count = 0;
        foreach ($this->rawdata as $row) {
            $labels[] = $row->timeperiod;
            if (isset($row->web)) {
                $series['web'][] = $row->web;
            }
            if (isset($row->ws)) {
                $series['ws'][] = $row->ws;
            }
        }

        $chart = new \report_mobile\chartjs\chart_line();
        $chart->set_smooth(true);
        foreach ($series as $key => $serie) {
            $key = ($key == 'ws' && $this->filterparams->mobileonly) ? 'mobileapp' : $key;
            $reportserie = new \report_mobile\chartjs\chart_series(get_string($key, 'report_mobile'), $serie);
            $chart->add_series($reportserie);
        }
        $chart->set_labels($labels);
        echo $output->render($chart);

        $this->build_table();
        $this->finish_output();
    }
}
