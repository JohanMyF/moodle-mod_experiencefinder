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
$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$sectionid = optional_param('sectionid', 0, PARAM_INT);
$promptid = optional_param('promptid', 0, PARAM_INT);
$questionid = optional_param('questionid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('experiencefinder', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$experiencefinder = $DB->get_record('experiencefinder', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/experiencefinder:manage', $context);

$url = new moodle_url('/mod/experiencefinder/builder.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_title(format_string($experiencefinder->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->js_call_amd('mod_experiencefinder/builder', 'init', [
    $cm->id,
    get_string('sectionordersaved', 'experiencefinder'),
    get_string('sectionordersavefailed', 'experiencefinder'),
]);


/** Return section-level SHAPE result choices for this course. */
function experiencefinder_section_result_options(int $courseid): array {
    $options = [
        'none:0' => get_string('sectionresultnone', 'experiencefinder'),
        'collage:0' => get_string('visualcollage', 'experiencefinder'),
    ];
    $mods = [
        'selfprofile' => get_string('selfprofilesummary', 'experiencefinder'),
        'passionfinder' => get_string('passionfinderpaneltitle', 'experiencefinder'),
        'skillsfinder' => get_string('skillsfinderpaneltitle', 'experiencefinder'),
        'personalityfinder' => get_string('personalityfinderpaneltitle', 'experiencefinder'),
    ];

    // Fast modinfo is cached by Moodle and avoids one database query per sibling module.
    $modinfo = get_fast_modinfo($courseid);
    foreach ($modinfo->get_cms() as $cmrecord) {
        $modname = $cmrecord->modname;
        if (!isset($mods[$modname]) || !empty($cmrecord->deletioninprogress)) {
            continue;
        }
        $options[$modname . ':' . $cmrecord->id] = $mods[$modname] . ' — ' .
            format_string($cmrecord->name) . ' (cmid ' . $cmrecord->id . ')';
    }

    return $options;
}

