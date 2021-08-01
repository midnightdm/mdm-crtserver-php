    <?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}
/* * * * * *
 * LiveScanModel class
 * classes/LiveScanModel.class.php
 *
 */
class LiveScanModel extends Firestore {

    public function __construct() {
        parent::__construct(['name' => 'LiveScan']);
    }

    public function getAllLiveScans() {
        //Write
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
        $this->db->collection('LiveScan')->document('mmsi'.$live['liveVesselID'])->set($live);
    }

    public function updateLiveScan($live){
        $this->db->collection('LiveScan')
            ->document('mmsi'.$live['liveVesselID'])
            ->set($live, ["merge"=> true]);
    }

    public function deleteLiveScan($vesselID) {
        $document = $this->db->collection('LiveScan')->document('mmsi'.$vesselID);
        $snapshot = $document->snapshot();
        if($snapshot->exists()) {
            $document->delete();
            return true;
        } else {
            echo "Couldn't delete vesselID ".$vesselID. " from LiveScans.\n";
            return false;
        }
    }
}