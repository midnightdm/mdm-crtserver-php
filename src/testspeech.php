<?php

if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}

$config =  [
    'liveScanTimeout'     => 300, //liveScan age with no updates before removal
    'cleanUpTimeout'      => 180, //time between removeOldScans() run
    'savePassagesTimeout' => 1200, //time between db writes of passages  
    'errEmail' =>   getEnv('MDM_CRT_ERR_EML'),
    'dbHost'   =>   getEnv('MDM_CRT_DB_HOST'),
    'dbUser'   =>   getEnv('MDM_CRT_DB_USR'),
    'dbPwd'    =>   getEnv('MDM_CRT_DB_PWD'),
    'dbName'   =>   getEnv('MDM_CRT_DB_NAME'),
    'socket_address' => '127.0.0.1',
    'socket_port' => '10111',
    'image_base' => 'https://storage.googleapis.com/www.clintonrivertraffic.com/',
    'no_image' => 'https://storage.googleapis.com/www.clintonrivertraffic.com/images/vessels/no-image-placard.jpg',
    'firestore_json_file' => 'mdm-qcrt-demo-1-f28500aebc1a.json', //Used by Firestore class
    'texttospeech_json_file' => 'mdm-qcrt-demo-1-a05f6f070f3b.json', //Used by TextToSpeech class
    'appPath'=> 'e:/app',
    'cloud_projectID'=>'mdm-qcrt-demo-1',
    'cloud_bucket_name'=>'www.clintonrivertraffic.com',
    'nonVesselFilter' => [
      3660692,
      '003660690',
      '003660692',
      993660690,
      993660692,
      993660691,
      993683001,
      993683030,
      993683031,
      993683032,
      993683033,
      993683034,
      993683035,
      993683111,
      993683112,
      993683113,
      993683108,
      993683109,
      993683110,
      993683155,
      993683156,
      993683157,
      993683158   
    ],
    'localVesselFilter' => [366986450, 368024780, 366970820, 366970780, 366970360, 367614749, 367143650]
  ];
$strJsonFileContents = file_get_contents('e:\app\mdm-qcrt-demo-1-f28500aebc1a.json');

//Make the config array available globally
define('CONFIG_ARR', $config);

//Convert into array & Put into CONSTANT
//define('GOOGLE_APPLICATION_CREDENTIALS', json_decode($strJsonFileContents, true));
//putenv('GOOGLE_APPLICATION_CREDENTIALS=c:\app\mdm-qcrt-demo-1-f28500aebc1a.json');
include_once('e:/app/vendor/autoload.php');
include_once('crtfunctions_helper.php');
include_once('Firestore.class.php');
include_once('AlertsModel.class.php');
include_once('PlotDaemon.class.php');
include_once('TextToSpeech.class.php');

//Load all the dependencies

include_once('LiveScan.class.php');
include_once('LiveScanModel.class.php');
include_once('Vessels.class.php');
include_once('VesselsModel.class.php');

include_once('Zone.class.php');
include_once('Location.class.php');

include_once('PassagesModel.class.php');
include_once('Messages.class.php');
include_once('CloudStorage.class.php');

$ts = time();
$plotDaemon = new PlotDaemon();
$am = new AlertsModel($plotDaemon);
$lsObj = new LiveScan($ts, "America Simulated", 367710540, 41.7868017, -90.2475283, 4, 270, $plotDaemon, false);
echo "Simulating trigger event m516dp";
$am->triggerEvent('m516dp', $lsObj);

// $vo = new MyTextToSpeech();
// $name = 'da366970820';
// $str = "Traveling upriver, towing vessel, Terrebonne, passed 3 miles south of the Clinton drawbridge.";
// $rawAudiof = $vo->getSpeech($str, $name, 2);
// file_put_contents("e:/app/logs/".$name."female.mp3", $rawAudiof); 
// foreach($vo->voice_names as $name) {
//   $rawAudiom = $vo->getSpeech($str, $name, 1);
//   $rawAudiof = $vo->getSpeech($str, $name, 2);
//   file_put_contents("c:/app/logs/".$name."male.mp3", $rawAudiom);
//   file_put_contents("c:/app/logs/".$name."female.mp3", $rawAudiof); 
// }





