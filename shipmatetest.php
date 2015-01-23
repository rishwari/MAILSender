<?php

$querydebugmode = 'true';
$type = 'pdo';

$shipmate = DB_connect_sql($shipmate_db_host,$shipmate_db,$shipmate_db_user,$shipmate_db_pwd);

$db = DB_connect($myshipmate_db_host,$myshipmate_db,$myshipmate_db_user,$myshipmate_db_pwd);

$stmt = "CREATE TABLE IF NOT EXISTS delay_table (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,Source VARCHAR(30) NOT NULL,Ship_id VARCHAR(30) NOT NULL, Ship VARCHAR(30) NOT NULL,Age_IN VARCHAR(30) NOT NULL,Age_OUT VARCHAR(30) NOT NULL,timer_reset TIMESTAMP)";
$create = $db->exec($stmt);

$stmt = "CREATE TABLE IF NOT EXISTS run_time (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, source VARCHAR(30) NOT NULL ,date_start VARCHAR(30) NOT NULL,date_end VARCHAR(30) NOT NULL)";
$create = $db->exec($stmt);

$stmt =  runQuery("INSERT INTO run_time (source,date_start,date_end) VALUE ('ShipMate',Now(),Now())",'','insert',$type,$db,$querydebugmode);
$stmt = runQuery("SELECT date_start,date_end FROM run_time WHERE source ='ShipMate' ORDER BY id DESC LIMIT 1",'','select', $type, $db, $querydebugmode);
$stmt->setFetchMode(PDO::FETCH_NUM);
$endtime = $stmt->fetch();

if($endtime[1] == null)
{
    logline("INFO", "Previous ShipMate run is still executing");
    die();
}

$stmt =  runQuery("INSERT INTO run_time (source,date_start) VALUE ('ShipMate',Now())",'','insert',$type,$db,$querydebugmode);
$id = $db->lastInsertId();


?>


    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
        <title>ShipMate Replication Report</title>
        <link href="ns5repl.css" rel="stylesheet" type="text/css" />
    </head>

<body>
    <img src="logo.gif" alt="HTML5 Icon" style="width:128px;height:128px">
    <h1 class="h1">ShipMate Replication report (<?php print date("D d M Y - H:i:s",time()); ?>)</h1>
    <table width="90%" border="0">
        <tr>
            <td class="first-line">&nbsp;</td>
            <td class="first-line">Ship(CallSign)(version)</td>
            <td class="first-line">From Vessel  </td>
            <td class="first-line">Date</td>
            <td class="first-line">Age (D:H:M:S)</td>
            <td class="first-line">To Vessel  </td>
            <td class="first-line">Age (D:H:M:S)</td>
        </tr>


<?php
$stmt = runQuery("SELECT VESSELMF.VESSEL,VESSELMF.CALLSIGN FROM VESSELMF",'', 'select', $type, $shipmate, $querydebugmode);
$stmt->setFetchMode(PDO::FETCH_NUM);
$vsl_info = $stmt->fetchAll();

