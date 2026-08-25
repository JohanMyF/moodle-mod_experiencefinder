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

defined('MOODLE_INTERNAL') || die();

function experiencefinder_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        default:
            return null;
    }
}

/** Filemanager options for teacher-uploaded visual collage gallery files. */
function experiencefinder_collage_filemanager_options(): array {
    return [
        'subdirs' => 0,
        'maxbytes' => 0,
        'maxfiles' => -1,
        'accepted_types' => ['.png', '.svg', '.jpg', '.jpeg', '.webp'],
        'return_types' => FILE_INTERNAL,
    ];
}

/** Serve teacher-uploaded collage gallery files. */
function experiencefinder_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel !== CONTEXT_MODULE || $filearea !== 'collagegallery') {
        return false;
    }
    require_login($course, true, $cm);

    // The URL created by make_pluginfile_url() includes the itemid as the
    // first argument after the filearea. For this gallery we always use
    // itemid 0, so remove it before building the filepath. Without this,
    // Moodle looks for files in filepath /0/ and teacher-uploaded images
    // appear as broken thumbnails in the collage picker.
    $itemid = 0;
    if (!empty($args) && is_numeric($args[0])) {
        $itemid = (int)array_shift($args);
    }

    $filename = array_pop($args);
    if ($filename === null) {
        return false;
    }
    $filepath = '/' . implode('/', $args) . '/';
    if ($filepath === '//') {
        $filepath = '/';
    }

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_experiencefinder', 'collagegallery', $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, false, $options);
}

function experiencefinder_add_instance($data, $mform = null) {
    global $DB;
    $data->timecreated = time();
    $data->timemodified = time();
    if (isset($data->shapeintro_editor)) {
        $data->shapeintro = $data->shapeintro_editor['text'];
        $data->shapeintroformat = $data->shapeintro_editor['format'];
    }
    $id = $DB->insert_record('experiencefinder', $data);
    if (!empty($data->collagegallery_filemanager) && !empty($data->coursemodule)) {
        $context = context_module::instance($data->coursemodule);
        file_save_draft_area_files($data->collagegallery_filemanager, $context->id, 'mod_experiencefinder', 'collagegallery', 0,
            experiencefinder_collage_filemanager_options());
    }
    return $id;
}

function experiencefinder_update_instance($data, $mform = null) {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    if (isset($data->shapeintro_editor)) {
        $data->shapeintro = $data->shapeintro_editor['text'];
        $data->shapeintroformat = $data->shapeintro_editor['format'];
    }
    if (!empty($data->collagegallery_filemanager) && !empty($data->coursemodule)) {
        $context = context_module::instance($data->coursemodule);
        file_save_draft_area_files($data->collagegallery_filemanager, $context->id, 'mod_experiencefinder', 'collagegallery', 0,
            experiencefinder_collage_filemanager_options());
    }
    return $DB->update_record('experiencefinder', $data);
}

function experiencefinder_delete_instance($id) {
    global $DB;
    if (!$experiencefinder = $DB->get_record('experiencefinder', ['id' => $id])) {
        return false;
    }
    $sections = $DB->get_records('experiencefinder_sections', ['experiencefinderid' => $id]);
    foreach ($sections as $section) {
        $DB->delete_records('experiencefinder_prompts', ['sectionid' => $section->id]);
        $DB->delete_records('experiencefinder_questions', ['sectionid' => $section->id]);
    }
    $responses = $DB->get_records('experiencefinder_responses', ['experiencefinderid' => $id]);
    foreach ($responses as $response) {
        $DB->delete_records('experiencefinder_answers', ['responseid' => $response->id]);
        $DB->delete_records('experiencefinder_selections', ['responseid' => $response->id]);
        $DB->delete_records('experiencefinder_collage_items', ['responseid' => $response->id]);
    }
    $DB->delete_records('experiencefinder_responses', ['experiencefinderid' => $id]);
    $DB->delete_records('experiencefinder_sections', ['experiencefinderid' => $id]);
    $DB->delete_records('experiencefinder', ['id' => $id]);
    return true;
}

function experiencefinder_cm_info_view(cm_info $cm) {
    global $DB;
    if ($experiencefinder = $DB->get_record('experiencefinder', ['id' => $cm->instance], 'id, intro, introformat', IGNORE_MISSING)) {
        if (!empty($experiencefinder->intro)) {
            $cm->set_content(format_module_intro('experiencefinder', $experiencefinder, $cm->id, false));
        }
    }
}

function experiencefinder_get_response($experiencefinderid, $userid) {
    global $DB;
    if ($response = $DB->get_record('experiencefinder_responses', ['experiencefinderid' => $experiencefinderid, 'userid' => $userid])) {
        return $response;
    }
    $response = (object)[
        'experiencefinderid' => $experiencefinderid,
        'userid' => $userid,
        'timecreated' => time(),
        'timemodified' => time(),
    ];
    $response->id = $DB->insert_record('experiencefinder_responses', $response);
    return $response;
}

/**
 * Get the top SelfProfile categories for a linked SelfProfile course module.
 *
 * The first framework keeps this deliberately defensive because SelfProfile may
 * evolve. It tries the most common sibling table shape:
 * selfprofile_categories -> selfprofile_items -> selfprofile_answers.
 *
 * @param stdClass $experiencefinder The ExperienceFinder instance.
 * @param int $userid User id whose SelfProfile results should be read.
 * @param int $limit Maximum number of categories to return.
 * @return array Each item contains name, score and percent.
 */
