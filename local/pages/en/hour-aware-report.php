<?php

$GLOBALS['debug'] = FALSE;

$this->lang->load('requests', $this->language);
$this->lang->load('global', $this->language);

//require_once FCPATH . "vendor/autoload.php";

$this->load->model('organization_model');

/*function getLeaveTypes($ci)
{
    $sql = <<<EOF
-- 
-- The list of leave types

select id,name
  from types
 order by id
;
EOF;

    $query = $ci->db->query($sql);

    $rows = $query->result_array();
    $ids = array();
    foreach ($rows as $row) {
        $ids[$row["id"]] = $row["name"];
    }
    return $ids;
}


function getUserIdsWithLeavesBetweenDates($startdate, $enddate)
{

    $sql = <<<EOF
-- 
-- The list of reports are the reports is the union of the following reports
-- - leaves that are between the start and the end date
-- - leaves that are start before the start date and end between the start and the end date
-- - leaves that start between the start and end date and end after the end date

select distinct users.id 
 from 
      leaves
 join status on leaves.status = status.id
 join users on leaves.employee = users.id
 join types on leaves.type = types.id
where
      status.id = 3 AND -- ACCEPTED
      ( (
          leaves.startdate >= '$startdate' AND
          leaves.enddate  <= '$enddate'
        ) OR 
        (
          leaves.startdate < '$startdate' AND
          leaves.enddate  >= '$startdate' AND
          leaves.enddate  <= '$enddate'
        ) OR
        (
          leaves.startdate >= '$startdate' AND
          leaves.startdate  <= '$enddate' AND
          leaves.enddate > '$enddate'
        )
      )
order by users.id
EOF;

    $query = $this>db->query($sql);

    $rows = $query->result_array();
    $ids = array();
    foreach ($rows as $row) {
        $ids[] = $row["id"];
    }
    return $ids;
}


function getLeavesBetweenDates($ci, $startdate, $enddate)
{

    $tablename = str_replace("-", "_", "leavestable_${startdate}_${enddate}");


    $createtemptable = <<<EOF
create or replace temporary table $tablename
-- 
-- The list of reports are the reports is the union of the following reports
-- - leaves that are between the start and the end date
-- - leaves that are start before the start date and end between the start and the end date
-- - leaves that start between the start and end date and end after the end date

select t.userid, t.firstname, t.lastname, t.type, t.leavetype, SUM(t.effectiveduration) as duration from
(
(
select leaves.id as id,
       users.id as userid,
       users.firstname,
       users.lastname,
       leaves.type,
       types.name as leavetype,
       status.name as leavestatus,
       leaves.startdate,
       leaves.enddate,
       leaves.duration,
       @durationoutsideofperiodv := 0 as durationoutsideofperiod, 
       @daysoffoutsideofperiodv := 0 as daysoffoutsideofperiod,
       @halfdayadjustment := 0 as halfdayadjustment,
       (leaves.duration - @durationoutsideofperiodv + @daysoffoutsideofperiodv + @halfdayadjustment) as effectiveduration
 from 
      leaves
 join status on leaves.status = status.id
 join users on leaves.employee = users.id
 join types on leaves.type = types.id
where
      status.id = 3 AND -- ACCEPTED
      leaves.startdate >= '$startdate' AND
      leaves.enddate  <= '$enddate'
) UNION
(
-- effectiveduration for leaves that start between '$startdate' and '$enddate':
-- The duration of the leave
-- Substracting the days after the end date (except the daysoff)
-- And adjusting half a day in case the end was in the Morning
-- Example:
--  '$startdate' = "2019-04-01";
--  '$enddate' = "2019-04-30";
--
-- Calendar:
--
--      March 2019           April           
-- Su Mo Tu We Th Fr Sa  Su Mo Tu We Th Fr Sa 
--                 1  2      1  2  3  4  5  6
--  3  4  5  6  7  8  9   7  8  9 10 11 12 13
-- 10 11 12 13 14 15 16  14 15 16 17 18 19 20
-- 17 18 19 20 21 22 23  21 22 23 24 25 26 27
-- 24 25 26 27 28 29 30  28 29 30
-- 31
-- 
-- Leave:
-- Starting 2019-03-28 in the Afternoon and ending 2019-04-01 in the morning
-- +----+------------+------------+--------+----------+-------+---------------+-------------+----------+------+
-- | id | startdate  | enddate    | status | employee | cause | startdatetype | enddatetype | duration | type |
-- +----+------------+------------+--------+----------+-------+---------------+-------------+----------+------+
-- | 20 | 2019-03-28 | 2019-04-01 |      3 |        1 |       | Afternoon     | Morning     |    2.000 |    1 |
-- +----+------------+------------+--------+----------+-------+---------------+-------------+----------+------+
-- 
-- The duration for the period is 0.5 days, where
-- duration = 2 (from the leave output above) 
--            - 4 (days from 28th of March until the 31st of March Mar, these are durationoutsideofperiodv)
--            + 2 (dayoffs, 30th and 31st of Mar, Saturday and Sunday)
--            + 0.5 (as startdatetype is Afternon, if it would be in the Morning the duration in the leave would have been 2.5 days)
select leaves.id as id,
       users.id as userid,
       users.firstname,
       users.lastname, 
       leaves.type,
       types.name as leavetype,
       status.name as leavestatus,
       leaves.startdate,
       leaves.enddate,
       leaves.duration,
       @durationoutsideofperiodv := DATEDIFF('$startdate', leaves.startdate) as durationoutsideofperiod, 
       @daysoffoutsideofperiodv := (select count(1) 
                                     from dayoffs
                                    where
                                          dayoffs.contract = users.contract and
                                          dayoffs.date < '$startdate' and
                                          dayoffs.date >= leaves.startdate) as daysoffoutsideofperiod,
       @halfdayadjustment := if(startdatetype = "Afternoon", 0.5, 0) as halfdayadjustment,
       (leaves.duration - @durationoutsideofperiodv + @daysoffoutsideofperiodv + @halfdayadjustment) as effectiveduration
 from 
      leaves
 join status on leaves.status = status.id
 join users on leaves.employee = users.id
 join types on leaves.type = types.id
where
      status.id = 3 AND -- ACCEPTED
      leaves.startdate < '$startdate' AND
      leaves.enddate  >= '$startdate' AND
      leaves.enddate  <= '$enddate'
) UNION
(
-- effectiveduration for leaves that start between '$startdate' and '$enddate':
-- The duration of the leave
-- Substracting the days after the end date (except the daysoff)
-- And adjusting half a day in case the end was in the Morning
-- Example:
--  '$startdate' = "2019-04-01";
--  '$enddate' = "2019-04-30";
--
-- Calendar:
--
--       April                  May           
-- Su Mo Tu We Th Fr Sa  Su Mo Tu We Th Fr Sa 
--     1  2  3  4  5  6            1  2  3  4 
--  7  8  9 10 11 12 13   5  6  7  8  9 10 11 
-- 14 15 16 17 18 19 20  12 13 14 15 16 17 18 
-- 21 22 23 24 25 26 27  19 20 21 22 23 24 25 
-- 28 29 30              26 27 28 29 30 31    
--
-- Leave:
-- Starting 2019-04-30 in the Afternoon and ending 2019-05-02 in the morning
-- +----+------------+------------+--------+----------+-------+---------------+-------------+----------+------+
-- | id | startdate  | enddate    | status | employee | cause | startdatetype | enddatetype | duration | type |
-- +----+------------+------------+--------+----------+-------+---------------+-------------+----------+------+
-- | 23 | 2019-04-30 | 2019-05-06 |      3 |        1 |       | Afternoon     | Morning     |    4.000 |    1 |
-- +----+------------+------------+--------+----------+-------+---------------+-------------+----------+------+
-- 
-- The duration for the period is 0.5 days, where
-- duration = 4 (from the leave output above) 
--            - 6 (days from 1st of May until the 6th of May, these are durationoutsideofperiodv)
--            + 2 (dayoffs, 4th and 5th of May which are Saturday and Sunday)
--            + 0.5 (as enddatetype is Morning, if it would be in the Afternoon the duration in the leave would have been 4.5 days)
select leaves.id as id,
       users.id as userid,
       users.firstname,
       users.lastname,
       leaves.type,
       types.name as leavetype,
       status.name as leavestatus,
       leaves.startdate,
       leaves.enddate,
       leaves.duration,
       @durationoutsideofperiodv := DATEDIFF(leaves.enddate, '$enddate') as durationoutsideofperiod, 
       @daysoffoutsideofperiodv := (select count(1) 
                                     from dayoffs
                                    where
                                          dayoffs.contract = users.contract and
                                          dayoffs.date > '$enddate' and
                                          dayoffs.date <= leaves.enddate) as daysoffoutsideofperiod,
       @halfdayadjustment := if(enddatetype = "Morning", 0.5, 0) as halfdayadjustment,
       (leaves.duration - @durationoutsideofperiodv + @daysoffoutsideofperiodv + @halfdayadjustment) as effectiveduration
 from 
      leaves
 join status on leaves.status = status.id
 join users on leaves.employee = users.id
 join types on leaves.type = types.id
where
      status.id = 3 AND -- ACCEPTED
      leaves.startdate >= '$startdate' AND
      leaves.startdate  <= '$enddate' AND
      leaves.enddate > '$enddate'
)  
) t
group by t.userid, t.type
EOF;

    $querystart = <<<EOF2
select userid,firstname,lastname
EOF2;
    $queryend = <<<EOF3
  , SUM(duration) as total
  from $tablename
  group by userid
EOF3;

    $leavetypes = getLeaveTypes($ci);
    $query = $querystart;
    foreach ($leavetypes as $id => $type) {
        $query .= <<<EOF4
        ,sum(coalesce(
         (select duration
          where type = $id)
       ,0)) as '$type'
EOF4;
    }


    $query .= $queryend;
    if ($GLOBALS['debug']) {
        echo nl2br($createtemptable);
        echo "<br/>";
    }

    $droptemptable = "drop temporary table $tablename";

    $createoutput = $ci->db->query($createtemptable);
    if ($GLOBALS['debug'])
        echo nl2br($query);

    $queryresult = $ci->db->query($query);
    $queryresult2 = $ci->db->query("select * from $tablename");
    if ($GLOBALS['debug']) {
        echo "error:" . print_r($ci->db->error());
        echo "<br/>";
    }
    $rows2 = $queryresult2->result_array();
    $rows = $queryresult->result_array();
    $dropoutput = $ci->db->query($droptemptable);

    return $rows;
}
*/



