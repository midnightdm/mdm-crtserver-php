<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}

//use Minishlink\WebPush\WebPush;
//use Minishlink\WebPush\Subscription;


/* * * * * * * * *
 * AlertsModel Class
 * src/AlertsModel.class.php
 * 
 */
class AlertsModel extends Firestore {

  public $messageController;
  public $appPath;
  public $daemon;
  public $cs; //Cloud Storage obj
  
  public function __construct($daemonCallback) {
      parent::__construct(['name' => 'Alertpublish']);
      flog("INIT: AlertsModel\n");
      global $config;
      $this->appPath = $config['appPath'];
      //Initialize Messages contoller
      $this->messageController = new Messages($config);
      $this->daemon = $daemonCallback;
      $this->cs = new CloudStorage('AlertsModel');
  }

  public function getAlertsAll($region) {
      flog("AlertsModel::getAlertsAll()\n");
      switch($region) {
        case "clinton": $collection = "Alertpublish"; break;
        case "qc"     : $collection = "AlertpublishQC"; break;
      }
      $docRef = $this->db->collection($collection)->document('all');
      $snapshot = $docRef->snapshot();
      if($snapshot->exists()) {
          return $snapshot->data();
      } else {
          return false;
      }  
  }

  public function setAlertsAll($data, $region='clinton') {
    switch($region) {
      case "clinton": $collection = "Alertpublish"; break;
      case "qc"     : $collection = "AlertpublishQC"; break;
    }
      $this->db->collection($collection)->document('all')->set($data);
  }

  public function getAlertsPassenger($region='clinton') {
    switch($region) {
      case "clinton": $collection = "Alertpublish"; break;
      case "qc"     : $collection = "AlertpublishQC"; break;
    }
      $docRef = $this->db->collection($collection)->document('passenger');
      $snapshot = $docRef->snapshot();
      if($snapshot->exists()) {
          return $snapshot->data();
      } else {
          return false;
      }
  }

  public function setAlertsPassenger($data, $region='clinton') {
    switch($region) {
      case "clinton": $collection = "Alertpublish"; break;
      case "qc"     : $collection = "AlertpublishQC"; break;
    }
    $this->db->collection($collection)->document('passenger')->set($data);
  }

  public function voiceIsSet($file) {
    flog("AlertsModel::voiceIsSet($file) --> ");
    $docRef = $this->db->collection('Voice')->document($file); 
    $snapshot = $docRef->snapshot();
    $ret = $snapshot->exists();
    flog($ret."\n");
    return $ret;
  }

  public function setVoice($data) {
    $this->db->collection('Voice')->document($data['id'])->set($data);
  }

  public function checkForUserNotificationTestRequest() {
    flog("      * checkForUserNotificationTestRequest()");
    $ref = $this->db->collection('user_devices');
    $query = $ref->where("alertTestRequest", "==", true);  
    $documents = $query->documents();

    $count = 0;
    foreach($documents as $document) {
      if($document->exists()) {   
        $count++;            
        $user = $document->data();
        $userID = $document->id();
        
        if($user['subscription']['is_enabled']) {
          if($user['alertMethod']=='notification') {         
              $this->pushTestNoticeTo($user);
          } elseif($user['alertMethod']=='email') {
              flog( "        pushTestEmailTo(".$user['alertDest']."\n");
              $this->pushTestEmailTo($user, $event, $liveObj);
          } elseif($user['alertMethod']=='sms') {
              flog( "        pushTestSmsTo(".$user['alertDest']."\n");
              $this->pushTestSmsTo($user);
          }
          
        }  
        $this->resetUserNotificationTestRequest($userID);
      } else {
          flog( "Document ". $document->id(). " does not exist!\n");
      }     
    }
    if($count) {
      flog("\n          $count notification tests pushed\n");
    } else {
      flog(" = NONE\n");
    }
    
  }

  public function resetUserNotificationTestRequest($userID) {
    $time = getNow();
    $this->db->collection('user_devices')
    ->document($userID)
    ->set(['alertTestRequest'=> false, 'alertTestedTS' => $time],['merge'=>true]);
  }   

