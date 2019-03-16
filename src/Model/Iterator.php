<?php
/**
 * Created for zephyr.
 * User: Michael S. Edwards
 * Date: 11/8/2017
 * Time: 8:07 PM
 */

namespace Codingjungle\Database\Model;

/**
 * Iterator class for building objects for the model
 * Class Iterator
 * @package Codingjungle\Database\Model
 */
class Iterator extends \CachingIterator
{
    /**
     * @brief model class that will be used
     * @var null
     */
    protected $model = null;

    /**
     * Iterator constructor.
     *
     * @param mixed $statement
     * @param string        $model
     */
    public function __construct( $statement, string $model)
    {
        $this->model = $model;
        return parent::__construct(new \IteratorIterator($statement), static::FULL_CACHE);
    }

    /**
     * get the current record and load into model
     * @return \Codingjungle\Database\Model
     */
    public function current(): \Codingjungle\Database\Model
    {
        /**
         * @var $model \Codingjungle\Database\Model
         */
        $model = $this->model;

        return $model::create(parent::current());
    }

    /**
     * return the model's total count
     * @return int
     */
    public function count(): int
    {
        /**
         * @var $stmt \PDOStatement
         */
        $stmt = $this->getInnerIterator();
        return (int) $stmt->rowCount();
    }
}
