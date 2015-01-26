<?php

include 'itss_conf.php';

$type = 'pdo';
$querydebugmode = 'false';
$debug ='false';

$db = DB_connect($collect_av_db_host,$collect_av_db,$collect_av_db_user,$collect_av_db_pwd);

?>

    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1"/>
        <title>Fleet IT Status</title>
        <link href="ns5repl.css" rel="stylesheet" type="text/css"/>
    </head>

<body>
    <h1 class="h1">Fleet IT status (<?php print date("D d M Y - H:i:s", time()); ?>)</h1>
<table border="0" CELLSPACING="5">


<?php
print "  <tr>\n";
print "   <td class=\"first-line\">&nbsp;</td>\n";
print "<td class=\"first-line\">VPN</td><td class=\"first-line\">Anti Virus</td><td class=\"first-line\">NS5</td><td class=\"first-line\">Shipmate</td><td class=\"first-line\">Remarks</td>";
print "  <tr>\n";
?>

<?php

$stmt =  runQuery("SELECT DISTINCT id,shipname,callsign FROM ship_list WHERE active = 1 ORDER BY shipname ASC",'','select',$type,$db,$querydebugmode);
$stmt->setFetchMode(PDO::FETCH_NUM);
$ships = $stmt->fetchAll();
// Outer loop over the ships
foreach ($ships as $shipdetail){
    $hasproblem = false;
    $outputbuf = "";
    $iconSize = 32;

    // Always write the first column
    print "  <tr>\n";
    $outputbuf .= "  <td><a href=\"shipdetail.php?shipid=$shipdetail[0]\">$shipdetail[1] ($shipdetail[2])</a></td>";


    // Let's see how VPN is doing
    $vpnStatus = vpnStatusByShipID($shipdetail[0],$type,$db,$querydebugmode);
    $statusFlag = "vpn_bad.png";
    if ($vpnStatus) {
        $statusFlag = "vpn_good.png";
    }
    $outputbuf .= " <td><img src=\"icon/$statusFlag\" width=\"$iconSize\" height=\"$iconSize\" /></td>";

    // What state is AV in
    $avStatus = avStatusByShipID($shipdetail[0],$type,$db,$querydebugmode);
    $statusFlag = "Bug_bad.png";
    if ($avStatus) {
        $statusFlag = "Bug_good.png";
    }
    $outputbuf .= " <td><img src=\"icons/$statusFlag\" width=\"$iconSize\" height=\"$iconSize\" /></td>";

    // What state is AV in
    $avStatus = avStatusByShipID($shipdetail[0],$type,$db,$querydebugmode);
    $statusFlag = "ns5_bad.png";
    if ($avStatus) {
        $statusFlag = "ns5_good.png";
    }
    $outputbuf .= " <td><img src=\"icons/$statusFlag\" width=\"$iconSize\" height=\"$iconSize\" /></td>";

    // What state is AV in
//    $avStatus = avStatusByShipID($shipdetail[0],$type,$db,$querydebugmode);
    //  $statusFlag = "sm_bad.png";
    //if ($avStatus) {
    //  $statusFlag = "sm_good.png";
    //}
    // $outputbuf .= " <td><img src=\"icons/$statusFlag\" width=\"$iconSize\" height=\"$iconSize\" /></td>";

// Anything else we need to alert on
    $infoStatus = infoByShipID($shipdetail[0],$type,$db,$querydebugmode);

    $outputbuf .= " <td>$infoStatus</td>";

    if ($hasproblem)
    {
        $outputbuf = str_replace("Good_bug.png","Bad_bug.png", $outputbuf);
    }
    print $outputbuf;
    print "</tr>";
}

function DB_connect($datafeed_db_host,$datafeed_db,$datafeed_db_user,$datafeed_db_pwd)
{
    // connect to the IT Support System database and return the Database handler while handling connection error

    try
    {
        $db = new PDO("mysql:host=$datafeed_db_host;dbname=$datafeed_db", $datafeed_db_user, $datafeed_db_pwd);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($debug)
        {
            logline("INFO","Connection Established");
        }        return $db;
    }
    catch(PDOException $e)
    {
        logline("MySQL Error", 'My SQL Database connection Failure:' . $e->getMessage() . "<br/>");
        die();
    }
}
function sec2dhms($sec, $padHours = false)
{
    $remaining = $sec;
    $dhms = "";

    // There are 86400 secs in a day
    $days = floor($sec / 86400);
    $remaining = $sec - ($days * 86400);
    $dhms .= str_pad($days, 2, "0", STR_PAD_LEFT) . ':';


    // There are 3600 secs in an hour
    $hours = floor($remaining / 3600);
    $remaining = $remaining - ($hours * 3600);
    $dhms .= str_pad($hours, 2, "0", STR_PAD_LEFT) . ':';

    // 60 mins
    $mins = floor($remaining / 60);
    $remaining = $remaining - ($mins * 60);
    $dhms .= str_pad($mins, 2, "0", STR_PAD_LEFT) . ':';

    // Secs is the remainders
    $secs = $remaining;
    $dhms .= str_pad($secs, 2, "0", STR_PAD_LEFT);

    return $dhms;
}