  public function triggerEvent($event, $liveObj) {
      //Error check for valid event code
      $codes = array("alphada", "alphaua", "alphadp", "alphaup", "bravoda", "bravoua", "bravodp", "bravoup", "charlieda", "charlieua", "charliedp", "charlieup", "deltada", "deltaua", "deltadp", "deltaup", "detecta", "detectp","albany", "camanche", "beaverua", "beaverda", 
      "echoda", "echoua", "echodp", "echoup", "foxtrotda", "foxtrotua", "foxtrotup", "foxtrotua", "golfda", "golfua", "golfdp", "golfup", "hotelda", "hotelua","hoteldp","hotelup", "m500ua", "m500up", "m500da", "m500dp",
      "m501ua", "m501up", "m501da", "m501dp", "m502ua", "m502up", "m502da", "m502dp"," m503up", "m504up", "m505up", "m506up", "m507up","m508up","m509up", "m510up", "m511up", "m512up", "m513up", "m514up", "m515up", "m516up", "m517up", "m518up", "m519up", "m520up", "m521up", "m522up", "m523up","m524up", "m525up","m526up", "m527up", "m528up", "m529up", "m530up", "m531up", "m532up", "m533up", "m534up", "m535up", "m536up", "m537up", "m538up", "m539up", "m503dp", "m504dp", "m505dp", "m506dp", "m507dp", "m508dp", "m509dp", "m510dp","m511dp","m512dp","m513dp","m514dp","m515dp","m516dp","m517dp",  "m518dp","m519dp","m520dp","m521dp","m522dp","m523dp","m524dp","m525dp","m526dp","m527dp","m528dp","m529dp","m530dp","m531dp","m532dp","m533dp","m534dp","m535dp","m536dp","m537dp","m538dp","m539dp","m503ua","m504ua","m505ua","m506ua","m507ua","m508ua","m509ua","m510ua","m511ua","m512ua","m513ua","m514ua","m515ua","m516ua","m517ua", "m518ua","m519ua","m520ua","m521ua","m522ua","m523ua","m524ua","m525ua","m526ua","m527ua","m528ua","m529ua","m530ua","m531ua","m532ua","m533ua","m534ua","m535ua","m536ua","m537ua","m538ua","m539ua","m503da","m504da","m505da","m506da","m507da","m508da","m509da","m510da","m511da","m512da","m513da","m514da","m515da","m516da","m517da", "m518da","m519da","m520da","m521da","m522da","m523da","m524da","m525da","m526da","m527da","m528da","m529da","m530da","m531da","m532da","m533da","m534da","m535da","m536da","m537da","m538da","m539da", "sabula");
      
      if(!in_array($event, $codes)) {
          flog( "\033[43m AlertsModel::triggerEvent(".$event.") not in filter. Probably an excluded edge-of-range mile marker.\033[0m\r\n");
          return false;
      }

      //Publish the event to the database if it passes waypoint type filter 
      $filter = ["alphada", "alphaua", "alphadp", "alphaup", "bravoda", "bravoua", "bravodp", "bravoup", "charlieda", "charlieua", "charliedp", "charlieup", "deltada", "deltaua", "deltadp", "deltaup"];
      if(in_array($event, $filter)) {
        if($this->publishAlertMessage($event, $liveObj)) {
          flog("\033[41m AlertsModel::triggerEvent(".$event.", ".$liveObj->liveName.") WAYPOINT PUBLISHED\033[0m\r\n");
        }
      }    
      //If not otherwise published, but is passenger vessel push a voice annoucement
      else if(str_ends_with($event, 'p')) {
        if(!isset($liveObj->liveRegion) || $liveObj->liveRegion=="outside") {
          return false;
        }
        flog("\033[41m AlertsModel::triggerEvent(".$event.", ".$liveObj->liveName.") PASSENGER VESSEL PROGRESS ACCOUNCEMENT\033[0m\r\n");
        $this->announcePassengerProgress($event, $liveObj);
      }

      //Match triggered event to possible saved subcriptions  
      $ref = $this->db->collection('user_devices');
      $query = $ref->where("events", "array-contains", $event);  
      $documents = $query->documents();

      $count = 0;
      foreach($documents as $document) {

          if($document->exists()) {   
              $count++;            
              $user = $document->data();
              //flog( "User Match Found: ".var_dump($user)."\n");
              if($user['subscription']['is_enabled']) {
                  if($user['alertMethod']=='notification') {  
                      flog( "pushNoticeTo(".$user['subscription']['auth'].") for event ".$event."\n"); 
                      $apubID = $this->getApubID($liveObj->liveRegion); //Method in parent       
                      $this->pushNoticeTo($user, $event, $apubID, $liveObj);
                  } elseif($user['alertMethod']=='email') {
                      flog( "pushEmailTo(".$user['alertDest'].") for event ".$event."\n");
                      $this->pushEmailTo($user, $event, $liveObj);
                  } elseif($user['alertMethod']=='sms') {
                      flog( "pushSmsTo(".$user['alertDest'].") for event ".$event."\n");
                      $this->pushSmsTo($user, $event, $liveObj);
                  }
              }  
          } else {
              flog( "Document ". $document->id(). " does not exist!\n");
          }
      }
      if($count) {
          flog( "*---*---*---*---*---*---*---*---*---*---*---*---*---*---*---*---*---*---*---*---*\n");
          flog( "|                                                                               |\n");
          flog( "*\033[31m   AlertsModel::triggerEvent() found $count subscriber matches for event $event. \033[0m\n");
          flog( "|                                                                               |\n");
          flog( "*---*---*---*---*---*---*---*---*---*---*---*---*---*---*---*---*---*---*---*---*\n");
      }

  }

