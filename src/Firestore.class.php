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
  $path = $config['appPath'] . "/" . $config['firestore_json_file'];
  //flog("INIT: Firestore loading json from $path \n"); 
  $strJsonFileContents = file_get_contents($path);
  //Convert into array & Put into CONSTANT
  define('GOOGLE_APPLICATION_CREDENTIALS', json_decode($strJsonFileContents, true));
}




/**
 * This is a custom library to interact with the firebase firestore cloud db
 */
class Firestore {
   protected $db;
   protected $apiUrl;
   protected $name;
   public $projectID;
  
  

  public function __construct($collection) {
    //echo var_dump($collection);
    global $config;






   $this->apiUrl = $config['MDM_CRT_PLOT_POST'];
    $this->projectID = $config['cloud_projectID'];
    $this->name = $collection['name'];
    flog("Firestore::__construct() -> google cred:".GOOGLE_APPLICATION_CREDENTIALS['project_id']." collection: ". $this->name ."\n"); 
    $this->db = new FirestoreClient([
        'keyFile' => GOOGLE_APPLICATION_CREDENTIALS,
        'projectId'=> $this->projectID 
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
  
  // Removed region selection 7/12/25
  public function stepApubID_Old($region) {
   //Get current apubID from the admin document
    $admin = $this->db->collection('Passages')->document('Admin')->snapshot();
    $field = 'lastApubID'; 
    $apubID = $admin->data()[$field];
    //increment it
    $apubID++;
    flog("stepApubID(): $apubID\n");
    //Update the admin document with the new apubID
    $this->db->collection('Passages')
        ->document('Admin')
        ->set([$field=>$apubID], ['merge'=>true]);
    return $apubID;
  }

  public function stepApubID() {
    $apubID = $this->getApubID();
    if($apubID === false) {
        flog("stepApubID() failed to retrieve lastApubID from MongoDB.\n");
        return false;
    }
    $apubID++;
      flog("stepApubID(): $apubID\n");
    $url = $this->apiUrl."/live/ControlData";
    $data = [
        'lastApubID' => $apubID
    ];
    $responseMongo = put_page($url, $data);

    if($responseMongo['http_code'] == 200) {
        flog("stepApubID() updated to $apubID\n");
        return $apubID;
    } else {
        flog("stepApubID() failed with REST Message ${responseMongo['message']}.\n");
        return false;
    }
  }

  // Removed region selection 7/12/25 
  public function getApubID_Old($region) {
    $admin = $this->db->collection('Passages')->document('Admin')->snapshot();
    $field = 'lastApubID';
    $apubID = $admin->data()[$field];
    return $apubID;
  }

  public function getApubID() {
      $url = $this->apiUrl."/live/ControlData";
      $responseMongo = grab_page($url);
      if($responseMongo && isset($responseMongo['lastApubID'])) {
          return $responseMongo['lastApubID'];
      } else {
          flog("getApubID() failed to retrieve lastApubID from MongoDB.\n");
          return false;
      }
  }

  public function stepVpubID_Old($region) {
    $admin = $this->db->collection('Passages')->document('Admin')->snapshot();
    //$field = $region=='qc'? 'lastQcVpubID' : 'lastVpubID';
    //Altered 9/18/23 to share vpub directory and ids
    $field = 'lastVpubID';
    $vpubID = $admin->data()[$field];
    $vpubID++;
    flog("stepVpubID(): $vpubID\n");
    $this->db->collection('Passages')
        ->document('Admin')
        ->set([$field=>$vpubID], ['merge'=>true]);
    return $vpubID;
  }

  public function stepVpubID() {
    $vpubID = $this->getVpubID();
    if($vpubID === false) {
        flog("stepVpubID() failed to retrieve lastVpubID from MongoDB.\n");
        return false;
    }
    $vpubID++;
      flog("stepVpubID(): $vpubID\n"); 
    $url = $this->apiUrl."/live/ControlData";
    $data = [
        'lastVpubID' => $vpubID
    ];
    $responseMongo = put_page($url, $data);

    if($responseMongo['http_code'] == 200) {
        flog("stepVpubID() updated to $vpubID\n");
        return $vpubID;
    } else {
        flog("stepVpubID() failed with REST Message ${responseMongo['message']}.\n");
        return false;
    }
  }
   
  public function getVpubID_Old($region) {
    $admin = $this->db->collection('Passages')->document('Admin')->snapshot();
    //Altered 9/18/23 to put all in one
    //$field = $region=='qc'? 'lastQcVpubID' : 'lastVpubID';
    $field = 'lastVpubID';
    $vpubID = $admin->data()[$field];
    return $vpubID;
  }

  public function getVpubID() {
      $url = $this->apiUrl."/live/ControlData";
      $responseMongo = grab_page($url);
      if($responseMongo && isset($responseMongo['lastVpubID'])) {
          return $responseMongo['lastVpubID'];
      } else {
          flog("getVpubID() failed to retrieve lastVpubID from MongoDB.\n");
          return false;
      }
  }

  public function setClCamera($camera) {
    if($camera['name'] != "A" && $camera['name'] != "B" && $camera['name'] != 'C' && $camera['name'] != "D") {
      trigger_error("setClCamera() received invalid camera name. Must be 'A', 'B' 'C' or 'D' but  it was ".$camera['name']."\n");
      return;
    }
    $admin = $this->db->collection('Passages')->document('Admin')->set([ "webcamNumCl"=>$camera['name'], 'webcamZoomCl'=> $camera['zoom'] ], ["merge"=>true]);
    flog("setClCamera updated to ".$camera['name']."\n");
  }

  public function setCfCamera($camera) {
    if($camera['name'] != "A" && $camera['name'] != "B" && $camera['name'] != 'C' && $camera['name'] != "D" && $camera['name'] != 'E') {
      trigger_error("setCfCamera() received invalid camera name. Must be 'A', 'B' 'C' 'D' or 'E' but  it was ".$camera['name']."\n");
      return;
    }
    $admin = $this->db->collection('Passages')->document('Admin')->set([ "webcamNumCf"=>$camera['name'], 'webcamZoomCf'=> $camera['zoom'] ], ["merge"=>true]);
    flog("setCfCamera updated to ".$camera['name']."\n");
  }

}