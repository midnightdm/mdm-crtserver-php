<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}
/* * * * * * * * *
 * LiveScan Class
 * daemon/classes/livescan.class.php
 * 
 */

class LiveScan {
  //public $liveID;
  public $liveInitTS;
  public $liveInitLat;
  public $liveInitLon;
  public $liveLastTS = null;
  public $liveLastLat = null;
  public $livePrevLat = null;
  public $liveLastLon = null;
  public $liveDirection = 'undetermined';
  public $liveName;
  public $liveVesselID;
  public $liveVessel = null;
  public $liveLocation = null;
  public $liveMarkerAlphaWasReached = FALSE;
  public $liveMarkerAlphaTS = null;
  public $liveMarkerBravoWasReached = FALSE;
  public $liveMarkerBravoTS = null;
  public $liveMarkerCharlieWasReached = FALSE;
  public $liveMarkerCharlieTS = null;
  public $liveMarkerDeltaWasReached = FALSE;
  public $liveMarkerDeltaTS = null;
  public $liveCallSign;
  public $isReloaded;
  public $triggerQueued;
  public $triggerActivated;
  public $liveEta;
  public $liveSpeed;
  public $liveCourse;
  public $liveLength;
  public $liveWidth;
  public $liveDraft;
  public $livePassageWasSaved = false;
  public $liveIsLocal;
  public $callBack;
  public $lookUpCount = 0;
  public $dirScore    = 0;

  public function __construct($ts, $name, $id, $lat, $lon, $speed, $course, $cb, $reload=false, $reloadData=[]) {
    $this->callBack = $cb;
    if ($reload) {
      foreach ($reloadData as $attribute => $value) {        
        //Skip loading DB string on reload, add object instead.
        if($attribute=="liveLocation") {
          $this->liveLocation = null;
          continue;
        }
        $this->$attribute = $value;
        if($attribute=='liveName') {
          echo "  Reloading ".$value." from DB.\n";
          //lookUpVessel() & calculateLocation() deferred to post construction
          // in calling method reloadSaveScans()
        }
      }
      //Add "reload" and trigger flags
      $this->reload = true;
      $this->triggerQueued = false;
      $this->triggerActivated = true;
      //Delete db record pending update of new live data
      $this->callBack->LiveScanModel->deleteLiveScan($id);      
    } else {
      $this->setTimestamp($ts, 'liveInitTS');
      $this->setTimestamp($ts, 'liveLastTS');
      $this->liveName = $name;

      $this->liveVesselID = $id;
      $this->liveIsLocal = in_array($id, $this->callBack->localVesselFilter);      
      $this->liveInitLat = $lat;
      $this->liveInitLon = $lon;
      $this->liveSpeed = $speed;
      $this->liveCourse = $course;     
      $this->lookUpVessel();
      //Use scraped vesselName if not provided by transponder
      if(strpos($this->liveName, strval($id))>-1) {
        $this->liveName = $this->liveVessel->vesselName;
      }
      $validated = $this->insertNewRecord();
      //Unset this construction if above failed
      if(!$validated) {
        unset($this->callBack->liveScan['mmsi'.$id]);
        return;
      } 
      //Test for previous detect, don't alert if within last 8 hours
      $lastDetected = $this->callBack->VesselsModel->getVesselLastDetectedTS($id)['vesselLastDetectedTS'];
      echo "lastDetected check = ".$lastDetected;
      if($lastDetected==false || ($ts-$lastDetected)>28800) {
        $this->triggerQueued = true;
        $this->triggerActivated = false;
      } 
    }   
  }

  public function checkDetectEventTrigger() {
    //Performs trigger when condtions met. (Created 2021-07-31)
    if($this->triggerQueued && 
      !$this->triggerActivated && 
      $this->liveDirection !=="undetermined" &&
      $this->liveLocation != null) 
    {
      $this->callBack->VesselsModel->updateVesselLastDetectedTS($this->liveVesselID, time());
      $event = strpos($this->liveVessel->vesselType, "assenger")>-1 ? "detectp" : "detecta";
      $this->callBack->AlertsModel->triggerEvent($event, $this);
      $this->triggerQueued = false;
      $this->triggerActivated = true;
    }
   
  }

