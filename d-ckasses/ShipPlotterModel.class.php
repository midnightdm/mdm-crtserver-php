<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}
/* * * * * *
 * ShipPlotterModel class
 * daemon/classes/ShipPlotterModel.class.php
 *
 */
class ShipPlotterModel extends Dbh {

  public function __construct() {
    parent::__construct();
  }

  public function getStatus() {
    $sql = "select * from shipplotter where id = 1";
    $db  = $this->db();
    $ret = $db->query($sql);
    $row = $ret->fetch();
    return $row;
    //$status['isReachable']  
    //$status['lastUpTS']    
    //$status['lastDownTS']   
  }

  public function serverIsUp($ts) {
    echo "running serverIsUp() ";
    $sql = "update shipplotter set isReachable = true, lastUpTS = ?  WHERE id = 1";
    $db  = $this->db();
    $ret = $db->prepare($sql);
    $ret->execute([$ts]);    
  }

  public function serverIsDown($ts) {
    echo "running serverIsDown() ";
    $sql = "update shipplotter set isReachable = false, lastDownTS = ?  WHERE id = 1";
    $db  = $this->db();
    $ret = $db->prepare($sql);
    $ret->execute([$ts]);
  }
}
?>