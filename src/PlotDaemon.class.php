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
  public $lastCleanUp;
  //public $lastDeletesCleanUp;
  public $lastJsonSave;
  public $LiveScanModel;
  public $VesselsModel;
  public $alertsAll;
  public $alertsPassenger;
  public $alertsAllQC;
  public $alertsPassengerQC;
  public $AlertsModel;
  public $PassagesModel;
  public $liveScanTimeout;
  public $cleanUpTimeout;
  public $savePassagesTimeout;
  public $image_base;


  public function setup() {

      $config = CONFIG_ARR;

      $this->liveScan = array(); //LiveScan objects - the heart of this app - get stored here
      $this->alertsAll = array();
      $this->alertsPassenger = array();
      $this->rowsBefore = 0;
      $this->LiveScanModel = new LiveScanModel();
      $this->VesselsModel = new VesselsModel();
      $this->AlertsModel = new AlertsModel($this);
      $this->PassagesModel = new PassagesModel();
      $this->lastCleanUp = time()-50; //Used to increment cleanup routine
      //$this->lastDeletesCleanUp = time()-50;
      $this->lastJsonSave = time()-10; //Used to increment liveScan.json save
      $this->lastPassagesSave = time()-50;//Increments savePassages routine
      
      //Set values below in $config array in config.php
      $this->liveScanTimeout = intval($config['liveScanTimeout']); 
      $this->cleanUpTimeout = intval($config['cleanUpTimeout']); 
      $this->savePassagesTimeout = intval($config['savePassagesTimeout']);        
      $this->errEmail = $config['errEmail']; 
      $this->dbHost = $config['dbHost'];
      $this->dbUser = $config['dbUser'];
      $this->dbPwd  = $config['dbPwd'];
      $this->dbName = $config['dbName'];   
      $this->nonVesselFilter = $config['nonVesselFilter'];
      $this->localVesselFilter = $config['localVesselFilter'];
      $this->image_base = $config['image_base'];
      $this->socket_address = $config['socket_address'];
      $this->socket_port    = $config['socket_port'];      
  }

  public function start() {
      flog( " Starting mdm-crt2-server\n\n");  
      flog( "\t\t >>>     Type CTRL+C at any time to quit.    <<<\r\n\n\n");
      
      $this->setup();
      $this->run = true;
      $this->reloadSavedScans();
      sleep(3);
      $this->reloadSavedAlertsAll();
      sleep(3);       
      $this->reloadSavedAlertsPassenger(); 
      sleep(3);
      $this->updateLiveScanLength();
      $this->run();
  }

  protected function run() {
      $ais = new MyAIS($this);      

      //Reduce errors
      error_reporting(~E_WARNING);
      //Create a UDP sockets
      if(!($aisMonSock = socket_create(AF_INET, SOCK_DGRAM, 0))) {
          $errorcode = socket_last_error();
          $errormsg = socket_strerror($errorcode);
          die("Couldn't create inbound socket: [$errorcode] $errormsg \n");
      }
      flog( "Socket created \n");


      //A run once message for Brian at start up to enable companion app
      flog( "\033[41m *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  * \033[0m\r\n"); 
      flog( "\033[41m *                       A T T E N T I O N,                     * \033[0m\r\n");
      flog( "\033[41m *                           B R I A N                          * \033[0m\r\n");
      flog( "\033[41m *                                                              * \033[0m\r\n");
      flog( "\033[41m *  Ensure you have AISMon running.                             * \033[0m\r\n");
      flog( "\033[41m *      - Enable 'UDP Output'                                   * \033[0m\r\n");
      flog( "\033[41m *      - Add the following to IP:port                          * \033[0m\r\n");
      flog( "\033[41m *           192.168.1.172:10111                                    * \033[0m\r\n");
      flog( "\033[41m *                                                              * \033[0m\r\n");
      flog( "\033[41m *                                                              * \033[0m\r\n");
      flog( "\033[41m *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  * \033[0m\r\n\r\n");
      // Bind the source address
      if( !socket_bind($aisMonSock, $this->socket_address, $this->socket_port) ) {
          $errorcode = socket_last_error();
          $errormsg = socket_strerror($errorcode);
          die("Could not bind inbound socket : [$errorcode] $errormsg \n");
      }
      flog( "Socket bind OK \n");

      while($this->run==true) {
          //** This is Main Loop of this server for the UDP version ** 
          //Do some communication, this loop can handle multiple clients
          flog("Waiting for data on $this->socket_address:$this->socket_port ... \n");
          //Receive some data
          $r = socket_recvfrom($aisMonSock, $buf, 512, 0, $local_ip, $local_port);
          $msg = $buf;
          $len = strlen($msg);

          //Look for other UDP traffic
          if(!str_contains($buf, '!AIVDM')) {
            flog("\033[44m".$buf."\033[0m\r\n");
          }
          //Send data to AIS the decoder
          $ais->process_ais_buf($buf);

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
          
          flog( "$local_ip:$local_port -- $buf  Also sent $sentMst bytes to myshiptracking.com, $sentVf bytes to vesselfinder.com & $sentMt bytes to marinetraffic.com\n");
          /*
          //Since above process is a loop, you can't add any more below. 
          //Put further repeating instructions in THAT loop (MyAIS.class.php) 
          */
      }
      socket_close($aisMonSock);
  }


  public function removeOldScans() {
      $now = time(); 
      if(($now-$this->lastCleanUp) > $this->cleanUpTimeout) {
          //Only perform once every few min to reduce db queries
          flog( "PlotDaemon::removeOldScans()... \n");     
          foreach($this->liveScan as $key => $obj) {  
              //Test age of transponder update [changed from move update 3/3/22].  
              $deleteIt = false;       
              flog( "   ... Vessel ". $obj->liveName . " last transponder ". ($now - $obj->transponderTS) . " seconds ago (Timeout is " . $this->liveScanTimeout . " seconds) ");
              if(($now - $this->liveScanTimeout) > $obj->transponderTS) { //1-Q) Is record is older than timeout value?
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
                          flog( "The record is 15 minutes old");
                          //      4-Q) Is vessel parked?
                          if(intval(rtrim($obj->liveSpeed, "kts"))<1) {
                              //    4-A) Yes, then keep in live.
                              flog( ", but vessel is parked, so keeping in live");
                          } else {
                              //    4-A) No, speed is interupted value.
                              flog( " with no updates so delete it.\r\n");
                              $deleteIt = true;
                          }
                          //Check for stale reload time [Added 10/2/21]
                          if(($now - $obj->reloadTS) > 900) {
                              flog( ", but vessel was reloaded with no new updates. Deleting it.\r\n");
                              $deleteIt = true;
                          }
                      } else {
                          //      3-A) No, then keep waiting.
                          flog( " keeping in live.\r\n");
                      }
                  }
              }
              //Check DB for admin command to scrape new vessel
              $mmsi = $this->VesselsModel->testForAddVessel();
              if($mmsi) {
                  flog("Admin request received to add vessel ".$mmsi);
                  $vesselData = $this->VesselsModel->lookUpVessel($mmsi);
                  flog(" ".$vesselData['vesselName']);
                  //Test for error
                  if(isset($vesselData['error'])) {
                      $this->VesselsModel->reportVesselError($vesselData);
                      flog("There was an error: ".$vesselData['error']."\n");
                  } else {
                      $this->VesselsModel->insertVessel($vesselData);
                      $this->VesselsModel->resetAddVessel();
                      flog("Added vessel ".$vesselData['vesselName']."\n");
                  }
              }
              //Check DB for admin command to test Alert trigger
              $alertData = $this->VesselsModel->checkForAlertTest();
              $key = $alertData['alertTestKey'];
              
              if($alertData['alertTestDo']) {
                flog( "\033[41m *  *  *       Alert Simulation Triggered      *  *  *  *  * \033[0m\r\n"); 
                flog( "\033[41m *  *  *       Test Event: ".$alertData['alertTestEvent']."  *  *  *  *  *\n");
                flog( "\033[41m *  *  *       Test Key:    $key  *  *  *  *\n");
                
                $this->AlertsModel->triggerEvent($alertData['alertTestEvent'], $this->liveScan[$key]);
                sleep(3);
                $this->VesselsModel->resetAlertTest();
              }
              //Check DB for user request to sendTestNotification
              $this->AlertsModel->testForUserNotificationTestRequest(); 

              //Check DB for admin command to stop daemon & run updates
              if($this->LiveScanModel->testExit()==true) {
                  flog( "Stopping plotserver at request of database.\n\n");
                  $this->run = false;
              }
              //Do deletes according to test conditions
              if($deleteIt) {
                  $obj->savePassageIfComplete(true);          
                  flog( 'Deleting old livescan record for '.$obj->liveName .' '.getNow()."\n");
                  if($this->LiveScanModel->deleteLiveScan($obj->liveVesselID)) {
                      //Table delete was sucessful, remove object from array
                      $key = 'mmsi'.$obj->liveVesselID;
                      flog("Db delete was sucessful. Now deleting object with key $key from liveScan array.\n");
                      unset($this->liveScan[$key]);
                      $this->updateLiveScanLength();
                  } else {
                      error_log('Error deleting LiveScan ' . $obj->liveVesselID);
                  }
              }
              //1-A) No, record is fresh, so keep in live.
              flog( "\r\n");
          }    
      }   
      $this->lastCleanUp = $now;
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
    /* Write liveScan obj quantity to 'Passages/Admin' after any insert or delete
     *   Run by   MyAIS::decode_ais() on new LiveScan construction 
     *   and by   PlotDaemon::removeOldScans()
     */ 
    $currentLiveScanLength = count($this->liveScan);
    $this->LiveScanModel
      ->updateLiveScanLength($currentLiveScanLength);
    
  }

  public function saveAllScans() {
      $now = time();
      if(($now - $this->lastPassagesSave) > $this->savePassagesTimeout) {
          flog( "Writing passages to db...\n");
      $scans = count($this->liveScan);
      foreach($this->liveScan as $liveScanObj) {
        if($liveScanObj->liveLocation instanceof Location) {
          $len = count($liveScanObj->liveLocation->events);
        } else {
          $len = 0;
          flog( "\033[41m *  liveScanObj for {$liveScanObj->liveName} is missing its 'Location' data object.  * \033[0m\r\n"); 
        }
        //Unset vessel with bad data
        if(!isset($liveScanObj->liveRegion)) {
          $key = 'mmsi'.$obj->liveVesselID;
          unset($this->liveScan[$key]);
          flog("\033[41m *  liveScanObj for {$liveScanObj->liveName} has bad or missing data and was unset.  * \033[0m\r\n");
          continue;
        }
        flog( "   ...".$liveScanObj->liveName. " ".$len." events.\n");
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
      flog( "Finished saving ".$scans." live vessels to passages.\n");
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


