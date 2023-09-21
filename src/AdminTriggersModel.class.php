<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}
/* * * * * *
 * AdminTriggersModel class
 * src/AdminTriggersModel.class.php
 *
 */
class AdminTriggersModel extends Firestore {
  public $adminData;
  public $webcamSitesData;
  public $dataTS;
  public $camDataTS;

  public function __construct() {
      parent::__construct(['name' => 'AdminTriggers']);
      $this->dataTS = null;
      $this->camDataTS = null;
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


  public function getWebcamSitesDocument() {
    //flog("          AdminTriggersModel::getWebccamSitesDocument()\n");
    $now = time();
    //Read from DB if not just done.
    if($this->camDataTS===null || $now-$this->camDataTS >10) {
      $document = $this->db->collection('Controls')->document('webcamSites');
      $snapshot = $document->snapshot();
      if($snapshot->exists()) {
        $this->webcamSitesData = $snapshot->data();
        $this->camDataTS = $now;
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

  public function testForEncoderIsEnabled() {
    if($this->getAdminDocument()) {
      if($this->adminData['encoderIsEnabled']==true) {
        return ['state' => true, 
                'ts' => $this->adminData['encoderEnabledTS'], 
                'vesselID'=> $this->adminData['encoderEnablerVesselID'],
                'vesselDir'=> $this->adminData['encoderEnablerVesselDir']
              ];
      }
      return ['state'=> false, 'ts' => null];
    }
    return  ['state'=> false, 'ts' => null];
  }


  public function testForEncoderIsManualEnabled() {
    if($this->getAdminDocument()) {
      if($this->adminData['encoderIsManualEnabled']==true) {
        return ['state' => true, 
                'ts' => $this->adminData['encoderEnabledTS'], 
                'vesselID'=> $this->adminData['encoderEnablerVesselID'],
                'vesselDir'=> $this->adminData['encoderEnablerVesselDir']
              ];
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

  public function testForEncoderManualStart() {
    if($this->getAdminDocument()) {
      if($this->adminData['encoderManualStart']==true) {
        return true;
      }
      return false;
    }
    return false;
  }


  public function resetEncoderIsEnabled() {
    $this->db->collection('Passages')
    ->document('Admin')
    ->set(['encoderIsEnabled'=> false, 'encoderStart'=>false],['merge'=>true]);
  }


  public function resetEncoderIsManualEnabled() {
    $this->db->collection('Passages')
    ->document('Admin')
    ->set(['encoderIsManualEnabled'=> false, 'encoderManualStart'=>false],['merge'=>true]);
  }
  
  public function setEncoderStart($liveObj) {
    $this->db->collection('Passages')
    ->document('Admin')
    ->set(['encoderStart'=>true, 
           'encoderEnablerVesselID' => $liveObj->liveVesselID,
           'encoderEnablerVesselDir'=> $liveObj->liveDirection
          ],['merge'=>true]);
  }

  public function setEncoderManualStart($liveObj) {
    $this->db->collection('Passages')
    ->document('Admin')
    ->set(['encoderManualStart'=>true, 
           'encoderEnablerVesselID' => $liveObj->liveVesselID,
           'encoderEnablerVesselDir'=> $liveObj->liveDirection
          ],['merge'=>true]);
  }

  public function resetEncoderStart() {
    $this->db->collection('Passages')
    ->document('Admin')
    ->set(['encoderStart'=>false],['merge'=>true]);
  }

  public function resetEncoderManualStart() {
    $this->db->collection('Passages')
    ->document('Admin')
    ->set(['encoderManualStart'=>false],['merge'=>true]);
  }

  public function setEncoderEnabledTrue() {
    $now = time();
    $this->db->collection('Passages')
    ->document('Admin')
    ->set(['encoderIsEnabled'   => true,
           'encoderEnabledTS' => $now, 
          ],['merge'=>true]);
  }

  public function setEncoderManualEnabledTrue() {
    $now = time();
    $this->db->collection('Passages')
    ->document('Admin')
    ->set(['encoderIsManualEnabled'   => true,
           'encoderEnabledTS' => $now, 
          ],['merge'=>true]);
  }


  public function setWebcams($cameraNames) {
    $this->db->collection('Controls')
    ->document('webcamSites')
    ->set([
        'clinton'   => $cameraNames['clinton'],
        'clintoncf' => $cameraNames['clintoncf'],
        'qc'        => $cameraNames['qc']
    ],['merge'=>true]);
  }

  public function setSiteWebcam($site, $data) {
    $this->db->collection('Controls')
    ->document('webcamSites')
    ->set([$site => $data],["merge"=>true]);
  }

  public function getWebcams() {
    if($this->getWebcamSitesDocument()) {
        $cameraNames = [
            'clinton'  =>$this->webcamSitesData['clinton'],
            'clintoncf'=>$this->webcamSitesData['clintoncf'],
            'qc'       =>$this->webcamSitesData['qc']
        ];
        return $cameraNames;
        
    }
  }

  public function setClCamsAreDisabled() {
    $this->db->collection('Passages')
    ->document('Admin')
    ->set(['showClVideoOn'   => false, 'webcamClAllCamsAreDisabled' => true],['merge'=>true]);
  }

  public function setClCamsAreEnabled() {
    $this->db->collection('Passages')
    ->document('Admin')
    ->set(['showClVideoOn'   => true, 'webcamClAllCamsAreDisabled' => false],['merge'=>true]);
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