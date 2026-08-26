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

$string['pluginname'] = 'ExperienceFinder';
$string['modulename'] = 'ExperienceFinder';
$string['modulenameplural'] = 'ExperienceFinders';
$string['pluginadministration'] = 'ExperienceFinder administration';
$string['experiencefinder:addinstance'] = 'Add a new ExperienceFinder activity';
$string['experiencefinder:view'] = 'View ExperienceFinder';
$string['experiencefinder:manage'] = 'Manage ExperienceFinder';
$string['experiencefinder:viewreports'] = 'View ExperienceFinder reports';
$string['privacy:metadata:experiencefinder_responses'] = 'Stores one ExperienceFinder response record for a participant.';
$string['privacy:metadata:experiencefinder_responses:userid'] = 'The user who owns the response.';
$string['privacy:metadata:experiencefinder_answers'] = 'Stores participant reflection answers.';
$string['privacy:metadata:experiencefinder_answers:answertext'] = 'The text entered by the participant.';
$string['privacy:metadata:experiencefinder_selections'] = 'Stores non-sensitive selected experience inventory items.';
$string['name'] = 'Name';
$string['shapeintro'] = 'Portfolio introduction';
$string['shapeintro_help'] = 'Teacher-defined text shown at the beginning of the final Discovery Portfolio.';
$string['openbuilder'] = 'Open ExperienceFinder builder';
$string['viewreports'] = 'View reports';
$string['downloadpdf'] = 'Download PDF';
$string['section'] = 'Section';
$string['sections'] = 'Sections';
$string['addsection'] = 'Add section';
$string['editsection'] = 'Edit section';
$string['deletesection'] = 'Delete section';
$string['prompt'] = 'Prompt';
$string['prompts'] = 'Prompt list';
$string['addprompt'] = 'Add prompt';
$string['question'] = 'Question';
$string['questions'] = 'Reflection questions';
$string['addquestion'] = 'Add question';
$string['reflectiononly'] = 'Reflection only - do not store selection';
$string['storeselection'] = 'Store selection for report';
$string['savechanges'] = 'Save changes';
$string['saveandcontinue'] = 'Save and continue';
$string['saved'] = 'Saved';
$string['yourreflection'] = 'Your reflection';
$string['teacherbuilderintro'] = 'Create the reflection sections, prompt lists and questions used by this ExperienceFinder.';
$string['nostructure'] = 'This ExperienceFinder has not yet been configured.';
$string['notconfiguredstudent'] = 'This ExperienceFinder is not yet ready for participants.';
$string['reflectionpromptinfo'] = 'These items are provided only to prompt reflection. You do not need to select or disclose anything you do not wish to record.';
$string['storedpromptinfo'] = 'Select any items that apply. These selections may appear in your Discovery Portfolio.';
$string['shapeportfolio'] = 'My Unique Results and Profile';
$string['participant'] = 'Participant';
$string['dategenerated'] = 'Date generated';
$string['responses'] = 'Responses';
$string['noresponses'] = 'No responses have been saved yet.';
$string['unsupportedpdf'] = 'PDF generation is a framework placeholder in this first version.';

