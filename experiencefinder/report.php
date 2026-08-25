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
$cm = get_coursemodule_from_id('experiencefinder', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$experiencefinder = $DB->get_record('experiencefinder', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/experiencefinder:viewreports', $context);

$PAGE->set_url('/mod/experiencefinder/report.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($experiencefinder->name));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('responses', 'experiencefinder'));

echo html_writer::div(
    html_writer::link(new moodle_url('/mod/experiencefinder/view.php', ['id' => $cm->id]), get_string('view')) . ' | ' .
    html_writer::link(new moodle_url('/mod/experiencefinder/builder.php', ['id' => $cm->id]), get_string('openbuilder', 'experiencefinder')),
    'ef-toolbar'
);

$responses = $DB->get_records('experiencefinder_responses', ['experiencefinderid' => $experiencefinder->id], 'timemodified DESC');
if (!$responses) {
    echo html_writer::div(get_string('noresponses', 'experiencefinder'), 'alert alert-info');
    echo $OUTPUT->footer();
    exit;
}

$userids = array_values(array_unique(array_map(static function($response) {
    return (int)$response->userid;
}, $responses)));
$users = $userids ? $DB->get_records_list('user', 'id', $userids) : [];

$table = new html_table();
$table->head = [get_string('participant', 'experiencefinder'), get_string('timemodified', 'experiencefinder'), get_string('downloadpdf', 'experiencefinder')];
foreach ($responses as $response) {
    if (empty($users[$response->userid])) {
        continue;
    }
    $user = $users[$response->userid];
    $pdfurl = new moodle_url('/mod/experiencefinder/pdf.php', ['id' => $cm->id, 'userid' => $user->id]);
    $table->data[] = [fullname($user), userdate($response->timemodified), html_writer::link($pdfurl, get_string('downloadpdf', 'experiencefinder'))];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