/** Parse a section-level result selector value into a safe type/cmid pair. */
function experiencefinder_parse_section_result(string $value): array {
    $allowed = ['none', 'selfprofile', 'passionfinder', 'skillsfinder', 'personalityfinder', 'collage'];
    $parts = explode(':', $value, 2);
    $type = $parts[0] ?? 'none';
    $cmid = isset($parts[1]) ? (int)$parts[1] : 0;
    if (!in_array($type, $allowed, true) || $type === 'none') {
        return ['none', 0];
    }
    if ($type === 'collage') {
        return ['collage', 0];
    }
    if ($cmid <= 0) {
        return ['none', 0];
    }
    return [$type, $cmid];
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    if ($action === 'addsection') {
        [$resulttype, $resultcmid] = experiencefinder_parse_section_result(optional_param('resultlink', 'none:0', PARAM_TEXT));
        $max = $DB->get_field_sql('SELECT MAX(sortorder) FROM {experiencefinder_sections} WHERE experiencefinderid = ?', [$experiencefinder->id]);
        $record = (object)[
            'experiencefinderid' => $experiencefinder->id,
            'name' => required_param('name', PARAM_TEXT),
            'description' => optional_param('description', '', PARAM_CLEANHTML),
            'descriptionformat' => FORMAT_HTML,
            'resulttype' => $resulttype,
            'resultcmid' => $resultcmid,
            'sortorder' => (int)$max + 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $DB->insert_record('experiencefinder_sections', $record);

    } else if ($action === 'updatesection' && $sectionid) {
        $section = $DB->get_record('experiencefinder_sections', ['id' => $sectionid, 'experiencefinderid' => $experiencefinder->id], '*', MUST_EXIST);
        $section->name = required_param('name', PARAM_TEXT);
        [$resulttype, $resultcmid] = experiencefinder_parse_section_result(optional_param('resultlink', 'none:0', PARAM_TEXT));
        $section->description = optional_param('description', '', PARAM_CLEANHTML);
        $section->resulttype = $resulttype;
        $section->resultcmid = $resultcmid;
        $section->timemodified = time();
        $DB->update_record('experiencefinder_sections', $section);

    } else if ($action === 'deletesection' && $sectionid) {
        $section = $DB->get_record('experiencefinder_sections', ['id' => $sectionid, 'experiencefinderid' => $experiencefinder->id], '*', MUST_EXIST);
        $DB->delete_records('experiencefinder_prompts', ['sectionid' => $section->id]);
        $DB->delete_records('experiencefinder_questions', ['sectionid' => $section->id]);
        $DB->delete_records('experiencefinder_collage_items', ['sectionid' => $section->id]);
        $DB->delete_records('experiencefinder_sections', ['id' => $section->id]);

    } else if ($action === 'addprompt' && $sectionid) {
        $section = $DB->get_record('experiencefinder_sections', ['id' => $sectionid, 'experiencefinderid' => $experiencefinder->id], '*', MUST_EXIST);
        $max = $DB->get_field_sql('SELECT MAX(sortorder) FROM {experiencefinder_prompts} WHERE sectionid = ?', [$section->id]);
        $DB->insert_record('experiencefinder_prompts', (object)[
            'sectionid' => $section->id,
            'prompttext' => required_param('prompttext', PARAM_TEXT),
            'prompttype' => 'reflection',
            'storeselection' => optional_param('storeselection', 0, PARAM_INT),
            'sortorder' => (int)$max + 1,
        ]);

    } else if ($action === 'updateprompt' && $promptid) {
        $prompt = $DB->get_record('experiencefinder_prompts', ['id' => $promptid], '*', MUST_EXIST);
        $DB->get_record('experiencefinder_sections', ['id' => $prompt->sectionid, 'experiencefinderid' => $experiencefinder->id], '*', MUST_EXIST);
        $prompt->prompttext = required_param('prompttext', PARAM_TEXT);
        $prompt->storeselection = optional_param('storeselection', 0, PARAM_INT);
        $DB->update_record('experiencefinder_prompts', $prompt);

    } else if ($action === 'deleteprompt' && $promptid) {
        $prompt = $DB->get_record('experiencefinder_prompts', ['id' => $promptid], '*', MUST_EXIST);
        $DB->get_record('experiencefinder_sections', ['id' => $prompt->sectionid, 'experiencefinderid' => $experiencefinder->id], '*', MUST_EXIST);
        $DB->delete_records('experiencefinder_selections', ['promptid' => $prompt->id]);
        $DB->delete_records('experiencefinder_prompts', ['id' => $prompt->id]);

    } else if ($action === 'addquestion' && $sectionid) {
        $section = $DB->get_record('experiencefinder_sections', ['id' => $sectionid, 'experiencefinderid' => $experiencefinder->id], '*', MUST_EXIST);
        $max = $DB->get_field_sql('SELECT MAX(sortorder) FROM {experiencefinder_questions} WHERE sectionid = ?', [$section->id]);
        $DB->insert_record('experiencefinder_questions', (object)[
            'sectionid' => $section->id,
            'questiontext' => required_param('questiontext', PARAM_TEXT),
            'sortorder' => (int)$max + 1,
        ]);

    } else if ($action === 'updatequestion' && $questionid) {
        $question = $DB->get_record('experiencefinder_questions', ['id' => $questionid], '*', MUST_EXIST);
        $DB->get_record('experiencefinder_sections', ['id' => $question->sectionid, 'experiencefinderid' => $experiencefinder->id], '*', MUST_EXIST);
        $question->questiontext = required_param('questiontext', PARAM_TEXT);
        $DB->update_record('experiencefinder_questions', $question);

    } else if ($action === 'deletequestion' && $questionid) {
        $question = $DB->get_record('experiencefinder_questions', ['id' => $questionid], '*', MUST_EXIST);
        $DB->get_record('experiencefinder_sections', ['id' => $question->sectionid, 'experiencefinderid' => $experiencefinder->id], '*', MUST_EXIST);
        $DB->delete_records('experiencefinder_answers', ['questionid' => $question->id]);
        $DB->delete_records('experiencefinder_questions', ['id' => $question->id]);
    }
    redirect($url);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($experiencefinder->name) . ': ' . get_string('openbuilder', 'experiencefinder'));

echo html_writer::div(get_string('teacherbuilderintro', 'experiencefinder'), 'alert alert-info');
echo html_writer::div(
    html_writer::link(new moodle_url('/mod/experiencefinder/view.php', ['id' => $cm->id]), get_string('view')) . ' | ' .
    html_writer::link(new moodle_url('/mod/experiencefinder/report.php', ['id' => $cm->id]), get_string('viewreports', 'experiencefinder')),
    'ef-toolbar'
);

$resultoptions = experiencefinder_section_result_options((int)$course->id);

$sections = $DB->get_records('experiencefinder_sections', ['experiencefinderid' => $experiencefinder->id], 'sortorder ASC, id ASC');

// Load child records once, then render them from memory inside the section loop.
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

echo html_writer::start_div('ef-sections', ['id' => 'ef-sections']);
foreach ($sections as $section) {
    echo html_writer::start_tag('details', [
        'class' => 'ef-card ef-section-card',
        'data-section-id' => $section->id,
        'draggable' => 'true'
    ]);
    echo html_writer::start_tag('summary', ['class' => 'ef-section-summary']);
    echo html_writer::span('☰', 'ef-draghandle', ['title' => get_string('dragsection', 'experiencefinder')]);
    echo html_writer::span(format_string($section->name), 'ef-section-title');
    echo html_writer::span(get_string('clicktoopen', 'experiencefinder'), 'ef-summaryhint');
    echo html_writer::end_tag('summary');

    echo html_writer::start_div('ef-section-body');
    echo format_text($section->description, $section->descriptionformat, ['context' => $context]);

    echo html_writer::start_tag('details', ['class' => 'ef-editdetails']);
    echo html_writer::tag('summary', get_string('editsection', 'experiencefinder'));
    echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'ef-editform']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'updatesection']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sectionid', 'value' => $section->id]);
    echo html_writer::label(get_string('name'), 'sectionname' . $section->id);
    echo html_writer::empty_tag('input', ['id' => 'sectionname' . $section->id, 'type' => 'text', 'name' => 'name', 'value' => s($section->name), 'class' => 'form-control']);
    echo html_writer::label(get_string('description'), 'sectiondesc' . $section->id);
    echo html_writer::tag('textarea', s($section->description), ['id' => 'sectiondesc' . $section->id, 'name' => 'description', 'class' => 'form-control', 'rows' => 3]);
    echo html_writer::label(get_string('sectionresult', 'experiencefinder'), 'sectionresult' . $section->id);
    $currentresult = (($section->resulttype ?? 'none') === 'none') ? 'none:0' : (($section->resulttype ?? 'none') . ':' . (int)($section->resultcmid ?? 0));
    echo html_writer::select($resultoptions, 'resultlink', $currentresult, false, ['id' => 'sectionresult' . $section->id, 'class' => 'custom-select']);
    if (($section->resulttype ?? 'none') === 'collage') {
        echo html_writer::div(get_string('collagepreview_help', 'experiencefinder'), 'ef-note');
        echo experiencefinder_render_collage_library_preview((int)$experiencefinder->id);
    }
    echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('savechanges', 'experiencefinder'), 'class' => 'btn btn-primary mt-2']);
    echo html_writer::end_tag('form');
    echo html_writer::end_tag('details');

    $prompts = $promptsbysection[$section->id] ?? [];
    echo html_writer::tag('h4', get_string('prompts', 'experiencefinder'));
    if ($prompts) {
        echo html_writer::start_tag('ul', ['class' => 'ef-list']);
        foreach ($prompts as $prompt) {
            $badge = $prompt->storeselection ? get_string('storeselection', 'experiencefinder') : get_string('reflectiononly', 'experiencefinder');
            $edit = html_writer::start_tag('details', ['class' => 'ef-itemedit']) .
                html_writer::tag('summary', get_string('edit')) .
                html_writer::start_tag('form', ['method' => 'post', 'class' => 'ef-editform']) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'updateprompt']) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'promptid', 'value' => $prompt->id]) .
                html_writer::empty_tag('input', ['type' => 'text', 'name' => 'prompttext', 'value' => s($prompt->prompttext), 'class' => 'form-control']) .
                html_writer::select([0 => get_string('reflectiononly', 'experiencefinder'), 1 => get_string('storeselection', 'experiencefinder')], 'storeselection', $prompt->storeselection, false, ['class' => 'custom-select mt-1']) .
                html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('savechanges', 'experiencefinder'), 'class' => 'btn btn-primary btn-sm mt-1']) .
                html_writer::end_tag('form') . html_writer::end_tag('details');
            $deleteform = html_writer::tag('form',
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'deleteprompt']) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'promptid', 'value' => $prompt->id]) .
                html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('delete'), 'class' => 'btn btn-link text-danger btn-sm']),
                ['method' => 'post', 'class' => 'ef-inlineform ef-confirm-form', 'data-confirm-message' => get_string('confirmdeleteprompt', 'experiencefinder')]
            );
            echo html_writer::tag('li', html_writer::span(format_string($prompt->prompttext), 'ef-itemtext') . ' <span class="badge badge-secondary">' . s($badge) . '</span> ' . html_writer::span($edit . $deleteform, 'ef-itemactions'));
        }
        echo html_writer::end_tag('ul');
    }

    echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'ef-addform']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'addprompt']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sectionid', 'value' => $section->id]);
    echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'prompttext', 'placeholder' => get_string('addprompt', 'experiencefinder'), 'class' => 'form-control']);
    echo html_writer::select([0 => get_string('reflectiononly', 'experiencefinder'), 1 => get_string('storeselection', 'experiencefinder')], 'storeselection', 0, false, ['class' => 'custom-select mt-1']);
    echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('addprompt', 'experiencefinder'), 'class' => 'btn btn-secondary mt-1']);
    echo html_writer::end_tag('form');

    $questions = $questionsbysection[$section->id] ?? [];
    echo html_writer::tag('h4', get_string('questions', 'experiencefinder'));
    if ($questions) {
        echo html_writer::start_tag('ul', ['class' => 'ef-list']);
        foreach ($questions as $question) {
            $edit = html_writer::start_tag('details', ['class' => 'ef-itemedit']) .
                html_writer::tag('summary', get_string('edit')) .
                html_writer::start_tag('form', ['method' => 'post', 'class' => 'ef-editform']) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'updatequestion']) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'questionid', 'value' => $question->id]) .
                html_writer::empty_tag('input', ['type' => 'text', 'name' => 'questiontext', 'value' => s($question->questiontext), 'class' => 'form-control']) .
                html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('savechanges', 'experiencefinder'), 'class' => 'btn btn-primary btn-sm mt-1']) .
                html_writer::end_tag('form') . html_writer::end_tag('details');
            $deleteform = html_writer::tag('form',
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'deletequestion']) .
                html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'questionid', 'value' => $question->id]) .
                html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('delete'), 'class' => 'btn btn-link text-danger btn-sm']),
                ['method' => 'post', 'class' => 'ef-inlineform ef-confirm-form', 'data-confirm-message' => get_string('confirmdeletequestion', 'experiencefinder')]
            );
            echo html_writer::tag('li', html_writer::span(format_string($question->questiontext), 'ef-itemtext') . ' ' . html_writer::span($edit . $deleteform, 'ef-itemactions'));
        }
        echo html_writer::end_tag('ul');
    }

    echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'ef-addform']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'addquestion']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sectionid', 'value' => $section->id]);
    echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'questiontext', 'placeholder' => get_string('addquestion', 'experiencefinder'), 'class' => 'form-control']);
    echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('addquestion', 'experiencefinder'), 'class' => 'btn btn-secondary mt-1']);
    echo html_writer::end_tag('form');

    echo html_writer::tag('form',
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'deletesection']) .
        html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sectionid', 'value' => $section->id]) .
        html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('deletesection', 'experiencefinder'), 'class' => 'btn btn-outline-danger mt-3']),
        ['method' => 'post', 'class' => 'ef-confirm-form', 'data-confirm-message' => get_string('confirmdeletesection', 'experiencefinder')]
    );
    echo html_writer::end_div();
    echo html_writer::end_tag('details');
}
echo html_writer::end_div();

