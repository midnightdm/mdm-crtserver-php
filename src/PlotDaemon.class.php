 <?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}

/*  *   *   *   *   *   *   *   *   *   *   *  * 
 *   This app processes packets from a UDP stream. 
 *   Configure Output Options of your external AISMon app for 
 *      UDP Output = (enabled) 
 *      IP:Port    = (127.0.0.1:10111)
 *
 *     HINT: AISMon version 2.2.0 has no config file, so
 *           you'll have to add settings upon each start. 
 *           See src/AISMonSS.png for screenshot.
 *
 *   NOTE: See config.php for app settings and note environmental varables there.
 */

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *  
 *  src/PlotDaemon.class.php
 * 
 *  This class is a daemon that runs an endless while loop, listens
 *  for raw NMEA data, decodes useful AIS information and stores it 
 *  as LiveScan objects in an array. 
 *  
 *  setup() is a substitute for __construct. Instantiate then run start().
 *
 */
class PlotDaemon {
  public $liveScan;
  public $rowsNow;
  public $rowsBefore;
  protected $run;
  public $encoderIsEnabled = false;
  public $encoderIsManualEnabled = false;
  public $encoderEnabledTS = null;
  public $encoderEnabledScore = 0;
  public $encoderEnablerVesselID = null;
  public $encoderEnablerVesselDir = null;
  public $aisTestMode;
  public $config;
  
  public $currentCameraName;
  public $lastCameraName;
  public $lastCameraSwitch;

  public $lastCleanUp;
  public $lastJsonSave;

  public $LiveScanModel;
  public $AdminTriggersModel;
  public $VesselsModel;
  public $alertsAll;
  public $alertsPassenger;
  public $alertsAllQC;
  public $alertsPassengerQC;
  public $AlertsModel;
  public $PassagesModel;
  public $Facebook;
  
  public $cameraTimeout;
  public $cleanUpTimeout;
  public $liveScanTimeout;
  public $savePassagesTimeout;
  public $image_base;


  public function __construct($config_ref) {
    $this->config = $config_ref;   
  }

  public function setup() {
    $now    = time();
    
    $this->liveScan            = array(); //LiveScan objects - the heart of this app - get stored here
    $this->alertsAll           = array();
    $this->alertsPassenger     = array();
    $this->rowsBefore          = 0;
    $this->LiveScanModel       = new LiveScanModel();
    $this->AdminTriggersModel  = new AdminTriggersModel();
    $this->VesselsModel        = new VesselsModel();  
    $this->AlertsModel         = new AlertsModel($this);
    $this->PassagesModel       = new PassagesModel();
    //$this->Twitter             = new Twitter();
    //$this->Facebook            = new Facebook($this->config);
    $this->lastCleanUp         = $now-50; //Used to increment cleanup routine
   
    $this->lastJsonSave        = $now-10; //Used to increment liveScan.json save
    $this->lastPassagesSave    = $now-50; //Increments savePassages routine
    
    
     //Regionalized as 'clinton', 'clintoncf' & 'qc'
    $this->defaultCameraName   = [
        "clinton"    => ["srcID"=>"CabinUR",   "zoom"=> 0, "vesselsInRange" => ["None"]],
        "clintoncf"  => ["srcID"=>"CabinUR",   "zoom"=> 0, "vesselsInRange" => ["None"] ],
        "qc"         => ["srcID"=>"PortByron", "zoom"=> 0, "vesselsInRange" => ["None"] ]
    ];
    
     $this->currentCameraName   = [
        "clinton"    => ["srcID"=>"CabinUR",   "zoom"=> 0, "vesselsInRange" => ["None"] ],
        "clintoncf"  => ["srcID"=>"CabinUR",   "zoom"=> 0, "vesselsInRange" => ["None"] ],
        "qc"         => ["srcID"=>"PortByron", "zoom"=> 0, "vesselsInRange" => ["None"] ]
    ];
    $this->lastCameraName      = [
        "clinton"    => ["srcID"=>"CabinUR",   "zoom"=> 0, "vesselsInRange" => ["None"]],
        "clintoncf"  => ["srcID"=>"CabinUR",   "zoom"=> 0, "vesselsInRange" => ["None"] ],
        "qc"         => ["srcID"=>"PortByron", "zoom"=> 0, "vesselsInRange" => ["None"] ]
    ];

     //Prevents rapid camera switching if 2 vessels enable cam
    $this->lastCameraSwitch    = [
        "clinton"    =>$now-50,
        "clintoncf"  =>$now-50,
        "clinton"    =>$now-50
    ];

    //Set values below in $config array in config.php
    $this->cameraTimeout       = 30;
    $this->liveScanTimeout     = intval($this->config['liveScanTimeout']); 
    $this->cleanUpTimeout      = intval($this->config['cleanUpTimeout']); 
    $this->savePassagesTimeout = intval($this->config['savePassagesTimeout']);  
    $this->socketDataTimer     = 0;
    $this->errEmail            = $this->config['errEmail']; 
    //$this->dbHost              = $this->config['dbHost'];
    //$this->dbUser              = $this->config['dbUser'];
    //$this->dbPwd               = $this->config['dbPwd'];
    //$this->dbName              = $this->config['dbName'];   
    $this->nonVesselFilter     = $this->config['nonVesselFilter'];
    $this->localVesselFilter   = $this->config['localVesselFilter'];
    $this->image_base          = $this->config['image_base'];
    $this->socket_address      = $this->config['socket_address'];
    $this->socket_port         = $this->config['socket_port']; 
       
    $this->encoderUrl          = $this->config['encoderUrl'];
    $this->streamUrl           = $this->config['streamUrl'];
    $this->streamPath          = $this->config['streamPath'];
    $this->streamKey           = $this->config['streamKey'];
    $this->encoderUsr          = $this->config['encoderUsr'];
    $this->encoderPwd          = $this->config['encoderPwd'];
    $this->encoderWatchList    = $this->config['encoderWatchList'];
    $this->encoderUpriverWatch = $this->config['encoderUpriverWatch'];
    $this->encoderDnriverWatch = $this->config['encoderDnriverWatch'];
    $this->encoderTimeoutValue = $this->config['encoderTimeoutValue']; //Seconds
  }