foreach ($vsl_info as $vsl) {

    $stmt = runQuery("With LatestPackets As
(SELECT MAX (SENDLOG.[FILENAME]) AS [FILENAME_LP] FROM DBSHIP.dbo.SENDLOG SENDLOG INNER JOIN DBSHIP.dbo.VESSELMF VESSELMF ON (SENDLOG.VSLCODE = VESSELMF.VSLCODE) GROUP BY VESSELMF.VESSEL)
SELECT VESSELMF.VESSEL,
       SENDLOG.[FILENAME],
       SENDLOG.PACKETNO,
       SENDLOG.SENDDATE,
       SENDLOG.STATUS
  FROM DBSHIP.dbo.SENDLOG SENDLOG
       INNER JOIN DBSHIP.dbo.VESSELMF VESSELMF
          ON (SENDLOG.VSLCODE = VESSELMF.VSLCODE)
WHERE (VESSELMF.VESSEL = ?)AND (SENDLOG.[FILENAME] IN (Select FILENAME_LP from LatestPackets))", array($vsl[0]), 'select', $type, $shipmate, $querydebugmode);
    $stmt->setFetchMode(PDO::FETCH_NUM);
    $load_files = $stmt->fetch();
   // echo"$load_files[0],$load_files[1],$load_files[2],$load_files[3],$load_files[4],$load_files[5],$load_files[6]";

    $stmt = runQuery("SELECT VESSELMF.VESSEL, RECVLOG.[FILENAME], RECVLOG.PACKETNO, RECVLOG.RECVDATE, RECVLOG.VERSION_NO FROM RECVLOG INNER JOIN VESSELMF
ON (RECVLOG.VSLREALCODE = VESSELMF.VSLCODE) WHERE (VESSELMF.VESSEL = ?) AND (RECVLOG.[FILENAME] IN (SELECT MAX (RECVLOG.[FILENAME]) AS [FILENAME]
FROM RECVLOG INNER JOIN VESSELMF ON (RECVLOG.VSLREALCODE = VESSELMF.VSLCODE)
                              GROUP BY VESSELMF.VESSEL))", array($vsl[0]), 'select', $type, $shipmate, $querydebugmode);

    $stmt->setFetchMode(PDO::FETCH_NUM);
    $dump_files = $stmt->fetch();
    //echo"$dump_files[0],$dump_files[1],$dump_files[2],$dump_files[3],$dump_files[4],$dump_files[5]";

    $stmt = runQuery("SELECT VESSELMF.VESSEL, MAX (VSL_VERSION.EXE_NAME)AS EXE, MAX (VSL_VERSION.VERSION_NO)AS VERSION
  FROM DBSHIP.dbo.VSL_VERSION VSL_VERSION
       INNER JOIN DBSHIP.dbo.VESSELMF VESSELMF
          ON (VSL_VERSION.VSLCODE = VESSELMF.VSLCODE)
WHERE (VESSELMF.VESSEL = ?) AND (VSL_VERSION.EXE_NAME = 'DBVSL VERSION') GROUP BY VESSELMF.VESSEL",array($vsl[0]),'select',$type,$shipmate,$querydebugmode);

    $stmt->setFetchMode(PDO::FETCH_NUM);
    $vsl_ver= $stmt->fetch();
   // echo"$vsl_ver[0],$vsl_ver[1],$vsl_ver[2]";


    $stmt = runQuery("SELECT OFF_VERSION.LOCATION,
       OFF_VERSION.EXE_NAME,
       MAX (OFF_VERSION.VERSION_NO) AS VERSION,
       MAX (OFF_VERSION.UPDATED_DATE) AS DATE_UPDATED
  FROM DBSHIP.dbo.OFF_VERSION OFF_VERSION
WHERE (OFF_VERSION.EXE_NAME = 'SHIPMATEDASHBOARD.EXE')
GROUP BY OFF_VERSION.LOCATION, OFF_VERSION.EXE_NAME",'','select',$type,$shipmate,$querydebugmode);

    $stmt->setFetchMode(PDO::FETCH_NUM);
    $shr_ver= $stmt->fetch();
//echo"$shr_ver[0],$shr_ver[1],$shr_ver[2]";



    // Set threshold values
    $load_tresh = 2*24*60*60;
    $confirm_tresh = 3*24*60*60;
    $day = 24*60*60; // Day and a half now :-)
    $load_css = "normal";
    $confirm_css = "normal";

    // Take MS SQL time stamps and stick them in the variable
    $load_ts = strtotime($load_files[4]);
    $dump_ts = strtotime($dump_files[4]);

    // Do some analysis on the timestamps to determine the status
    $since_last_load = time() - $load_ts; // in sec
    $since_last_confirmation = time() - $dump_ts; // in sec

    // Error if central has not loaded
    if ( $since_last_load > $load_tresh )
    {
        $icon = "flag_red.png";
        $load_css = "error";
    }
    // Error if ship has not confirmed
    elseif ( $since_last_confirmation > $confirm_tresh) {
        $icon = "flag_red.png";
        $confirm_css = "error";
    }
    // Warning if central has not loaded
    elseif ( $since_last_load > ($load_tresh-$day) ) {
        $icon = "flag_orange.png";
        $load_css = "warning";
    }
    // Warning if ship has not loaded
    elseif ( $since_last_confirmation > ($confirm_tresh-$day) ) {
        $icon = "flag_orange.png";
        $confirm_css = "warning";
    }
    // Warning if central load and ship load > 1.5 day apart
    elseif ( ($dump_ts + 1.5 * $day) <  $load_ts ) {
        $icon = "flag_orange.png";
        $confirm_css = "warning";
    }
    else {
        $icon = "flag_green.png";
    }

    if ( ($load_files[0] != "") && ($since_last_load < 4320000) ) {
        print " <tr>\n";
        print "  <td><img src=\"$icon\" alt=\"OK\" width=\"16\" height=\"16\" /></td>\n";
        print "  <td>$vsl[0]($vsl[1])($vsl_ver[2])</td>";
        print "  <td>$load_files[1]</td>";
        print "  <td>$load_files[4]</td>";
        print "  <td class=\"$load_css\">" . (sec2dhms(time() - $load_ts)) . "</td>";
        print "  <td>$dump_files[1]</td>";
        print "  <td class=\"$confirm_css\">" . (sec2dhms(time() - $dump_ts)) . " $since_last_load</td>";
        print "</tr>";
    }



    /////////////////////////////

    $stmt = runQuery("INSERT INTO delay_table (Source,Ship_id,Ship,Age_IN,Age_OUT,timer_reset) VALUES ('ShipMate,:vsl[1],:vsl[0],:agein,:ageout,NOW())",array(':vsl[1]' => $vsl[1], ':vsl[0]' => $vsl[0],':load_ts' => $load_ts, ':dumpfiles' => $dumpfiles[0], ':dump_ts' => $dump_ts),'insert',$type,$db,$querydebugmode);

    date_default_timezone_set('Etc/GMT+8');
    $timestamp = time();
    $timer = 60*60;
    $longtimer = 60*60*24*365;

    $stmt = runQuery("SELECT Ship,timer_reset FROM delay_table ORDER BY timer_reset DESC LIMIT 1",'','select',$type,$db,$querydebugmode);
    $stmt->setFetchMode(PDO::FETCH_NUM);
    $timer_result = $stmt->fetch();

    $time = strtotime(($timer_result[1])) + $timer;
    $year_time = strtotime($timer_result[1]) + $longtimer;

    if ($timestamp > $time)
    {
        $stmt = runQuery("INSERT INTO ns5_status_storage (Ship,FromVessel,_Date_,Age,ToVessel,Age2,timer_reset) VALUES (:shipdetail,:loadfiles,NOW(),:load_ts,:dumpfiles,:dump_ts,NOW())",array(':shipdetail' => $shipdetail[0], ':loadfiles' => $loadfiles[0],':load_ts' => $load_ts, ':dumpfiles' => $dumpfiles[0], ':dump_ts' => $dump_ts),'insert',$type,$db,$querydebugmode);
    }

    if ( $timestamp > $year_time)
    {
        $stmt = runQuery("DELETE FROM delay_table WHERE Ship = ? ",array($debug),'select',$type,$db,$querydebugmode);
    }

    ////////////////////////////////
}

$stmt =  runQuery('UPDATE run_time SET date_end = Now() WHERE id = ? ',array($id),'update',$type,$db,$querydebugmode);

?>

    </table>
    <br>
    <table width="74%" border="0">
        <tr>
            <td width="9%" class="first-line">Status</td>
            <td width="91%" class="first-line">Explanation</td>
        </tr>
        <tr>
            <td valign="top">
                <img src="flag_green.png" alt="OK" width="16" height="16" /><br />
                <img src="flag_orange.png" alt="Warning" width="16" height="16" /><br />
                <img src="flag_red.png" alt="Error" width="16" height="16" /><br /></td>
            <td valign="top">
                Replication is normal, no problems identified<br />
                There are some issues with replication. But central is receiving files OK<br />
                We are not receiving files from the ship   </p><br />
            </td>
        </tr>
    </table>


    </table>
    <br>
    <table width="74%" border="0">
        <tr>
            <td width="9%" class="first-line">INDEX</td>
            <td width="91%" class="first-line">Explanation</td>
        </tr>
        <tr>
            <td valign="top">
                FromVessel<br />
                ToVessel<br />
                Age<br />
                Date</td>
            <td valign="top">
                Package sent from individual ships to central<br />
                The central addressing and replying to the individual ships<br />
                Time since the package was sent <br />
                Date the package was sent  </p><br />
            </td>
        </tr>
    </table>

<?php

function DB_connect($datafeed_db_host,$datafeed_db,$datafeed_db_user,$datafeed_db_pwd)
{
    // connect to the IT Support System database and return the Database handler while handling connection error

    try
    {
        $db = new PDO("mysql:host=$datafeed_db_host;dbname=$datafeed_db", $datafeed_db_user, $datafeed_db_pwd);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        logline("INFO","Connection Established");
        return $db;
    }
    catch(PDOException $e)
    {
        logline("MySQL Error", 'My SQL Database connection Failure:' . $e->getMessage() . "<br/>");
        die();
    }
}
function DB_connect_sql($datafeed_db_host,$datafeed_db,$datafeed_db_user,$datafeed_db_pwd)
{
    // connect to the IT Support System database and return the Database handler while handling connection error

    try
    {
        $db = new PDO("sqlsrv:Server=$datafeed_db_host;Database=$datafeed_db", $datafeed_db_user, $datafeed_db_pwd);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        logline("INFO","Connection Established");

        // $sql = "CREATE TABLE delay_table (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,Ship_id VARCHAR(30) NOT NULL, Ship VARCHAR(30) NOT NULL,Age_IN VARCHAR(30) NOT NULL,,Age_OUT VARCHAR(30) NOT NULL,timer_reset TIMESTAMP,ns5_run_time VARCHAR(30),shipmate_run_time VARCHAR(30))";
        // $db->exec($sql);
        // logline("INFO","New Table Successfully Created");

        return $db;
    }
    catch(PDOException $e)
    {
        logline("MySQL Error", 'My SQL Database connection Failure:' . $e->getMessage() . "<br/>");
        die();
    }
}
function logline ($type, $msg) {
    // Global variable to figure out if we need to add extra linefeed after PRGS print
    global $wasPRGS;

    // Function to print a logline to console
    $timestamp = date("Ymd H:i:s");
    list($usec, $sec) = explode(" ", microtime());
    $usec = substr($usec,2,3);

    if ( $type == "PRGS") {
        // Progress should plot only dots and no linefeed
        $wasPRGS = true;


        if ( $msg == "" )
        {
            echo "                              ";
        } else {
            // only if the number of seconds is even, print a dot to keep rate of screen updates lower
            if ( time() % 2 == 0)
            {
                echo $msg;
            }
        }
    } elseif ( ($type != "") && ($type != "PRGS") ) {
        if ( $wasPRGS ) {
            $wasPRGS = false;
            echo "\n";
        }
        $type .= ": ";
        echo "[$timestamp." . $usec . "] $type$msg\n";
    } else {
        if ( $wasPRGS ) {
            $wasPRGS = false;
            echo "\n";
        }
        $msg = "      " . $msg;
        echo "[$timestamp." . $usec . "] $type$msg\n";
    }
}

function params($string,$data)
{
    if (($data == NULL) || ($data == '')) {
        logline("QUERYDEBUG", "$string");
    }
    else{$data == array_values($data);
        foreach ($data as $k => $v)
        {
            if (is_string($k)) {
                $string = str_replace("$k", $v, $string);
            } else
            {
                $string = preg_replace('/\?/', $v, $string, 1);
            }
        }
        logline("QUERYDEBUG", "$string");}
}

function queryselectcheck($check)
{

    $rowcount = $check->rowCount();
    if ($rowcount >= 1) {
        logline("QUERYDEBUG_Results", "Query statement returned $rowcount results");
    } else {
        logline("ERROR", "No results found, exiting program");
        die();
    }

}

function queryinsertcheck($check)
{
    $insertcheck = NULL;

    $insertcheck = $check->lastInsertId();

    if (is_numeric($insertcheck) ) {
        if ($insertcheck >= 1) {
            logline("QUERYDEBUG_Results", "The ID of the newly inserted computer object is $insertcheck");
        } else {
            logline("ERROR", "Could not execute insert statement");
            die();
        }
    }

}

function runQuery($myquery,$parameters,$querytype, $type, $db, $querydebugmode)
{
    if ($type == 'pdo') {
        try {
            $query = $myquery;
            $stmt = $db->prepare($query);

            if ($parameters == ''){
                $queryparameters = array();
            }
            else {
                $queryparameters = $parameters;
            }

            if ($querydebugmode) {
                params($query, $queryparameters);
            }
            $stmt->execute($queryparameters);


        } catch (PDOException $e) {

            logline("ERROR","Caught an exception: ".$e->getMessage());

            if ($querytype == 'select') {
                if ($querydebugmode) {
                    params($query, $queryparameters);
                    queryselectcheck($stmt);
                }
            } else if ($querytype == 'insert') {
                if ($querydebugmode) {
                    params($query, $queryparameters);
                    queryinsertcheck($db);
                }
            } else {
                if ($querydebugmode) {
                    params($query, $queryparameters);
                }
            }
        }

    }
    return $stmt;
}

function sec2hms ($sec, $padHours = false)
{

    // holds formatted string
    $hms = "";

    // there are 3600 seconds in an hour, so if we
    // divide total seconds by 3600 and throw away
    // the remainder, we've got the number of hours
    $hours = intval(intval($sec) / 3600);

    // add to $hms, with a leading 0 if asked for
    $hms .= ($padHours)
        ? str_pad($hours, 2, "0", STR_PAD_LEFT). ':'
        : $hours. ':';

    // dividing the total seconds by 60 will give us
    // the number of minutes, but we're interested in
    // minutes past the hour: to get that, we need to
    // divide by 60 again and keep the remainder
    $minutes = intval(($sec / 60) % 60);

    // then add to $hms (with a leading 0 if needed)
    $hms .= str_pad($minutes, 2, "0", STR_PAD_LEFT). ':';

    // seconds are simple - just divide the total
    // seconds by 60 and keep the remainder
    $seconds = intval($sec % 60);

    // add to $hms, again with a leading 0 if needed
    $hms .= str_pad($seconds, 2, "0", STR_PAD_LEFT);

    // done!
    return $hms;

}

function sec2dhms ($sec, $padHours = false)
{
    $remaining = $sec;
    $dhms = "";

    // There are 86400 secs in a day
    $days = floor($sec / 86400);
    $remaining = $sec - ($days * 86400);
    $dhms .= str_pad($days, 2, "0", STR_PAD_LEFT). ':';

    // There are 3600 secs in an hour
    $hours = floor( $remaining / 3600 );
    $remaining = $remaining - ($hours * 3600);
    $dhms .= str_pad($hours, 2, "0", STR_PAD_LEFT). ':';

    // 60 mins
    $mins = floor( $remaining / 60 );
    $remaining = $remaining - ( $mins * 60 );
    $dhms .= str_pad($mins, 2, "0", STR_PAD_LEFT). ':';

    // Secs is the remainders
    $secs = $remaining;
    $dhms .= str_pad($secs, 2, "0", STR_PAD_LEFT);

    return $dhms;
}

?>

</body>
</html>