echo html_writer::start_div('ef-card ef-addsection');
echo html_writer::tag('h3', get_string('addsection', 'experiencefinder'));
echo html_writer::start_tag('form', ['method' => 'post']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'addsection']);
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'name', 'placeholder' => get_string('section', 'experiencefinder'), 'class' => 'form-control', 'required' => 'required']);
echo html_writer::tag('textarea', '', ['name' => 'description', 'placeholder' => get_string('description'), 'class' => 'form-control mt-2', 'rows' => 3]);
echo html_writer::label(get_string('addsectionresult', 'experiencefinder'), 'addsectionresult');
echo html_writer::select($resultoptions, 'resultlink', 'none:0', false, ['id' => 'addsectionresult', 'class' => 'custom-select mt-2']);
echo html_writer::start_div('ef-add-collage-preview', ['style' => 'display:none']);
echo html_writer::div(get_string('collagepreview_help', 'experiencefinder'), 'ef-note ef-collage-builder-preview-note');
echo experiencefinder_render_collage_library_preview((int)$experiencefinder->id);
echo html_writer::end_div();
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('addsection', 'experiencefinder'), 'class' => 'btn btn-primary mt-2']);
echo html_writer::end_tag('form');
echo html_writer::end_div();

echo $OUTPUT->footer();
