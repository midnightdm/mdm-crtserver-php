<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}
/* * * * * * * * *
 * TimeLogger Class
 * daemon/classes/TimeLogger.class.php
 * 
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class TimeLogger {
  public $hrBuild = "";
  public $count   = 0;
  public $prevTS  = null;

  public function timecheck() {
    //Get now in Central time
    $ts = time() + getTimeOffset();
    //Set prevTS as now on first run
    if($this->prevTS == null) {
      $this->prevTS = $ts;
    }
    //Test if 15 minutes have passed
    if(($ts-$this->prevTS)>54000) {
      //Then write to string
      $this->hrBuild .= date('D d M h:ia', $ts).'\n';
      $this->count++;
      //and reset prevTS
      $this->prevTS = $ts;
    }
    //Test if hour has passed
    if($this->count > 3) {
      //Send string
      $this->emailMsg($this->hrBuild);
      //Reset counter & string
      $this->count = 0;
      $this->hrBuild = "";
    }
  }

  public function emailMsg($msgStr) {
    $mail = new PHPMailer();
    $mail->isSMTP();
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = 'ssl';
    $mail->Host = 'smtp.gmail.com';
    $mail->Port = '465';
    $mail->Username = getEnv('CRT_GMAIL_USERNAME');
    $mail->Password = getEnv('CRT_GMAIL_PASSWORD');
    $mail->SetFrom(getEnv('CRT_GMAIL_USERNAME'));
    $mail->Subject('Hourly worker report');
    $mail->Body = $msgStr;
    $mail->AddAddress('bgtalkingdog@gmail.com');
    $mail->Send();
  }
}