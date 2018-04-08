<?php namespace MyQ;

class Query {
    public $config;
    /**
     * Genera una instancia de configuracion del contexto del query
     * @param { array } $config - este por defecto puede tener una carga de :
     * query  : {string}
     * params : {array}
     * table  : {string}
     * prefix : {string}
     * db     : { instance of PDO }  
     */
    function __construct( array $config = [] )
    {
        $this->config = [
            "query" => $config["query"]  ?? "",
            "params" => $config["params"] ?? [],
            "table" => $config["table"]  ?? "",
            "prefix" => $config["prefix"] ?? "",
            "db" => $config["db"],
        ];
    }
    /**
     * Permite contruir un multiquery
     * @return {string}
     */
    private function nextQuery()
    {
        return $this->config["query"] ? $this->config["query"].";" : "";
    }
    /**
     * Permite iniciar una consulta select
     * @param {array}
     * @return {instance of self } retorna una instancia inmutable de si misma
     */
    function select( array $select = [] )
    {
        $isAll   = count($select) === 0;
        $params  = array_merge([],$this->config["params"]);
        $query   = join(
            " ",
            [
                "SELECT",
                $isAll ? "*" : join(
                    ", ",
                    $this->map(
                        function ($field) use (&$params) {
                            if(is_array( $field )){
                                $field = $this->mapWalk(
                                    function ($field, $fun, $as = false) use (&$params) {
                                        $use = strtoupper($fun);
                                        switch( $use ) {
                                                case "MIN":
                                                case "MAX":
                                                case "COUNT":
                                                case "AVG":
                                                case "SUM":
                                                    return $use."(".$this->secureParam($field).")".( $as ? " AS ".$this->secureParam($as) : "" );
                                                break;
                                                default:
                                                    return $this->secureParam($field)." AS ".$this->secureParam($fun);
                                                break;
                                        }
                                    },
                                    $field,
                                    3
                                );
                                return $field[0];
                            } else {
                                return $this->secureParam($field);
                            }
                        },
                        $select
                    )
                ),
                "FROM",
                $this->getTable()
            ]
        );
        return new self(array_merge($this->config,[
            "query"=>$query,
            "params"=>$params
        ]));
    }
    /**
     * Permite contruir una consulta insert
     * @param {array} [field]=>value
     * @return {instance of self } retorna una instancia inmutable de si misma
     */
    function insert(array $columns)
    {
        $params = array_merge(
            [],
            $this->config["params"]
        );
        $groupColumns = [];
        $groupValues  = [];

        foreach ($columns as $column => $value) {
            array_push(
                $groupColumns,
                $this->secureParam($column)
            );
            array_push(
                $groupValues,
                $this->bindParam(
                    $params,
                    $value
                )
            );
        }
        $query = [
            $this->nextQuery()."INSERT INTO",
            $this->getTable(),
            "(".join(", ",$groupColumns).")",
            "VALUES",
            "(".join(", ",$groupValues).")"
        ];
        return new self(array_merge(
            $this->config,
            [
                "query"=>join(
                    " ",
                    $query
                ),
                "params"=>$params
            ]
        ));
    }
    /**
     * Permite contruir una consulta delete
     * @return {instance of self } retorna una instancia inmutable de si misma
     */
    function delete()
    {
        return new self(array_merge(
            $this->config,
            [
                "query"  => $this->nextQuery()."DELETE FROM ".$this->getTable(),
            ]
        ));
    }
    /**
     * Permite contruir una consulta update
     * @return {instance of self } retorna una instancia inmutable de si misma
     */
    function update(array $columns)
    {
        $params = array_merge([], $this->config["params"]);
        $group = [];
        foreach ($columns as $column => $value) {
            array_push(
                $group,
                $this->secureParam($column)." = ".$this->bindParam($params,$value)
            );
        }
        $query = [
            $this->nextQuery()."UPDATE",
            $this->getTable(),
            "SET",
            join(" ", $group)
        ];

        return new self(array_merge(
            $this->config,
            [
                "query"=>join(" ",$query),
                "params"=>$params
            ]
        ));
    }
     /**
     * Permite concatenar a la consulta un formato sql where
     * @return {instance of self } retorna una instancia inmutable de si misma
     */
    function where(array $where)
    {
        $params = array_merge(
            [],
            $this->config["params"]
        );
        $query  =  $this->toWhere(
            $where,
            $params
        );

        return new self(array_merge(
            $this->config,
            [
                "query" => join(
                    " ",
                    [
                        $this->config["query"],
                        strpos($this->config["query"],"WHERE") === false ? "WHERE" : "AND",
                        $query
                    ]
                ),
                "params" =>$params
            ]
        ));
    }
    /**
     * permite añadir un limite a la selecion where
     * @param {integer} 
     * @return {instance of self } retorna una instancia inmutable de si misma
     */
    function limit( int $limit )
    {
        return new self(array_merge(
            $this->config,
            [
                "query"=>join(
                    " ",
                    [
                        $this->config["query"],
                        "LIMIT",
                        $limit
                    ]
                )
            ]
        ));
    }
    /**
     * permite añadir join a select
     * 
     * @return {instance of self } retorna una instancia inmutable de si misma
     */
    function join(array $joins, $type = "inner")
    {
        $table = $this->config["table"];
        
        $type  = strtoupper($type);
        $query = $this->config["query"];
        switch ( $type ) {
            case "SELF":
                $from = $this->map(
                    function ($as) {
                        return $this->getTable()." AS ".$this->secureParam($as);
                    },
                    $joins
                );   
                $query = substr(
                    $query,
                    0,
                    strlen($query) - strlen(
                        substr($query,strpos($query,"FROM"))
                    )
                )."FROM ".join(", ",$from);
                break;
            case "INNER":
            case "LEFT" :
            case "RIGHT":
            case "FULL OUTER":
                $inner = [];
                foreach ($joins as $table1 => $table2) {
                    $take1 = explode(".",$table1)[0];
                    $take2 = explode(".",$table2)[0];
                    $with  = $take1 !== $table ? $take1 :
                             ($take2 !== $table ? $take2 : false);
                    if ($with) {
                        $withPrefix = $this->getTable($with,true,false);
                        $with = $this->secureParam($with);
                        array_push($inner,join(" ",[
                            $type,
                            "JOIN",
                            $withPrefix.($withPrefix !== $with ? " AS ".$with : "" ),
                            "ON",
                            $this->secureParam( $table1)." = ".$this->secureParam( $table2 )
                        ]));
                    }
                }
                $query = $this->config["query"]." ".join(" ",$inner);
                break;
        }
        return new self(array_merge($this->config,[
            "query"=>$query
        ]));
    }