  public function setTimestamp($ts, $attribute) {
    $test = ['liveLastTS', 'liveInitTS', 'liveMarkerAlphaTS', 'liveMarkerBravoTS', 'liveMarkerCharlieTS', 'liveMarkerDeltaTS'];
    if(!in_array($attribute, $test)) { 
      $errMsg = "Invalid attribute: " . $attribute . " in LiveScan::setTimeStamp().";
      throw new Exception($errMsg);
      return null;  
    }
    if(is_int($ts) && $ts > 1506800000) {
      $this->$attribute = $ts;
    } elseif ($unixTS = strtotime($ts)) {
        $this->$attribute = $unixTS;
    } else {
        $errMsg = "Invalid timestamp " . $ts . " in LiveScan:setTimeStamp().";
        throw new Exception($errMsg);
    }
  }

  public function insertNewRecord() {   
    //Error check to make sure starting pos is not 0
    if($this->liveInitLat < 1 || $this->liveInitLon < 1) {
      echo "Bogus starting position data rejected.";
      return false;
    }
    $data = [];
    $data['liveInitTS'] = $this->liveInitTS;
    $data['liveLastTS'] = $this->liveLastTS;
    $data['liveInitLat'] = $this->liveInitLat;
    $data['liveInitLon'] = $this->liveInitLon;
    $data['liveDirection'] = $this->liveDirection;
    $data['liveLocation'] = "";
    $data['liveVesselID'] = $this->liveVesselID;
    $data['liveName'] = $this->liveName;
    $data['liveLength'] = $this->liveLength;
    $data['liveWidth'] = $this->liveWidth;
    $data['liveDraft'] = $this->liveDraft;
    $data['liveCallSign'] = $this->liveCallSign;
    $data['liveSpeed'] = $this->liveSpeed;
    $data['liveCourse'] = $this->liveCourse;
    $data['liveIsLocal'] = $this->liveIsLocal;
    echo 'Inserting new livescan record for '.$this->liveName .' '.getNow()."\n"; 
    $this->callBack->LiveScanModel->insertLiveScan($data);
    return true;
  }

  public function update($ts, $name, $id, $lat, $lon, $speed, $course) {
    //Function run by run() in crtDaemon.class.php
    //Is this first update after init?
    if($this->liveLastLat == null) {
      //Yes. Then update TS.
      $this->setTimestamp($ts, 'liveLastTS');      
    } else {
      //Does the transponder report movement?
      if(intval(rtrim($this->liveSpeed, "kts"))>0) {
        //Yes. Has position changed?
        if($this->liveLastLat != $lat || $this->liveLastLon != $lon) {
          //Yes. Then update TS.
          $this->setTimestamp($ts, 'liveLastTS');
          //And test if detect event can be triggered
          $this->checkDetectEventTrigger();           
        } //No. Then do nothing keeping last TS.
      }
    }    
    $this->livePrevLat = $this->liveLastLat==null ? $this->liveInitLat : $this->liveLastLat;
    $this->liveLastLat = $lat;
    $this->liveLastLon = $lon;
    $this->liveSpeed   = $speed;
    $this->liveCourse  = $course;
    //$this->liveDest    = $dest;
    $this->liveName    = $name;
    $this->determineDirection();
    if($this->liveName=="" || (is_null($this->liveVessel) && $this->lookUpCount < 5)) {
      $this->lookUpVessel();
    }
    $this->calculateLocation();
    $this->checkMarkerPassage();
    //And remove reload flag if set.
    if($this->isReloaded) {
      $this->insertNewRecord(); //Adds reload as new db record 
      $this->isReloaded = false;
    }
    $this->savePassageIfComplete();
    $this->updateRecord();
  }

  public function updateRecord() {
    $data = [];
    $data['liveLastTS'] = $this->liveLastTS;
    $data['liveLastLat'] = $this->liveLastLat;
    $data['liveLastLon'] = $this->liveLastLon;
    $data['liveDirection'] = $this->liveDirection;
    $data['liveLocation'] = $this->liveLocation->description;
    $data['liveEvent']  = $this->liveLocation->event;
    $data['liveEvents'] = $this->liveLocation->events; //This is array
    $data['liveName'] = $this->liveName;
    $data['liveVesselID'] = $this->liveVesselID;
    $data['liveSpeed'] = $this->liveSpeed;
    //$data['liveDest'] = $this->liveDest;
    $data['liveCourse'] = $this->liveCourse;
    $data['liveMarkerAlphaWasReached'] = $this->liveMarkerAlphaWasReached;
    $data['liveMarkerAlphaTS'] = $this->liveMarkerAlphaTS;
    $data['liveMarkerBravoWasReached'] = $this->liveMarkerBravoWasReached;
    $data['liveMarkerBravoTS'] = $this->liveMarkerBravoTS;
    $data['liveMarkerCharlieWasReached'] = $this->liveMarkerCharlieWasReached;
    $data['liveMarkerCharlieTS'] = $this->liveMarkerCharlieTS;
    $data['liveMarkerDeltaWasReached'] = $this->liveMarkerDeltaWasReached;
    $data['liveMarkerDeltaTS'] = $this->liveMarkerDeltaTS;
    $data['livePassageWasSaved'] = $this->livePassageWasSaved;
    //echo "updateRecord() Calling LiveScanModel->updateLiveScan  \n";
    //var_dump($data);
    $this->callBack->LiveScanModel->updateLiveScan($data);
  }