  public function start() {
    flog( " Starting mdm-crt2-server\n\n");  
    flog( "\t\t >>>     Type CTRL+C at any time to quit.    <<<\r\n\n\n");
    if($this->config['aisTestMode']==true) {
        flog("     Using aisTestMode.\n      Firestore Database will not be used.\n       Vessel info will flog only.\r\n");
        $this->aisTestMode = true;
        $this->socket_address      = $this->config['socket_address'];
        $this->socket_port         = $this->config['socket_port'];
    //    $this->run = true;
    //    $this->run();
    //    return;
    }
    $this->setup();
    $this->run = true;
    $this->reloadSavedScans();
    sleep(3);
    $this->reloadSavedAlertsAll();
    sleep(3);       
    $this->reloadSavedAlertsPassenger(); 
    sleep(3);
    $this->updateLiveScanLength();
    $this->checkDbForEncoderStart();
    $this->run();
  }

  protected function run() {
    //Runtime initialization
    $this->ais = new MyAIS($this);      
    //Reduce errors
    error_reporting(~E_WARNING);
    //Create the default UDP socket for receiving AIS NMEA data
    if(!($aisMonSock = socket_create(AF_INET, SOCK_DGRAM, 0))) {
        $errorcode = socket_last_error();
        $errormsg = socket_strerror($errorcode);
        die("Couldn't create inbound socket: [$errorcode] $errormsg \n");
    }
    flog( "Socket created \n");
    $ipandport = $this->socket_address.":".$this->socket_port;

    //A run once message for Brian at start up to enable companion app
    flog( "\033[41m *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  * \033[0m\r\n"); 
    flog( "\033[41m *                       A T T E N T I O N,                     * \033[0m\r\n");
    flog( "\033[41m *                           B R I A N                          * \033[0m\r\n");
    flog( "\033[41m *                                                              * \033[0m\r\n");
    flog( "\033[41m *  Ensure you have AISMon running.                             * \033[0m\r\n");
    flog( "\033[41m *      - Enable 'UDP Output'                                   * \033[0m\r\n");
    flog( "\033[41m *      - Add the following to IP:port                          * \033[0m\r\n");
    flog( "\033[41m *               $ipandport                                * \033[0m\r\n");
    flog( "\033[41m *                                                              * \033[0m\r\n");
    flog( "\033[41m *                                                              * \033[0m\r\n");
    flog( "\033[41m *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  * \033[0m\r\n\r\n");
    
    // Set socket receive timeout
    $timeOutVal = array("sec"=>90, "usec"=>0); 
    if(!socket_set_option($aisMonSock, SOL_SOCKET, SO_RCVTIMEO, $timeOutVal)) {
      trigger_error("Unable to set timeout option on socket.");
    }

    // Bind the source address
    if( !socket_bind($aisMonSock, $this->socket_address, $this->socket_port) ) {
        $errorcode = socket_last_error();
        $errormsg = socket_strerror($errorcode);
        die("Could not bind inbound socket : [$errorcode] $errormsg \n");
    }
    flog( "Socket bind OK \n");

    while($this->run==true) {
      //*********************************************************************** 
      //*                                                                     *
      //*                  This is MAIN LOOP of this server                   * 
      //*                                                                     *
      
      //Do some communication, this loop can handle multiple clients
      if($this->encoderIsEnabled || $this->encoderIsManualEnabled) {
        flog("--- \033[1;31mStream ENABLED\033[0m -----------------------------------------------\n"); 
      } else {
        flog("------------------------------------------------------------------\n");
      }
      flog("\nWaiting for data on $this->socket_address:$this->socket_port ... \n");
      //Receive some data (Silent error flag no longer works in PHP 8)      
      @socket_recvfrom($aisMonSock, $buf, 512, 0, $local_ip, $local_port);
      //  sleep(1); [Necessary?]
      $msgWasSkipped = $buf==null; //True when no buffer output
      //Skip buffer processing if the socket receive timed out.    
      if(!$msgWasSkipped) {
        //DON'T strip \n from end of $buf. It breaks AIS decoding which needs it.
        //$buf = str_replace("\n", '', $buf);
        //Use test mode buffer if set
        if($this->aisTestMode) {
            $this->processBufferTestMode($buf, $local_ip, $local_port);  
        } else {
            $this->processBuffer($buf, $local_ip, $local_port);   
        }

             
      }else {
        flog("  No data received for ".$timeOutVal['sec']." seconds.\n    Proceeding with rest of loop.\n");
      }
      //Reset buffer
      $buf = null;
      //Resuming here if above was skipped with
      //    things to do on each loop besides UDP data handling (Skip if aisTestMode)
      if(!$this->aisTestMode) {
         $this->switchCamera();    //Used built-in 30sec timer
         $this->adminCommands();	 //Uses removeOldScans() timer	
         $this->removeOldScans(); //Resets the timer shared by adminCommands() 
         $this->saveAllScans();   //Has its own interval timer
      }

      flog("                                               ".getNow()."\n");
      //*                                                                      *
      //*                          End of main loop                            *
      //*                                                                      *
      //************************************************************************ 
    }
    socket_close($aisMonSock);
  }

