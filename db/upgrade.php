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
 * upgrade processes for this module.
 *
 * @package   mod_presence
 * @copyright 2011 Artem Andreev <andreev.artem@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once(dirname(__FILE__) . '/upgradelib.php');

/**
 * upgrade this presence instance - this function could be skipped but it will be needed later
 * @param int $oldversion The old version of the presence module
 * @return bool
 */
function xmldb_presence_upgrade($oldversion=0) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2020120102) {
        // Define key spaceid (foreign) to be dropped form room_slot.
        $table = new xmldb_table('presence_sws');
        // Define field eventid to be added to room_slot.
        $field = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);

        // Conditionally launch add field eventid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    }

    if ($oldversion < 2021041402) {

        // Define field attendant to be added to presence_user.
        $table = new xmldb_table('presence_user');
        $field = new xmldb_field('attendant', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'statusremark');

        // Conditionally launch add field attendant.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Presence savepoint reached.
        upgrade_mod_savepoint(true, 2021041402, 'presence');
    }

    if ($oldversion < 2021041403) {

        // Define field id to be added to presence_user.
        $table = new xmldb_table('presence_user');

        $field = new xmldb_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
        // Conditionally launch add field id.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('contactperson', XMLDB_TYPE_TEXT, null, null, null, null, null, 'realname');
        // Conditionally launch add field contactperson.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('agegroup', XMLDB_TYPE_TEXT, null, null, null, null, null, 'contactperson');
        // Conditionally launch add field agegroup.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('backgrounds', XMLDB_TYPE_TEXT, null, null, null, null, null, 'agegroup');
        // Conditionally launch add field backgrounds.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('languages', XMLDB_TYPE_TEXT, null, null, null, null, null, 'backgrounds');
        // Conditionally launch add field languages.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('incomes', XMLDB_TYPE_TEXT, null, null, null, null, null, 'languages');
        // Conditionally launch add field incomes.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('supports', XMLDB_TYPE_TEXT, null, null, null, null, null, 'incomes');
        // Conditionally launch add field supports.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('situations', XMLDB_TYPE_TEXT, null, null, null, null, null, 'supports');
        // Conditionally launch add field situations.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('degree', XMLDB_TYPE_TEXT, null, null, null, null, null, 'situations');
        // Conditionally launch add field degree.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('degreetarget', XMLDB_TYPE_TEXT, null, null, null, null, null, 'degree');
        // Conditionally launch add field degreetarget.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('checklistchecks', XMLDB_TYPE_TEXT, null, null, null, null, null, 'degreetarget');
        // Conditionally launch add field checklistchecks.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('checklistitems', XMLDB_TYPE_TEXT, null, null, null, null, null, 'checklistchecks');
        // Conditionally launch add field checklistitems.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }


        // Presence savepoint reached.
        upgrade_mod_savepoint(true, 2021041403, 'presence');
    }

    if ($oldversion < 2021041404) {

        // Rename field supervisor on table presence_user to NEWNAMEGOESHERE.
        $table = new xmldb_table('presence_user');
        $field = new xmldb_field('attendant', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'statusremark');

        // Launch rename field supervisor.
        $dbman->rename_field($table, $field, 'supervisor');

        // Presence savepoint reached.
        upgrade_mod_savepoint(true, 2021041404, 'presence');
    }

    if ($oldversion < 2021041405) {

        // Define field realname to be added to presence_user.
        $table = new xmldb_table('presence_user');
        $field = new xmldb_field('realname', XMLDB_TYPE_TEXT, null, null, null, null, null, 'timecreated');

        // Conditionally launch add field realname.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Presence savepoint reached.
        upgrade_mod_savepoint(true, 2021041405, 'presence');
    }


    if ($oldversion < 2021052602) {

        // Define field teacher to be added to presence_sessions.
        $table = new xmldb_table('presence_sessions');
        $field = new xmldb_field('teacher', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'lastevaluatedby');

        // Conditionally launch add field teacher.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Presence savepoint reached.
        upgrade_mod_savepoint(true, 2021052602, 'presence');
    }


    if ($oldversion < 2021060201) {

        // Define field birthday to be added to presence_user.
        $table = new xmldb_table('presence_user');
        $field = new xmldb_field('birthday', XMLDB_TYPE_CHAR, '10', null, null, null, null, 'timecreated');

        // Conditionally launch add field birthplace.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field birthplace to be added to presence_user.
        $table = new xmldb_table('presence_user');
        $field = new xmldb_field('birthplace', XMLDB_TYPE_TEXT, null, null, null, null, null, 'birthday');

        // Conditionally launch add field birthplace.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field title to be added to presence_user.
        $table = new xmldb_table('presence_user');
        $field = new xmldb_field('title', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'birthplace');

        // Conditionally launch add field birthplace.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Presence savepoint reached.
        upgrade_mod_savepoint(true, 2021060201, 'presence');
    }

    if ($oldversion < 2021060203) {

        // Define field nextexam to be added to presence_user.
        $table = new xmldb_table('presence_user');
        $field = new xmldb_field('nextexam', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'title');

        // Conditionally launch add field nextexam.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Presence savepoint reached.
        upgrade_mod_savepoint(true, 2021060203, 'presence');
    }


    if ($oldversion < 2021060601) {

        // Define field attendantlab to be added to presence_user.
        $table = new xmldb_table('presence_user');
        $field = new xmldb_field('attendantlab', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'attendant');

        // Conditionally launch add field attendantlab.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Presence savepoint reached.
        upgrade_mod_savepoint(true, 2021060601, 'presence');
    }


    if ($oldversion < 2021060602) {

        // Rename field supervisor on table presence_user to NEWNAMEGOESHERE.
        $table = new xmldb_table('presence_user');
        $field = new xmldb_field('attendantlab', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'attendant');

        // Launch rename field supervisor.
        $dbman->rename_field($table, $field, 'supervisorlab');

        // Presence savepoint reached.
        upgrade_mod_savepoint(true, 2021060602, 'presence');
    }


    if ($oldversion < 2021080401) {

        // Define field active to be added to presence_user.
        $table = new xmldb_table('presence_user');
        $field = new xmldb_field('active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'timecreated');

        // Conditionally launch add field active.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Presence savepoint reached.
        upgrade_mod_savepoint(true, 2021080401, 'presence');
    }

    if ($oldversion < 2021082501) {

        // Define field realfirstname to be added to presence_user.
        $table = new xmldb_table('presence_user');
        $field = new xmldb_field('realfirstname', XMLDB_TYPE_TEXT, null, null, null, null, null, 'realname');

        // Conditionally launch add field realfirstname.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('addressco', XMLDB_TYPE_TEXT, null, null, null, null, null, 'contactperson');

        // Conditionally launch add field addressco.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Presence savepoint reached.
        upgrade_mod_savepoint(true, 2021082501, 'presence');
    }


    if ($oldversion < 2022020601) {

        // Define field personalneeds to be added to presence_user.
        $table = new xmldb_table('presence_user');

        $field = new xmldb_field('personalneeds', XMLDB_TYPE_TEXT, null, null, null, null, null, 'timecreated');

        // Conditionally launch add field personalneeds.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('workneeds', XMLDB_TYPE_TEXT, null, null, null, null, null, 'personalneeds');

        // Conditionally launch add field workneeds.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('heardaboutsc', XMLDB_TYPE_TEXT, null, null, null, null, null, 'workneeds');

        // Conditionally launch add field heardaboutsc.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Presence savepoint reached.
        upgrade_mod_savepoint(true, 2022020601, 'presence');
    }

    if ($oldversion < 2022041901) {

        // Define field wordcloud to be added to presence_user.
        $table = new xmldb_table('presence_user');
        $field = new xmldb_field('wordcloud', XMLDB_TYPE_TEXT, null, null, null, null, null, 'timecreated');

        // Conditionally launch add field wordcloud.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Presence savepoint reached.
        upgrade_mod_savepoint(true, 2022041901, 'presence');
    }


    return true;
}

