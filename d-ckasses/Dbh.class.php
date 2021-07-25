 bv<?php
if(php_sapi_name() !='cli') { exit('No direct script access allowed.');}
/* * * * * * * * * *
 * Database Handler Class
 * daemon/classes/dbh.php
 * 
 */

class Dbh {
  private $dbHost;
  private $dbUser;
  private $dbPwd;
  private $dbName;

  public function __construct($file = '') {
    if($file == '') {
      $file = getEnv('HOST_IS_HEROKU') ?  'daemon/crtconfig.php' :  getEnv('MDM_CRT_CONFIG_PATH');
      //Override config path when testing from heroku bash
      if(isset($GLOBALS['cli']) && $GLOBALS['cli']==true) {
        $file = "crtconfig.php";
      }
      //echo "The document root file is: ". $file;
    }
    if(!is_string($file)) {
      throw new Exception('Dbh could not load config file');
    }
    $config = include($file);
    $this->dbHost = $config['dbHost'];
    $this->dbUser = $config['dbUser'];
    $this->dbPwd  = $config['dbPwd'];
    $this->dbName = $config['dbName'];
  }

  protected function db() {
    $dsn = 'mysql:host=' . $this->dbHost . ';dbname=' . $this->dbName;
    $pdo = new PDO($dsn, $this->dbUser, $this->dbPwd);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
  }
}
