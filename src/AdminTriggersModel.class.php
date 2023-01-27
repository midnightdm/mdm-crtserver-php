<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}
/* * * * * *
 * AdminTriggersModel class
 * src/AdminTriggersModel.class.php
 *
 */
class AdminTriggersModel extends Firestore {
  public $adminData;
  public $dataTS;

  public function __construct() {
      parent::__construct(['name' => 'AdminTriggers']);
      $this->dataTS = null;
  }

  public function getAdminDocument() {
    $now = time();
    //Read from DB if not just done.
    if($this->dataTS===null || $now-$this->dataTS >10) {
      $document = $this->db->collection('Passages')->document('Admin');
      $snapshot = $document->snapshot();
      if($snapshot->exists()) {
        $this->adminData = $snapshot->data();
        $this->dataTS = $now;
        return true;
      }
      return false;
    } 
  }

  public function testExit() {
    if($this->getAdminDocument()) {
      if($this->adminData['exit']==true) {
        return true;
      }
      return false;
    }
    return false;
  }

  public function resetExit() {
    $this->db->collection('Passages')
    ->document('Admin')
    ->set(['exit'=> false],['merge'=>true]);
  }    

  public function testForEncoderEnabled() {
    if($this->getAdminDocument()) {
      if($this->adminData['encoderEnabled']==true) {
        return true;
      }
      return false;
    }
    return false;
  }

  public function resetEncoderEnable() {
    $this->db->collection('Passages')
    ->document('Admin')
    ->set(['encoderEnable'=> false],['merge'=>true]);
  }   

    
  public function testForAddVessel() {
    if($this->getAdminDocument()) {
      if($this->adminData['vesselsStatusMsg']=="Process pending. This could take up to 3 minutes.") {
        return $this->adminData['vesselID'];
      }
      return false;
    }
    return false;  
  }

  public function resetAddVessel() {
    $this->db->collection('Passages')
      ->document('Admin')
      ->set(
        [
          'vesselError'=> false, 
          'vesselStatusMsg' => "Ready for your input.", 
          'formAwaitingReset'=> true
        ], 
        ['merge'=>true]
      );
  } 
  

  public function checkForAlertTest() {
    if($this->getAdminDocument()) {
      if($this->adminData['alertTestDo']==true) {
        return array(
          'go'=>true, 
          'key'=>$this->adminData['alertTestKey'], 
          'event'=>$this->adminData['alertTestEvent']
        );
      }
      return false;
    }
    return false;
  }


  public function resetAlertTest() {
    flog("   VesselsModel::resetAlertTest()\n");
    $this->db->collection('Passages')
    ->document('Admin')
    ->set(['alertTestDo' => false ],['merge'=>true]);
  }

}