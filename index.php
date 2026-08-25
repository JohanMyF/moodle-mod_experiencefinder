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

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course);

$PAGE->set_url('/mod/experiencefinder/index.php', ['id' => $id]);
$PAGE->set_title(get_string('modulenameplural', 'experiencefinder'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'experiencefinder'));

$instances = get_all_instances_in_course('experiencefinder', $course);
if (!$instances) {
    notice(get_string('thereareno', 'moodle', get_string('modulenameplural', 'experiencefinder')), new moodle_url('/course/view.php', ['id' => $course->id]));
}

$table = new html_table();
$table->head = [get_string('name')];
foreach ($instances as $instance) {
    $url = new moodle_url('/mod/experiencefinder/view.php', ['id' => $instance->coursemodule]);
    $table->data[] = [html_writer::link($url, format_string($instance->name))];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
