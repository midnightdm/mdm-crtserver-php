<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}
/* * * * * * * * *
 * Vessel Class
 * src/vessel.class.php
 * 
 */

class Vessel {
  public $tags = array(
    'vesselName', 'vesselID', 'vesselHasImage', 'vesselImageUrl', 'vesselType'
  );
  public $vesselName;  
  public $vesselID;
  public $vesselHasImage;
  public $vesselImageUrl;
  public $vesselType;
  public $daemonCallback;

  public function __construct($dataArr, $daemonCB) {
    $this->daemonCallback = $daemonCB;
    foreach($this->tags as $tag) {
      //echo '$'. 'dataArr[' . $tag . '] = ' . $dataArr[$tag];

      $this->$tag = $dataArr[$tag];
    }
    $this->saveIfNew();
  }

  public function saveIfNew() {
      
    if($this->daemonCallback->VesselsModel->vesselHasRecord($this->vesselID)) {
      return;
    }
    foreach($this->tags as $tag) {
      $data[$tag] = $this->$tag;
    }
    $data['vesselRecordAddedTS'] = time();
    $this->daemonCallback->VesselsModel->insertVessel($data);
  }
}