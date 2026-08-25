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

class restore_experiencefinder_activity_structure_step extends restore_activity_structure_step {
    protected function define_structure() {
        $paths = [];
        $paths[] = new restore_path_element('experiencefinder', '/activity/experiencefinder');
        $paths[] = new restore_path_element('experiencefinder_section', '/activity/experiencefinder/sections/section');
        $paths[] = new restore_path_element('experiencefinder_prompt', '/activity/experiencefinder/sections/section/prompts/prompt');
        $paths[] = new restore_path_element('experiencefinder_question', '/activity/experiencefinder/sections/section/questions/question');
        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('experiencefinder_response', '/activity/experiencefinder/responses/response');
            $paths[] = new restore_path_element('experiencefinder_answer', '/activity/experiencefinder/responses/response/answers/answer');
            $paths[] = new restore_path_element('experiencefinder_selection', '/activity/experiencefinder/responses/response/selections/selection');
            $paths[] = new restore_path_element('experiencefinder_collageitem', '/activity/experiencefinder/responses/response/collageitems/collageitem');
        }
        return $this->prepare_activity_structure($paths);
    }

    protected function process_experiencefinder($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        $data->timecreated = time();
        $data->timemodified = time();
        // Linked sibling course modules are course-local choices. Reset on restore so
        // the teacher can choose the appropriate SelfProfile activity in the new course.
        $data->selfprofilecmid = 0;
        $data->passionfindercmid = 0;
        $data->skillsfindercmid = 0;
        $data->personalityfindercmid = 0;
        $newitemid = $DB->insert_record('experiencefinder', $data);
        $this->apply_activity_instance($newitemid);
    }

    protected function process_experiencefinder_section($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->experiencefinderid = $this->get_new_parentid('experiencefinder');
        // Section-level linked sibling course modules are course-local.
        // Visual Collage is internal to ExperienceFinder and can be restored safely.
        if (($data->resulttype ?? 'none') !== 'collage') {
            $data->resulttype = 'none';
            $data->resultcmid = 0;
        } else {
            $data->resultcmid = 0;
        }
        $newitemid = $DB->insert_record('experiencefinder_sections', $data);
        $this->set_mapping('experiencefinder_section', $oldid, $newitemid);
    }

    protected function process_experiencefinder_prompt($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->sectionid = $this->get_new_parentid('experiencefinder_section');
        $newitemid = $DB->insert_record('experiencefinder_prompts', $data);
        $this->set_mapping('experiencefinder_prompt', $oldid, $newitemid);
    }

    protected function process_experiencefinder_question($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->sectionid = $this->get_new_parentid('experiencefinder_section');
        $newitemid = $DB->insert_record('experiencefinder_questions', $data);
        $this->set_mapping('experiencefinder_question', $oldid, $newitemid);
    }

    protected function process_experiencefinder_response($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->experiencefinderid = $this->get_new_parentid('experiencefinder');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (!$data->userid) {
            return;
        }
        $newitemid = $DB->insert_record('experiencefinder_responses', $data);
        $this->set_mapping('experiencefinder_response', $oldid, $newitemid);
    }

    protected function process_experiencefinder_answer($data) {
        global $DB;
        $data = (object)$data;
        $data->responseid = $this->get_new_parentid('experiencefinder_response');
        $data->questionid = $this->get_mappingid('experiencefinder_question', $data->questionid);
        if ($data->responseid && $data->questionid) {
            $DB->insert_record('experiencefinder_answers', $data);
        }
    }

    protected function process_experiencefinder_selection($data) {
        global $DB;
        $data = (object)$data;
        $data->responseid = $this->get_new_parentid('experiencefinder_response');
        $data->promptid = $this->get_mappingid('experiencefinder_prompt', $data->promptid);
        if ($data->responseid && $data->promptid) {
            $DB->insert_record('experiencefinder_selections', $data);
        }
    }


    protected function process_experiencefinder_collageitem($data) {
        global $DB;
        $data = (object)$data;
        $data->responseid = $this->get_new_parentid('experiencefinder_response');
        $data->sectionid = $this->get_mappingid('experiencefinder_section', $data->sectionid);
        if ($data->responseid && $data->sectionid) {
            $DB->insert_record('experiencefinder_collage_items', $data);
        }
    }

    protected function after_execute() {
        $this->add_related_files('mod_experiencefinder', 'intro', null);
        $this->add_related_files('mod_experiencefinder', 'collagegallery', null);
    }
}