    function orderBy(array $columns)
    {
        return new self(array_merge(
            $this->config,
            [
                "query" => join(
                    " ",
                    [
                        $this->config["query"],
                        "ORDER BY",
                        join(", ",$this->map(
                            function($value,$index)
                            {
                                return $this->secureParam( $index )." ".($value > 0 ? "ASC" : "DESC");
                            },
                            $columns
                        )),
                    ]
                ),
            ]
        ));
    }

    function raw(string $string){
        return new self(array_merge(
            $this->config,
            [
                "query" => join(
                    " ",
                    [
                        $this->config["query"],
                        $string
                    ]
                ),
            ]
        ));
    }

    function setParams(array $params){
        return new self(array_merge(
            $this->config,
            [
                "params" => array_merge(
                    $this->config["params"],
                    $params
                ),
            ]
        ));
    }

    function getParam($param){
        return isset($this->config["params"][$param]) ? $this->config["params"][$param] : null;
    }

    private function getTable( $table = null ,$secure = true, $as = true)
    {
        $table  = $table ?? $this->config["table"];
        $prefix = $this->config["prefix"];
        $alias  = $table;
        if ($secure) {
            $alias = $this->secureParam($prefix.$table);
            $table = $this->secureParam($table);
        }
        return $alias.( $prefix &&  $as ? " AS {$table}" : "" );
    }

