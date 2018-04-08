<?php namespace MyQ;

require_once "query.php";


class Connect{
    public $config;
    function __construct( array $config ){
        $this->config = [
            "prefix" => $config["prefix"]??"",
            "db"     => $config["db"]
        ];
    }
    function __get( $prop ){
        return new Query([
            "table"  => $prop,
            "prefix" => $this->config["prefix"],
            "db"     => $this->config["db"]
        ]);
    }
    function __destruct (){
        unset($this->config["db"]);
    }
}