<?php

namespace Codingjungle;

use Codingjungle\Database\Builders\Delete;
use Codingjungle\Database\Builders\Insert;
use Codingjungle\Database\Builders\Replace;
use Codingjungle\Database\Builders\Select;
use Codingjungle\Database\Builders\Update;
use Codingjungle\Database\Schema;


/**
 * @brief      Db Class
 * @author     Michael S. Edwards
 * @package    Zephyr
 * @subpackage Codingjungle
 */
class Database extends \PDO
{
    /**
     * multiton store
     * @var array
     */
    protected static $multiton = [];

    /**
     * tables prefix
     * @var string
     */
    public $prefix = '';

    /**
     * default engine type
     * @var string
     */
    public $engine = 'innodb';

    /**
     * @var string
     */
    public $collation = 'utf8mb4_unicode_ci';

    /**
     * @var string
     */
    public $charset = 'utf8mb4';

    /**
     * binds store
     * @var array
     */
    protected $binds = [];

    /**
     * query store
     * @var string
     */
    protected $query = null;

    /**
     * setups a instance for the db class
     *
     * @param string $id
     * @param array  $config
     *
     * @return static
     */
    public static function forge(string $id = "default", array $config = []): \Codingjungle\Database
    {
        try {
            if (!isset(static::$multiton[ $id ])) {

                if( !count( $config ) ){
                    $config = require __DIR__.'/config.settings.php';
                }

                $host = $config[ 'host' ] ?? '127.0.0.1';
                $dbName = $config[ 'database' ] ?? '';
                $userName = $config[ 'username' ];
                $password = $config[ 'password' ];
                $socket = $config[ 'socket' ] ?? null;
                $port = $config[ 'port' ] ?? 3306;
                $charset = $config['charset'] ?? 'utf8mb4';
                $collation = $config['collation'] ?? 'utf8mb4_unicode_ci';
                $engine = $config['engine'] ?? 'InnoDB';
                $dsn[] = "mysql:";

                if ($host === "localhost") {
                    $host = '127.0.0.1';
                }

                if ($host) {
                    $dsn[] = "host={$host};port={$port};";
                } else {
                    if ($socket) {
                        $dsn[] = "unix_socket={$socket}";
                    } else {
                        throw new \InvalidArgumentException('Socket or Host needs to be configured in conf.php');
                    }
                }

                if ($dbName) {
                    $dsn[] = "dbname={$dbName};";
                }

                $dsn[] = "charset=" . $charset . ";";
                $dsn = \implode('', $dsn);

                $db = new static($dsn, $userName, $password);
                $db->setAttribute(static::ATTR_ERRMODE, static::ERRMODE_EXCEPTION);
                $db->setAttribute(static::ATTR_DEFAULT_FETCH_MODE, static::FETCH_ASSOC);

                if (defined( 'DEVELOPMENT') && DEVELOPMENT) {
                    $db->exec('SET SQL_MODE="ANSI_QUOTES,ONLY_FULL_GROUP_BY,STRICT_ALL_TABLES"');
                    $db->exec("SET NAMES '" . $charset . "' COLLATE '" . $collation . "'");
                }

                $db->engine = $engine;
                $db->charset = $charset;
                $db->collation = $collation;

                if ( isset($config[ 'prefix' ]) && $config['prefix'] ) {
                    $db->prefix = $config[ 'prefix' ] ;
                }

                static::$multiton[ $id ] = $db;
            }

            return static::$multiton[ $id ];
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    /**
     * this is an override for the query() for PDO to allow profiler to work
     * @return mixed
     */
    public function query()
    {
        $args = func_get_args();
        $statement = $args[ 0 ];
        $this->query = $statement;
        $query = call_user_func_array('parent::query', $args);
        return $query;
    }

    /**
     * returns the query, if you execute query()
     * @return string
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * returns a select object
     * @param string|null $table
     * @return Select
     */
    public function select(string $table): \Codingjungle\Database\Builders\Select
    {
        return new Select( $this, $table);
    }

    /**
     * returns a Insert object to run a replace query
     * @param string $table
     * @return Replace
     */
    public function insert(string $table): \Codingjungle\Database\Builders\Insert
    {
        return new Insert($this, $table);
    }

    /**
     * returns a Replace object to run a replace query
     * @param string $table
     * @return Replace
     */
    public function replace(string $table): \Codingjungle\Database\Builders\Replace
    {
        return new Replace($this, $table);
    }


    /**
     * returns a Delete object for deleting rows/truncating table
     * @param string $table
     * @return Delete
     */
    public function delete(string $table ): \Codingjungle\Database\Builders\Delete
    {
        return new Delete($this, $table);
    }

    /**
     * returns a Update object for updating records
     * @param $table
     *
     * @return Update
     */
    public function update($table): \Codingjungle\Database\Builders\Update
    {
        return new Update( $this, $table );
    }

    /**
     * @return \Codingjungle\Database\Schema
     */
    public function schema(){
        return new Schema($this);
    }
}
