<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}

//Tell PHP to save error logs
ini_set("log_errors", 1);
ini_set("error_log", "/tmp/php-error.log");



//Load all the dependencies
include_once('config.php');
//Make the config array available globally
//define('CONFIG_ARR', $config);

include_once('src/ais.2.php');
include_once('src/MyAIS.class.php');
include_once('src/PlotDaemon.class.php');
include_once('src/crtfunctions_helper.php');

//Check Config for aisTestMode which limits some dependencies
//if($config['aisTestMode']===false) {
//    echo "NORMAL MODE";
//    include_once('src/Firestore.class.php');
    include_once('src/LiveScan.class.php');
    include_once('src/LiveScanModel.class.php');
//    include_once('src/AdminTriggersModel.class.php');
    include_once('src/Vessels.class.php');
    include_once('src/VesselsModel.class.php');
    include_once('src/Zone.class.php');
    include_once('src/Location.class.php');
//    include_once('src/AlertsModel.class.php');
//    include_once('src/PassagesModel.class.php');
//    include_once('src/Messages.class.php');
//    include_once('src/CloudStorage.class.php');
//    include_once('src/TextToSpeech.class.php');
// } else {
//     echo "AIS TEST MODE";
// }

set_error_handler('errorHandler', E_ALL);

$path =  'vendor/autoload.php';
//echo "Vendor Path = ".$path."\n";
require_once($path);



//This is the active part of the app. It creates the daemon object then starts the loop.
$plotDaemon = new PlotDaemon($config);

$plotDaemon->setup();
$plotDaemon->run = true;
//$plotDaemon->reloadSavedScans();
//sleep(3);
//$plotDaemon->updateLiveScanLength();



//public function __construct($ts, $name, $id, $lat, $lon, $speed, $course, $pd, $reload=false, $reloadData=[], $isTestMode=false)
$liveScan = new LiveScan(
    time(), 
    'Test Vessel', 
    '368262180', 
    42.1009900, 
    -90.1607883, 
    10.0, 
    180, 
    $this, 
    false, 
    [], 
    $config['aisTestMode']
);

$liveScan->lookUpVessel();
exit;