 <?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}

/*  *   *   *   *   *   *   *   *   *   *   *  * 
 *   This app processes packets from a UDP stream. 
 *   Configure Output Options of your external AISMon app for 
 *      UDP Output = (enabled) 
 *      IP:Port    = (127.0.0.1:10110)
 *
 *     HINT: AISMon version 2.2.0 has no config file, so
 *           you'll have to add settings upon each start. 
 *           See classes/AISMonSS.png for screenshot.
 *
 *   NOTE: See config.php for app settings and note environmental varables there.
 */

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
    public $lastCleanUp;
    public $LiveScanModel;
	public $VesselsModel;
	public $AlertsModel;
    public $PassagesModel;
    public $timeout;
    public $image_base;


    protected function setup() {
        $this->livePlot = array();
        $this->rowsBefore = 0;
        $this->LiveScanModel = new LiveScanModel();
        $this->VesselsModel = new VesselsModel();
        $this->AlertsModel = new AlertsModel();
        $this->PassagesModel = new PassagesModel();
        $this->lastCleanUp = 0; //Used to increment cleanup routine
        $this->timeout = intval($config['timeout']);   //$config array in config.php      
        $this->errEmail = $config['errEmail']; 
        $this->dbHost = $config['dbHost'];
        $this->dbUser = $config['dbUser'];
        $this->dbPwd  = $config['dbPwd'];
        $this->dbName = $config['dbName'];   
        $this->nonVesselFilter = $config['nonVesselFilter'];
        $this->localVesselFilter = $config['localVesselFilter'];
        $this->image_base = $config['image_base'];      
    }

    public function start() {
        echo "\t\t >>>     Type CTRL+C at any time to quit.    <<<\r\nPlotDaemon::start() \r\n";
        $this->setup();
        $this->run = true;
        $this->reloadSavedScans();
        $this->run();
    }

    protected function run() {
        $ais = new MyAIS($this);      

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


