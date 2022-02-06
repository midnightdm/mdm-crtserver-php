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
  public  $voice_names = [
    "en-US-Wav-enet-G",
    "en-US-Wav-enet-H",
    "en-US-Wav-enet-I",
    "en-US-Wav-enet-J",
    "en-US-Wav-enet-A",
    "en-US-Wav-enet-B",
    "en-US-Wav-enet-C",
    "en-US-Wav-enet-D",
    "en-US-Wav-enet-E",
    "en-US-Wav-enet-F",
    "en-US-Standard-A",
    "en-US-Standard-B",
    "en-US-Standard-C",
    "en-US-Standard-D",
    "en-US-Standard-E",
    "en-US-Standard-F",
    "en-US-Standard-G",
    "en-US-Standard-H",
    "en-US-Standard-I",
    "en-US-Standard-J"
  ];

  public $client;
  public $input;
  public $projectID;
  public $voice;
  public $audioConfig;

  public function __construct() {
      global $config;
      $this->projectID = $config['cloud_projectID'];
      // $this->client = new TextToSpeechClient();
      // $this->client->useApplicationDefaultCredentials();
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

  public function __destruct() {
    $this->client->close();
  }

  public function getRandomVoiceName() {
    $len = count($this->voice_names)-1;
    $num = rand(0,$len);
    return $this->voice_names[$num];
  }

  public function getRandomVoiceGender() {
    $num = rand(0,1);
    $arr = ['MALE', 'FEMALE' ];
    return $arr[$num];
  }

  public function getSpeech($textString, $name, $gender) {
    //Randomize voice
    flog("MyTextToSpeech::getSpeech($textString) using voice \n");
    $this->input->setText($textString);
    $this->voice->setName($name);
    $this->voice->setSsmlGender($gender);
    $response = $this->client->synthesizeSpeech(
      $this->input,
      $this->voice,
      $this->audioConfig
    );
    //Return audio context to be saved as file
    $content = $response->getAudioContent();
    return $content;
  }

  public function listVoices() {
    $response = $this->client->listVoices();
    $voices = $response->getVoices();
    $text = "";
    foreach($voices as $voice) {
      $ttext = "Name: ".$voice->getName().PHP_EOL;
      foreach($voice->getLanguageCodes() as $languageCode) {
        if($languageCode=="en-US") {
          $text .= $ttext;
          continue;
        }
      }
    }
    file_put_contents('c:/app/logs/voice_list.txt', $text);
  }

}


