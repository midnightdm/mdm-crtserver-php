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


   public function savePassage($liveScanObj) {
      $data['passageVesselID'] = $liveScanObj->liveVesselID;
      $data['passageDirection']       = $liveScanObj->liveDirection;
      if($liveScanObj->liveLocation instanceof Location) {
      $data['passageEvents'] = $liveScanObj->liveLocation->events;
      } else{
      $data['passageEvents'] = [];
      }
      $offset = getTimeOffset();
      
      //Do not save if no events exist
      if(count($data['passageEvents'])==0 )
      {
         flog( "No events to save for ".$liveScanObj->liveName.".\n");
         return;
      }
   

      //Default to today's date if no other found
      $firstEventTS = time()+$offset;
      //Determine passage date for label
      //   Use first event's date 
      foreach($data['passageEvents'] as $event => $ts) {
         //Test validity of ts value
         if($ts>100000000) {
               $firstEventTS = $ts+$offset;
               break;
         } 
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
      $model =  [
               "date" => $humanDate,
               "id" => $liveScanObj->liveVesselID,
               "image" => $liveScanObj->liveVessel->vesselImageUrl,
               "name"  => $liveScanObj->liveName,
               "type"  => $liveScanObj->liveVessel->vesselType
      ];
      
      //Final error check for bogus month
      if($month < 202001) {
         flog( "Bogus month ".$month." for ".$liveScanObj->liveName.". Passage not saved.\n");
         return;
      }

      //Send data to Firestore *** DISABLED Mar 28, 2025 **
      /*
      $this->db->collection('Vessels')
         ->document('mmsi'.$data['passageVesselID'])
         ->set(['vesselPassages' => [ $data['date'] => $data] ] , ['merge' => true]);
      
      $this->db->collection('Passages')
         ->document($month)
         ->set($passage, ['merge' => true]);
      
      $this->db->collection('Passages')
         ->document('All')
         ->set($model, ['merge' => true]);

      flog( "\033[33m           Passage records saved to Firestore for $liveScanObj->liveName ".getNow()."\033[0m\n");
      */

      //Send same data to MongoDb through API
      $url1 = $this->apiUrl."/vessels/passage";
      $responseMongo = post_page($url1, 
         [
            'passageVesselID' => $data['passageVesselID'],
            'date' => $data['date'],
            'passageData'=>  $data 
         ]);
         flog( "\033[33m           Passage records for $liveScanObj->liveName vessel saved to Mongo ".getNow()."\n                \033[0m\n");

      $url2 = $this->apiUrl."/passagelogs/month";
      flog( "\033[33m           Save month $month day $day ".getNow()."\033[0m\n");
      $responseMongo = post_page($url2,
         [
            'month' => $month,
            'day' => $day,
            'passageVesselID' => $data['passageVesselID'],
            'passageData'=> $data
         ]);

      $url3 = $this->apiUrl."/passagelogs/last";
      $responseMongo = post_page($url3,
         [
            'passageVesselID' => $data['passageVesselID'],
            'passageSummary'=> $model
         ]);

   }

}  
