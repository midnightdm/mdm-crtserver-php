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
    'appPath'=> 'C:/app',
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


//Convert into array & Put into CONSTANT
define('GOOGLE_APPLICATION_CREDENTIALS', json_decode($strJsonFileContents, true));

include_once('e:/app/vendor/autoload.php');
include_once('crtfunctions_helper.php');
include_once('TextToSpeech.class.php');
$vo = new MyTextToSpeech();
$str = "Towing vessel, Artco Innovation, passed the Clinton drawbridge traveling downriver.";

$rawAudio = $vo->getSpeech($str);
file_put_contents('e:\app\logs\test_audio.mp3', $rawAudio);