function experiencefinder_get_selfprofile_top_categories(stdClass $experiencefinder, int $userid, int $limit = 5): array {
    if (empty($experiencefinder->selfprofilecmid)) {
        return [];
    }
    return experiencefinder_get_selfprofile_top_categories_by_cmid((int)$experiencefinder->selfprofilecmid, $userid, $limit);
}

function experiencefinder_get_selfprofile_top_categories_by_cmid(int $selfprofilecmid, int $userid, int $limit = 5): array {
    global $DB;

    if (empty($selfprofilecmid)) {
        return [];
    }

    $cm = get_coursemodule_from_id('selfprofile', $selfprofilecmid, 0, false, IGNORE_MISSING);
    if (!$cm) {
        return [];
    }
    $selfprofileid = (int)$cm->instance;

    $dbman = $DB->get_manager();
    foreach (['selfprofile_categories', 'selfprofile_statements', 'selfprofile_attempts', 'selfprofile_responses'] as $tablename) {
        if (!$dbman->table_exists(new xmldb_table($tablename))) {
            return [];
        }
    }

    // SelfProfile stores one submitted attempt per participant, then one response per statement.
    // This mirrors mod_selfprofile/results.php rather than guessing at an answer table.
    $attempt = $DB->get_record('selfprofile_attempts', [
        'selfprofileid' => $selfprofileid,
        'userid' => $userid,
    ], '*', IGNORE_MULTIPLE);

    if (!$attempt) {
        $attempts = $DB->get_records('selfprofile_attempts', [
            'selfprofileid' => $selfprofileid,
            'userid' => $userid,
        ], 'timemodified DESC, id DESC', '*', 0, 1);
        $attempt = reset($attempts);
    }

    if (!$attempt || (isset($attempt->status) && $attempt->status !== 'submitted')) {
        return [];
    }

    $scale = json_decode($DB->get_field('selfprofile', 'scalejson', ['id' => $selfprofileid]) ?? '');
    $minscale = 0;
    $maxscale = 5;
    $scalerange = 5;

    if (json_last_error() === JSON_ERROR_NONE && is_array($scale) && !empty($scale)) {
        $values = [];
        foreach ($scale as $scaleitem) {
            if (is_object($scaleitem) && isset($scaleitem->value) && is_numeric($scaleitem->value)) {
                $values[] = (float)$scaleitem->value;
            }
        }
        if (!empty($values)) {
            $minscale = min($values);
            $maxscale = max($values);
            $scalerange = max(1, $maxscale - $minscale);
        }
    }

    $sql = "SELECT c.id,
                   c.name,
                   c.description,
                   AVG(CASE
                       WHEN s.scoringdirection = 'reverse'
                       THEN (:scalemax + :scalemin - r.scalevalue)
                       ELSE r.scalevalue
                   END) AS averagescore,
                   COUNT(r.id) AS responsecount
              FROM {selfprofile_categories} c
              JOIN {selfprofile_statements} s
                ON s.categoryid = c.id
               AND s.enabled = 1
              JOIN {selfprofile_responses} r
                ON r.statementid = s.id
               AND r.attemptid = :attemptid
             WHERE c.selfprofileid = :selfprofileid
          GROUP BY c.id, c.name, c.description, c.sortorder
          ORDER BY averagescore DESC, c.sortorder ASC";

    $results = $DB->get_records_sql($sql, [
        'attemptid' => $attempt->id,
        'selfprofileid' => $selfprofileid,
        'scalemax' => $maxscale,
        'scalemin' => $minscale,
    ], 0, $limit);

    $rows = [];
    foreach ($results as $result) {
        if ((int)$result->responsecount === 0 || $result->averagescore === null) {
            continue;
        }
        $average = round((float)$result->averagescore, 2);
        $percent = round((max(0, $average - $minscale) / $scalerange) * 100);
        $rows[] = [
            'name' => format_string($result->name),
            'score' => $average,
            'percent' => max(5, min(100, $percent)),
        ];
    }

    return $rows;
}


/**
 * Get positive PassionFinder results for a linked PassionFinder course module.
 *
 * ExperienceFinder deliberately uses only positive preference signals here.
 * The detailed MaxDiff-style table remains in PassionFinder; the SHAPE portfolio
 * only needs the people, causes, activities or contexts that appear to energise
 * the participant.
 *
 * @param int $passionfindercmid Linked PassionFinder course module id.
 * @param int $userid Participant user id.
 * @return array Grouped by category name. Each item contains label, score and rank.
 */