  public function pushSmsTo($user, $event, $liveObj) {
    $phone = $user['alertDest'];
    $message = $this->buildAlertMessage(
        $event, 
        $liveObj->liveName, 
        $liveObj->liveVessel->vesselType, 
        $liveObj->liveDirection,
        $liveObj->liveLocation->eventTS,
        $liveObj->liveLastLat,
        $liveObj->liveLastLon,
        $liveObj->liveLocation->description[0]
    );
    $report = $this->messageController->sendOneSMS($phone, $message);
    
    //$this->generateAlertLogSms($clickSendResponse, $smsMessages);
    flog( "Sent SMS message to ".$phone."  Report: ".$report."\n");
  }

  public function pushTestSmsTo($user) {
    $phone = $user['alertDest'];
    $message = 'This is a test message requested by the user.';
    $report = $this->messageController->sendOneSMS($phone, $message);
    
    //$this->generateAlertLogSms($clickSendResponse, $smsMessages);
    flog( "Sent test SMS message to ".$phone."  Report: ".$report."\n");
  }

  public function pushNoticeTo($user, $event, $apubID, $liveObj) {
    //Prepare notification message
    $messageTxt = $this->buildAlertMessage(
            $event, 
            $liveObj->liveName, 
            $liveObj->liveVessel->vesselType, 
            $liveObj->liveDirection,
            $liveObj->liveLocation->eventTS,
            $liveObj->liveLastLat,
            $liveObj->liveLastLon,
            $liveObj->liveLocation->description[0]
        );
    flog("pushNoticeTo(".$user['subscription']['auth'].", ".$event.", ".$apubID.", ".$liveObj->liveName." \nMessageTxt: ".$messageTxt);
    $report = $this->messageController->sendOneNotification($user, $messageTxt, $apubID, $event);

    if($report->isSuccess()) {
      flog( "Webpush success.\n");
    } else {
      $target = $report->getRequest()->getUri()->__toString();
      flog( "Failed for {$target}: {$report->getReason()}");
    }	
  }
    

  public function pushTestNoticeTo($user) {
    //Prepare notification message
    $messageTxt = "This is a test message requested by the user.";
    flog("pushTestNoticeTo(". $user['subscription']['auth']. "\n");
    $report = $this->messageController->sendOneNotification($user, $messageTxt, 0, "test");

    if($report->isSuccess()) {
      flog( "Webpush success.\n");
    } else {
      $target = $report->getRequest()->getUri()->__toString();
      flog( "Failed for {$target}: {$report->getReason()}");
    }	
  }

  public function pushEmailTo($user, $event, $liveObj) {
      $address = $user['alertDest'];
      $subject = "CRT Notice for ".$liveObj->liveName;
      $message = $this->buildAlertMessage(
          $event, 
          $liveObj->liveName, 
          $liveObj->liveVessel->vesselType, 
          $liveObj->liveDirection,
          $liveObj->liveLocation->eventTS,
          $liveObj->liveLastLat,
          $liveObj->liveLastLon,
          $liveObj->liveLocation->description[1]
      );
      $report = $this->messageController->sendEmail($address, $subject, $message);
      flog( $report); 
  }

  public function pushTestEmailTo($user) {
    $address = $user['alertDest'];
      $subject = "CRT Notice for Test Vessel";
      $message = "This is a test notification message requested by the user.";
      $report = $this->messageController->sendEmail($address, $subject, $message);
      flog( $report); 
  }

