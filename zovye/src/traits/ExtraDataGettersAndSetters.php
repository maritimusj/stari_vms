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
    private $__extra_data = null;

    /**
     * @param $data
     * @return false|string
     */
    public static function serializeExtra($data)
    {
        return json_encode($data);
    }

    /**
     * 获取扩展数据
     * @param mixed $key
     * @param null $default
     * @return mixed|null
     */
    public function getExtraData($key = '', $default = null)
    {
        $this->deserializeExtra();

        if ($key) {
            return getArray($this->__extra_data, $key, $default);
        }

        return $this->__extra_data;
    }

    /**
     * 设置扩展数据
     * @param $key
     * @param null $val
     * @return mixed
     */
    public function setExtraData($key, $val = null)
    {
        $this->deserializeExtra();

        if (is_array($this->__extra_data)) {
            if (is_string($key)) {
                setArray($this->__extra_data, $key, $val);
            } else {
                setArray($this->__extra_data, '', $key);
            }
        }

        return static::setExtra(json_encode($this->__extra_data));
    }

    public function deserializeExtra()
    {
        if (is_null($this->__extra_data)) {
            if ($this->extra) {
                $this->__extra_data = json_decode($this->extra, true);
            }

            if (empty($this->__extra_data)) {
                $this->__extra_data = [];
            }
        }
    }
}
