<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\base;

use Countable;
use Iterator;

/**
 * Class modelObjIterator
 * @property modelFactory $factory
 */
class modelObjIterator implements Iterator, Countable
{
    private $factory;
    private $container;
    private $pos = 0;

    public function __construct($factory, $list)
    {
        $this->factory = $factory;
        $this->container = $list ?: [];
    }

    public function count(): int
    {
        return sizeof($this->container);
    }

    //Iterator implements
    public function rewind()
    {
        $this->pos = 0;
    }

    /**
     * @return mixed
     */
    public function current()
    {
        if ($this->container && is_array($this->container[$this->pos])) {
            $data = $this->container[$this->pos];
            if (isset($data['id'])) {
                $classname = $this->factory->objClassname();
                $obj = new $classname($data['id'], $this->factory);
                $obj->__setData($data);
                return $obj;
            }
        }

        return null;
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
}
