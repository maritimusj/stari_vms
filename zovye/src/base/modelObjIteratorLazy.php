<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\base;

use Countable;
use Iterator;

class modelObjIteratorLazy implements Iterator, Countable
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
}
