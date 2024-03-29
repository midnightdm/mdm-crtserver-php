<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}

use Abraham\TwitterOAuth\TwitterOAuth;


/**
 * This is a custom library to interact with the Twitter API
 */
class Twitter {
  protected $connection;
  protected $consumerKey;
  protected $consumerSecret;
  protected $accessToken;
  protected $accessTokenSecret;
  

  public function __construct() {
    global $config;
    $this->consumerKey       = $config['twitterConsumerKey'];
    $this->consumerSecret    = $config['twitterConsumerSecret'];
    $this->accessToken       = $config['twitterAccessToken'];
    $this->accessTokenSecret = $config['twitterTokenSecret']; 
    $this->slidesFilePath    = $config['twitterSlidesFilePath'];   // "c:\\app\\twitter\\clinton\\"
    $this->connection = new TwitterOAuth($this->consumerKey, $this->consumerSecret, $this->accessToken, $this->accessTokenSecret);
    flog("Twitter::__construct() \n"); 
        
  }

  public function postStreamAnnoucement($vesselID, $vesselDirection) {
    $p = $this->slidesFilePath;
    if($vesselID == "368048990" && vesselDirection == "upriver")   { $png = $p."slide-16.png"; } //American Countess
    if($vesselID == "368048990" && vesselDirection == "downriver") { $png = $p."slide-15.png"; }
    if($vesselID == "367755720" && vesselDirection == "upriver")   { $png = $p."slide-18.png"; } //American Duchess
    if($vesselID == "367755720" && vesselDirection == "downriver") { $png = $p."slide-17.png"; }
    //if($vesselID == "368082970" && vesselDirection == "upriver")   { $png = $p."slide-.png"; } //American Harmony
    //if($vesselID == "368082970" && vesselDirection == "downriver") { $png = $p."slide-.png"; }
    //if($vesselID == "368135920" && vesselDirection == "upriver")   { $png = $p."slide-.png"; } //American Jazz
    //if($vesselID == "368135920" && vesselDirection == "downriver") { $png = $p."slide-.png"; }
    if($vesselID == "368205140" && vesselDirection == "upriver")   { $png = $p."slide-06.png"; } //American Melody
    if($vesselID == "368205140" && vesselDirection == "downriver") { $png = $p."slide-05.png"; }
    //if($vesselID == "367517320" && vesselDirection == "upriver")   { $png = $p."slide-.png"; } //American Pride
    //if($vesselID == "367517320" && vesselDirection == "downriver") { $png = $p."slide-.png"; }
    if($vesselID == "366950740" && vesselDirection == "upriver")   { $png = $p."slide-04.png"; } //American Queen
    if($vesselID == "366950740" && vesselDirection == "downriver") { $png = $p."slide-03.png"; }
    //if($vesselID == "368046350" && vesselDirection == "upriver")   { $png = $p."slide-.png"; } //American Song
    //if($vesselID == "368046350" && vesselDirection == "downriver") { $png = $p."slide-.png"; }
    if($vesselID == "367710540" && vesselDirection == "upriver")   { $png = $p."slide-20.png"; } //American Splendor
    if($vesselID == "367710540" && vesselDirection == "downriver") { $png = $p."slide-19.png"; }
    if($vesselID == "368261060" && vesselDirection == "upriver")   { $png = $p."slide-22.png"; } //American Symphony
    if($vesselID == "368261060" && vesselDirection == "downriver") { $png = $p."slide-21.png"; }
    if($vesselID == "367335590" && vesselDirection == "upriver")   { $png = $p."slide-12.png"; } //Celebration Belle
    if($vesselID == "367335590" && vesselDirection == "downriver") { $png = $p."slide-11.png"; } 
    if($vesselID == "367648680" && vesselDirection == "upriver")   { $png = $p."slide-14.png"; } //Queen of the Mississippi
    if($vesselID == "367648680" && vesselDirection == "downriver") { $png = $p."slide-13.png"; } 
    //if($vesselID == "367050090" && vesselDirection == "upriver")   { $png = $p."slide-.png"; } //Spirit of Peoria
    //if($vesselID == "367050090" && vesselDirection == "downriver") { $png = $p."slide-.png"; }
    if($vesselID == "367107060" && vesselDirection == "upriver")   { $png = $p."slide-02.png"; } //Twilight
    if($vesselID == "367107060" && vesselDirection == "downriver") { $png = $p."slide-01.png"; }
    if($vesselID == "368261120" && vesselDirection == "upriver")   { $png = $p."slide-08.png"; } //Viking Mississippi
    if($vesselID == "368261120" && vesselDirection == "downriver") { $png = $p."slide-09.png"; }
    
    $media1 = $this->connection->upload('media/upload', ['media' => $png]);
    //$media2 = $connection->upload('media/upload', ['media' => '/path/to/file/kitten2.jpg']);
    $parameters = [
    'status' => ' ',
    'media_ids' => [$media1->media_id_string]
    ];
    $result = $this->connection->post('statuses/update', $parameters);
    flog("Dump of twitter API result: ".var_dump($result));
    
  }



}