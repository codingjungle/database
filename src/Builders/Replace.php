<?php

namespace Codingjungle\Database\Builders;

/**
 * @brief      Replace Class
 * @author     Michael S. Edwards
 * @package    Zephyr
 * @subpackage Codingjungle\Database\Builders
 */
class Replace extends Insert
{
    protected $type = 'REPLACE';
    protected $onDuplicateUpdate = null;
    /**
     * @inheritdoc
     */
    protected function compile()
    {
        parent::compile();
        if ($this->onDuplicateUpdate) {
            $this->query .= ' ON DUPLICATE KEY UPDATE ' . \implode(',', $this->onDuplicateUpdate);
        }
    }
    /**
     * @return Insert
     */
    public function onDuplicateUpdate(): \Codingjungle\Database\Builders\Insert
    {
        $this->onDuplicateUpdate = \array_map(function ($column) {
            return $column . ' = VALUES(' . $column . ')';
        }, $this->columns);
        $this->type = 'INSERT';
        return $this;
    }
}
