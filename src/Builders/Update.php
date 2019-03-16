<?php

namespace Codingjungle\Database\Builders;

/**
 * @brief      Update Class
 * @author     Michael S. Edwards
 * @package    Zephyr
 * @subpackage Codingjungle\Database\Builders
 */
class Update extends Query
{
    use Traits\Where;

    /**
     * @var array
     */
    protected $columns = [];

    /**
     * @param $data
     *
     * @return $this
     */
    public function set(array $data ){
        foreach ($data as $key => $val) {
            $this->columns[] = $key . ' = :' . $key;
            $this->addBinds( $key, $val);
        }
        return $this;
    }

    /**
     *
     */
    protected function compile()
    {
        $this->query = 'UPDATE '. $this->db->prefix . $this->table;
        if( \count( $this->joins ) ){
            $this->compileJoins();
        }

        if( \count( $this->columns ) ){
            $this->query .= ' SET ' . \implode( ',', $this->columns );
        }

        if( \count( $this->where ) ){
            $this->query .= ' WHERE ' .$this->buildWhereBinds( $this->where );
        }
    }

    /**
     * @inheritdoc
     * @return int
     */
    public function execute()
    {
        $parent = parent::execute();
        $return = $parent->rowCount();
        $parent->closeCursor();
        return $return;
    }
}
