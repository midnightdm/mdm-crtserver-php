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
      $vesselID = strval($liveScanObj->liveVesselID);
      $data['passageVesselID'] = $vesselID;
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
               "id" => $vesselID,
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
      $vesselPayload = [
         'passageVesselID' => $vesselID,
         'date' => $data['date'],
         'passageData' => $data
      ];
      $responseMongo = post_page($url1, $vesselPayload);
      if($responseMongo === false || str_contains(strtolower(strval($responseMongo)), 'error')) {
         flog("\033[41m PassagesModel::savePassage vessel endpoint error for mmsi$vesselID: ".strval($responseMongo)."\033[0m\n");
      } else {
         flog( "\033[33m           Passage records for $liveScanObj->liveName vessel saved to Mongo ".getNow()."\n                \033[0m\n");
      }

      $url2 = $this->apiUrl."/passagelogs/month";
      flog( "\033[33m           Save month $month day $day ".getNow()."\033[0m\n");
      $monthPassageData = $data;
      $monthPassageData['passageVesselID'] = intval($vesselID);
      $monthPayload = [
         'month' => $month,
         'day' => intval($day),
         'passageVesselID' => intval($vesselID),
         'passageData' => $monthPassageData
      ];
      $responseMongo = post_page($url2, $monthPayload);
      if($responseMongo === false || str_contains(strtolower(strval($responseMongo)), 'error')) {
         flog("\033[41m PassagesModel::savePassage month endpoint error for mmsi$vesselID: ".strval($responseMongo)."\033[0m\n");
      }

      $url3 = $this->apiUrl."/passagelogs/last";
      $lastPayload = [
         'passageVesselID' => $vesselID,
         'passageSummary' => $model
      ];
      $responseMongo = post_page($url3, $lastPayload);
      if($responseMongo === false || str_contains(strtolower(strval($responseMongo)), 'error')) {
         flog("\033[41m PassagesModel::savePassage last endpoint error for mmsi$vesselID: ".strval($responseMongo)."\033[0m\n");
      }

   }

}  
