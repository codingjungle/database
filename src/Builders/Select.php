<?php

namespace Codingjungle\Database\Builders;

/**
 * @brief      Select Class
 * @author     Michael S. Edwards
 * @package    Zephyr
 * @subpackage Codingjungle\Database\Builders
 */
class Select extends Query
{

    use Traits\Where, Traits\Order, Traits\Limit;

    /**
     * columns to query on
     * @var string|string
     */
    protected $columns = '*';

    protected $having =  null;

    protected $groupBy = null;

    /**
     * sets the columns for the query
     *
     * @param $columns
     *
     * @return $this
     */
    public function columns(array $columns): \Codingjungle\Database\Builders\Select
    {
        $this->columns = $columns;
        return $this;
    }

    /**
     * @param $data
     *
     * @return $this
     */
    public function groupBy( $data ): \Codingjungle\Database\Builders\Select
    {
        $this->groupBy = $data;
        return $this;
    }

    /**
     * @param array $data
     *
     * @return $this
     */
    public function having( array $data ): \Codingjungle\Database\Builders\Select
    {
        $this->having = $data;
        return $this;
    }

    /**
     * returns the first record found
     * @throws \UnderflowException
     * @return mixed
     */
    public function first():array
    {
        $stmt = $this->execute();
        $return = $stmt->fetch();
        $stmt->closeCursor();

        if (!$return) {
            throw new \UnderflowException('No records found.');
        }

        return $return;
    }

    /**
     * compiles the query to be executed
     */
    protected function compile()
    {
        $this->query = null;
        if ($this->columns) {
            if (\is_array($this->columns) and \count($this->columns)) {
                $this->query = 'Select ' . \implode(',', $this->columns );
            } else {
                $this->query = 'Select ' . $this->columns;
            }
        } else {
            $this->query = 'Select *';
        }

        $this->query .= ' FROM ' . $this->table;

        //do joins here when we get that far
        if( \count( $this->joins ) ) {
            $this->compileJoins();
        }

        if ($this->where) {
            $where = $this->buildWhereBinds($this->where);
            $this->query .= ' WHERE ' . $where;
        }

        if ($this->order) {
            $this->query .= ' ORDER BY ' . $this->order;
        }

        if ($this->limit) {
            $this->query .= ' LIMIT '.$this->limit;
        }

        if( $this->having ){
            $this->query .= ' HAVING '. $this->buildWhereBinds( $this->having );
        }

        if( $this->groupBy ){
            $this->query .= ' GROUP BY ' . $this->groupBy;
        }
    }
}
