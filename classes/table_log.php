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

defined('MOODLE_INTERNAL') || die;

/**
 * Table mobile class for displaying logs.
 *
 * @package    report_mobile
 * @copyright  2017 Juan Leyva
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_mobile_table_log extends table_sql {

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

        $cols = array('date');
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

        $groupid = 0;
        if (!empty($this->filterparams->courseid) && $this->filterparams->courseid != SITEID) {
            if (!empty($this->filterparams->groupid)) {
                $groupid = $this->filterparams->groupid;
            }

            $joins[] = "courseid = :courseid";
            $params['courseid'] = $this->filterparams->courseid;
        }

        if (!empty($this->filterparams->siteerrors)) {
            $joins[] = "( action='error' OR action='infected' OR action='failed' )";
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

        // Getting all members of a group.
        if ($groupid and empty($this->filterparams->userid)) {
            if ($gusers = groups_get_members($groupid)) {
                $gusers = array_keys($gusers);
                $joins[] = 'userid IN (' . implode(',', $gusers) . ')';
            } else {
                $joins[] = 'userid = 0'; // No users in groups, so we want something that will always be false.
            }
        } else if (!empty($this->filterparams->userid)) {
            $joins[] = "userid = :userid";
            $params['userid'] = $this->filterparams->userid;
        }

        if (!empty($this->filterparams->date)) {
            $joins[] = "timecreated > :date AND timecreated < :enddate";
            $params['date'] = $this->filterparams->date;
            $params['enddate'] = $this->filterparams->date + DAYSECS; // Show logs only for the selected date.
        }

        if (isset($this->filterparams->edulevel) && ($this->filterparams->edulevel >= 0)) {
            $joins[] = "edulevel = :edulevel";
            $params['edulevel'] = $this->filterparams->edulevel;
        } else if ($useextendeddbindex) {
            list($edulevelsql, $edulevelparams) = $DB->get_in_or_equal(array(\core\event\base::LEVEL_OTHER,
                \core\event\base::LEVEL_PARTICIPATING, \core\event\base::LEVEL_TEACHING), SQL_PARAMS_NAMED, 'edulevel');
            $joins[] = "edulevel ".$edulevelsql;
            $params = array_merge($params, $edulevelparams);
        }

        $joins[] = "anonymous = 0";

        $selector = implode(' AND ', $joins);

        $this->rawdata = [['date' => 'Week 1', 'allsources' => 50, 'web' => 30, 'ws' => 20],
            ['allsources' => 50, 'date' => 'Week 2', 'web' => 40, 'ws' => 10]];
    }
}
