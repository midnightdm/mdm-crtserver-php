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
  public $LiveScanModel;
  public $VesselsModel;
  public $alertsAll;
  public $alertsPassenger;
  public $AlertsModel;
  public $PassagesModel;
  public $liveScanTimeout;
  public $cleanUpTimeout;
  public $savePassagesTimeout;
  public $image_base;


  protected function setup() {

      $config = CONFIG_ARR;

      $this->liveScan = array();
      $this->alertsAll = array();
      $this->alertsPassenger = array();
      $this->rowsBefore = 0;
      $this->LiveScanModel = new LiveScanModel();
      $this->VesselsModel = new VesselsModel();
      $this->AlertsModel = new AlertsModel($this);
      $this->PassagesModel = new PassagesModel();
      $this->lastCleanUp = time()-50; //Used to increment cleanup routine
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
      flog( "Socket created \n");
      //A run once message for Brian at start up to enable companion app
      flog( "\033[41m *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  * \033[0m\r\n"); 
      flog( "\033[41m *                       A T T E N T I O N,                     * \033[0m\r\n");
      flog( "\033[41m *                           B R I A N                          * \033[0m\r\n");
      flog( "\033[41m *                                                              * \033[0m\r\n");
      flog( "\033[41m *  Ensure you have AISMon running.                             * \033[0m\r\n");
      flog( "\033[41m *      - Enable 'UDP Output'                                   * \033[0m\r\n");
      flog( "\033[41m *      - Add the following to IP:port                          * \033[0m\r\n");
      flog( "\033[41m *           127.0.0.1:10111                                    * \033[0m\r\n");
      flog( "\033[41m *                                                              * \033[0m\r\n");
      flog( "\033[41m *                                                              * \033[0m\r\n");
      flog( "\033[41m *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  * \033[0m\r\n\r\n");
      // Bind the source address
      if( !socket_bind($sock, $this->socket_address, $this->socket_port) ) {
          $errorcode = socket_last_error();
          $errormsg = socket_strerror($errorcode);
          die("Could not bind socket : [$errorcode] $errormsg \n");
      }
      flog( "Socket bind OK \n");
      
      while($this->run==true) {
          //** This is Main Loop this server for the UDP version ** 
          //Do some communication, this loop can handle multiple clients
          flog("Waiting for data ... \n");
          //Receive some data
          $r = socket_recvfrom($sock, $buf, 512, 0, $remote_ip, $remote_port);
          flog( "$remote_ip : $remote_port -- " . $buf);
          //Send back the data to the decoder
          $ais->process_ais_buf($buf);

          //Since above process is a loop, you can't add any more below. 
          //Put further repeating instructions in THAT loop (MyAIS.class.php)
      }
      socket_close($sock);
  }


  public function removeOldScans() {
      $now = time(); 
      if(($now-$this->lastCleanUp) > $this->cleanUpTimeout) {
          //Only perform once every few min to reduce db queries
          flog( "PlotDaemon::removeOldScans()... \n");     
          foreach($this->liveScan as $key => $obj) {  
              //Test age of update.  
              $deleteIt = false;       
              flog( "   ... Vessel ". $obj->liveName . " last updated ". ($now - $obj->liveLastTS) . " seconds ago (Timeout is " . $this->liveScanTimeout . " seconds) ");
              if(($now - $this->liveScanTimeout) > $obj->liveLastTS) { //1-Q) Is record is older than timeout value?
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
                      if ($now - $obj->liveLastTS > 900) {
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
                      unset($this->liveScan[$key]);
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
        flog( "   ...".$liveScanObj->liveName. " ".$len." events.\n");
        $liveScanObj->livePassageWasSaved = true;
        $this->PassagesModel->savePassage($liveScanObj);
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
        $this->liveScan[$key]->lookUpVessel();
        //Initialize location object
        $this->liveScan[$key]->liveLocation = new Location($this->liveScan[$key]);
        //Restore db saved event data
        $this->liveScan[$key]->liveLocation->lastEvent = $row['liveEvent'];
        $this->liveScan[$key]->liveLocation->events = $row['liveEvents'];
        //Now update with new location supressing event trigger
        $this->liveScan[$key]->calculateLocation(true);           
      }
  }

  protected function reloadSavedAlertsAll() {
    flog("CRTDaemon::reloadSavedAlertsAll()\n");
    $all =  $this->AlertsModel->getAlertsAll();
    if($all !== false && is_array($all)) {
      //Sort by Date decending
      usort($all, fn($a, $b) => $b['apubTS'] - $a['apubTS']);
      //Enforce queue limit of 20
      $this->alertsAll = array_slice($all, 0, 20); 
    } else {
      throw new Exception("reloadSavedAlertsAll() failed to get data.");
    }
           
  }

  protected function reloadSavedAlertsPassenger() {
    flog("CRTDaemon::reloadSavedAlertsPassenger()\n");
    $pass =  $this->AlertsModel->getAlertsPassenger();
    if($pass !== false && is_array($pass)) {
      //Sort by Date decending
      usort($pass, fn($a, $b) => $b['apubTS'] - $a['apubTS']);
      //Enfore queue limit of 20
      $this->alertsPassenger = array_slice($pass, 0, 20);
    } else {
      flog( "\033[41m *  PlotDaemon::reloadSavedAlertsPassenger() failed to get data.  * \033[0m\r\n");
    }

  }
    
}


