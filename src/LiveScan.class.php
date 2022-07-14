<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}
/* * * * * * * * *
 * LiveScan Class
 * daemon/src/livescan.class.php
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
  public $livePrevLon = null;
  public $liveDirection = 'undetermined';
  public $liveName;
  public $liveVesselID;
  public $liveVessel = null;
  public $transponderTS = null;
  public $liveLocation = null;
  public $liveSegment = null;
  public $liveMarkerAlphaWasReached = FALSE;
  public $liveMarkerAlphaTS = null;
  public $liveMarkerBravoWasReached = FALSE;
  public $liveMarkerBravoTS = null;
  public $liveMarkerCharlieWasReached = FALSE;
  public $liveMarkerCharlieTS = null;
  public $liveMarkerDeltaWasReached = FALSE;
  public $liveMarkerDeltaTS = null;
  //Added 4 more waypoints for QC site
  public $liveMarkerEchoWasReached = FALSE;
  public $liveMarkerEchoTS = null;
  public $liveMarkerFoxtrotWasReached = FALSE;
  public $liveMarkerFoxtrotTS = null;
  public $liveMarkerGolfWasReached = FALSE;
  public $liveMarkerGolfTS = null;
  public $liveMarkerHotelWasReached = FALSE;
  public $liveMarkerHotelTS = null;

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
  public $reload;
  public $reloadTS;

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
          flog("   ...Reloading ".$value." from DB.\n");
          //lookUpVessel() & calculateLocation() deferred to post construction
          // in calling method reloadSaveScans()
        }
      }
      //Add "reload" and trigger flags
      $this->reload = true;
      $this->reloadTS = time();
      $this->triggerQueued = false;
      $this->triggerActivated = true;
      //Delete db record pending update of new live data
      $this->callBack->LiveScanModel->deleteLiveScan($this->liveVesselID);      
    } else {
      $this->setTimestamp($ts, 'liveInitTS');
      $this->setTimestamp($ts, 'liveLastTS');
      $this->setTimestamp($ts, 'transponderTS');
      $this->liveName = $name;

      $this->liveVesselID = $id;
      $this->liveIsLocal = in_array($id, $this->callBack->localVesselFilter);      
      //Validate latitude and logitude values
      if(!$this->validateLatitude($lat) || !$this->validateLongitude($lon)) {
        unset($this->callBack->liveScan['mmsi'.$id]);
        flog("\033[41m *  * Constructor stopped for vessel $id because of a bad initial position value: $lat, $lon.  *  * \033[0m\r\n");
        return;
      }
      $this->liveInitLat = $lat;
      $this->liveInitLon = $lon;
      $this->liveSpeed = $speed;
      $this->liveCourse = $course;
      $this->liveSegment = $this->determineSegment($lat);     
      $this->lookUpVessel();
      //Use scraped vesselName if not provided by transponder
      if(strpos($this->liveName, strval($id))>-1) {
        $this->liveName = $this->liveVessel->vesselName;
      }
      $validated = $this->insertNewRecord();
      //Unset this construction if above failed
      if(!$validated) {
        flog("\033[41m *  * Constructor stopped for vessel $id because insertNewRecord() failed.  *  * \033[0m\r\n");
        unset($this->callBack->liveScan['mmsi'.$id]);
        return;
      } 
      //Test for previous detect, don't alert if within last 8 hours
      $lastDetected = $this->callBack->VesselsModel->getVesselLastDetectedTS($id);
     flog("lastDetected check = ".$lastDetected."\n");
      if($lastDetected==false || ($ts-$lastDetected)>28800) {
        $this->triggerQueued = true;
        $this->triggerActivated = false;
      } 
    }   
  }

  public function checkDetectEventTrigger() {
    //Performs trigger when condtions met. (Created 2021-07-31)
    $event = strpos($this->liveVessel->vesselType, "assenger")>-1 ? "detectp" : "detecta";
    flog("LiveScan::checkDetectEventTrigger(".$event.")...\n");
    $ta = !$this->triggerActivated;
    $nn = $this->liveLocation != null;
    if($this->triggerQueued && 
      !$this->triggerActivated && 
      //$this->liveDirection !=="undetermined" &&
      $this->liveLocation != null) 
    {
      flog("Calling VesselsModel->updateVesselLastDetectedTS.\n");
      $this->callBack->VesselsModel->updateVesselLastDetectedTS($this->liveVesselID, time());
      

      if($this->liveLocation->verifyWaypointEvent($event)) {
        //trigger an alert
        $this->triggerQueued = false;
        $this->triggerActivated = true;
        $this->callBack->AlertsModel->triggerEvent($event, $this);
        flog("\033[42m \033[30m       ...ALERT BY ".$this->liveName."\033[0m\n");
      }

    } else {
      flog("LiveScan::checkDetectEventTrigger() didn't pass conditional tests...\n");
      // flog("LiveScan::checkDetectEventTrigger() didn't pass conditional tests...\n      triggerQueued? $this->triggerQueued\n      NOT triggerActivated? $ta,\n      liveLocation NOT null?  $n\n\n");
    }
  }

  public function setTimestamp($ts, $attribute) {
    $test = ['liveLastTS', 'liveInitTS', 'liveMarkerAlphaTS', 'liveMarkerBravoTS', 'liveMarkerCharlieTS', 'liveMarkerDeltaTS', 'transponderTS'];
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
    if($this->liveInitLat < 1 || $this->liveInitLon == 0) {
      flog("Bogus starting position data rejected.");
      return false;
    }
    $data = [];
    $data['liveInitTS'] = $this->liveInitTS;
    $data['liveLastTS'] = $this->liveLastTS;
    $data['transponderTS'] = $this->transponderTS;
    $data['liveInitLat'] = $this->liveInitLat;
    $data['liveInitLon'] = $this->liveInitLon;
    $data['liveDirection'] = $this->liveDirection;
    $data['liveLocation'] = "";
    $data['liveSegment'] = $this->liveSegment;
    $data['liveVesselID'] = $this->liveVesselID;
    $data['liveName'] = $this->liveName;
    $data['liveLength'] = $this->liveLength;
    $data['liveWidth'] = $this->liveWidth;
    $data['liveDraft'] = $this->liveDraft;
    $data['liveCallSign'] = $this->liveCallSign;
    $data['liveSpeed'] = $this->liveSpeed;
    $data['liveCourse'] = $this->liveCourse;
    $data['imageUrl']   = $this->liveVessel->vesselImageUrl;
    $data['type']       = $this->liveVessel->vesselType;
    $data['liveIsLocal'] = $this->liveIsLocal;
    flog('Inserting new livescan record for '.$this->liveName .' '.getNow()."\n"); 
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
          //And test if detect event can be triggered [DISABLED 6/2/22, not working ]
          //$this->checkDetectEventTrigger();           
        } //No. Then do nothing keeping last TS.
      }
    }
    //liveLastTS changes only on movement, this changes on each transponder data receipt     
    $this->setTimestamp($ts, 'transponderTS');
    $this->livePrevLat = $this->liveLastLat==null ? $this->liveInitLat : $this->liveLastLat;
    $this->livePrevLon = $this->liveLastLon==null ? $this->liveInitLon : $this->liveLastLon;
    //Store new latitude value if valid
    if($this->validateLatitude($lat)) {
      $this->liveLastLat = $lat;
      $latWasUpdated = true;
    //Otherwise use latitude from previous update that was good
    } else {
      $this->liveLastLat = $this->livePrevLat;
      $latWasUpdated = false;
    }
    
    //Store new longitude if valid (& lat wasn't rejected)
    if($this->validateLongitude($lon) && $latWasUpdated) {
      $this->liveLastLon = $lon;
    //Otherwise use longitude from previous update that was good.
    } else {
      $this->liveLastLon = $this->livePrevLon;
    }
    
    $this->liveSpeed   = $speed;
    $this->liveCourse  = $course;
    $this->liveSegment = $this->determineSegment($lat); 
    $this->liveName    = $name;
    $this->determineDirection();
    if($this->liveName=="" || str_contains($this->liveName, "@@") || (is_null($this->liveVessel) && $this->lookUpCount < 5)) {
      $this->lookUpVessel();
    }
    $this->calculateLocation();
    //$this->checkMarkerPassage(); Retired 7/10/22 after duties passed to calculateLocation()
    if($this->liveLocation != null) {
      $this->liveRegion = $this->liveLocation->determineRegion(); //Added 7/10/22
    }
    
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
    $data['transponderTS'] = $this->transponderTS;
    $data['liveLastLat'] = $this->liveLastLat;
    $data['liveLastLon'] = $this->liveLastLon;
    $data['liveDirection'] = $this->liveDirection;
    if($this->liveLocation instanceof Location) {
      $data['liveLocation'] = ucfirst($this->liveLocation->description[0]);
      $data['liveEvent']  = $this->liveLocation->event;
      $data['liveEvents'] = $this->liveLocation->events; //This is array
    } else{
      $data['liveLocation'] = "Location Not Calculated";
      $data['liveEvent'] = "";
      $data['liveEvents'] = [];
    }
    $data['liveName'] = $this->liveName;
    $data['liveVesselID'] = $this->liveVesselID;
    $data['liveSpeed'] = $this->liveSpeed;
    $data['liveCourse'] = $this->liveCourse;
    $data['liveSegment'] = $this->liveSegment;
    $data['liveRegion'] = $this->liveRegion;
    $data['imageUrl']   = $this->liveVessel->vesselImageUrl;
    $data['type']       = $this->liveVessel->vesselType;
    //Clinton Waypoints
    $data['liveMarkerAlphaWasReached'] = $this->liveMarkerAlphaWasReached;
    $data['liveMarkerAlphaTS'] = $this->liveMarkerAlphaTS;
    $data['liveMarkerBravoWasReached'] = $this->liveMarkerBravoWasReached;
    $data['liveMarkerBravoTS'] = $this->liveMarkerBravoTS;
    $data['liveMarkerCharlieWasReached'] = $this->liveMarkerCharlieWasReached;
    $data['liveMarkerCharlieTS'] = $this->liveMarkerCharlieTS;
    $data['liveMarkerDeltaWasReached'] = $this->liveMarkerDeltaWasReached;
    $data['liveMarkerDeltaTS'] = $this->liveMarkerDeltaTS;
    //QC Waypoints
    $data['liveMarkerEchoWasReached'] = $this->liveMarkerEchoWasReached;
    $data['liveMarkerEchoTS'] = $this->liveMarkerEchoTS;
    $data['liveMarkerGolfWasReached'] = $this->liveMarkerGolfWasReached;
    $data['liveMarkerGolfTS'] = $this->liveMarkerGolfTS;
    $data['liveMarkerFoxtrotWasReached'] = $this->liveMarkerFoxtrotWasReached;
    $data['liveMarkerFoxtrotTS'] = $this->liveMarkerFoxtrotTS;
    $data['liveMarkerHotelWasReached'] = $this->liveMarkerHotelWasReached;
    $data['liveMarkerHotelTS'] = $this->liveMarkerHotelTS;
    $data['livePassageWasSaved'] = $this->livePassageWasSaved;

    $this->callBack->LiveScanModel->updateLiveScan($data);
  }

  public function determineSegment($lat) {
    //Calculates river segment 0-4 below, between or above waypoints
    if($lat < MARKER_DELTA_LAT) {
      return 0;
    } elseif($lat > MARKER_DELTA_LAT  && $lat < MARKER_CHARLIE_LAT) {
      return 1;
    } elseif($lat > MARKER_CHARLIE_LAT && $lat < MARKER_BRAVO_LAT) {
      return 2;
    } elseif($lat > MARKER_BRAVO_LAT && $lat < MARKER_ALPHA_LAT) {
      return 3;
    } elseif($lat > MARKER_ALPHA_LAT) {
      return 4;
    } else {
      return null;
    }
  }

  public function determineDirection() {
    //When monitored river section runs north/south (N Hemisphere)
    if(RIVER_ORIENTATION_SETTING == NORTH_SOUTH) {
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
    //When monitored river section runs east/west (W Hemisphere)
    } elseif(RIVER_ORIENTATION_SETTING==EAST_WEST) {
      //Downriver is when lon is decreasing
      if($this->liveLastLon < $this->livePrevLon) {
        //Deincrement score negative downto to min -3
        if($this->dirScore>-3) {
          $this->dirScore--;
        }    
      //Upriver is when lon is increasing
      } elseif ($this->liveLastLon > $this->livePrevLon) {
        //Increment score positive upto max of 3
        if($this->dirScore<3) {
          $this->dirScore++;
        }
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


  //DEPRICATED 
  public function checkMarkerPassage() {
    flog("LiveScan::checkMarkerPassage()...\n");
    //For upriver Direction (Lat increasing)
    if($this->liveLastLat < (MARKER_DELTA_LAT - 1) || $this->liveLastLat > (MARKER_ALPHA_LAT + 1)) { 
      return; //Skips further testing if lat jumped to a bogus value beyond local view.
    } 
    if($this->liveDirection == "upriver") {
      if(!$this->liveMarkerDeltaWasReached && ($this->liveInitLat != $this->liveLastLat) && (MARKER_DELTA_LAT > $this->liveInitLat) && 
      ($this->liveLastLat > MARKER_DELTA_LAT))   {       
        $type  = strpos($this->liveVessel->vesselType, "assenger") ? "p" : "a";
        $event = "deltau".$type;
        //Pass to Location::verifyWaypointEvent() for verification
        if($this->liveLocation->verifyWaypointEvent($event)) {
          $this->liveMarkerDeltaWasReached = true;
          $this->liveMarkerDeltaTS = $this->liveLastTS;
          $this->callBack->AlertsModel->triggerEvent($event, $this);
          flog("\33[42m      ...Delta waypoint was reached by ".$this->liveName." traveling Upriver.\033[0m\n\n");
        }
        return;         
      }
      if(!$this->liveMarkerCharlieWasReached && ($this->liveInitLat != $this->liveLastLat) && (MARKER_CHARLIE_LAT > $this->liveInitLat) && ($this->liveLastLat > MARKER_CHARLIE_LAT)) {
        $type  = strpos($this->liveVessel->vesselType, "assenger") ? "p" : "a";
        $event = "charlieu".$type;
        //Pass to Location::verifyWaypointEvent() for verification
        if($this->liveLocation->verifyWaypointEvent($event)) {
          //trigger an alert
          $this->liveMarkerCharlieWasReached = true;
          $this->liveMarkerCharlieTS = $this->liveLastTS;
          $this->callBack->AlertsModel->triggerEvent($event, $this); 
          flog("\33[42m      ...Charlie waypoint was reached by ".$this->liveName." traveling Upriver.\033[0m\n\n");
        }
        return;
      }
      if(!$this->liveMarkerBravoWasReached && ($this->liveInitLat != $this->liveLastLat) && (MARKER_BRAVO_LAT > $this->liveInitLat) && ($this->liveLastLat > MARKER_BRAVO_LAT)) {
        $type  = strpos($this->liveVessel->vesselType, "assenger") ? "p" : "a";
        $event = "bravou".$type;
        //Pass to Location::verifyWaypointEvent() for verification
        if($this->liveLocation->verifyWaypointEvent($event)) {
          //trigger an alert
          $this->liveMarkerBravoWasReached = true;
          $this->liveMarkerBravoTS = $this->liveLastTS;
          $this->callBack->AlertsModel->triggerEvent($event, $this); 
          flog("\33[42m      ...Bravo waypoint was reached by ".$this->liveName." traveling Upriver.\033[0m\n\n");
        }
        return;
      }
      if(!$this->liveMarkerAlphaWasReached && ($this->liveInitLat != $this->liveLastLat) && (MARKER_ALPHA_LAT > $this->liveInitLat) && ($this->liveLastLat > MARKER_ALPHA_LAT)) {
        $type  = strpos($this->liveVessel->vesselType, "assenger") ? "p" : "a";
        $event = "alphau".$type;
        if($this->liveLocation->verifyWaypointEvent($event)) {
          //trigger an alert
          $this->liveMarkerAlphaWasReached = true;
          $this->liveMarkerAlphaTS = $this->liveLastTS;
          $this->callBack->AlertsModel->triggerEvent($event, $this); 
          flog("\33[42m      ...Alpha waypoint was reached by ".$this->liveName." traveling Upriver.\033[0m\n\n");
        }
        return;
      }
    //For downriver direction (Lat decreasing)
    } elseif ($this->liveDirection == "downriver") {
      if(!$this->liveMarkerAlphaWasReached && ($this->liveInitLat != $this->liveLastLat) && (MARKER_ALPHA_LAT < $this->liveInitLat) && ($this->liveLastLat < MARKER_ALPHA_LAT)) {
        $type  = strpos($this->liveVessel->vesselType, "assenger") ? "p" : "a";
        $event = "alphad".$type;
        if($this->liveLocation->verifyWaypointEvent($event)) {
          //trigger an alert
          $this->liveMarkerAlphaWasReached = true;
          $this->liveMarkerAlphaTS = $this->liveLastTS;
          $this->callBack->AlertsModel->triggerEvent($event, $this); 
          flog( "\33[42m      ...Alpha waypoint was reached by ".$this->liveName." traveling Downriver.\033[0m\n\n");
        }    
        return;
      }
      if(!$this->liveMarkerBravoWasReached && ($this->liveInitLat != $this->liveLastLat) && (MARKER_BRAVO_LAT < $this->liveInitLat) && ($this->liveLastLat < MARKER_BRAVO_LAT)) {
        $type  = strpos($this->liveVessel->vesselType, "assenger") ? "p" : "a";
        $event = "bravod".$type;
        //Pass to Location::verifyWaypointEvent() for verification
        if($this->liveLocation->verifyWaypointEvent($event)) {
          //trigger an alert
          $this->liveMarkerBravoWasReached = true;
          $this->liveMarkerBravoTS = $this->liveLastTS;
          $this->callBack->AlertsModel->triggerEvent($event, $this); 
          flog( "\33[42m      ...Bravo waypoint was reached by ".$this->liveName." traveling Downriver.\033[0m\n\n");
        }       
        return;
      }
      if(!$this->liveMarkerCharlieWasReached && ($this->liveInitLat != $this->liveLastLat) && (MARKER_CHARLIE_LAT < $this->liveInitLat) && ($this->liveLastLat < MARKER_CHARLIE_LAT)) {
        $type  = strpos($this->liveVessel->vesselType, "assenger") ? "p" : "a";
        $event = "charlied".$type;
        //Pass to Location::verifyWaypointEvent() for verification
        if($this->liveLocation->verifyWaypointEvent($event)) {
          //trigger an alert
          $this->liveMarkerCharlieWasReached = true;
          $this->liveMarkerCharlieTS = $this->liveLastTS;
          $this->callBack->AlertsModel->triggerEvent($event, $this); 
          flog( "\33[42m      ...Charlie waypoint was reached by ".$this->liveName." traveling Downriver.\033[0m\n\n");
        }
        return;
      }
      if(!$this->liveMarkerDeltaWasReached && ($this->liveInitLat != $this->liveLastLat) && (MARKER_DELTA_LAT < $this->liveInitLat) && ($this->liveLastLat < MARKER_DELTA_LAT)) {
        $type  = strpos($this->liveVessel->vesselType, "assenger") ? "p" : "a";
        $event = "deltad".$type;
        //Pass to Location::verifyWaypointEvent() for verification
        if($this->liveLocation->verifyWaypointEvent($event)) {
          $this->liveMarkerDeltaWasReached = true;
          $this->liveMarkerDeltaTS = $this->liveLastTS;
          $this->callBack->AlertsModel->triggerEvent($event, $this);
          flog( "\33[42m      ...Delta waypoint was reached by ".$this->liveName." traveling Downriver.\033[0m\n\n");
        }       
      }           
    }
    flog( "   ...No conditions met.\n\n");
  }
  
  //END DEPRICATED

  public function lookUpVessel() {   
    flog( 'LiveScan::lookUpVessel() '.getNow()."\n");
    //See if Vessel data is available locally
    if($data = $this->callBack->VesselsModel->getVessel($this->liveVesselID)) {
      //flog( "Vessel found in database: " . var_dump($data));
      $this->liveVessel = new Vessel($data, $this->callBack);
      //Give saved vesselName to live if it has none or number only
      if($this->liveName == "" || strpos($this->liveName, '[')  || str_contains($this->liveName, "@@")) {
        $this->liveName = $this->liveVessel->vesselName;
      }
      return;
    }
    //Disabling further scraping until new data source found
    
    //Otherwise scrape data from a website
    $url = 'https://www.myshiptracking.com/vessels/';
    $q = $this->liveVesselID;
    flog( "Begin scraping for vesselID " . $this->liveVesselID."\n");
    //$html = grab_page($url, $q);  
    //Edit segment from html string
    //  $startPos = strpos($html, '<div class="vessels_main_data cell">');
    //$startPos = strpos($html, '<div class="card-body p-2 p-sm-3">'); //Update 7/13/22
    //$clip     = substr($html, $startPos);
    //flog( "substr clip: ".$clip);
    //  $endPos   = (strpos($clip, '</tbody>')+8);
    //$endPos   = (strpos($clip, '<div class="d-flex justify-content-center d-none d-md-block d-lg-none">'));
    //$len      = strlen($clip);
    //$edit     = substr($clip, 0, ($endPos-$len));   
    //Count lookup attempt
    $this->lookUpCount++;        
    //Use DOM Document class
    //$dom = new DOMDocument();
    //@ $dom->loadHTML($edit);
    //assign data gleened from mst table rows
    $data = [];
    //$rows = $dom->getElementsByTagName('tr');
    //desired rows are 0, 5, 11 & 12
    try {
    //  $vesselName  =  ucwords( strtolower( $rows->item(0)->getElementsByTagName('h1')->item(0)->textContent) );
    } 
    catch (exception $e) {
      flog( "lookUpVessel() failed on vesselName with error ".$e->getMessage()."\n");
      $vesselName = $this->liveVesselID;
    }
    
    //$data['vesselType'] = $rows->item(5)->getElementsByTagName('td')->item(1)->textContent;
    //Filter Spare - Local Vessel
    //if($data['vesselType']=="Spare - Local Vessel") {
    //  $data['vesselType'] = "Local";
    //}
    
    //$data['vesselOwner'] = $rows->item(11)->getElementsByTagName('td')->item(1)->textContent;
    //$data['vesselBuilt'] = $rows->item(12)->getElementsByTagName('td')->item(1)->textContent;
    //Try for image
    // try {
    //   //Upload to cloud bucket
    //   $cs = new CloudStorage(); 
    //   if($cs->scrapeImage($this->liveVesselID)) {
    //     $base = $cs->image_base;
    //     $data['vesselHasImage'] = true;
    //     $data['vesselImageUrl'] = $base.'images/vessels/mmsi' . $this->liveVesselID.'.jpg'; 
    //   } else {
    //     $data['vesselHasImage'] = false;
    //     $data['vesselImageUrl'] = $cs->no_image;
    //     //'https://storage.googleapis.com/www.clintonrivertraffic.com/images/vessels/no-image-placard.jpg';
    //   }
    // }
    // catch (exception $e) {
    //   //
    //   $data['vesselHasImage'] = false;
    //   $data['vesselImageUrl'] = $cs->no_image;
    // }
    
    // $data['vesselID'] = $this->liveVesselID;
    // $data['vesselName'] = $vesselName; 
    
    //Additionally scrape rows 4, 6 & 8 for considered use
    //$callSign = $rows->item(4)->getElementsByTagName('td')->item(1)->textContent;
    //$size     = $rows->item(6)->getElementsByTagName('td')->item(1)->textContent;
    //$draft    = $rows->item(8)->getElementsByTagName('td')->item(1)->textContent;
    //Parse size into seperate length and width
    // if($size=="---") {
    //   $length = "---";
    //   $width  = "---";
    // } else if(strpos($size, "x") === false) {
    //   $length  = $size;
    //   $width   = $size;
    // } else {
    //   $sizeArr = explode(" ", $size); 
    //   $width   = trim($sizeArr[2])."m";
    //   $length  = trim($sizeArr[0])."m";
    // }

    //Use local data unless scraped is better
    // $data['vesselCallSign'] = $this->liveCallSign=="unknown" ? $callsign : $this->liveCallSign;
    // $data['vesselLength']   = $this->liveLength  =="0m"      ? $length   : $this->liveLength;
    // $data['vesselWidth']    = $this->liveWidth   =="0m"      ? $width    : $this->liveWidth;
    // $data['vesselDraft']    = $this->liveDraft   =="0.0m"    ? $draft    : $this->liveDraft;    
    
    $cs = new CloudStorage();
    $data['vesselHasImage'] = false;
    $data['vesselImageUrl'] = $cs->no_image;
    $this->liveVessel = new Vessel($data, $this->callBack);
    // //In case scraped data replaced the local above, also update the live object
    // $this->liveName     = $data['vesselName'];
    // $this->liveCallSign = $data['vesselCallSign'];
    // $this->liveLength   = $data['vesselLength'];
    // $this->liveWidth    = $data['vesselWidth'];
    // $this->liveDraft    = $data['vesselDraft'];
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
    //Clinton Region
    if($this->liveRegion=="clinton") {
      //Save if at least 4 markers passed
      $score = 0;
      if($this->liveMarkerAlphaWasReached){   $score++; }
      if($this->liveMarkerBravoWasReached){   $score++; } 
      if($this->liveMarkerCharlieWasReached){ $score++; }
      if($this->liveMarkerDeltaWasReached){   $score++; }
        
      if($score >3) {
        $this->callBack->PassagesModel->savePassageClinton($this);
        $this->livePassageWasSaved = true;
        return true;
      }
    //QC Region
    } else if($this->liveRegion=="qc") {
      //Save if at least 4 markers passed
      $score = 0;
      if($this->liveMarkerEchoWasReached){   $score++; }
      if($this->liveMarkerFoxtrotReached){   $score++; } 
      if($this->liveMarkerGolfWasReached){   $score++; }
      if($this->liveMarkerHotelWasReached){  $score++; }
        
      if($score >3) {
        $this->callBack->PassagesModel->savePassageQC($this);
        $this->livePassageWasSaved = true;
        return true;
      }
    }
    if($overRide) {
      return true;
    }
    return false;
  }

  public function validateLatitude($lat) {
    //preg_match('/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/', $lat);
    $latd = doubleval($lat);
    if($latd < -90 || $latd > 90) {
      return false;
    }
    return true;
  }

  public function validateLongitude($lon) {
    //preg_match('/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/', $lon);
    $lond = doubleval($lon);
    if($lond < -180 || $lond > 180) {
      return false;
    }
    return true;
  }

}
