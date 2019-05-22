<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

require (dirname(__DIR__).'/../reports/hour-aware-functions.php');

$this->auth->checkIfOperationIsAllowed('native_report_leaves');
$this->lang->load('leaves', $this->language);
$this->load->model('organization_model');
$this->load->model('leaves_model');
$this->load->model('types_model');
$this->load->model('dayoffs_model');

$startdate = $this->input->get("startdate") === FALSE ? 0 : $this->input->get("startdate");
$enddate = $this->input->get("enddate") === FALSE ? 0 : $this->input->get("enddate");

$data['startdate'] = $startdate; 
$data['enddate'] = $enddate; 

$data['refDate'] = date("Y-m-d");
if (isset($_GET['refDate']) && $_GET['refDate'] != NULL) {
    $data['refDate'] = date("Y-m-d", $_GET['refDate']);
}
$data['include_children'] = filter_var($_GET['children'], FILTER_VALIDATE_BOOLEAN);
//$this->load->view('reports/leaves/export', $data);

// require_once FCPATH . "vendor/autoload.php";

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$sheet->setTitle(mb_strimwidth(lang('reports_export_leaves_title'), 0, 28, "..."));  //Maximum 31 characters allowed in sheet title.

//$startdate = $this->input->get("startdate") === FALSE ? 0 : $this->input->get("startdate");
//$enddate = $this->input->get("enddate") === FALSE ? 0 : $this->input->get("enddate");
//$entity = $this->input->get("entity") === FALSE ? 0 : $this->input->get("entity");
$children = filter_var($this->input->get("children"), FILTER_VALIDATE_BOOLEAN);
$requests = filter_var($this->input->get("requests"), FILTER_VALIDATE_BOOLEAN);

$leavesBetweenDates = getLeavesBetweenDates($this, $startdate, $enddate);

$result = array();
$leave_requests = array();
$index = 0;

foreach ($leavesBetweenDates as $leave) {
    $result[$index]['userid'] = $leave['userid'];
    $result[$index]['lastname'] = $leave['lastname'];
    $result[$index]['firstname'] = $leave['firstname'];
    $result[$index]['type'] = $leave['type'];
    $result[$index]['leavetype'] = $leave['leavetype'];
    $result[$index]['duration'] = $leave['duration'];

    if ($requests) $leave_requests[$leave['userid']] = $this->leaves_model->getAcceptedLeavesBetweenDates($leave['userid'], $startdate, $enddate);
    $index++;
}

$index = 0;
$max = 0;
$line = 2;
$i18n = array("id", "userid", "firstname", "lastname", "type", "leavetype", "duration");
foreach ($result as $index => $row) {
    $columnIndex = 1;
    foreach ($row as $key => $value) {
        if ($line == 2) {
            $colidx = columnName($columnIndex) . '1';
            if (in_array($key, $i18n)) {
                $sheet->setCellValue($colidx, $key);
            } else {
                $sheet->setCellValue($colidx, $key);
            }
            $max++;
        }
        $colidx = columnName($columnIndex) . $line;
        $sheet->setCellValue($colidx, $value);
        $columnIndex++;
    }
    $index++;
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
?>