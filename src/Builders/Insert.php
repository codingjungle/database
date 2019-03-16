<?php

namespace Codingjungle\Database\Builders;

/**
 * @brief      Insert Class
 * @author     Michael S. Edwards
 * @package    Zephyr
 * @subpackage Codingjungle\Database\Builders
 */
class Insert extends \Codingjungle\Database\Builders\Query
{
    protected $type = 'INSERT';
    protected $values = null;
    protected $columns = null;
    protected $bindPointer = 1;

    /**
     * @inheritdoc
     */
    protected function compile()
    {
        $this->query = $this->type . ' INTO ' . $this->db->prefix . $this->table;
        $this->compileJoins();
        $this->query .= ' ( ' . \implode(',', $this->columns) . ' ) VALUES ';
        $values = [];
        foreach ($this->values as $value) {
            $values[] = '( ' . \implode(',', $value) . ' )';
        }
        $this->query .= \implode(',', $values);
    }

    /**
     * builds the insert data
     * @param array $data
     * @return Insert
     */
    public function values(array $data): \Codingjungle\Database\Builders\Insert
    {
        if (!\is_int(\key($data))) {
            $data = [$data];
        }
        $columns = \array_keys(\reset($data));
        foreach ($data as $row) {
            if ( !(\count(\array_diff(\array_keys($row), $columns ) ) ) ) {
                throw new \InvalidArgumentException('Illegal modification of columns to insert on!');
            }
            $values = [];
            foreach ($row as $value) {
                $values[] = "?";
                $this->addBinds($this->bindPointer, $value);
                $this->bindPointer++;
            }
            $this->values[] = $values;
        }
        if ($this->columns) {
            if (!(\count(\array_diff($this->columns, $columns ) ) ) ) {
                throw new \InvalidArgumentException('Illegal modification of columns to insert on!');
            }
        }
        $this->columns = $columns;
        return $this;
    }

    /**
     * @inheritdoc
     * @return string
     */
    public function execute()
    {
        $parent = parent::execute();
        $parent->closeCursor();
        return $this->db->lastInsertId();
    }
}