  public function buildAlertMessage($event, $vesselName, $vesselType, $direction, $ts, $lat, $lon, $locDesc) {
    //$loc = ""; 
    $str = "m/j/Y h:i:sa";
    $offset = getTimeOffset();
    //Parse event code to get status (and marker data)
    
    // if(str_starts_with($event, 'alpha'   )) { 
    //     $status = "alpha";
    // } elseif(str_starts_with($event, 'bravo'   )) {
    //     $status = "bravo";
    // } elseif(str_starts_with($event, 'charlie' )) {
    //     $status = "charlie";
    // } elseif(str_starts_with($event, 'delta'   )) {
    //     $status = "delta";
    // } elseif(str_starts_with($event, 'echo'   )) {
    //     $status = "echo";
    // } elseif(str_starts_with($event, 'foxtrot'   )) {
    //     $status = "foxtrot";    
    // } elseif(str_starts_with($event, 'golf'   )) {
    //     $status = "golf";    
    // } elseif(str_starts_with($event, 'hotel'   )) {
    //     $status = "hotel";
    // } elseif(str_starts_with($event, 'albany'  )) {
    //     $status = "albany";
    // } elseif(str_starts_with($event, 'camanche')) {
    //     $status = "camanche";
    // } elseif(str_starts_with($event, 'beaver' ) ) {
    //     $status = "beaver";
    // } elseif(str_starts_with($event, 'detect' ) ) {
    //     $status = "detect";
    // } elseif(str_starts_with($event, "m")) { 
    //     $status = "marker"; 
    //     $mile = substr(1,3);
    // } else { 
    //     $status = "Not Resolved";
    // }
    // flog( "AlertsModel::buildAlertMessage() event: $event\n");
    // switch($status) {
    //     case "alpha" : $evtDesc = "crossed 3 mi N of Lock 13 ";  break;
    //     case "bravo" : $evtDesc = $direction=="downriver" ? "left " : " reached ";
    //                     $evtDesc .= "Lock 13 "; break;
    //     case "charlie" : $evtDesc = "passed the Clinton RR drawbridge ";  break;
    //     case "delta" : $evtDesc = "crossed 3 mi S of drawbridge ";  break;
    //     case "echo" : $evtDesc = "passed the I-80 bridge, LeClaire ";  break;
    //     case "foxtrot" : $evtDesc = "is at Lock 14 ";  break;
    //     case "golf" : $evtDesc = "is at Lock 15 ";  break;
    //     case "hotel" : $evtDesc = "crossed the I-280 bridge ";  break;
    //     case "detect" : $evtDesc = "has been detected "; break;
    //     case "albany" : $evtDesc = "has entered the Albany sand pit harbor ";  break;
    //     case "camanche": $evtDesc = "has entered the Camanche marina harbor ";  break;
    //     case "beaver" : $evtDesc = "is now in Beaver slough ";  break;
    //     case "marker" : $evtDesc = "is ".$location; break;
    // }
    $txt  = str_replace('Vessel', '', $vesselType);
    $txt .= " Vessel ".$vesselName." was ".$locDesc;
    $txt .= $direction=='undetermined' ? "" : " traveling ".$direction;
    $txt .= ". ".date($str, ($ts+$offset));
    return $txt;
  }

  public function buildVoiceMessage($event, $vesselName, $vesselType, $direction, $ts, $lat, $lon, $locDesc) {
    // $loc = ""; 
    // //Parse event code to get status (and marker data)  
    // if(str_starts_with($event, 'alpha'   )) { 
    //     $status = "alpha";
    // } elseif(str_starts_with($event, 'bravo'   )) {
    //     $status = "bravo";
    // } elseif(str_starts_with($event, 'charlie' )) {
    //     $status = "charlie";
    // } elseif(str_starts_with($event, 'delta'   )) {
    //     $status = "delta";
    // } elseif(str_starts_with($event, 'echo'   )) {
    //     $status = "echo";
    // } elseif(str_starts_with($event, 'foxtrot' )) {
    //     $status = "foxtrot";    
    // } elseif(str_starts_with($event, 'golf'   )) {
    //     $status = "golf";    
    // } elseif(str_starts_with($event, 'hotel'   )) {
    //     $status = "hotel";        
    // } elseif(str_starts_with($event, 'albany'  )) {
    //     $status = "albany";
    // } elseif(str_starts_with($event, 'camanche')) {
    //     $status = "camanche";
    // } elseif(str_starts_with($event, 'beaver' ) ) {
    //     $status = "beaver";
    // } elseif(str_starts_with($event, 'detect' ) ) {
    //     $status = "detect";
    // } elseif(str_starts_with($event, "m")) { 
    //     $status = "marker"; 
    //     $mile = substr(1,3);
    // } else { 
    //     $status = "Not Resolved";
    // }
    // flog( "AlertsModel::buildVoiceMessage() event: $event, status: $status\n");
    // switch($status) {
    //     case "alpha" : $evtDesc = "crossed 3 miles north of Lock 13 ";  break;
    //     case "bravo" : $evtDesc = $direction=="downriver" ? " has left " : " has reached ";
    //                     $evtDesc .= "Lock 13 "; break;
    //     case "charlie" : $evtDesc = "passed the Clinton drawbridge ";  break;
    //     case "delta" : $evtDesc = "crossed 3 miles south of the Clinton drawbridge ";  break;
    //     case "echo" : $evtDesc = "passed the Interstate 80 bridge in LeClaire ";  break;
    //     case "foxtrot" : $evtDesc = "is at Lock 14 below Princeton ";  break;
    //     case "golf" : $evtDesc = "is at Lock 15 in Davenport";  break;
    //     case "hotel" : $evtDesc = "crossed the Intersate two-eighty bridge in Davenport ";  break;
    //     case "detect" : $evtDesc = "has been detected "; break;
    //     case "albany" : $evtDesc = "has entered the Albany sand pit harbor ";  break;
    //     case "camanche": $evtDesc = "has entered the Camanche marina harbor ";  break;
    //     case "beaver" : $evtDesc = "is now in Beaver slough ";  break;
    //     case "marker" : $evtDesc = "is ".$location; break;
    // }
    $txt = $direction=='undetermined' ? "" : "Traveling ".$direction.", ";
    $txt .= str_replace('vessel', '', $vesselType);
    $txt .= " Vessel, ".$vesselName.", is ".$locDesc;
    $txt .= ".";
    return $txt;
  }


