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
        //flog("INIT: PassagesModel\n");
    }

    public function savePassageClinton($liveScanObj) {
        $data['passageVesselID'] = $liveScanObj->liveVesselID;
        $data['passageDirection']       = $liveScanObj->liveDirection;
        $data['passageMarkerAlphaTS']   = $liveScanObj->liveMarkerAlphaTS;
        $data['passageMarkerBravoTS']   = $liveScanObj->liveMarkerBravoTS;
        $data['passageMarkerCharlieTS'] = $liveScanObj->liveMarkerCharlieTS;
        $data['passageMarkerDeltaTS']   = $liveScanObj->liveMarkerDeltaTS;
        if($liveScanObj->liveLocation instanceof Location) {
          $data['passageEvents'] = $liveScanObj->liveLocation->events;
          foreach($liveScanObj->liveLocation->events as $event=>$ts) {
            if(str_starts_with($event, 'alpha')) {
              $data['passageMarkerAlphaTS']   = $ts;
            }
            if(str_starts_with($event, 'bravo')) {
              $data['passageMarkerBravoTS']   = $ts;
            }
            if(str_starts_with($event, 'charlie')) {
              $data['passageMarkerCharllieTS']   = $ts;
            }
            if(str_starts_with($event, 'delta')) {
              $data['passageMarkerDeltaTS']   = $ts;
            }
          }
        } else{
          $data['passageEvents'] = [];
        }
        $offset = getTimeOffset();
        
        //Do not save if no events exist
        if(count($data['passageEvents'])==0 )
        {
            flog( "No Clinton events to save for ".$liveScanObj->liveName.".\n");
            return;
        }
       

        //Default to today's date if no other found
        $firstEventTS = time()+$offset;
        //Determine passage date for label
        //   Use first other event's date if no waypoint time
        if(!is_int($data['passageMarkerDeltaTS']) || !is_int($data['passageMarkerAlphaTS'])) {
          foreach($data['passageEvents'] as $event => $ts) {
            //Test validity of ts value
            if($ts>100000000) {
              $firstEventTS = $ts+$offset;
              break;
            } 
          }
          
        //Otherwise use first reached waypoint time    
        } else {
            $firstEventTS = $data['passageDirection'] == "upriver" ? intval($data['passageMarkerDeltaTS'])+$offset : intval($data['passageMarkerAlphaTS'])+$offset;
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

        flog( "\033[33m Clinton passage records saved for $liveScanObj->liveName ".getNow()."\033[0m\n");
    }



    public function savePassageQC($liveScanObj) {
      $data['passageVesselID'] = $liveScanObj->liveVesselID;
      $data['passageDirection']       = $liveScanObj->liveDirection;
      if($liveScanObj->liveLocation instanceof Location) {
        $data['passageEvents'] = $liveScanObj->liveLocation->events;
        foreach($liveScanObj->liveLocation->events as $event=>$ts) {
          if(str_starts_with($event, 'echo')) {
            $data['passageMarkerEchoTS']   = $ts;
          }
          if(str_starts_with($event, 'foxtrot')) {
            $data['passageMarkerFoxtrotTS']   = $ts;
          }
          if(str_starts_with($event, 'golf')) {
            $data['passageMarkerGolfTS']   = $ts;
          }
          if(str_starts_with($event, 'hotel')) {
            $data['passageMarkerHotelTS']   = $ts;
          }
        }
      } else{
        $data['passageEvents'] = [];
        $data['passageMarkerEchoTS']   = $liveScanObj->liveMarkerEchoTS;
        $data['passageMarkerFoxtrotTS']   = $liveScanObj->liveMarkerFoxtrotTS;
        $data['passageMarkerGolfTS'] = $liveScanObj->liveMarkerGolfTS;
        $data['passageMarkerHotelTS']   = $liveScanObj->liveMarkerHotelTS;
      }
      $offset = getTimeOffset();
      
      //Do not save if no events exist
      if(count($data['passageEvents'])==0)  
         
      {
          flog( "No QC events to save for ".$liveScanObj->liveName.".\n");
          return;
      }
     

      //Default to today's date if no other found
      $firstEventTS = time()+$offset;
      //Determine passage date for label
      //   Use first other event's date if no waypoint time
      if(!is_int($data['passageMarkerGolfTS']) || !is_int($data['passageGolfTS'])) {
        foreach($data['passageEvents'] as $event => $ts) {
          //Test validity of ts value
          if($ts>100000000) {
            $firstEventTS = $ts+$offset;
            break;
          } 
        }
        
      //Otherwise use first reached waypoint time    
      } else {
          $firstEventTS = $data['passageDirection'] == "upriver" ? intval($data['passageMarkerHotelTS'])+$offset : intval($data['passageMarkerEchoTS'])+$offset;
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
      
      $this->db->collection('PassagesQC')
          ->document($month)
          ->set($passage, ['merge' => true]);
      
      $this->db->collection('PassagesQC')
          ->document('All')
          ->set($model, ['merge' => true]);

      flog( "\033[33m QC passage records saved for $liveScanObj->liveName ".getNow()."\033[0m\n");
  }


}  