/*
 *         <FIELD NAME="attendant" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
        <FIELD NAME="realname" TYPE="text" NOTNULL="true" SEQUENCE="false" COMMENT="Real name of this user"/>
        <FIELD NAME="contactperson" TYPE="text" NOTNULL="true" SEQUENCE="false" COMMENT="A contact person to this user"/>
        <FIELD NAME="agegroup" TYPE="text" NOTNULL="true" SEQUENCE="false" COMMENT="Age group of this user"/>
        <FIELD NAME="backgrounds" TYPE="text" NOTNULL="true" SEQUENCE="false" COMMENT="backgrounds of user, list of items"/>
        <FIELD NAME="languages" TYPE="text" NOTNULL="true" SEQUENCE="false" COMMENT="Languages of user, list of items"/>
        <FIELD NAME="incomes" TYPE="text" NOTNULL="true" SEQUENCE="false" COMMENT="Incomes of user, list of items"/>
        <FIELD NAME="supports" TYPE="text" NOTNULL="true" SEQUENCE="false" COMMENT="Supporting programs, list of items"/>
        <FIELD NAME="situations" TYPE="text" NOTNULL="true" SEQUENCE="false" COMMENT="Personal situation of user, list of items"/>
        <FIELD NAME="degree" TYPE="text" NOTNULL="true" SEQUENCE="false" COMMENT="Highest degree of user"/>
        <FIELD NAME="degreetarget" TYPE="text" NOTNULL="true" SEQUENCE="false" COMMENT="Degree user wants to achieve"/>
        <FIELD NAME="checklistchecks" TYPE="text" NOTNULL="true" SEQUENCE="false" COMMENT="Checked items on checklist"/>
        <FIELD NAME="checklistitems" TYPE="text" NOTNULL="true" SEQUENCE="false" COMMENT="Custom items for users checklist"/>
 */