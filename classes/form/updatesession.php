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
 * Update form
 *
 * @package    mod_presence
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_presence\form;

defined('MOODLE_INTERNAL') || die();

/**
 * class for displaying update session form.
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class updatesession extends \moodleform {

    /**
     * Called to define this moodle form
     *
     * @return void
     */
    public function definition() {

        global $DB;
        $mform    =& $this->_form;

        $modcontext    = $this->_customdata['modcontext'];
        $sessionid     = $this->_customdata['sessionid'];
        $presence           = $this->_customdata['att'];

        if (!$sess = $DB->get_record('presence_sessions', array('id' => $sessionid) )) {
            error('No such session in this course');
        }

        $maxfiles = intval(get_config('enableunlimitedfiles', 'mod_presence')) ? EDITOR_UNLIMITED_FILES : 0;
        $defopts = array('maxfiles' => $maxfiles, 'noclean' => true, 'context' => $modcontext);
        $sess = file_prepare_standard_editor($sess, 'description', $defopts, $modcontext, 'mod_presence', 'session', $sess->id);


        $sess->bookings = presence_sessionbookings($sess->id);

        $starttime = $sess->sessdate - usergetmidnight($sess->sessdate);
        $starthour = floor($starttime / HOURSECS);
        $startminute = floor(($starttime - $starthour * HOURSECS) / MINSECS);

        $enddate = $sess->sessdate + $sess->duration;
        $endtime = $enddate - usergetmidnight($enddate);
        $endhour = floor($endtime / HOURSECS);
        $endminute = floor(($endtime - $endhour * HOURSECS) / MINSECS);


        $data = array(
            'sessiondate' => $sess->sessdate,
            'sessiondatestring' => userdate($sess->sessdate, get_string('strftimedaydate', 'langconfig')),
            'sestime' => array('starthour' => $starthour, 'startminute' => $startminute,
            'endhour' => $endhour, 'endminute' => $endminute),
            'teacherid' => $sess->teacher,
            'sdescription' => $sess->description,
            'calendarevent' => $sess->calendarevent,
            'roomid' => $sess->roomid,
            'maxattendants' => $sess->maxattendants,
        );

        $mform->addElement('header', 'general', get_string('changesession', 'presence'));


        // $olddate = construct_session_full_date_time($sess->sessdate, $sess->duration);
         $mform->addElement('static', 'sessiondatestring', get_string('date', 'presence'));

        $mform->addElement('html', '<data data-module="mod_presence" data-presence-sessionid="'.$sess->id.'" />');
        $mform->addElement('html', '<div class="hidden" data>');
        $mform->addElement('date_selector', 'sessiondate', get_string('sessiondate', 'presence'));
        $mform->addElement('html', '</div>');
        // For multiple sessions.
        $mform->addElement('checkbox', 'addmultiply', '', get_string('updatefollowing', 'presence'));

        for ($i = 0; $i <= 23; $i++) {
            $hours[$i] = sprintf("%02d", $i);
        }
        for ($i = 0; $i < 60; $i += 5) {
            $minutes[$i] = sprintf("%02d", $i);
        }

        $sesendtime = array();
        $sesendtime[] =& $mform->createElement('static', 'from', '', get_string('from', 'presence'));
        $sesendtime[] =& $mform->createElement('select', 'starthour', get_string('hour', 'form'), $hours, false, true);
        $sesendtime[] =& $mform->createElement('select', 'startminute', get_string('minute', 'form'), $minutes, false, true);
        $sesendtime[] =& $mform->createElement('static', 'to', '', get_string('to', 'presence'));
        $sesendtime[] =& $mform->createElement('select', 'endhour', get_string('hour', 'form'), $hours, false, true);
        $sesendtime[] =& $mform->createElement('select', 'endminute', get_string('minute', 'form'), $minutes, false, true);

        $mform->addGroup($sesendtime, 'sestime', get_string('time', 'presence'), array(' '), true);

        presence_form_session_teacher($mform, $presence, $sess);

        $mform->addElement('textarea', 'sdescription', get_string('description', 'presence'),
                           array('rows' => 2, 'columns' => 100), $defopts);
        $mform->setType('sdescription', PARAM_RAW);

        presence_form_session_room($mform, $presence, $sess);

        $mform->addElement('html', '<div id="presence_collisions"></div>');

        $mform->setDefaults($data);
        $this->add_action_buttons(true);
    }

    /**
     * Perform minimal validation on the settings form
     * @param array $data
     * @param array $files
     */
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);

        $sesstarttime = $data['sestime']['starthour'] * HOURSECS + $data['sestime']['startminute'] * MINSECS;
        $sesendtime = $data['sestime']['endhour'] * HOURSECS + $data['sestime']['endminute'] * MINSECS;
        if ($sesendtime < $sesstarttime) {
            $errors['sestime'] = get_string('invalidsessionendtime', 'presence');
        }

        return $errors;
    }
}
