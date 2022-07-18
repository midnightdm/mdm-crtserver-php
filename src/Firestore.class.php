<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}

use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\FieldValue;


/* * * * * * * * * * * * * * * * * * * * * * * * * * * * 
 *                                                     *
 *   NOTE: The .json file below is project specific    *
 *   and private.  Admin will need to secure one       *
 *   and put their own reference here. Be sure to      *
 *   include it in your .gitignore file to prevent     *
 *   public exposure.                                  *
 *                                                     *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * */

//echo $_SERVER['DOCUMENT_ROOT'] . $config['firestore_json_file'];
if(!isset($strJsonFileContents)) {
  $strJsonFileContents = file_get_contents($_SERVER['DOCUMENT_ROOT'] . $config['firestore_json_file']);
  //Convert into array & Put into CONSTANT
  define('GOOGLE_APPLICATION_CREDENTIALS', json_decode($strJsonFileContents, true));
}


/**
 * This is a custom library to interact with the firebase firestore cloud db
 */
class Firestore {
  protected $db;
  protected $name;

  public function __construct($collection) {
    //echo var_dump($collection);
    $this->name = $collection['name'];
    flog("Firestore::__construct() -> google cred:".GOOGLE_APPLICATION_CREDENTIALS['project_id']."\n"); 
    $this->db = new FirestoreClient([
        'keyFile' => GOOGLE_APPLICATION_CREDENTIALS,
        'projectId'=> 'mdm-qcrt-demo-1'
    ]);    
  }

  public function getDocument($name) {
    $snapshot = $this->db->collection($this->name)->document($name)->snapshot();
    if(!$snapshot->exists()) {
        return false;
    }
    return $snapshot->data();
  }

  public function serverTimestamp() {
    return FieldValue::serverTimestamp();
  }
   
  public function stepApubID($region) {
    $admin = $this->db->collection('Passages')->document('Admin')->snapshot();
    $field = $region == "qc"? 'lastQcApubID' : 'lastApubID'; 
    $apubID = $admin->data()[$field];
    $apubID++;
    flog("stepApubID(): $apubID\n");
    $this->db->collection('Passages')
        ->document('Admin')
        ->set([$field=>$apubID], ['merge'=>true]);
    return $apubID;
  }

  public function getApubID($region) {
    $admin = $this->db->collection('Passages')->document('Admin')->snapshot();
    $field = $region=='qc'? 'lastQcApubID' : 'lastApubID';
    $apubID = $admin->data()[$field];
    return $apubID;
  }

  public function stepVpubID($region) {
    $admin = $this->db->collection('Passages')->document('Admin')->snapshot();
    $field = $region=='qc'? 'lastQcVpubID' : 'lastVpubID';
    $vpubID = $admin->data()[$field];
    $vpubID++;
    flog("stepVpubID(): $vpubID\n");
    $this->db->collection('Passages')
        ->document('Admin')
        ->set([$field=>$vpubID], ['merge'=>true]);
    return $vpubID;
  }
   
  public function getVpubID($refion) {
    $admin = $this->db->collection('Passages')->document('Admin')->snapshot();
    $field = $region=='qc'? 'lastQcVpubID' : 'lastVpubID';
    $vpubID = $admin->data()[$field];
    return $vpubID;
  }

}