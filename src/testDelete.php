<?php

if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}
$strJsonFileContents = file_get_contents('e:\app\mdm-qcrt-demo-1-f28500aebc1a.json');


function flog($string) {
  $date = Date('ymd', time()+getTimeOffset());
  //$file = '/../logs/crt_".$date.".log";
  $file = "e:/app/logs/crt_".$date.".log";
  $handle = fopen($file,'a');
  fwrite($handle, $string);
  fclose($handle);
  echo $string;
}

function getTimeOffset($ts="") {
  $tz = new DateTimeZone("America/Chicago");
  if($ts==="") {
    $ts = time();
  }
  $dt = new DateTime();
  $dt->setTimestamp($ts);
  $dt->setTimeZone($tz);
  return $dt->format("I") ? -18000 : -21600;
}

//Convert into array & Put into CONSTANT
define('GOOGLE_APPLICATION_CREDENTIALS', json_decode($strJsonFileContents, true));
//putenv('GOOGLE_APPLICATION_CREDENTIALS=c:\app\mdm-qcrt-demo-1-f28500aebc1a.json');
include_once('e:/app/vendor/autoload.php');
//include_once('crtfunctions_helper.php');
include_once('Firestore.class.php');
// include_once('AlertsModel.class.php');
// include_once('PlotDaemon.class.php');
// include_once('TextToSpeech.class.php');

//Load all the dependencies

//include_once('LiveScan.class.php');
include_once('LiveScanModel.class.php');
// include_once('Vessels.class.php');
// include_once('VesselsModel.class.php');

// include_once('Zone.class.php');
// include_once('Location.class.php');

// include_once('PassagesModel.class.php');
// include_once('Messages.class.php');
// include_once('CloudStorage.class.php');
$lsm = new LiveScanModel();
$lsm->cleanupDeletes();