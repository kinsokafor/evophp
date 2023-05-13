<?php

namespace EvoPhp\Database;

use Exception;
use EvoPhp\Api\Operations;

/**
 * summary
 */
class Query extends Database
{
    use DataType;
    /**
     * This is the statement to execute
     */
    public $statement = "";

    /**
     * This is true when statement is ready to execute
     */
    public $ready = false;

    /**
     * Automatically prepend AND before next where statement
     */
    public $prependAnd = false;

    /**
     * Controls where statement
     */
    public $hasWhere = false;

    /*
    * Query data
    */
    protected $data = [];

    /*
    * Query data type
    */
    protected $dataTypes = "";

    /**
     * Amount of queries made
     *
     * @since 1.2.0
     * @var int
     */
    public $num_queries = 0;

    /**
     * Count of rows returned by previous query
     *
     * @since 0.71
     * @var int
     */
    public $num_rows = 0;

    /**
     * Count of affected rows by previous query
     *
     * @since 0.71
     * @var int
     */
    var $affected_rows = 0;

    /**
     * The ID generated for an AUTO_INCREMENT column by the previous query (usually INSERT).
     *
     * @since 0.71
     * @var int
     */
    public $insert_id = 0;

    /**
     * Last query made
     *
     * @since 0.71
     * @var string
     */
    var $last_query;

    /**
     * Results of the last query made
     *
     * @since 0.71
     * @var array|null
     */
    var $last_result;

    /**
     * MySQL result, which is either a resource or boolean.
     *
     * @since 0.71
     * @var mixed
     */
    protected $result;

    public $havingTypes;

    public $havingData;

    public $last_error;

    /**
     * Cached column info, for sanity checking data before inserting
     *
     * @since 4.2.0
     * @var array
     */
    protected $col_meta = array();

    const ARRAY_N = "ARRAY_N";

    const ARRAY_A = "ARRAY_A";

    const OBJECT = "OBJECT";

    const OBJECT_K = "OBJECT_K";

    private $order = "";

    private $limit = false;

    private $offset = false;

    private $group = "";

    private $having = "";

    private $isInsert = false;

    private $leadingSetComma = false;

    public function __construct()
    {
        parent::__construct();
    }

    public function __destruct() {
        $this->execute();
    }

    public function __call($method, $args) {
        if(method_exists($this, $method)) {
            return $this->$method($args[0]);
        }
    }

    public function __get($property) {
        return $this->$property;
    }

    public function halt() {
        $this->ready = false;
        return $this;
    }

    public function execute() {
        if(!$this->ready) {
            // not ready to execute.
            return false;
        }

        $this->ready = false; //prevent double execution

        $this->organize();

        $this->flush();

        $this->last_query = $this->statement;

        try {
            if(count($this->data)) {
                $stmt = $this->connection->prepare($this->statement);
                if($this->isInsert) {
                    $this->affected_rows = 0;
                    foreach ($this->data as $value) {
                        $value = array_values($value);
                        $stmt->bind_param($this->dataTypes, ...$value);
                        $stmt->execute();
                        $this->affected_rows++;
                    }
                } else {
                    $stmt->bind_param($this->dataTypes, ...$this->data);
                    $stmt->execute();
                    $this->result = $stmt->get_result();
                }
            } else {
                $this->result = $this->connection->query($this->statement);
            }
        }
        catch(Exception $e) {
            
        }
        $this->log_error($this->connection->error); 

        $this->num_queries++;

        if ( preg_match( '/^\s*(create|alter|truncate|drop)\s/i', $this->statement ) ) {
            return $this->result;
        } elseif ( preg_match( '/^\s*(insert|delete|update|replace)\s/i', $this->statement ) ) {
            
            // Take note of the insert_id
            if ( preg_match( '/^\s*(insert|replace)\s/i', $this->statement ) ) {
                $this->insert_id = $this->connection->insert_id;
                return $this->insert_id;
            }
            // Return number of rows affected
            return $this->affected_rows;
        } else {
            $num_rows = 0;
            while ( $row = mysqli_fetch_object( $this->result ) ) {
                $this->last_result[ $num_rows ] = $row;
                $num_rows++;
            }
            // Log number of rows the query returned
            // and return number of rows selected
            $this->num_rows = $num_rows;
            return $this;
        }
    }

