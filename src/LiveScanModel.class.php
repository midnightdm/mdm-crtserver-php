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
    }

    public function updateLiveScan($live){
        $this->db->collection('LiveScan')
            ->document('mmsi'.$live['liveVesselID'])
            ->set($live, ["merge"=> true]);
    }

    public function resetExit() {
        $this->db->collection('Passages')
        ->document('Admin')
        ->set(['exit'=> false],['merge'=>true]);
    }    

    public function testExit() {
        $document = $this->db->collection('Passages')
            ->document('Admin');
        $snapshot = $document->snapshot();
        if($snapshot->exists()) {
            $data = $snapshot->data();
            if($data['exit']==true) {
                return true;
            }
            return false;   
        }
        return false;
    }

    public function deleteLiveScan($vesselID) {
      $ts  = time();  
      $now = date('n/j/Y, g:i:s A', $ts);
      $day = date('w', ts);     
      $document = $this->db->collection('LiveScan')->document('mmsi'.$vesselID);
      $snapshot = $document->snapshot();
      if($snapshot->exists()) {
          $document->delete();
          //Record to 'Deletes' collection
          $dat = ["date"=> $now, "day"=>$day, "id"=>$vesselID, "ts"=> $ts ];
          $d2 = $this->db->collection('Deletes')->document()->set($dat);
          return true;
      } else {
          flog( "Couldn't delete vesselID ".$vesselID. " from LiveScans.\n");
          return false;
      }
    }
}