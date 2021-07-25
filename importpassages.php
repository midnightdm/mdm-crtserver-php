<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}

include_once('classes/Firestore.class.php');
include_once('d-classes/Dbh.class.php');

/* * * * * * * * *
 * ImportPassagesModel Class
 * daemon/classes/AlertsModel.class.php
 * 
 */
class ImportPassagesModel extends Dbh {
    public function __construct() {
      parent::__construct();
    }

    public function getAllPassages() {
        $db = $this->db();
        return $db->query('SELECT * FROM passages')->fetchAll();
    }

}

class ExportPassagesModel extends Firestore {
    public function __construct() {
        parent::__construct(['name' => 'Vessels']);
    }


}