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

/**
 * Table mobile class for displaying logs.
 *
 * @package    report_mobile
 * @copyright  2017 Juan Leyva
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class table_usage_mod extends table_usage {

    /**
     * Sets up the table_log parameters.
     *
     * @param string $uniqueid unique id of form.
     * @param stdClass $filterparams (optional) filter params.
     */
    public function __construct($uniqueid, $filterparams = null) {
        parent::__construct($uniqueid, $filterparams);
        // Rename first column.
        $cols = array();
        $cols[] = 'component';
        $cols[] = 'web';
        $cols[] = 'ws';
        $this->define_columns($cols);

        $headers = array();
        $headers[] = get_string('activitymodule');
        $headers[1] = $this->headers[1];
        $headers[2] = $this->headers[2];
        $this->define_headers($headers);
    }

    /**
     * Query the reader. Store results in the object for use by build_table.
     *
     * @param int $pagesize size of page for paginated displayed table.
     * @param bool $useinitialsbar do you want to use the initials bar.
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $DB;

        list($logtable, $selector, $params, $groupby) = $this->get_base_selector();

        if (empty($groupby)) {
            $groupby = 'GROUP BY component';
        } else {
            $groupby .= ', component';
        }

        $sql = "SELECT origin, component, COUNT('x') as totalcount
                  FROM {{$logtable}}
                 WHERE $selector
                 $groupby
        ";
        $records = $DB->get_recordset_sql($sql, $params);

        $this->rawdata = array();
        foreach ($records as $record) {
            if (strpos($record->component, 'mod_') === false) {
                continue;
            }
            $web = ($record->origin == 'web') ? $record->totalcount : 0;
            $ws = ($record->origin == 'ws') ? $record->totalcount : 0;

            if (isset($this->rawdata[$record->component])) {
                $this->rawdata[$record->component]->web += $web;
                $this->rawdata[$record->component]->ws += $ws;
            } else {
                $component = get_string('pluginname', $record->component);
                $this->rawdata[$record->component] = (object) ['component' => $component, 'web' => $web, 'ws' => $ws];
            }
        }
        $records->close();
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
        $series = array('ws' => array(), 'web' => array());

        foreach ($this->rawdata as $row) {
            $labels[] = $row->component;
            $series['web'][] = $row->web;
            $series['ws'][] = $row->ws;
        }

        $chart = new \report_mobile\chartjs\chart_bar();
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