    public function rows($output = self::OBJECT) {
        $new_array = [];
        if($output == self::OBJECT) {
            return $this->last_result;
        } elseif ( $output == self::OBJECT_K ) {
            // Return an array of row objects with keys from column 1
            // (Duplicates are discarded)
            if ( $this->last_result ) {
                foreach ( $this->last_result as $row ) {
                    $var_by_ref = get_object_vars( $row );
                    $key        = array_shift( $var_by_ref );
                    if ( ! isset( $new_array[ $key ] ) ) {
                        $new_array[ $key ] = $row;
                    }
                }
            }
            return $new_array;
        } else {
            if ( $this->last_result ) {
                foreach ( (array) $this->last_result as $row ) {
                    if ( $output == self::ARRAY_N ) {
                        // ...integer-keyed row arrays
                        $new_array[] = array_values( get_object_vars( $row ) );
                    } else {
                        // ...column name-keyed row arrays
                        $new_array[] = get_object_vars( $row );
                    }
                }
            }
            return $new_array;
        }
    }

    public function query($statement, $dataTypes = "", ...$data) {
        $this->resetStatement();
        $this->statement = $statement;
        if(count($data)) {
            $this->data = $data;
            $this->dataTypes = $dataTypes;
        }
        $this->ready = true;
        return $this;
    }

    public function flush() {
        $this->last_result   = array();
        $this->last_query    = null;
        $this->affected_rows = 0;
        $this->num_rows      = 0;
        $this->last_error    = '';
    }

    private function resetStatement() {
        $this->statement = "";
        $this->data = [];
        $this->dataTypes = "";
        $this->order = "";
        $this->limit = false;
        $this->offset = false;
        $this->isInsert = false;
        $this->leadingSetComma = false;
        $this->group = "";
        $this->having = "";
        $this->havingData = [];
        $this->havingTypes = "";
        $this->hasWhere = false;
        $this->prependAnd = false;
        return $this;
    }

    public function select($table, $column = "*") {
        $this->resetStatement();
        $this->statement .= "SELECT $column FROM `$table`";
        $this->ready = true;
        return $this;
    }

    public function selectDistinct($table, $column = "*") {
        $this->resetStatement();
        $this->statement .= "SELECT DISTINCT $column FROM `$table`";
        $this->ready = true;
        return $this;
    }

    public function delete($table) {
        $this->resetStatement();
        $this->statement .= "DELETE FROM `$table`";
        $this->ready = true;
        return $this;
    }

    public function insert($table, $dataTypes, ...$data) {
        $this->resetStatement();
        $columns = array_keys($data[0]);
        $values = "";
        foreach ($columns as $v) {
            $values .= " ?";
            if(end($columns) !== $v) $values .= ",";
        }
        $columns = implode(", ", $columns);
        $this->statement .= "INSERT INTO `$table` ($columns) VALUES ($values)";
        $this->data = $data;
        $this->dataTypes = $dataTypes;
        $this->isInsert = true;
        $this->ready = true;
        return $this;
    }

    public function update($table) {
        $this->resetStatement();
        $this->statement .= "UPDATE `$table`";
        $this->ready = false;
        return $this;
    }

    public function set($column, $value, $type = false) {
        $this->statement .= ($this->leadingSetComma) ? "," : " SET";
        $this->statement .= " $column = ?";
        array_push($this->data, $value);
        $this->dataTypes .= !$type ? $this->evaluateData($value)->valueType : $type;
        $this->leadingSetComma = true;
        $this->ready = true;
        return $this;
    }

    public function where($column, $value, $type = false, $rel = "LIKE") {

        if(!$this->hasWhere)
            $this->statement .= " WHERE";

        if($this->prependAnd)
            $this->and();

        $this->statement .= " `$column` $rel ?";

        array_push($this->data, $value);

        $this->dataTypes .= !$type ? $this->evaluateData($value)->valueType : $type;;

        $this->prependAnd = true;

        $this->hasWhere = true;

        $this->ready = true;

        return $this;
    }

    public function whereGroup(array $meta = [], string | array $rel = "LIKE") {
        if(!Operations::count($meta)) return $this;
        foreach ($meta as $meta_name => $meta_value) {
            $rel = (is_array($rel) && isset($rel[$meta_name])) ? $rel[$meta_name] : $rel;
            $this->where(
                $meta_name, 
                $meta_value, 
                $this->evaluateData($meta_value)->valueType, 
                $rel
            );
        }
        return $this;
    }

