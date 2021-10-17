<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}

# Imports the Google Cloud client library
use Google\Cloud\Storage\StorageClient;

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
            'projectId'=>$this->projectID,
            'keyFilePath' => GOOGLE_APPLICATION_CREDENTIALS,
        ]);
    }

    public function upload($sourcePath, $destName) {    
        $this->sourcePath = $sourcePath;       
        $this->destName   = $destName;
        if(is_readable($sourcePath)) {
            $file = fopen($sourcePath, 'r');
            $bucket = $this->storage->bucket($this->bucketName);
            $object = $bucket->upload($file, ['name' => $destName]);
            flog("Uploaded ".basename($sourcePath)." to gs://".$this->bucketName."/".$desttName."\n");

        }
        
    }

}





