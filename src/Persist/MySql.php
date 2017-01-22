<?php
/**
 * User: Raaghu
 * Date: 8/5/13
 * Time: 11:25 PM
 */
namespace PhpPlatform\Persist;

use PhpPlatform\Errors\Exceptions\Persistence\NoConnectionException;
use PhpPlatform\Config\Settings;
use PhpPlatform\Persist\Exception\InvalidInputException;

class MySql extends \mysqli{

    static private $instance = null;

    static private $OUTPUT_DATE_FORMAT = null;
    static private $OUTPUT_DATETIME_FORMAT = null;

    private $dbHost = null;
    private $dbUsername = null;
    private $dbPassword = null;
    private $dbName = null;
    private $dbPort = null;
    private $dbSocket = null;
    private $sqlLogFile = null;

    function __construct($host = null,$username = null,$passwd = null,$dbname = null,$port = null,$socket = null,$sqlLogFile = null){
        $this->dbHost     = $host != null       ? $host       : Settings::getSettings('php-platform/persist','dbHost');
        $this->dbUsername = $username != null   ? $username   : Settings::getSettings('php-platform/persist','dbUsername');
        $this->dbPassword = $passwd != null     ? $passwd     : Settings::getSettings('php-platform/persist','dbPassword');
        $this->dbName     = $dbname != null     ? $dbname     : Settings::getSettings('php-platform/persist','dbName');
        $this->dbPort     = $port != null       ? $port       : Settings::getSettings('php-platform/persist','dbPort');
        $this->dbSocket   = $socket != null     ? $socket     : Settings::getSettings('php-platform/persist','dbSocket');
        $this->sqlLogFile = $sqlLogFile != null ? $sqlLogFile : Settings::getSettings('php-platform/persist','sqlLogFile');

        parent::__construct($this->dbHost,$this->dbUsername,$this->dbPassword,$this->dbName,$this->dbPort,$this->dbSocket);
    }

    static public function getInstance($force = false){
        if(self::$instance == null || $force){
            if (extension_loaded('mysqli')) {
                /* MYSQLI */
                self::$instance = @new MySql();
                if (mysqli_connect_error()) {
                    Throw new NoConnectionException("Error Connecting to Database. Please check your configuration , Cause ".mysqli_connect_error());
                }
            } else {
                throw new NoConnectionException("Fatal Error :  mysqli extension is not loaded");
            }
        }
        return self::$instance;
    }

    function query($queryString){
        if(isset($this->sqlLogFile)){
            if(!file_exists(dirname($this->sqlLogFile))){
                mkdir(dirname($this->sqlLogFile), 0777, true);
            }
            error_log($queryString."\n",3,$this->sqlLogFile);
        }
        return parent::query($queryString);
    }

    public static function getMysqlDate($dateStr=null,$includeTime=null,$dateStrIsTimestamp = false){
        if($dateStr == null){
            $date = time();
        }else if(!$dateStrIsTimestamp){
            $date = strtotime($dateStr);
        }else{
        	$date = $dateStr;
        }
        $format = "Y-m-d";
        if(isset($includeTime)){
            $format .= " H:i:s";
        }
        $mysqlDate = date($format,$date);
        return $mysqlDate;
    }

    public static function getMysqlTime($ampm="AM",$hh=0,$mm=0,$ss=0){
        if(strcasecmp($ampm, "PM") == 0){
            if($hh != 12){
                $hh = $hh+12;
            }
        }else{
            if($hh == 12){
                $hh = 0;
            }
        }

        $mysqlTime = $hh.":".$mm.":".$ss;
        return $mysqlTime;
    }

    public static function getMysqlBooleanValue($value){
        if(is_string($value)){
            if(strtoupper($value) == "TRUE"){
                $value = '1';
            }else if(strtoupper($value) == "FALSE"){
                $value = '0';
            }else{
                throw new InvalidInputException("Not a boolean value");
            }
        }elseif ($value == true){
            $value = '1';
        }elseif ($value == false){
            $value = '0';
        }else{
            throw new InvalidInputException("Not a boolean value");
        }
        return $value;
    }

    public static function getOutputDateFormat(){
        if(!isset(self::$OUTPUT_DATE_FORMAT)){
            self::$OUTPUT_DATE_FORMAT = Settings::getSettings('php-platform/persist','outputDateFormat');
        }
        return self::$OUTPUT_DATE_FORMAT;
    }

    public static function getOutputDateTimeFormat(){
        if(!isset(self::$OUTPUT_DATETIME_FORMAT)){
            self::$OUTPUT_DATETIME_FORMAT = Settings::getSettings('php-platform/persist','outputDateTimeFormat');
        }
        return self::$OUTPUT_DATETIME_FORMAT;
    }
    
    public static function getMysqlTimeZone($phpTimeZone){
    	$dtz = new \DateTimeZone($phpTimeZone);
    	$timeInTimeZone = new \DateTime('now', $dtz);
    	
    	$sign = "+";
    	$offset = $dtz->getOffset( $timeInTimeZone ) / 3600;
    	if($offset < 0){
    		$sign = "-";
    		$offset = -1 * $offset;
    	}
    	$hourPart = intval($offset);
    	$minutePart = $offset-$hourPart;
    	$minutePart = $minutePart * 60;
    	
    	$timeZoneForMySql = $sign.$hourPart.":".$minutePart;
    	
    	return $timeZoneForMySql;
    }

}

?>