<?php

namespace Codingjungle\Database\Builders\Traits;

/**
 * @brief      Limit Trait
 * @author     Michael S. Edwards
 * @package    Zephyr
 * @subpackage Codingjungle\Database\Builders\Traits
 */
trait Limit
{
    /**
     * limit clause
     * @var null|string
     */
    protected $limit = null;

    /**
     * sets the limit clause
     * @param int      $limit
     * @param int|null $offset
     *
     * @return $this
     */
    public function limit( int $limit, int $offset=null)
    {
        if ($offset != null) {
            $this->limit= $limit . ',' . $offset;
        } else {
            $this->limit =  $limit;
        }

        return $this;
    }
}