  public function determineDirection() {
    //Downriver when lat is decreasing
    if($this->liveLastLat < $this->livePrevLat) {
      //Deincrement score negative downto to min -3
      if($this->dirScore>-3) {
        $this->dirScore--;
      }      
      //Upriver when lat is increasing
    } elseif ($this->liveLastLat > $this->livePrevLat) {
      //Increment score positive upto max of 3
      if($this->dirScore<3) {
        $this->dirScore++;
      }
    }
    //Set direction according to score
    if($this->dirScore > 0) {
      $this->liveDirection = 'upriver';
    } elseif($this->dirScore < 0) {
      $this->liveDirection = 'downriver';
    } else {
      $this->liveDirection = 'undetermined';
    }
  }

  public function checkMarkerPassage() {
    echo "LiveScan::checkMarkerPassage() ";
    //For upriver Direction (Lat increasing)
    if($this->liveLastLat < (MARKER_DELTA_LAT - 1) || $this->liveLastLat > (MARKER_ALPHA_LAT + 1)) { 
      return; //Skips further testing if lat jumped to a bogus value beyond local view.
    } 
    if($this->liveDirection == "upriver") {
      if(!$this->liveMarkerDeltaWasReached && ($this->liveInitLat != $this->liveLastLat) && (MARKER_DELTA_LAT > $this->liveInitLat) && 
      ($this->liveLastLat > MARKER_DELTA_LAT))   {
        $this->liveMarkerDeltaWasReached = true;
        $this->liveMarkerDeltaTS = $this->liveLastTS;
        echo "upriver delta was reached.\n\n";
        //     $this->callBack->setApubId($this->callBack->AlertsModel->triggerDeltaEvent($this));
        //Pass waypoint event to Location::waypointEvent()
        $type  = strpos($this->liveVessel->vesselType, "assenger") ? "p" : "a";
        $event = "deltau".$type;
        $this->liveLocation->waypointEvent($event);
        
        return; //Added 5/19/21 to each if statement trying to stop simultaneous event triggers.        
      }
      if(!$this->liveMarkerCharlieWasReached && ($this->liveInitLat != $this->liveLastLat) && (MARKER_CHARLIE_LAT > $this->liveInitLat) && ($this->liveLastLat > MARKER_CHARLIE_LAT)) {
        $this->liveMarkerCharlieWasReached = true;
        $this->liveMarkerCharlieTS = $this->liveLastTS; 
        echo "upriver charlie was reached.\n\n";       
        //  $this->callBack->setApubId($this->callBack->AlertsModel->triggerCharlieEvent($this));
        //Pass waypoint event to Location::waypointEvent()
        $type  = strpos($this->liveVessel->vesselType, "assenger") ? "p" : "a";
        $event = "charlieu".$type;
        $this->liveLocation->waypointEvent($event);

        return;
      }
      if(!$this->liveMarkerBravoWasReached && ($this->liveInitLat != $this->liveLastLat) && (MARKER_BRAVO_LAT > $this->liveInitLat) && ($this->liveLastLat > MARKER_BRAVO_LAT)) {
        $this->liveMarkerBravoWasReached = true;
        $this->liveMarkerBravoTS = $this->liveLastTS; 
        echo "upriver bravo was reached.\n\n";       
        //  $this->callBack->setApubId($this->callBack->AlertsModel->triggerBravoEvent($this));
        //Pass waypoint event to Location::waypointEvent()
        $type  = strpos($this->liveVessel->vesselType, "assenger") ? "p" : "a";
        $event = "bravou".$type;
        $this->liveLocation->waypointEvent($event);
        return;
      }
      if(!$this->liveMarkerAlphaWasReached && ($this->liveInitLat != $this->liveLastLat) && (MARKER_ALPHA_LAT > $this->liveInitLat) && ($this->liveLastLat > MARKER_ALPHA_LAT)) {
        $this->liveMarkerAlphaWasReached = true;
        $this->liveMarkerAlphaTS = $this->liveLastTS;    
        echo "upriver alpha was reached.\n";    
        // $this->callBack->setApubId($this->callBack->AlertsModel->triggerAlphaEvent($this));
        //Pass waypoint event to Location::waypointEvent()
        $type  = strpos($this->liveVessel->vesselType, "assenger") ? "p" : "a";
        $event = "deltau".$type;
        $this->liveLocation->waypointEvent($event);
        return;
      }
    //For downriver direction (Lat decreasing)
    } elseif ($this->liveDirection == "downriver") {
      if(!$this->liveMarkerAlphaWasReached && ($this->liveInitLat != $this->liveLastLat) && (MARKER_ALPHA_LAT < $this->liveInitLat) && ($this->liveLastLat < MARKER_ALPHA_LAT)) {
        $this->liveMarkerAlphaWasReached = true;
        $this->liveMarkerAlphaTS = $this->liveLastTS;    
        echo "downriver alpha was reached.\n\n";    
        // $this->callBack->setApubId($this->callBack->AlertsModel->triggerAlphaEvent($this));
        //Pass waypoint event to Location::waypointEvent()
        $type  = strpos($this->liveVessel->vesselType, "assenger") ? "p" : "a";
        $event = "alphad".$type;
        $this->liveLocation->waypointEvent($event);        
        return;
      }
      if(!$this->liveMarkerBravoWasReached && ($this->liveInitLat != $this->liveLastLat) && (MARKER_BRAVO_LAT < $this->liveInitLat) && ($this->liveLastLat < MARKER_BRAVO_LAT)) {
        $this->liveMarkerBravoWasReached = true;
        $this->liveMarkerBravoTS = $this->liveLastTS;  
        echo "downriver bravo was reached.\n\n";       
        // $this->callBack->setApubId($this->callBack->AlertsModel->triggerBravoEvent($this));
        //Pass waypoint event to Location::waypointEvent()
        $type  = strpos($this->liveVessel->vesselType, "assenger") ? "p" : "a";
        $event = "bravod".$type;
        $this->liveLocation->waypointEvent($event);        
        return;
      }
      if(!$this->liveMarkerCharlieWasReached && ($this->liveInitLat != $this->liveLastLat) && (MARKER_CHARLIE_LAT < $this->liveInitLat) && ($this->liveLastLat < MARKER_CHARLIE_LAT)) {
        $this->liveMarkerCharlieWasReached = true;
        $this->liveMarkerCharlieTS = $this->liveLastTS;
        echo "downriver charlie was reached.\n\n";         
        //  $this->callBack->setApubId($this->callBack->AlertsModel->triggerCharlieEvent($this));
        //Pass waypoint event to Location::waypointEvent()
        $type  = strpos($this->liveVessel->vesselType, "assenger") ? "p" : "a";
        $event = "charlied".$type;
        $this->liveLocation->waypointEvent($event);
        return;
      }
      if(!$this->liveMarkerDeltaWasReached && ($this->liveInitLat != $this->liveLastLat) && (MARKER_DELTA_LAT < $this->liveInitLat) && ($this->liveLastLat < MARKER_DELTA_LAT)) {
        $this->liveMarkerDeltaWasReached = true;
        $this->liveMarkerDeltaTS = $this->liveLastTS;  
        echo "downriver delta was reached.\n";       
        //  $this->callBack->setApubId($this->callBack->AlertsModel->triggerDeltaEvent($this));
        //Pass waypoint event to Location::waypointEvent()
        $type  = strpos($this->liveVessel->vesselType, "assenger") ? "p" : "a";
        $event = "deltad".$type;
        $this->liveLocation->waypointEvent($event);        
      }           
    }
    echo "no conditions met.\n\n";
  }
  