  public function publishAlertMessage($event, $liveScan) {
    flog("AlertsModel::publishAlertMessage(".$event.",".$liveScan->liveName.") ");
    //This function gets run by Event trigger methods of this class 
    $ts = time();
    if(!isset($liveScan->liveRegion)) {
      trigger_error("AlertsModel::publishAlertMessage() liveScan object parameter is missing liveRegion data.\n");
      return false;
    }
    $region = $liveScan->liveRegion;
    $vesselType = $liveScan->liveVessel==null ? "" : $liveScan->liveVessel->vesselType;
    $apubID = $this->getApubID($region) + 1; //Method in parent
    $type  = strpos($liveScan->liveVessel->vesselType, "assenger") ? "p" : "a";
    $txt = $this->buildAlertMessage(
      $event, 
      $liveScan->liveName, 
      $vesselType,
      $liveScan->liveDirection, 
      $ts, 
      $liveScan->liveInitLat, 
      $liveScan->liveInitLon,
      $liveScan->liveLocation->description[0]
    );
    //Build voice file name based on event, direction & vesselID
    $voiceFileName = substr($event, 0,1).substr($event, -2,1).$liveScan->liveVesselID.".mp3";
    flog( "  AlertsModel::publishAlertMessage() voiceFileName: $voiceFileName, apubID: $apubID\n");
    $apubVoiceUrl = $this->cs->image_base."voice/".$voiceFileName;
    //Build text for voice synthesis
    $voiceTxt = $this->buildVoiceMessage(
      $event, 
      $liveScan->liveName, 
      $vesselType,
      $liveScan->liveDirection, 
      $ts, 
      $liveScan->liveInitLat, 
      $liveScan->liveInitLon,
      $liveScan->liveLocation->description[1]
    );
    flog('$voiceTxt: '.$voiceTxt."\n");
    $this->generateVoice($voiceFileName, $apubVoiceUrl, $voiceTxt);
    $data = [
        'apubID'=>$apubID,
        'apubTS'=>$ts,
        'apubDate'=>$this->serverTimestamp(), 
        'apubText'=>$txt,
        'apubType'=> $type,
        'apubVesselID'=>$liveScan->liveVesselID,
        'apubVesselImageUrl' => $liveScan->liveVessel->vesselImageUrl,
        'apubVoiceUrl' => $apubVoiceUrl,
        'apubVesselName' => $liveScan->liveName, 
        'apubEvent'=>$event, 
        'apubDir'=>$liveScan->liveDirection
    ];
    //Add new Alert document for perm record
    switch($region) {
      case "qc":  
        $collection = "AlertpublishQC"; 
        $arrName = $type=='p' ? 'alertsPassengerQC':'alertsAllQC'; 
        break;                     
      case "clinton": 
        $collection = "Alertpublish";
        $arrName = $type=='p' ? 'alertsPassenger' : 'alertsAll';
        break;
    }
    $this->db->collection($collection)->document(strval($apubID))->set($data);
    //Save new apubID to admin to trigger JS
    $this->stepApubID($region);
    //Also update collective alert list queue (a or p type)...
    
    $len = count($this->daemon->$arrName);
    flog("Last $arrName obj in $len sized array before queue update:". var_dump($this->daemon->$arrName[$len-1])."\n");
    $this->daemon->$arrName = objectQueue($this->daemon->$arrName, $data, 20);
    $len = count($this->daemon->$arrName);
    flog("Last $arrName obj in $len sized array after queue update:". var_dump($this->daemon->$arrName[$len-1])."\n");
    //...and save as db document
    $sref = $type=='p' ? 'setAlertsPassenger' : 'setAlertsAll';
    $this->$sref($this->daemon->$arrName, $region);
    //Use updated array to write RSS files
    $this->generateRss($type, $region);
    return true;
  }


