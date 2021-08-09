<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *  daemon2/classes/LivePlot.class.php 
 * 
 *  This class is the data model for holding plain vessel tranponder data
 *  after it is decoded, but before it is further processed by LiveScan
 *  for display.
 * 
 */
class LivePlot {
    public $plotID;
    public $ts;
    public $id;
    public $name;
    public $lat;
    public $lon;
    public $speed;
    public $course;
    public $plotDaemon;

    public function __construct($ts, $name, $id, $lat, $lon, $speed, $course, $callBack) {
        $this->ts = $ts;        
        $this->name = $name;
        $this->id = $id;
        $this->lat = $lat;
        $this->lon  = $lon;
        $this->speed = $speed;
        $this->course = $course;
        $this->plotDaemon = $callBack;
        $this->formatName();
        $this->formatLon();
        $this->formatSpeed();
        $this->formatCourse();
        //post to api or write kml file based on PlotDaemon configuation
        if($this->plotDaemon->destination=='api') {
            $this->post(false);
        } elseif($this->plotDaemon->destination=='kml') {
            $this->plotDaemon->saveKml();
        }
        
    }

    public function update($ts, $name, $lat, $lon, $speed, $course) {
        $this->ts = $ts;
        //$this->name = $name;
        $this->lat = $lat;
        $this->lon  = $lon;
        $this->speed = $speed;
        $this->course = $course;
        $this->formatLon();
        $this->formatSpeed();
        $this->formatCourse();
        //post to api or write kml file based on PlotDaemon configuation
        if($this->plotDaemon->destination=='api') {
            $this->post(true);
        } elseif($this->plotDaemon->destination=='kml') {
            $this->plotDaemon->saveKml();
        }
        //echo "livePlot[mmsi".$this->id."]->update(".date("F j, Y, g:i:sa", ($this->ts+getTimeOffset()))."(name:".$this->name." lat:".$this->lat." lon:".$this->lon." speed:".$this->speed." course:".$this->course.")\r\n";
    }

    public function formatName() {
        //Get vessel's name
        $name = $this->name;        
        $name     = trim($name, ' @');                   //Remove white spaces or @ symbols
        $name     = str_replace(',', '', $name);   //Remove commas (,)
        $name     = str_replace('.', '. ', $name); //Add space after (.)
        $name     = str_replace('  ', ' ', $name); //Remove double space
        $name     = ucwords(strtolower($name)); //Change capitalization
        //Substitute for blank name
        if($name=="") {
            $name = $this->id."[us]";
        }
        $this->name = $name;
    }
    
    public function formatLon() {
        //Filter extra chars @ after possible bogus lon decimal like -90.2471359.5E
        $lonArr   = explode(".", $this->lon);
        //echo "Lon: ".var_dump($lonArr);
        $lon      = count($lonArr)>1 ? floatval($lonArr[0].".".$lonArr[1]) : $this->lon;
        $this->lon = $lon;
    }

    public function formatSpeed() {
        $speed    = trim($this->speed);
        $this->speed    = $speed."kts";
    }

    public function formatCourse() {
        $course   = trim($this->course);
        $this->course = $course."deg";
    }   
    
    public function post($update=true) {
        $postType = $update==true ?  "update" : "insert";
        $data = array(
            'apiKey' => getenv('MDM_CRT_DB_PWD'),
            'postType' => $postType,
            'ts' => $this->ts,
            'id' => $this->id,
            'name' => $this->name,
            'lat' => $this->lat,
            'lon' => $this->lon,
            'speed' => $this->speed,
            'course' => $this->course
        );
        $response =  json_decode( post_page(API_POST_URL, $data) );
        if($postType=="insert" && is_int($response->plotID)) {
            $this->plotID = $response->plotID;
            echo "plotID ".$this->plotID." ";
        }
        if(is_object($response)) {
            echo $postType." ".$response->message."\r\n";
        }
    }
}