    public function switchCamera() {
        $now = time();
        $hasBeenSwitched = false;
       
        //Tally vessels in view of a camera
        $liveObjects = [];
        $vesselsPerCamera = [];
        $vesselsPerRegion = [];

        foreach($this->liveScan as $key => $liveObj) {
            if($liveObj->inCameraRange) {
                $liveObjects[] = $liveObj;
                $vesselsPerCamera[$liveObj->liveCamera["srcID"]][] = $liveObj->liveName;
                $vesselsPerRegion[$liveObj->liveRegion][] = $liveObj;
            }
        }
        $totalVesselsInRange   = count($liveObjects); //show to regoinal (clintoncf)
        $totalVesselsInClinton = isset($vesselsPerRegion["clinton"]) ? count($vesselsPerRegion["clinton"]) : 0; //show to clinton
        $totalVesselsInQc       = isset($vesselsPerRegion["qc"]) ? count($vesselsPerRegion["qc"]) : 0 ; //show to qc
        
        $vesselsOnPortByronCam = isset($vesselsPerCamera["PortByron"]) ? count($vesselsPerCamera["PortByron"]) : 0;
        $vesselsOnCabinDR      = isset($vesselsPerCamera["CabinDR"]) ? count($vesselsPerCamera["CabinDR"]) : 0;
        $vesselsOnCabinUR      = isset($vesselsPerCamera["CabingUR"]) ? count($vesselsPerCamera["CabinUR"]) : 0;
        $vesselsOnCabin        = $vesselsOnCabinDR + $vesselsOnCabinUR;
        $vesselsOnHistoricalSocCam = isset($vesselsPerCamera["HistorialSoc"]) ? count($vesselsPerCamera["HistoricalSoc"]) : 0 ;
        $vesselsOnSawmillCam   = isset($vesselsPerCamera["Sawmill"]) ? count($vesselsPerCamera["Sawmill"]) : 0;
        
        //Get any database camera changes before doing new changes based on vessel reports
        $updated = $this->updateCameraStatus();

            
        //Evaluate each location
        //  If more than one camera in a region is showing a vessel, rotate through cameras.
        //  If more than one vessel is on a single camera, list all the names
        if($totalVesselsInClinton>0) {
            foreach($vesselsPerRegion['clinton'] as $key => $liveObj) {
                //Is the tested vessel's camera name the one switched on now?
                if($liveObj->liveCamera["srcID"] == $this->currentCameraName["clinton"]["srcID"]) {
                //yes, then has is been on more than the time limit?
                    if($now-$this->lastCameraSwitch["clinton"] > $this->cameraTimeout) {
                    //yes, then is there another to switch to?
                        if($totalVesselsInClinton>1) {
                        //yes, then switch it to the next one
                            $nextKey = $key>$totalVesselsInClinton-1 ? 0 : $key+1;
                            $this->currentCameraName["clinton"] = $vesselsPerRegion["clinton"][$nextKey]->liveCamera;
                            $this->currentCameraName["clinton"]["vesselsInRange"][] = $vesselsPerRegion["clinton"][$nextKey]->liveName;
                            $this->lastCameraSwitch["clinton"] = $now;
                            $this->lastCameraName["clinton"] =  $this->currentCameraName["clinton"];
                            $hasBeenSwitched = true;
                            $name =  $this->currentCameraName["clinton"]["srcID"];
                            flog( "     \033[45m Site clinton switched to camera $name \033[0m\r\n\r\n");
                        }
                    }
                    //no, then use only one found
                    $this->currentCameraName["clinton"] = $vesselsPerRegion["clinton"][0]->liveCamera;
                    $this->currentCameraName["clinton"]["vesselsInRange"][] = $vesselsPerRegion["clinton"][0]->liveName;
                    $this->lastCameraSwitch["clinton"] = $now;
                    $this->lastCameraName["clinton"] =  $this->currentCameraName["clinton"];
                    $hasBeenSwitched = true;
                    $name =  $this->currentCameraName["clinton"]["srcID"];
                    flog( "     \033[45m Site clinton switched to camera $name \033[0m\r\n\r\n");
                }
                //no, then just get the vesselName
            }
        } else {
            //Clear vesselsInRange when none
            if(count($this->currentCameraName["clinton"]["vesselsInRange"])) {
                $hasBeenSwitched = true;
            }
            $this->currentCameraName["clinton"]["vesselsInRange"] = ["None"];   
        }
        if($totalVesselsInQc>0 ) {
            foreach($vesselsPerRegion['qc'] as $key => $liveObj) {
                //Is the tested vessel's camera name the one switched on now?
                if($liveObj->liveCamera["srcID"] == $this->currentCameraName["qc"]["srcID"]) {
                //yes, then has is been on more than the time limit?
                    if($now-$this->lastCameraSwitch["qc"] > $this->cameraTimeout) {
                    //yes, then is there another to switch to?
                        if($totalVesselsInQc>1) {
                        //yes, then switch it to the next one
                            $nextKey = $key>$totalVesselsInQc-1 ? 0 : $key+1;
                            $this->currentCameraName["qc"] = $vesselsPerRegion["qc"][$nextKey]->liveCamera;
                            $this->currentCameraName["qc"]["vesselsInRange"][] = $vesselsPerRegion["qc"][$nextKey]->liveName;
                            $this->lastCameraSwitch["qc"] = $now;
                            $this->lastCameraName["qc"] =  $this->currentCameraName["qc"];
                            $hasBeenSwitched = true;
                            $name =  $this->currentCameraName["qc"]["srcID"];
                            flog( "    \033[45m  Site qc switched to camera $name \033[0m\r\n\r\n");
                        }
                    }
                    //no, then use only one found
                    $this->currentCameraName["qc"] = $vesselsPerRegion["qc"][0]->liveCamera;
                    $this->currentCameraName["qc"]["vesselsInRange"][] = $vesselsPerRegion["qc"][0]->liveName;
                    $this->lastCameraSwitch["qc"] = $now;
                    $this->lastCameraName["qc"] =  $this->currentCameraName["qc"];
                    $hasBeenSwitched = true;
                    $name =  $this->currentCameraName["qc"]["srcID"];
                    flog( "     \033[45m Site qc switched to camera $name \033[0m\r\n\r\n");
                }
                //no, then just get the vesselName
            }
        } else {
            //Clear vesselsInRange when none
            if(count($this->currentCameraName["qc"]["vesselsInRange"])) {
                $hasBeenSwitched = true;
            }
            $this->currentCameraName["qc"]["vesselsInRange"] = ["None"];
        }
        if($totalVesselsInRange > 0) {
            foreach($liveObjects as $key => $liveObj) {
                //Is the tested vessel's camera name the one switched on now?
                if($liveObj->liveCamera["srcID"] == $this->currentCameraName["clintoncf"]["srcID"]) {
                //yes, then has is been on more than the time limit?
                    if($now-$this->lastCameraSwitch["clintoncf"] > $this->cameraTimeout) {
                    //yes, then is there another to switch to?
                        if($totalVesselsInClinton>1) {
                        //yes, then switch it to the next one
                            $nextKey = $key>$totalVesselsInRange-1 ? 0 : $key+1;
                            $this->currentCameraName["clintoncf"] = $liveObjects[$nextKey]->liveCamera;
                            $this->currentCameraName["clintoncf"]["vesselsInRange"][] = $vesselsPerRegion["clintoncf"][$nextKey]->liveName;
                            $this->lastCameraSwitch["clintoncf"] = $now;
                            $this->lastCameraName["clintoncf"] =  $this->currentCameraName["clintoncf"];
                            $hasBeenSwitched = true;
                            $name =  $this->currentCameraName["clintoncf"]["srcID"];
                            flog( "     \033[45m Site clintoncf switched to camera $name \033[0m\r\n\r\n");
                        }
                    }
                    //no, then use only one found
                    $this->currentCameraName["clintoncf"] = $vesselsPerRegion["clintoncf"][0]->liveCamera;
                    $this->currentCameraName["clintoncf"]["vesselsInRange"][] = $vesselsPerRegion["clintoncf"][0]->liveName;
                    $this->lastCameraSwitch["clintoncf"] = $now;
                    $this->lastCameraName["clintoncf"] =  $this->currentCameraName["clintoncf"];
                    $hasBeenSwitched = true;
                    $name =  $this->currentCameraName["clintoncf"]["srcID"];
                    flog( "     \033[45m Site clintoncf switched to camera $name \033[0m\r\n\r\n");
                }
                //no, then just get the vesselName
            }
        } else {
            //Clear vesselsInRange when none
            if(count($this->currentCameraName["clintoncf"]["vesselsInRange"])) {
                $hasBeenSwitched = true;
            }
            $this->currentCameraName["clintoncf"]["vesselsInRange"] = ["None"];
        }
        if($hasBeenSwitched) {
            $this->AdminTriggersModel->setWebcams($this->currentCameraName);
        }
        
        
    }

