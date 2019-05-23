<?php

$GLOBALS['debug'] = FALSE;

$this->lang->load('requests', $this->language);
$this->lang->load('global', $this->language);
$this->lang->load('leaves', $this->language);
$this->lang->load('reports', $this->language);


require_once FCPATH . "vendor/autoload.php";

$this->load->model('organization_model');

?>

<h2><?php echo lang('reports_leaves_title');?></h2>

<div class="row-fluid">
    <div class="span2">
            <input type="text" name="startdate" id="startdate" />
    </div>
    <div class="span3">
            <input type="text" name="enddate" id="enddate" />
    </div>
    <div class="span3">
        <label for="txtEntity"><?php echo lang('reports_leaves_field_entity');?></label>
        <div class="input-append">
        <input type="text" id="txtEntity" name="txtEntity" readonly />
        <button id="cmdSelectEntity" class="btn btn-primary"><?php echo lang('reports_leaves_button_entity');?></button>
        </div>
        <label for="chkIncludeChildren">
                <input type="checkbox" id="chkIncludeChildren" name="chkIncludeChildren" checked /> <?php echo lang('reports_leaves_field_subdepts');?>
        </label>
    </div>
    <div class="span3">
        <div class="pull-right">
            &nbsp;
            <button class="btn btn-primary" id="cmdLaunchReport"><i class="mdi mdi-file-chart"></i>&nbsp; <?php echo lang('reports_leaves_button_launch');?></button>
            <button class="btn btn-primary" id="cmdExportReport"><i class="mdi mdi-download"></i>&nbsp; <?php echo lang('reports_leaves_button_export');?></button>
        </div>
    </div>
</div>

<div class="row-fluid"><div class="span12">&nbsp;</div></div>

<div id="reportResult"></div>

<div class="row-fluid"><div class="span12">&nbsp;</div></div>

<div id="frmSelectEntity" class="modal hide fade">
    <div class="modal-header">
        <a href="#" onclick="$('#frmSelectEntity').modal('hide');" class="close">&times;</a>
         <h3><?php echo lang('reports_leaves_popup_entity_title');?></h3>
    </div>
    <div class="modal-body" id="frmSelectEntityBody">
        <img src="<?php echo base_url();?>assets/images/loading.gif">
    </div>
    <div class="modal-footer">
        <a href="#" onclick="select_entity();" class="btn"><?php echo lang('reports_leaves_popup_entity_button_ok');?></a>
        <a href="#" onclick="$('#frmSelectEntity').modal('hide');" class="btn"><?php echo lang('reports_leaves_popup_entity_button_cancel');?></a>
    </div>
</div>

<link rel="stylesheet" href="<?php echo base_url();?>assets/css/flick/jquery-ui.custom.min.css">
<script src="<?php echo base_url();?>assets/js/jquery-ui.custom.min.js"></script>
<?php //Prevent HTTP-404 when localization isn't needed
if ($language_code != 'en') { ?>
<script src="<?php echo base_url();?>assets/js/i18n/jquery.ui.datepicker-<?php echo $language_code;?>.js"></script>
<?php } ?>
<script src="<?php echo base_url();?>assets/js/bootbox.min.js"></script>
<script type="text/javascript" src="<?php echo base_url();?>assets/js/moment-with-locales.min.js" type="text/javascript"></script>
<script type="text/javascript" src="<?php echo base_url();?>assets/js/js.state-2.2.0.min.js"></script>
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
    moment.locale('<?php echo $language_code;?>');

    $("#frmSelectEntity").alert();

    $("#cmdSelectEntity").click(function() {
        $("#frmSelectEntity").modal('show');
        $("#frmSelectEntityBody").load('<?php echo base_url(); ?>organization/select');
    });

    $('#startdate').datepicker({
            dateFormat: 'yy-mm-dd'
        });
    $('#startdate').datepicker('setDate', new Date());

    $('#enddate').datepicker({
            dateFormat: 'yy-mm-dd'
        });
    $('#enddate').datepicker('setDate', 1);

    $('#cmdExportReport').click(function() {
        var rtpQuery = '<?php echo base_url();?>hour-aware-report-export';
        var tmpUnix = moment($("#refdate").datepicker("getDate")).utc().unix();
        var startdate = $("#startdate").val();
        var enddate = $("#enddate").val();
        if (entity != -1) {
            rtpQuery += '?entity=' + entity;
        } else {
            rtpQuery += '?entity=0';
        }

        if (startdate == 0 || enddate == 0) {
                bootbox.alert("<?php echo lang('leaves_flash_msg_hourly_reports_days_report');?>");
                return false;
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
        var ajaxQuery = '<?php echo base_url();?>hour-aware-report-show';
        var startdate = $("#startdate").val();
        var enddate = $("#enddate").val();
        if (entity != -1) {
            ajaxQuery += '?entity=' + entity;
        } else {
            ajaxQuery += '?entity=0';
        }

        if (startdate == 0 || enddate == 0) {
                bootbox.alert("<?php echo lang('leaves_flash_msg_hourly_reports_days_report');?>");
                return false;
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
        $('#reportResult').html("<img src='<?php echo base_url();?>assets/images/loading.gif' />");

        $.ajax({
          url: ajaxQuery
        })
        .done(function( data ) {
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
    if(Cookies.get('rep_entity') !== undefined) {
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
