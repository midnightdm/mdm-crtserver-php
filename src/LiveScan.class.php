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
  public $liveRegion  = null;
  public $liveMile = null;
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

  public $inCameraRange = FALSE;
  //Valid cameras: 'CabinDR', 'CabinUR', 'HistoricalSoc', 'SawmillLeft','SawmillCenter','SawmillRight', 'PortByron'
  public $liveCamera   = [
    "srcID"=>false, 
    "zoom"=>0
  ]; 
  public $isReloaded;
  public $triggerQueued;
  public $triggerActivated;
  //public $liveEta;
  public $liveSpeed;
  public $liveCourse;
  //public $liveLength;
  //public $liveWidth;
  //public $liveDraft;
  public $livePassageWasSaved = false;
  public $liveIsLocal;
  public $PlotDaemon;
  public $lookUpCount = 0;
  public $dirScore    = 0;
  public $reload;
  public $reloadTS;
  public $lastDetectedTS;
  public $lastVideoRecordedTS;

  public function __construct($ts, $name, $id, $lat, $lon, $speed, $course, $pd, $reload=false, $reloadData=[], $isTestMode=false) {
    $this->PlotDaemon = $pd;
    //Reload construct
    if ($reload) {
      foreach ($reloadData as $attribute => $value) {        
        //Skip loading DB string on reload, add object instead.
        if($attribute=="liveLocation") {
          $this->liveLocation = null;
          continue;
        }
        if($attribute=="inCameraRange") {
          $this->inCameraRange = false;
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
      $this->PlotDaemon->LiveScanModel->deleteLiveScan($this->liveVesselID);
    //New Construct        
    } else {
      $this->setTimestamp($ts, 'liveInitTS');
      $this->setTimestamp($ts, 'liveLastTS');
      $this->setTimestamp($ts, 'transponderTS');
      $this->liveName = $name;
      $this->liveVesselID = $id;
      $this->liveIsLocal = in_array($id, $this->PlotDaemon->localVesselFilter);      
      //Validate latitude and logitude values
      if(!$this->validateLatitude($lat) || !$this->validateLongitude($lon)) {
        unset($this->PlotDaemon->liveScan['mmsi'.$id]);
        flog("\033[41m *  * Constructor stopped for vessel $id because of a bad initial position value: $lat, $lon.  *  * \033[0m\r\n");
        return;
      }
      $this->liveInitLat = $lat;
      $this->liveInitLon = $lon;
      $this->liveSpeed = $speed;
      $this->liveCourse = $course;
      //  liveSegment property deferred until first update
      // if($this->liveLocation != null) {
      //   $this->liveSegment = $this->liveLocation->determineSegment();//Added 8/21/22 $this->determineSegment($lat);  
      // }  
      $this->lookUpVessel();
      $newDetect = $this->testWhenVesselLastDetected($id,$ts);
      if($newDetect) {
        flog("\033[41m vesselLastDetectedTS has been updated for $this->liveName.\033[0m\n");
      }
      //Use scraped vesselName if not provided by transponder
      if(strpos($this->liveName, strval($id))>-1 || $this->liveName==="") {
        $this->liveName = $this->liveVessel->vesselName;
      }
      //Skip DB writes in Test Mode
      if($isTestMode) {
        flog("LiveScan::construct() skipping DB writes\n");
        return;
      }
      $recordInserted = $this->insertNewRecord();
      //Unset this construction if above failed
      if(!$recordInserted) {
        flog("\033[41m *  * Constructor stopped for vessel $id because insertNewRecord() failed.  *  * \033[0m\r\n");
        unset($this->PlotDaemon->liveScan['mmsi'.$id]);
        return;
      }     
    }
    //Set 0 value if not set
    if(!isset($this->lastVideoRecordedTS)) {
      $this->lastVideoRecordedTS = 0;
    }   
  }

  public function testWhenVesselLastDetected($id,$ts) {
    //Test for previous detect, don't resave if within last 24 hours
    $lastDetectedTS = $this->PlotDaemon->VesselsModel->getVesselLastDetectedTS($id);
    if($lastDetectedTS!==false && ($ts-$lastDetectedTS[0])>86400) {
       //If not recent, put date string in LiveScan
       $this->lastDetectedTS = $lastDetectedTS[1];
       //Then write date TS to vessel record 
       $this->PlotDaemon->VesselsModel->updateVesselLastDetectedTS($id,$ts);
       return true;
    }
    return false; 
  }


  //Depricated Function Not Used
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
      $this->PlotDaemon->VesselsModel->updateVesselLastDetectedTS($this->liveVesselID, time());
      

      if($this->liveLocation->isNewEvent($event)) {
        //trigger an alert
        $this->triggerQueued = false;
        $this->triggerActivated = true;
        $this->PlotDaemon->AlertsModel->triggerEvent($event, $this);
        flog("\033[42m \033[30m       ...ALERT BY ".$this->liveName."\033[0m\n");
      }

    } else {
      flog("LiveScan::checkDetectEventTrigger() didn't pass conditional tests...\n");
      // flog("LiveScan::checkDetectEventTrigger() didn't pass conditional tests...\n      triggerQueued? $this->triggerQueued\n      NOT triggerActivated? $ta,\n      liveLocation NOT null?  $n\n\n");
    }
  }
  //End Depricated Function


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
    $data['liveCamera'] = $this->liveCamera;
    $data['inCameraRange'] = $this->inCameraRange;
    //$data['liveLength'] = $this->liveLength;
    //$data['liveWidth'] = $this->liveWidth;
    //$data['liveDraft'] = $this->liveDraft;
    //$data['liveCallSign'] = $this->liveCallSign;
    $data['lastDetectedTS'] = $this->lastDetectedTS;
    $data['liveSpeed'] = $this->liveSpeed;
    $data['liveCourse'] = $this->liveCourse;
    $data['imageUrl']   = $this->liveVessel->vesselImageUrl;
    $data['type']       = $this->liveVessel->vesselType;
    $data['liveIsLocal'] = $this->liveIsLocal;
    flog('Inserting new livescan record for '.$this->liveName .' '.getNow()."\n"); 
    $this->PlotDaemon->LiveScanModel->insertLiveScan($data);
    return true;
  }

  public function update($ts, $name, $id, $lat, $lon, $speed, $course, $isTestMode=false) {
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
    
    $this->liveName    = $name;
    $this->determineDirection();
    if($this->liveName=="" || str_contains($this->liveName, "@@") || (is_null($this->liveVessel) && $this->lookUpCount < 5)) {
      $this->lookUpVessel();
    }
    $this->calculateLocation();
    //$this->checkMarkerPassage(); Retired 7/10/22 after duties passed to calculateLocation()
    if($this->liveLocation != null) {
      $this->liveRegion  = $this->liveLocation->determineRegion(); //Added 7/10/22
      $this->liveSegment = $this->liveLocation->determineSegment();//Added 8/21/22
      $this->liveCamera = $this->liveLocation->determineCamera();           //Added 9/24/22
      //Do somethings with camera data
     
      if($this->liveCamera["srcID"]) {
        flog("          calculateLocation() found {$this->liveName} in camera {$this->liveCamera['srcID']} range.\n");
        $this->inCameraRange = true;
      } else {
        $this->inCameraRange = false;
      }
      //Test if vessel in video capture target area
      $this->liveLocation->determineIfPassingCamera();
    }
    //Skip DB access in test mode
    if($isTestMode) {
        flog("update() skipping DB\n");
        return;
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
    $data['inCameraRange'] = $this->inCameraRange;
    //$data['isInCameraRange'] = $this->isInCameraRange;
    $data['liveCamera'] = $this->liveCamera;
    if($this->liveLocation instanceof Location) {
      $data['liveLocation'] = ucfirst($this->liveLocation->description[0]);
      $data['rangeMile'] = $this->liveLocation->description[2]; //This is integer
      $data['liveEvent']  = $this->liveLocation->event;
      $data['liveEvents'] = $this->liveLocation->events; //This is array
    } else{
      $data['liveLocation'] = "Location Not Calculated";
      $data['liveEvent'] = "";
      $data['liveEvents'] = [];
    }
    if(isset($this->lastVideoRecordedTS)) {
      $data['lastVideoRecordedTS'] = $this->lastVideoRecordedTS;
    }
    $data['liveName'] = $this->liveName;
    $data['liveVesselID'] = $this->liveVesselID;
    $data['liveSpeed'] = $this->liveSpeed;
    $data['liveCourse'] = $this->liveCourse;
    $data['liveSegment'] = $this->liveSegment;
    $data['liveRegion'] = $this->liveRegion;
    $data['imageUrl']   = $this->liveVessel->vesselImageUrl;
    $data['type']       = $this->liveVessel->vesselType;
    $data['vesselWatchOn'] = $this->liveVessel->vesselWatchOn;
    
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

    $this->PlotDaemon->LiveScanModel->updateLiveScan($data);
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


  public function determineEncoderStartConditions_old() {
    flog("         - determineEncoderStartConditions(".$this->liveName.")");
    //Has determination score reached the deactivation threshold with encoder enabled?
    if($this->PlotDaemon->encoderIsEnabled && $this->PlotDaemon->encoderEnabledScore < 1) {
      //Yes, then send deactivation command to the database
      $this->PlotDaemon->AdminTriggersModel->resetEncoderStart();
      flog("\n        resetEncoderStart()");
      return;
    }
    //Has live stream exceeded timeout value?
    $now = time();
    if($this->PlotDaemon->encoderIsEnabled && $now - $this->PlotDaemon->encoderEnabledTS > $this->PlotDaemon->encoderTimeoutValue) {
      //Yes, then decrease determination score. 
      $this->PlotDaemon->encoderEnabledScore--;
      flog("\n         Encoder Score=".$this->PlotDaemon->encoderEnabledScore);
      return;
    }//No, then test other conditions
    //Is this vessel on the target list? 
    if(!in_array(strval($this->liveVesselID), $this->PlotDaemon->encoderWatchList, true)) {
        flog(" = FALSE\n");
      return; //No, done
    } //Yes, continue
    //Is vessel in watch area (based on travel direction)?
    $isInWatchArea = false;
    if($this->liveDirection=="upriver") {
      if(in_array($this->liveLocation->mm, $this->PlotDaemon->encoderUpriverWatch)) {
        $isInWatchArea = true;
      } 
    } else if($this->liveDirection == "downriver") {
      if(in_array($this->liveLocation->mm, $this->PlotDaemon->encoderDnriverWatch)) {
        $isInWatchArea = true;
      }
    }
    if(!$isInWatchArea) { 
      //No, then we're done, but demerit if encoder is on.
      if($this->PlotDaemon->encoderIsEnabled) {
        $this->PlotDaemon->encoderEnabledScore--;
        flog("\n         Encoder Score=".$this->PlotDaemon->encoderEnabledScore);
      }
    } //Yes, continue
    //Has the determination score reached activation threshold?
    if($this->PlotDaemon->encoderEnabledScore > 2) {
      //Yes, then send encoder start command to the database
      $this->PlotDaemon->AdminTriggersModel->setEncoderStart($this);
      flog("\n        running setEncoderStart()");
      return;
    }//No, then increase determination score. Maybe activation next round.
    $this->PlotDaemon->encoderEnabledScore++;
    flog("\n         Encoder Score=".$this->PlotDaemon->encoderEnabledScore);
  }


  public function determineEncoderStartConditions() {
    flog("         - determineEncoderStartConditions(".$this->liveName.")");
    //If on 
    if($this->PlotDaemon->encoderIsEnabled) {
    //      and done,
        if($this->PlotDaemon->encoderEnabledScore < 1) {
    //                then turn off.
            $this->PlotDaemon->AdminTriggersModel->resetEncoderStart(); //sets encoderStart = false in db
            flog("\n        resetEncoderStart()\n");
            return;
    //If on and NOT done,
        } else {
    //                    then if watched vessel
            if($this->vesselIsOnWatchList() ) {
    //                                            is not still ready,            
                if(!$this->vesselIsInWatchArea()) {
    //                                                                then lower its score.
                    $this->PlotDaemon->encoderEnabledScore--;
                    flog(" = TRUE\n");
                    flog("\n         Encoder Score=".$this->PlotDaemon->encoderEnabledScore."\n");
                }
    //                     and not a watched vessel, 
            } else {
    //                                               then do nothing.
                    flog(" = FALSE\n");
            }
        } 
    //If off
    } else if($this->PlotDaemon->encoderIsEnabled==false) {
    //        and ready
        if($this->vesselIsOnWatchList() && $this->vesselIsInWatchArea()) {
    //                  and verified,
            if($this->PlotDaemon->encoderEnabledScore > 2) {
    //                                then turn on.
                $this->PlotDaemon->AdminTriggersModel->setEncoderStart($this); //sets encoderStart = true in db
    //                  but not verified
            } else {
    //                                    then raise score.
                $this->PlotDaemon->encoderEnabledScore++;
                flog("\n         Encoder Score=".$this->PlotDaemon->encoderEnabledScore);
            }
    //If off and not ready, then do nothing.
        } else {
            flog(" = FALSE\n");
            return;
        }
    }
  }


  public function vesselIsOnWatchList() { //bool
    return in_array(strval($this->liveVesselID), $this->PlotDaemon->encoderWatchList, true);
  }

  public function vesselIsInWatchArea() { //bool 
    //Check for missing location data
    if(!$this->liveLocation instanceof Location || !isset($this->liveLocation->mm)) {
        error_log("vesselIsInWatchArea() couldn't find location data for ".$this->liveName);
        return false;
    }
     //Is vessel in watch area (based on travel direction)?
     $isInWatchArea = false;
     if($this->liveDirection=="upriver") {
       if(in_array($this->liveLocation->mm, $this->PlotDaemon->encoderUpriverWatch)) {
         $isInWatchArea = true;
       } 
     } else if($this->liveDirection == "downriver") {
       if(in_array($this->liveLocation->mm, $this->PlotDaemon->encoderDnriverWatch)) {
         $isInWatchArea = true;
       }
     }
     return $isInWatchArea;
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


  public function lookUpVessel() {   
    flog( '      LiveScan::lookUpVessel() '."\n");
    //See if Vessel data is available locally
    if($data = $this->PlotDaemon->VesselsModel->getVessel($this->liveVesselID)) {
      //flog( "Vessel found in database: " .$this->liveName);
      $this->liveVessel = new Vessel($data, $this->PlotDaemon);
      //Give saved vesselName to live if it has none or number only
      if($this->liveName == "" || strpos($this->liveName, '[')  || str_contains($this->liveName, "@@")) {
        $this->liveName = $this->liveVessel->vesselName;
      }
      return;
    }
    
    //Otherwise scrape data from a website
    $url = 'https://www.marinetraffic.com/en/ais/details/ships/mmsi:';
    $q = $this->liveVesselID;
    flog( "Begin scraping for vesselID " . $this->liveVesselID."\n");
    $html = grab_page($url, $q);  
    
    //Edit segment from html string
    $startPos = strpos($html,'<title>Ship ')+12;
    $clip     = substr($html, $startPos);
    $endPos   = (strpos($clip, ' Registered'));
    $len      = strlen($clip);
    $edit     = substr($clip, 0, ($endPos-$len));           
    
    //Isolate vessel type from parenthesis
    $pstart   = strpos($edit, '(');
    $pend     = strpos($edit, ')');
    $type     = substr($edit, $pstart+1, ($pend-2));
    $type     = str_replace(')', '', $type);
    //Vessel name is first part
    $name     = substr($edit, 0, $pstart-1); 
    
    //Count lookup attempt
    $this->lookUpCount++;        

    //assign data gleened from mst table rows
    $data = [];
    $data['vesselType'] = $type;
    
    //Filter Spare - Local Vessel
    if($data['vesselType']=="Spare - Local Vessel") {
      $data['vesselType'] = "Towing";
    }

    //Try for image
    $cs = new CloudStorage('lookUpVessel() in LiveScan'); 
    try {      
      if($cs->scrapeImage($this->liveVesselID)) {
        $base = $cs->image_base;
        $data['vesselHasImage'] = true;
        $data['vesselImageUrl'] = $base.'images/vessels/mmsi' . $this->liveVesselID.'.jpg'; 
      } else {
        $data['vesselHasImage'] = false;
        $data['vesselImageUrl'] = $cs->no_image;
        //'https://storage.googleapis.com/www.clintonrivertraffic.com/images/vessels/no-image-placard.jpg';
      }
    }
    catch (exception $e) {
      $data['vesselHasImage'] = false;
      $data['vesselImageUrl'] = $cs->no_image;
    }
    $data['vesselRecordAddedTS'] = time();
    $data['vesselID'] = $this->liveVesselID;
    $data['vesselWatchOn']  = false;
    $data['vesselName'] = $name; 
    
    $this->liveVessel = new Vessel($data, $this->PlotDaemon);
    //In case scraped data replaced the local above, also update the live object
    $this->liveName     = $data['vesselName'];    
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
    //Save if at least 6 mile markers passed    
    if(count($this->liveLocation->events)>5) {
        $this->PlotDaemon->PassagesModel->savePassage($this);
        $this->livePassageWasSaved = true;
        return true;
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
