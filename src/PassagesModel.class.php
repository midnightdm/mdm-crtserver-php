<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}
/* * * * * *
 * PassagesModel class
 * dsrc/passagesmodel.class.php
 *
 */

class PassagesModel extends Firestore {

    public function __construct() {
        parent::__construct(['name' => 'Vessels']);
    }

    public function savePassage($liveScanObj) {
        $data['passageVesselID'] = $liveScanObj->liveVesselID;
        //$data['vesselName'] = $liveScanObj->liveName;
        //$data['vesselImage'] = $liveScanObj->liveVessel->vesselImageUrl;
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
            $data['passageMarkerDeltaTS'] ==null)
        {
            flog( "No events to save for ".$liveScanObj->liveName.".\n");
            return;
        }
       

        //Default to today's date if no other found
        $firstEventTS = time()+$offset;
        //Determine passage date for label
        //   Use first other event's date if no waypoint time
        if(!is_int($data['passageMarkerDeltaTS']) || !is_int($data['passageMarkerAlphaTS'])) {
            $c = count($data['passageEvents']); $i=0;
            if($c>0) {
                while($i<0) {
                    $key = key($data['passageEvents']) + $offset;
                    if($key > 100000000) {
                        $firstEventTS = $key;
                        break;
                    }
                    $i++;
                }  
            } 
        //Otherwise use first reached waypoint time    
        } else {
            $key = $data['passageDirection'] == "upriver" ? $data['passageMarkerDeltaTS']+$offset : $data['passageMarkerAlphaTS']+$offset;  
        }
        if($key > 100000000) {
            $firstEventTS = $key;
        }
            
        //Build array for Passages by Date collection with added data
        $data['date'] = date('Y-m-d', $firstEventTS); 
        $data['vesselName'] = $liveScanObj->liveName;
        $data['vesselImageUrl'] = $liveScanObj->liveVessel->vesselImageUrl;
        
        $month = date('Ym', $firstEventTS);
        flog( "month=".$month.", ");
        $day   = date('d' , $firstEventTS);
        flog( "day=".$day."\n");
        $passage = [
            $day => [
                'mmsi'.$data['passageVesselID'] => $data
            ]
        ];

        //Build array for Passages All document update
        $humanDate = date('M d, Y', $firstEventTS);
        $model = [ $data['passageVesselID'] => [
                "date" => $humanDate,
                "id" => $liveScanObj->liveVesselID,
                "image" => $liveScanObj->liveVessel->vesselImageUrl,
                "name"  => $liveScanObj->liveName
             ]
        ];
        
        //Final error check for bogus month
        if($month < 202001) {
            flog( "Bogus month ".$month." for ".$liveScanObj->liveName.". Passage not saved.\n");
            return;
        }

        $this->db->collection('Vessels')
            ->document('mmsi'.$data['passageVesselID'])
            ->set(['vesselPassages' => [ $data['date'] => $data] ] , ['merge' => true]);
        
        $this->db->collection('Passages')
            ->document($month)
            ->set($passage, ['merge' => true]);
        
        $this->db->collection('Passages')
            ->document('All')
            ->set($model, ['merge' => true]);

        flog( "\033[33m Passage records saved for $liveScanObj->liveName ".getNow()."\033[0m\n");

        

    }

}  
