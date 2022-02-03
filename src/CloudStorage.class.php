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
    public $appPath;
    public $image_base;
    public $no_image; 

    public function __construct() {
        global $config;
        $this->appPath = $config['appPath'];
        $this->bucketName = $config['cloud_bucket_name'];
        $this->image_base = $config['image_base'];
        $this->no_image = $config['no_image'];
        
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
            flog("Uploaded ".basename($sourcePath)." to https://storage.googleapis.com/".$this->bucketName."/".$destName."\n");
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
        $fileName = $this->appPath.'/scraped-images/mmsi'.$mmsi.'.jpg'; 
        file_put_contents($fileName, $imgData);
        $this->upload($fileName, 'images/vessels/mmsi'.$mmsi.'.jpg');
        return true;
    }

    public function saveVoiceFile($fileName, $audioData) {
      flog("ClooudStorage::saveVoiceFile($fileName");
      $localPath = $this->appPath."/tempaudio/".$fileName;
      file_put_contents($localPath, $audioData);
      $this->upload($fileName, 'voices/'.$fileName);
    }

}





