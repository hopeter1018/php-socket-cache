<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Hopeter1018\SocketCache;

/**
 * @version $id$
 * @author peter.ho
 */
class Server
{

    private static $maxClients = 10;
    protected static $client = Array ();
    protected static $sock = null;
    private static $dataKeyValue = array();
    private static $dataStats = array();

    /**
     * 
     * @param string $address
     * @param int $port
     * @param int $maxClients
     */
    public static function register($address = '127.0.0.1', $port = 9908, $maxClients = 1)
    {
        ignore_user_abort(true);
        set_time_limit(0);

        // Array that will hold client information
        static::$maxClients = $maxClients;

        // Create a TCP Stream socket
        static::$sock = socket_create(AF_INET, SOCK_STREAM, 0);

        // Bind the socket to an address/port
        socket_bind(static::$sock, $address, $port) or die('Could not bind to address');

        // Start listening for connections
        socket_listen(static::$sock);

        // Run loop
        static::loop(array(), array());

        // Close the master sockets
        socket_close(static::$sock);
    }

    protected static function getRead()
    {
        $read[0] = static::$sock;
        for ($i = 0; $i < static::$maxClients; $i++) {
            if (isset(static::$client[$i]) and static::$client[$i]['sock'] != null) {
                $read[$i + 1] = static::$client[$i]['sock'];
            }
        }
        return $read;
    }

    protected static function bindClients()
    {
        for ($i = 0; $i < static::$maxClients; $i++) {
            if (!isset(static::$client[$i]) or !isset(static::$client[$i]['sock']) or static::$client[$i]['sock'] == null) {
                static::$client[$i] = array(
                    'sock' => socket_accept(static::$sock),
                );
                break;
            } elseif ($i == static::$maxClients - 1) {
                print ("too many clients");
            }
        }
    }

    protected static function doAction($input, $i)
    {
        $output = true;
        if (substr($input, 0, 4) === "SAVE") {
            list($action, $key, $value) = explode(" ", $input, 3);
            static::$dataKeyValue[$key] = $value;
        } elseif (substr($input, 0, 5) === "FETCH") {
            list($action, $key) = explode(" ", $input, 2);
            return static::$dataKeyValue[$key];
        } elseif (substr($input, 0, 5) === "FLUSH") {
            static::$dataKeyValue = array();
        } elseif (substr($input, 0, 6) === "DELETE") {
            list($action, $key) = explode(" ", $input, 2);
            unset(static::$dataKeyValue[$key]);
        } elseif (substr($input, 0, 8) === "GETSTATS") {
            $output = static::$dataStats;
        } elseif (substr($input, 0, 8) === "CONTAINS") {
            list($action, $key) = explode(" ", $input, 2);
            $output = isset(static::$dataKeyValue[$key]);
        } elseif (substr($input, 0, 4) === "EXIT") {
            exit;
        } else {
            $output = preg_replace("/[ \t\n\r]/", "", $input) . chr(0);
        }
        socket_write(static::$client[$i]['sock'], json_encode(array(
            "index" => $i,
            "serverTime" => time(),
            "output" => $output,
        )));
        socket_close(static::$client[$i]['sock']);
        unset(static::$client[$i]);
    }

    protected static function action($read)
    {
        // If a client is trying to write - handle it now
        for ($i = 0; $i < static::$maxClients; $i++) {
            // for each client
            if (isset(static::$client[$i]) and in_array(static::$client[$i]['sock'], $read)) {
                $input = socket_read(static::$client[$i]['sock'], 1024);
                if ($input == null) {
                    // Zero length string meaning disconnected
                    unset(static::$client[$i]);
                } else {
                    self::doAction($input, $i);
                }
            } else {
                isset(static::$client[$i]) and socket_close(static::$client[$i]['sock']);
                unset(static::$client[$i]);
            }
        }
    }

    protected static function loop($write, $except)
    {
        while (true) {
            // Setup clients listen socket for reading
            $read = static::getRead();

            // Set up a blocking call to socket_select()
            $ready = socket_select($read, $write, $except, null);

            /* if a new connection is being made add it to the client array */
            if (in_array(static::$sock, $read)) {
                static::bindClients();

                if (--$ready <= 0) {
                    continue;
                }
            } // end if in_array

            static::action($read);
        } // end while
    }

}
