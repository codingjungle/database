<?php

namespace Codingjungle\Database;

class Schema
{

    /**
     * @var \Codingjungle\Database
     */
    protected $db = null;

    /**
     * @var bool
     */
    protected $install = true;

    /**
     * @var array
     */
    protected $alt = [
        'addTable'     => 'dropTable',
        'addColumn'    => 'dropColumn',
        'addIndex'     => 'dropIndex',
        'changeTable'  => 'noAlt',
        'changeColumn' => 'noAlt',
    ];

    /**
     * Schema constructor.
     *
     * @param \Codingjungle\Database $db
     * @param bool                   $uninstall
     */
    public function __construct(\Codingjungle\Database $db, bool $uninstall = false)
    {
        $this->db = $db;
        if ($uninstall) {
            $this->install = false;
        }
    }

    /**
     * @param string $table
     * @param string $index
     *
     * @return bool
     */
    public function indexExists(string $table, string $index): bool
    {
        try {
            $table = $this->db->prefix . $table;
            $query = 'SHOW INDEXES FROM ' . $table . ' WHERE Key_name LIKE ' . $index;
            $this->db->query($query);

            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * @param string $name
     */
    public function addDatabase(string $name)
    {
        try {
            $query = 'CREATE DATABASE ' . $name . ' CHARACTER SET ' . Db::CHARSET . ' COLLATE ' . Db::COLLATION;
            $this->db->query($query);
            $this->db->exec("USE {$name}");
        } catch (\PDOException $e) {
        }
    }

    /**
     * @param string $table
     *
     * @return bool
     */
    public function tableExists(string $table): bool
    {
        try {
            $table = $this->db->prefix . $table;
            $this->db->query("SELECT 1 FROM {$table} LIMIT 1");

            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * @param string $table
     * @param string $column
     *
     * @return bool
     */
    public function columnExists(string $table, string $column): bool
    {
        try {
            $table = $this->db->prefix . $table;
            $query = 'SHOW COLUMNS FROM ' . $table . ' LIKE ' . $column;
            $this->db->query($query);

            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    public function addTable(array $data): bool
    {

        if (!$this->tableExists($data['table'])) {
            try {
                $table = $this->db->prefix . $data[ 'table' ];

                if (isset($data[ 'temp' ]) and $data[ 'temp' ]) {
                    $create = 'CREATE TEMPORARY';
                } else {
                    $create = 'CREATE';
                }

                if (isset($data[ 'engine' ])) {
                    $engine = $data[ 'engine' ];
                } else {
                    $engine = $this->db->engine;
                }

                $columns = [];

                foreach ($data[ 'columns' ] as $c) {
                    $columns[] = "\n" . $this->buildColumn($c);
                }

                if (isset($data[ 'indices' ])) {
                    foreach ($data[ 'indices' ] as $index) {
                        $type = \mb_strtoupper($index[ 'type' ]);

                        if ($type == "PRIMARY") {
                            $type .= ' KEY';
                        } else {
                            if ($type == "INDEX") {
                                $type = 'KEY';
                            }
                        }

                        if (!\is_array($index[ 'columns' ])) {
                            $cols = '`' . $index[ 'columns' ] . '`';
                            if (!isset($index[ 'name' ])) {
                                $name = $index[ 'columns' ];
                            } else {
                                $name = $index[ 'name' ];
                            }
                        } else {
                            $cols = \implode(',', \array_map(function ($col) {
                                return '`' . $col . '`';
                            }, $index[ 'columns' ]));
                            if (!isset($index[ 'name' ])) {
                                $first = \array_shift($index[ 'columns' ]);
                                $hash = \mb_substr(\md5($cols), 0, 5);
                                $name = $first . '_' . $hash;
                            } else {
                                $name = $index[ 'name' ];
                            }
                        }
                        $columns[] = "\n" . $type . ' `' . $name . '` (' . $cols . ')';
                    }
                }

                $columns = \implode(",", $columns);

                $format = "%s TABLE `%s` ( %s \n) ENGINE=" . $engine . ' DEFAULT CHARSET=' . Db::CHARSET . ' COLLATE=' . Db::COLLATION;

                if (isset($data[ 'comment' ])) {
                    $format .= ' COMMENT ' . $this->db->quote($data[ 'comment' ]);
                }

                $format = \sprintf($format, $create, $table, $columns);
                $this->db->query($format);

                return true;
            } catch (\PDOException $e) {
                if( ENVIRONMENT == 'dev'){
                    throw $e;
                }
                return false;
            }
        } else {
            $return = false;
            if (isset($data[ 'columns' ]) && \is_array($data[ 'columns' ])) {
                //table exist so lets check the tables
                foreach ($data[ 'columns' ] as $val) {
                    $this->addColumn($data[ 'table' ], $val);
                }
                $return = true;
            }

            if (isset($data[ 'indices' ]) and \is_array($data[ 'indices' ])) {
                foreach ($data[ 'indices' ] as $val) {
                    $this->addColumn($data[ 'table' ], $val);
                }
                $return = true;
            }

            return $return;
        }
    }

    /**
     * @param string $table
     * @param array  $column
     *
     * @return bool
     */
    public function addColumn(string $table, array $column): bool
    {
        try {
            if ($this->columnExists($table, $column[ 'name' ])) {
                return false;
            }
            $table = $this->db->prefix . $table;
            $query = 'ALTER TABLE ' . $table . ' ADD COLUMN ' . $this->buildColumn($column);
            $this->db->query($query);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param string $table
     * @param string $index
     *
     * @return bool
     */
    public function addIndex(string $table, array $index): bool
    {
        if ($this->indexExists($table, $index[ 'name' ])) {
            return false;
        }
        $table = $this->db->prefix . $table;
        $type = (\mb_strtoupper($index[ 'type' ]) == 'INDEX')
            ? \mb_strtoupper($index[ 'type' ])
            : \mb_strtoupper($index[ 'type' ]) . ' INDEX';

        $columns = implode(',', $index[ 'columns' ]);
        $query = 'CREATE ' . $type . ' ' . $index[ 'name' ];
        $query .= ' ON ' . $table . ' ( ' . $columns . ' )';

        return $this->db->query($query);
    }

    /**
     * @param string $table
     * @param string $tableNew
     *
     * @return bool
     */
    public function changeTable(string $table, string $tableNew): bool
    {
        try {
            $table = $this->db->prefix . $table;
            $tableNew = $this->db->prefix . $tableNew;
            $query = 'RENAME TABLE ' . $table . ' TO ' . $tableNew;
            $this->db->query($query);

            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * @param string $table
     * @param string $oldColumn
     * @param array  $data
     *
     * @return bool
     */
    public function changeColumn(string $table, string $oldColumn, array $data): bool
    {
        try {
            $table = $this->db->prefix . $table;
            $query = 'ALTER TABLE `' . $table . ' CHANGE COLUMN ' . $oldColumn . $this->buildColumn($data);
            $this->db->query($query);

            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * @param array $tables
     *
     * @return bool
     */
    public function dropTable(array $tables): bool
    {
        try {
            $prefix = $this->db->prefix;
            $tables = implode(',', array_map(function ($table) use ($prefix) {
                return '`' . $prefix . $table . '`';
            }, $tables));
            $query = 'DROP TABLE IF EXISTS ' . $tables;
            $this->db->query($query);

            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * @param string $table
     * @param string $column
     *
     * @return bool
     */
    public function dropColumn(string $table, string $column): bool
    {
        try {
            $table = $this->db->prefix . $table;
            $query = 'ALTER TABLE ' . $table . ' DROP COLUMN ' . $column;
            $this->db->query($query);

            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * @param string $table
     * @param string $index
     *
     * @return bool
     */
    public function dropIndex(string $table, string $index): bool
    {
        try {
            $table = $this->db->prefix . $table;
            $query = 'ALTER TABLE ' . $table . ' DROP INDEX ' . $index;
            $this->db->query($query);

            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * @param array $data
     *
     * @return string
     */
    private function buildColumn(array $data): string
    {
        $column = '`' . $data[ 'name' ] . '` ' . \strtoupper($data[ 'type' ]);

        $lengthColumns = [
            'binary',
            'bit',
            'bigint',
            'char',
            'decimal',
            'double',
            'float',
            'int',
            'integer',
            'numeric',
            'mediumint',
            'real',
            'smallint',
            'tinyint',
            'varchar',
            'varbinary',
        ];

        if (\in_array($data[ 'type' ], $lengthColumns)) {
            $length = $data[ 'length' ] ?? 255;
            $column .= '(' . $length;

            if (\in_array($data[ 'type' ], ['double', 'float', 'real']) or (\in_array($data[ 'type' ], [
                        'decimal',
                        'numeric',
                    ]) and isset($data[ 'decimals' ]))
            ) {
                $column .= ',' . $data[ 'decimals' ];
            }

            $column .= ')';
        }

        $unsignedZeroFillColumns = [
            'bigint',
            'decimal',
            'double',
            'float',
            'int',
            'integer',
            'mediumint',
            'numeric',
            'smallint',
            'tinyint',
        ];

        if (\in_array($data[ 'type' ], $unsignedZeroFillColumns)) {
            if (isset($data[ 'unsigned' ]) and $data[ 'unsigned' ] === true) {
                $column .= ' UNSIGNED';
            }
            if (isset($data[ 'zerofill' ]) and $data[ 'zerofill' ] === true) {
                $column .= ' ZEROFILL';
            }
        }

        if (\in_array($data[ 'type' ], ['enum', 'set'])) {
            $vals = [];
            foreach ($data[ 'values' ] as $val) {
                $vals[] = $this->db->quote($val);
            }

            $column .= ' (' . \implode(',', $vals) . ')';
        }

        if (isset($data[ 'binary' ]) and $data[ 'binary' ] and \in_array($data[ 'type' ], [
                'char',
                'longtext',
                'mediumtext',
                'text',
                'tinytext',
                'varchar',
            ])
        ) {
            $column .= ' BINARY';
        }

        if (\in_array($data[ 'type' ], [
            'char',
            'enum',
            'longtext',
            'mediumtext',
            'set',
            'text',
            'tinytext',
            'varchar',
            'json'
        ])) {
            $column .= ' CHARACTER SET ' . Db::CHARSET . ' COLLATE ' . Db::COLLATION;
        }

        if ((isset($data[ 'is_null' ]) and !$data[ 'is_null' ]) or (isset($data[ 'ai' ]) and $data[ 'ai' ])) {
            $column .= ' NOT NULL';
        } else {
            $column .= ' NULL';
        }

        if (isset($data[ 'ai' ]) and $data[ 'ai' ]) {
            $column .= ' AUTO_INCREMENT';
        } else {
            $noDefault = ['bigblob', 'blob', 'longblob', 'longtext', 'mediumblob', 'mediumtext', 'text', 'tinytext', 'json'];

            if (isset($data[ 'default' ]) and !\in_array($data[ 'type' ], $noDefault)) {
                if ($data[ 'type' ] == 'bit') {
                    $column .= ' DEFAULT ' . $data[ 'default' ];
                } else {
                    $default = null;
                    if (\is_int($data[ 'default' ]) or \is_numeric($data[ 'default' ]) or \is_float($data[ 'default' ]) or \is_double($data[ 'default' ])) {
                        $default = \floatval($data[ 'default' ]);
                    } else {
                        if ($data[ 'default' ] == 'CURRENT_TIMESTAMP' or $data[ 'default' ] == 'BIT') {
                            $default = $data[ 'default' ];
                        } else {
                            $default = $this->db->quote($data[ 'default' ]);
                        }
                    }

                    if ($default) {
                        $column .= ' DEFAULT ' . $default;
                    }
                }
            }
        }

        /* Comment */
        if (isset($data[ 'comment' ]) and !empty($data[ 'comment' ])) {
            $column .= " COMMENT " . $this->db->quote($data[ 'comment' ]);
        }

        /* Return */

        return $column;
    }

    /**
     * syncs tables while in_dev
     */
    public function sync()
    {
        $sql = null;
        $module = new \ZPM\Core\Setup\Schema();
        $modData = new \ZPM\Core\Setup\Info();

        $module->install();
    }

    public function up($type, $data)
    {
//        if (!$this->install && isset($this->alt[ $type ])) {
//            $type = $this->alt[ $type ];
//        }
        $table = $data[ 'table' ];
//        dd( $this->tableExists($table) );
        switch ($type) {
            case 'addTable':
                if (!$this->tableExists($table)) {
                    $this->addTable($data);
                } else {
                    if (isset($data[ 'columns' ])) {
                        $columns = [];
                        foreach ($data[ 'columns' ] as $c) {
                            if (!$this->columnExists($table, $c[ 'name' ])) {
                                $columns[] = $this->addColumn($table, $c);
                            }
                        }
                    }
                }
                try {
                    if (isset($data[ 'inserts' ])) {
                        foreach ($data[ 'inserts' ] as $insert) {
                            $this->db->insert($table)->values($insert)->execute();
                        }
                    }
                }
                catch( \Exception $e ){

                }
                break;
            case 'addColumn':
                $this->addColumn($table, $data[ 'column' ]);
                break;
            case 'addIndex':
                $this->addIndex($table, $data[ 'index' ]);
                break;
            case 'changeTable':
                $this->changeTable($table, $data[ 'new' ]);
                break;
            case 'changeColumn':
                $this->changeColumn($table, $data[ 'column' ], $data[ 'definition' ]);
                break;
            case 'dropTable':
                $this->dropTable($table);
                break;
            case 'dropColumn':
                $this->dropColumn($table, $data[ 'column' ]);
                break;
            case 'dropIndex':
                $this->dropIndex($table, $data[ 'index' ]);
                break;
            case 'noAlt':
                break;
            default:
                throw new \InvalidArgumentException("The defined type: " . $type . " doesn't exist!");
        }
    }
}