function experiencefinder_get_passionfinder_positive_items_by_cmid(int $passionfindercmid, int $userid): array {
    global $DB;

    if (empty($passionfindercmid)) {
        return [];
    }

    $cm = get_coursemodule_from_id('passionfinder', $passionfindercmid, 0, false, IGNORE_MISSING);
    if (!$cm) {
        return [];
    }
    $passionfinderid = (int)$cm->instance;

    $dbman = $DB->get_manager();
    foreach (['passionfinder', 'passionfinder_attempts', 'passionfinder_results'] as $tablename) {
        if (!$dbman->table_exists(new xmldb_table($tablename))) {
            return [];
        }
    }

    $passionfinder = $DB->get_record('passionfinder', ['id' => $passionfinderid], '*', IGNORE_MISSING);
    if (!$passionfinder) {
        return [];
    }

    $attempts = $DB->get_records('passionfinder_attempts', [
        'passionfinderid' => $passionfinderid,
        'userid' => $userid,
    ], 'completedtime DESC, timemodified DESC, id DESC', '*', 0, 1);

    if (empty($attempts)) {
        return [];
    }
    $attempt = reset($attempts);

    $sql = "SELECT id,
                   categoryid,
                   itemid,
                   appearances,
                   mostcount,
                   leastcount,
                   netscore,
                   normalisedscore,
                   rank
              FROM {passionfinder_results}
             WHERE attemptid = :attemptid
               AND normalisedscore > 0
          ORDER BY categoryid ASC, rank ASC, normalisedscore DESC, itemid ASC";

    $records = $DB->get_records_sql($sql, ['attemptid' => $attempt->id]);
    if (!$records) {
        return [];
    }

    $labels = experiencefinder_passionfinder_label_maps($passionfinder);

    $groups = [];
    foreach ($records as $record) {
        $categoryid = (string)$record->categoryid;
        $itemid = (string)$record->itemid;
        $category = $labels['categories'][$categoryid] ?? $categoryid;
        $itemlabel = $labels['items'][$itemid] ?? $itemid;

        if (!isset($groups[$category])) {
            $groups[$category] = [];
        }
        $groups[$category][] = [
            'label' => format_string($itemlabel),
            'score' => round((float)$record->normalisedscore, 2),
            'rank' => (int)$record->rank,
        ];
    }

    return $groups;
}

/** Build readable category and item labels from a PassionFinder instrument JSON definition. */
function experiencefinder_passionfinder_label_maps(stdClass $passionfinder): array {
    $maps = [
        'categories' => [],
        'items' => [],
    ];

    if (empty($passionfinder->instrumentjson)) {
        return $maps;
    }

    $instrument = json_decode($passionfinder->instrumentjson);
    if (json_last_error() !== JSON_ERROR_NONE || !is_object($instrument)) {
        return $maps;
    }

    if (empty($instrument->categories) || !is_array($instrument->categories)) {
        return $maps;
    }

    foreach ($instrument->categories as $category) {
        if (!is_object($category) || empty($category->id)) {
            continue;
        }
        $categoryid = (string)$category->id;
        $maps['categories'][$categoryid] = !empty($category->name) ? (string)$category->name : $categoryid;

        if (empty($category->items) || !is_array($category->items)) {
            continue;
        }
        foreach ($category->items as $item) {
            if (!is_object($item) || empty($item->id)) {
                continue;
            }
            $itemid = (string)$item->id;
            $maps['items'][$itemid] = !empty($item->label) ? (string)$item->label : $itemid;
        }
    }

    return $maps;
}

/** Render the positive PassionFinder items as a warm animated heart list. */
function experiencefinder_render_passionfinder_panel_by_cmid(int $cmid, int $userid): string {
    $groups = experiencefinder_get_passionfinder_positive_items_by_cmid($cmid, $userid);
    if (!$groups) {
        if (!empty($cmid)) {
            return html_writer::div(get_string('passionfinderlinkednoresults', 'experiencefinder'), 'ef-placeholder ef-sibling-panel');
        }
        return '';
    }

    $out = html_writer::start_div('ef-sibling-panel ef-passionfinder-panel ef-animate-hearts', ['data-ef-animated' => '0']);
    $index = 0;
    foreach ($groups as $category => $items) {
        $out .= html_writer::start_div('ef-pf-group');
        $out .= html_writer::tag('h4', s($category), ['class' => 'ef-pf-category']);
        $out .= html_writer::start_tag('ul', ['class' => 'ef-pf-list']);
        foreach ($items as $item) {
            $delay = 0;
            $heart = html_writer::span('♥', 'ef-pf-heart', ['aria-hidden' => 'true']);
            $label = html_writer::span(s($item['label']), 'ef-pf-label');
            $out .= html_writer::tag('li', $heart . $label, [
                'class' => 'ef-pf-item',
                'style' => 'transition-delay:' . $delay . 's',
            ]);
            $index++;
        }
        $out .= html_writer::end_tag('ul');
        $out .= html_writer::end_div();
    }
    $out .= html_writer::end_div();
    return $out;
}

/** Return first matching field name in a table column array. */
function experiencefinder_first_existing_field(array $columns, array $candidates): ?string {
    foreach ($candidates as $candidate) {
        if (isset($columns[$candidate])) {
            return $candidate;
        }
    }
    return null;
}



/**
 * Get the top SkillsFinder competencies for a linked SkillsFinder course module.
 *
 * SkillsFinder stores its calculated report data as JSON on the participant
 * attempt. ExperienceFinder reads only the top competencies and presents them
 * as a visual strengths/abilities summary for the SHAPE portfolio.
 */