  public function lookUpVessel() {   
    echo 'LiveScan::lookUpVessel() '.getNow()."\n";
    //See if Vessel data is available locally
    if($data = $this->callBack->VesselsModel->getVessel($this->liveVesselID)) {
      //echo "Vessel found in database: " . var_dump($data);
      $this->liveVessel = new Vessel($data, $this->callBack);
      //Give saved vesselName to live if it has none or number only
      if($this->liveName == "" || strpos($this->liveName, '[')  || strpos($this->liveName, "@@")) {
        $this->liveName = $this->liveVessel->vesselName;
      }
      return;
    }
    //Otherwise scrape data from a website
    $url = 'https://www.myshiptracking.com/vessels/';
    $q = $this->liveVesselID;
    echo "Begin scraping for vesselID " . $this->liveVesselID."\n";
    $html = grab_page($url, $q);  
    //Edit segment from html string
    $startPos = strpos($html, '<div class="vessels_main_data cell">');
    $clip     = substr($html, $startPos);
    //echo "substr clip: ".$clip;
    $endPos   = (strpos($clip, '</tbody>')+8);
    $len      = strlen($clip);
    $edit     = substr($clip, 0, ($endPos-$len));   
    //Count lookup attempt
    $this->lookUpCount++;        
    //Use DOM Document class
    $dom = new DOMDocument();
    @ $dom->loadHTML($edit);
    //assign data gleened from mst table rows
    $data = [];
    $rows = $dom->getElementsByTagName('tr');
    //desired rows are 0, 5, 11 & 12
    try {
      $vesselName  =  ucwords( strtolower( $rows->item(0)->getElementsByTagName('strong')->item(0)->textContent) );
    } 
    catch (exception $e) {
      echo "lookUpVessel() failed on vesselName with error ".$e->getMessage()."\n";
      $vesselName = $this->liveVesselID;
    }
    
    $data['vesselType'] = $rows->item(5)->getElementsByTagName('td')->item(1)->textContent;
    //Filter Spare - Local Vessel
    if($data['vesselType']=="Spare - Local Vessel") {
      $data['vesselType'] = "Local";
    }
    
    $data['vesselOwner'] = $rows->item(11)->getElementsByTagName('td')->item(1)->textContent;
    $data['vesselBuilt'] = $rows->item(12)->getElementsByTagName('td')->item(1)->textContent;
    //Try for image
    try {
      if(saveImage($this->liveVesselID)) {
        //$endPoint = getEnv('AWS_ENDPOINT');
        $base = $this->callBack->image_base;
        $data['vesselHasImage'] = true;
        //$data['vesselImageUrl'] = $endPoint . 'vessels/mmsi' . $this->liveVesselID . '.jpg';      
        $data['vesselImageUrl'] = $base.'vessels/jpg/' . $this->liveVesselID; 
      } else {
        $data['vesselHasImage'] = false;
      }
    }
    catch (exception $e) {
      //
      $data['vesselHasImage'] = false;
    }
    
    $data['vesselID'] = $this->liveVesselID;
    $data['vesselName'] = $vesselName; 
    
    //Additionally scrape rows 4, 6 & 8 for considered use
    $callSign = $rows->item(4)->getElementsByTagName('td')->item(1)->textContent;
    $size     = $rows->item(6)->getElementsByTagName('td')->item(1)->textContent;
    $draft    = $rows->item(8)->getElementsByTagName('td')->item(1)->textContent;
    //Parse size into seperate length and width
    if($size=="---") {
      $length = "---";
      $width  = "---";
    } else if(strpos($size, "x") === false) {
      $length  = $size;
      $width   = $size;
    } else {
      $sizeArr = explode(" ", $size); 
      $width   = trim($sizeArr[2])."m";
      $length  = trim($sizeArr[0])."m";
    }

    //Use local data unless scraped is better
    $data['vesselCallSign'] = $this->liveCallSign=="unknown" ? $callsign : $this->liveCallSign;
    $data['vesselLength']   = $this->liveLength  =="0m"      ? $length   : $this->liveLength;
    $data['vesselWidth']    = $this->liveWidth   =="0m"      ? $width    : $this->liveWidth;
    $data['vesselDraft']    = $this->liveDraft   =="0.0m"    ? $draft    : $this->liveDraft;    
    $this->liveVessel = new Vessel($data, $this->callBack);
    //In case scraped data replaced the local above, also update the live object
    $this->liveName     = $data['vesselName'];
    $this->liveCallSign = $data['vesselCallSign'];
    $this->liveLength   = $data['vesselLength'];
    $this->liveWidth    = $data['vesselWidth'];
    $this->liveDraft    = $data['vesselDraft'];
  }  

  public function calculateLocation($suppressTrigger=false) {
    if($this->liveLocation === null) {
      $this->liveLocation = new Location($this);
    }
    $this->liveLocation->calculate($suppressTrigger);
  }

  public function savePassageIfComplete($overRide = false) {
    if($this->livePassageWasSaved || $this->liveIsLocal) {
      return true;
    }
    //Save if at least 4 markers passed
    $score = 0;
    if($this->liveMarkerAlphaWasReached){   $score++; }
    if($this->liveMarkerBravoWasReached){   $score++; } 
    if($this->liveMarkerCharlieWasReached){ $score++; }
    if($this->liveMarkerDeltaWasReached){   $score++; }
      
    if($score >3) {
      $this->callBack->PassagesModel->savePassage($this);
      $this->livePassageWasSaved = true;
      return true;
    }
    if($overRide) {
      return true;
    }
    return false;
  }

}
