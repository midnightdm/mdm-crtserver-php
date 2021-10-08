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
    'image_base' => 'https://www.clintonrivertraffic.com/',
    'firestore_json_file' => 'mdm-qcrt-demo-1-f28500aebc1a.json', //Used by Firestore class
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