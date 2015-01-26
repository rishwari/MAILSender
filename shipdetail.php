<?php
/**
 * Created by PhpStorm.
 * User: brijniersce
 * Date: 9/9/14
 * Time: 10:03 AM
 */
include 'itss_conf.php';
$type = 'pdo';
$querydebugmode = 'false';


// Setup DB connection
$db = DB_connect($collect_av_db_host,$collect_av_db,$collect_av_db_user,$collect_av_db_pwd);
// Global vars
$iconSize = 64;


// Extract the shipid
if ($debug)
{
    logline("INFO", "Extracting shipid from IT Fleet Status");
}
$shipid = $_GET["shipid"];

$stmt = runQuery("SELECT shipname FROM ship_list WHERE id=?",array($shipid),'select', $type, $db, $querydebugmode);
$stmt->setFetchMode(PDO::FETCH_NUM);
$shipname= $stmt->fetch();

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
    <h1 class="h1">Ship Detail for <?php print " $shipname[0] (" . date("D d M Y - H:i:s", time()); ?>)</h1>
    <table border="0" CELLSPACING="5">

<?php
$stmt = runQuery("SELECT id,pcname,ipaddr,memory,operatingsys,model,serial,datechanged FROM pc_list WHERE ship_id=?",array($shipid),'select', $type, $db, $querydebugmode);
$stmt->setFetchMode(PDO::FETCH_NUM);
$shipdetails= $stmt->fetchAll();
//while ( list($pcid,$pcname,$ipaddr,$memory,$os,$model,$serial,$datechanged) = mysql_fetch_row($result) ) {
foreach ($shipdetails as $shipdetail){
    echo "<tr>\n";
    echo "<td><img src=\"icons/pc.png\" width=\"$iconSize\" height=\"$iconSize\" /><br>$shipdetail[1]</td>\n";
    echo "<td>IP address: $shipdetail[2]<br>Memory: $shipdetail[3] MB<br>Model: $shipdetail[5]<br>Serial: $shipdetail[6]<br>Date changed: $shipdetail[7]<br> DB ID: $shipdetail[0]\n";


    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Figure out the AV status for this PC
    //////////////////////////////////////////////////////////////////////////////////////////////////////////////
    $statusFlag = "Bug_bad.png";
    $stmt = runQuery("SELECT version,ideage,sophosEngineVer,error,dateadded FROM gav_status WHERE pc_id=? ORDER BY dateadded DESC LIMIT 1",array($shipdetail[0]),'select', $type, $db, $querydebugmode);
    $stmt->setFetchMode(PDO::FETCH_NUM);
    $shiplist= $stmt->fetch();
    //list($version,$ideage,$enginever,$error,$dateadded) = mysql_fetch_row($gav);
    foreach ($shiplist as $shipvalues)
        if ( ($shipvalues[0] == "") && ($shipvalues[1] == "") )
        {
            // No sophos on this host
            $statusText = "Sophos not installed";
        } else {
            $statusText = "CheckGAV Version: $shipvalues[0]<br>IDE age: $shipvalues[1] hrs<br>Sophos Engine: $shipvalues[2]<br>Error: $shipvalues[3]<br>Date added: $shipvalues[4]";
            $statusFlag = "Bug_good.png";
        }

    if ( $shipvalues[1] > 48 ) {
        $statusFlag = "Bug_bad.png";
    }

    echo "<td><img src=\"icons/$statusFlag\" width=\"$iconSize\" height=\"$iconSize\" /></td>\n";
    echo "<td>$statusText</td>\n";

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Figure out the VPN status for this PC
    //////////////////////////////////////////////////////////////////////////////////////////////////////////////
    $statusFlag = "vpn_bad.png";

    $stmt = runQuery("SELECT status_value FROM pc_status WHERE pc_id=? AND status_type = 'ping' ORDER BY dateadded DESC LIMIT 1",array($shipdetail[0]),'select', $type, $db, $querydebugmode);

    $stmt->setFetchMode(PDO::FETCH_NUM);
    $vpn= $stmt->fetch();
    $count = $stmt->rowCount();

    if ( $count ==  0) {
        // No entries for this host at all
        $statusText = "No ping records for this host";
    } elseif ($vpn[0] == 0) {
        $statusText = "Host did not respond to ping";
    } else {
        $statusText = "Ping response in $vpn[0] ms";
        $statusFlag = "vpn_good.png";
    }
    echo "<td><img src=\"icons/$statusFlag\" width=\"$iconSize\" height=\"$iconSize\" /></td>\n";
    echo "<td>$statusText</td>\n";
    echo "</tr>\n";
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
        if ($querydebugmode == 'true')
        {
            logline("QUERYDEBUG", "$string");
        }}
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
        {
            logline("QUERYDEBUG", "$string");
        }
    }}

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
                    params($query, $queryparameters,$querydebugmode);
                }
            }
        }

    }
    return $stmt;
}
?>


    </table>
