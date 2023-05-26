<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\traits;

use function zovye\getArray;
use function zovye\setArray;

/**
 * Trait ExtraDataGettersAndSetters
 * @package zovye\traits
 * @method setExtra($data)
 */
trait ExtraDataGettersAndSetters
{
    private $__extraData = null;

    /**
     * @param $data
     * @return mixed|string
     */
    public static function serializeExtra($data): string
    {
        $res = json_encode($data);
        return $res === false ? '' : $res;
    }

    /**
     * 获取扩展数据
     * @param mixed $key
     * @param null $default
     * @return mixed|null
     */
    public function getExtraData($key = '', $default = null)
    {
        $this->unserializeExtra();
        if ($key) {
            return getArray($this->__extraData, $key, $default);
        }

        return $this->__extraData;
    }

    /**
     * 设置扩展数据
     * @param $key
     * @param null $val
     * @return mixed
     */
    public function setExtraData($key, $val = null)
    {
        $this->unserializeExtra();

        if (is_array($this->__extraData)) {
            if (is_string($key)) {
                setArray($this->__extraData, $key, $val);
            } else {
                setArray($this->__extraData, '', $key);
            }
        }

        return static::setExtra(json_encode($this->__extraData));
    }

    public function unserializeExtra()
    {
        if (is_null($this->__extraData)) {
            if ($this->extra) {
                $this->__extraData = json_decode($this->extra, true);
            }

            if (empty($this->__extraData)) {
                $this->__extraData = [];
            }
        }
    }
}
