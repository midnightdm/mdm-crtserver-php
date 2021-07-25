<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}


/* * * * * * * * *
 * AlertsModel Class
 * daemon/classes/AlertsModel.class.php
 * 
 */
class AlertsModel extends Dbh {
  public function __construct() {
    parent::__construct();
  }

 
  public function buildAlertMessage($event, $vesselName, $vesselType, $direction, $ts, $lat, $lon) {
    $loc = "";
    $str = "m/j/Y h:i:sa";
    $offset = getTimeOffset();
    switch($event) {
      case "detected": $evtDesc = "Transponder detected";
                     $loc    .= "\nLocation: https://maps.google.com/maps?q=".$lat.",".$lon; break;
      case "alpha" : $evtDesc = "crossed 3 mi N of Lock 13 ";  break;
      case "bravo" : $evtDesc = $direction=="downriver" ? "left " : " reached ";
                     $evtDesc .= "Lock 13 "; break;
      case "charlie" : $evtDesc = "passed the Clinton RR drawbridge ";  break;
      case "delta" : $evtDesc = "crossed 3 mi S of drawbridge ";  break;
    }
    $txt  = str_replace('Vessel', '', $vesselType);
    $txt .= " Vessel ".$vesselName." ".$evtDesc;
    $txt .= $direction=='undetermined' ? "" : "traveling ".$direction;
    $txt .= ". ".date($str, ($ts+$offset)).$loc;
    return $txt;
  }

  public function postAlertMessage($event, $liveScan) {
  //This function gets run by Event trigger methods of this class 
    $ts = time();
    $vesselType = $liveScan->liveVessel==null ? "" : $liveScan->liveVessel->vesselType;
    $txt = $this->buildAlertMessage(
      $event, 
      $liveScan->liveName, 
      $vesselType,
      $liveScan->liveDirection, 
      $ts, 
      $liveScan->liveInitLat, 
      $liveScan->liveInitLon
    );
    //$sql = "INSERT INTO alertpublish (apubTS, apubText, apubVesselID, apubVesselName) VALUES ( ". $ts.", ".addslashes($txt).", "
    // .$liveScan->liveVesselID.", ".$liveScan->liveName.")";
    $sql = "INSERT INTO alertpublish (apubTS, apubText, apubVesselID, apubVesselName, apubEvent, apubDir) VALUES (:apubTS, :apubText, :apubVesselID, :apubVesselName, :apubEvent, :apubDir)";
    $data = ['apubTS'=>$ts, 'apubText'=>$txt, 'apubVesselID'=>$liveScan->liveVesselID, 'apubVesselName' => $liveScan->liveName, 'apubEvent'=>$event, 'apubDir'=>$liveScan->liveDirection];
    $db = $this->db();
    $res = $db->prepare($sql);
    
    try {
      $res->execute($data);
    } catch(PDOException $exception){ 
      echo $exception; 
    }            
  }
  
