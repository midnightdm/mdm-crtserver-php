<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}
/* * * * * * * * *
 * CRTdaemon Class
 * daemon/classes/crtdaemon.class.php
 *  
 *  */

class CRTdaemon  {
  protected $config;
  protected $run = false;
  protected $lastScanTS;
  protected $lastRemoveTS;
  protected $liveScan = array();
  protected $kmlUrl;
  protected $kmlUrlTest;
  protected $datasource;
  protected $testMode;
  public    $jsonUrl;
  protected $errEmail;
  protected $timeout;
  protected $xmlObj;
  protected $lastXmlObj;  
  protected $nonVesselFilter = array();
  public    $localVesselFilter = array();
  public    $LiveScanModel;
  public    $PassagesModel;
  public    $VesselsModel;
  public    $AlertsModel;
  public    $apubId;
  public    $lastApubId;
  
  //Some loop helpers
  protected $logger;
  protected $shipPlotter;

  public function __construct($configStr)  {   
    if(!is_string($configStr)) {
      throw new Exception('configStr must point to existing file.');
    }
    $this->config = $configStr;

  }

  public function setApubId($id) {
    //This method run by LiveScan::checkMarkerPassage()
    if(is_int($id)) {
      $this->apubId = intval($id);
    } else {
      echo "ERROR: CRTdaemon::setApubId() received an invalid id from an event trigger.\n";
    }
    
  }

  protected function setup() {
    $config = include($this->config);
    $this->kmlUrl = $config['kmlUrl']; 
    $this->kmlUrlTest  =  $config['kmlUrlTest']; //Used when Test Mode is true
    $this->testMode = boolval($config['testMode']);
    $this->datasource = $config['datasource']; //Either 'kml' or 'api'
    $this->jsonUrl = $config['jsonUrl'];
    $this->timeout = intval($config['timeout']);
    $this->lastRemoveTS = "new";
    $this->errEmail = $config['errEmail'];    
    $this->nonVesselFilter = $config['nonVesselFilter'];
    $this->localVesselFilter = $config['localVesselFilter'];
    $this->LiveScanModel = new LiveScanModel();
    $this->PassagesModel = new PassagesModel();
    $this->VesselsModel = new VesselsModel();
    $this->AlertsModel = new AlertsModel();  
    //Load ID of last published alert.  
    $this->lastApubId = $this->apubId = $this->AlertsModel->getLastPublishedAlertId();
    echo "crtconfig.php loaded.\n";
  }

  // * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
  // *  This function is the main loop of this application.  *
  // * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
  protected function run() {    
    echo "CRTdaemon::run()= ".$this->run."\r\n";
    $this->shipPlotter = new ShipPlotter();
    $this->logger = new TimeLogger();
    $testIteration = 1; //Test Code Only
    while($this->run==true) {
      $ts   = time(); //Starts timing loop work duration
      //Test for transponder datasource
      if($this->datasource=='api') {
        $this->loadLivePlots();  
      } elseif($this->datasource=="kml") {
        //echo "testIteration = ".$testIteration; //For testing only
        $this->loadKmlPlots($testIteration);
      } else {
        exit("ERROR: Configured datasource $this->datasource is not valid.\r\n");
      }
      
      //Test if liveScan triggered any events on this loop
      $this->checkAlertStatus();
      $this->lastApubId = $this->apubId;
      $this->removeOldScans(); 
      $this->logger->timecheck();
      
      
      //Force web server to generate json file
      $dummy = grab_page($this->jsonUrl);      
      unset($dummy);
      //Subtract loop processing time from sleep delay...
      $endTS    = time();
      $duration = $endTS - $ts;
      //...unless time is more than 60 sec then use 1 sec
      $sleepTime = $duration > 20 ? 1 : (20 - $duration);
      echo "Loop duration = ".$duration.' '.getNow()." \n";      
      sleep($sleepTime);
      if($this->testMode && $testIteration == 12) { 
        $this->testMode = false; //Turn test mode off after run limit reached  
      }
      if($this->testMode) {
        $testIteration++; //Test Only: limits to 12 loops
      }                                     
    }
  }

