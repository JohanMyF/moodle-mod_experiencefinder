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
require_once($CFG->dirroot . '/mod/experiencefinder/lib.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('experiencefinder', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$experiencefinder = $DB->get_record('experiencefinder', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/experiencefinder:view', $context);

$PAGE->set_url('/mod/experiencefinder/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($experiencefinder->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->js_call_amd('mod_experiencefinder/portfolio', 'init');


// Trigger the standard module viewed event for Moodle logging and analytics.
$event = \mod_experiencefinder\event\course_module_viewed::create([
    'context' => $context,
    'objectid' => $experiencefinder->id,
]);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('experiencefinder', $experiencefinder);
$event->trigger();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $response = experiencefinder_get_response($experiencefinder->id, $USER->id);
    $response->timemodified = time();
    $DB->update_record('experiencefinder_responses', $response);

    $questions = $DB->get_records_sql("SELECT q.*
                                         FROM {experiencefinder_questions} q
                                         JOIN {experiencefinder_sections} s ON s.id = q.sectionid
                                        WHERE s.experiencefinderid = ?", [$experiencefinder->id]);
    $existinganswerrecords = $DB->get_records('experiencefinder_answers', ['responseid' => $response->id]);
    $existinganswers = [];
    foreach ($existinganswerrecords as $answerrecord) {
        $existinganswers[$answerrecord->questionid] = $answerrecord;
    }
    foreach ($questions as $question) {
        $field = 'q_' . $question->id;
        $text = optional_param($field, '', PARAM_TEXT);
        $existing = $existinganswers[$question->id] ?? null;
        if ($existing) {
            $existing->answertext = $text;
            $existing->timemodified = time();
            $DB->update_record('experiencefinder_answers', $existing);
        } else if (trim($text) !== '') {
            $DB->insert_record('experiencefinder_answers', (object)[
                'responseid' => $response->id,
                'questionid' => $question->id,
                'answertext' => $text,
                'timemodified' => time(),
            ]);
        }
    }

    $storedprompts = $DB->get_records_sql("SELECT p.*
                                             FROM {experiencefinder_prompts} p
                                             JOIN {experiencefinder_sections} s ON s.id = p.sectionid
                                            WHERE s.experiencefinderid = ? AND p.storeselection = 1", [$experiencefinder->id]);
    $existingselectionrecords = $DB->get_records('experiencefinder_selections', ['responseid' => $response->id]);
    $existingselections = [];
    foreach ($existingselectionrecords as $selectionrecord) {
        $existingselections[$selectionrecord->promptid] = $selectionrecord;
    }
    foreach ($storedprompts as $prompt) {
        $selected = optional_param('p_' . $prompt->id, 0, PARAM_INT);
        $existingselection = $existingselections[$prompt->id] ?? null;
        if ($selected && !$existingselection) {
            $DB->insert_record('experiencefinder_selections', (object)[
                'responseid' => $response->id,
                'promptid' => $prompt->id,
                'timecreated' => time(),
            ]);
        } else if (!$selected && $existingselection) {
            $DB->delete_records('experiencefinder_selections', ['id' => $existingselection->id]);
        }
    }

    $collagesections = $DB->get_records('experiencefinder_sections', [
        'experiencefinderid' => $experiencefinder->id,
        'resulttype' => 'collage',
    ]);
    foreach ($collagesections as $collagesection) {
        $json = optional_param('collage_' . $collagesection->id, '[]', PARAM_TEXT);
        experiencefinder_save_collage_items((int)$response->id, (int)$collagesection->id, $json);
        $snapshot = optional_param('collage_snapshot_' . $collagesection->id, '', PARAM_TEXT);
        experiencefinder_save_collage_snapshot((int)$response->id, (int)$collagesection->id, $snapshot);
    }

    redirect(new moodle_url('/mod/experiencefinder/view.php', ['id' => $cm->id]), get_string('saved', 'experiencefinder'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($experiencefinder->name));

if (has_capability('mod/experiencefinder:manage', $context)) {
    echo html_writer::div(
        html_writer::link(new moodle_url('/mod/experiencefinder/builder.php', ['id' => $cm->id]), get_string('openbuilder', 'experiencefinder'), ['class' => 'btn btn-primary']) . ' ' .
        html_writer::link(new moodle_url('/mod/experiencefinder/report.php', ['id' => $cm->id]), get_string('viewreports', 'experiencefinder'), ['class' => 'btn btn-secondary']),
        'ef-toolbar'
    );
}

if (!empty($experiencefinder->intro)) {
    echo format_module_intro('experiencefinder', $experiencefinder, $cm->id);
}


$sections = $DB->get_records('experiencefinder_sections', ['experiencefinderid' => $experiencefinder->id], 'sortorder ASC, id ASC');
if (!$sections) {
    echo html_writer::div(get_string('notconfiguredstudent', 'experiencefinder'), 'alert alert-warning');
    echo $OUTPUT->footer();
    exit;
}

$response = experiencefinder_get_response($experiencefinder->id, $USER->id);
$answers = $DB->get_records_menu('experiencefinder_answers', ['responseid' => $response->id], '', 'questionid, answertext');
$selections = $DB->get_records_menu('experiencefinder_selections', ['responseid' => $response->id], '', 'promptid, id');

$allprompts = $DB->get_records_sql("SELECT p.*
                                     FROM {experiencefinder_prompts} p
                                     JOIN {experiencefinder_sections} s ON s.id = p.sectionid
                                    WHERE s.experiencefinderid = ?
                                 ORDER BY p.sectionid, p.sortorder, p.id", [$experiencefinder->id]);
$promptsbysection = [];
foreach ($allprompts as $prompt) {
    $promptsbysection[$prompt->sectionid][] = $prompt;
}

$allquestions = $DB->get_records_sql("SELECT q.*
                                       FROM {experiencefinder_questions} q
                                       JOIN {experiencefinder_sections} s ON s.id = q.sectionid
                                      WHERE s.experiencefinderid = ?
                                   ORDER BY q.sectionid, q.sortorder, q.id", [$experiencefinder->id]);
$questionsbysection = [];
foreach ($allquestions as $question) {
    $questionsbysection[$question->sectionid][] = $question;
}

echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'ef-studentform']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

foreach ($sections as $section) {
    echo html_writer::start_div('ef-card');
    echo html_writer::tag('h3', format_string($section->name));
    echo format_text($section->description, $section->descriptionformat, ['context' => $context]);

    echo experiencefinder_render_section_result_panel($section, $USER->id);

    $sectionprompts = $promptsbysection[$section->id] ?? [];
    $reflectionprompts = array_filter($sectionprompts, static function($prompt) {
        return empty($prompt->storeselection);
    });
    if ($reflectionprompts) {
        echo html_writer::div(get_string('reflectionpromptinfo', 'experiencefinder'), 'ef-note');
        echo html_writer::start_tag('div', ['class' => 'ef-promptchips ef-reflectionchips']);
        foreach ($reflectionprompts as $prompt) {
            echo html_writer::span(format_string($prompt->prompttext), 'ef-chip');
        }
        echo html_writer::end_tag('div');
    }

    $storedprompts = array_filter($sectionprompts, static function($prompt) {
        return !empty($prompt->storeselection);
    });
    if ($storedprompts) {
        echo html_writer::div(get_string('storedpromptinfo', 'experiencefinder'), 'ef-note');
        foreach ($storedprompts as $prompt) {
            $checked = isset($selections[$prompt->id]) ? ['checked' => 'checked'] : [];
            echo html_writer::start_div('form-check');
            echo html_writer::empty_tag('input', array_merge(['type' => 'checkbox', 'class' => 'form-check-input', 'id' => 'p_' . $prompt->id, 'name' => 'p_' . $prompt->id, 'value' => 1], $checked));
            echo html_writer::tag('label', format_string($prompt->prompttext), ['class' => 'form-check-label', 'for' => 'p_' . $prompt->id]);
            echo html_writer::end_div();
        }
    }

    $questions = $questionsbysection[$section->id] ?? [];
    foreach ($questions as $question) {
        echo html_writer::start_div('ef-question');
        echo html_writer::tag('label', format_string($question->questiontext), ['for' => 'q_' . $question->id, 'class' => 'ef-questionlabel']);
        echo html_writer::tag('textarea', s($answers[$question->id] ?? ''), ['id' => 'q_' . $question->id, 'name' => 'q_' . $question->id, 'rows' => 5, 'class' => 'form-control']);
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
}

echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('saveandcontinue', 'experiencefinder'), 'class' => 'btn btn-primary ef-save']);
echo html_writer::end_tag('form');

echo html_writer::div(html_writer::link(new moodle_url('/mod/experiencefinder/pdf.php', ['id' => $cm->id]), get_string('downloadpdf', 'experiencefinder'), ['class' => 'btn btn-secondary']), 'ef-toolbar');

echo $OUTPUT->footer();
