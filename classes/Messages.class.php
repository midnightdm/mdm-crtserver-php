<?php

if(php_sapi_name() !='cli') { exit('No direct script access allowed: Messages.class.php');}
/* * * * * *
 * Messages class
 * daemon/classes/Messages.class.php
 * 
 *  Requires edits replaced $messages with params ($user, $event, $liveObj)
 *
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
//use Pusher\PushNotifications\PushNotifications;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class Messages {
  public $config;
  public $smsApiInstance;
  public $emailApiInstance;
  //public $pusherApiInstance;
  public $webPushInstance;
  public $msg;

  function __construct() {
    //require_once(__DIR__ . '/../../vendor/autoload.php');
    // Configure HTTP basic authorization: BasicAuth
    $this->config = ClickSend\Configuration::getDefaultConfiguration()
      ->setUsername(getEnv('MDM_CRT_ERR_EML'))
      ->setPassword(getEnv('CLICKSEND_KEY'));
    $this->smsApiInstance = new ClickSend\Api\SMSApi(new GuzzleHttp\Client(),$this->config);
    $this->emailApiInstance = $this->initEmail();
    //$this->pusherApiInstance = $this->initPusher();
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

  function sendSMSes($messages) { //$messages needs to be assoc. array
    $msgs = [];
    foreach($messages as $m)  {   
      $msg = new \ClickSend\Model\SmsMessage();
      $msg->setBody($m['text']); 
      $msg->setTo('+'.$m['phone']);
      $msg->setSource("sdk");
      $msgs[] = $msg;
    }

    // \ClickSend\Model\SmsMessageCollection | SmsMessageCollection model
    $sms_messages = new \ClickSend\Model\SmsMessageCollection(); 
    $sms_messages->setMessages($msgs);
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

  function sendOneNotification($userArr, $messageTxt, $liveObj ) {  
    //Prepare subcription from user array. 
    $subscriber = array();
    $subscriber['endpoint']  = $userArr['subscription']['endpoint'];
    $subscriber['auth']      = $userArr['subscription']['auth'];
    $subscriber['p256dh']    = $userArr['subscription']['p256dh'];
    
    $message = [
			"title" => "CRT Notice for ".$liveObj->liveName,
			"body"  =>  $messageTxt,
			"icon"  => "images/favicon.png",
			"url"   => "https://www.clintonrivertraffic.com/livescan/live"
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
    $subscription = createSubscription($data);
		
    $report = $this->WebPushLibrary
      ->webPush
      ->sendOneNotification($subscription, json_encode($message));
    return $report;
  }

  function sendNotifications($messages) { //$messages needs to be assoc. array
    foreach($messages as $m) {
      $result = $this->pusherApiInstance->publishToInterests(
        array($m['to']),
        array(
          "fcm" => array(
            "notification" => array(
              "title" => $m['subject'],
              "body"  => $m['text']
            )
          ),
          "apns" => array("aps" => array(
            "alert" => array(
              "title" => $m['subject'],
              "body" => $m['text']
            )
          )),
          "web" => array(
            "notification" => array(
              "title" => $m['subject'],
              "body" => $m['text']
            )
          )
        )
      );
      return $result;
      //For testing only
      //echo "pusher response= ". var_dump($result);
    }
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
    $mail->Username = getEnv('CRT_GMAIL_USERNAME');
    $mail->Password = getEnv('CRT_GMAIL_PASSWORD');
    $mail->SetFrom(getEnv('CRT_GMAIL_USERNAME'));
    return $mail;
  }

  public function initPusher() {

    return new PushNotifications(
      array(
        "instanceId" => getEnv('PUSHER_INSTANCE_ID'),
        "secretKey"  => getEnv('PUSHER_SECRET_KEY')
      )
    );
  }

  public function initWebPush() {
    //Prepare VAPID package and initialize WebPush
    $auth = array(
			'VAPID' => array(
				'subject' => 'https://www.clintonrivertraffic.com/about',
				'publicKey' => getenv('MDM_VKEY_PUB'),
				'privateKey' => getenv('MDM_VKEY_PRI') 
			)
		);	
    return new WebPush($auth);
  }
}  


function createSubscription($data) {
	return Subscription::create($data);
}

?>