function vpnStatusByShipID($shipid,$type,$db,$querydebugmode) {
    // Look up the last reported VPN status for this ship

    $stmt = runQuery("SELECT pcs.status_value FROM pc_status AS pcs LEFT JOIN pc_list AS pcl ON pcs.pc_id=pcl.id LEFT JOIN ship_list AS sl ON sl.id=pcl.ship_id WHERE sl.id=? AND status_type='ping' ORDER BY pcs.dateadded DESC LIMIT 1",array($shipid),'select', $type, $db, $querydebugmode);
    $result = $stmt->rowCount();
    if ($result < 1 ) {
        // We got no result, return zero
        return false;
    } else {
        // Pull the actual value and see if larger then zero
        $stmt->setFetchMode(PDO::FETCH_NUM);
        $status = $stmt->fetch();

        if ($status > 0) {
            return true;
        } else {
            return false;
        }
    }

}

function avStatusByShipID($shipid,$type,$db,$querydebugmode)
{
    // See what we have report wise for this ship
    $stmt = runQuery('SELECT MAX(gs.ideage) FROM gav_status AS gs LEFT JOIN pc_list AS pcl ON pcl.id=gs.pc_id LEFT JOIN ship_list AS sl ON sl.id=pcl.ship_id WHERE gs.dateadded > DATE_SUB(NOW(),INTERVAL 1 WEEK) AND sl.id=?',array($shipid),'select', $type, $db, $querydebugmode);
    $row_count = $stmt->rowCount();
    if ($row_count < 1){
        return false;

    }else{
        $stmt->setFetchMode(PDO::FETCH_NUM);
        $ideage = $stmt->fetch();

        if (!is_numeric($ideage)){
            return false;
        }
        if ($ideage > 72){
            return false;

        }else{
            return true;
        }
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


        if ( $msg == "" ) {
            echo "                              ";
        } else {
            // only if the number of seconds is even, print a dot to keep rate of screen updates lower
            if ( time() % 2 == 0) {
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

function params($string,$data,$querydebugmode)
{
    if (($data == NULL) || ($data == '')) {
        if($querydebugmode == 'true')
        { logline("QUERYDEBUG", "$string");
        }
    }
    else{
        $data == array_values($data);
        foreach ($data as $k => $v)
        {
            if (is_string($k)) {
                $string = str_replace("$k", $v, $string);
            } else
            {
                $string = preg_replace('/\?/', $v, $string, 1);
            }
        }
        if($querydebugmode == 'true')
        {        logline("QUERYDEBUG", "$string");
        }}
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
                params($query, $queryparameters,$querydebugmode);
            }
            $stmt->execute($queryparameters);


        } catch (PDOException $e) {

            logline("ERROR","Caught an exception: ".$e->getMessage());

            if ($querytype == 'select') {
                if ($querydebugmode) {
                    params($query, $queryparameters,$querydebugmode);
                    queryselectcheck($stmt);
                }
            } else if ($querytype == 'insert') {
                if ($querydebugmode) {
                    params($query, $queryparameters,$querydebugmode);
                    queryinsertcheck($db);
                }
            } else {
                if ($querydebugmode) {
                    params($query, $queryparameters,$querydebugnode);
                }
            }
        }

    }
    return $stmt;
}

function infoByShipID($shipid,$type,$db,$querydebugmode) {
    // First we check if there is an overlapping IP range

    $stmt = runQuery("SELECT lanrange from ship_list WHERE id=?",array($shipid),'select', $type, $db, $querydebugmode);
    $stmt->setFetchMode(PDO::FETCH_NUM);
    $lanrange = $stmt->fetch();

    $stmt = runQuery("SELECT id FROM ship_list WHERE lanrange =?",array($lanrange[0]),'select', $type, $db, $querydebugmode);
    $rowcount = $stmt->rowCount();
    if ( $rowcount > 1 ) {
        return "WARNING: Duplicate IP range!!";
    } else {
        return NULL;
    }
}


?>

</body>
</html>