function experiencefinder_get_skillsfinder_top_competencies_by_cmid(int $skillsfindercmid, int $userid, int $limit = 5): array {
    global $DB;

    if (empty($skillsfindercmid)) {
        return [];
    }

    $cm = get_coursemodule_from_id('skillsfinder', $skillsfindercmid, 0, false, IGNORE_MISSING);
    if (!$cm) {
        return [];
    }
    $skillsfinderid = (int)$cm->instance;

    $dbman = $DB->get_manager();
    foreach (['skillsfinder', 'skillsfinder_attempts'] as $tablename) {
        if (!$dbman->table_exists(new xmldb_table($tablename))) {
            return [];
        }
    }

    $attempts = $DB->get_records('skillsfinder_attempts', [
        'skillsfinderid' => $skillsfinderid,
        'userid' => $userid,
    ], 'completedtime DESC, timemodified DESC, id DESC', '*', 0, 1);

    if (!$attempts) {
        return [];
    }
    $attempt = reset($attempts);

    if ((string)($attempt->status ?? '') !== 'completed' || empty($attempt->resultsjson)) {
        return [];
    }

    $results = json_decode((string)$attempt->resultsjson, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($results)) {
        return [];
    }

    $top = $results['topcompetencies'] ?? $results['competencies'] ?? [];
    if (!is_array($top) || empty($top)) {
        return [];
    }

    $rows = [];
    foreach (array_slice($top, 0, $limit) as $item) {
        if (!is_array($item) || empty($item['title'])) {
            continue;
        }
        $average = isset($item['average']) && is_numeric($item['average']) ? (float)$item['average'] : 0.0;
        $rows[] = [
            'title' => format_string((string)$item['title']),
            'description' => trim((string)($item['description'] ?? '')),
            'average' => round($average, 2),
            'custom' => !empty($item['custom']),
        ];
    }

    return $rows;
}

/** Render the top SkillsFinder competencies as animated ability bubbles. */
function experiencefinder_render_skillsfinder_panel_by_cmid(int $cmid, int $userid): string {
    $rows = experiencefinder_get_skillsfinder_top_competencies_by_cmid($cmid, $userid, 5);
    if (!$rows) {
        if (!empty($cmid)) {
            return html_writer::div(get_string('skillsfinderlinkednoresults', 'experiencefinder'), 'ef-placeholder ef-sibling-panel');
        }
        return '';
    }

    $max = 0.0;
    foreach ($rows as $row) {
        $max = max($max, (float)$row['average']);
    }
    $max = max(1.0, $max);

    $out = html_writer::start_div('ef-sibling-panel ef-skillsfinder-panel ef-animate-skills', ['data-ef-animated' => '0']);
    $out .= html_writer::start_div('ef-sf-cloud');
    foreach ($rows as $index => $row) {
        $ratio = max(0.45, min(1.0, ((float)$row['average'] / $max)));
        $size = 7.0 + ($ratio * 4.5); // rem.
        $delay = $index * 0.22;
        $desc = $row['description'] !== '' ? format_text($row['description'], FORMAT_PLAIN, ['para' => false]) : '';
        $content = html_writer::span(s($row['title']), 'ef-sf-title') .
            html_writer::span(s((string)$row['average']), 'ef-sf-score');
        if ($desc !== '') {
            $content .= html_writer::span($desc, 'ef-sf-desc');
        }
        $out .= html_writer::tag('div', $content, [
            'class' => 'ef-sf-bubble',
            'style' => 'width:' . $size . 'rem;height:' . $size . 'rem;transition-delay:' . $delay . 's',
        ]);
    }
    $out .= html_writer::end_div();
    $out .= html_writer::end_div();
    return $out;
}



/**
 * Get the saved PersonalityFinder matrix result for a linked PersonalityFinder course module.
 *
 * PersonalityFinder stores the calculated matrix in personalityfinder_responses.resultsjson.
 * ExperienceFinder reads that prepared result and renders only the 2x2 matrix and selected style.
 */
function experiencefinder_get_personalityfinder_matrix_by_cmid(int $personalityfindercmid, int $userid): array {
    global $DB;

    if (empty($personalityfindercmid)) {
        return [];
    }

    $cm = get_coursemodule_from_id('personalityfinder', $personalityfindercmid, 0, false, IGNORE_MISSING);
    if (!$cm) {
        return [];
    }
    $personalityfinderid = (int)$cm->instance;

    $dbman = $DB->get_manager();
    foreach (['personalityfinder', 'personalityfinder_responses'] as $tablename) {
        if (!$dbman->table_exists(new xmldb_table($tablename))) {
            return [];
        }
    }

    $responses = $DB->get_records('personalityfinder_responses', [
        'personalityfinderid' => $personalityfinderid,
        'userid' => $userid,
    ], 'timemodified DESC, id DESC', '*', 0, 1);
    $response = $responses ? reset($responses) : false;

    if (!$response) {
        return [];
    }

    // Prefer the matrix already calculated and stored by PersonalityFinder.
    if (!empty($response->resultsjson)) {
        $results = json_decode((string)$response->resultsjson, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($results)) {
            $matrix = $results['matrix'] ?? [];
            if (is_array($matrix) && !empty($matrix)) {
                return $matrix;
            }
        }
    }

    // Fallback for sites where an older/development response row exists without a readable
    // resultsjson value. Recalculate via PersonalityFinder's own result helper when available.
    if (empty($response->responsesjson)) {
        return [];
    }

    try {
        if (!class_exists('\mod_personalityfinder\local\config')
                || !class_exists('\mod_personalityfinder\local\results')) {
            return [];
        }
        $personalityfinder = $DB->get_record('personalityfinder', ['id' => $personalityfinderid], '*', IGNORE_MISSING);
        if (!$personalityfinder) {
            return [];
        }
        $config = \mod_personalityfinder\local\config::decode($personalityfinder->configjson ?? '');
        $raw = json_decode((string)$response->responsesjson, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($raw)) {
            return [];
        }
        $recalculated = \mod_personalityfinder\local\results::calculate($config, (object)$raw);
        $matrix = $recalculated['matrix'] ?? [];
        return (is_array($matrix) && !empty($matrix)) ? $matrix : [];
    } catch (Throwable $e) {
        return [];
    }
}

