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

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_experiencefinder_mod_form extends moodleform_mod {
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('name', 'experiencefinder'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_intro_elements();

        $mform->addElement('header', 'shapeportfoliohdr', get_string('shapeportfolio', 'experiencefinder'));
        $mform->setExpanded('shapeportfoliohdr', false);

        $mform->addElement('editor', 'shapeintro_editor', get_string('shapeintro', 'experiencefinder'), null,
            ['maxfiles' => 0, 'trusttext' => false]);
        $mform->addHelpButton('shapeintro_editor', 'shapeintro', 'experiencefinder');
        $mform->setType('shapeintro_editor', PARAM_RAW);

        $mform->addElement('filemanager', 'collagegallery_filemanager', get_string('collagegallery', 'experiencefinder'), null,
            self::collage_filemanager_options());
        $mform->addHelpButton('collagegallery_filemanager', 'collagegallery', 'experiencefinder');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Filemanager options for the teacher-supplied visual collage gallery.
     *
     * The standard plugin may still ship with starter assets in pix/collage,
     * but teachers can add their own transparent PNG artwork here without
     * touching the plugin code directory.
     *
     * @return array
     */
    private static function collage_filemanager_options(): array {
        return [
            'subdirs' => 0,
            'maxbytes' => 0,
            'maxfiles' => -1,
            'accepted_types' => ['.png', '.svg', '.jpg', '.jpeg', '.webp'],
            'return_types' => FILE_INTERNAL,
        ];
    }

    /**
     * Add a course-module selector for one of the SHAPE sibling activities.
     *
     * @param string $fieldname The ExperienceFinder DB field.
     * @param string $modname The sibling module name.
     * @param string $labelstring Language string for the label.
     * @param string $nostring Language string for the zero option.
     */
    private function add_linked_activity_select(string $fieldname, string $modname, string $labelstring, string $nostring): void {
        global $DB;

        $mform = $this->_form;
        $options = [0 => get_string($nostring, 'experiencefinder')];

        if (!empty($this->current->course)) {
            $sql = "SELECT cm.id, i.name
                      FROM {course_modules} cm
                      JOIN {modules} m ON m.id = cm.module
                      JOIN {{$modname}} i ON i.id = cm.instance
                     WHERE cm.course = ? AND m.name = ? AND cm.deletioninprogress = 0
                  ORDER BY i.name ASC";
            try {
                $records = $DB->get_records_sql($sql, [$this->current->course, $modname]);
                foreach ($records as $record) {
                    $options[$record->id] = format_string($record->name) . ' (cmid ' . $record->id . ')';
                }
            } catch (Throwable $e) {
                // The sibling module may not be installed. Keep the selector harmless.
            }
        }

        $mform->addElement('select', $fieldname, get_string($labelstring, 'experiencefinder'), $options);
        if (get_string_manager()->string_exists($labelstring . '_help', 'experiencefinder')) {
            $mform->addHelpButton($fieldname, $labelstring, 'experiencefinder');
        }
        $mform->setType($fieldname, PARAM_INT);
        $mform->setDefault($fieldname, 0);
    }

    public function data_preprocessing(&$defaultvalues) {
        if ($this->current && !empty($this->current->instance)) {
            $defaultvalues['shapeintro_editor'] = [
                'text' => $defaultvalues['shapeintro'] ?? '',
                'format' => $defaultvalues['shapeintroformat'] ?? FORMAT_HTML,
            ];
        }

        $draftitemid = file_get_submitted_draft_itemid('collagegallery_filemanager');
        if (!empty($this->current->coursemodule)) {
            $context = context_module::instance($this->current->coursemodule);
            file_prepare_draft_area($draftitemid, $context->id, 'mod_experiencefinder', 'collagegallery', 0,
                self::collage_filemanager_options());
        }
        $defaultvalues['collagegallery_filemanager'] = $draftitemid;
    }
}
