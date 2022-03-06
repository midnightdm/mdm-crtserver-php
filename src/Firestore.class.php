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

echo $_SERVER['DOCUMENT_ROOT'] . $config['firestore_json_file'];
$strJsonFileContents = file_get_contents($_SERVER['DOCUMENT_ROOT'] . $config['firestore_json_file']);


//Convert into array & Put into CONSTANT
define('GOOGLE_APPLICATION_CREDENTIALS', json_decode($strJsonFileContents, true));

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
   
  public function stepApubID() {
    $admin = $this->db->collection('Passages')->document('Admin')->snapshot();
    $apubID = $admin->data()['lastApubID'];
    $apubID++;
    flog("stepApubID(): $apubID\n");
    $this->db->collection('Passages')
        ->document('Admin')
        ->set(['lastApubID'=>$apubID], ['merge'=>true]);
    return $apubID;
  }

  public function getApubID() {
    $admin = $this->db->collection('Passages')->document('Admin')->snapshot();
    $apubID = $admin->data()['lastApubID'];
    return $apubID;
  }

  public function stepVpubID() {
    $admin = $this->db->collection('Passages')->document('Admin')->snapshot();
    $vpubID = $admin->data()['lastVpubID'];
    $vpubID++;
    flog("stepVpubID(): $vpubID\n");
    $this->db->collection('Passages')
        ->document('Admin')
        ->set(['lastVpubID'=>$vpubID], ['merge'=>true]);
    return $vpubID;
  }
   
  public function getVpubID() {
    $admin = $this->db->collection('Passages')->document('Admin')->snapshot();
    $vpubID = $admin->data()['lastVpubID'];
    return $vpubID;
  }

}