?>
<h2><?php echo lang('Leave Management System'); ?></h2>

<h2><?php echo lang('reports_leaves_title'); ?></h2>

<div class="row-fluid">
    <div class="span4">

        <label for="startdate"><?php echo lang('leaves_create_field_start'); ?>
            <input type="text" name="startdate" id="startdate" />
        </label>
        <label for="enddate"><?php echo lang('leaves_create_field_end'); ?>
            <input type="text" name="enddate" id="enddate" />
        </label>

        <div class="alert hide alert-error" id="lblHourlyRequestBeyondLimitsAlert" onclick="$('#lblHourlyRequestBeyondLimitsAlert').hide();">
            <button type="button" class="close">&times;</button>
            <?php echo lang('leaves_flash_msg_hourly_reports_beyond_limits'); ?>
        </div>

        <br />
    </div>
    <div class="span4">
        <label for="txtEntity"><?php echo lang('reports_leaves_field_entity'); ?></label>
        <div class="input-append">
            <input type="text" id="txtEntity" name="txtEntity" readonly />
            <button id="cmdSelectEntity" class="btn btn-primary"><?php echo lang('reports_leaves_button_entity'); ?></button>
        </div>
        <label for="chkIncludeChildren">
            <input type="checkbox" id="chkIncludeChildren" name="chkIncludeChildren" checked /> <?php echo lang('reports_leaves_field_subdepts'); ?>
        </label>
    </div>
    <div class="span4">
        <div class="pull-right">
            <label for="chkLeaveDetails">
                <input type="checkbox" id="chkLeaveDetails" name="chkLeaveDetails" /> <?php echo lang('reports_leaves_field_leave_requests'); ?>
            </label>
            &nbsp;
            <button class="btn btn-primary" id="cmdLaunchReport"><i class="mdi mdi-file-chart"></i>&nbsp; <?php echo lang('reports_leaves_button_launch'); ?></button>
            <button class="btn btn-primary" id="cmdExportReport"><i class="mdi mdi-download"></i>&nbsp; <?php echo lang('reports_leaves_button_export'); ?></button>
        </div>
    </div>
