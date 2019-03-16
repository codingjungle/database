<?php

namespace Codingjungle\Database\Builders;

/**
 * @brief      Delete Class
 * @author     Michael S. Edwards
 * @package    Zephyr
 * @subpackage Codingjungle\Database\Builders
 */
class Delete extends Query
{
    use Traits\Where, Traits\Order, Traits\Limit;

    /**
     * @var bool
     */
    protected $truncate = false;

    /**
     * @var null
     */
    protected $from = null;

    /**
     * this will truncate a table, this is executed immediately.
     */
    public function truncate(){
        $this->truncate = true;
        $this->execute()->closeCursor();
    }

    /**
     * @param $table
     *
     * @return $this
     */
    public function from( $table ): \Codingjungle\Database\Builders\Delete
    {
        $this->from = $table;
        return $this;
    }

    protected function compile(){

        if( $this->truncate ){
            $this->query = 'TRUNCATE ' . $this->db->prefix . $this->table;
        }
        else {
            if( count( $this->joins ) ) {
                if( $this->from == null ){
                    throw new \InvalidArgumentException( 'From is empty, please define which table you wish to delete from.');
                }
                $this->query = 'DELETE ' . $this->db->prefix . $this->table;
                $this->query .= ' FROM ' . $this->db->prefix . $this->from;
                $this->compileJoins();
            }
            else{
                $this->query = 'DELETE FROM ' .$this->db->prefix . $this->table;
            }

            if ($this->where == null) {
                throw new \InvalidArgumentException('Where clause is empty. please set.');
            } else {
                $clause = $this->buildWhereBinds($this->where);
                $this->query .= ' WHERE ' . $clause;
            }

            if ($this->limit) {
                $this->query .= ' LIMIT ' . $this->limit;
            }

            if ($this->order) {
                $this->query .= ' ORDER BY ' . $this->order;
            }
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
