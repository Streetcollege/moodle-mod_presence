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
    public $name = "Studienbericht";
    public $listed = true;
    private $test = 17;



    public function write($spreadsheet, $params) {
        global $DB;

        define('SC_LONGTERMTHRESHOLD', 8);

        $datefrom = $params['datefrom'];
        $dateto = $params['dateto'];
        $timefrom = strtotime($datefrom);
        $timeto = strtotime($dateto) + (24 * 3600);

        $records = $DB->get_records_sql('
            SELECT pe.studentid, u.idnumber, u.firstname, u.lastname, COUNT(*) as presences, SUM(pe.duration) / 3600 hours
            FROM {presence_evaluations} pe
            LEFT JOIN {user} u ON pe.studentid = u.id
            LEFT JOIN {presence_sessions} ps ON pe.sessionid = ps.id
            WHERE pe.duration > 0
            AND ps.sessdate >= :timefrom
            AND ps.sessdate < :timeto
            AND ps.description <> \'Lernbegleitung\'
            GROUP BY pe.studentid, u.idnumber, u.firstname, u.lastname
            ORDER BY u.idnumber ASC
        ', [
            'timefrom' => $timefrom,
            'timeto' => $timeto,
        ]);


        $stats = [
            'all' => [
                'title' => 'Gesamt',
                'presences' => 0,
                'hours' => 0,
                'students' => 0,
                'linespace' => 1,
            ],
            'long' => [
                'title' => 'Langzeitstudenten ('.SC_LONGTERMTHRESHOLD.'+ Anw.)',
                'presences' => 0,
                'hours' => 0,
                'students' => 0,
                'linespace' => 0,
            ],
            'short' => [
                'title' => 'Kurzzeitstudenten (<'.SC_LONGTERMTHRESHOLD.' Anw.)',
                'presences' => 0,
                'hours' => 0,
                'students' => 0,
                'linespace' => 1,
            ],
            'students' => [
                'title' => 'Studierende',
                'presences' => 0,
                'hours' => 0,
                'students' => 0,
                'linespace' => 0,

            ],
            'teachers' => [
                'title' => 'Lernende Dozenten',
                'presences' => 0,
                'hours' => 0,
                'students' => 0,
                'linespace' => 1,
            ],
        ];

        function recordToStats($n, $record, &$stats) {
            $stats[$n]['students']++;
            $stats[$n]['presences'] += $record->presences;
            $stats[$n]['hours'] += $record->hours;
        }

        foreach($records as $record) {
            $record->idnumber = $record->idnumber ? $record->idnumber : 'SC-0'.$record->studentid;
            if ($record->presences >= SC_LONGTERMTHRESHOLD) {
                recordToStats('long', $record, $stats);
            } else {
                recordToStats('short', $record, $stats);
            }
            if (substr($record->idnumber,0,3) == 'SCX') {
                recordToStats('teachers', $record, $stats);
            } else {
                recordToStats('students', $record, $stats);
            }
            recordToStats('all', $record, $stats);
        }


//       echo '<pre>'.print_r($records, true).'</pre>';
//        exit();

        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder() );




        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Gesamt');
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getColumnDimension('C')->setAutoSize(true);

        $row = 1;
        $sheet->setCellValueByColumnAndRow(1, $row++, 'Anwesenheit in Kurseinheiten');
        $sheet->setCellValueByColumnAndRow(1, $row++, $datefrom.' bis '.$dateto);
        $row += 1;
        $sheet->setCellValueByColumnAndRow(2, $row, 'Anwesenheiten');
        $sheet->setCellValueByColumnAndRow(3, $row, 'Stunden');
        $sheet->setCellValueByColumnAndRow(4, $row, 'Studenten');
        $row++;
        foreach ($stats as $stat) {
            $sheet->setCellValueByColumnAndRow(1, $row, $stat['title']);
            $sheet->setCellValueByColumnAndRow(2, $row, $stat['presences']);
            $sheet->setCellValueByColumnAndRow(3, $row, $stat['hours']);
            $sheet->setCellValueByColumnAndRow(4, $row, $stat['students']);
            $sheet->getStyle('A'.$row.':A'.$row)->getFont()->setBold(true);
            $sheet->getStyle('C'.$row.':C'.$row)->getNumberFormat()->setFormatCode('0.0');
            $row += $stat['linespace'] + 1;
        }