</div>

<div class="row-fluid">
    <div class="span12">&nbsp;</div>
</div>

<div id="reportResult"></div>

<div class="row-fluid">
    <div class="span12">&nbsp;</div>
</div>

<div id="frmSelectEntity" class="modal hide fade">
    <div class="modal-header">
        <a href="#" onclick="$('#frmSelectEntity').modal('hide');" class="close">&times;</a>
        <h3><?php echo lang('reports_leaves_popup_entity_title'); ?></h3>
    </div>
    <div class="modal-body" id="frmSelectEntityBody">
        <img src="<?php echo base_url(); ?>assets/images/loading.gif">
    </div>
    <div class="modal-footer">
        <a href="#" onclick="select_entity();" class="btn"><?php echo lang('reports_leaves_popup_entity_button_ok'); ?></a>
        <a href="#" onclick="$('#frmSelectEntity').modal('hide');" class="btn"><?php echo lang('reports_leaves_popup_entity_button_cancel'); ?></a>
    </div>
</div>


<div id="mytest2">
    <table>
        <?php

        //if(isset($_POST['cmdLaunchReport'])){
        //$startdate = '2019-05-07';
        //$enddate= '2019-05-09';
        //}
        //$userids = getUserIdsWithLeavesBetweenDates($this, $startdate, $enddate);
        //$leaves = getLeavesBetweenDates($this, $startdate, $enddate);
        //echo print_r($leaves);

        //foreach ($leaves as $row ) {
        // TODO table header
        ?>
        <tr>
            <?php //foreach ($row as $key => $value) {
            ?>

    </table>