  protected function loadKmlPlots($testIteration) {    
    $xml = "";    
    //Use real or test kml files according to testMode bool  
    $kmlUrl = $this->testMode==true ? $this->kmlUrlTest.$testIteration.".kml" : $this->kmlUrl;
    $xml = @file_get_contents($kmlUrl);            
    if ($xml===false) {
      echo "Ship Plotter $kmlUrl -up = ".$this->shipPlotter->isReachable.' '.getNow();
      //Compares present value to stored state to prevent recursion
      if($this->shipPlotter->isReachable==true){
        $this->shipPlotter->serverIsUp(false);
      }                
      sleep(20);
      return;                
    } else {
      $this->xmlObj  = simplexml_load_string($xml);
      //Compares present value to stored state to prevent recursion
      if($this->shipPlotter->isReachable==false){
        $this->shipPlotter->serverIsUp(true);
      }          
      echo "Ship Plotter +up = ".$this->shipPlotter->isReachable.' '.getNow()."\n";
    }
    if($this->xmlObj === $this->lastXmlObj){
      echo "xmlObj same as lastXmlObj: {$ts} \n\n";
      sleep(10);
      return;
    }            
    //Loop through place marks
    $pms = $this->xmlObj->Document->Placemark;
    $ts  = time();          
    foreach($pms as $pm) {
      if(isset($pm->description)) {
        $descArr = explode("\n", $pm->description);
        //Get vessel's name
        $name = $descArr[0];
        $startPos = strpos($name, 'Name ') +5;          
        $name     = trim(substr($name, $startPos)); //Remove white spaces
        $name     = str_replace(',', '', $name);   //Remove commas (,)
        $name     = str_replace('.', '. ', $name); //Add space after (.)
        $name     = str_replace('  ', ' ', $name); //Remove double space
        //Get vessel's MMSI id
        $id       = $descArr[1];
        $startPos = strpos($id,'MMSI ') + 5;
        $id       = trim(substr($id, $startPos)); //Remove white spaces  
        //Clean special case id
        $id       = str_replace('[us]', '', $id);
        
        //Filter out stationary transponders              
        if(in_array($id,   $this->nonVesselFilter)) { continue 1; }
        $name     = ucwords(strtolower($name)); //Change capitalization
      
        //Get vessel's coordinates
        $position = $descArr[6];
        $startPos = strpos($position,'Pos ') + 4;
        $position = substr($position, $startPos);
        $posArr   = explode(" ", $position);
        $lat      = floatval($posArr[0]);
        //Filter extra chars @ after possible bogus lon decimal like -90.2471359.5E
        $lonArr   = explode(".", $posArr[1]);
        //echo "Lon: ".var_dump($lonArr);
        if(count($lonArr)>1) {
          $lon      = floatval($lonArr[0].".".$lonArr[1]);
        } else {
          $lon = $posArr[1];
        }
        

        $speed    = $descArr[7];
        $pos      = strpos($speed,'Speed ') + 6;
        $speed    = trim(substr($speed, $pos));
        
        $course   = $descArr[8];
        $pos      = strpos($course,'Course ') + 7;
        $course   = trim(substr($course, $pos));
        
        $dest  = $descArr[4];
        $pos      = strpos($dest,'Dest ') + 5;
        $dest  = trim(substr($dest, $pos));
        
        $length   = $descArr[10];
        $pos      = strpos($length,'Length ') + 7;
        $length   = trim(substr($length, $pos));

        $width    = $descArr[11];
        $pos      = strpos($width,'Width ') + 6;
        $width    = trim(substr($width, $pos));

        $draft    = $descArr[12];
        $pos      = strpos($draft,'Draft ') + 6;
        $draft    = trim(substr($draft, $pos));     

        //Parse new time string if available
        if(isset($descArr[13])) {
          $dataTime     = $descArr[13];
          $pos      = strpos($dataTime,'Time ') + 5;
          $dataTime     = trim(substr($dataTime, $pos));
        } else {
          $dataTime = "";
        }
  

        $callsign = $descArr[2];
        $pos      = strpos($callsign,'c/s ') + 4;
        $callsign = trim(substr($callsign, $pos)); 

        //Testing new feature
        if($dataTime != "") {
          $ts = intval($dataTime);
        }
        $key  = 'mmsi'.$id;
        
        if(isset($this->liveScan[$key])) {
          //If name has MMSI instead of text, substitute with stored vessels data
          if(strpos($name, $id)>-1 ) {
            $name = $this->liveScan[$key]->liveVessel->vesselName;
          }

          $this->liveScan[$key]->update($ts, $name, $id, $lat, $lon, $speed, $course, $dest);
          echo "liveScan->update(". $ts . " " . $name . " " . $id . " ". $lat . " " . $lon . " " . $speed . " " . $course . " " . $dest .")\n";
          //Add new record only if data time isn't older than timeout to prevent recursion after removeOldScans()
        } elseif($dataTime > (time()-$this->timeout)) {
          $this->liveScan[$key] = new LiveScan($ts, $name, $id, $lat, $lon, $speed, $course, $dest, $length, $width, $draft, $callsign, $this);
          echo "new LiveScan(". $ts . " " . $name . " " . $id . " ". $lat . " " . $lon . " " . $speed . " " . $course . " " . $dest  . " " . $width . " " . $draft . " " . $callsign,")\n";
        }
      }      
    }
    $this->lastXmlObj = $this->xmlObj;
  }


