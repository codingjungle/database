<?php

namespace Codingjungle\Database;

/**
 * a base model class for interacting with a database table
 * Class Model
 * @package Codingjungle\Database
 */
abstract class Model
{
    /**
     * must be declared in every subclass.
     * @var array
     */
    protected static $records = [];

    /**
     * table used by the model. required to be declared and set in every subclass.
     * @var null
     */
    protected static $table = null;

    /**
     * the primary id field in the database where looks up occur. if you use a prefix, don't use it here.
     * @var string
     */
    protected static $primary = 'id';

    /**
     * the name of the bitwise column
     * @var null
     */
    protected static $bitwiseColumn = 'bitwise';

    /**
     * bitwise options
     * @var array
     */
    protected static $bitwiseOptions = [];

    /**
     * joins you would want the model to always execute
     * @var null
     */
    protected static $joins = [];

    /**
     * data store for column names/values
     * @var array
     */
    protected $data = [];

    /**
     * if anything has changed so it can update the db entry
     * @var array
     */
    protected $changed = [];

    /**
     * is this a new record or one needing updated.
     * @var bool
     */
    protected $new = true;

    /**
     * Model constructor.
     * @throws \Exception
     */
    final public function __construct()
    {
        if (ENVIRONMENT == 'dev') {
            $r = new \ReflectionClass($this);

            if ( $r->getParentClass()->getName() == 'Codingjungle\Database\Model'  && $r->getProperty('records')->getDeclaringClass()->getName() != get_called_class()) {
                throw new \Exception('This class, ' . get_called_class() . ', is missing static property $records, please declare it. It is required to be declared by all children classes of Codingjungle\Database\Model!');
            }
            if ( $r->getParentClass()->getName() == 'Codingjungle\Database\Model'  && $r->getProperty('table')->getDeclaringClass()->getName() !=
            get_called_class() ) {
                throw new \Exception('This class, ' . get_called_class() . ', is missing static property $table, please declare it. It is required to be declared by all children classes of Codingjungle\Database\Model!');
            }
        }
    }

    /**
     * retrieves the first record of the table that matches the ID on the primary field
     *
     * @param $id
     * @throws \OutOfBoundsException
     * @return static
     */
    public static function first($id):\Codingjungle\Database\Model
    {
        try {
            $field = static::$primary;
            $table = null;
            $table = static::$table;

            if (isset(static::$records[ $id ])) {
                return static::$records[ $id ];
            }

            $where = null;
            $where = [
                $field . ' = :id',
                'id' => $id,
            ];

            $query = static::db()->select($table)->where($where);

            if (\is_array(static::$joins)) {
                foreach (static::$joins as $val) {
                    $query->join($val[ 'table' ], $val[ 'on' ], $val[ 'type' ] ?? "left", $val[ 'using' ] ?? false);
                }
            }

            return static::create($query->first(), true);
        } catch (\UnderflowException $e) {
            throw new \OutOfRangeException;
        }
    }

    /**
     * @param array $where
     *
     * @return array|mixed
     */
    public static function findOne( array $where ){
        return static::create( static::db()->select(static::$table)->where( $where )->first() );
    }

    /**
     * this isn't here cause i'm lazy, this is here to allow a dev to use a different DB config if they desire.
     * @return \Codingjungle\Database\Database
     */
    protected static function db()
    {
        return \Codingjungle\Database\Database::forge();
    }

    /**
     * creates an object out of the data passed to it.
     *
     * @param array $data
     * @param bool  $store
     * @throws \BadFunctionCallException
     * @return static
     */
    public static function create(array $data, bool $store = true): \Codingjungle\Database\Model
    {
        $id = static::$primary;
        if (isset($data[ $id ])) {
            if (isset(static::$records[ $data[ $id ] ])) {
                return static::$records[ $data[ $id ] ];
            }
        } else {
            throw new \BadMethodCallException('Data missing primary ID field: ' . $id);
        }

        $model = new static;
        $model->new = false;
        $model->data = $data;

        if (\method_exists($model, 'init')) {
            $model->init();
        }

        if ($store) {
            static::$records[ $data[ $id ] ] = $model;
        }

        return $model;
    }

    /**
     * returns all the records from this database without limit, if you need to limit or pass a where, use find.
     * @return \Codingjungle\Database\Model\Iterator|array
     */
    public static function all()
    {
        $table = static::$table;
        $query = static::db()->select($table);

        if (\is_array(static::$joins)) {
            foreach (static::$joins as $val) {
                $query->join($val[ 'table' ], $val[ 'on' ], $val[ 'type' ] ?? "left", $val[ 'using' ] ?? false);
            }
        }

        return new \Codingjungle\Database\Model\Iterator($query->execute(), static::class);
    }

    /**
     * @param array       $where
     * @param string|null $order
     * @param int         $limit
     * @param int|null    $offset
     * @param array       $joins
     * @param null        $having
     * @param null        $groupBy
     *
     * @return \Codingjungle\Database\Model\Iterator
     */
    public static function find(
        array $where,
        string $order = null,
        int $limit = 25,
        int $offset = null,
        array $joins = [],
        $having = null,
        $groupBy = null
    ): \Codingjungle\Database\Model\Iterator {
        $table = static::$table;

        if ($order == null) {
            $order = static::$primary . ' ASC';
        }

        $sql = static::db()
            ->select($table)
            ->where($where)
            ->having( $having )
            ->groupBy($groupBy)
            ->order($order)
            ->limit($limit, $offset);

        $joins = array_merge($joins, static::$joins);

        if (\count($joins)) {
            foreach ($joins as $val) {
                $sql->join($val[ 'table' ], $val[ 'on' ], $val[ 'type' ] ?? "left", $val[ 'using' ] ?? false);
            }
        }

        return new \Codingjungle\Database\Model\Iterator($sql->execute(), static::class);
    }