    private function toWhere( array $where, array &$bind = [] )
    {
        $query = $this->mapWalk(
            function ($equal, $operator, $compare) use (&$bind) {
                $not = "";
                if (strpos( $operator, "!" ) === 0) {
                    $operator = str_replace("!","",$operator);
                    $not = "NOT ";
                }
                $operator = strtolower($operator);

                switch ( $operator ) {
                    case ">=":
                    case ">":
                    case "<=":
                    case "<":
                    case "=":
                    case "<>":
                        if (is_null( $compare )) {
                            return $this->secureParam( $equal )." IS {$not}NULL";
                        } else {
                            return $not.$this->secureParam( $equal )." {$operator} ".$this->bindParam( $bind, $compare );
                        }
                    case "[]":
                    case "between":
                        return $not.join(
                            " ",
                            [
                                $this->secureParam( $equal ),
                                "BETWEEN",
                                $this->bindParam( $bind, $compare[0] ),
                                "AND",
                                $this->bindParam( $bind, $compare[1] ),
                            ]
                        );
                    case "{}":
                    case "in":
                        return $not.join(
                            " ",
                            [
                                $this->secureParam( $equal ),
                                "IN",
                                "(".join(", ",$this->map(function($value) use (&$bind){
                                    return $this->bindParam($bind,$value);
                                },$compare)).")"
                            ]
                        );    
                    case "||":
                    case "or":
                        return $not."(".$this->toWhere( $equal, $bind )." OR ".$this->toWhere( $compare, $bind ).")";
                    case "%":
                    case "like":
                        return $not.join(
                            " ",
                            [
                                $this->secureParam( $equal ),
                                "LIKE",
                                $this->bindParam( $bind, $compare )
                            ]
                        );
                }
            },
            $where,
            3
        );

        return join(" AND ",$query);
    }

    private function secureParam( string $param )
    {
        $param = preg_replace(
            "/[^A-Za-z0-9\.\_\-\s\*]+/",
            "",
            $param
        );
    
        if (preg_match("/[\s]+/", $param)) {
            return "`{$param}`";
        }

        if (preg_match("/[\.]+/", $param)) {
            return $param;
        }
        
        return "`{$param}`";
    }

    function fetch( $fetch = \PDO::FETCH_ASSOC, ...$args )
    {
        list( $instance, $status ) = $this->execute();
        return strpos( $this->config["query"] ,"SELECT") === 0 ? (
            $status ? (
                is_null($fetch) ? $instance : $instance->fetchAll($fetch,...$args)
            ) : []
        ) : $status;
    }
    function execute( array $params = [] )
    {
        $instance = $this->config["db"]->prepare(
            $this->config["query"]
        );
        $status = $instance->execute( count($params) > 0 ?  $params : $this->config["params"] );
        return [ $instance, $status ];
    }
    function map(callable $callback,array $array)
    {
        $next = [];
        foreach ($array as $index => $value) {
            array_push($next,$callback($value,$index));
        }
        return $next;
    }
    private function mapWalk( callable $callback, array $array, int $step )
    {
        $position = 0;
        
        $withCall = false;
        $next     = [];
        foreach ($array as $index => $value ) {
            if ($position < $step) {
                $position++;
            }

            if ($step == $position) {
                array_push($next,
                    $callback(
                        ...array_slice( $array, $index - ( $step - 1 ) )
                    )
                );
                $position = 0;
                $withCall = true;
            }
        }
        if (!$withCall) {
            array_push($next,$callback(...$array) );
        }
        return $next;
    }
    private function bindColumn(array $bind, $value , $prefix = "bind_column_")
    {
        $index = "{$prefix}".count($bind);
        $bind[$index] = $value;
        return $index;
    }
    private function bindParam(array &$bind, $value, string $prefix = ":param_" )
    {
        $index = "{$prefix}".count($bind);
        $bind[ $index ] = $value;
        return $index;
    }
    function __destruct ()
    {
        unset($this->config["db"]);
    }
}