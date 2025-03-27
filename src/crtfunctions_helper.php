<?php 
/* * * * * 
 * application/helpers/crtfunctions_helper.php
 */

function is_selected($title, $test) {
  if($title===$test) {
    return "selected";
  } else {
    return "";
  }
}

function base_url() {
  return "localhost/mdm-crt/";
}

function getTimeOffset($ts="") {
  $tz = new DateTimeZone("America/Chicago");
  if($ts==="") {
    $ts = time();
  }
  $dt = new DateTime();
  $dt->setTimestamp($ts);
  $dt->setTimeZone($tz);
  return $dt->format("I") ? -18000 : -21600;
}

function getNow($dateString="Y-m-d H:i:s") {  
  return date($dateString, time()+getTimeOffset());
}

function getYesterdayRange() {
  $offset = -0;
  $today = getdate();
  $todayMidnight = mktime(0,0,0,$today['mon'],$today['mday'])+$offset;
  $yesterdayMidnight = $todayMidnight - 86400 +$offset;
  return [$yesterdayMidnight, ($todayMidnight-1)];
}

function getTodayRange() {
  $offset = getTimeOffset(); //-18000;
  $today = getdate();
  $todayMidnight = mktime(0,0,0,$today['mon'], $today['mday']);
  return [$todayMidnight, $today[0]];
}

function getLast24HoursRange() {
  $offset = getTimeOffset(); //-18000;
  $today = getdate();
  return [($today[0]-86400), $today[0]];
}

function getLast7DaysRange() {
  $offset = getTimeOffset(); //-18000;
  $today = getdate();
  return [($today[0]-604800), $today[0]];
}

function printRange($dateArr) {
  if(!is_array($dateArr)) {
    return "Invalid range array used in printRange()";
  }
  return "Range is ".date('g:ia l, M j', $dateArr[0])." to ".date('g:ia l, M j', $dateArr[1]);
}

//Has server specific 'hard-set' file path
//function saveImage($mmsi) {
//  Replaced by CloudStorage::scrapeImage()
//}

//function to grab page using cURL
function grab_page($url, $query='') {
  //echo "Function grab_page() \$url=$url, \$query=$query\n";
  $ch = curl_init();
  //UA last updated 4/10/21
  $ua = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.114 Safari/537.36";
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_USERAGENT, $ua);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
  curl_setopt($ch, CURLOPT_TIMEOUT, 40);
  curl_setopt($ch, CURLOPT_URL, $url.$query);
  //ob_start();
  return curl_exec($ch);
  //ob_end_clean();
  curl_close($ch);
} 

function grab_image($url){
	$ch = curl_init ();
  $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:86.0) Gecko/20100101 Firefox/86.0';
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_USERAGENT, $ua);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
  curl_setopt($ch, CURLOPT_URL, $url);
	return curl_exec($ch); 
}

function grab_protected($url, $user, $pw){
	$ch = curl_init ();
  $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:86.0) Gecko/20100101 Firefox/86.0';
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_USERAGENT, $ua);
  curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
  curl_setopt($ch, CURLOPT_USERPWD, $user.":".$pw);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
  curl_setopt($ch, CURLOPT_URL, $url);
	return curl_exec($ch); 
}

//function to post to page using cURL
function post_page($url, $data=array('postvar1' => 'value1')) {
  $ch = curl_init();
  //UA last updated 4/10/21
  $ua = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.114 Safari/537.36";
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_USERAGENT, $ua);
//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
  curl_setopt($ch, CURLOPT_TIMEOUT, 40);
  curl_setopt($ch, CURLOPT_URL, $url);
  
  // Convert PHP array to JSON for MongoDB REST API
  $json_data = json_encode($data);
  
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
  
  $result = curl_exec($ch);
  
  if (curl_errno($ch)) {
    flog("CURL Error: " . curl_error($ch) . "\n");
  }
  
  curl_close($ch);
  return $result;
}

function flog($string) {
  $date = Date('ymd', time());
  $file = __DIR__."/../../../../logs/crt_".$date.".log";
  //$file = "e:/app/logs/crt_".$date.".log";
  $handle = fopen($file,'a');
  fwrite($handle, $string);
  fclose($handle);
  echo $string;
}

function objectQueue($arr, $add, $size=20) { //Returns updated $arr
  $arr[] = $add;
  if(count($arr)>$size) {
    array_shift($arr);
  }
  return $arr;
}

function errorHandler($type, $msg, $file=null, $line=null) {
  //Ignore warning socket timeout produces
  if( str_contains($msg, "socket_recvfrom(): Unable to recvfrom") ) {
    return;
  }
  //Ignore clicksend version incompatibility with php 8
  if( str_contains($msg, "#[\ReturnTypeWillChange]") ) {
    return;
  }
  //Ignore Firestore Writebatch error
  if( str_contains($msg, "Google\\Cloud\\Firestore\\WriteBatch")) {
    return;
  } 
  flog("\033[41m *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  * \033[0m\r\n");
  flog("\033[41m ERROR: ".$type.": ".$msg." in ".$file." on line ".$line." ".getNow()."\033[0m\r\n");
  flog("\033[41m *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  *  * \033[0m\r\n");
}

