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
    include_once('src/Firestore.class.php');
    include_once('src/LiveScan.class.php');
    include_once('src/LiveScanModel.class.php');
    include_once('src/AdminTriggersModel.class.php');
    include_once('src/Vessels.class.php');
    include_once('src/VesselsModel.class.php');
    include_once('src/Zone.class.php');
    include_once('src/Location.class.php');
    include_once('src/AlertsModel.class.php');
    include_once('src/PassagesModel.class.php');
    include_once('src/Messages.class.php');
    include_once('src/CloudStorage.class.php');
    include_once('src/TextToSpeech.class.php');
// } else {
//     echo "AIS TEST MODE";
// }

set_error_handler('errorHandler', E_ALL);

$path =  'vendor/autoload.php';
//echo "Vendor Path = ".$path."\n";
require_once($path);

// * * * Constant Definitions * * * 
putenv('GOOGLE_APPLICATION_CREDENTIALS=c:\app\mdm-qcrt-demo-1-f28500aebc1a.json');

/* River orientation (Set one that is prevailing for your waypoints in the 5th position below for your app)
    A North-South or South-North river setting employs lat to calculate upriver/downriver vessel direction.
    An East-West or West-East river setting employs lon to calculate upriver/downriver vessel direction
*/
define('NORTH_SOUTH', 0); //North is upriver, lat increases
define('EAST_WEST',   1); //East is upriver, lon increases
define('SOUTH_NORTH', 2); //South is upriver, lat decreases
define('WEST_EAST',   3); //West is upriver, lon decreases
define('RIVER_ORIENTATION_SETTING', NORTH_SOUTH); //Set Yours Here


//Marker Alpha Lat is 3 mi upriver Lock 13
define('MARKER_ALPHA_LAT', 41.938785);

//Marker Bravo Lat is Lock 13
define ('MARKER_BRAVO_LAT', 41.897258);

//Marker Charlie Lat is RR bridge
define ('MARKER_CHARLIE_LAT', 41.836353);

//Marker Delta Lat is 3 mi downriver RR bridge
define('MARKER_DELTA_LAT', 41.800704);

//Set path to live log or sample data file here (See PlotDaemon::setup() for more)
define('AIS_LOG_PATH', 'AISMon.log');

//Set the URL of the API that will save the decoded data
define('API_POST_URL', getenv('MDM_CRT_PLOT_POST'));
define('API_DELETE_URL', getenv('MDM_CRT_PLOT_DELETE'));



//This is the active part of the app. It creates the daemon object then starts the loop.
$plotDaemon = new PlotDaemon($config);
$plotDaemon->start();

/*  The remainer of the script is disabled unless debugging  */

//$ais = new MyAIS();

// Test Single Message
$test = false;
if ($test) {
	$buf = "!AIVDM,1,1,,A,15DAB600017IlR<0e2SVCC4008Rv,0*64\r\n";
	// Important Note:
	// After receiving input from incoming serial or TCP/IP, call the process_ais_buf(...) method and pass in
	// the input from device for further processing.
	$ais->process_ais_buf($buf);
}

// Test With Large Array Of Messages - represent packets of incoming data from serial port or IP connection
if ($test) {
	$test2_a = array( "sdfdsf!AIVDM,1,1,,B,18JfEB0P007Lcq00gPAdv?v000Sa,0*21\r\n!AIVDM,1,1,,B,18Jjr@00017Kn",
		"jh0gNRtaHH00@06,0*37\r\n!AI","VDM,1,1,,B,18JTd60P017Kh<D0g405cOv00L<c,0*",
		"42\r\n",
		"!AIVDM,2,1,8,A,55RiwV02>3bLS=HJ220t<D4r0<u84j222222221?=PD?55Pf0BTjCQhD,0*73\r\n",
		"!AIVDM,2,2,8,A,3lQH888888",
		"88880,2*6A\r",
		"\n!AIVDM,2,1,9,A,569w5`02>0V090=V221@DpN0<PV222222222221EC8S@:5O`0B4jCQhD,0*11\r\n!AIVDM,2,2,9,A,3lQH88888888880,2*6B\r\n!AIVDO,1,1,",
		",A,D05GdR1MdffpuTf9H0,4*7","E\r\n!AIVDM,1,1,,A,?","8KWpp0kCm2PD00,2*6C\r\n!AIVDM,1,1,,A,?8KWpp1Cf15PD00,2*3B\r\nUIIII"
	);
	foreach ($test2_a as $test2_1) {
		// Important Note:
		// After receiving input from incoming serial or TCP/IP, call the process_ais_buf(...) method and pass in
		// the input from device for further processing.
		$ais->process_ais_buf($test2_1);
	}
}
?>