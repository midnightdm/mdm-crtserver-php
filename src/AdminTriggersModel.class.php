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
    //flog("          AdminTriggersModel::getAdminDocument()\n");
    $now = time();
    //Read from DB if not just done.
    if($this->dataTS===null || $now-$this->dataTS >10) {
      $document = $this->db->collection('Passages')->document('Admin');
      $snapshot = $document->snapshot();
      if($snapshot->exists()) {
        $this->adminData = $snapshot->data();
        $this->dataTS = $now;
        //flog("            updated document retrieved\n");
        return true;
      }
      //flog("            no document snapshot\n");
      return false;
    }
    //flog("            stored document used\n");
    return true;
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
        return ['state' => true, 'ts' => $this->adminData['encoderEnabledTS']];
      }
      return ['state'=> false, 'ts' => null];
    }
    return  ['state'=> false, 'ts' => null];
  }

  public function testForEncoderStart() {
    if($this->getAdminDocument()) {
      if($this->adminData['encoderStart']==true) {
        return true;
      }
      return false;
    }
    return false;
  }

  public function resetEncoderEnabled() {
    $this->db->collection('Passages')
    ->document('Admin')
    ->set(['encoderEnabled'=> false, 'encoderStart'=>false],['merge'=>true]);
  }
  
  public function setEncoderStart() {
    $this->db->collection('Passages')
    ->document('Admin')
    ->set(['encoderStart'=>true],['merge'=>true]);
  }

  public function resetEncoderStart() {
    $this->db->collection('Passages')
    ->document('Admin')
    ->set(['encoderStart'=>false],['merge'=>true]);
  }

  public function setEncoderEnabledTrue() {
    $now = time();
    $this->db->collection('Passages')
    ->document('Admin')
    ->set(['encoderEnabled'=> true, 'encoderEnabledTS'=>$now],['merge'=>true]);
  }

    
  public function testForAddVessel() {
    if($this->getAdminDocument()) {
      if($this->adminData['vesselStatusMsg']=="Process pending. This could take up to 3 minutes.") {
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
      flog(" = NONE\n");
      return false;
    }
    flog("\n          checkForAlertTest() couldn't get Admin Document\n");
    return false;
  }


  public function resetAlertTest() {
    flog("   VesselsModel::resetAlertTest()\n");
    $this->db->collection('Passages')
    ->document('Admin')
    ->set(['alertTestDo' => false ],['merge'=>true]);
  }

}