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

namespace mod_experiencefinder\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\helper;
use core_privacy\local\request\writer;

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('experiencefinder_responses', [
            'userid' => 'privacy:metadata:experiencefinder_responses:userid',
        ], 'privacy:metadata:experiencefinder_responses');
        $collection->add_database_table('experiencefinder_answers', [
            'answertext' => 'privacy:metadata:experiencefinder_answers:answertext',
        ], 'privacy:metadata:experiencefinder_answers');
        $collection->add_database_table('experiencefinder_selections', [], 'privacy:metadata:experiencefinder_selections');
        $collection->add_database_table('experiencefinder_collage_items', [
            'filename' => 'privacy:metadata:experiencefinder_collage_items:filename',
        ], 'privacy:metadata:experiencefinder_collage_items');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextmodule
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {experiencefinder} ef ON ef.id = cm.instance
                  JOIN {experiencefinder_responses} r ON r.experiencefinderid = ef.id
                 WHERE r.userid = :userid";
        $contextlist->add_from_sql($sql, ['contextmodule' => CONTEXT_MODULE, 'modname' => 'experiencefinder', 'userid' => $userid]);
        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $cm = get_coursemodule_from_id('experiencefinder', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $experiencefinder = $DB->get_record('experiencefinder', ['id' => $cm->instance]);
            $response = $DB->get_record('experiencefinder_responses', ['experiencefinderid' => $experiencefinder->id, 'userid' => $userid]);
            if (!$response) {
                continue;
            }
            $data = (object)[
                'responses' => $DB->get_records('experiencefinder_answers', ['responseid' => $response->id]),
                'selections' => $DB->get_records('experiencefinder_selections', ['responseid' => $response->id]),
                'collageitems' => $DB->get_records('experiencefinder_collage_items', ['responseid' => $response->id]),
            ];
            writer::with_context($context)->export_data([get_string('pluginname', 'experiencefinder')], $data);
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('experiencefinder', $context->instanceid);
        if (!$cm) {
            return;
        }
        $responses = $DB->get_records('experiencefinder_responses', ['experiencefinderid' => $cm->instance]);
        foreach ($responses as $response) {
            $DB->delete_records('experiencefinder_answers', ['responseid' => $response->id]);
            $DB->delete_records('experiencefinder_selections', ['responseid' => $response->id]);
            $DB->delete_records('experiencefinder_collage_items', ['responseid' => $response->id]);
        }
        $DB->delete_records('experiencefinder_responses', ['experiencefinderid' => $cm->instance]);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $cm = get_coursemodule_from_id('experiencefinder', $context->instanceid);
            if (!$cm) {
                continue;
            }
            if ($response = $DB->get_record('experiencefinder_responses', ['experiencefinderid' => $cm->instance, 'userid' => $userid])) {
                $DB->delete_records('experiencefinder_answers', ['responseid' => $response->id]);
                $DB->delete_records('experiencefinder_selections', ['responseid' => $response->id]);
                $DB->delete_records('experiencefinder_collage_items', ['responseid' => $response->id]);
                $DB->delete_records('experiencefinder_responses', ['id' => $response->id]);
            }
        }
    }
}
