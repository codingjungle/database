<?php

namespace Codingjungle\Database\Builders\Traits;

/**
 * @brief      Order Trait
 * @author     Michael S. Edwards
 * @package    Zephyr
 * @subpackage Codingjungle\Database\Builders\Traits
 */
trait Order
{
    /**
     * order by clause
     * @var null|string
     */
    protected $order = null;
    /**
     * sets the order clause
     *
     * @param string $order
     *
     * @return $this
     */
    public function order(string $order)
    {
        $this->order = $order;

        return $this;
    }
}