/** Render a PersonalityFinder 2x2 matrix with an animated crosshair/marker. */
function experiencefinder_render_personalityfinder_matrix_panel_by_cmid(int $cmid, int $userid): string {
    $matrix = experiencefinder_get_personalityfinder_matrix_by_cmid($cmid, $userid);
    if (!$matrix) {
        if (!empty($cmid)) {
            return html_writer::div(get_string('personalityfinderlinkednoresults', 'experiencefinder'), 'ef-placeholder ef-sibling-panel');
        }
        return '';
    }

    $left = (string)($matrix['horizontal_left_label'] ?? 'Left');
    $right = (string)($matrix['horizontal_right_label'] ?? 'Right');
    $top = (string)($matrix['vertical_top_label'] ?? 'Top');
    $bottom = (string)($matrix['vertical_bottom_label'] ?? 'Bottom');
    $markerleft = max(0, min(100, (float)($matrix['marker_left'] ?? 50)));
    $markertop = max(0, min(100, (float)($matrix['marker_top'] ?? 50)));
    $quadrants = is_array($matrix['quadrants'] ?? null) ? $matrix['quadrants'] : [];
    $selected = is_array($matrix['selected'] ?? null) ? $matrix['selected'] : [];

    $out = html_writer::start_div('ef-sibling-panel ef-personalityfinder-panel ef-animate-personality', [
        'data-ef-animated' => '0',
        'style' => '--ef-pm-left:' . $markerleft . '%; --ef-pm-top:' . $markertop . '%;',
    ]);

    $out .= html_writer::start_div('ef-pm-wrap');
    $out .= html_writer::div(s($left), 'ef-pm-axis ef-pm-axis-left');
    $out .= html_writer::div(s($right), 'ef-pm-axis ef-pm-axis-right');
    $out .= html_writer::div(s($top), 'ef-pm-axis ef-pm-axis-top');
    $out .= html_writer::div(s($bottom), 'ef-pm-axis ef-pm-axis-bottom');

    $out .= html_writer::start_div('ef-pm-matrix', ['role' => 'img', 'aria-label' => get_string('personalitymatrixarialabel', 'experiencefinder')]);
    foreach ($quadrants as $quadrant) {
        if (!is_array($quadrant)) {
            continue;
        }
        $position = preg_replace('/[^a-z\-]/', '', (string)($quadrant['position'] ?? ''));
        $classes = 'ef-pm-quadrant ef-pm-' . $position;
        if (!empty($quadrant['selected'])) {
            $classes .= ' ef-pm-selected';
        }
        $content = '';
        if (!empty($quadrant['roman'])) {
            $content .= html_writer::div(s((string)$quadrant['roman']), 'ef-pm-roman');
        }
        if (!empty($quadrant['heading'])) {
            $content .= html_writer::div(s((string)$quadrant['heading']), 'ef-pm-heading');
        }
        if (!empty($quadrant['label'])) {
            $content .= html_writer::div(s((string)$quadrant['label']), 'ef-pm-label');
        }
        if (!empty($quadrant['summary'])) {
            $content .= html_writer::div(s((string)$quadrant['summary']), 'ef-pm-summary');
        }
        $out .= html_writer::div($content, $classes);
    }
    $out .= html_writer::div('', 'ef-pm-axisline ef-pm-axisline-horizontal', ['aria-hidden' => 'true']);
    $out .= html_writer::div('', 'ef-pm-axisline ef-pm-axisline-vertical', ['aria-hidden' => 'true']);
    $out .= html_writer::tag('div',
        html_writer::span('', 'ef-pm-marker-dot'),
        ['class' => 'ef-pm-marker', 'aria-hidden' => 'true']
    );
    $out .= html_writer::end_div();
    $out .= html_writer::end_div();

    if (!empty($selected)) {
        $title = trim((string)($selected['label'] ?? ''));
        $summary = trim((string)($selected['summary'] ?? ''));
        $assignments = trim((string)($selected['assignments'] ?? ''));
        $prompt = trim((string)($selected['prompt'] ?? ''));
        $box = '';
        if ($title !== '') {
            $box .= html_writer::tag('h4', s($title));
        }
        if ($summary !== '') {
            $box .= html_writer::tag('p', s($summary));
        }
        if ($assignments !== '') {
            $box .= html_writer::tag('p', html_writer::tag('strong', get_string('possibleassignments', 'experiencefinder') . ': ') . s($assignments));
        }
        if ($prompt !== '') {
            $box .= html_writer::tag('p', s($prompt), ['class' => 'ef-muted']);
        }
        if ($box !== '') {
            $out .= html_writer::div($box, 'ef-pm-selected-card');
        }
    }

    $out .= html_writer::end_div();
    return $out;
}