    public function updateCameraStatus() {
        $cameraNames = $this->AdminTriggersModel->getWebcams();
        //echo var_dump($cameraNames);
        $updated = ["clinton"=>false, "clintoncf"=>false, "qc"=>false];
        foreach($cameraNames as $camera => $data) {
            //Has camera changed remotely?
            if($this->currentCameraName[$camera]["srcID"] != $data["srcID"] ||
               $this->currentCameraName[$camera]["zoom"] != $data["zoom"]) {
                //Yes, then update model
                $updated[$camera] = true;
                $this->currentCameraName[$camera] = $data;
                $this->lastCameraSwitch[$camera] = time();
                $this->lastCameraName[$camera] =  $this->currentCameraName[$camera];
                $name =  $this->currentCameraName[$camera]["srcID"];
                flog( "     \033[45m Site $camera manually switched to camera $name \033[0m\r\n\r\n");
            }
        }
        return $updated;
    }

  public function removeOldScans() {
    $now = time(); 
    // flog("           now: ".$now."\n");
    // flog("-  lastCleanUp: ".$this->lastCleanUp."\n");
    // flog("________________________________________\n:");
    // flog("        Equals: ".$now-$this->lastCleanUp."\n");
    // flog("cleanUpTimeout: ".$this->cleanUpTimeout."\n");
    if(($now-$this->lastCleanUp) > $this->cleanUpTimeout) {
      //Only perform once every few min to reduce db queries
      flog( "    PlotDaemon::removeOldScans()...   \n");     
      foreach($this->liveScan as $key => $obj) {  
        //Test age of transponder update [changed from move update 3/3/22].  
        $deleteIt = false;       
        flog( "      * Vessel ". $obj->liveName . " last transponder ". ($now - $obj->transponderTS) . " seconds ago (Timeout is " . $this->liveScanTimeout . " seconds) ");
        if(($now - $this->liveScanTimeout) > $obj->transponderTS) { //1-Q) Is record older than timeout value?
          /*1-A) Yes, then 
            *     2-Q) Is it near the edge of receiving range?
            *         (Seperate check for upriver & downriver vessels removed 6/13/21) */
          if(($obj->liveLastLat > MARKER_ALPHA_LAT || $obj->liveLastLat < MARKER_DELTA_LAT)) {
            //    2-A) Yes, then save it to passages table
            flog( "is near edge of range.\r\n");
            $deleteIt = true;
          } else {
            //    2-A) No.
            flog( "is NOT near edge of range.\r\n");
            //        3-Q) Is record older than 15 minutes?
            if ($now - $obj->transponderTS > 900) {
              //      3-A) Yes
              flog( "          The record is 15 minutes old");
              //      4-Q) Is vessel parked?
              if(intval(rtrim($obj->liveSpeed, "kts"))<1) {
                  //    4-A) Yes, then keep in live.
                  flog( ", but vessel is parked, so keeping in live");
              } else {
                  //    4-A) No, speed is interupted value.
                  flog( " with no updates so delete it.");
                  $deleteIt = true;
              }
              //Check for stale reload time [Added 10/2/21]
              if(($now - $obj->reloadTS) > 900) {
                  flog( ", but vessel was reloaded with no new updates. Deleting it.");
                  $deleteIt = true;
              }
                } else {
                    //      3-A) No, then keep waiting.
                    flog( " keeping in live.\r\n");
                }
            }
        }
        /* Admin commands MOVED to own function */
        //Do deletes according to test conditions
        if($deleteIt) {
            $obj->savePassageIfComplete(true);          
            flog( "\n      * Deleting old livescan record for ".$obj->liveName ." ".getNow());
            if($this->LiveScanModel->deleteLiveScan($obj->liveVesselID)) {
                //Table delete was sucessful, remove object from array
                $key = 'mmsi'.$obj->liveVesselID;
                flog("\n          Db delete was sucessful. Now deleting object with key $key from liveScan array.");
            } else {
                error_log("\n        Error deleting LiveScan " . $obj->liveVesselID);
            }
            unset($this->liveScan[$key]);
            $this->updateLiveScanLength();
        } else {
          flog(" Not Deleted");
        }
        //1-A) No, record is fresh, so keep in live.
        flog("\n");
      }
      if(!count($this->liveScan)) {
        flog(" = NONE\n");
      }  
      $this->lastCleanUp = $now;  
    }
  }   
    

  public function adminCommands() {
    //Runs on same timing as removeOldScans(), but only that function resets the timer.
    $now = time(); 
    if(($now-$this->lastCleanUp) > $this->cleanUpTimeout) {
        flog("now: $now, lastCleanup: {$this->lastCleanUp}, cleanUpTimeout: {$this->cleanUpTimeout}\n");
        flog("    PlotDaemon::adminCommands()...\n");
      //Check DB for new vessel ID to scrape
      $this->checkDbForInputVessels();
      //Check DB for admin command to test Alert trigger
      $this->checkDbForAlertSimulation();
      //Check DB for user request to sendTestNotification
      $this->AlertsModel->checkForUserNotificationTestRequest(); 
      //Check DB for admin command to stop daemon & run updates
      $this->checkDbForDaemonReset();
      //Test whether to turn on enableEncoder flag in db
      $this->checkLivescanForWatchedVessels();
      //Check database for enableEncoder flags
      $this->checkDbForEncoderStart();
      $this->checkDbForEncoderManualStart();
    }
  }

