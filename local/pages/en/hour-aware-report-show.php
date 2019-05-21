<?php

require (dirname(__DIR__).'\..\reports\hour-aware-functions.php');


    $this->auth->checkIfOperationIsAllowed('native_report_leaves');
    $this->lang->load('leaves', $this->language);
    $startdate = $this->input->get("startdate") === FALSE ? 0 : $this->input->get("startdate");
    $enddate = $this->input->get("enddate") === FALSE ? 0 : $this->input->get("enddate");
    $entity = $this->input->get("entity") === FALSE ? 0 : $this->input->get("entity");
    $children = filter_var($this->input->get("children"), FILTER_VALIDATE_BOOLEAN);
    $requests = filter_var($this->input->get("requests"), FILTER_VALIDATE_BOOLEAN);

    $this->load->model('organization_model');
    $this->load->model('leaves_model');
    $this->load->model('types_model');
    $this->load->model('dayoffs_model');
    //$types = $this->types_model->getTypes();

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

        if ($requests) $leave_requests[$leave['userid']] = $this->leaves_model->getAcceptedLeavesBetweenDates($leave['userid'], $startdate, $enddate);
    }



    $table = '';
    $thead = '';
    $tbody = '';
    $line = 2;
    $i18 = array("id", "userid", "firstname", "lastname", "type", "leavetype", "duration");
    foreach ($result as $user_id => $row) {
        $index = 1;
        $tbody .= '<tr>';
        foreach ($row as $key => $value) {
            if ($line == 2) {
                if (in_array($key, $i18)) {
                    $thead .= '<th>' . $key . '</th>';
                } else {
                    $thead .= '<th>' . $key . '</th>';
                }
            }
            if ($key == 'id') {
                $tbody .= '<td><a href="' . base_url() . 'leaves/view/' . $value . '" target="_blank">' . $value . '</a></td>';
            } else {
                $tbody .= '<td>' . $value . '</td>';
            }
            $index++;
        }
        $tbody .= '</tr>';
        //Display a nested table listing the leave requests
        if ($requests) {
            if (count($leave_requests[$user_id])) {
                $tbody .= '<tr><td colspan="' . count($row) . '">';
                $tbody .= '<table class="table table-bordered table-hover" style="width: auto !important;">';
                $tbody .= '<thead><tr>';
                $tbody .= '<th>' . lang('leaves_index_thead_id') . '</th>';
                $tbody .= '<th>' . lang('leaves_index_thead_start_date') . '</th>';
                $tbody .= '<th>' . lang('leaves_index_thead_end_date') . '</th>';
                $tbody .= '<th>' . lang('leaves_index_thead_type') . '</th>';
                $tbody .= '<th>' . lang('leaves_index_thead_duration') . '</th>';
                $tbody .= '</tr></thead>';
                $tbody .= '<tbody>';
                //Iterate on leave requests
                foreach ($leave_requests[$user_id] as $request) {
                    $date = new DateTime($request['startdate']);
                    $startdate = $date->format(lang('global_date_format'));
                    $date = new DateTime($request['enddate']);
                    $enddate = $date->format(lang('global_date_format'));
                    $tbody .= '<tr>';
                    $tbody .= '<td><a href="' . base_url() . 'leaves/view/' . $request['id'] . '" target="_blank">' . $request['id'] . '</a></td>';
                    $tbody .= '<td>' . $startdate . ' (' . lang($request['startdatetype']) . ')</td>';
                    $tbody .= '<td>' . $enddate . ' (' . lang($request['enddatetype']) . ')</td>';
                    $tbody .= '<td>' . $request['type'] . '</td>';
                    $tbody .= '<td>' . $request['duration'] . '</td>';
                    $tbody .= '</tr>';
                }
                $tbody .= '</tbody>';
                $tbody .= '</table>';
                $tbody .= '</td></tr>';
            } else {
                $tbody .= '<tr><td colspan="' . count($row) . '">----</td></tr>';
            }
        }
        $line++;
    }
    $table = '<table class="table table-bordered table-hover">' .
        '<thead>' .
        '<tr>' .
        $thead .
        '</tr>' .
        '</thead>' .
        '<tbody>' .
        $tbody .
        '</tbody>' .
        '</table>';

    $this->output->set_output($table);

