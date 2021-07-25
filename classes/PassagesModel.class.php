<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}
/* * * * * *
 * PassagesModel class
 * dclasses/passagesmodel.class.php
 *
 */

class PassagesModel extends Firestore {

    public function __construct() {
        parent::__construct(['name' => 'Vessels']);
    }

    public function savePassage($liveScanObj) {
        $data['passageVesselID'] = $liveScanObj->liveVesselID;
        $data['passageDirection'] = $liveScanObj->liveDirection;
        $data['passageMarkerAlphaTS'] = $liveScanObj->liveMarkerAlphaTS;
        $data['passageMarkerBravoTS'] = $liveScanObj->liveMarkerBravoTS;
        $data['passageMarkerCharlieTS'] = $liveScanObj->liveMarkerCharlieTS;
        $data['passageMarkerDeltaTS'] = $liveScanObj->liveMarkerDeltaTS;
        $data['passageEvents'] = $liveScanObj->liveLocation->events;
        $offset = getTimeOffset();
        $data['date'] = $data['passageDirection'] == "upriver" ?  date('Y-m-d', $data['passageMarkerDeltaTS']+$offset) : date('Y-m-d', $data['passageMarkerAlphaTS']+offset);
        $this->db->collection('Vessels')->document('mmsi'.$data['passageVesselID'])->collection('Passages')->document($data['date'])->set($data);

    }

}  