  //Not used as of 3/27/22 in favor of cloud API
  public function saveLivescanJson() {
    //To be run 3 times per minute
    $now = time();
    if(($now - $this->lastJsonSave) > 20) {
      $this->AlertsModel->saveLivescanJson();
      $this->lastJsonSave = $now;
    }
  }

  public function updateLiveScanLength() {
    //Skip in AIS Test Mode
    if($this->aisTestMode) {
        flog("updateLiveScanLength() skipped for aisTestMode");
        return;
    }
    /* Updated 8/27/22 to test region and push quantity of vessels in each */
     $c = 0;  $q = 0;
     foreach($this->liveScan as $obj) {
      if($obj->liveRegion=='clinton') {
        $c++;
      } else if($obj->liveRegion=='qc') {
        $q++;
      }
      flog("updateLiveScanLength() has $c clinton and $q qc vessels");
      $this->LiveScanModel->updateLiveScanLength(["clinton" => $c, "qc"=>$q]);
     } 
    
  }

  public function saveAllScans() {
    $now = time();
    if(($now - $this->lastPassagesSave) > $this->savePassagesTimeout) {
      flog("    saveAllScans()");
      flog( "\n      Writing passages to db");
      $scans = count($this->liveScan);
      foreach($this->liveScan as $liveScanObj) {
        if($liveScanObj->liveLocation instanceof Location) {
          $len = count($liveScanObj->liveLocation->events);
        } else {
          $len = 0;
          flog( "\n          *\033[41m *  liveScanObj for {$liveScanObj->liveName} is missing its 'Location' data object.  * \033[0m"); 
        }
        //Unset vessel with bad data
        if(!isset($liveScanObj->liveRegion)) {
          $key = 'mmsi'.$liveScanObj->liveVesselID;
          unset($this->liveScan[$key]);
          flog("\n          *\033[41m *  liveScanObj for {$liveScanObj->liveName} has bad or missing data and was unset.  * \033[0m");
          continue;
        }
        flog( "\n          * ".$liveScanObj->liveName. " ".$len." events.");
        $liveScanObj->livePassageWasSaved = true;
        //Determine Clinton or QC passage save
        if($liveScanObj->liveRegion == "clinton") {
         $this->PassagesModel->savePassageClinton($liveScanObj);
        } else if($liveScanObj->liveRegion == "qc") {
           $this->PassagesModel->savePassageQC($liveScanObj);  
        }
        //$this->PassagesModel->savePassage($liveScanObj);
      }
      $this->lastPassagesSave = $now;
      flog( "\n      Finished saving ".$scans." live vessels to passages.\n");
      //Test for unset live scans
      if($scans > count($this->liveScan)) {
        $this->updateLiveScanLength();
      }
    }
  }

  //UNUSED
  public function captureVideo($liveObj) {
    flog("plotDaemon::captureVideo()\n");
    //Runs a local batch file which uses SSH to trigger an FFMPEG script on a remote server.
    $id   = $liveObj->liveVesselID;
    $name = str_replace([" ","."], "", $liveObj->liveName);
    $dir  = $liveObj->liveDirection;
    //Pass command run to outside script to prevent process delay
    $timer = popen("start /B php saveB.php $id $name $dir","r");
    sleep(2);
    pclose($timer);

    //exec("C:/app/saveB.cmd $id $name $dir", $outputArray);
    //flog(implode("\n", $outputArray)."\n");
  }

  public function enableEncoder() {
    if($this->encoderIsEnabled) {
        flog("\n          plotDaemon::enableEncoder() -> already enabled\n");
        return;
    }
    //Check for reload 
    $test = $this->AdminTriggersModel->testForEncoderIsEnabled();
    if($test['state']) {
      $this->encoderIsEnabled= true;
      $this->encoderEnabledTS = new DateTime();
      $this->encoderEnabledTS->setTimestamp($test['ts']);
      $this->encoderEnablerVesselID  = $test['vesselID'];
      $this->encoderEnablerVesselDir = $test['vesselDir'];
      $this->encoderEnabledScore = 5;
    }

    flog("\n          plotDaemon::enableEncoder() -> starting\n");   

    //Set Video options
    $video = "http://".$this->encoderUrl."/cgi-bin/set_codec.cgi?type=video&media_grp=1&media_chn=0&video_enc=96&profile=1&rc_mod=0&fps=30&gop=30&cbr_bit=2048&fluctuate=0&des_width=1920&des_height=1080";
    $screen1 = grab_protected($video, $this->encoderUsr, $this->encoderPwd);
    sleep(2);
    //Set Audio options
    $audio = "http://".$this->encoderUrl."/cgi-bin/set_codec.cgi?type=audio&media_grp=1&audio_interface=0&audio_enctype=100&audio_bitrate=128000";
    $screen2 = grab_protected($audio, $this->encoderUsr, $this->encoderPwd);
    sleep(2);
    //Update server with RTMP enabled
    $rtmp = "http://".$this->encoderUrl."/cgi-bin/set_codec.cgi?type=serv&media_grp=1&media_chn=0&http_sle=0&rstp_sle=0&mul_sle=0&hls_sle=0&rtmp_sle=1&rtmp_ip=".$this->streamUrl."&rtmp_port=1935&rtmp_path=".$this->streamPath."&rtmp_node=".$this->streamKey."&onvif_sle=0";
    $screen3 = grab_protected($rtmp, $this->encoderUsr, $this->encoderPwd);
    sleep(5);
    //Reboot server to activate
    $reboot = "http://".$this->encoderUrl."/cgi-bin/set_sys.cgi?type=reboot";
    $screen4 = grab_protected($reboot, $this->encoderUsr, $this->encoderPwd);
    if(str_contains($screen1, "succeed") && str_contains($screen2, "succeed") && str_contains($screen3, "succeed") && str_contains($screen4, "succeed")) {
      $this->encoderIsEnabled= true;
      $this->encoderEnabledTS = new DateTime();
      $this->AdminTriggersModel->setEncoderEnabledTrue();
      flog("          Encoder enable success!\n");
      //Wait and reboot again for good measure
      sleep(20);
      flog("          Pausing for 2nd encoder reboot.\n");
      grab_protected($reboot, $this->encoderUsr, $this->encoderPwd);
    } else {
        flog ("          Encoder enable failure: ".$screen1.", ". $screen2.", ". $screen3.", ".$screen4." \n");
    }
    //Send twitter notification of active live stream
    //$this->Twitter->postStreamAnnouncement($this->encoderEnablerVesselID, $this->encoderEnablerVesselDir);
  }


