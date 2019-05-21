<?php

require (dirname(__DIR__).'\..\reports\hour-aware-functions.php');

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
$this->load->view('reports/leaves/export', $data);