    /**
     * returns the number of records in a table.
     *
     * @param array $where
     * @param array $joins the joins array if there are any
     *
     * @return int
     */
    public static function count(array $where = [], array $joins = []): int
    {
        $table = static::$table;

        $sql = static::db()->select($table);

        if (count($where)) {
            $sql->where($where);
        }

        $joins = array_merge($joins, static::$joins);
        if (\count($joins)) {
            foreach ($joins as $val) {
                $sql->join($val[ 'table' ], $val[ 'on' ], $val[ 'type' ] ?? "left", $val[ 'using' ] ?? false);
            }
        }

        return $sql->count();
    }

    /**
     * magic method to retrieve 'props' from $data/bitwise and/or magic methods like get__methodName.
     *
     * @param $key
     *
     * @return bool|mixed|null
     */
    public function __get(string $key)
    {
        if (\method_exists($this, 'get__' . $key)) {
            $func = 'get__' . $key;

            return $this->{$func}();
        } else {
            if (isset(static::$bitwiseOptions[ $key ]) and isset($this->data[ static::$bitwiseColumn ])) {
                return (bool)(static::$bitwiseOptions[ $key ] & $this->data[ static::$bitwiseColumn ]);
            } else {
                if (isset($this->data[ $key ])) {
                    return $this->data[ $key ];
                }
            }
        }

        return null;
    }

    /**
     * magic method to set $key/$value to the $data prop or $bitwise or to a magic set__method
     *
     * @param $key
     * @param $value
     */
    public function __set(string $key, $value)
    {
        if (\method_exists($this, 'set__' . $key)) {
            $func = 'set__' . $key;
            $this->{$func}($value);
        } else {
            if (isset(static::$bitwiseOptions[ $key ]) and isset($this->data[ static::$bitwiseColumn ])) {
                if ($value) {
                    if (isset($this->data[ static::$bitwiseColumn ])) {
                        $this->changed[ static::$bitwiseColumn ] = $this->data[ static::$bitwiseColumn ];
                        $this->changed[ static::$bitwiseColumn ] |= static::$bitwiseOptions[ $key ];
                    }
                    $this->data[ static::$bitwiseColumn ] |= static::$bitwiseOptions[ $key ];
                } else {
                    if (isset($this->data[ static::$bitwiseColumn ])) {
                        $this->changed[ static::$bitwiseColumn ] = $this->data[ static::$bitwiseColumn ];
                        $this->changed[ static::$bitwiseColumn ] &= ~static::$bitwiseOptions[ $key ];
                    }
                    $this->data[ static::$bitwiseColumn ] &= ~static::$bitwiseOptions[ $key ];
                }
            } else {
                if (isset($this->data[ $key ])) {
                    $this->changed[ $key ] = $value;
                }
                $this->data[ $key ] = $value;
            }
        }
    }

    /**
     * checks if key is set in $data
     *
     * @param $key
     *
     * @return bool
     */
    public function __isset(string $key): bool
    {
        if (\method_exists($this, 'get__' . $key)) {
            $func = 'get__' . $key;

            return $this->$func() !== null;
        }

        return isset($this->data[ $key ]);
    }

    /**
     * deletes current record
     * @return int
     */
    public function delete(): int
    {
        $field = static::$primary;
        if (!$this->new) {
            $table = static::$table;

            $delete = static::db()->delete($table)->where( [$field . ' = :id', 'id' => $this->{$field}]);
            $joins = static::$joins;

            if (\count($joins)) {
                foreach ($joins as $val) {
                    $delete->join($val[ 'table' ], $val[ 'on' ], $val[ 'type' ] ?? "left", $val[ 'using' ] ?? false);
                }
            }

            $delete->execute();
        } else {
            throw new \UnderflowException;
        }
    }

    /**
     * creates or updates the record
     */
    public function save()
    {
        $field = static::$primary;
        if ($this->new) {
            $data = $this->data;
            $save = [];
            foreach ($data as $k => $val) {
                $save[ $k ] = $val;
            }

            $id = $this->insert($save);

            if ($this->{$field} === null and $id) {
                $this->{$field} = $id;
            }

            $this->new = false;

            static::$records[ $this->{$field} ] = $this;
        } else {
            $data = $this->changed;
            $save = [];
            foreach ($data as $k => $val) {
                $save[ $k ] = $val;
            }
            $this->changed = [];
            $this->update($save);
        }

    }

    /**
     * insert data into the table
     *
     * @param array $data
     *
     * @return int
     */
    private function insert(array $data): int
    {
        $table = static::$table;
        return static::db()->insert($table)->values($data)->execute();
    }

    /**
     * updates a record in the table
     * @throws \UnderflowException
     *
     * @param array
     *
     * @return int
     */
    private function update(array $data): int
    {
        $field = static::$primary;
        if (!$this->new) {
            $table = static::$table;
            return static::db()
                ->update($table)
                ->set( $data )
                ->where( [$field . ' = :id', 'id' => $this->{$field}])
                ->execute();
        } else {
            throw new \UnderflowException;
        }
    }

    /**
     * returns the $data prop
     * @return array
     */
    public function get__data(): array
    {
        return $this->data;
    }

}
