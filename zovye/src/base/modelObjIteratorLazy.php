<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\base;

use ArrayAccess;
use Countable;
use Iterator;

class modelObjIteratorLazy implements Iterator, Countable, ArrayAccess
{
    /** @var modelFactory */
    private $factory;
    private $container;
    private $pos = 0;

    public function __construct($factory, $res)
    {
        $this->factory = $factory;
        $this->container = $res ?: [];
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return sizeof($this->container);
    }

    //Iterator implements
    public function rewind()
    {
        $this->pos = 0;
    }

    public function key()
    {
        return $this->pos;
    }

    public function next()
    {
        ++$this->pos;
    }

    public function valid(): bool
    {
        return isset($this->container[$this->pos]);
    }

    public function current()
    {
        if ($this->container) {
            $id = $this->container[$this->pos]['id'];
            return $this->factory->load($id);
        }

        return null;
    }

    public function offsetExists($offset): bool
    {
       return isset($this->container[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->container[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->container[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->container[$offset]);
    }
}
