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

function xmldb_experiencefinder_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026061501) {
        // Earlier framework cleanup point.
        upgrade_mod_savepoint(true, 2026061501, 'experiencefinder');
    }

    if ($oldversion < 2026061502) {
        $table = new xmldb_table('experiencefinder');
        $field = new xmldb_field('selfprofilecmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'shapeintroformat');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026061502, 'experiencefinder');
    }

    if ($oldversion < 2026061504) {
        $table = new xmldb_table('experiencefinder');

        $fields = [
            new xmldb_field('passionfindercmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'selfprofilecmid'),
            new xmldb_field('skillsfindercmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'passionfindercmid'),
            new xmldb_field('personalityfindercmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'skillsfindercmid'),
        ];

        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_mod_savepoint(true, 2026061504, 'experiencefinder');
    }


    if ($oldversion < 2026061505) {
        $table = new xmldb_table('experiencefinder_sections');

        $field = new xmldb_field('resulttype', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, 'none', 'descriptionformat');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('resultcmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'resulttype');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026061505, 'experiencefinder');
    }


    if ($oldversion < 2026061614) {
        $table = new xmldb_table('experiencefinder_collage_items');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('responseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('sectionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('filename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('xpos', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '100');
            $table->add_field('ypos', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '100');
            $table->add_field('width', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '220');
            $table->add_field('zindex', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('response_section', XMLDB_INDEX_NOTUNIQUE, ['responseid', 'sectionid']);
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026061614, 'experiencefinder');
    }


    if ($oldversion < 2026061820) {
        $table = new xmldb_table('experiencefinder_collage_snapshots');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('responseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('sectionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('pngdata', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('response_section', XMLDB_INDEX_UNIQUE, ['responseid', 'sectionid']);
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026061820, 'experiencefinder');
    }

    return true;
}