  public function enableEncoderManually() {
    if($this->encoderIsManualEnabled) {
        flog("\n          plotDaemon::enableEncoderManually() -> already enabled\n");
        return;
    }
    //Check for reload 
    $test = $this->AdminTriggersModel->testForEncoderIsManualEnabled();
    if($test['state']) {
      $this->encoderIsManualEnabled= true;
      $this->encoderEnabledTS = new DateTime();
      $this->encoderEnabledTS->setTimestamp($test['ts']);
      $this->encoderEnablerVesselID  = $test['vesselID'];
      $this->encoderEnablerVesselDir = $test['vesselDir'];
      $this->encoderEnabledScore = 5;
    }

    flog("\n          plotDaemon::enableEncoderManually() -> starting\n");   

    //Set Video options
    $video = "http://".$this->encoderUrl."/cgi-bin/set_codec.cgi?type=video&media_grp=1&media_chn=0&video_enc=96&profile=1&rc_mod=0&fps=30&gop=30&cbr_bit=2048&fluctuate=0&des_width=1920&des_height=1080";
    $screen1 = grab_protected($video, $this->encoderUsr, $this->encoderPwd);
    sleep(2);
    //Set Audio options
    $audio = "http://".$this->encoderUrl."/cgi-bin/set_codec.cgi?type=audio&media_grp=1&audio_interface=0&audio_enctype=100&audio_bitrate=128000";
    $screen2 = grab_protected($audio, $this->encoderUsr, $this->encoderPwd);
    sleep(2);
    
    //Update server with RTMP enabled
    $rtmp = "http://".$this->encoderUrl."/cgi-bin/set_codec.cgi?type=serv&media_grp=1&media_chn=0&http_sle=0&rstp_sle=0&mul_sle=0&hls_sle=0&rtmp_sle=1&rtmp_ip=".$this->streamUrl."&rtmp_port=1935&rtmp_path=".$this->streamPath."&rtmp_node=".$this->streamKey."&onvif_sle=0";
    $screen3 = grab_protected($rtmp, $this->encoderUsr, $this->encoderPwd);
    sleep(3);
    
    //Reboot server to activate
    $reboot = "http://".$this->encoderUrl."/cgi-bin/set_sys.cgi?type=reboot";
    $screen4 = grab_protected($reboot, $this->encoderUsr, $this->encoderPwd);
    if(str_contains($screen1, "succeed") && str_contains($screen2, "succeed") && str_contains($screen3, "succeed") && str_contains($screen4, "succeed")) {
      $this->encoderIsManualEnabled= true;
      $this->encoderEnabledTS = new DateTime();
      $this->AdminTriggersModel->setEncoderManualEnabledTrue();
      flog("          Manual Encoder enable success!\n");
    } else {
      flog ("          Manual Encoder enable failure: ".$screen1.", ". $screen2.", ". $screen3.", ".$screen4." \n");
    }
    //Send twitter notification of active live stream
    //$this->Twitter->postStreamAnnouncement($this->encoderEnablerVesselID, $this->encoderEnablerVesselDir);
  }




  public function disableEncoder() {
    if(!$this->encoderIsEnabled) {
      //flog("\n          plotDaemon::disableEncoder() -> disabled already\n");
      return false;
    }
    flog("\n          plotDaemon::disableEncoder() -> disabling now\n");
    //Disable server url
    $disable = "http://".$this->encoderUrl."/cgi-bin/set_codec.cgi?type=serv&media_grp=1&media_chn=0&rtmp_sle=0";
    $result1 = grab_protected($disable, $this->encoderUsr, $this->encoderPwd);
    //sleep(1);
    //Reboot server to activate
    $reboot = "http://".$this->encoderUrl."/cgi-bin/set_sys.cgi?type=reboot";
    $result2 = grab_protected($reboot, $this->encoderUsr, $this->encoderPwd);
    if(str_contains($result1, "succeed") && str_contains($result2, "succeed")) {
      $ts = new DateTime();
      $duration = $ts->diff($this->encoderEnabledTS);
      $formated = $duration->format('%h hours, %i minutes, %s seconds');
      $flength  = strlen($formated); //compensate spacing 
      $padding  = $flength==31 ? "": " ";
      flog( "\033[41m *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *\033[0m\r\n");
      flog( "\033[41m *  *  *                Live Stream Encoder DISABLED\033[0m\033[41m                  *  *  *\033[0m\r\n");
      flog( "\033[41m *  *  *  Final Stream Duration was $formated $padding  *  *  *\033[0m\r\n");
      flog( "\033[41m *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *\033[0m\r\n");
      $this->encoderIsEnabled = false;
      $this->encoderEnabledTS = null;
      $this->encoderEnabledScore = 0;
      $this->AdminTriggersModel->resetEncoderIsEnabled();
      return true;
    } else {
      flog("\033[41m plotDaemon::disableEncoder() function was run, but it did not receive a \"succeed\" response confirming that encoder was turned off.  The encoder's response for disable command was\n\t: $result1\n\t on reboot: $result2\033[0m\n\n");
      return false;
    }
  }

  public function disableEncoderManually() {
    if(!$this->encoderIsManualEnabled) {
      //flog("\n          plotDaemon::disableEncoder() -> disabled already\n");
      return false;
    }
    flog("\n          plotDaemon::disableEncoderManually() -> disabling now\n");
    //Disable server url
    $disable = "http://".$this->encoderUrl."/cgi-bin/set_codec.cgi?type=serv&media_grp=1&media_chn=0&rtmp_sle=0";
    $result1 = grab_protected($disable, $this->encoderUsr, $this->encoderPwd);
    //sleep(1);
    //Reboot server to activate
    $reboot = "http://".$this->encoderUrl."/cgi-bin/set_sys.cgi?type=reboot";
    $result2 = grab_protected($reboot, $this->encoderUsr, $this->encoderPwd);
    if(str_contains($result1, "succeed") && str_contains($result2, "succeed")) {
      $ts = new DateTime();
      $duration = $ts->diff($this->encoderEnabledTS);
      $formated = $duration->format('%h hours, %i minutes, %s seconds');
      $flength  = strlen($formated); //compensate spacing 
      $padding  = $flength==31 ? "": " ";
      flog( "\033[41m *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *\033[0m\r\n");
      flog( "\033[41m *  *  *                Live Stream Encoder DISABLED\033[0m\033[41m                  *  *  *\033[0m\r\n");
      flog( "\033[41m *  *  *  Final Stream Duration was $formated $padding  *  *  *\033[0m\r\n");
      flog( "\033[41m *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *\033[0m\r\n");
      $this->encoderIsManualEnabled= false;
      $this->encoderEnabledTS = null;
      $this->encoderEnabledScore = 0;
      $this->AdminTriggersModel->resetEncoderIsManualEnabled();
      return true;
    } else {
      flog("\033[41m plotDaemon::disableEncoderManually() function was run, but it did not receive a \"succeed\" response confirming that encoder was turned off.  The encoder's response for disable command was\n\t: $result1\n\t on reboot: $result2\033[0m\n\n");
      return false;
    }
  }