  public function generateAlertMessages($limit) {
    //This function gets run by CRTdaemon::checkAlertStatus()
    $db = $this->db();
    $sql = "SELECT * FROM alertpublish ORDER BY apubTS DESC LIMIT $limit";
    $q1 = $db->query($sql);
    
    //Get data for new found messages     
    if($limit > 1) {
      $publishData    = $q1->fetchAll();
    } elseif($limit==1) {
      $publishData    = [];
      $publishData[0] = $q1->fetch(); 
    }
    unset($db);
    //arrays for messages
    $smsMessages   = [];
    $emailMessages = [];
    $notifMessages = [];
    
    //Check data
    //echo "Dumping \$publishData array with \$limit = ".$limit."\n";
    //die(var_dump($publishData));

    //Loop through publish data to get elements for next searches
    foreach($publishData as $row) {
      $alertID   = $row['apubID'];
      $txt       = $row['apubText'];
      $vesselID  = $row['apubVesselID'];
      $name      = $row['apubVesselName'];
      $event     = $row['apubEvent'];
      $dir       = $row['apubDir'];
      switch($dir) {
        case "upriver"  : $add = "Up";   break;
        case "downriver": $add = "Down"; break;
        default         : $add = "";     break;
      }
      
      //Find alerts for this event and direction for 'any' vessel
      $sql = "SELECT alertDest, alertMethod FROM alerts WHERE alertOnAny = 1 AND alertOn".ucfirst($event).$add. " = 1";
      $db = $this->db();
      $q2 = false;
      try {
        
        //echo "Tried query was \"".$sql."\"\n";
        $q2 = $db->query($sql);
        //echo "Dumping \$q2 ".var_dump($q2)."\n";
      } catch(PDOException $exception){ 
        echo $exception; 
      }  
      if(!$q2) {
        //error_log("No 'Any' alerts found for alertpublish ID $alertID");
        echo "No 'Any' alerts found for alertpublish ID $alertID\n";
        continue;
      } elseif ($q2->rowCount()) {
        $alertOnAnyData = $q2->fetchAll();
        foreach($alertOnAnyData as $row) {
          if($row['alertMethod']=='sms') {
            $smsMsg = ['phone'=>$row['alertDest'], 'text'=>'CRT Alert '.$alertID."\n".$txt, 'event' => $event, 'dir' => $dir, 'alertID' => $alertID];
            $smsMessages[] = $smsMsg;
          } elseif($row['alertMethod']=='email') {
            $emlMsg = ['to'=>$row['alertDest'],  'text'=>$txt, 'subject'=> 'CRT Alert '.$alertID.' for '.$name, 'event' => $event, 'dir' => $dir, 'alertID' => $alertID];
            $emailMessages[] = $emlMsg;
          } elseif($row['alertMethod']=='notification') {
            $notMsg = ['to'=>$row['alertDest'],  'text'=>$txt, 'subject'=> 'CRT Alert '.$alertID.' for '.$name, 'event' => $event, 'dir' => $dir, 'alertID' => $alertID];
            $notifMessages[] = $notMsg;
          }
        }
      }      
      unset($db);
      
      //Find alerts for this event and direction for specified vessel   
      $sql = "SELECT alertDest, alertMethod FROM alerts WHERE alertVesselID = ? AND alertOn".ucfirst($event).$add. " = 1";
      $db = $this->db();
      $q3 = $db->prepare($sql);
      $q3->execute([$vesselID]);
      if(!$q3) {
        //error_log("No vessel specific alerts found for alertpublish ID $alertID");
        echo "No 'vessel specific alerts found for alertpublish ID $alertID\n";
        continue;
      } elseif ($q3->rowCount()) { 
        $alertOnVesselData = $q3->fetchAll();
        foreach($alertOnVesselData as $row) {
          if($row['alertMethod']=='sms') {
            $smsMsg = ['phone'=>$row['alertDest'], 'text'=>'CRT Alert '.$alertID.' '.$txt, 'event' => $event, 'dir' => $dir, 'alertID' => $alertID];
            $smsMessages[] = $smsMsg;
          } elseif($row['alertMethod']=='email') {
            $emlMsg = ['to'=>$row['alertDest'],  'text'=>$txt, 'subject'=> 'CRT Alert '.$alertID.' for '.$name, 'event' => $event, 'dir' => $dir, 'alertID' => $alertID];
            $emailMessages[] = $emlMsg;
          } elseif($row['alertMethod']=='notification') {
            $notMsg = ['to'=>$row['alertDest'],  'text'=>$txt, 'subject'=> 'CRT Alert '.$alertID.' for '.$name, 'event' => $event, 'dir' => $dir, 'alertID' => $alertID];
            $notifMessages[] = $notMsg;
          }
        }
      }
      unset($db);
    }
 
     //---- Test code start point ----
     //echo "Dumping smsMessages array now....:\n\n";
     //echo var_dump($smsMessages);
     //echo "Dumping emailMessages array now....:\n\n";
     //echo var_dump($emailMessages);
     //--- Test code end point -----
     
     //Send $smsMessages & $emailMessages assembled in loops above
     $msgController = new Messages();
     $qtySmsMessages = count($smsMessages);
     if($qtySmsMessages>0) {
        $clickSendResponse = json_decode($msgController->sendSMS($smsMessages));
        $this->generateAlertLogSms($clickSendResponse, $smsMessages);
        echo "Sent $qtySmsMessages SMS messages.\n";
        unset($smsMessages);
     }
      
     $qtyEmailMessages = count($emailMessages);
     if($qtyEmailMessages>0) {
       //$clickSendResponse = json_decode($msgController->sendSMS($emailMessages));
        $msgController->sendEmail($emailMessages);
        $this->generateAlertLogEmail(null, $emailMessages);
        echo "Sent $qtyEmailMessages Email messages.\n";
        unset($emailMessages);
     }

     $qtyNotifMessages = count($notifMessages);
     if($qtyNotifMessages>0) {
        $pusherResponse = $msgController->sendNotification($notifMessages);
        $this->generateAlertLogNotif($pusherResponse, $notifMessages);
        unset($notifMessages);
     }
  }
  
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

  public function triggerDetectEvent($liveScan) { //Returns alertpublish record id
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
