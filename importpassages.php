<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}

include_once('classes/Firestore.class.php');

include_once('classes/passagesdata.php'); //$vess and $pass arrays
include_once('classes/crtfunctions_helper.php');

/* * * * * * * * *
 * ImportPassagesModel Class
 * daemon/classes/AlertsModel.class.php
 * 
 */


class ExportPassagesModel extends Firestore {
    public function __construct() {
        parent::__construct(['name' => 'Vessels']);
    }

    public function run($vessels) {
        foreach($vessels as $v) {
            $vess = [
                "vesselBuilt" => $v[10],
                "vesselCallSign" => $v[2],
                "vesselDraft" => $v[8],
                "vesselHasImage" => $v[6],
                "vesselID" => $v[1],
                "vesselImageUrl" => $v[7],
                "vesselLastDetectedTS" => $v[13],
                "vesselLength" => $v[4],
                "vesselName" => $v[0],
                "vesselOwner" => $v[9],
                "vesselRecordAddedTS" => $v[12],
                "vesselType" => $v[3],
                "vesselWatchOn" => $v[11],
                "vesselWidth" => $v[5]
            ];

            $this->db->collection('Vessels')->document('mmsi'.$v[1])->set($vess, ['merge'=> true]);

        }
    }

    public function run2($passages) {
        foreach($passages as $p) {
            $ts = $p[2] == "upriver" ? $p[6] : $p[3]; //Time 1st waypoint was crossed
            $offset = getTimeOffset($ts); 
            $pass = [
                "passageVesselID" => $p[1],
                "passageDirection" => $p[2],
                "passageMarkerAlphaTS" => $p[3],
                "passageMarkerBravoTS" => $p[4],
                "passageMarkerCharlieTS" => $p[5],
                "passageMarkerDeltaTS" => $p[6],
                "passageEvents" => [],
                "date" =>  date('Y-m-d', $ts+$offset)
            ];
            $this->db->collection('Vessels')->document('mmsi'.$p[1])->collection('Passages')->document($pass['date'])->set($pass, ["merge" => true]);
        }
    }
}

echo "Starting ".date('c')."\n";
$model = new ExportPassagesModel();
$model->run($vess);
//$model->run2($pass);
echo "Done ".date('c');
?>