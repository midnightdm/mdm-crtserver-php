<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}

$config =  [
    'timeout'  =>   600,
    'errEmail' =>   getEnv('MDM_CRT_ERR_EML'),
    'dbHost'   =>   getEnv('MDM_CRT_DB_HOST'),
    'dbUser'   =>   getEnv('MDM_CRT_DB_USR'),
    'dbPwd'    =>   getEnv('MDM_CRT_DB_PWD'),
    'dbName'   =>   getEnv('MDM_CRT_DB_NAME'),
    'image_base' => 'https://www.clintonrivertraffic.com/',
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
    'localVesselFilter' => [366986450, 368024780, 366970820, 366970780, 366970360, 367614749]
  ];