<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}
//$autoload = $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php';
//exit($autoload);
//require_once $autoload;


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

// $config = $_SERVER['DOCUMENT_ROOT'] . "config.php"

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
   

   

}