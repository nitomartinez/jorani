<?php

$GLOBALS['debug'] = FALSE;

$this->lang->load('requests', $this->language);
$this->lang->load('global', $this->language);
$this->lang->load('leaves', $this->language);
$this->lang->load('reports', $this->language);


require_once FCPATH . "vendor/autoload.php";

$this->load->model('organization_model');

function getLeaveTypes($ci) {
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
    foreach($rows as $row) {
        $ids[$row["id"]] = $row["name"];
    }
    return $ids;
}


function getUserIdsWithLeavesBetweenDates($ci, $startdate, $enddate) {

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

    $query = $ci->db->query($sql);

    $rows = $query->result_array();
    $ids = array();
    foreach($rows as $row) {
        $ids[] = $row["id"];
    }
    return $ids;
}


function getLeavesBetweenDates($ci, $startdate, $enddate) {

    $tablename = str_replace("-", "_", "leavestable_${startdate}_${enddate}");

    
    $createtemptable = <<<EOF
create temporary table if not exists $tablename as
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
        (IF(type=$id,duration,0))
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
        echo "error:".print_r($ci->db->error());
         echo "<br/>";
    }
    $rows2 = $queryresult2->result_array();
    $rows = $queryresult->result_array();
    $dropoutput = $ci->db->query($droptemptable);

    return $rows2;
}

?>