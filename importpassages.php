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

    public function run3($passages) {
        $yyyymm = [];
        //Build array with all passages
        foreach($passages as $p) {
            //correct for 0 ts
            $c = $this->timeCorrector($p[3], $p[4], $p[5], $p[6]);
            $ts = $p[2] == "upriver" ? $c[3] : $c[0]; //Time 1st waypoint was crossed
            $offset = getTimeOffset($ts); 
            $ts+= $offset;
            $pmonth = date('Ym', $ts);
            $pdate  = date('d', $ts);        
            if(!isset($yyyymm[$pmonth])) {
                $yyyymm[$pmonth] = [];
            }
            if(!isset($yyyymm[$pmonth][$pdate])) {
                $yyyymm[$pmonth][$pdate] = [];
            }


            $yyyymm[$pmonth][$pdate]['mmsi'.$p[1]] = [
                "passageVesselID" => $p[1],
                "passageDirection" => $p[2],
                "passageMarkerAlphaTS" => $c[0],
                "passageMarkerBravoTS" => $c[1],
                "passageMarkerCharlieTS" => $c[2],
                "passageMarkerDeltaTS" => $c[3],
                "passageEvents" => [],
                "date" =>  date('Y-m-d', $ts+$offset)
            ];
        }
        //Loop through array to write to db
        foreach($yyyymm as $month => $value) {
            //For Passage collection
            $this->db->collection('Passages')->document(strval($month))->set($value, ["merge" => true]);
            //For Vessels collection
            $this->db->collection('Vessels')->document('mmsi'.$p[1])->collection('Passages')->document($pass['date'])->set($pass, ["merge" => true]);
        }
    }

    public function run4($passages) {
        $mmsi = [];
        //Build array with all passages
        foreach($passages as $p) {
            //correct for 0 ts
            $c = $this->timeCorrector($p[3], $p[4], $p[5], $p[6]);
            $ts = $p[2] == "upriver" ? $c[3] : $c[0]; //Time 1st waypoint was crossed
            $offset = getTimeOffset($ts); 
            $ts+= $offset;
            $pdate  = date('Y-m-d', $ts);        
            if(!isset($mmsi['mmsi'.$p[1]])) {
                $mmsi['mmsi'.$p[1]]["vesselPassages"] = [];
            }
            $mmsi['mmsi'.$p[1]]["vesselPassages"][$pdate] = [
                "passageVesselID" => $p[1],
                "passageDirection" => $p[2],
                "passageMarkerAlphaTS" => $c[0],
                "passageMarkerBravoTS" => $c[1],
                "passageMarkerCharlieTS" => $c[2],
                "passageMarkerDeltaTS" => $c[3],
                "passageEvents" => [],
                "date" =>  date('Y-m-d', $ts+$offset)
            ];
        }
        //Loop through array to write to db
        foreach($mmsi as $vessel => $value) {
            //For Passage collection
            //$this->db->collection('Passages')->document(strval($month))->set($value, ["merge" => true]);
            //For Vessels collection
            $this->db->collection('Vessels')->document($vessel)->set($value, ["merge" => true]);
        }
    }

    public function timeCorrector($a, $b, $c, $d) {
        $arr = [$a, $b, $c, $d];
        if(!in_array(0, $arr)) {
            return $arr;
        }
        if($d==0) {
            if($a > 1599688000) {
                $b = $a+1200;
                $c = $b+1200;
                $d = $c+1200;
            } elseif($b > 1599688000) {
                $a = $b-1200;
                $c = $b+1200;
                $d = $c+1200;
            } elseif($c > 1599688000) {
                $b = $c-1200;
                $a = $b-1200;
                $d = $c+1200;
            }
        } elseif($c==0) {          
            if($a > 1599688000) {
                $b = $a+1200;
                $c = $b+1200;
                $d = $c+1200;
            } elseif($b > 1599688000) {
                $a = $b-1200;
                $c = $b+1200;
                $d = $c+1200;
            } elseif($d > 1599688000) {
                $c = $d-1200;
                $b = $c-1200;
                $a = $b-1200;
            }
             
        } elseif ($b==0) {
            if($a > 1599688000) {
                $b = $a+1200;
                $c = $b+1200;
                $d = $c+1200;
            } elseif($c > 1599688000) {
                $b = $c-1200;
                $a = $b-1200;
                $d = $c+1200;
            } elseif($d > 1599688000) {
                $c = $d-1200;
                $b = $c-1200;
                $a = $b-1200;
            }
        } elseif($a==0) {
            if($c > 1599688000) {
                $b = $c-1200;
                $a = $b-1200;
                $d = $c+1200;
            } elseif($b > 1599688000) {
                $a = $b-1200;
                $c = $b+1200;
                $d = $c+1200;
            } elseif($d > 1599688000) {
                $c = $d-1200;
                $b = $c-1200;
                $a = $b-1200;
            }
        }
        return [$a, $b, $c, $d];

    }
}

$start = time();
echo "Starting ".date('c')."\n";
$model = new ExportPassagesModel();
$model->run($vess);
$model->run4($pass);
$finish = time();

echo "Done ".date('c')." in ".($finish-$start)." seconds\n";
?>