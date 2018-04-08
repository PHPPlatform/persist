<?php

namespace PhpPlatform\Persist\Connection;

use PhpPlatform\Config\Settings;
use PhpPlatform\Errors\Exceptions\Persistence\NoConnectionException;
use PhpPlatform\Persist\Package;

final class ConnectionFactory {
	private static $connections = [];
	
	/**
	 * This method creates and returns connection implementation , based on the configuration property 'dbConnections'
	 * 
	 * @throws NoConnectionException
	 * @return Connection
	 */
	static function getConnection($connectionName = null){
	    if(!isset($connectionName)){
	        $connectionName = 'default';
	    }
	    
	    if(!isset(self::$connections[$connectionName])){
	        $connectionParams = Settings::getSettings(Package::Name,'dbConnections.'.$connectionName);
	        
	        if(!(is_array($connectionParams) && isset($connectionParams['providerClass']))){
	            throw new NoConnectionException("Invalid or No configuration for $connectionName dbConnection");
	        }
	        
	        if(!array_key_exists('params', $connectionParams)){
	            $connectionParams['params'] = [];
	        }
	        
	        $connection = new $connectionParams['providerClass']($connectionParams['params']);
	        
	        if(!($connection instanceof Connection)){
	            throw new NoConnectionException($connectionParams['providerClass']." is not an instance of ".Connection::class);
	        }
	        
	        self::$connections[$connectionName] = $connection;
	    }
	    
	    return self::$connections[$connectionName];
	}
	
}