</div>



<link rel="stylesheet" href="<?php echo base_url(); ?>assets/css/flick/jquery-ui.custom.min.css">
<script src="<?php echo base_url(); ?>assets/js/jquery-ui.custom.min.js"></script>
<?php //Prevent HTTP-404 when localization isn't needed
if ($language_code != 'en') { ?>
    <script src="<?php echo base_url(); ?>assets/js/i18n/jquery.ui.datepicker-<?php echo $language_code; ?>.js"></script>
<?php } ?>
<script src="<?php echo base_url();?>assets/js/bootbox.min.js"></script>
<script type="text/javascript" src="<?php echo base_url(); ?>assets/js/moment-with-locales.min.js" type="text/javascript"></script>
<script type="text/javascript" src="<?php echo base_url(); ?>assets/js/js.state-2.2.0.min.js"></script>
<script type="text/javascript">
    var entity = -1; //Id of the selected entity
    var entityName = ''; //Label of the selected entity
    var includeChildren = true;
    var leaveDetails = false;

    function select_entity() {
        entity = $('#organization').jstree('get_selected')[0];
        entityName = $('#organization').jstree().get_text(entity);
        $('#txtEntity').val(entityName);
        $("#frmSelectEntity").modal('hide');
        Cookies.set('rep_entity', entity);
        Cookies.set('rep_entityName', entityName);
        Cookies.set('rep_includeChildren', includeChildren);
    }

    $(document).ready(function() {
        //Init datepicker widget
        moment.locale('<?php echo $language_code; ?>');

        $('#startdate').datepicker({
            dateFormat: 'yy-mm-dd'
        });
        $('#startdate').datepicker('setDate', new Date());
        $('#enddate').datepicker({
            dateFormat: 'yy-mm-dd'
        });
        $('#enddate').datepicker('setDate', 1);

        $("#frmSelectEntity").alert();

        $("#cmdSelectEntity").click(function() {
            $("#frmSelectEntity").modal('show');
            $("#frmSelectEntityBody").load('<?php echo base_url(); ?>organization/select');
        });

        $('#cmdExportReport').click(function() {
            var rtpQuery = '<?php echo base_url(); ?>reports/leaves/export';
            var startdate = $("#startdate").val();
            var enddate = $("#enddate").val();
            if (entity != -1) {
                rtpQuery += '?entity=' + entity;
            } else {
                rtpQuery += '?entity=0';
            }

            rtpQuery += '&startdate=' + startdate;
            rtpQuery += '&enddate=' + enddate;


            if ($('#chkIncludeChildren').prop('checked') == true) {
                rtpQuery += '&children=true';
            } else {
                rtpQuery += '&children=false';
            }
            if ($('#chkLeaveDetails').prop('checked') == true) {
                rtpQuery += '&requests=true';
            } else {
                rtpQuery += '&requests=false';
            }
            document.location.href = rtpQuery;
        });

        $('#cmdLaunchReport').click(function() {
            var ajaxQuery = '<?php echo base_url(); ?>reports/leaves/executeHours';
            var startdate = $("#startdate").val();
            var enddate = $("#enddate").val();
            if (entity != -1) {
                ajaxQuery += '?entity=' + entity;
            } else {
                ajaxQuery += '?entity=0';
            }

            if (startdate == 0 || enddate == 0) {
                bootbox.alert("<?php echo lang('leaves_flash_msg_hourly_reports_days_report');?>");
            }

            ajaxQuery += '&startdate=' + startdate;
            ajaxQuery += '&enddate=' + enddate;

            if ($('#chkIncludeChildren').prop('checked') == true) {
                ajaxQuery += '&children=true';
            } else {
                ajaxQuery += '&children=false';
            }
            if ($('#chkLeaveDetails').prop('checked') == true) {
                ajaxQuery += '&requests=true';
            } else {
                ajaxQuery += '&requests=false';
            }

            if (startdate == "" || enddate == "") {
                $('#reportResult').html("<img src='<?php echo base_url(); ?>assets/images/loading.gif' />");
            }

            $.ajax({
                    url: ajaxQuery
                })
                .done(function(data) {
                    $('#reportResult').html(data);
                });

        });

        //Toggle include sub-entities option
        $('#chkIncludeChildren').on('change', function() {
            includeChildren = $('#chkIncludeChildren').prop('checked');
            Cookies.set('rep_includeChildren', includeChildren);
        });

        //Toggle include leave requests
        $('#chkLeaveDetails').on('change', function() {
            leaveDetails = $('#chkLeaveDetails').prop('checked');
            Cookies.set('rep_leaveDetails', leaveDetails);
        });

        //Cookie has value ? take -1 by default
        if (Cookies.get('rep_entity') !== undefined) {
            entity = Cookies.get('rep_entity');
            entityName = Cookies.get('rep_entityName');
            includeChildren = Cookies.get('rep_includeChildren');
            leaveDetails = (Cookies.get('rep_leaveDetails') === undefined) ? "false" : Cookies.get('rep_leaveDetails');
            //Parse boolean values
            includeChildren = $.parseJSON(includeChildren.toLowerCase());
            leaveDetails = $.parseJSON(leaveDetails.toLowerCase());
            $('#txtEntity').val(entityName);
            $('#chkIncludeChildren').prop('checked', includeChildren);
            $('#chkLeaveDetails').prop('checked', leaveDetails);
        } else { //Set default value
            Cookies.set('rep_entity', entity);
            Cookies.set('rep_entityName', entityName);
            Cookies.set('rep_includeChildren', includeChildren);
            Cookies.set('rep_leaveDetails', leaveDetails);
        }
    });
</script>