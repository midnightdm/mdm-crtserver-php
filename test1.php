<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}

include_once('classes/Firestore.class.php');
include_once('classes/crtfunctions_helper.php');
include_once('classes/AlertsModel.class.php');


$am = new AlertsModel();

$ret = $am->triggerEvent('alphadp');
exit($ret);