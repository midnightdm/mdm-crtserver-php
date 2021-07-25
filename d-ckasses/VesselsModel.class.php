<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}
/* * * * * *
 * VesselsModel class
 * daemon/classes/vesselsmodel.php
 *
 */

class VesselsModel extends Dbh {

  public function __construct() {
    parent::__construct();
  }

  public function getVessel($vesselID) {
    $sql = "SELECT * FROM vessels WHERE vesselID = ?";
    $db = $this->db();
    $ret = $db->prepare($sql);
    $ret->execute([$vesselID]);
    if($ret->rowCount()>0) {
      return $ret->fetch();
    } else {
      return false;
    }
  }

  public function vesselHasRecord($vesselID) {
    $sql = "SELECT vesselName FROM vessels WHERE vesselID = ?";
    $db = $this->db();
    $ret = $db->prepare($sql);
    $ret->execute([$vesselID]);
    return $ret->rowCount();
  }

  public function getVesselLastDetectedTS($vesselID) {
    $sql = "SELECT vesselLastDetectedTS FROM vessels WHERE vesselID = ?";
    $db = $this->db();
    $ret = $db->prepare($sql);
    $ret->execute([$vesselID]);
    if($ret->rowCount()>0) {
      return $ret->fetch();
    } else {
      return false;
    }
  }

  public function updateVesselLastDetectedTS($vesselID, $ts) {
    $sql = "UPDATE vessels SET vesselLastDetectedTS=:vesselLastDetectedTS WHERE vesselID=:vesselID";
    $db = $this->db();
    $ret = $db->prepare($sql);
    $ret->execute(['vesselLastDetectedTS'=>$ts, 'vesselID'=>$vesselID]);
    return true;
  }

  public function insertVessel($dataArr) {
    $sql = "INSERT INTO vessels (vesselName, vesselID,  vesselHasImage, vesselImageUrl, vesselCallSign, vesselType, 
       vesselLength, vesselWidth, vesselDraft, vesselOwner, vesselBuilt, vesselRecordAddedTS) VALUES (:vesselName, :vesselID, :vesselHasImage, :vesselImageUrl, :vesselCallSign, :vesselType, :vesselLength, :vesselWidth, :vesselDraft, :vesselOwner, :vesselBuilt, :vesselRecordAddedTS)";
      $db = $this->db();
      //echo "insertVessel() data: ". var_dump($dataArr);
      $ret = $db->prepare($sql);
      $ret->execute($dataArr);
      //echo "{$dataArr['vesselName']} added to db or errorCode= ".var_dump($ret->errorInfo())."\n";
  }
} 