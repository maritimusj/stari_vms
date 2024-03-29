<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\traits;

use zovye\util\Util;
use function zovye\toSnakeCase;

trait GettersAndSetters
{
    private $__setter_filters = [];
    private $__getter_filters = [];

    public static function __callStatic($name, $params)
    {
        if (strncasecmp($name, 'has', 3) == 0) {
            $prop = toSnakeCase(ltrim($name, 'has'));

            return property_exists(get_called_class(), $prop);
        }

        return false;
    }

    public function configFilter($setterOrGetter, $filters)
    {
        $filters = is_array($filters) ? $filters : [$filters];
        if ($setterOrGetter == 'set') {
            $this->__setter_filters = array_merge($this->__setter_filters, $filters);
        } elseif ($setterOrGetter == 'get') {
            $this->__getter_filters = array_merge($this->__getter_filters, $filters);
        }
    }

    public function __call($name, $params)
    {
        if (strncasecmp($name, 'get', 3) == 0) {
            $prop = toSnakeCase(ltrim($name, 'get'));
            if (!in_array($prop, $this->__getter_filters) && property_exists($this, $prop)) {
                if ($params && $params[0] === true && method_exists($this, 'forceReloadPropertyValue')) {
                    return $this->forceReloadPropertyValue($prop, $params);
                }

                return $this->$prop;
            }
        } elseif (strncasecmp($name, 'set', 3) == 0) {
            $prop = toSnakeCase(ltrim($name, 'set'));
            if (!in_array($prop, $this->__setter_filters) && property_exists(
                    $this,
                    $prop
                ) && $this->$prop !== $params[0]) {
                $this->$prop = $params[0];
                if (Util::traitUsed($this, DirtyChecker::class)) {
                    $this->setDirty($prop);
                }
            }

            return $this;
        } elseif (strncasecmp($name, 'is', 2) == 0) {
            $prop = toSnakeCase(ltrim($name, 'is'));
            if ($params && $params[0] === true && method_exists($this, 'forceReloadPropertyValue')) {
                return boolval($this->forceReloadPropertyValue($prop, $params));
            }

            return boolval($this->$prop);
        } elseif (strncasecmp($name, 'has', 3) == 0) {
            $prop = toSnakeCase(ltrim($name, 'has'));

            return property_exists($this, $prop);
        } else {
            if (DEBUG) {
                trigger_error("call undefined method $name on ".get_called_class(), E_USER_ERROR);
            }
        }

        return null;
    }
}
