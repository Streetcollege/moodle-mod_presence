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
 * Adding presence sessions
 *
 * @package    mod_presence
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once($CFG->dirroot.'/lib/formslib.php');

$capabilities = array(
    'mod/presence:managepresences',
);

$pageparams = new mod_presence_sessions_page_params();
$id                     = required_param('id', PARAM_INT);
$pageparams->action     = required_param('action', PARAM_INT);
$pageparams->maxattendants = optional_param('maxattendants', 0, PARAM_INT);

presence_init_page([
    'url' => new moodle_url('/mod/presence/manage.php'),
    'tab' => $pageparams->action == mod_presence_sessions_page_params::ACTION_ADD ?
        presence_tabs::TAB_ADD : presence_tabs::TAB_UPDATE,
    'printheader' => false,
]);

$formparams = array('course' => $course, 'cm' => $cm, 'modcontext' => $context, 'att' => $presence);
switch ($presence->pageparams->action) {
    case mod_presence_sessions_page_params::ACTION_ADD:
        $PAGE->requires->js('/mod/presence/js/rooms.js');
        $url = $presence->url_sessions(array('action' => mod_presence_sessions_page_params::ACTION_ADD));
        $mform = new \mod_presence\form\addsession($url, $formparams);

        if ($mform->is_cancelled()) {
            redirect($presence->url_manage());
        }

        if ($formdata = $mform->get_data()) {
            $formdata->maxattendants = $pageparams->maxattendants;
            $sessions = presence_construct_sessions_data_for_add($formdata, $presence);
            $presence->add_sessions($sessions);
            if (count($sessions) == 1) {
                $message = get_string('sessiongenerated', 'presence');
            } else {
                $message = get_string('sessionsgenerated', 'presence', count($sessions));
            }

            mod_presence_notifyqueue::notify_success($message);
            // Redirect to the sessions tab always showing all sessions.
            $SESSION->presencecurrentpresenceview[$cm->course] = PRESENCE_VIEW_ALL;
            redirect($presence->url_manage());
        } else {
            presence_print_header();
        }
        break;
    case mod_presence_sessions_page_params::ACTION_UPDATE:
        $PAGE->requires->js('/mod/presence/js/rooms.js');
        $sessionid = required_param('sessionid', PARAM_INT);
        $url = $presence->url_sessions(array('action' => mod_presence_sessions_page_params::ACTION_UPDATE, 'sessionid' => $sessionid));
        $formparams['sessionid'] = $sessionid;
        $mform = new \mod_presence\form\updatesession($url, $formparams);

        if ($mform->is_cancelled()) {
            redirect($presence->url_manage());
        }

        if ($formdata = $mform->get_data()) {
            $formdata->maxattendants = $pageparams->maxattendants;
            $presence->update_session_from_form_data($formdata, $sessionid);

            mod_presence_notifyqueue::notify_success(get_string('sessionupdated', 'presence'));
            redirect($presence->url_manage());
        } else {
            presence_print_header();
        }
        $currenttab = presence_tabs::TAB_UPDATE;
        break;
    case mod_presence_sessions_page_params::ACTION_DELETE:
        $PAGE->requires->js('/mod/presence/js/sessions_delete.js');
        $cal = new \mod_presence\calendar($presence);

        $sessionid = required_param('sessionid', PARAM_INT);
        $multiple = optional_param('multiple', 0, PARAM_INT);
        $confirm  = optional_param('confirm', null, PARAM_INT);

        $session = $DB->get_record('presence_sessions', ['id' => $sessionid]);
        if (!$session) {
            redirect($presence->url_manage(), get_string('sessiondeleted', 'presence'));
        }


        if (isset($confirm) && confirm_sesskey()) {
            if ($multiple) {
                $sessions = $cal->get_series_dates($sessionid);
                $sessionids = [];
                foreach ($sessions as $session) {
                    $sessionids[] = $session->id;
                }
            } else {
                $sessionids = [$sessionid, ];
            }
            $presence->delete_sessions($sessionids);
            redirect($presence->url_manage(), get_string('sessionsdeleted', 'presence'));
        }
        presence_print_header();
        $sessinfo = $presence->get_session_info($sessionid);
        $sessions = $cal->get_series_dates($sessinfo->id);
        $sessions[0]->first = 1;

        $params = array('action' => $presence->pageparams->action, 'sessionid' => $sessionid, 'confirm' => 1, 'sesskey' => sesskey());

        $templatecontext = (object)[
            'cmid' => $presence->cmid,
            'sesskey' => $USER->sesskey,
            'sessionid' => $sessionid,
            'sessions' => $sessions,
            'multiple' => count($sessions) > 1,
            'urlmanage' => $presence->url_manage()->out_as_local_url(),
            'urlsessions' => $presence->url_sessions()->out_as_local_url(),
        ];
        echo $OUTPUT->render_from_template('mod_presence/sessions_delete', $templatecontext);

        echo $OUTPUT->footer();
        exit;
    default:
        presence_print_header();
        break;
}

$mform->display();

echo $OUTPUT->footer();