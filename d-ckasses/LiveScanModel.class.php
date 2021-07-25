<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}
/* * * * * *
 * LiveScanModel class
 * daemon/classes/LiveScanModel.class.php
 *
 */

class LiveScanModel extends Dbh {

  public function __construct() {
    parent::__construct();
  }

  public function getAllLiveScans() {
    $db = $this->db();
    return $db->query('SELECT * FROM live')->fetchAll();
  }

  public function getAllLivePlots() {
    $db = $this->db();
    return $db->query('SELECT * FROM plot')->fetchAll();
  }

  public function insertLiveScan($dataArr) {
    $db = $this->db();
    $sql = "INSERT INTO live (liveInitTS, liveLastTS, liveInitLat, liveInitLon, liveDirection, liveLocation, liveVesselID, liveName, liveLength, liveWidth, liveDraft, liveCallSign, liveSpeed, liveCourse, liveDest, liveIsLocal) VALUES (:liveInitTS, :liveLastTS, :liveInitLat, :liveInitLon, :liveDirection, :liveLocation, :liveVesselID, :liveName, :liveLength, :liveWidth, :liveDraft, :liveCallSign, :liveSpeed, :liveCourse, :liveDest, :liveIsLocal)";
     $ret = $db->prepare($sql);
     $ret->execute($dataArr);
     $liveID = $db->lastInsertID();
     //$ret = null;
     //echo "{$dataArr['liveName']} added to db liveID= $liveID , errorCode= ".var_dump($ret->errorInfo())."\n";
     unset($db);
     return $liveID;    
  }

  public function updateLiveScan($dataArr) {
    $db = $this->db();
    $sql = "UPDATE live SET liveLastTS = :liveLastTS, liveLastLat = :liveLastLat, "
      . "liveLastLon = :liveLastLon, liveDirection = :liveDirection,"
      . "liveLocation= :liveLocation, liveName = :liveName, "
      . "liveSpeed = :liveSpeed, liveDest = :liveDest, liveCourse = :liveCourse, "      
      . "liveMarkerAlphaWasReached = :liveMarkerAlphaWasReached, "
      . "liveMarkerBravoWasReached = :liveMarkerBravoWasReached, "
      . "liveMarkerCharlieWasReached = :liveMarkerCharlieWasReached, "
      . "liveMarkerDeltaWasReached = :liveMarkerDeltaWasReached, "
      . "liveMarkerAlphaTS = :liveMarkerAlphaTS, liveMarkerBravoTS = :liveMarkerBravoTS, "
      . "liveMarkerCharlieTS = :liveMarkerCharlieTS, liveMarkerDeltaTS = :liveMarkerDeltaTS, livePassageWasSaved = :livePassageWasSaved "
      . "WHERE liveVesselID = :liveVesselID";
    $ret = $db->prepare($sql);
    $db->beginTransaction();
    //echo "updateLiveScan() data: ".var_dump($dataArr);
    $ret->execute($dataArr);
    $db->commit();
    $c = $ret->rowCount();
    $ret = null;
    //echo "LiveScanModel::updateLiveScan() rows updated= $c for {$dataArr['liveName']} \n";
    
  }

  public function deleteLiveScan($liveID) {
    $db = $this->db();
    $sql = "DELETE FROM live WHERE liveID = ?";
    $ret = $db->prepare($sql);
    $ret->execute([$liveID]);
    $c = $ret->rowCount();
    return $c;
  }
}
