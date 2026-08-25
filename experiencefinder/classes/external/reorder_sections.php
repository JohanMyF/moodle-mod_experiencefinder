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
 * ExperienceFinder component.
 *
 * @package    mod_experiencefinder
 * @copyright  2026 Johan Venter
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_experiencefinder\external;

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/** External function used to persist builder section order. */
class reorder_sections extends external_api {
    /** @return external_function_parameters */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'ExperienceFinder course module id'),
            'order' => new external_value(PARAM_SEQUENCE, 'Comma-separated ExperienceFinder section ids'),
        ]);
    }

    /**
     * Persist the requested section order.
     * @param int $cmid Course module id.
     * @param string $order Comma-separated section ids.
     * @return array
     */
    public static function execute(int $cmid, string $order): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid, 'order' => $order]);
        $cm = get_coursemodule_from_id('experiencefinder', $params['cmid'], 0, false, MUST_EXIST);
        $experiencefinder = $DB->get_record('experiencefinder', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/experiencefinder:manage', $context);

        $ids = array_values(array_filter(array_map('intval', explode(',', $params['order']))));
        $validsections = $DB->get_records('experiencefinder_sections', ['experiencefinderid' => $experiencefinder->id], 'sortorder ASC, id ASC', 'id');
        $transaction = $DB->start_delegated_transaction();
        $sortorder = 1;
        foreach ($ids as $sectionid) {
            if (isset($validsections[$sectionid])) {
                $DB->set_field('experiencefinder_sections', 'sortorder', $sortorder++, ['id' => $sectionid]);
                unset($validsections[$sectionid]);
            }
        }
        foreach ($validsections as $section) {
            $DB->set_field('experiencefinder_sections', 'sortorder', $sortorder++, ['id' => $section->id]);
        }
        $transaction->allow_commit();
        return ['status' => true];
    }

    /** @return external_single_structure */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Whether the order was saved'),
        ]);
    }
}
