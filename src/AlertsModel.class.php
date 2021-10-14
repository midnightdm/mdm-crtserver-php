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
    
    public function __construct() {
        parent::__construct(['name' => 'user_devices']);
        //Initialize Messages contoller
        $this->messageController = new Messages();

    }

    public function triggerEvent($event, $liveObj) {
        //Error check for valid event code
        $codes = array("alphada", "alphaua", "alphadp", "alphaup", "bravoda", "bravoua", "bravodp", "bravoup", "charlieda", "charlieua", "charliedp", "charlieup", "deltada", "deltaua", "deltadp", "deltaup", "detecta", "detectp","albany", "camanche", "beaverua", "beaverda", "m503up", "m504up", "m505up", "m506up", "m507up","m508up","m509up", "m510up", "m511up", "m512up", "m513up", "m514up", "m515up", "m516up", "m517up", "m518up", "m519up", "m520up", "m521up", "m522up", "m523up","m524up", "m525up","m526up", "m527up", "m528up", "m529up", "m530up", "m531up", "m532up", "m533up", "m534up", "m535up", "m536up", "m537up", "m538up", "m539up", "m503dp", "m504dp", "m505dp", "m506dp", "m507dp", "m508dp", "m509dp", "m510dp","m511dp","m512dp","m513dp","m514dp","m515dp","m516dp","m517dp",  "m518dp","m519dp","m520dp","m521dp","m522dp","m523dp","m524dp","m525dp","m526dp","m527dp","m528dp","m529dp","m530dp","m531dp","m532dp","m533dp","m534dp","m535dp","m536dp","m537dp","m538dp","m539dp","m503ua","m504ua","m505ua","m506ua","m507ua","m508ua","m509ua","m510ua","m511ua","m512ua","m513ua","m514ua","m515ua","m516ua","m517ua", "m518ua","m519ua","m520ua","m521ua","m522ua","m523ua","m524ua","m525ua","m526ua","m527ua","m528ua","m529ua","m530ua","m531ua","m532ua","m533ua","m534ua","m535ua","m536ua","m537ua","m538ua","m539ua","m503da","m504da","m505da","m506da","m507da","m508da","m509da","m510da","m511da","m512da","m513da","m514da","m515da","m516da","m517da", "m518da","m519da","m520da","m521da","m522da","m523da","m524da","m525da","m526da","m527da","m528da","m529da","m530da","m531da","m532da","m533da","m534da","m535da","m536da","m537da","m538da","m539da");
        
        if(!in_array($event, $codes)) {
            flog( "ERROR: AlertsModel::triggerEvent(".$event.") failed. Event not recognized.\n");
            return false;
        }

        //Publish the event to the database [Added 10/13/21]
        $this->publishAlertMessage($event, $liveObj);

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
                        $this->pushNoticeTo($user, $event, $liveObj);
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
            $liveObj->liveLocation->description
        );
        $report = $this->messageController->sendOneSMS($phone, $message);
        
        //$this->generateAlertLogSms($clickSendResponse, $smsMessages);
        flog( "Sent SMS message to ".$phone."  Report: ".$report."\n");
        
    }

    public function pushNoticeTo($user, $event, $liveObj) {
		//Prepare notification message
		$messageTxt = $this->buildAlertMessage(
            $event, 
            $liveObj->liveName, 
            $liveObj->liveVessel->vesselType, 
            $liveObj->liveDirection,
            $liveObj->liveLocation->eventTS,
            $liveObj->liveLastLat,
            $liveObj->liveLastLon,
            $liveObj->liveLocation->description
        );
        $report = $this->messageController->sendOneNotification($user, $messageTxt, $liveObj);

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
            $liveObj->liveLocation->description
        );
        $report = $this->messageController->sendEmail($address, $subject, $message);
        flog( $report); 
    }

    public function buildAlertMessage($event, $vesselName, $vesselType, $direction, $ts, $lat, $lon, $location) {
        $loc = "";
        $str = "m/j/Y h:i:sa";
        $offset = getTimeOffset();
        //Parse event code to get status (and marker data)
        if(strpos($event, "m")==0) { 
            $status = "marker"; 
            $mile = substr(1,3);
        } elseif(strpos($event, 'alpha'   )==0) { 
            $status = "alpha";
        } elseif(strpos($event, 'bravo'   )==0) {
            $status = "bravo";
        } elseif(strpos($event, 'charlie' )==0) {
            $status = "charlie";
        } elseif(strpos($event, 'delta'   )==0) {
            $status = "delta";
        } elseif(strpos($event, 'albany'  )==0) {
            $status = "albany";
        } elseif(strpos($event, 'camanche')==0) {
            $status = "camanche";
        } elseif(strpos($event, 'beaver' ) ==0) {
            $status = "beaver";
        } elseif(strpos($event, 'detect' ) ==0) {
            $status = "detect";
        } else { 
            $status = "Not Resolved";
        }
        flog( "AlertsModel::buildAlertMessage() event: $event, status: $status\n");
        switch($status) {
            case "alpha" : $evtDesc = "crossed 3 mi N of Lock 13 ";  break;
            case "bravo" : $evtDesc = $direction=="downriver" ? "left " : " reached ";
                            $evtDesc .= "Lock 13 "; break;
            case "charlie" : $evtDesc = "passed the Clinton RR drawbridge ";  break;
            case "delta" : $evtDesc = "crossed 3 mi S of drawbridge ";  break;
            case "detect" : $evtDesc = "has been detected "; break;
            case "albany" : $evtDesc = "has entered the Albany sand pit harbor ";  break;
            case "camanche": $evtDesc = "has entered the Camanche marina harbor ";  break;
            case "beaver" : $evtDesc = "is now in Beaver slough ";  break;
            case "marker" : $evtDesc = " is ".$location; break;
        }
        $txt  = str_replace('Vessel', '', $vesselType);
        $txt .= " Vessel ".$vesselName." ".$evtDesc;
        $txt .= $direction=='undetermined' ? "" : "traveling ".$direction;
        $txt .= ". ".date($str, ($ts+$offset)).$loc;
        return $txt;
    }

    public function publishAlertMessage($event, $liveScan) {
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
        $data = [
            'apubTS'=>$ts, 
            'apubText'=>$txt, 
            'apubVesselID'=>$liveScan->liveVesselID, 
            'apubVesselName' => $liveScan->liveName, 
            'apubEvent'=>$event, 
            'apubDir'=>$liveScan->liveDirection
        ];
        $this->db->collection('Alertpublish')->add($data);
        flog('AlertsModel::publishAlertMessage()\n');
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


