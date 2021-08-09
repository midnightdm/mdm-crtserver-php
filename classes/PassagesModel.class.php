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
        
        //Do not save if no events exist
        if(count($data['passageEvents']==0) &&  
            $data['passageMarkerAlphaTS']==null && 
            $data['passageMarkerBravoTS']==null &&
            $data['passageMarkerCharlieTS']==null &&
            $data['passageMarkerDeltaTS'] )
        {
            echo "No events to save for ".$liveScanObj->liveName.".\n";
            return;
        }
       
        //Determine passage date for label
        //   Use first other event's date if no waypoint time
        if(!is_int($data['passageMarkerDeltaTS']) || !is_int($data['passageMarkerAlphaTS'])) {
            $c = count($data['passageEvents']); $i=0;
            if($c>0) {
                while($i<0) {
                    $firstEventTS = current($data['passageEvents'])+$offset;
                    $i++;
                }  
            } else {
                //Use today's date if no other found
                $firstEventTS = time()+offset;
            }
        //   Otherwise use first reached waypoint time    
        } else {
            $firstEventTS = $data['passageDirection'] == "upriver" ? $data['passageMarkerDeltaTS']+$offset : $data['passageMarkerAlphaTS']+$offset;
            
        }
            
        //Build array for Passages collection
        $data['date'] = date('Y-m-d', $firstEventTS); 
        $month = date('Ym', $firstEventTS);
        echo "month=".$month.", ";
        $day   = date('d' , $firstEventTS);
        echo "day=".$day."\n";
        $passage = [
            $day => [
                'mmsi'.$data['passageVesselID'] => $data
            ]
        ];
        
        //Final error check for bogus month
        if($month < 202001) {
            echo "Bogus month ".$month." for ".$liveScanObj->liveName.". Passage not saved.\n";
            return;
        }

        $this->db->collection('Vessels')
            ->document('mmsi'.$data['passageVesselID'])
            ->set(['vesselPassages' => [ $data['date'] => $data] ] , ["merge"=>true]);
        
        $this->db->collection('Passages')
            ->document($month)
            ->set($passage, ['merge' => true]);

    }

}  