/** Render all linked SHAPE summaries as a compact portfolio panel. */
function experiencefinder_render_shape_portfolio_panel(stdClass $experiencefinder, int $userid): string {
    $haslinks = !empty($experiencefinder->selfprofilecmid) || !empty($experiencefinder->passionfindercmid)
        || !empty($experiencefinder->skillsfindercmid) || !empty($experiencefinder->personalityfindercmid);

    if (!$haslinks) {
        return '';
    }

    $out = html_writer::start_div('ef-shape-portfolio');

    if (!empty($experiencefinder->shapeintro)) {
        $out .= html_writer::div(format_text($experiencefinder->shapeintro, $experiencefinder->shapeintroformat), 'ef-shape-intro');
    }

    $out .= experiencefinder_render_selfprofile_panel($experiencefinder, $userid);
    if (!empty($experiencefinder->passionfindercmid)) {
        $out .= experiencefinder_render_passionfinder_panel_by_cmid((int)$experiencefinder->passionfindercmid, $userid);
    }
    if (!empty($experiencefinder->skillsfindercmid)) {
        $out .= experiencefinder_render_skillsfinder_panel_by_cmid((int)$experiencefinder->skillsfindercmid, $userid);
    }
    if (!empty($experiencefinder->personalityfindercmid)) {
        $out .= experiencefinder_render_personalityfinder_matrix_panel_by_cmid((int)$experiencefinder->personalityfindercmid, $userid);
    }

    $out .= html_writer::end_div();
    return $out;
}

/** Render a temporary linked sibling placeholder until the sibling extractor is implemented. */
function experiencefinder_render_linked_placeholder(stdClass $experiencefinder, string $fieldname, string $titlestring): string {
    if (empty($experiencefinder->{$fieldname})) {
        return '';
    }

    return html_writer::div(
        html_writer::tag('h3', get_string($titlestring, 'experiencefinder')) .
        html_writer::div(get_string('linkedextractorpending', 'experiencefinder'), 'ef-muted'),
        'ef-sibling-panel ef-sibling-placeholder'
    );
}


/** Render a linked SHAPE result selected for a specific ExperienceFinder section. */

/** Return allowed filename extensions for visual collage assets. */
function experiencefinder_collage_allowed_extensions(): array {
    return ['svg', 'png', 'jpg', 'jpeg', 'webp'];
}

/** Resolve an ExperienceFinder activity instance id to its module context. */
function experiencefinder_get_context_by_instanceid(int $experiencefinderid): ?context_module {
    global $DB;
    if (!$experiencefinderid) {
        return null;
    }
    $sql = "SELECT cm.id
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
             WHERE m.name = ? AND cm.instance = ? AND cm.deletioninprogress = 0";
    $cmid = $DB->get_field_sql($sql, ['experiencefinder', $experiencefinderid], IGNORE_MULTIPLE);
    if (!$cmid) {
        return null;
    }
    return context_module::instance((int)$cmid, IGNORE_MISSING) ?: null;
}

/**
 * Return all available visual collage assets.
 *
 * This includes starter files shipped in pix/collage and teacher-uploaded files
 * in the activity's collagegallery file area. PNG files are deliberately
 * supported because AI-generated transparent PNG illustrations are often much
 * more expressive than hand-authored SVG geometry.
 *
 * @param int $experiencefinderid Optional instance id, needed for teacher-uploaded files.
 * @return array keyed by a stable asset key. Values contain key, label, url and source.
 */
function experiencefinder_get_collage_library(int $experiencefinderid = 0): array {
    global $CFG;

    $allowed = experiencefinder_collage_allowed_extensions();
    $items = [];

    $dir = $CFG->dirroot . '/mod/experiencefinder/pix/collage';
    if (is_dir($dir)) {
        foreach (scandir($dir) ?: [] as $basename) {
            if ($basename === '.' || $basename === '..') {
                continue;
            }
            $path = $dir . '/' . $basename;
            $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
            if (!is_file($path) || !in_array($ext, $allowed, true) || !preg_match('/^[a-zA-Z0-9_. -]+\.[a-zA-Z0-9]+$/', $basename)) {
                continue;
            }
            $key = 'default:' . $basename;
            $items[$key] = [
                'key' => $key,
                'label' => pathinfo($basename, PATHINFO_FILENAME),
                'url' => $CFG->wwwroot . '/mod/experiencefinder/pix/collage/' . rawurlencode($basename),
                'source' => 'default',
            ];
            // Backward compatibility for layouts saved before asset keys were namespaced.
            $items[$basename] = $items[$key];
            $items[$basename]['key'] = $basename;
        }
    }

    if ($context = experiencefinder_get_context_by_instanceid($experiencefinderid)) {
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_experiencefinder', 'collagegallery', 0, 'filename', false);
        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $filename = $file->get_filename();
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) {
                continue;
            }
            $key = 'gallery:' . $filename;
            $items[$key] = [
                'key' => $key,
                'label' => pathinfo($filename, PATHINFO_FILENAME),
                'url' => moodle_url::make_pluginfile_url($context->id, 'mod_experiencefinder', 'collagegallery', 0, '/', $filename)->out(false),
                'source' => 'gallery',
            ];
        }
    }

    uasort($items, function($a, $b) {
        return strnatcasecmp($a['label'], $b['label']);
    });
    return $items;
}

