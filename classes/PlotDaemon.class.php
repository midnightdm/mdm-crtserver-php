 <?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *  
 *  classes/PlotDaemon.class.php
 * 
 *  This class is a daemon that runs an endless while loop, listens
 *  for raw NMEA data, decodes useful AIS information and stores it 
 *  as LivePlot objects in an array. 
 *  
 *  setup() is a substitute for __construct. Instantiate then run start().
 *
 */

class PlotDaemon {
    public $liveScan;
    public $rowsNow;
    public $rowsBefore;
    protected $run;
    protected $source;
    public $destination;
    protected $kmlpath;
    public $lastCleanUp;
    public $LiveScanModel;
	public $VesselsModel;
	public $AlertsModel;
    public $PassagesModel;
    public $timeout;

    protected function setup() {
        $this->livePlot = array();
        $this->rowsBefore = 0;
        $this->LiveScanModel = new LiveScanModel();
        $this->VesselsModel = new VesselsModel();
        $this->AlertsModel = new AlertsModel();
        $this->PassagesModel = new PassagesModel();

        /* This app can read AIS log files or process packets from a UDP stream. 
         *
         * For the LOGFILE option:
         *   - Configure Output Options of your AISMon program to save a log file.
         *   - Type the path to that file on line 12 of plotserver.php.
         *   - Enable $this->source = 'log' below.
         *   - Comment out $this->source = 'udp' below.
         * 
         * For the UDP stream option:
         *   - Configure Output Options of your AISMon for UDP Output (IP:Port = 127.0.0.1:10110)
         *      HINT: AISMon version 2.2.0 has no config file, so
         *            you'll have to add settings upon each start. 
         *            See classes/AISMonSS.png for screenshot.
         *   - Enable $this->source = 'udp' below.
         *   - Comment out $this->source = 'log' below.
         */
        //$this->source = 'log';
        $this->source = 'udp';

        /* Also this app can save processed data by posting to an api or saving as a kml file
         * 
         * For the API file option:
         *   - Enable $this->destination = 'api' below.
         *   - Comment out $this->destination = 'kml' below.
         *   - Define API_POST_URL path line 15 of plotserver.php.
         *   - The path in $this->kmlpath below will be ignored.
         *   
         * 
         * For the KML file option:
         *   - Enable $this->destination = 'kml' below.
         *   - Comment out $this->destination = 'api' below.
         *   - Edit a file path to $this->kmlpath = 'path/filename.kml' below that.
         *   - The API_POST_URL on line 15 of plotserver.php will be ignored.
         */
        //$this->destination = 'api';
        $this->destination = 'udp';
        $this->kmlpath = 'E:\xampp\htdocs\plotserver\google_ships.kml';
        $this->lastCleanUp = 0; //Used to increment cleanup routine

        $config =  [
            'kmlUrl'   =>   '',
            'kmlUrlTest' => getEnv('BASE_URL').'js/pp_google-test',
            'datasource' => 'kml', //Either 'api' or 'kml'
            'testMode' =>   false,
            'jsonUrl'  =>   getEnv('BASE_URL').'livejson',
            'timeout'  =>   600,
            'errEmail' =>   getEnv('MDM_CRT_ERR_EML'),
            'dbHost'   =>   getEnv('MDM_CRT_DB_HOST'),
            'dbUser'   =>   getEnv('MDM_CRT_DB_USR'),
            'dbPwd'    =>   getEnv('MDM_CRT_DB_PWD'),
            'dbName'   =>   getEnv('MDM_CRT_DB_NAME'),
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
        $this->kmlUrl = $config['kmlUrl']; 
        $this->kmlUrlTest  =  $config['kmlUrlTest']; //Used when Test Mode is true
        $this->testMode = boolval($config['testMode']);
        $this->datasource = $config['datasource']; //Either 'kml' or 'api'
        $this->jsonUrl = $config['jsonUrl'];
        $this->timeout = intval($config['timeout']);
        $this->lastCleanUp = 0;
        $this->errEmail = $config['errEmail'];    
        $this->nonVesselFilter = $config['nonVesselFilter'];
        $this->localVesselFilter = $config['localVesselFilter'];



        

    }

    public function start() {
        echo "\t\t >>>     Type CTRL+C at any time to quit.    <<<\r\nPlotDaemon::start() \r\n";
        $this->setup();
        $this->run = true;
        $this->reloadSavedScans();       $this->run();
    }

    protected function run() {
        $ais = new MyAIS($this);
        
        /* UDP live port version starts here */
        if($this->source == 'udp') {
            //Reduce errors
            error_reporting(~E_WARNING);
            //Create a UDP socket
            if(!($sock = socket_create(AF_INET, SOCK_DGRAM, 0))) {
                $errorcode = socket_last_error();
                $errormsg = socket_strerror($errorcode);
                die("Couldn't create socket: [$errorcode] $errormsg \n");
            }
            echo "Socket created \n";
            // Bind the source address
            if( !socket_bind($sock, "127.0.0.1", 10110) ) {
                $errorcode = socket_last_error();
                $errormsg = socket_strerror($errorcode);
                die("Could not bind socket : [$errorcode] $errormsg \n");
            }
            echo "Socket bind OK \n";
            
            while($this->run==true) {
                //** This is Main Loop this server for the UDP version ** 
                //Do some communication, this loop can handle multiple clients
                echo "Waiting for data ... \n";
                //Receive some data
                $r = socket_recvfrom($sock, $buf, 512, 0, $remote_ip, $remote_port);
                echo "$remote_ip : $remote_port -- " . $buf;
                //Send back the data to the decoder
                $ais->process_ais_buf($buf);

                //Since above process is a loop, you can't add any more below. 
                //Put further repeating instructions in THAT loop (MyAIS.class.php)
            }
            socket_close($sock);
        
         /* LOG file reading version starts here*/   
        } elseif($this->source == 'log') {
            while($this->run==true) {
                //** This is Main Loop of this server for the LOG version ** 
                // Add each read line to an array
                $file = file_get_contents(AIS_LOG_PATH); 
                if ($file) {
                    $array = explode(PHP_EOL, $file);
                    $this->rowsNow = count($array); //Saves last read line number so later resume won't duplicate
                    echo "Rows now: ".$this->rowsNow. ". Rows before: ".$this->rowsBefore.".\r\n";
                    if($this->rowsNow == $this->rowsBefore) { //Retry later if no new lines
                        sleep(5);
                        continue;
                    }
                }
                foreach($array as $element) {
                    //echo $element."\r\n";
                    $ais->process_ais_buf($element."\r\n"); //Linefeed is needed by filter in decoder
                }
                //Remove old plots every 3 minutes if using api
                $isCleanUpTime = (time() - $this->lastCleanUp) > 180;
                if($isCleanUpTime ) {
                    $this->removeOldScans(); 
                }                               
                $this->rowsBefore = $this->rowsNow;
                sleep(10);
            }

        } else {
            exit("ERROR: PlotDaemon::setup() doesn't have a correct source option set.");
        }
    }

    public function saveKml() {
        $kml = "";
        $head = <<<_END
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://earth.google.com/kml/2.0">
<Document>
<Style id="mystile1r">
<LineStyle>
<color>ff0000ff</color>
</LineStyle>
<PolyStyle>
<color>7f0000ff</color>
</PolyStyle>
</Style>
<Style id="mystyle1y">
<LineStyle>
<color>ff00ffff</color>
</LineStyle>
<PolyStyle>
<color>7f00ffff</color>
</PolyStyle>
</Style>
<Style id="mystyle1b">
<LineStyle>
<color>ffff0000</color>
</LineStyle>
<PolyStyle>
<color>7fff0000</color>
</PolyStyle>
</Style>
<Style id="mystyle2">
<IconStyle id="1">
<Icon>
<href>root://icons/palette-4.png</href>
<x>32</x>
<y>0</y>
<w>32</w>
</Icon>
</IconStyle>
</Style>
_END;

        //Add head to kml string
        $kml .= $head;
        $now = time();
        //Add placemarks to kml string
        if(count($this->livePlot)>0 ) {
            foreach($this->livePlot as $key => $obj) {
                if(!is_object($obj)) {
                    echo "o is not an object\r\n";
                    continue;
                }
                //Remove when no data for 3 min
                if(($now-$obj->ts)>180) {
                    echo "Removing old $key\r\n";
                    unset($this->livePlot[$key]);
                }
                $pm = "\r\n<Placemark>\n<description>";
                $pm .= "Name ".$obj->name."\r\n";
                $pm .= "MMSI ".$obj->id."\r\n";
                $pm .= "c/s ---\r\n";
                $pm .= "IMO 0000000\r\n";
                $pm .= "Dest ---\r\n";
                $pm .= "Eta ---\r\n";
                $pm .= "Pos ".$obj->lat." ".$obj->lon."\r\n";
                $pm .= "Speed ".$obj->speed."\r\n";
                $pm .= "Course ".$obj->course."\r\n";
                $pm .= "Heading ---\r\n";
                $pm .= "Length ---\r\n";
                $pm .= "Width ---\r\n";
                $pm .= "Draft ---\r\n";
                $pm .= "Time ".$obj->ts."\r\n";
                $pm .= "</description>\r\n<name>".$obj->name."</name>\r\n";
                $pm .= "<styleUrl>#mystyle2</styleUrl>\r\n";
                $pm .= "<visibility>1</visibility>\r\n";
                $pm .= "<Point>\r\n";
                $pm .= "<altitudeMode>absolute</altitudeMode>\r\n";
                $pm .= "<coordinates>".$obj->lon.", ".$obj->lat.",0.0</coordinates>\r\n";
                $pm .= "</Point>\r\n</Placemark>\r\n";
                $kml .= $pm;
            }
        }
        
        //Add foot to kml string
        $foot = "</Document></kml>";
        $kml .= $foot;
        
        $res = file_put_contents($this->kmlpath, $kml, LOCK_EX);
        if($res) {
            echo "Saved ".$res." bytes to ".$this->kmlpath."\r\n";
        } else {
            echo "ERROR: file_put_contents failed.\r\n";
        }        
    }

    public function removeOldScans() {
        $now = time(); 
        if(($now-$this->lastCleanUp) > 180) {
            //Only perform once every 3 min to reduce db queries
            echo "PlotDaemon::removeOldScans()... \n";     
            foreach($this->liveScan as $key => $obj) {  
                //Test age of update.  
                $deleteIt = false;       
                echo "   ... Vessel ". $obj->liveName . " last updated ". ($now - $obj->liveLastTS) . " seconds ago (Timeout is " . $this->timeout . " seconds) ";
                if(($now - $this->timeout) > $obj->liveLastTS) { //1-Q) Is record is older than timeout value?
                    /*1-A) Yes, then 
                     *     2-Q) Is it near the edge of receiving range?
                     *         (Seperate check for upriver & downriver vessels removed 6/13/21) */
                    if(($obj->liveLastLat > MARKER_ALPHA_LAT || $obj->liveLastLat < MARKER_DELTA_LAT)) {
                        //    2-A) Yes, then save it to passages table
                        echo "is near edge of range.\r\n";
                        $deleteIt = true;
                    } else {
                        //    2-A) No.
                        echo "is NOT near edge of range.\r\n";
                        //        3-Q) Is record older than 8 hours?
                        if ($now - $obj->liveLastTS > 21600) {
                            //      3-A) Yes
                            echo "The record is 8 hours old";
                            //      4-Q) Is vessel parked?
                            if(intval(rtrim($obj->liveSpeed, "kts"))<1) {
                                //    4-A) Yes, then keep in live.
                                echo ", but vessel is parked, so keeping in live";
                            } else {
                                //    4-A) No, speed is interupted value.
                                echo " with no updates so delete it.\r\n";
                                $deleteIt = true;
                            }
                        } else {
                            //      3-A) No, then keep waiting.
                            echo " keeping in live.\r\n";
                        }
                    }
                }
            
                //Do deletes according to test conditions
                if($deleteIt) {
                    $obj->savePassageIfComplete(true);          
                    echo 'Deleting old livescan record for '.$obj->liveName .' '.getNow()."\n";
                    if($this->LiveScanModel->deleteLiveScan($obj->liveVesselID)) {
                        //Table delete was sucessful, remove object from array
                        unset($this->liveScan[$key]);
                    } else {
                        error_log('Error deleting LiveScan ' . $obj->liveVesselID);
                    }
                }
                //1-A) No, record is fresh, so keep in live.
                echo "\r\n";
            }    
        }   
        $this->lastCleanUp = $now;
    } 

    protected function reloadSavedScans() {
        echo "CRTDaemon::reloadSavedScans() started ".getNow()."...\n";
        if(!($data = $this->LiveScanModel->getAllLiveScans())) {
          echo "   ... No old scans. ".getNow()."\n";
          return;
        }
        $this->liveScan = array();
        foreach($data as $row) {      
          $key = 'mmsi'. $row['liveVesselID'];
          echo "   ... Reloading ".$row['liveName']."\n";
          $this->liveScan[$key] = new LiveScan(null, null, null, null, null, null, null, $this, true, $row);
          $this->liveScan[$key]->lookUpVessel();
          $this->liveScan[$key]->calculateLocation(true); //supress event trigger
        }
    }
    
}