  protected function processBuffer($buf, $local_ip, $local_port) {
    $msg = $buf;
    $len = strlen($msg);
    $cpy = str_replace("\n", '', $buf);
    flog("  UDP packet received on $local_ip:$local_port =\n");
    //Filter UDP traffic by NMEA prefix
    if(!str_contains($buf, '!AIVDM')) {
      //Non-NMEA data is private message. It gets logged in blue & not forwarded.
      flog("    \033[44m".$cpy."\033[0m");
    } else {
      flog("    $cpy\n");
      //Send data to AIS the decoder

      $this->ais->process_ais_buf($buf, false);
      /* process_ais_buf calls process_ais_raw
         process_ais_raw calls process_ais_itu
         process_ais_itu calls decode_ais which has custom extention
         decode_ais calls back LiveScan objects in the array plotDaemon->liveScan
      */

      //Forward NMEA sentence to myshiptracking.com
      $mstSock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
      if($mstSock) {
        $sentMst = socket_sendto($mstSock, $msg, $len, 0, '178.162.215.175', 31995);
        socket_close($mstSock);
      } else {
        $sndMst = 0;
      }

      //Forward NMEA sentence to vesselfinder.com
      $vfSock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
      if($vfSock) {
        $sentVf = socket_sendto($vfSock, $msg, $len, 0, 'ais.vesselfinder.com', 5616);
        socket_close($vfSock);
      } else {
        $sendVf = 0;
      }

      //Forward NMEA sentence to marinetraffic.comm
      $mtSock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
      if($mtSock) {
        $sentMt = socket_sendto($mtSock, $msg, $len, 0, '5.9.207.224', 6051);
        socket_close($mtSock);
      } else {
        $sentMt = 0;
      }
      flog( "  Also sent $sentMst bytes to myshiptracking, vesselfinder & marinetraffic\n");
    }
  }


  protected function processBufferTestMode($buf, $local_ip, $local_port) {
   $msg = $buf;
    $len = strlen($msg);
    $cpy = str_replace("\n", '', $buf);
    flog("  UDP packet received on $local_ip:$local_port = $len bytes\n");
    //Filter UDP traffic by NMEA prefix
    if(!str_contains($buf, '!AIVDM')) {
        //Non-NMEA data is private message. It gets logged in blue & not forwarded.
        flog("    \033[44m $cpy \033[0m\n");
        //flog("    $cpy\n");
    } else {
        
        flog("     $cpy\n");  
        //Send data to AIS the decoder
        
        $this->ais->process_ais_buf($buf, true); //isTest condition = true
      /* process_ais_buf calls process_ais_raw
         process_ais_raw calls process_ais_itu
         process_ais_itu calls decode_ais which has custom extention
         decode_ais calls back LiveScan objects in the array plotDaemon->liveScan
      */
      
    }
  }


  protected function checkDbForInputVessels() {
    flog("      * checkDbForInputVessels()  ");
    $mmsi = $this->AdminTriggersModel->testForAddVessel();
    if($mmsi) {
      flog("\n        Admin request received to add vessel ".$mmsi);
      $vesselData = $this->VesselsModel->lookUpVessel($mmsi);
      flog(" ".$vesselData['vesselName']);
      //Test for error
      if(isset($vesselData['error'])) {
          $this->VesselsModel->reportVesselError($vesselData);
          flog("\n        There was an error: ".$vesselData['error']."\n");
      } else {
          $this->VesselsModel->insertVessel($vesselData);
          $this->AdminTriggersModel->resetAddVessel();
          flog("\n        Added vessel ".$vesselData['vesselName']."\n");
      }
    } else {
      flog("  = NONE\n");
    }
  }

  protected function checkDbForAlertSimulation() {
    flog("      * checkDbForAlertSimulation()");
    $alertData = $this->AdminTriggersModel->checkForAlertTest();
    if($alertData && $alertData['go']==true) {
      flog( "\n\033[41m *  *  *       Alert Simulation Triggered      *  *  *  *  * \033[0m\r\n"); 
      flog( "\033[41m *  *  *       Test Event: ".$alertData['event']."  *  *  *  *  *\n");
      flog( "\033[41m *  *  *       Test Key:    ".$alertData['key']."*  *  *  *\n\n");       
      $this->AlertsModel->triggerEvent($alertData['event'], $this->liveScan[$alertData['key']]);
      sleep(3);
      $this->AdminTriggersModel->resetAlertTest();
    } 
    //No else here because AdminTriggersModel::checkForAlertTest() does flog of result.
  }

  protected function checkDbForDaemonReset() {
    flog("      * checkDbForDaemonReset()    ");
    if($this->AdminTriggersModel->testExit()) {
      flog( " = \033[41mTRUE -> Restarting plotserver\033[0m\n");
      $this->run = false;
    } else {
      flog(" = NONE\n");
    }
  }

  public function checkDbForEncoderStart() {
    flog("      * checkDbForEncoderStart()  ");
    if($this->AdminTriggersModel->testForEncoderStart()) {
        flog(" = TRUE\n");
        $this->enableEncoder();
    } else {
        $wasJustDisabled = $this->disableEncoder();
    }
     //Show screen reminder if live encoder is enabled.
    if($this->encoderIsEnabled) {
      $ts = new DateTime();
      $duration = $ts->diff($this->encoderEnabledTS);
      $formated = $duration->format('%h hours, %i minutes');
      $flength  = strlen($formated); //compensate spacing 
      $padding  = $flength==19 ? "": " ";
      flog( "\033[41m *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *\033[0m\r\n");
      flog( "\033[41m *  *  *            YouTube Live Stream Encoder is \033[5mENABLED\033[0m\033[41m            *  *  *\033[0m\r\n");
      flog( "\033[41m *  *  *             Stream Duration = $formated $padding           *  *  *\033[0m\r\n");
      flog( "\033[41m *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *\033[0m\r\n");
    } else {
      if($wasJustDisabled) {
        return; //Skips msg below when disableEncoder gives its own msg
      }
      flog("  = DISABLED\n");
    }
  }

