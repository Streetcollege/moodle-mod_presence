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
 * Class definition for mod_presence\export
 *
 * @package    mod_presence
 * @author     Florian Metzger-Noel (github.com/flocko-motion)
 * @copyright  2021
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace mod_presence;

defined('MOODLE_INTERNAL') || die();

class export
{
    public function __construct() {

    }

    /**
     * Return a list of tables available for export
     * @param $strid
     * @param null $component
     * @throws \coding_exception
     */
    public function available_tables() {
        return [
            new export_sws(),
            new export_active_students(),
        ];
    }
}

class export_sws {

    public $id = 'mod_presence/presence';
    public $name = "SWS Entwicklung";
    public $listed = true;


    public function write($spreadsheet, $params) {
        global $DB;

        $datefrom = $params['datefrom'];
        $dateto = $params['dateto'];

        $records = $DB->get_records_sql('
            SELECT sws.id as id, u.id as uid, u.lastname, u.firstname, sws.sws, mc.fullname as coursename, 
                   sws.timemodified, doz.firstname as dozfirstname, doz.lastname as dozlastname 
            FROM {presence_sws} sws
            LEFT JOIN {user} u ON u.id = sws.userid
            LEFT JOIN {user} doz ON sws.modifiedby = doz.id
            LEFT JOIN {course} mc on sws.courseid = mc.id    
            ORDER BY u.lastname, u.firstname, u.id, sws.timemodified
        ');

//        echo '<pre>'.print_r($records, true).'</pre>';
//        exit();

        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder() );

        $sheet = $spreadsheet->getActiveSheet();
        $row = 1;
        $sheet->setCellValueByColumnAndRow(1, $row, 'SWS Entwicklung '.$datefrom.' bis '.$dateto);

        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getColumnDimension('C')->setAutoSize(true);
        $sheet->getColumnDimension('D')->setAutoSize(true);
        $sheet->getColumnDimension('E')->setAutoSize(true);
        $sheet->getStyle('A3:E3')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFCCCCCC');
        $sheet->getStyle('A1:E3')->getFont()->setBold(true);

        $row += 2;
        $sheet->setCellValueByColumnAndRow(1, $row, 'Student*in');
        $sheet->setCellValueByColumnAndRow(2, $row, 'SWS');
        $sheet->setCellValueByColumnAndRow(3, $row, 'Datum');
        $sheet->setCellValueByColumnAndRow(4, $row, 'Kurs');
        $sheet->setCellValueByColumnAndRow(5, $row, 'Dozent*in');

        $row += 1;
        $uid = 0;
        foreach ($records as $record) {
            $date = date('Y-m-d', $record->timemodified);
            if ($date < $datefrom || $date > $dateto) {
                continue;
            }
            if ($uid != $record->uid) {
                $row++;
                $uid = $record->uid;
                $fullname = $record->lastname . ($record->lastname && $record->firstname ? ', ' : '') .$record->firstname;
                $sheet->setCellValueByColumnAndRow(1, $row, $fullname);
            }
            $col = 2;
            $sheet->setCellValueByColumnAndRow($col++, $row, $record->sws);
            $sheet->setCellValueByColumnAndRow($col++, $row, date('d.m.Y H:i', $record->timemodified));

            $sheet->setCellValueByColumnAndRow($col++, $row, $record->coursename);

            $dozname = $record->dozlastname . ($record->dozlastname && $record->dozfirstname ? ', ' : '')
                . $record->dozfirstname;
            $sheet->setCellValueByColumnAndRow($col++, $row, $dozname);
            $row++;
        }

    }
}

class export_active_students {

    public $id = 'mod_presence/active_students';
    public $name = "Atkive Studenten";
    public $listed = true;


    public function write($spreadsheet, $params) {
        global $DB;

        $datefrom = $params['datefrom'];
        $dateto = $params['dateto'];
        $timefrom = strtotime($datefrom);
        $timeto = strtotime($dateto) + (24 * 3600);

        $records = $DB->get_records_sql('
            SELECT pe.studentid, u.idnumber, COUNT(*) as presences, SUM(pe.duration) / 3600 hours
            FROM {presence_evaluations} pe
            LEFT JOIN {user} u ON pe.studentid = u.id
            LEFT JOIN {presence_sessions} ps ON pe.sessionid = ps.id
            WHERE pe.duration > 0
            AND ps.sessdate >= :timefrom
            AND ps.sessdate < :timeto
            GROUP BY pe.studentid, u.idnumber
            ORDER BY pe.studentid ASC
        ', [
            'timefrom' => $timefrom,
            'timeto' => $timeto,
        ]);

        $longterm = 0;
        $shortterm = 0;
        $longtermthreshold = 8;
        foreach($records as $record) {
            if ($record->presences >= $longtermthreshold) {
                $longterm++;
            } else {
                $shortterm++;
            }
        }


//       echo '<pre>'.print_r($records, true).'</pre>';
//        exit();

        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder() );




        $sheet = $spreadsheet->getActiveSheet();
        $row = 1;
        $sheet->setCellValueByColumnAndRow(1, $row++, 'Anwesenheiten ');
        $sheet->setCellValueByColumnAndRow(1, $row++, $datefrom.' bis '.$dateto);
        $row += 1;
        $sheet->setCellValueByColumnAndRow(2, $row++, 'Anzahl');
        $sheet->setCellValueByColumnAndRow(1, $row, 'Langzeitstudenten ('.$longtermthreshold.'+ Anw.)');
        $sheet->setCellValueByColumnAndRow(2, $row++, $longterm);
        $sheet->setCellValueByColumnAndRow(1, $row, 'Kurzzeitstudenten (<'.$longtermthreshold.' Anw.)');
        $sheet->setCellValueByColumnAndRow(2, $row++, $shortterm);

        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getColumnDimension('C')->setAutoSize(true);

//        $sheet->getStyle('A3:E3')->getFill()
//            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
//            ->getStartColor()->setARGB('FFCCCCCC');
        $sheet->getStyle('A1:A6')->getFont()->setBold(true);

        $sheet->getStyle('A4:C4')->getFont()->setBold(true);

        $sheet->getStyle('A9:C9')->getFont()->setBold(true);

        $row += 2;
        $sheet->setCellValueByColumnAndRow(1, $row, 'Matrikelnr.');
        $sheet->setCellValueByColumnAndRow(2, $row, 'Anwesenheiten');
        $sheet->setCellValueByColumnAndRow(3, $row, 'Stunden');

        $row += 1;
        foreach ($records as $record) {
            $col = 1;
            $sheet->setCellValueByColumnAndRow($col++, $row, $record->idnumber ? $record->idnumber : '[user_'.$record->studentid.']');
            $sheet->setCellValueByColumnAndRow($col++, $row, $record->presences);
            $sheet->setCellValueByColumnAndRow($col++, $row, $record->hours);
            $row++;
        }

    }
}