<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}
/* * * * * *
 * VesselsModel class
 * src/vesselsmodel.php
 *
 */
class VesselsModel extends Firestore {
  public $cs;
  public $image_base;

  public function __construct() {
      parent::__construct(['name' => 'Vessels']);
      $this->cs = new CloudStorage();
  }

  public function getVessel($vesselID) {
    //
    $document = $this->db->collection('Vessels')->document('mmsi'.$vesselID);
    $snapshot = $document->snapshot();
    if($snapshot->exists()) {
        return $snapshot->data();
    } else {
        return false;
    }
  }
  
  public function vesselHasRecord($vesselID) {
    //true if vessel exists
    return $this->db->collection('Vessels')->document('mmsi'.$vesselID)->snapshot()->exists();
  }

  public function getVesselLastDetectedTS($vesselID) {
    $document = $this->db->collection('Vessels')->document('mmsi'.$vesselID);
    $snapshot = $document->snapshot();
    if($snapshot->exists()) {
        return $snapshot->data()['vesselLastDetectedTS'];
    } else {
        return false;
    }
  }
  
  public function updateVesselLastDetectedTS($vesselID, $ts) {
    //In Vessels/:id document
    $document = $this->db->collection('Vessels')->document('mmsi'.$vesselID);
    $snapshot = $document->snapshot();
    if($snapshot->exists()) {
        $document->set(['vesselLastDetectedTS'=>$ts], ['merge'=>true]);
    } else {
        return false;
    }
    //And in Passages/All document
    $date = date("F j, Y", $ts);
    $document = $this->db->collection('Passages')->document('All');
    $snapshot = $document->snapshot();
    if($snapshot-exists()) {
      $document->set([$vesselID => ['date' => $date ]], ['merge'=> true]);
    }
  }
  
  public function insertVessel($dataArr) {
    //Add a new vessel record
    $this->db->collection('Vessels')->document('mmsi'.$dataArr['vesselID'])->set($dataArr);
  }

  //Added 10/31/21 
  function lookUpVessel($vesselID) {      
    //Test vessel id validity in range 200 000 000 - 899 999 999
    if(intval($vesselID) < 200000000 || intval($vesselID) > 899999999) {
      return ["error" => "Not a valid MMSI ID number."];
    }
    //See if Vessel data is available locally
    if($data = $this->vesselHasRecord($vesselID)) {
      //echo "Vessel found in database: " . var_dump($data);
      return ["error"=>"Vessel ID is already in the database."];
    }
    //Otherwise scrape data from a website
    $url = 'https://www.myshiptracking.com/vessels/';
    $q = $vesselID;
    $html = grab_page($url, $q);  
    //Edit segment from html string
    $startPos = strpos($html,'<div class="vessels_main_data cell">');
    $clip     = substr($html, $startPos);
    $endPos   = (strpos($clip, '</div>')+6);
    $len      = strlen($clip);
    $edit     = substr($clip, 0, ($endPos-$len));           
    //Use DOM Document class
    $dom = new DOMDocument();
    @ $dom->loadHTML($edit);
    //assign data gleened from mst table rows
    $data = [];
    $rows = $dom->getElementsByTagName('tr');
    //desired rows are 5, 11 & 12
    $data['vesselType'] = $rows->item(5)->getElementsByTagName('td')->item(1)->textContent;
    $data['vesselOwner'] = $rows->item(11)->getElementsByTagName('td')->item(1)->textContent;
    $data['vesselBuilt'] = $rows->item(12)->getElementsByTagName('td')->item(1)->textContent;
  
    //Try for image
    try {
      if($this->cs->scrapeImage($vesselID)) {
        //$endPoint = getEnv('AWS_ENDPOINT');
        $base = $this->cs->image_base;
        $data['vesselHasImage'] = true;
        $data['vesselImageUrl'] = $base.'images/vessels/mmsi' . $vesselID.'.jpg';      
      } else {
        $data['vesselHasImage'] = false;
        $data['vesselImageUrl'] = $this->cs->no_image;
        //'https://storage.googleapis.com/www.clintonrivertraffic.com/images/vessels/no-image-placard.jpg';
      }
    }
    catch (exception $e) {
      //
      $data['vesselHasImage'] = false;
      $data['vesselImageUrl'] = $this->cs->no_image;
    }
    //data gleened locally by daemon needs done remotely in manual admin add
    $data['vesselRecordAddedTS'] = time();
    $data['vesselWatchOn']  = false;
    $data['vesselID']       = $vesselID;
    $name                   = $rows->item(0)->getElementsByTagName('td')->item(1)->textContent;
    //Test for no data returned which is probably bad vesselID 
    if($name=="---") {
      return ["error"=>"The provided Vessel ID was not found."];
    }
    $data['vesselCallSign'] = $rows->item(4)->getElementsByTagName('td')->item(1)->textContent;
    $size                   = $rows->item(6)->getElementsByTagName('td')->item(1)->textContent;
    $data['vesselDraft']    = $rows->item(8)->getElementsByTagName('td')->item(1)->textContent;   
    
    //Cleanup parsing needed for some data
    //$name     = trim(substr($name, $startPos)); //Remove white spaces
    $name     = str_replace(',', '', $name);   //Remove commas (,)
    $name     = str_replace('.', ' ', $name); //Add space after (.)
    $name     = str_replace('  ', ' ', $name); //Remove double space
    $name     = ucwords(strtolower($name)); //Change capitalization
    $data['vesselName'] = $name;
    //Format size string into seperate length and width
    if($size=="---") {
      $data['vesselLength'] = "---";
      $data['vesselWidth'] = "---";
    } else if(strpos($size, "x") === false) {
      $data['vesselLength'] = $size;
      $data['vesselWidth'] = $size;
    } else {
      $sizeArr = explode(" ", $size); 
      $data['vesselWidth'] = trim($sizeArr[2])."m";
      $data['vesselLength'] = trim($sizeArr[0])."m";
    }  
    return $data;
  } 

  public function resetAddVessel() {
    $this->db->collection('Passages')
    ->document('Admin')
    ->set(['vesselError'=> false, 'vesselStatusMsg' => "Ready for your input."],['merge'=>true]);
  }    

  public function testForAddVessel() {
    $document = $this->db->collection('Passages')
        ->document('Admin');
    $snapshot = $document->snapshot();
    if($snapshot->exists()) {
      $data = $snapshot->data();
      if($data['vesselStatusMsg']=="Process pending. This could take up to 3 minutes.") {
        return $data['vesselID'];
      }
    } else {
      return false;
    }
  }

  public function reportVesselError($data) {
    $this->db->collection('Passages')
    ->document('Admin')
    ->set(['vesselError'=> true, 'vesselStatusMsg' => $data['error']],['merge'=>true]);
  }
}

