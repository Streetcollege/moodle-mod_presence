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
 * Class definition for mod_presence\presence
 *
 * @package    mod_presence
 * @author     Florian Metzger-Noel (github.com/flocko-motion)
 * @copyright  2021
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace mod_presence;

defined('MOODLE_INTERNAL') || die();

class presence
{
    /**
     * Get presence id for course.
     * @param $courseid
     * @throws \coding_exception
     */
    public static function get_presence_id($courseid) {
        global $DB;

        $moduleid = $DB->get_field('modules', 'id', ['name'=>'presence']);
        if (!$moduleid) {
            throw new \coding_exception("module presence not found in modules table");
        }

        $presenceid = $DB->get_field_SQL('select p.id
            FROM {presence} p
            LEFT JOIN {course_modules} cm ON  cm.instance = p.id
            WHERE cm.module = :moduleid
                AND cm.course = :courseid
                AND cm.deletioninprogress = 0
            LIMIT 1',
            ['courseid' => $courseid, 'moduleid' => $moduleid]
        );

        return $presenceid;
    }

    /**
     * Get sessions for presence
     * @param $params - ['presenceid', 'timefrom', 'timestamp']
     */
    public static function get_sessions($presenceid, $params) {
        global $DB, $USER;
        $filters = [];
        if($presenceid) {
            array_push($filters, 'ps.presenceid = '.intval($presenceid));
        }
        if(isset($params['timefrom'])) {
            array_push($filters, 'ps.sessdate >= '.intval($params['timefrom']));
        }
        if(isset($params['timeto'])) {
            array_push($filters, 'ps.sessdate <= '.intval($params['timeto']));
        }
        $sql = 'SELECT ps.*, pr.name roomname, c.fullname coursename, cc.name categoryname, pb.id booked '
            .', u.firstname teacherfirstname, u.lastname teacherlastname '
            .', (SELECT COUNT(*) FROM {presence_bookings} WHERE sessionid = ps.id) attendants '
            .'FROM {presence_sessions} ps '
            .'LEFT JOIN {presence_rooms} pr ON ps.roomid = pr.id '
            .'LEFT JOIN {presence} p ON ps.presenceid = p.id '
            .'LEFT JOIN {course} c ON c.id = p.course '
            .'LEFT JOIN {course_categories} cc ON c.category = cc.id '
            .'LEFT JOIN {presence_bookings} pb ON (ps.id = pb.sessionid AND pb.userid = '.intval($USER->id).') '
            .'LEFT JOIN {user} u ON u.id = ps.teacher '
            .'WHERE '
            . (sizeof($filters) ? implode(' AND ', $filters) : '')
            .' ORDER BY ps.sessdate ASC '
            . (isset($params['maxresults']) ? 'LIMIT '.intval($params['maxresults']) : '');
        $sessions = $DB->get_records_sql($sql);
        //
        if (isset($params['minresults']) && isset($params['timeto']) && sizeof($sessions) < $params['minresults']) {
            unset ($params['timeto']);
            $params['maxresults'] = $params['minresults'];
            unset($params['minresults']);
            return self::get_sessions($presenceid, $params);
        }
        $reachedend = false;
        if (isset($params['maxresults']) && $params['maxresults'] > sizeof($sessions)) {
            $reachedend = true;
        }
        return [
            'sessions' => $sessions,
            'reachedend' => $reachedend,
        ];
    }

    /**
     * Get sessions for presence
     * @param $userid - ['presenceid', 'timefrom', 'timestamp']
     */
    public static function get_bookings($userid, $params) {
        global $DB;
        $filters = [];
        if($presenceid) {
            array_push($filters, 'ps.presenceid = '.intval($presenceid));
        }
        if(isset($params['timefrom'])) {
            array_push($filters, 'ps.sessdate >= '.intval($params['timefrom']));
        }
        if(isset($params['timeto'])) {
            array_push($filters, 'ps.sessdate <= '.intval($params['timeto']));
        }
        $sql = 'SELECT ps.*, pr.name roomname, c.fullname coursename, cc.name categoryname, pb.id booked '
            .', u.firstname teacherfirstname, u.lastname teacherlastname '
            .', (SELECT COUNT(*) FROM {presence_bookings} WHERE sessionid = ps.id) attendants '
            .'FROM {presence_sessions} ps '
            .'LEFT JOIN {presence_rooms} pr ON ps.roomid = pr.id '
            .'LEFT JOIN {presence} p ON ps.presenceid = p.id '
            .'LEFT JOIN {course} c ON c.id = p.course '
            .'LEFT JOIN {course_categories} cc ON c.category = cc.id '
            .'LEFT JOIN {presence_bookings} pb ON (ps.id = pb.sessionid AND pb.userid = '.intval($userid).') '
            .'LEFT JOIN {user} u ON u.id = ps.teacher '
            .'WHERE pb.userid='.intval($userid).' '
            .(sizeof($filters) ? ' AND '.implode(' AND ', $filters) : '').' '
            .' ORDER BY ps.sessdate ASC';
        $sessions = $DB->get_records_sql($sql);
        return $sessions;
    }

    /**
     * Store a guidance remark by creating a session and evaluating it with a single student
     * @param $courseid
     * @param $studentid
     * @param $date - any format that strtotime can parse
     * @param $duration - in seconds
     * @param $personalityremark
     * @param $courseremark
     * @return bool|int
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function record_guidance($courseid, $studentid, $date, $duration, $personalityremark, $courseremark) {
        global $DB, $USER;
        if (!has_any_capability(['local/streetcollege:team','local/streetcollege:external'], \context_system::instance())) {
            throw new \coding_exception("access denied - missing capability");
        }
        $presenceid = self::get_presence_id($courseid);
        if (!$presenceid) {
            throw new \coding_exception("error saving remark - course has no presence plugin attached");
        }
        $timestamp = strtotime($date);
        if (!$timestamp || !$studentid || !$courseid || !$date || !$duration) {
            throw new \coding_exception("missing date");
        }
        // create session
        $sessionid = $DB->insert_record('presence_sessions', [
            'presenceid' => $presenceid,
            'sessdate' => $timestamp,
            'duration' => $duration,
            'timemodified' => $timestamp,
            'description' => 'Lernbegleitung',
            'descriptionformat' => 0,
            'caleventid' => 0,
            'calendarevent' => 0,
            'roomid' => 0,
            'maxattendants' => 0,
            'mustevaluate' => 0,
            'lastevaluated' => $timestamp,
            'lastevaluatedby' => $USER->id,
            'calgroup' => 0
        ]);
        if (!$sessionid) {
            throw new \coding_exception("error creating session for storing remark");
        }
        // create evaluation
        $evaluationid = $DB->insert_record('presence_evaluations', [
           'sessionid' => $sessionid,
           'studentid' => $studentid,
            'duration' => $duration,
            'timetaken' => $timestamp,
            'takenby' => $USER->id,
            'remarks_course' => $courseremark,
            'remarks_personality' => $personalityremark,
        ]);
        if (!$evaluationid) {
            throw new \coding_exception("error storing remark - didn't receive evaluationid");
        }
        return $evaluationid;
    }

}