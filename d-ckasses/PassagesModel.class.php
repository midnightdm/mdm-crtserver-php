<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}
/* * * * * *
 * PassagesModel class
 * daemon/classes/passagesmodel.class.php
 *
 */

class PassagesModel extends Dbh {

  public function __construct() {
    parent::__construct();
  }

  public function savePassage($liveScanObj) {
    $data['passageVesselID'] = $liveScanObj->liveVesselID;
    $data['passageDirection'] = $liveScanObj->liveDirection;
    $data['passageMarkerAlphaTS'] = $liveScanObj->liveMarkerAlphaTS;
    $data['passageMarkerBravoTS'] = $liveScanObj->liveMarkerBravoTS;
    $data['passageMarkerCharlieTS'] = $liveScanObj->liveMarkerCharlieTS;
    $data['passageMarkerDeltaTS'] = $liveScanObj->liveMarkerDeltaTS;
    $sql = "INSERT INTO passages (passageVesselID, "
      . "passageDirection, passageMarkerAlphaTS, "
      . "passageMarkerBravoTS, passageMarkerCharlieTS, "
      . "passageMarkerDeltaTS) VALUES (:passageVesselID, "
      . ":passageDirection, :passageMarkerAlphaTS, "
      . ":passageMarkerBravoTS, :passageMarkerCharlieTS, "
      . ":passageMarkerDeltaTS)";
      $db = $this->db();
      $ret = $db->prepare($sql);
      $ret->execute($data);
      $c = $ret->rowCount();
      return $c;
  }

  public function insertData($passageData) {
    $sql = "INSERT INTO passages (passageVesselID, "
      . "passageDirection, passageMarkerAlphaTS, "
      . "passageMarkerBravoTS, passageMarkerCharlieTS, "
      . "passageMarkerDeltaTS) VALUES (:passageVesselID, "
      . ":passageDirection, :passageMarkerAlphaTS, "
      . ":passageMarkerBravoTS, :passageMarkerCharlieTS, "
      . ":passageMarkerDeltaTS)";
    $db = $this->db();
    $ret = $db->prepare($sql)      ;
    $ret->execute($passageData);
    $c = $ret->rowCount();
  }
}  