/** Render a scrollable builder preview of the collage library. */
function experiencefinder_render_collage_library_preview(int $experiencefinderid = 0): string {
    $files = experiencefinder_get_collage_library($experiencefinderid);
    if (!$files) {
        return html_writer::div(get_string('collagenolibrary', 'experiencefinder'), 'ef-placeholder');
    }
    $out = html_writer::start_div('ef-collage-library-preview');
    foreach ($files as $asset) {
        // Hide backward-compatible duplicate bare default keys from the preview.
        if ($asset['source'] === 'default' && strpos($asset['key'], ':') === false) {
            continue;
        }
        $out .= html_writer::div(
            html_writer::empty_tag('img', ['src' => $asset['url'], 'alt' => s($asset['label'])]) .
            html_writer::div(s($asset['label']), 'ef-collage-preview-name'),
            'ef-collage-preview-tile'
        );
    }
    $out .= html_writer::end_div();
    return $out;
}

/** Read the participant's saved collage layout for one section. */
function experiencefinder_get_collage_items(int $responseid, int $sectionid): array {
    global $DB;
    if (!$responseid || !$sectionid) {
        return [];
    }
    return array_values($DB->get_records('experiencefinder_collage_items', [
        'responseid' => $responseid,
        'sectionid' => $sectionid,
    ], 'zindex ASC, id ASC'));
}

/** Save a participant collage layout from JSON produced by the browser canvas. */
function experiencefinder_save_collage_items(int $responseid, int $sectionid, string $json): void {
    global $DB;

    $DB->delete_records('experiencefinder_collage_items', ['responseid' => $responseid, 'sectionid' => $sectionid]);
    $items = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($items)) {
        return;
    }

    $library = experiencefinder_get_collage_library((int)$sectionid ? (int)$DB->get_field('experiencefinder_sections', 'experiencefinderid', ['id' => $sectionid]) : 0);
    $now = time();
    $z = 1;
    foreach ($items as $item) {
        if (!is_array($item) || empty($item['file']) || !isset($library[$item['file']])) {
            continue;
        }
        $record = (object)[
            'responseid' => $responseid,
            'sectionid' => $sectionid,
            'filename' => (string)$item['file'],
            'xpos' => max(0, min(1000, (int)($item['x'] ?? 100))),
            'ypos' => max(0, min(600, (int)($item['y'] ?? 100))),
            'width' => max(80, min(650, (int)($item['w'] ?? 220))),
            'zindex' => $z++,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $DB->insert_record('experiencefinder_collage_items', $record);
    }
}


/** Save a rendered PNG snapshot of a participant collage for faithful PDF export. */
function experiencefinder_save_collage_snapshot(int $responseid, int $sectionid, string $datauri): void {
    global $DB;

    $datauri = trim($datauri);
    if ($datauri === '' || strpos($datauri, 'data:image/png;base64,') !== 0) {
        return;
    }
    // Guard against unexpectedly huge submissions. About 8MB base64 is plenty for a PDF snapshot.
    if (strlen($datauri) > 8388608) {
        return;
    }

    $record = $DB->get_record('experiencefinder_collage_snapshots', ['responseid' => $responseid, 'sectionid' => $sectionid]);
    $now = time();
    if ($record) {
        $record->pngdata = $datauri;
        $record->timemodified = $now;
        $DB->update_record('experiencefinder_collage_snapshots', $record);
    } else {
        $DB->insert_record('experiencefinder_collage_snapshots', (object)[
            'responseid' => $responseid,
            'sectionid' => $sectionid,
            'pngdata' => $datauri,
            'timemodified' => $now,
        ]);
    }
}

/** Return a rendered PNG snapshot for one collage section if available. */
function experiencefinder_get_collage_snapshot(int $responseid, int $sectionid): string {
    global $DB;
    $snapshot = $DB->get_record('experiencefinder_collage_snapshots', ['responseid' => $responseid, 'sectionid' => $sectionid]);
    return $snapshot ? (string)$snapshot->pngdata : '';
}

