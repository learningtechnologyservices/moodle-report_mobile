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
 * Post installation and migration code.
 *
 * @package    report_mobile
 * @copyright  2017 Juan Leyva
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

function xmldb_report_mobile_install() {
    global $DB;

    $dbman = $DB->get_manager();

    // Add index to the standard logstore table to speed-up queries.
    $tablename = 'logstore_standard_log';
    if (!$dbman->table_exists($tablename)) {
        return;
    }
    // Add index to the origin field in the logstore_standard_log table.
    $table = new xmldb_table($tablename);
    $index = new xmldb_index('origin', XMLDB_INDEX_NOTUNIQUE, array('origin'));

    // Conditionally launch add index.
    if (!$dbman->index_exists($table, $index)) {
        $dbman->add_index($table, $index);
    }
}

