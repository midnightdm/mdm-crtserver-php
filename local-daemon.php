<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}

//Load all the dependencies
include_once('src/ais.2.php');
include_once('src/MyAIS.class.php');
include_once('src/PlotDaemon.class.php');
include_once('src/LivePlot.class.php');

//Function to convert stored GMT time to central time for display
function getTimeOffset() {
    $tz = new DateTimeZone("America/Chicago");
    $dt = new DateTime();
    $dt->setTimeZone($tz);
    return $dt->format("I") ? -18000 : -21600;
}


//This is the active part of the app. It creates the daemon object then starts the loop.
$plotDaemon = new PlotDaemon();
$plotDaemon->start();