/** Render an editable or read-only Visual Collage panel for a participant. */
function experiencefinder_render_visual_collage_panel(stdClass $section, int $responseid, bool $readonly = false): string {
    global $CFG;
    $files = experiencefinder_get_collage_library((int)$section->experiencefinderid);
    $items = experiencefinder_get_collage_items($responseid, (int)$section->id);
    $jsonitems = [];
    foreach ($items as $item) {
        $jsonitems[] = [
            'file' => $item->filename,
            'x' => (int)$item->xpos,
            'y' => (int)$item->ypos,
            'w' => (int)$item->width,
        ];
    }

    $classes = 'ef-sibling-panel ef-collage-panel' . ($readonly ? ' ef-collage-readonly' : '');
    $out = html_writer::start_div($classes, ['data-section-id' => (int)$section->id]);

    if (!$readonly) {
        $out .= html_writer::div(get_string('collageinstructions', 'experiencefinder'), 'ef-note');
    }

    if (!$readonly) {
        $out .= html_writer::tag('h4', get_string('collageavailable', 'experiencefinder'));
        if ($files) {
            $out .= html_writer::start_div('ef-collage-library');
            foreach ($files as $asset) {
                if ($asset['source'] === 'default' && strpos($asset['key'], ':') === false) {
                    continue;
                }
                $out .= html_writer::tag('button',
                    html_writer::empty_tag('img', ['src' => $asset['url'], 'alt' => s($asset['label'])]),
                    ['type' => 'button', 'class' => 'ef-collage-choice', 'data-file' => $asset['key'], 'data-src' => $asset['url'], 'title' => s($asset['label']), 'aria-label' => get_string('collageadd', 'experiencefinder') . ': ' . s($asset['label'])]
                );
            }
            $out .= html_writer::end_div();
        } else {
            $out .= html_writer::div(get_string('collagenolibrary', 'experiencefinder'), 'ef-placeholder');
        }
    }

    $out .= html_writer::tag('h4', get_string('collagecanvas', 'experiencefinder'));
    $out .= html_writer::start_div('ef-collage-canvas', ['data-wwwroot' => $CFG->wwwroot]);
    foreach ($jsonitems as $index => $item) {
        $asset = $files[$item['file']] ?? null;
        if (!$asset) {
            continue;
        }
        $style = 'left:' . ($item['x'] / 10) . '%;top:' . ($item['y'] / 6) . '%;width:' . ($item['w'] / 10) . '%;z-index:' . ($index + 1) . ';';
        $img = html_writer::empty_tag('img', ['src' => $asset['url'], 'alt' => s($asset['label']), 'draggable' => 'false']);
        if (!$readonly) {
            $img .= html_writer::span('', 'ef-collage-resize', ['aria-hidden' => 'true']);
        }
        $out .= html_writer::div($img, 'ef-collage-item', ['data-file' => $item['file'], 'style' => $style]);
    }
    if ($readonly && empty($jsonitems)) {
        $out .= html_writer::div(get_string('collageempty', 'experiencefinder'), 'ef-muted ef-collage-empty');
    }
    $out .= html_writer::end_div();

    if (!$readonly) {
        $out .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'collage_' . (int)$section->id,
            'class' => 'ef-collage-data',
            'value' => json_encode($jsonitems),
        ]);
        $out .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'collage_snapshot_' . (int)$section->id,
            'class' => 'ef-collage-snapshot',
            'value' => experiencefinder_get_collage_snapshot($responseid, (int)$section->id),
        ]);
        $out .= html_writer::tag('button', get_string('collageremove', 'experiencefinder'), ['type' => 'button', 'class' => 'btn btn-secondary btn-sm ef-collage-remove']);
    }

    $out .= html_writer::end_div();
    return $out;
}

function experiencefinder_render_section_result_panel(stdClass $section, int $userid): string {
    $type = $section->resulttype ?? 'none';
    $cmid = (int)($section->resultcmid ?? 0);

    if ($type === 'collage') {
        $response = experiencefinder_get_response((int)$section->experiencefinderid, $userid);
        return experiencefinder_render_visual_collage_panel($section, (int)$response->id, false);
    }

    if ($type === 'none' || $cmid <= 0) {
        return '';
    }

    if ($type === 'selfprofile') {
        return experiencefinder_render_selfprofile_panel_by_cmid($cmid, $userid);
    }

    if ($type === 'passionfinder') {
        return experiencefinder_render_passionfinder_panel_by_cmid($cmid, $userid);
    }

    if ($type === 'skillsfinder') {
        return experiencefinder_render_skillsfinder_panel_by_cmid($cmid, $userid);
    }

    if ($type === 'personalityfinder') {
        return experiencefinder_render_personalityfinder_matrix_panel_by_cmid($cmid, $userid);
    }
    return '';
}

/** Render a compact SelfProfile panel for browser and PDF views. */
function experiencefinder_render_selfprofile_panel(stdClass $experiencefinder, int $userid): string {
    $cmid = !empty($experiencefinder->selfprofilecmid) ? (int)$experiencefinder->selfprofilecmid : 0;
    return experiencefinder_render_selfprofile_panel_by_cmid($cmid, $userid);
}

function experiencefinder_render_selfprofile_panel_by_cmid(int $cmid, int $userid): string {
    $rows = experiencefinder_get_selfprofile_top_categories_by_cmid($cmid, $userid, 5);
    if (!$rows) {
        if (!empty($cmid)) {
            return html_writer::div(get_string('selfprofilelinkednoresults', 'experiencefinder'), 'ef-placeholder ef-sibling-panel');
        }
        return '';
    }

    $out = html_writer::start_div('ef-sibling-panel ef-selfprofile-panel');
    $out .= html_writer::start_div('ef-selfprofile-bars ef-animate-bars', ['data-ef-animated' => '0']);
    $i = 0;
    foreach ($rows as $row) {
        $delay = $i * 0.33;
        $out .= html_writer::start_div('ef-sp-row', ['style' => 'transition-delay:' . $delay . 's']);
        $out .= html_writer::div(s($row['name']), 'ef-sp-label');
        $out .= html_writer::start_div('ef-sp-track');
        $out .= html_writer::div('', 'ef-sp-fill', [
            'data-width' => (int)$row['percent'],
            'style' => 'width:0%',
        ]);
        $out .= html_writer::end_div();
        $out .= html_writer::div(s((string)$row['score']), 'ef-sp-score');
        $out .= html_writer::end_div();
        $i++;
    }
    $out .= html_writer::end_div();
    $out .= html_writer::end_div();
    return $out;
}
