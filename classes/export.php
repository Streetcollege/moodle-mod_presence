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
        ];
    }
}

class export_sws {

    public $id = 'mod_presence/presence';
    public $name = "SWS Entwicklung";

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