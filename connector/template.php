<?php

/* Connector Template
 *
 * Writting new connectors should be simple.
 *
 * Should add configuration variables to conf/conf.schema.json
 * Should add configuration variables default values to conf/conf.sample.php
 *
 */


class connector
{
    public $primary_handler;
    public $name;


    public function __construct()
    {
        $this->name="connector-code";   // Codename of connector. Should match code in CONNECTOR array.
        // You could access it's global PARMS with $GLOBALS["CONNECTOR"][$this->name]
    }


    public function open($contextData, $customParms)
    {
        $path='/api/extra/generate/stream/';
        $url=$GLOBALS["CONNECTOR"][$this->name]["url"].$path;
        $context="";


        foreach ($contextData as $s_role=>$s_msg) {	// Have to mangle context format

            // Prepare data to sent to LLM
            // Here we should make another class or util to manage different prompt templates.

        }

        $data=[];

        $MAX_TOKENS=((isset($GLOBALS["CONNECTOR"][$this->name]["max_tokens"]) ? $GLOBALS["CONNECTOR"][$this->name]["max_tokens"] : 48)+0);


        // This code is to change max_tokens, as some actions/or flows will need custom values. Example, write diary functionality.
        if (isset($GLOBALS["FORCE_MAX_TOKENS"])) {
            if ($GLOBALS["FORCE_MAX_TOKENS"]==null) {
                unset($data["max_tokens"]);
            } elseif ($customParms["MAX_TOKENS"]) {
                $data["max_tokens"]=$GLOBALS["FORCE_MAX_TOKENS"];

            }
        }

        if (isset($customParms["MAX_TOKENS"])) {
            if ($customParms["MAX_TOKENS"]==null) {
                unset($data["max_tokens"]);
            } elseif ($customParms["MAX_TOKENS"]) {
                $data["max_tokens"]=$customParms["MAX_TOKENS"];
            }
        }


        // primary_handler property should hold the connection handler.
        $this->primary_handler = null;


    }


    public function process()
    {
        // Get data from openened connection, and return it. Only return new data. 
        // You can check if all data has been received here, and mark the connection to close (isDone)
    }


    public function close()
    {
        // Close connection
    }

    public function isDone()
    {
        // You can check if all data has been received here, and mark the connection to close (isDone)
        // Return true when data is done.

    }

    public function processActions()
    {
        // This is only for action enabled mode (Only OpenIA atm)
        return [];
    }


}
