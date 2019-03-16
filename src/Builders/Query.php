<?php

namespace Codingjungle\Database\Builders;

/**
 * @brief      Query Class
 * @author     Michael S. Edwards
 * @package    Zephyr
 * @subpackage Codingjungle\Database\Builders
 */
abstract class Query implements \Countable
{
    /**
     * @var \Codingjungle\Database
     */
    protected $db = null;
    /**
     * SQL Query
     * @var null
     */
    protected $query = null;
    /**
     * Table to Query on
     * @var null|string
     */
    protected $table = null;
    /**
     * joins from \Codingjungle\Database\Collectors\Join
     * @var null|array
     */
    protected $joins = null;

    /**
     * Since PDO doesn't return the query with the binds, we need to do it manually!
     * @var null|array
     */
    public $markers = null;
    /**
     * an array of the binds for prepared queries
     * @var array
     */
    public $binds = [];
    /**
     * @var \PDOStatement
     */
    protected $stmt = null;
    /**
     * after execution, this is the parsed query with binds.
     * @var null
     */
    protected $proccessedQuery = null;

    /**
     * Query constructor.
     *
     * @param \Codingjungle\Database $db
     * @param string                 $table
     */
    public function __construct(\Codingjungle\Database $db, string $table )
    {
        $this->db = $db;
        $this->table = $table;
    }

    /**
     * compiles the query if there is one, mainly here for the Select Class
     */
    abstract protected function compile();

    /**
     * adds a join for the query
     *
     * @param string $table
     * @param string $on
     * @param string $type
     * @param bool   $using
     *
     * @return $this
     */
    public function join(string $table, string $on, string $type = "left", bool $using = false)
    {
        $this->joins[] = ['table' => $table, 'on' => $on, 'type' => $type, 'using' => $using];

        return $this;
    }

    /**
     * compiles joins into $query
     *
     * @param string $query
     */
    public function compileJoins()
    {
        if (\count($this->joins)) {
            foreach ($this->joins as $join) {
                if( $this instanceof Insert){
                    $this->query .= ' (SELECT * FROM ' . $this->db->prefix . $this->table;
                }
                $table = $this->db->prefix . $join[ 'table' ];
                $on = $join[ 'on' ];
                $type = \mb_strtolower($join[ 'type' ]);
                $using = false;
                if (isset($join[ 'using' ]) && $join['using']) {
                    $using = true;
                }
                switch ($type) {
                    case 'inner':
                    case 'cross':
                        $this->query .= ' INNER JOIN ';
                        break;
                    case 'straight':
                        $this->query .= ' STRAIGHT_JOIN ';
                        if ($using) {
                            throw new \InvalidArgumentException("can't use a straight join on using!");
                        }
                        break;
                    case 'left':
                    case 'right':
                        $this->query .= ' ' . \mb_strtoupper($type) . ' JOIN ';
                        break;
                    case 'join':
                        $this->query .= ' JOIN ';
                        break;
                }

                $this->query .=  ( $this->db->prefix ) ? $table . ' AS '. $join['table'] : $table;

                if ($using) {
                    $this->query .= ' USING (' . $on . ') ';
                } else {
                    $this->query .= ' ON (' . $on .') ';
                }
                if( $this instanceof Insert ){
                    $this->query .= ') ';
                }
            }

        }
    }

    /**
     * Preps the binds
     *
     * @param array $where should contain the query and ele
     *
     * @return string the where statement
     */
    public function buildWhereBinds(array $where): string
    {
        $return = array_shift($where);

        //do we have anything to bind?
        if (\is_array($where) and \count($where)) {
            foreach( $where as $k => $v ) {
                $this->addBinds($k, $v);
            }
        }

        return $return;
    }

    /**
     * gathers all the binds before running in binds()
     *
     * @param array $binds an array of the binds to process, can be assoc or numerical array
     */
    public function addBinds($key, $val)
    {
        if( $key ){
            $this->binds[$key] = $val;
        }
        else {
            $this->binds[] = $val;
        }
    }

