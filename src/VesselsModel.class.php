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
  public $videoIsPassing;

  public function __construct() {
      parent::__construct(['name' => 'Vessels']);
      //flog("INIT: VesselsModel\n");
      $this->cs = new CloudStorage('VesselsModel');
      $this->videoIsPassing = $this->getVideoIsPassing();
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
        $data = $snapshot->data();
        $passages = [];
        if(isset($data['vesselPassages'])) {
          foreach($data['vesselPassages'] as $date=>$obj) {
            $passages[] = $date;
          }
          rsort($passages);
          $dt = date_create($passages[0]);
          $dtStr = $dt->format('D M j, Y');
          flog("getVesselLastDetectedTS() ".$passages[0]." ".$dtStr);
          return [$dt->getTimeStamp(), $dtStr];
        } else {  
          flog( "\033[41m *  VesselsModel::getVesselLastDetectedTS() failed to find TS data.  * \033[0m\r\n"); 
          return false;
        }      
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
    if($snapshot->exists()) {
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
    //Otherwise scrape data from a website UPDATED 7/13/22
    $url = 'https://www.marinetraffic.com/en/ais/details/ships/mmsi:';
    $q = $vesselID;
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


    /*/Use DOM Document class
    $dom = new DOMDocument();
    @ $dom->loadHTML($html);
    */
    //assign data gleened from mst table rows
    $data = [];
    $data['vesselType'] = $type;
    //$data['vesselOwner'] = $rows->item(11)->getElementsByTagName('td')->item(1)->textContent;
    //$data['vesselBuilt'] = $rows->item(12)->getElementsByTagName('td')->item(1)->textContent;
      
    //Try for image
    try {
      if($this->cs->scrapeImage($vesselID)) {
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
      $data['vesselHasImage'] = false;
      $data['vesselImageUrl'] = $this->cs->no_image;
    }
    //data gleened locally by daemon needs done remotely in manual admin add
    $data['vesselRecordAddedTS'] = time();
    $data['vesselWatchOn']  = false;
    $data['vesselID']       = $vesselID;
    
    //Test for no data returned which is probably bad vesselID 
    if($name=="---") {
      return ["error"=>"The provided Vessel ID was not found."];
    }
 
    
    //Cleanup parsing needed for some data
    //$name     = trim(substr($name, $startPos)); //Remove white spaces
    $name     = str_replace(',', '', $name);   //Remove commas (,)
    $name     = str_replace('.', ' ', $name); //Add space after (.)
    $name     = str_replace('  ', ' ', $name); //Remove double space
    $name     = ucwords(strtolower($name)); //Change capitalization
    $data['vesselName'] = $name;

    return $data;
  } 


  public function reportVesselError($data) {
    $this->db->collection('Passages')
    ->document('Admin')
    ->set(['vesselError'=> true, 'vesselStatusMsg' => $data['error'], 'formAwaitingReset'=> true],['merge'=>true]);
  }


  public function setVideoIsPassing($state) {
    if(!is_bool($state)) {
      trigger_error("      VesselsModel::setVideoIsPassing value is not boolean.");
      return;
    }
    if($state !== $this->videoIsPassing) {
      $this->db->collection('Passages')
      ->document('Admin')
      ->set(['videoIsPassing'=> $state], ['merge'=>true]);
      $this->videoIsPassing = $state;
      flog("      VesselsModel::setVideoIsPassing() = UPDATED\n");
    } else {
      flog("      VesselsModel::setVideoIsPassing() = UNCHANGED\n");
    }
    
  }

  public function getVideoIsPassing() {
    $document = $this->db->collection('Passages')->document('Admin');
    $snapshot = $document->snapshot();
    if($snapshot->exists()) {
      $data = $snapshot->data();
      //Returns boolean value
      return $data["videoIsPassing"];
    }
  }
}