//        $sheet->getStyle('A3:E3')->getFill()
//            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
//            ->getStartColor()->setARGB('FFCCCCCC');
        $sheet->getStyle('A1:A6')->getFont()->setBold(true);
        $sheet->getStyle('A4:D4')->getFont()->setBold(true);


        $row += 2;
        $sheet->getStyle('A'.$row.':C'.$row)->getFont()->setBold(true);
        $sheet->setCellValueByColumnAndRow(1, $row, 'Matrikelnr.');
        $sheet->setCellValueByColumnAndRow(2, $row, 'Anwesenheiten');
        $sheet->setCellValueByColumnAndRow(3, $row, 'Stunden');

        $row += 1;
        foreach ($records as $record) {
            $col = 1;
            $sheet->setCellValueByColumnAndRow($col++, $row, $record->idnumber);
            $sheet->setCellValueByColumnAndRow($col++, $row, $record->presences);
            $sheet->setCellValueByColumnAndRow($col++, $row, $record->hours);
            $sheet->getStyle('C'.$row.':C'.$row)->getNumberFormat()->setFormatCode('0.0');
            $row++;
        }

        $configs = [
            (object)[
                'sheetname' => 'Kurse',
                'title' => 'Anwesenheit in Kurseinheiten',
                'sessionname' => 'Anwesenheiten',
                'sqlfilter' => 'AND ps.description <> \'Lernbegleitung\'',
                'countstudents' => true,
            ],
            (object)[
                'sheetname' => 'Begleitung',
                'title' => 'Persönliche Begleitung von Student*innen',
                'sessionname' => 'Begleitungen',
                'sqlfilter' => 'AND ps.description = \'Lernbegleitung\'',
                'countstudents' => false,
            ],
        ];

        function recordToStats2($n, $record, &$stats) {

            $stats[$n]['students']++;
            if ($record->presences >= SC_LONGTERMTHRESHOLD) {
                $stats[$n]['studentslong']++;
            } else {
                $stats[$n]['studentsshort']++;
            }
            $stats[$n]['presences'] += $record->presences;
            $stats[$n]['hours'] += $record->hours;
        }

        foreach($configs as $config) {
            // second sheet - presence by course
            $records = $DB->get_records_sql('
                SELECT MAX(pe.id) id, cc.name category, c.fullname course, pe.studentid, u.id userid, u.idnumber, u.firstname, u.lastname, COUNT(*) as presences, SUM(pe.duration) / 3600 hours
                FROM {presence_evaluations} pe
                LEFT JOIN {user} u ON pe.studentid = u.id
                LEFT JOIN {presence_sessions} ps ON pe.sessionid = ps.id
                LEFT JOIN {presence} p ON p.id = ps.presenceid
                LEFT JOIN {course} c ON c.id = p.course
                LEFT JOIN {course_categories} cc ON c.category = cc.id
                WHERE pe.duration > 0
                AND ps.sessdate >= :timefrom
                AND ps.sessdate < :timeto
                 '.$config->sqlfilter.' 
                GROUP BY pe.studentid, u.id, u.idnumber, c.fullname, cc.name
                ORDER BY cc.name, c.fullname, u.idnumber ASC
            ', [
                'timefrom' => $timefrom,
                'timeto' => $timeto,
            ]);
            foreach ($records as $record) {
                $record->idnumber = $record->idnumber ? $record->idnumber : 'SC-0' . $record->studentid;
            }

            $recordscats = $DB->get_records_sql('
                SELECT MAX(pe.id) id, cc.name category, pe.studentid, u.id userid, u.idnumber, u.firstname, u.lastname, COUNT(*) as presences, SUM(pe.duration) / 3600 hours
                FROM {presence_evaluations} pe
                LEFT JOIN {user} u ON pe.studentid = u.id
                LEFT JOIN {presence_sessions} ps ON pe.sessionid = ps.id
                LEFT JOIN {presence} p ON p.id = ps.presenceid
                LEFT JOIN {course} c ON c.id = p.course
                LEFT JOIN {course_categories} cc ON c.category = cc.id
                WHERE pe.duration > 0
                AND ps.sessdate >= :timefrom
                AND ps.sessdate < :timeto
                 '.$config->sqlfilter.' 
                GROUP BY pe.studentid, u.id, u.idnumber, cc.name
                ORDER BY cc.name, u.idnumber ASC
            ', [
                'timefrom' => $timefrom,
                'timeto' => $timeto,
            ]);
            foreach ($recordscats as $record) {
                $record->idnumber = $record->idnumber ? $record->idnumber : 'SC-0' . $record->studentid;
            }

            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($config->sheetname);
            $sheet->getColumnDimension('A')->setAutoSize(true);
            $sheet->getColumnDimension('B')->setAutoSize(true);
            $sheet->getColumnDimension('C')->setAutoSize(true);
            $sheet->getColumnDimension('D')->setAutoSize(true);
            $sheet->getColumnDimension('E')->setAutoSize(true);
            if($config->countstudents) {
                $sheet->getColumnDimension('F')->setAutoSize(true);
                $sheet->getColumnDimension('G')->setAutoSize(true);
                $sheet->getColumnDimension('H')->setAutoSize(true);
            }

            $row = 1;
            $sheet->setCellValueByColumnAndRow(1, $row++, $config->title);
            $sheet->setCellValueByColumnAndRow(1, $row++, $datefrom . ' bis ' . $dateto);
            $sheet->getStyle('A1:H2')->getFont()->setBold(true);

            $stats = [];
            foreach ($records as $record) {
                if (!isset($stats[$record->category . '-' . $record->course])) {
                    $stats[$record->category . '-' . $record->course] = [
                        'category' => $record->category,
                        'course' => $record->course,
                        'hours' => 0,
                        'presences' => 0,
                        'studentslong' => 0,
                        'studentsshort' => 0,
                        'students' => 0,
                    ];
                }
                recordToStats2($record->category . '-' . $record->course, $record, $stats);
            }

            $statscats = [];
            foreach ($recordscats as $record) {
                if (!isset($statscats[$record->category])) {
                    $statscats[$record->category] = [
                        'category' => $record->category,
                        'course' => '',
                        'hours' => 0,
                        'presences' => 0,
                        'studentslong' => 0,
                        'studentsshort' => 0,
                        'students' => 0,
                    ];
                }
                recordToStats2($record->category, $record, $statscats);
            }

            $row += 2;
            $sheet->getStyle('A' . $row . ':H' . $row)->getFont()->setBold(true);
            $sheet->setCellValueByColumnAndRow(1, $row, 'Fachbereich');
            $sheet->setCellValueByColumnAndRow(2, $row, 'Kurs');
            $sheet->setCellValueByColumnAndRow(4, $row, $config->sessionname);
            $sheet->setCellValueByColumnAndRow(5, $row, 'Stunden');
            if ($config->countstudents) {
                $sheet->setCellValueByColumnAndRow(6, $row, 'Student:innen');
                $sheet->setCellValueByColumnAndRow(7, $row, 'Langzeit');
                $sheet->setCellValueByColumnAndRow(8, $row, 'Kurzzeit');
            }

            $row++;
            foreach ($statscats as $stat) {
                $sheet->setCellValueByColumnAndRow(1, $row, $stat['category']);
                $sheet->setCellValueByColumnAndRow(2, $row, $stat['course']);
                $sheet->setCellValueByColumnAndRow(3, $row, 'Gesamt');
                $sheet->setCellValueByColumnAndRow(4, $row, $stat['presences']);
                $sheet->setCellValueByColumnAndRow(5, $row, $stat['hours']);
                if ($config->countstudents) {
                    $sheet->setCellValueByColumnAndRow(6, $row, $stat['students']);
                    $sheet->setCellValueByColumnAndRow(7, $row, $stat['studentslong']);
                    $sheet->setCellValueByColumnAndRow(8, $row, $stat['studentsshort']);
                }
                $sheet->getStyle('E' . $row . ':E' . $row)->getNumberFormat()->setFormatCode('0.0');
                $row += 1;
            }
            $row += 1;

            foreach ($stats as $stat) {
                $sheet->setCellValueByColumnAndRow(1, $row, $stat['category']);
                $sheet->setCellValueByColumnAndRow(2, $row, $stat['course']);
                $sheet->setCellValueByColumnAndRow(3, $row, 'Gesamt');
                $sheet->setCellValueByColumnAndRow(4, $row, $stat['presences']);
                $sheet->setCellValueByColumnAndRow(5, $row, $stat['hours']);
                if ($config->countstudents) {
                    $sheet->setCellValueByColumnAndRow(6, $row, $stat['students']);
                    $sheet->setCellValueByColumnAndRow(7, $row, $stat['studentslong']);
                    $sheet->setCellValueByColumnAndRow(8, $row, $stat['studentsshort']);
                }
                $sheet->getStyle('E' . $row . ':E' . $row)->getNumberFormat()->setFormatCode('0.0');
                $row += 1;
            }


            $row += 2;
            $sheet->getStyle('A' . $row . ':F' . $row)->getFont()->setBold(true);
            $sheet->setCellValueByColumnAndRow(1, $row, 'Fachbereich');
            $sheet->setCellValueByColumnAndRow(2, $row, 'Kurs');
            $sheet->setCellValueByColumnAndRow(3, $row, 'Matrikelnr.');
            $sheet->setCellValueByColumnAndRow(4, $row, $config->sessionname);
            $sheet->setCellValueByColumnAndRow(5, $row, 'Stunden');

            $row += 1;
            $prev = null;
            foreach ($records as $record) {
                $col = 1;
                if ($prev && $prev != $record->category . '-' . $record->course) {
                    $row++;
                }
                $sheet->setCellValueByColumnAndRow($col++, $row, $record->category);
                $sheet->setCellValueByColumnAndRow($col++, $row, $record->course);
                $sheet->setCellValueByColumnAndRow($col++, $row, $record->idnumber);
                $sheet->setCellValueByColumnAndRow($col++, $row, $record->presences);
                $sheet->setCellValueByColumnAndRow($col++, $row, $record->hours);
                $sheet->getStyle('E' . $row . ':E' . $row)->getNumberFormat()->setFormatCode('0.0');
                $prev = $record->category . '-' . $record->course;
                $row++;
            }
        }
        $spreadsheet->setActiveSheetIndex(0);
    }
}