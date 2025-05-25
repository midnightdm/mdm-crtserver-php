    <?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}
/* * * * * *
 * LiveScanModel class
 * src/LiveScanModel.class.php
 *
 */
class LiveScanModel extends Firestore {

  public function __construct() {
      parent::__construct(['name' => 'LiveScan']);
      //flog("INIT: LiveScanModel\n");
  }

public function getAllLiveScans() {
    //Firestore grab
   //  $documents = $this->db->collection('LiveScan')->documents();
   //  $scans = []; 
   //  foreach($documents as $document) {
   //      if($document->exists()) {
   //          $scans[$document->id()] = $document->data();
   //      }
   //  }
    //MongoDB grab
    $mongoScans = [];
    $jsonResponse = grab_page($this->apiUrl."/live/json");
    if($jsonResponse['http_code'] == 200) {
      $collection = json_decode($jsonResponse['body'], true);
    } else {
      $collection = null;
    }
    //Ensure associative array 
    if(is_array($collection)) {
       foreach($collection as $document) {
           $mongoScans[$document['_id']] = $document;
       }
    } else {
       errorHandler(501,"Error decoding JSON response from MongoDB API", "LiveScanModel.class.php", 15); 
    }
    //return $scans;
    return $mongoScans;
  }


  public function insertLiveScan($live) {
    //flog("insertLiveScan(live) DATA=". $live. "EOF"); //Test Only
    //$this->db->collection('LiveScan')->document('mmsi'.$live['liveVesselID'])->set($live);

    //MongoDB write
    $url1 = $this->apiUrl."/live";
    $responseMongo = post_page($url1, 
    [
      'liveVesselID' => $live['liveVesselID'],
      'liveData'     => $live
   ]);
   //tlog('insertLiveScan: '.json_encode(['liveData' => $live], JSON_FORCE_OBJECT));
   //flog('MongoDB response: '. print_r($responseMongo) . "\n");
  }

  public function updateLiveScan($live){
    //$this->db->collection('LiveScan')
      //   ->document('mmsi'.$live['liveVesselID'])
      //   ->set($live, ["merge"=> true]);
      //MongoDB update
      $url1 = $this->apiUrl."/live/".$live['liveVesselID'];
      $responseMongo = put_page($url1, ['liveData' => $live]);
      //tlog('updateLiveScan: '.json_encode($responseMongo));
      //tlog('updateLiveScan: '.json_encode(['liveData' => $live]));
   }

  //Replaced in AdminTriggersModel
  public function resetExit() {
    $this->db->collection('Passages')
    ->document('Admin')
    ->set(['exit'=> false],['merge'=>true]);
  }    
 

  public function updateLiveScanLength($len) {
    $dat = [
      "liveScanLength"  => $len["clinton"], 
      "liveScanLengthQC"=> $len["qc"]
    ];
    $this->db
      ->collection('Passages')
      ->document('Admin')
      ->set($dat, ["merge"=> true]);  
  }

  public function deleteLiveScan($vesselID) {
    //  $ts  = time();  
    //  $now = date('n/j/Y, g:i:s A', $ts);
    //  $day = date('w', $ts);

    //MongoDB delete    
    $responseMongo = delete_page($this->apiUrl."/live/".$vesselID);
    //tlog('updateLiveScan: '.json_encode($responseMongo)); 
    return true;    
    //Firestore delete
   //  $document = $this->db->collection('LiveScan')->document('mmsi'.$vesselID);
   //  $snapshot = $document->snapshot();
   //  if($snapshot->exists()) {
   //      $document->delete();
   //      return true;
   //  } else {
   //      flog( "Couldn't delete vesselID ".$vesselID. " from LiveScans.\n");
   //      return false;
   //  }


  }

}