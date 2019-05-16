<?php
/**
 * This view exports into a Spreadsheet file the native report listing the approved leave requests of employees attached to an entity.
 * This report is launched by the user from the view reports/leaves.
 * @copyright  Copyright (c) 2014-2019 Benjamin BALET
 * @license      http://opensource.org/licenses/AGPL-3.0 AGPL-3.0
 * @link            https://github.com/bbalet/jorani
 * @since         0.4.3
 */

require_once FCPATH . "vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$sheet->setTitle(mb_strimwidth(lang('reports_export_leaves_title'), 0, 28, "..."));  //Maximum 31 characters allowed in sheet title.

$startdate = $this->input->get("startdate") === FALSE ? 0 : $this->input->get("startdate");
$enddate = $this->input->get("enddate") === FALSE ? 0 : $this->input->get("enddate");
$entity = $this->input->get("entity") === FALSE ? 0 : $this->input->get("entity");
$children = filter_var($this->input->get("children"), FILTER_VALIDATE_BOOLEAN);
$requests = filter_var($this->input->get("requests"), FILTER_VALIDATE_BOOLEAN);

//Compute facts about dates
if ($startdate == 0 || $enddate == 0) {
    $start = date('Y-01-01');
    $end = date('Y-12-31');
    $interval = abs(strtotime($end) - strtotime($start));
    $intervalDays = floor($interval / (60 * 60 * 24));
} else {
    $start = $startdate;
    $end = $enddate;
    $interval = abs(strtotime($end) - strtotime($start));
    $intervalDays = ($interval / (60 * 60 * 24));
}

$leavesBetweenDates = getLeavesBetweenDates($this, $startdate, $enddate);

$result = array();
$leave_requests = array();

foreach ($leavesBetweenDates as $leave) {
    $result[$leave['userid']]['userid'] = $leave['userid'];
    $result[$leave['userid']]['firstname'] = $leave['firstname'];
    $result[$leave['userid']]['lastname'] = $leave['lastname'];
    $result[$leave['userid']]['type'] = $leave['type'];
    $result[$leave['userid']]['leavetype'] = $leave['leavetype'];
    $result[$leave['userid']]['duration'] = $leave['duration'];
}

$max = 0;
$line = 2;
$i18n = array("id", "userid", "firstname", "lastname", "type", "leavetype", "duration");
foreach ($result as $user_id => $row) {
    $index = 1;
    foreach ($row as $key => $value) {
        if ($line == 2) {
            $colidx = columnName($index) . '1';
            if (in_array($key, $i18n)) {
                $sheet->setCellValue($colidx, $key);
            } else {
                $sheet->setCellValue($colidx, $key);
            }
            $max++;
        }
        $colidx = columnName($index) . $line;
        $sheet->setCellValue($colidx, $value);
        $index++;
    }
    $line++;
}

$colidx = columnName($max) . '1';
$sheet->getStyle('A1:' . $colidx)->getFont()->setBold(true);
$sheet->getStyle('A1:' . $colidx)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

//Autofit
for ($ii=1; $ii <$max; $ii++) {
    $col = columnName($ii);
    $sheet->getColumnDimension($col)->setAutoSize(TRUE);
}

$spreadsheet->exportName = 'leave_requests_'. $startdate . '_' . $enddate;
writeSpreadsheet($spreadsheet);
