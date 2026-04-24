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
 * Upgrade steps for mod_aidiscussion.
 *
 * @package   mod_aidiscussion
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade hook.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_aidiscussion_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026042301) {
        $table = new xmldb_table('aidiscussion');

        $replyprovider = new xmldb_field('replyprovider', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'aienabled');
        if (!$dbman->field_exists($table, $replyprovider)) {
            $dbman->add_field($table, $replyprovider);
        }

        $gradeprovider = new xmldb_field('gradeprovider', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'replyprovider');
        if (!$dbman->field_exists($table, $gradeprovider)) {
            $dbman->add_field($table, $gradeprovider);
        }

        $oldreplyproviderid = new xmldb_field('replyproviderid');
        if ($dbman->field_exists($table, $oldreplyproviderid)) {
            $dbman->drop_field($table, $oldreplyproviderid);
        }

        $oldgradeproviderid = new xmldb_field('gradeproviderid');
        if ($dbman->field_exists($table, $oldgradeproviderid)) {
            $dbman->drop_field($table, $oldgradeproviderid);
        }

        upgrade_mod_savepoint(true, 2026042301, 'aidiscussion');
    }

    if ($oldversion < 2026042302) {
        $poststable = new xmldb_table('aidiscussion_posts');

        $providercomponent = new xmldb_field('providercomponent', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'issubstantive');
        if (!$dbman->field_exists($poststable, $providercomponent)) {
            $dbman->add_field($poststable, $providercomponent);
        }

        $oldproviderid = new xmldb_field('providerid');
        if ($dbman->field_exists($poststable, $oldproviderid)) {
            $dbman->drop_field($poststable, $oldproviderid);
        }

        $jobstable = new xmldb_table('aidiscussion_jobs');
        $runnerprovider = new xmldb_field('runnerprovider', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'lastmessage');
        if (!$dbman->field_exists($jobstable, $runnerprovider)) {
            $dbman->add_field($jobstable, $runnerprovider);
        }

        $oldrunnerproviderid = new xmldb_field('runnerproviderid');
        if ($dbman->field_exists($jobstable, $oldrunnerproviderid)) {
            $dbman->drop_field($jobstable, $oldrunnerproviderid);
        }

        $gradestable = new xmldb_table('aidiscussion_grades');
        $gradeprovidercomponent = new xmldb_field(
            'providercomponent',
            XMLDB_TYPE_CHAR,
            '100',
            null,
            null,
            null,
            null,
            'integrityjson'
        );
        if (!$dbman->field_exists($gradestable, $gradeprovidercomponent)) {
            $dbman->add_field($gradestable, $gradeprovidercomponent);
        }

        $oldgradeproviderid = new xmldb_field('providerid');
        if ($dbman->field_exists($gradestable, $oldgradeproviderid)) {
            $dbman->drop_field($gradestable, $oldgradeproviderid);
        }

        upgrade_mod_savepoint(true, 2026042302, 'aidiscussion');
    }

    if ($oldversion < 2026042303) {
        $table = new xmldb_table('aidiscussion');
        $aidisplayname = new xmldb_field(
            'aidisplayname',
            XMLDB_TYPE_CHAR,
            '255',
            null,
            XMLDB_NOTNULL,
            null,
            'AI facilitator',
            'aienabled'
        );

        if (!$dbman->field_exists($table, $aidisplayname)) {
            $dbman->add_field($table, $aidisplayname);
        }

        $DB->set_field('aidiscussion', 'aidisplayname', 'AI facilitator', ['aidisplayname' => null]);
        $DB->set_field_select('aidiscussion', 'aidisplayname', 'AI facilitator', "aidisplayname = ''");

        upgrade_mod_savepoint(true, 2026042303, 'aidiscussion');
    }

    if ($oldversion < 2026042304) {
        $table = new xmldb_table('aidiscussion');
        $aireplydelayminutes = new xmldb_field(
            'aireplydelayminutes',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'maxairepliesperstudent'
        );

        if (!$dbman->field_exists($table, $aireplydelayminutes)) {
            $dbman->add_field($table, $aireplydelayminutes);
        }

        upgrade_mod_savepoint(true, 2026042304, 'aidiscussion');
    }

    if ($oldversion < 2026042401) {
        $table = new xmldb_table('aidiscussion');

        $teacherexample = new xmldb_field(
            'teacherexample',
            XMLDB_TYPE_TEXT,
            null,
            null,
            null,
            null,
            null,
            'promptformat'
        );
        if (!$dbman->field_exists($table, $teacherexample)) {
            $dbman->add_field($table, $teacherexample);
        }

        $teacherexampleformat = new xmldb_field(
            'teacherexampleformat',
            XMLDB_TYPE_INTEGER,
            '4',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'teacherexample'
        );
        if (!$dbman->field_exists($table, $teacherexampleformat)) {
            $dbman->add_field($table, $teacherexampleformat);
        }

        upgrade_mod_savepoint(true, 2026042401, 'aidiscussion');
    }

    if ($oldversion < 2026042402) {
        upgrade_mod_savepoint(true, 2026042402, 'aidiscussion');
    }

    return true;
}
