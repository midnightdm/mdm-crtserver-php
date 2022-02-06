<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}

# Imports the Google Cloud client library
use Google\Cloud\TextToSpeech\V1\AudioConfig;
use Google\Cloud\TextToSpeech\V1\AudioEncoding;
use Google\Cloud\TextToSpeech\V1\SynthesisInput;
use Google\Cloud\TextToSpeech\V1\TextToSpeechClient;
use Google\Cloud\TextToSpeech\V1\VoiceSelectionParams;


/* * * * * * * * * * * * * * * * * * * * * * * * * * * * 
 *                                                     *
 *   NOTE: The .json file below is project specific    *
 *   and private.  Admin will need to secure one       *
 *   and put their own reference here. Be sure to      *
 *   include it in your .gitignore file to prevent     *
 *   public exposure.                                  *
 *                                                     *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * */

//$strJsonFileContents = file_get_contents($_SERVER['DOCUMENT_ROOT'] . $config['texttospeech_json_file']);


class MyTextToSpeech {
  public $client;
  public $input;
  public $projectID;
  public $voice;
  public $audioConfig;

  public function __construct() {
      global $config;
      $this->projectID = $config['cloud_projectID'];
      //var_dump(GOOGLE_APPLICATION_CREDENTIALS); exit;
      flog("MyTextToSpeech::__construct() -> google cred:". GOOGLE_APPLICATION_CREDENTIALS['project_id']."\n" );
      $this->client = new TextToSpeechClient([
        'keyFile'  => GOOGLE_APPLICATION_CREDENTIALS,
        'projectId'=> $this->projectID
      ]);
      $this->input  = new SynthesisInput();
      $this->voice  = new VoiceSelectionParams();
      $this->voice->setLanguageCode('en-US');
      $this->audioConfig = new AudioConfig();
      $this->audioConfig->setAudioEncoding(AudioEncoding::MP3);
  }

  public function getSpeech($textString) {
    flog("MyTextToSpeech::getSpeech($textString)\n");
    $this->input->setText($textString);
    $response = $this->client(
      $this->input,
      $this->voice,
      $this->audioConfig
    );
    //Return audio context to be saved as file
    return $response->getAudioContent();
  }

  

}


