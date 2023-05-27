<?php

if(php_sapi_name() !='cli') { exit('No direct script access allowed: Messages.class.php');}
/* * * * * *
 * Messages class
 * daemon/src/Messages.class.php
 * 
 *  Requires edits replaced $messages with params ($user, $event, $liveObj)
 *
 */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class Messages {
  public $clicksendConfig;
  public $plotserverConfig;
  public $smsApiInstance;
  public $emailApiInstance;
  public $webPushInstance;
  public $msg;

  function __construct($config) {
    $plotserverConfig = $config;
    $username = $plotserverConfig['errEmail'];
    $password = $plotserverConfig['clicksendKey'];
    flog("INIT: Messages ClickSend U: $username P: $password\n");
    // Configure HTTP basic authorization: BasicAuth
    $this->clicksendConfig = ClickSend\Configuration::getDefaultConfiguration()
      ->setUsername($username)
      ->setPassword($password);
    
    $this->smsApiInstance = new ClickSend\Api\SMSApi(new GuzzleHttp\Client(),$this->clicksendConfig);
    $this->emailApiInstance = $this->initEmail();

    $this->webPushInstance = $this->initWebPush();
  }
  
  function sendOneSMS($phone, $message) {
    $msg = new ClickSend\Model\SmsMessage();
    $msg->setBody($message); 
    $msg->setTo('+'.$phone);
    $msg->setSource("sdk");
      
    $sms_messages = new \ClickSend\Model\SmsMessageCollection(); 
    $sms_messages->setMessages([$msg]);
    $try = 0;
    
    do {
      try {
          $tryAgain = false;
          $result = $this->smsApiInstance->smsSendPost($sms_messages);
          return $result;
      } catch (Exception $e) {
          $tryAgain = true;      
          echo 'Exception when calling SMSApi->smsSendPost: ', $e->getMessage(), PHP_EOL;
      }
    } while($tryAgain);
  }
  
  function sendOneEmail($address, $subject, $message) {
    $this->emailApiInstance->Subject = $subject;
    $this->emailApiInstance->Body    = $message;
    $this->emailApiInstance->clearAddresses();
    $this->emailApiInstance->AddAddress($address);
    try {
      $this->emailApiInstance->Send();
      return "okay";
    } catch (Exception $e) {
      return "Message could not be sent. Mailer Error: {$this->emailApiInstance->ErrorInfo}"; 
    }      
  }

  function sendEmails($messages) { //$messages needs to be assoc. array
    foreach($messages as $m) {
      $this->emailApiInstance->Subject = $m['subject'];
      $this->emailApiInstance->Body    = $m['text'];
      $this->emailApiInstance->clearAddresses();
      $this->emailApiInstance->AddAddress($m['to']);
      try {
        $this->emailApiInstance->Send();
        return "okay";
      } catch (Exception $e) {
        return "Message could not be sent. Mailer Error: {$this->emailApiInstance->ErrorInfo}"; 
      }      
    }
  }

  function sendOneNotification($userArr, $messageTxt, $apubID, $event ) {  
    //Prepare subcription from user array. 
    $subscriber = array();
    $subscriber['endpoint']  = $userArr['subscription']['endpoint'];
    $subscriber['auth']      = $userArr['subscription']['auth'];
    $subscriber['p256dh']    = $userArr['subscription']['p256dh'];
    $url = $this->getUrlBasedOn($event, $apubID);
    //Package message
    $message = [
			"title"  => $messageTxt." -CRT",
      "icon"  => "https://www.clintonrivertraffic.com/images/favicon.png",
			"url"   => $url
		];
    //Prepare subscription package  
    $data = [
      "contentEncoding" => "aesgcm",
      "endpoint" => $subscriber['endpoint'],
      "keys" => [
        "auth" =>   $subscriber['auth'],
        "p256dh" => $subscriber['p256dh']
      ]
    ];
    $cleanedMsg = stripslashes(json_encode($message));
    $subscription = createSubscription($data);
		flog(" Prepared Message Text: ".$cleanedMsg);

    $report = $this->webPushInstance
      ->sendOneNotification($subscription, $cleanedMsg);
    return $report;
  }

  public function initEmail() {
    $mail = new PHPMailer();
    $mail->isSMTP();
    $mail->SMTPDebug  = 0; //Use 2 for client & server details
    $mail->Host = "smtp.gmail.com";
    $mail->SMTPAuth = true;
    //$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->SMTPSecure = "tls";
    $mail->Port = "587";
    $mail->SMTPKeepAlive = true;
    //$mail->isHTML(true);
    $mail->Username = $this->plotserverConfig['gmailUsername']; //getEnv('CRT_GMAIL_USERNAME');
    $mail->Password = $this->plotserverConfig['gmailPassword']; //getEnv('CRT_GMAIL_PASSWORD');
    $mail->SetFrom($this->plotserverConfig['gmailUsername']);
    return $mail;
  }

  public function initWebPush() {
    //Prepare VAPID package and initialize WebPush
    $auth = array(
			'VAPID' => array(
				'subject' => $this->plotserverConfig['vapidSubject'], //'https://www.clintonrivertraffic.com',
				'publicKey' => $this->plotserverConfig['vkeyPub'], //getenv('MDM_VKEY_PUB'),
				'privateKey' => $this->plotserverConfig['vkeyPri'] //getenv('MDM_VKEY_PRI') 
			)
		);	
    $webPush = createWebPush($auth);
    return $webPush;
    //return new WebPush($auth);
  }

  function getUrlBasedOn($event, $apubID) {
    $filter = ["alphada", "alphaua", "alphadp", "alphaup", "bravoda", "bravoua", "bravodp", "bravoup", "charlieda", "charlieua", "charliedp", "charlieup", "deltada", "deltaua", "deltadp", "deltaup", "detecta", "detectp"];
    if(in_array($event, $filter)) {
      $url = 'https://www.clintonrivertraffic.com/alerts/waypoint/'.$apubID;      
    } else {
      $url = 'https://www.clintonrivertraffic.com/live';
    }
    return $url;
  }

  
}  // End of Class


function createSubscription($data) {
	return Subscription::create($data);
}

function createWebPush($auth) {
  //2nd param sets options for TTL, urgency, topic, batchSize
  return new WebPush($auth, [86400, null, null, 1000]);
}
?>
