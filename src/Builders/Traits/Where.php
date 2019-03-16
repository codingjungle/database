<?php

namespace Codingjungle\Database\Builders\Traits;

/**
 * @brief      Where Trait
 * @author     Michael S. Edwards
 * @package    Zephyr
 * @subpackage Codingjungle\Database\Builders\Traits
 */
trait Where
{
    /**
     * where statement
     * @var null|array
     */
    protected $where = null;

    /**
     * sets the where clause
     *
     * @param array $where
     *
     * @return $this
     */
    public function where(array $where)
    {
        $this->where = $where;

        return $this;
    }
}