$string['dragsection'] = 'Drag to reorder section';
$string['clicktoopen'] = 'Click to open';
$string['editprompt'] = 'Edit prompt';
$string['editquestion'] = 'Edit question';
$string['timemodified'] = 'Last modified';
$string['linkedselfprofile'] = 'Linked SelfProfile activity';
$string['linkedselfprofile_help'] = 'Choose the specific SelfProfile activity whose top categories should be displayed inside this ExperienceFinder. This uses the course module id, so it remains clear even when a course contains more than one SelfProfile activity.';
$string['noselfprofilelink'] = 'Do not link a SelfProfile activity yet';
$string['selfprofilesummary'] = 'Your top SelfProfile gifts';
$string['selfprofilelinkednoresults'] = 'A SelfProfile activity is linked, but no readable SelfProfile result was found for this participant yet.';
$string['linkedactivities'] = 'Linked activities';
$string['linkedpassionfinder'] = 'Linked PassionFinder activity';
$string['linkedpassionfinder_help'] = 'Choose the specific PassionFinder activity whose visual summary should later be displayed inside this ExperienceFinder.';
$string['linkedskillsfinder'] = 'Linked SkillsFinder activity';
$string['linkedskillsfinder_help'] = 'Choose the specific SkillsFinder activity whose competency visual should later be displayed inside this ExperienceFinder.';
$string['linkedpersonalityfinder'] = 'Linked PersonalityFinder activity';
$string['linkedpersonalityfinder_help'] = 'Choose the specific PersonalityFinder activity whose 2x2 matrix should later be displayed inside this ExperienceFinder.';
$string['nopassionfinderlink'] = 'Do not link a PassionFinder activity yet';
$string['noskillsfinderlink'] = 'Do not link a SkillsFinder activity yet';
$string['nopersonalityfinderlink'] = 'Do not link a PersonalityFinder activity yet';
$string['passionfinderpaneltitle'] = 'Your PassionFinder energy patterns';
$string['skillsfinderpaneltitle'] = 'Your SkillsFinder competencies';
$string['personalityfinderpaneltitle'] = 'Your PersonalityFinder matrix';
$string['linkedextractorpending'] = 'This linked activity is selected. The visual extractor for this sibling will be added in the next development step.';

$string['sectionresult'] = 'Result from other activities to display in this section';
$string['sectionresultnone'] = 'Do not display a linked result in this section';
$string['sectionresult_help'] = 'Choose one linked sibling activity result to display inside this section. This lets the teacher place SelfProfile, PassionFinder, SkillsFinder or PersonalityFinder summaries exactly where they belong in the final portfolio.';
$string['addsectionresult'] = 'Optional linked result';
$string['passionfinderlinkednoresults'] = 'A PassionFinder activity is linked, but no positive PassionFinder result was found for this participant yet.';
$string['skillsfinderlinkednoresults'] = 'A SkillsFinder activity is linked here, but no completed SkillsFinder result was found for this participant yet.';
$string['personalityfinderlinkednoresults'] = 'A PersonalityFinder activity is linked here, but no completed PersonalityFinder matrix was found for this participant yet.';
$string['youarehere'] = 'You are here';
$string['possibleassignments'] = 'Possible assignments';

$string['visualcollage'] = 'Visual Collage';
$string['visualcollage_help'] = 'A participant can choose symbolic images from the plugin collage library, place them on a canvas, resize them, and save the arrangement as part of this ExperienceFinder portfolio.';
$string['collagepreview'] = 'Collage image preview';
$string['collagepreview_help'] = 'This scrollable preview shows the starter collage images shipped with the plugin and any teacher-uploaded gallery images for this activity. SVG and transparent PNG files are supported.';
$string['collageavailable'] = 'Available images';
$string['collagecanvas'] = 'My visual collage';
$string['collageinstructions'] = 'Choose images, then drag and resize them on the canvas. Use size to show what feels more important.';
$string['collageadd'] = 'Add to collage';
$string['collageremove'] = 'Remove selected image';
$string['collagenolibrary'] = 'No collage images were found yet. Add starter images to pix/collage or upload a teacher gallery in the activity settings.';
$string['collageempty'] = 'No collage images have been saved yet.';
$string['privacy:metadata:experiencefinder_collage_items'] = 'Stores participant visual collage image selections and layout positions.';
$string['privacy:metadata:experiencefinder_collage_items:filename'] = 'The selected image filename from the collage image library.';

$string['collagegallery'] = 'Visual Collage gallery images';
$string['collagegallery_help'] = 'Upload transparent PNG images, SVG files, or other browser-friendly image files for the Visual Collage activity. These files are stored in Moodle file storage for this activity and appear alongside any starter images shipped in pix/collage.';
$string['confirmdeleteprompt'] = 'Delete this prompt?';
$string['confirmdeletequestion'] = 'Delete this question?';
$string['confirmdeletesection'] = 'Delete this section and all its prompts and questions?';
$string['personalitymatrixarialabel'] = 'Personality matrix';
$string['sectionordersaved'] = 'Section order saved';
$string['sectionordersavefailed'] = 'Section order could not be saved. Please reload and try again.';
