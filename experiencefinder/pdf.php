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
require_once($CFG->libdir . '/pdflib.php');

$id = required_param('id', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$cm = get_coursemodule_from_id('experiencefinder', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$experiencefinder = $DB->get_record('experiencefinder', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
if ($userid && $userid != $USER->id) {
    require_capability('mod/experiencefinder:viewreports', $context);
} else {
    require_capability('mod/experiencefinder:view', $context);
    $userid = $USER->id;
}

$participant = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
$response = $DB->get_record('experiencefinder_responses', ['experiencefinderid' => $experiencefinder->id, 'userid' => $userid]);

function experiencefinder_pdf_mimetype(string $filename): string {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $map = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
    ];
    return $map[$ext] ?? 'application/octet-stream';
}

function experiencefinder_pdf_asset_bytes(array $asset, context_module $context, array $galleryfiles = []): ?array {
    global $CFG;

    $key = (string)($asset['key'] ?? '');
    $source = (string)($asset['source'] ?? '');
    $filename = '';
    $content = false;

    if ($source === 'default') {
        $filename = basename(preg_replace('/^default:/', '', $key));
        $path = $CFG->dirroot . '/mod/experiencefinder/pix/collage/' . $filename;
        if (is_readable($path)) {
            $content = file_get_contents($path);
        }
    } else if ($source === 'gallery') {
        $filename = basename(preg_replace('/^gallery:/', '', $key));
        $file = $galleryfiles[$filename] ?? null;
        if ($file && !$file->is_directory()) {
            $content = $file->get_content();
        }
    }

    if ($content === false || $content === '') {
        return null;
    }

    return [
        'filename' => $filename,
        'mime' => experiencefinder_pdf_mimetype($filename),
        'content' => $content,
    ];
}

function experiencefinder_pdf_data_uri_from_bytes(array $bytes): string {
    return 'data:' . $bytes['mime'] . ';base64,' . base64_encode($bytes['content']);
}

function experiencefinder_pdf_render_selfprofile(int $cmid, int $userid): string {
    $rows = experiencefinder_get_selfprofile_top_categories_by_cmid($cmid, $userid, 5);
    if (!$rows) {
        return '';
    }

    $html = '<table cellpadding="3" cellspacing="0" border="0" style="width:100%;">';
    foreach ($rows as $row) {
        $percent = max(1, min(100, (int)$row['percent']));
        $remain = max(0, 100 - $percent);
        $html .= '<tr>';
        $html .= '<td width="31%"><strong>' . s($row['name']) . '</strong></td>';
        $html .= '<td width="58%"><table cellpadding="0" cellspacing="0" border="0" style="width:100%;"><tr>';
        $html .= '<td width="' . $percent . '%" bgcolor="#5aa3d8" style="height:8px; line-height:8px;">&nbsp;</td>';
        if ($remain > 0) {
            $html .= '<td width="' . $remain . '%" bgcolor="#e9eef2" style="height:8px; line-height:8px;">&nbsp;</td>';
        }
        $html .= '</tr></table></td>';
        $html .= '<td width="11%" align="right">' . s((string)$row['score']) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</table>';
    return '<div class="pdfpanel">' . $html . '</div>';
}

function experiencefinder_pdf_render_passionfinder(int $cmid, int $userid): string {
    $groups = experiencefinder_get_passionfinder_positive_items_by_cmid($cmid, $userid);
    if (!$groups) {
        return '';
    }
    $html = '<div class="pdfpanel">';
    foreach ($groups as $category => $items) {
        $html .= '<h3>' . s($category) . '</h3><p>';
        foreach ($items as $item) {
            $html .= '<span style="color:#cf2e4c;">♥</span> ' . s($item['label']) . ' &nbsp; ';
        }
        $html .= '</p>';
    }
    $html .= '</div>';
    return $html;
}

function experiencefinder_pdf_render_skillsfinder(int $cmid, int $userid): string {
    $rows = experiencefinder_get_skillsfinder_top_competencies_by_cmid($cmid, $userid, 5);
    if (!$rows) {
        return '';
    }
    $html = '<table cellpadding="5" cellspacing="0" border="1" style="width:100%; border-color:#d8e5ea;">';
    foreach ($rows as $row) {
        $html .= '<tr>';
        $html .= '<td width="24%" bgcolor="#eaf4ff"><strong>' . s($row['title']) . '</strong><br /><span style="color:#375a7f;">' . s((string)$row['average']) . '</span></td>';
        $html .= '<td width="76%">' . s($row['description']) . '</td>';
        $html .= '</tr>';
    }
    $html .= '</table>';
    return '<div class="pdfpanel">' . $html . '</div>';
}

function experiencefinder_pdf_render_personalityfinder(int $cmid, int $userid): string {
    $matrix = experiencefinder_get_personalityfinder_matrix_by_cmid($cmid, $userid);
    if (!$matrix) {
        return '';
    }

    $left = (string)($matrix['horizontal_left_label'] ?? 'Left');
    $right = (string)($matrix['horizontal_right_label'] ?? 'Right');
    $top = (string)($matrix['vertical_top_label'] ?? 'Top');
    $bottom = (string)($matrix['vertical_bottom_label'] ?? 'Bottom');
    $quadrants = is_array($matrix['quadrants'] ?? null) ? $matrix['quadrants'] : [];
    $selected = is_array($matrix['selected'] ?? null) ? $matrix['selected'] : [];

    $bypos = [];
    foreach ($quadrants as $q) {
        if (is_array($q) && !empty($q['position'])) {
            $bypos[(string)$q['position']] = $q;
        }
    }

    $cell = function(string $pos, string $bg) use ($bypos): string {
        $q = $bypos[$pos] ?? [];
        $roman = s((string)($q['roman'] ?? ''));
        $heading = s((string)($q['heading'] ?? ''));
        $label = s((string)($q['label'] ?? ''));
        $summary = s((string)($q['summary'] ?? ''));
        $sel = !empty($q['selected']) ? ' border:2px solid #c2410c;' : '';
        return '<td width="50%" bgcolor="' . $bg . '" style="height:92px; text-align:center;' . $sel . '">' .
            '<span style="color:#88a; font-size:9pt;">' . $roman . '</span><br />' .
            '<strong style="color:#063f65;">' . $heading . '</strong><br />' .
            '<strong>' . $label . '</strong><br />' .
            '<span style="font-size:8.5pt;">' . $summary . '</span>' .
            '</td>';
    };

    $html = '<div class="pdfpanel">';
    $html .= '<table cellpadding="4" cellspacing="0" border="0" style="width:100%;"><tr><td align="left"><strong>' . s($left) . '</strong></td><td align="right"><strong>' . s($right) . '</strong></td></tr></table>';
    $html .= '<table cellpadding="5" cellspacing="0" border="1" style="width:100%; border-color:#0f4f86;">';
    $html .= '<tr>' . $cell('top-left', '#eff6ff') . $cell('top-right', '#f0fdf4') . '</tr>';
    $html .= '<tr>' . $cell('bottom-left', '#fef2f2') . $cell('bottom-right', '#fffbeb') . '</tr>';
    $html .= '</table>';
    $html .= '<table cellpadding="4" cellspacing="0" border="0" style="width:100%;"><tr><td align="left"><strong>' . s($top) . '</strong></td><td align="right"><strong>' . s($bottom) . '</strong></td></tr></table>';

    if (!empty($selected)) {
        $html .= '<div style="border-left:3px solid #2b7dbc; background-color:#f5faff; padding:8px; margin-top:8px;">';
        if (!empty($selected['label'])) {
            $html .= '<h3>' . s((string)$selected['label']) . '</h3>';
        }
        if (!empty($selected['summary'])) {
            $html .= '<p>' . s((string)$selected['summary']) . '</p>';
        }
        if (!empty($selected['assignments'])) {
            $html .= '<p><strong>' . s(get_string('possibleassignments', 'experiencefinder')) . ':</strong> ' . s((string)$selected['assignments']) . '</p>';
        }
        if (!empty($selected['prompt'])) {
            $html .= '<p>' . s((string)$selected['prompt']) . '</p>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

function experiencefinder_pdf_render_collage(stdClass $section, int $responseid, stdClass $experiencefinder, context_module $context): string {
    $assets = experiencefinder_get_collage_library((int)$experiencefinder->id);
    $items = experiencefinder_get_collage_items($responseid, (int)$section->id);
    if (!$items) {
        return '<p style="color:#777;">' . s(get_string('collageempty', 'experiencefinder')) . '</p>';
    }

    // Load the gallery file area once instead of looking up each image inside the item loop.
    $galleryfiles = [];
    $fs = get_file_storage();
    foreach ($fs->get_area_files($context->id, 'mod_experiencefinder', 'collagegallery', 0, 'filename', false) as $galleryfile) {
        if (!$galleryfile->is_directory()) {
            $galleryfiles[$galleryfile->get_filename()] = $galleryfile;
        }
    }

    /*
     * PDF-safe collage output.
     *
     * The browser collage is an interactive canvas. TCPDF is not a browser and
     * does not faithfully honour the same pixel/CSS scaling model. Previous
     * attempts tried to rebuild the exact browser layout, but the saved browser
     * scale values caused the artwork to become unreadably tiny in the PDF.
     *
     * This version deliberately favours readability: it outputs the selected
     * collage images at a sensible fixed PDF size. The PDF therefore preserves
     * the participant's selected visual vocabulary even when exact drag positions
     * cannot be reproduced safely by TCPDF.
     */
    $html = '<div class="pdfpanel">';
    $html .= '<table cellpadding="8" cellspacing="0" border="0" style="width:100%;">';

    $col = 0;
    $rendered = 0;
    foreach ($items as $item) {
        $asset = $assets[$item->filename] ?? null;
        if (!$asset) {
            continue;
        }

        $bytes = experiencefinder_pdf_asset_bytes($asset, $context, $galleryfiles);
        if (!$bytes) {
            continue;
        }

        if ($col % 2 === 0) {
            $html .= '<tr>';
        }

        // TCPDF cannot reliably render arbitrary SVG files, especially SVGs
        // with filters, embedded images or PowerPoint/AI-generated markup.
        // Do not pass SVG data into <img> for the PDF: it can crash PDF creation.
        $ext = strtolower(pathinfo($bytes['filename'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['png', 'jpg', 'jpeg'], true)) {
            continue;
        }

        $src = experiencefinder_pdf_data_uri_from_bytes($bytes);
        $html .= '<td width="50%" align="center" valign="middle" style="height:42mm;">';
        $html .= '<img src="' . $src . '" style="width:36mm; max-height:33mm;" />';
        $html .= '</td>';

        $col++;
        $rendered++;

        if ($col % 2 === 0) {
            $html .= '</tr>';
        }
    }

    if ($col % 2 !== 0) {
        $html .= '<td width="50%">&nbsp;</td></tr>';
    }

    $html .= '</table>';

    if ($rendered === 0) {
        $html .= '<p style="color:#777;">' . s(get_string('collageempty', 'experiencefinder')) . '</p>';
    } else {
        $html .= '<p style="color:#777; font-size:8.5pt;">' .
            'Note: SVG and WebP collage images are displayed in the browser, but only PNG/JPG images are included in this PDF version to avoid TCPDF rendering errors.' .
            '</p>';
    }

    $html .= '</div>';
    return $html;
}

function experiencefinder_pdf_render_section_result_panel(stdClass $section, int $userid, ?stdClass $response, stdClass $experiencefinder, context_module $context): string {
    $type = $section->resulttype ?? 'none';
    $cmid = (int)($section->resultcmid ?? 0);
    if ($type === 'collage' && $response) {
        return experiencefinder_pdf_render_collage($section, (int)$response->id, $experiencefinder, $context);
    }
    if ($type === 'none' || $cmid <= 0) {
        return '';
    }
    if ($type === 'selfprofile') {
        return experiencefinder_pdf_render_selfprofile($cmid, $userid);
    }
    if ($type === 'passionfinder') {
        return experiencefinder_pdf_render_passionfinder($cmid, $userid);
    }
    if ($type === 'skillsfinder') {
        return experiencefinder_pdf_render_skillsfinder($cmid, $userid);
    }
    if ($type === 'personalityfinder') {
        return experiencefinder_pdf_render_personalityfinder($cmid, $userid);
    }
    return '';
}

function experiencefinder_pdf_build_html(stdClass $experiencefinder, stdClass $course, stdClass $participant, ?stdClass $response, context_module $context): string {
    global $DB;
    $pdfcss = file_get_contents(__DIR__ . '/pdfstyles.css');
    $html = '<style>' . $pdfcss . '</style><div class="mod-experiencefinder-pdf">';

    $html .= '<h1>' . s(get_string('shapeportfolio', 'experiencefinder')) . '</h1>';
    $html .= '<p class="meta"><strong>' . s(get_string('participant', 'experiencefinder')) . ':</strong> ' . s(fullname($participant)) . '<br />';
    $html .= '<strong>' . s(get_string('course')) . ':</strong> ' . s(format_string($course->fullname)) . '<br />';
    $html .= '<strong>' . s(get_string('dategenerated', 'experiencefinder')) . ':</strong> ' . s(userdate(time())) . '</p>';

    if (!empty($experiencefinder->shapeintro)) {
        $html .= format_text($experiencefinder->shapeintro, $experiencefinder->shapeintroformat, ['context' => $context]);
    }

    $answers = [];
    $selections = [];
    if ($response) {
        $answers = $DB->get_records_menu('experiencefinder_answers', ['responseid' => $response->id], '', 'questionid, answertext');
        $selections = $DB->get_records_menu('experiencefinder_selections', ['responseid' => $response->id], '', 'promptid, id');
    }

    $sections = $DB->get_records('experiencefinder_sections', ['experiencefinderid' => $experiencefinder->id], 'sortorder ASC, id ASC');

    $allstoredprompts = $DB->get_records_sql("SELECT p.*
                                                FROM {experiencefinder_prompts} p
                                                JOIN {experiencefinder_sections} s ON s.id = p.sectionid
                                               WHERE s.experiencefinderid = ? AND p.storeselection = 1
                                            ORDER BY p.sectionid, p.sortorder, p.id", [$experiencefinder->id]);
    $storedpromptsbysection = [];
    foreach ($allstoredprompts as $prompt) {
        $storedpromptsbysection[$prompt->sectionid][] = $prompt;
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

    foreach ($sections as $section) {
        // Start the Visual Collage section on a fresh PDF page.
        // This prevents the heading and collage from being split awkwardly.
        if (($section->resulttype ?? 'none') === 'collage') {
            $html .= '<br pagebreak="true" />';
        }

        $html .= '<h2>' . s(format_string($section->name)) . '</h2>';
        if (!empty($section->description)) {
            $html .= format_text($section->description, $section->descriptionformat, ['context' => $context]);
        }

        $panel = experiencefinder_pdf_render_section_result_panel($section, (int)$participant->id, $response ?: null, $experiencefinder, $context);
        if ($panel) {
            $html .= $panel;
        }

        $storedprompts = $storedpromptsbysection[$section->id] ?? [];
        $selectedlabels = [];
        foreach ($storedprompts as $prompt) {
            if (isset($selections[$prompt->id])) {
                $selectedlabels[] = format_string($prompt->prompttext);
            }
        }
        if ($selectedlabels) {
            $html .= '<h3>' . s(get_string('prompts', 'experiencefinder')) . '</h3><p>';
            foreach ($selectedlabels as $label) {
                $html .= '<span class="pill">' . s($label) . '</span> ';
            }
            $html .= '</p>';
        }

        $questions = $questionsbysection[$section->id] ?? [];
        foreach ($questions as $question) {
            $answer = trim($answers[$question->id] ?? '');
            $html .= '<h3>' . s(format_string($question->questiontext)) . '</h3>';
            $html .= '<div class="answer">' . ($answer !== '' ? nl2br(s($answer)) : '&nbsp;') . '</div>';
        }
    }

    $html .= '</div>';
    return $html;
}

$PAGE->set_url('/mod/experiencefinder/pdf.php', ['id' => $cm->id, 'userid' => $userid]);

$pdf = new pdf(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('Moodle ExperienceFinder');
$pdf->SetAuthor(fullname($participant));
$pdf->SetTitle(format_string($experiencefinder->name) . ' - ' . get_string('shapeportfolio', 'experiencefinder'));
$pdf->SetSubject(get_string('shapeportfolio', 'experiencefinder'));
$pdf->SetMargins(14, 14, 14);
$pdf->SetAutoPageBreak(true, 14);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->AddPage();

$html = experiencefinder_pdf_build_html($experiencefinder, $course, $participant, $response ?: null, $context);
$pdf->writeHTML($html, true, false, true, false, '');

$filename = clean_filename(format_string($experiencefinder->name) . '-' . fullname($participant) . '-portfolio.pdf');
$pdf->Output($filename, 'D');
