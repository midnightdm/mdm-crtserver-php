<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}

# Imports the Google Cloud client library
use Google\Cloud\Storage\StorageClient;

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * 
 *                                                     *
 *   NOTE: The .json file below is project specific    *
 *   and private.  Admin will need to secure one       *
 *   and put their own reference here. Be sure to      *
 *   include it in your .gitignore file to prevent     *
 *   public exposure.                                  *
 *                                                     *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * */

//$strJsonFileContents = file_get_contents($_SERVER['DOCUMENT_ROOT'] . $config['firestore_json_file']);

//Convert into array & Put into CONSTANT
//define('GOOGLE_APPLICATION_CREDENTIALS', json_decode($strJsonFileContents, true));

class CloudStorage {
    public $bucketName;
    public $projectID;
    public $storage;

    public function __construct() {
        global $config;
        $this->bucketName = $config['cloud_bucket_name'];
        
        //# Your Google Cloud Platform project ID
        $this->projectId = $config['cloud_projectID'];
        

        $this->storage = new StorageClient([
            'keyFile' => GOOGLE_APPLICATION_CREDENTIALS,
            'projectId'=>$this->projectID,
        ]);
    }

    public function upload($sourcePath, $destName) {    
        if(is_readable($sourcePath)) {
            $file = fopen($sourcePath, 'r');
            $bucket = $this->storage->bucket($this->bucketName);
            $object = $bucket->upload($file, ['name' => ''.$destName]);
            flog("Uploaded ".basename($sourcePath)." to https://storage.googleapis.com/www.clintonrivertraffic.com/".$this->bucketName."/".$destName."\n");
        }        
    }

    public function scrapeImage($mmsi) {
        flog("CloudStorage::scrapeImage($mmsi)\n");
        $url = 'https://www.myshiptracking.com/requests/getimage-normal/';
        $imgData = grab_image($url.$mmsi.'.jpg');
        $strLen = strlen($imgData);
        flog('$imgData length test: '.$strLen." bytes\n");
        if(!$strLen) {
            flog("No image saved for $mmsi\n");
            return false;
        }
        $fileName = 'E:/app/scraped-images/mmsi'.$mmsi.'.jpg'; 
        file_put_contents($fileName, $imgData);
        $this->upload($fileName, 'images/vessels/mmsi'.$mmsi.'.jpg');
        return true;
    }

}





