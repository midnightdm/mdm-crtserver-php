<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}
/* * * * * *
 * VesselsModel class
 * classes/vesselsmodel.php
 *
 */
class VesselsModel extends Firestore {

    public function __construct() {
        parent::__construct(['name' => 'Vessels']);
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
}