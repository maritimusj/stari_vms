<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\traits;

trait DirtyChecker
{
    private $__dirty_props = [];

    public function setDirty($props)
    {
        $props = is_array($props) ? $props : [$props];
        $this->__dirty_props = array_unique(array_merge($this->__dirty_props, $props));
    }

    public function clearDirty($props = null)
    {
        if (isset($props)) {
            $props = is_array($props) ? $props : [$props];
            $this->__dirty_props = array_diff($this->__dirty_props, $props);
        } else {
            $this->__dirty_props = [];
        }
    }

    public function isDirty($prop = null): bool
    {
        if (isset($prop)) {
            return in_array($prop, $this->__dirty_props);
        }

        return !empty($this->__dirty_props);
    }
}