    /**
     * builds the binds for prepared statement
     *
     * @param \PDOStatement|null $stmt
     */
    public function compileBinds( $stmt = null)
    {
        if ($this->binds) {
            $i = 1;
            $this->markers = [];

            foreach ($this->binds as $key => $value) {
                $type = null;

                if (!\is_int($key)) {
                    $param = $key;
                } else {
                    $param = $i;
                }

                $i++;

                if (\is_null($type)) {
                    switch (true) {
                        case \is_int($value):
                            $type = \PDO::PARAM_INT;
                            break;
                        case \is_bool($value):
                            $type = \PDO::PARAM_BOOL;
                            break;
                        case \is_null($value):
                            $type = \PDO::PARAM_NULL;
                            break;
                        default:
                            $type = \PDO::PARAM_STR;
                            break;
                    }
                }

                $this->markers[ $param ] = [
                    'value' => $value,
                    'type'  => $type,
                ];

                if( $stmt instanceof  \PDOStatement ) {
                    $stmt->bindValue($param, $value, $type);
                }
            }
        }
    }

    /**
     * Retrieves the query that last ran
     *
     * @return string
     */
    public function getQuery(): string
    {
        $this->proccessedQuery = null;
        if( !$this->query ) {
            $this->compile();
            $this->stmt = $this->db->prepare($this->query);
            $this->stmt->setFetchMode(\PDO::FETCH_ASSOC);
            $this->compileBinds($this->stmt);
        }
        if (\count($this->markers)) {
            $params = $this->markers;
            $processedQuery = $this->query;
            if ($params) {
                ksort($params);
                foreach ($params as $key => $value) {
                    $replaceValue = (is_array($value)) ? $value : ['value' => $value, 'type' => \PDO::PARAM_STR];
                    $replaceValue = $this->prepareValue($replaceValue);
                    $processedQuery = $this->replaceMarker($processedQuery, $key, $replaceValue);
                }
            }
            $this->proccessedQuery = $processedQuery;

            return $processedQuery;
        } else {
            $this->proccessedQuery = $this->query;

            return $this->query;
        }
    }

    /**
     * Prepare values for replacement in query
     *
     * @param  array $value value to be prepped
     *
     * @return string|null
     */
    protected function prepareValue(array $value)
    {
        if ($value[ 'value' ] === null) {
            return null;
        }

        if (\PDO::PARAM_INT === $value[ 'type' ]) {
            return (int)$value[ 'value' ];
        }

        return $this->db->quote($value[ 'value' ]);
    }

    /**
     * replaces the marker with the value in the query
     *
     * @param string $queryString the query string
     * @param string $marker      marker to be replaced
     * @param string $replValue   value to replace the marker
     *
     * @return string
     */
    protected function replaceMarker(string $queryString, string $marker, string $replValue): string
    {
        if (is_numeric($marker)) {
            $marker = "\?";
        } else {
            $marker = (preg_match("/^:/", $marker)) ? $marker : ":" . $marker;
        }

        $testParam = "/({$marker}(?!\w))(?=(?:[^\"']|[\"'][^\"']*[\"'])*$)/";

        return preg_replace($testParam, $replValue, $queryString, 1);
    }

    /**
     * returns affected row count from a query
     * @return int
     * @throws \Exception
     */
    public function count(): int
    {
        $this->stmt();
        $this->stmt->execute();
        $return = $this->stmt->rowCount();
        $this->stmt->closeCursor();

        return $return;
    }

    /**
     * prepares a query but doesn't execute, might be useful to some to be able to perform other PDOStatment methods on
     * it
     * @return \PDOStatement
     * @throws \Exception
     */
    public function stmt(): \PDOStatement
    {
        if (!$this->query) {
            $this->compile();
        }

        if ($this->query) {
            if ($this->stmt == null) {
                $this->stmt = $this->db->prepare($this->query);
            }

            $this->compileBinds($this->stmt);

            return $this->stmt;
        } else {
            throw new \Exception("The query is empty!");
        }
    }

    /**
     * executes a query
     * @return \PDOStatement
     * @throws \Exception
     */
    public function execute()
    {
        if (!$this->query) {
            $this->compile();
        }

        if ($this->query) {
            if ($this->stmt == null) {
                $this->stmt = $this->db->prepare($this->query);
                $this->stmt->setFetchMode(\PDO::FETCH_ASSOC);
                $this->compileBinds($this->stmt);
            }

            $this->stmt->execute();
            $error = $this->stmt->errorInfo();
            if (isset($error[ 0 ]) and $error[ 0 ] != 0) {
                throw new \Exception('SQLSTATE[' . $error[ 0 ] . '][' . $error[ 1 ] . '] ' . $error[ 2 ], $error[ 1 ]);
            }

            return $this->stmt;
        } else {
            throw new \Exception('The query is empty!');
        }
    }
}
