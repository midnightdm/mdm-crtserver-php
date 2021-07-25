<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}
/* * * * * * * * *
 * Vessel Class
 * classes/vessel.class.php
 * 
 */

class Vessel {
  public $tags = array(
    'vesselName', 'vesselID', 'vesselHasImage', 'vesselImageUrl','vesselCallSign', 'vesselType',  'vesselLength', 'vesselWidth', 'vesselDraft', 'vesselOwner', 'vesselBuilt'
  );
  public $vesselName;  
  public $vesselID;
  public $vesselHasImage;
  public $vesselImageUrl;
  public $vesselCallSign;
  public $vesselType;
  public $vesselLength;
  public $vesselWidth;
  public $vesselDraft;  
  public $vesselOwner;
  public $vesselBuilt;
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