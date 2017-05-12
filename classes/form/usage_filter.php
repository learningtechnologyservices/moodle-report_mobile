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
 * Usage report form filter.
 *
 * @package    report_mobile
 * @copyright  2017 Juan Leyva
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_mobile\form;
defined('MOODLE_INTERNAL') || die();

use moodleform;
use report_mobile\output\usage_report;

require_once($CFG->libdir . '/formslib.php');

/**
 * Usage report form filter.
 *
 * @package    report_mobile
 * @copyright  2017 Juan Leyva
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usage_filter extends moodleform {

    public function definition() {
        $mform = $this->_form;
        $id = $this->_customdata['id'];
        $modid = $this->_customdata['modid'];

        // Log reader.
        $readers = usage_report::get_readers();
        if (count($readers) > 1) {
            $mform->addElement('select', 'logreader', get_string('selectlogreader', 'report_log'), $readers);
        } else {
            reset($readers);
            $logreader = key($readers);
            $mform->addElement('hidden', 'logreader', $logreader);
            $mform->setType('logreader', PARAM_PLUGIN);
        }

        // Origin.
        $origins = usage_report::get_origin_options();
        $mform->addElement('select', 'origin', get_string('source', 'report_mobile'), $origins);
        $mform->setDefault('origin', '');

        // Date filter.
        $mform->addElement('date_selector', 'timestart', get_string('datestart', 'report_mobile'));
        $mform->setDefault('timestart', time() - WEEKSECS);
        $mform->addElement('date_selector', 'timeend', get_string('dateend', 'report_mobile'));
        $mform->setDefault('timeend', time());

        // Actions.
        $actions = usage_report::get_action_options();
        $mform->addElement('select', 'modaction', get_string('action'), $actions);
        $mform->setDefault('modaction', '');

        $mform->addElement('submit', 'submit', get_string('filterbydate', 'report_mobile'));
    }

    /**
     * Enforce validation rules here
     *
     * @param object $data Post data to validate
     * @return array
     **/
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Check open and close times are consistent.
        if ($data['timestart'] > $data['timeend']) {
            $errors['timestart'] = get_string('startdategreaterthanend', 'report_mobile');
        }

        return $errors;
    }

}