  public function announcePassengerProgress($event, $liveScan) {
    //This function gets run by Event trigger methods of this class 
    $ts = time();
    $region = $liveScan->liveRegion;
    $vesselType = $liveScan->liveVessel==null ? "" : $liveScan->liveVessel->vesselType;
    $vpubID = $this->getVpubID($region) + 1; //Method in parent
    $type  = strpos($liveScan->liveVessel->vesselType, "assenger") ? "p" : "a";
    if($type=='a') {
      trigger_error("Non passenger vessel sent to AlertsModel::announcePassengerProgress() function.");
    }
    //Build voice file name based on event & vesselID
    $voiceFileName = $event.$liveScan->liveVesselID.".mp3";    
    flog( "AlertsModel::announcePassengerProgress() voiceFileName: $voiceFileName, vpubID: $vpubID\n");
    $vpubVoiceUrl = $this->cs->image_base."voice/".$voiceFileName;
    //Build text for voice synthesis
    $voiceTxt = $this->buildVoiceMessage(
      $event, 
      $liveScan->liveName, 
      $vesselType,
      $liveScan->liveDirection, 
      $ts, 
      $liveScan->liveInitLat, 
      $liveScan->liveInitLon,
      $liveScan->liveLocation->description[1]
    );
    flog('$voiceTxt: '.$voiceTxt."\n");
    $this->generateVoice($voiceFileName, $vpubVoiceUrl, $voiceTxt);
    $data = [
        'vpubID'=>$vpubID,
        'vpubTS'=>$ts,
        'vpubDate' => $this->serverTimestamp(), 
        'vpubText'=>$voiceTxt,
        'vpubType'=> $type,
        'vpubVesselID'=>$liveScan->liveVesselID,
        'vpubVesselImageUrl' => $liveScan->liveVessel->vesselImageUrl,
        'vpubVoiceUrl' => $vpubVoiceUrl,
        'vpubVesselName' => $liveScan->liveName, 
        'vpubEvent'=>$event, 
        'vpubDir'=>$liveScan->liveDirection
    ];
    //Add new db document for perm record
    flog("announcePassengerProgress region is ".$region);
    switch($region) {
      
      case "qc":      $collection = "VoicepublishQC";  break;                     
      case "clinton": $collection = "Voicepublish";    break;
    }
    $this->db->collection($collection)->document(strval($vpubID))->set($data);
    //Now save new vpubID to admin which trips JS event
    $this->stepVpubID($region);
}

  public function generateVoice($fileName, $fullUrl, $text) {
    //Get speech instance & random voice 
    $mts = new MyTextToSpeech();
    $name = "en-US-Standard-C"; //$mts->getRandomVoiceName();
    $gender = "FEMALE";         //$mts->getRandomVoiceGender();
    //Check whether file with this name is in database
    $baseFileName = substr($fileName,0,-4);
    if(!$this->voiceIsSet($baseFileName)) {
      $data = [
        'date' => $this->serverTimestamp(),
        'fileName' => $fileName,
        'id'=> $baseFileName,
        'text' => $text,
        'ts'=> time(),
        'url'=> $fullUrl,
        'voice'=> $name." ".$gender
      ];
      flog("AlertsModel::generateVoice() using $name $gender\n");
      //If not, write file info to db
      $this->setVoice($data);
      //Use API to synthesize speech

      $audioData = $mts->getSpeech($text, $name, $gender);
      //Save audio file in cloud storage
      $this->cs->saveVoiceFile($fileName, $audioData);
    }

  }