    public function whereIn($column, $type, ...$values) {
        if(!count($values)) {
            return $this;
        }
        if(!$this->hasWhere)
            $this->statement .= " WHERE";

        if($this->prependAnd)
            $this->and();

        $vString = "";
        foreach($values as $v) {
            $vString .= " ?";
            if(end($values) !== $v) $vString .= ",";
            array_push($this->data, $v);
            $this->dataTypes .= $type;
        }
        $this->statement .= " $column IN ($vString)";

        $this->prependAnd = true;

        $this->hasWhere = true;

        $this->ready = true;
        return $this;
    }

    public function whereNotIn($column, $type, ...$values) {
        if(!count($values)) {
            return $this;
        }
        if(!$this->hasWhere)
            $this->statement .= " WHERE";

        if($this->prependAnd)
            $this->and();

        $vString = "";
        foreach($values as $v) {
            $vString .= " ?";
            if(end($values) !== $v) $vString .= ",";
            array_push($this->data, $v);
            $this->dataTypes .= $type;
        }
        $this->statement .= " $column NOT IN ($vString)";

        $this->prependAnd = true;

        $this->hasWhere = true;

        $this->ready = true;
        return $this;
    }

    public function orderBy($column, $order = 'ASC') {
        $order = $order == 'ASC' ? 'ASC' : 'DESC';
        $this->order .= " ORDER BY $column $order";
        return $this;
    }

    public function limit($limit) {
        $this->limit = $limit;
        return $this;
    }

    public function offset($offset) {
        $this->offset = $offset;
        return $this;
    }

    public function groupBy($column) {
        $this->group = " GROUP BY $column";
        return $this;
    }

    public function having($expression, $types = "", ...$data) {
        $this->having = " HAVING $expression";
        $this->havingTypes = $types;
        $this->havingData = $data;
        return $this;
    }

    private function organize() {

        $this->statement .= $this->group;

        if($this->having !== "") {
            $this->statement .= $this->having;

            if(count($this->havingData) && is_array($this->havingData)) {
                $this->data = array_merge($this->data, $this->havingData);

                $this->dataTypes .= $this->havingTypes;
            }
        }

        $this->statement .= $this->order;

        if($this->limit) {

            $this->statement .= " LIMIT ?";

            array_push($this->data, $this->limit);

            $this->dataTypes .= "i";

            if($this->offset) {

                $this->statement .= " OFFSET ?";

                array_push($this->data, $this->offset);

                $this->dataTypes .= "i";
            }

        }

    }

    public function openGroup() {
        $this->statement .= "(";
        $this->ready = false;
        $this->prependAnd = false;
        return $this;
    }

    public function closeGroup() {
        $this->statement .= ")";
        $this->ready = true;
        return $this;
    }

    public function and() {
        $this->statement .= " AND";
        $this->prependAnd = false;
        $this->ready = false;
        return $this;
    }

    public function or() {
        $this->statement .= " OR";
        $this->prependAnd = false;
        $this->ready = false;
        return $this;
    }

    public function log_error($error = "") {
        if($error !== "") {
            $file = 'sql-errors.txt';
            if(!file_exists($file)) {
                file_put_contents($file, "SQL Error Log".PHP_EOL);
            }
            $error = date("d-m-Y h:i:sA")."\t".$error;
            if($this->statement !== "") {
                $error .= "\nSTATEMENT: \t\t\t\t\"".$this->statement."\"";
            }
            $error .= "\n";
            $fp = fopen($file, 'a');//opens file in append mode  
            fwrite($fp, $error.PHP_EOL); 
            fclose($fp);
        }
    }

    /**
     * Real escape, using mysqli_real_escape_string() or mysql_real_escape_string()
     *
     * @see mysqli_real_escape_string()
     * @see mysql_real_escape_string()
     * @since 2.8.0
     *
     * @param  string $string to escape
     * @return string escaped
     */
    public function _real_escape( $string ) {
        if ( $this->connection ) {
            $escaped = $this->connection->real_escape_string( $string );
        } else {
            $escaped = addslashes( $string );
        }

        return $escaped;
    }

    public function checkTableExist($table_name) {
        $statement = "SELECT COUNT(*) AS tables
                    FROM information_schema.tables 
                    WHERE table_schema = DATABASE()
                    AND table_name = ?";
        $res = $this->query($statement, 's', $table_name)->execute()->rows();
        return $res[0]->tables > 0 ? true : false;
    }

}