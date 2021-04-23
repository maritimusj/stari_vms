<?php

namespace zovye\traits;

use function zovye\getArray;
use function zovye\ifEmpty;
use function zovye\setArray;

trait DataGetterAndSetter
{
    abstract function getRawData();

    abstract function setRawData($data);

    public function getData($sub = null, $default = null)
    {
        $data = unserialize($this->getRawData());
        if (empty($sub)) {
            return ifEmpty($data, $default);
        }

        return getArray($data, $sub, $default);
    }

    public function setData($sub, $data)
    {
        if ($sub) {
            $org = unserialize($this->getRawData());
            setArray($org, $sub, $data);
            $this->setRawData(serialize($org));
        } else {
            $this->setRawData(serialize($data));
        }
    }
}