<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Hopeter1018\SocketCache;

use Exception;

/**
 * Description of Client
 *
 * @version $id$
 * @author peter.ho
 */
class Client
{

    private static $isConnectFailed = false;
    private static $lastError = array();

    /**
     * 
     * @param boolean $isConnectFailed
     */
    public static function setIsConnectFailed($isConnectFailed)
    {
        self::$isConnectFailed = $isConnectFailed;
    }

    /**
     * 
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     */
    public static function addLastError($errno, $errstr, $errfile, $errline)
    {
        self::$isConnectFailed = true;
        self::$lastError[] = array($errno, $errstr, $errfile, $errline);
    }

    /**
     * 
     * @param string $cmd
     * @return mixed|null
     * @throws Exception failed fsockopen
     */
    public static function command($cmd)
    {
        $socket = static::fSockOpen('127.0.0.1', 9908);

        $result = null;
        if ($socket !== false) {
            fwrite($socket, $cmd);
            $result = json_decode(fgets($socket));
            fclose($socket);
        }
        return $result;
    }

    /**
     * 
     * @param string $address
     * @param int $port
     * @return resource
     * @throws Exception failed fsockopen
     */
    private static function fSockOpen($address, $port)
    {
        Client::setIsConnectFailed(false);
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            Client::addLastError($errno, $errstr, $errfile, $errline);
        });

        // Set the ip and port we will listen on
        $socket = fsockopen($address, $port);

        if (Client::$isConnectFailed) {
            throw new Exception('Cannot connect');
        }

        restore_error_handler();
        return $socket;
    }

}