  public function generateRss($vt, $region) { //$vt Vessel Type a=any, p=passenger
      flog("AlertsModel::generateRss()\n");
      //Set vars based on type
      $head =  $vt == "p" ? "PASSENGER" : "ALL VESSELS";
      $label = $vt == "p" ? "passenger" : "all commercial";
      switch($region) {
        case "clinton":
          $fileName = $vt == "p" ? "passenger.rss" : "any.rss";
          $arrName = $vt == "p" ? "alertsPassenger" :"alertsAll";
          $crossing = "Clinton, Iowa";
          $baseUrl = "https://www.clintonrivertraffic.com";
          break;
        case "qc":
          $fileName = $vt == "p" ? "passengerQC.rss" : "anyQC.rss";
          $arrName = $vt == "p" ? "alertsPassengerQC" :"alertsAllQC";
          $crossing = "the Quad Cities";
          $baseUrl = "https://www.qcrivertraffic.com";
          break;
      }  
      $documentArr = $this->daemon->$arrName;

      //Date building
      $str    = "D, j M Y G:i:s \C\D\T"; 
      $offset = getTimeOffset();
      $time   = time();
      $arrLen = count($documentArr);
      $endTS  = $documentArr[$arrLen-1]['apubTS'];
      $pubdate = date($str, ($endTS+$offset));
  
      //Begin building rss XML document
      $output = <<<_END
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:content="http://purl.org/rss/1.0/modules/content/" version="2.0">
  <channel>
    <title>Clinton River Traffic-$head</title>
    <link>$baseUrl</link>
    <description>Waypoint crossing notifications for $label vessels passing $crossing on the Mississippi river.</description>
    <language>en</language>
    <pubDate>$pubdate</pubDate>
_END;
      //Loop through returned data
      $items = "";
      foreach($documentArr as $data) {
          $vesselID  = $data['apubVesselID'];
          $alertID   = $data['apubID'];
          $vesselLink = $baseUrl."/alerts/waypoint/".$alertID;
          $vesselName  = $data['apubVesselName'];
          $itemPubDate = date( $str, ($data['apubTS']+$offset) );
          $vesselImg  = $data['apubVesselImageUrl'];
          $text       = htmlentities($data['apubText']);
          $title      = "Notice# ".$alertID." ".$text;
          $items .= "<item>\n\t\t<title>$title</title>\n\t\t<description>$text</description>\n\t\t"
                  ."<pubDate>$itemPubDate</pubDate>\n\t\t<link>$vesselLink</link>\n\t"
                  ."\t<content:encoded><![CDATA[<img src=\"$vesselImg\" alt=\"Image of vessel $vesselName\"/>]]></content:encoded>\n\t</item>\n\t";
      }
      //Conclude XML document
      $output .= $items."</channel>\n</rss>\n";
      //Save file locally
      file_put_contents($this->appPath."/". $fileName, $output);
      //Upload to cloud bucket
      $this->cs->upload( $this->appPath.'/'. $fileName, basename($fileName));          
  }


  //Methods below were for sql based version of app 

  public function generateAlertLogSms($clickSendResponse, $smsMessages) {
      //Gets run by generateAlertMessages() method of this class to document response from sms host
      $csArr = $clickSendResponse->data->messages;
      foreach ($smsMessages as $msg) {
          $data = [];
          $data['alogAlertID']   = intval($msg['alertID']);
          $data['alogDirection'] = $msg['dir'];
          $data['alogType']      = $msg['event'];
          $data['alogMessageTo'] = $msg['phone'];
          $data['alogMessageType'] = 'sms';
          $sms = current($csArr);
          while($sms) {
              if($sms->to == $msg['phone']) {
                  $data['alogMessageID']     = $sms->message_id;
                  $data['alogMessageCost']    = $sms->message_price;
                  $data['alogMessageStatus'] = $sms->status;
                  $data['alogTS']            = $sms->schedule;
                  break;          
              }
              next($csArr);
          }
          //Test dump
          //echo "AlertsModel::generateAlertLogSms() test dumping data array...\n";
          //var_dump($data);
          $db = $this->db();
          $sql = "INSERT INTO alertlog (alogAlertID, alogType, alogTS, alogDirection, alogMessageType, alogMessageTo, "
          . "alogMessageID, alogMessageCost, alogMessageStatus) VALUES (:alogAlertID, :alogType, :alogTS, "
          . ":alogDirection, :alogMessageType, :alogMessageTo, :alogMessageID, :alogMessageCost, :alogMessageStatus)";
          echo "AlertsModel::generateAlertLogSms()\n";
          $db->prepare($sql)->execute($data);
      }
  }

  /* DEPRICATED 
  public function generateAlertLogNotif($pusherResponse, $notifMessages) {
      //Gets run by generateAlertMessages() method of this class to document response from sms host
      $now = time();
      foreach($notifMessages as $m) {    
      $data = [
          'alogAlertID'=>substr($m['subject'],9), 
          'alogType'=>$m['event'], 
          'alogTS'=>$now, 
          'alogDirection'=>$m['dir'],
          'alogMessageType'=>'notif',
          'alogMessageTo' =>$m['to'],
          'alogMessageID'=>$pusherResponse->publishId,
          'alogMessageCost'=> "",
          'alogMessageStatus'=>""
      ];
      $db = $this->db();
      $sql = "INSERT INTO alertlog (alogAlertID, alogType, alogTS, alogDirection, alogMessageType, alogMessageTo, "
      . "alogMessageID, alogMessageCost, alogMessageStatus) VALUES (:alogAlertID, :alogType, :alogTS, "
      . ":alogDirection, :alogMessageType, :alogMessageTo, :alogMessageID, :alogMessageCost, :alogMessageStatus)";
      echo "AlertsModel::generateAlertLogNotif()\n";
      $db->prepare($sql)->execute($data);
      }
  }
  */