  public function checkDbForEncoderManualStart() {
    flog("      * checkDbForEncoderManualStart()  ");
    if($this->AdminTriggersModel->testForEncoderManualStart()) {
      $this->enableEncoderManually();
    } else {
      $wasJustDisabled = $this->disableEncoderManually();
    }
     //Show screen reminder if live encoder is enabled.
    if($this->encoderIsManualEnabled) {
      $ts = new DateTime();
      $duration = $ts->diff($this->encoderEnabledTS);
      $formated = $duration->format('%h hours, %i minutes');
      $flength  = strlen($formated); //compensate spacing 
      $padding  = $flength==19 ? "": " ";
      flog( "\033[41m *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *\033[0m\r\n");
      flog( "\033[41m *  *  *            YouTube Live Stream Encoder is \033[5mENABLED\033[0m\033[41m            *  *  *\033[0m\r\n");
      flog( "\033[41m *  *  *             Stream Duration = $formated $padding           *  *  *\033[0m\r\n");
      flog( "\033[41m *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *\033[0m\r\n");
    } else {
      if($wasJustDisabled) {
        return; //Skips msg below when disableEncoder gives its own msg
      }
      flog("  = DISABLED\n");
    }
  }

  protected function checkLivescanForWatchedVessels() {
    flog("      * checkLivescanForWatchedVessels()\n");
    if(!count($this->liveScan)) {
      flog(" = NONE\n");
      return;
    }
    foreach($this->liveScan as $key => $liveObj) {
      $liveObj->determineEncoderStartConditions();
    }
  }

  protected function reloadSavedScans() {
      flog( "CRTDaemon::reloadSavedScans() started ".getNow()."...\n");
      if(!($data = $this->LiveScanModel->getAllLiveScans())) {
        flog( "   ... No old scans. ".getNow()."\n");
        return;
      }
      $this->liveScan = array();
      foreach($data as $row) {      
        $key = 'mmsi'. $row['liveVesselID'];
        $this->liveScan[$key] = new LiveScan(null, null, null, null, null, null, null, $this, true, $row);
        
        //Ensure data included liveInitLat & liveInitLon or waypoint passages will fail
        if(!isset($this->liveScan[$key]->liveInitLat)) {
          $this->liveScan[$key]->liveInitLat = $this->liveScan[$key]->liveLastLat;
          flog("Setting liveInitLat as ".$this->liveScan[$key]->liveLastLat." on ".$this->liveScan[$key]->liveName." reload because it was empty");
        }
        if(!isset($this->liveScan[$key]->liveInitLon)) {
          flog("Setting liveInitLon as ".$this->liveScan[$key]->liveLastLon." on ".$this->liveScan[$key]->liveName." reload because it was empty");
          $this->liveScan[$key]->liveInitLon = $this->liveScan[$key]->liveLastLon;
        }
        
        
        $this->liveScan[$key]->lookUpVessel();
        //Initialize location object
        $this->liveScan[$key]->liveLocation = new Location($this->liveScan[$key]);
        //Restore db saved event data
        if(isset($row['liveEvent'])) {
          $this->liveScan[$key]->liveLocation->lastEvent = $row['liveEvent'];
        }
        if(isset($row['liveEvents'])) {
          $this->liveScan[$key]->liveLocation->events = $row['liveEvents'];
        }
        $this->liveScan[$key]->liveLocation->description = [];
        //Now update with new location supressing event trigger
        $this->liveScan[$key]->calculateLocation(true);           
      }
  }

  protected function reloadSavedAlertsAll() {
    flog("CRTDaemon::reloadSavedAlertsAll()\n");
    $allClinton =  $this->AlertsModel->getAlertsAll('clinton');
    if($allClinton !== false && is_array($allClinton)) {
      //Sort by Date decending
      usort($allClinton, fn($a, $b) => $b['apubTS'] - $a['apubTS']);
      //Enforce queue limit of 20
      $this->alertsAll = array_slice($allClinton, 0, 20); 
    } else {
      flog( "\033[41m *  PlotDaemon::reloadSavedAlertsAll('clinton') failed to get data.  * \033[0m\r\n");
    }
    $allQC = $this->AlertsModel->getAlertsAll('qc');
    if($allQC !== false && is_array($allQC)) {
      //Sort by Date decending
      usort($allQC, fn($a, $b) => $b['apubTS'] - $a['apubTS']);
      //Enforce queue limit of 20
      $this->alertsAllQC = array_slice($allQC, 0, 20); 
    } else {
      flog( "\033[41m *  PlotDaemon::reloadSavedAlertsAll('qc') failed to get data.  * \033[0m\r\n");
    }
           
  }

  protected function reloadSavedAlertsPassenger() {
    flog("CRTDaemon::reloadSavedAlertsPassenger()\n");
    $passClinton =  $this->AlertsModel->getAlertsPassenger('clinton');
    if($passClinton !== false && is_array($passClinton)) {
      //Sort by Date decending
      usort($passClinton, fn($a, $b) => $b['apubTS'] - $a['apubTS']);
      //Enfore queue limit of 20
      $this->alertsPassenger = array_slice($passClinton, 0, 20);
    } else {
      flog( "\033[41m *  PlotDaemon::reloadSavedAlertsPassenger('clinton') failed to get data.  * \033[0m\r\n");
    }
    $passQC =  $this->AlertsModel->getAlertsPassenger('qc');
    if($passQC !== false && is_array($passQC)) {
      //Sort by Date decending
      usort($passQC, fn($a, $b) => $b['apubTS'] - $a['apubTS']);
      //Enfore queue limit of 20
      $this->alertsPassengerQC = array_slice($passQC, 0, 20);
    } else {
      flog( "\033[41m *  PlotDaemon::reloadSavedAlertsPassenger('qc') failed to get data.  * \033[0m\r\n");
    }
  }

    
}


