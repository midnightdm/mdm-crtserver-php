<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}

/* * * * * *
 * crtdaemon CLI app runs with command >php crtdaemon.php
 * crtdaemon.php
 * 
 */

// * * * Constant Definitions * * * 
//Marker Alpha Lat is 3 mi upriver Lock 13
define('MARKER_ALPHA_LAT', 41.938785);

//Marker Bravo Lat is Lock 13
define ('MARKER_BRAVO_LAT', 41.897258);

//Marker Charlie Lat is RR bridge
define ('MARKER_CHARLIE_LAT', 41.836353);

//Marker Delta Lat is 3 mi downriver RR bridge
define('MARKER_DELTA_LAT', 41.800704);

// * * * Function Defintions * * *

//function to autoload class files upon instantiation
function myAutoLoader($className) {
    $path      = 'classes/';
    $extension =  '.class.php';
    $fullPath  = $path . $className . $extension;
    echo "   Loading " . $fullPath . '\\n\\n';
    if(!file_exists($fullPath)) {
        return false;
    }
    include_once($fullPath);
}

// * * *  Start of App * * *
//Stops unauthorized running

//$str = "Start"; //the damned thing!";
//$msg = "Unable to run crtdaemon.php\n\n";
//echo "Enter passphrase: ";
//$input = trim(fgets(STDIN, 1024));
//if($input != $str) {
//    die($msg);
//} 


//Load S3 classes
$vendorFile = getEnv('HOST_IS_HEROKU') ?  'vendor/autoload.php' :  '../vendor/autoload.php';
require_once($vendorFile); 

//Load classes as needed
//spl_autoload_register('myAutoLoader');
include_once('classes/CRTdaemon.class.php');
include_once('classes/Dbh.class.php');
include_once('classes/LiveScan.class.php');
include_once('classes/LiveScanModel.class.php');
include_once('classes/Location.class.php');
include_once('classes/PassagesModel.class.php');
include_once('classes/Vessel.class.php');
include_once('classes/VesselsModel.class.php');
include_once('classes/ShipPlotter.class.php');
include_once('classes/ShipPlotterModel.class.php');
include_once('classes/Messages.class.php');
include_once('classes/AlertsModel.class.php');
include_once('classes/TimeLogger.class.php');
include_once('classes/crtfunctions_helper.php');


//Create then start instance of CRTdaemon class that runs as a loop
//$daemon = new CRTdaemon(getEnv('MDM_CRT_CONFIG_PATH'));
//$daemon = new CRTdaemon('crtconfig.php');
$file = getEnv('HOST_IS_HEROKU') ?  'daemon/crtconfig.php' :  getEnv('MDM_CRT_CONFIG_PATH');
$daemon = new CRTdaemon($file);
$daemon->start();