  public function generateAlertLogEmail($clickSendResponse, $emailMessages ) {
      //Gets run by generateAlertMessages() method of this class to document phpMailer process. clickSend host portion is depreciated
      //$csArr = $clickSendResponse->data->data;
      foreach ($emailMessages as $msg) {
      $data = [];
      $data['alogAlertID']   = intval($msg['alertID']);
      $data['alogDirection'] = $msg['dir'];
      $data['alogType']      = $msg['event'];
      $data['alogMessageTo'] = $msg['to'];
      $data['alogMessageType'] = 'email';
      //$cs = current($csArr);
      //while($cs) {
      //  if($cs->to->email == $msg['to']) {
      //    $data['alogMessageID']     = $cs->message_id;
      //    $data['alogMessgeCost']    = $cs->price;
      //    $data['alogMessageStatus'] = $cs->status;
      //    $data['alogTS']            = $cs->date_added;
      //    break;          
      //  }
      //  next($csArr);
      //}
      $data['alogMessageID']     = 'N/A';
      $data['alogMessageCost']    = 0.0;
      $data['alogMessageStatus'] = 'N/A';
      $data['alogTS']            = time();
      //Test dump
      //echo "AlertsModel::generateAlertLogEmail() test dumping data array...\n";
      //var_dump($data);
      $db = $this->db();
      $sql = "INSERT INTO alertlog (alogAlertID, alogType, alogTS, alogDirection, alogMessageType, alogMessageTo, "
      . "alogMessageID, alogMessageCost, alogMessageStatus) VALUES (:alogAlertID, :alogType, :alogTS, "
      . ":alogDirection, :alogMessageType, :alogMessageTo, :alogMessageID, :alogMessageCost, :alogMessageStatus)";
      echo "AlertsModel::generateAlertLogEmail()\n";
      $db->prepare($sql)->execute($data);
      }
  }

  public function triggerDetectEvent($event, $liveScan) { 
      $this->postAlertMessage("detected", $liveScan);
      //$this->queueAlertsForVessel($liveScan->liveVesselID, "detect", 21600, "undetermined"); //6 hours
      echo "Alerts monitor Detect Event triggered by ".$liveScan->liveName."  \n";
      $apubID = $this->getLastPublishedAlertId();
      return $apubID;
  }

  public function triggerAlphaEvent($liveScan) { //Returns alertpublish record id
      $this->postAlertMessage("alpha", $liveScan);
      /*
      if($liveScan->liveDirection == 'downriver') {
      $this->queueAlertsForVessel($liveScan->liveVesselID, "alpha", 7200, $liveScan->liveDirection); //2 hours
      } 
      */   
      echo "Alerts monitor Alpha Event triggered by ".$liveScan->liveName."  \n";
      $apubID = $this->getLastPublishedAlertId();
      return $apubID;
  }

  public function triggerBravoEvent($liveScan) { //Returns alertpublish record id
      $this->postAlertMessage("bravo", $liveScan);
      //$this->queueAlertsForVessel($liveScan->liveVesselID, "bravo", 7200, $liveScan->liveDirection); //2 hours);
      $apubID = $this->getLastPublishedAlertId();
      echo "Alerts monitor Bravo Event triggered by ".$liveScan->liveName." with \$apubID = ".$apubID."\n";
      return $apubID;
  }

  public function triggerCharlieEvent($liveScan) { //Returns alertpublish record id
      $this->postAlertMessage("charlie", $liveScan);
      //$this->queueAlertsForVessel($liveScan->liveVesselID, "charlie", 7200, $liveScan->liveDirection); //2 hours)
      echo "Alerts monitor Charlie Event triggered by ".$liveScan->liveName."  \n";
      $apubID = $this->getLastPublishedAlertId();
      return $apubID;
  }

  public function triggerDeltaEvent($liveScan) { //Returns alertpublish record id
      $this->postAlertMessage("delta", $liveScan);
      /*
      if($liveScan->liveDirection=='upriver') {
      $this->queueAlertsForVessel($liveScan->liveVesselID, "delta", 7200, $liveScan->liveDirection); //2 hours
      } 
      */   
      echo "Alerts monitor Delta Event triggered by ".$liveScan->liveName."  \n";
      $apubID = $this->getLastPublishedAlertId();
      return $apubID;
  }

  public function getLastPublishedAlertId() {
      //Gets run by CRTdaemon::setup() and by $this->trigger_____Event() methods
      $sql = "SELECT apubID FROM alertpublish ORDER BY apubTS DESC LIMIT 1";
      $q = $this->db()->query($sql);
      if($results = $q->fetch()) {
          $apubID = intval($results['apubID']);
          //echo "getLastPublishedAlertId(): \n";
          //echo var_dump($results);
          //echo "\n".$apubID;
          return $apubID;
      }
      return false;
  }
}


