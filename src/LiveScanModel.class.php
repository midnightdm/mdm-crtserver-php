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
    $documents = $this->db->collection('LiveScan')->documents();
    $scans = [];
    foreach($documents as $document) {
        if($document->exists()) {
            $scans[$document->id()] = $document->data();
        }
    }
    return $scans;
  }


  public function insertLiveScan($live) {
    //flog("insertLiveScan(live) DATA=". $live. "EOF"); //Test Only
    $this->db->collection('LiveScan')->document('mmsi'.$live['liveVesselID'])->set($live);

    //MongoDB write
    $url1 = $this->apiUrl."/live";
    $responseMongo = post_page($url1, 
    [
      'liveVesselID' => $live['liveVesselID'],
      'liveData'     => $live
   ]);
   flog('MongoDB response: '. $responseMongo . "\n");
  }

  public function updateLiveScan($live){
    $this->db->collection('LiveScan')
        ->document('mmsi'.$live['liveVesselID'])
        ->set($live, ["merge"=> true]);

      //MongoDB write
      $url1 = $this->apiUrl."/live";
      $responseMongo = post_page($url1, 
      [
         'liveVesselID' => $live['liveVesselID'],
         'liveData'     => $live
      ]);
      flog('MongoDB response: '. $responseMongo['http_code']. "\n");
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
    $ts  = time();  
    $now = date('n/j/Y, g:i:s A', $ts);
    $day = date('w', $ts);     
    $document = $this->db->collection('LiveScan')->document('mmsi'.$vesselID);
    $snapshot = $document->snapshot();
    if($snapshot->exists()) {
        $document->delete();
        return true;
    } else {
        flog( "Couldn't delete vesselID ".$vesselID. " from LiveScans.\n");
        return false;
    }
  }

}