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

class backup_experiencefinder_activity_structure_step extends backup_activity_structure_step {
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $experiencefinder = new backup_nested_element('experiencefinder', ['id'], [
            'course', 'name', 'intro', 'introformat', 'shapeintro', 'shapeintroformat', 'selfprofilecmid', 'passionfindercmid', 'skillsfindercmid', 'personalityfindercmid', 'timecreated', 'timemodified'
        ]);
        $sections = new backup_nested_element('sections');
        $section = new backup_nested_element('section', ['id'], [
            'name', 'description', 'descriptionformat', 'resulttype', 'resultcmid', 'sortorder', 'timecreated', 'timemodified'
        ]);
        $prompts = new backup_nested_element('prompts');
        $prompt = new backup_nested_element('prompt', ['id'], ['prompttext', 'prompttype', 'storeselection', 'sortorder']);
        $questions = new backup_nested_element('questions');
        $question = new backup_nested_element('question', ['id'], ['questiontext', 'sortorder']);
        $responses = new backup_nested_element('responses');
        $response = new backup_nested_element('response', ['id'], ['userid', 'timecreated', 'timemodified']);
        $answers = new backup_nested_element('answers');
        $answer = new backup_nested_element('answer', ['id'], ['questionid', 'answertext', 'timemodified']);
        $selections = new backup_nested_element('selections');
        $selection = new backup_nested_element('selection', ['id'], ['promptid', 'timecreated']);
        $collageitems = new backup_nested_element('collageitems');
        $collageitem = new backup_nested_element('collageitem', ['id'], ['sectionid', 'filename', 'xpos', 'ypos', 'width', 'zindex', 'timecreated', 'timemodified']);

        $experiencefinder->add_child($sections);
        $sections->add_child($section);
        $section->add_child($prompts);
        $prompts->add_child($prompt);
        $section->add_child($questions);
        $questions->add_child($question);
        $experiencefinder->add_child($responses);
        $responses->add_child($response);
        $response->add_child($answers);
        $answers->add_child($answer);
        $response->add_child($selections);
        $selections->add_child($selection);
        $response->add_child($collageitems);
        $collageitems->add_child($collageitem);

        $experiencefinder->set_source_table('experiencefinder', ['id' => backup::VAR_ACTIVITYID]);
        $section->set_source_table('experiencefinder_sections', ['experiencefinderid' => backup::VAR_PARENTID]);
        $prompt->set_source_table('experiencefinder_prompts', ['sectionid' => backup::VAR_PARENTID]);
        $question->set_source_table('experiencefinder_questions', ['sectionid' => backup::VAR_PARENTID]);

        if ($userinfo) {
            $response->set_source_table('experiencefinder_responses', ['experiencefinderid' => backup::VAR_PARENTID]);
            $answer->set_source_table('experiencefinder_answers', ['responseid' => backup::VAR_PARENTID]);
            $selection->set_source_table('experiencefinder_selections', ['responseid' => backup::VAR_PARENTID]);
            $collageitem->set_source_table('experiencefinder_collage_items', ['responseid' => backup::VAR_PARENTID]);
        }

        $response->annotate_ids('user', 'userid');
        $answer->annotate_ids('experiencefinder_question', 'questionid');
        $selection->annotate_ids('experiencefinder_prompt', 'promptid');
        $collageitem->annotate_ids('experiencefinder_section', 'sectionid');
        $experiencefinder->annotate_files('mod_experiencefinder', 'intro', null);
        $experiencefinder->annotate_files('mod_experiencefinder', 'collagegallery', null);

        return $this->prepare_activity_structure($experiencefinder);
    }
}