  protected function removeOldScans() {
    $now = time(); 
    if($this->lastRemoveTS=="new" || ($now-$this->lastRemoveTS) > 180) {
      //Only perform once every 3 min to reduce db queries
      echo "CRTDaemon::removeOldScans()... \n";     
      foreach($this->liveScan as $key => $obj) {  
        //Test age of update.  
        $deleteIt = false;       
        echo '   ... Vessel '.$obj->liveName.' last updated '.$now - $obj->liveLastTS.' seconds ago (Timeout is '.$this->timeout." seconds) ";
        if(($now - $this->timeout) > $obj->liveLastTS) { //1-Q) Is record is older than timeout value?
          //1-A) Yes, then 
          //     2-Q) Is it near the edge of receiving range?
          //         (Seperate check for upriver & downriver vessels removed 6/13/21)
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
          if($this->LiveScanModel->deleteLiveScan($obj->liveID)){
            //Table delete was sucessful, remove object from array
            unset($this->liveScan[$key]);
          } else {
            error_log('Error deleting LiveScan ' . $obj->liveID);
          }
        }
        //1-A) No, record is fresh, so keep in live.
        echo "\r\n";   
      }
      $this->lastRemoveTS = $now;
    }
  }

  protected function checkAlertStatus() {
    //Calculate number of alerts published since last loop
    if(getEnv('HOST_IS_HEROKU')) {
      $alertQty = ($this->apubId - $this->lastApubId)/10;
    } else {
      $alertQty = ($this->apubId - $this->lastApubId);
    }
    echo "alertQty ($alertQty) = apubId ($this->apubId) - lastApubID ($this->lastApubId)/10 \n";
    if($alertQty > 0) {
      //New Alert Events triggered! Send messages to subscribers.
      $this->AlertsModel->generateAlertMessages($alertQty);
    }
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
      echo "   ... Reloading {$row['liveName']}\n";
      $this->liveScan[$key] = new LiveScan(null, null, null, null, null, null, null, null, null, null, null, null, $this, true, $row);
      $this->liveScan[$key]->lookUpVessel();
    }
  }

  protected function loadLivePlots() {
    echo "CRTDaemon::loadLivePlots() started ".getNow()."...\n";
    if(!($data = $this->LiveScanModel->getAllLivePlots())) {
      echo "   ... No current plots. ".getNow()."\n";
      return;
    }
    foreach($data as $row) {      
      $key = 'mmsi'. $row['plotVesselID'];
      $ts  = $row['plotTS'];
      $name = $row['plotName'];
      $id   = $row['plotVesselID'];
      $lat  = $row['plotLat'];
      $lon  = $row['plotLon'];
      $speed = $row['plotSpeed'];
      $course = $row['plotCourse'];
      $dest = "---";
      $length = "0m";
      $width  = "0m";
      $draft  = "0.0m"; 
      $callsign = "unknown";
      
      if(isset($this->liveScan[$key])) {
        //If name has MMSI instead of text, substitute with stored vessels data
        if(strpos($name, $id)>-1) {
          $name = $this->liveScan[$key]->liveVessel->vesselName;
        }
        $this->liveScan[$key]->update($ts, $name, $id, $lat, $lon, $speed, $course, $dest);
        echo "liveScan->update(". $ts . " " . $name . " " . $id . " ". $lat . " " . $lon . " " . $speed . " " . $course . " " . $dest .")\n";
      } else {
        $this->liveScan[$key] = new LiveScan($ts, $name, $id, $lat, $lon, $speed, $course, $dest, $length, $width, $draft, $callsign, $this);
        echo "new LiveScan(". $ts . " " . $name . " " . $id . " ". $lat . " " . $lon . " " . $speed . " " . $course . " " . $dest  . " " . $width . " " . $draft . " " . $callsign,")\n";
      }
      $this->liveScan[$key]->lookUpVessel();
    }         
  }
  

  protected function shutdown() {
    $msg = "* * * CRTdaemon shutdown " . date('c')." * * *";
    error_log($msg);
    //mail($this->errEmail, $msg, $msg, '', '');    
  }

  //DEPRECIATED unusable on Heroku
  public function signalStop($signal) {
    error_log('caught shutdown signal [' . $signal .']');
    $this->run = false;
  }

  //DEPRECIATED unusable on Heroku
  public function signalReload($signal) {
    error_log('caught shutdown signal [' . $signal .']');
    $this->setup();
    $this->reloadSavedScans();
  }

  public function start() {
    echo "CRTdaemon::start()\n";
    $this->run = true;
    $this->setup();
    echo "CRTdaemon::setup()\n";
    $this->reloadSavedScans();
    $this->run();
    echo "CRTdaemon::shutdown()\n";  
    $this->shutdown();
  }
}