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
 * Class definition for mod_presence\calendar
 *
 * @package    mod_presence
 * @author     Florian Metzger-Noel (github.com/flocko-motion)
 * @copyright  2020
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace mod_presence;

defined('MOODLE_INTERNAL') || die();

use mod_presence_structure;
use moodle_url;


class calendar
{
    private $presence;
    private $presenceid;

    public $rooms;

    public function  __construct(mod_presence_structure $presence = null, $presenceid = null) {
        global $DB;

        $this->presence = $presence;
        if ($this->presence) {
            $this->presenceid = $this->presence->id;
        } else if ($presenceid) {
            $this->presenceid = $presenceid;
        }

        $this->rooms = [];
        $this->rooms[0] = (object)[
            'id' => 0,
            'name' => get_string('noroom', 'presence'),
            'description' => '',
            'maxattendants' => 0,
            'bookable' => 1,
        ];
        $rows = $DB->get_records('presence_rooms', null, 'id ASC');
        foreach ($rows as $row) {
            $this->rooms[$row->id] = $row;
        }
    }




    public function get_rooms() {
        return $this->rooms;
    }

    /**
     * Get dates from this and the following dates of this calgroup starting from given session
     * @param int $sessionid
     * @param int $newfrom set a new starting unixtime for given session and recalc following session dates
     * @return array
     */
    public function get_series_dates(int $sessionid, int $newfrom = null) {
        global $DB;
        $session = $DB->get_record('presence_sessions', ['id' => $sessionid]);
        if (!$session) {
            throw new \coding_exception("session not found");
        }
        if ($session->calgroup == 0) {
            $session->sessdatestring = userdate($session->sessdate, get_string('strftimedaydatetime', 'langconfig'));
            return [0 => $session];
        }
        $dates = $DB->get_records_sql('
            SELECT * FROM {presence_sessions}
            WHERE calgroup = :calgroup 
            AND (sessdate >= :sessdate OR id = :sessid)
            ORDER BY sessdate ASC
            ', [
            'sessid' => $session->id,
            'calgroup' => $session->calgroup,
            'sessdate' => $session->sessdate,
        ]);
        foreach ($dates as $k => $date) {
            $date->sessdatestring = userdate($date->sessdate, get_string('strftimedaydatetime', 'langconfig'));
        }
        $dates = array_values($dates);
        if ($newfrom) {
            $timeoffset = $newfrom - strtotime(date('Y-m-d', $newfrom));
            foreach ($dates as $k => $date) {
                $dates[$k]->sessdate =  strtotime(date('Y-m-d', $date->sessdate)) + $timeoffset;
            }
        }
        return $dates;
    }

    /**
     * Calc dates of a repeated event
     * @param int $from unix timestamp <= first event
     * @param int $to unix timestamp >= last event
     * @param array $days array mo-su of 1/0 if that weekday has event
     * @param int $period number of weeks to next event
     * @return array list of unix timestamps of events
     */
    public function create_series_dates(int $from, int $to, array $days, int $period) : array {
        $dates = [];
        $periodcount = 0;
        for ($t = $from; $t <= $to + 3600 * 23; $t += 3600 * 24) {
            $weekday = date('N', $t) - 1;
            if ($days[$weekday]) {
                $debug = date('D Y-m-d H:i:s', $t);
                $dates[] = $t;
            }
            $periodcount++;
            if ($periodcount == 7) {
                $periodcount = 0;
                $t += 3600 * 24 * 7 * max(0, $period - 1);
            }
        }
        return $dates;
    }

    public function get_room_planner_schedule() {
        global $DB;
        $schedule = $DB->get_records_sql('
            SELECT ps.id, ps.sessdate, ps.duration, ps.description, ps.roomid, c.fullname, c.shortname
            FROM {presence_sessions} ps
            JOIN {presence} p ON ps.presenceid = p.id
            JOIN {course} c ON p.course = c.id
            WHERE CAST(TO_TIMESTAMP(ps.sessdate) as DATE) >= CURRENT_DATE
        ');


        // INFO: CAST(TO_TIMESTAMP(..) AS DATE) returns wrong date(!) .. maybe problem with timezones. User userdate instead.
        foreach ($schedule as $session) {
            $session->date = userdate($session->sessdate, '%F');
        }

        usort($schedule, function($a, $b) {
            $res = $a->date <=> $b->date;
            if ($res != 0) {
                return $res;
            }
            $res = $a->roomid <=> $b->roomid;
            if ($res != 0) {
                return $res;
            }
            return $a->sessdate <=> $b->sessdate;

        });

        $roomplan = [];
        $prevroom = null;
        $prevsession = null;
        foreach ($schedule as $session) {
            if (!isset($roomplan[$session->date])) {
                $rooms = [];
                foreach ($this->rooms as $room) {
                    $roomcopy = clone $room;
                    $roomcopy->schedule = [];
                    $rooms[$room->id] = $roomcopy;
                }
                $roomplan[$session->date] = (object)[
                    'date' => $session->dateformat = userdate(strtotime($session->date), get_string('strftimedatefullshort', 'langconfig')),
                    'rooms' => $rooms,
                ];
            }
            $session->room = $this->rooms[$session->roomid];
            $session->from = userdate($session->sessdate, get_string('strftimetime', 'langconfig'));
            $session->to = userdate($session->sessdate + $session->duration, get_string('strftimetime', 'langconfig'));
            $session->coursename = $session->shortname ? $session->shortname : $session->fullname;
            if ($prevsession && $prevsession->roomid == $session->roomid) {
                $collisionduration = $prevsession->sessdate + $prevsession->duration - $session->sessdate;
                if ($collisionduration > 0) {
                    $session->collision = true;
                    $prevsession->collision = true;
                    $prevsession->collisionduration = userdate($collisionduration, get_string('strftimetime', 'langconfig'));
                }
            }
            $roomplan[$session->date]->rooms[$session->roomid]->schedule[] = $session;
            $prevsession = $session;
        }
        return $roomplan;
    }

    /**
     * Return timestamp of the same date but with time 0:00
     * @param int $timestamp
     * @return int
     */
    public function remove_time_from_timestamp(int $timestamp) {
        return strtotime(date('Y-m-d', $timestamp));
    }

    /**
     * Return a timestamp with the same date but a different time
     * @param int $timestamp
     * @param int $time
     * @return int
     */
    public function change_timestamp_time(int $timestamp, int $time) {
        return $this->remove_time_from_timestamp($timestamp) + $time;
    }

    public function get_session_bookings(int $sessionid) {
        global $DB;
        $users = $DB->get_records_sql('
            SELECT u.id, u.picture, u.firstname, u.lastname, u.email, u.username, u.idnumber, 1 AS booked
            FROM {user} u
            LEFT JOIN {presence_bookings} pb ON u.id = pb.userid
            WHERE pb.sessionid = :sessionid
            ORDER BY u.firstname, u.lastname
            ', ['sessionid' => $sessionid]);
        foreach ($users as $user) {
            $user->picturebigurl = new moodle_url("/user/pix.php/{$user->id}/f1.jpg", []);
            $user->picturesmallurl = new moodle_url("/user/pix.php/{$user->id}/f2", []);
            $user->profileurl = new moodle_url('/mod/presence/userprofile.php', ['id' => $this->presenceid, 'userid' => $user->id]);
        }

        return $users;
    }
}