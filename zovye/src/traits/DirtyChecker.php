<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\traits;

trait DirtyChecker
{
    private $__dirtyRec = [];

    public function setDirty($props)
    {
        $now = time();
        $props = is_array($props) ? $props : [$props];
        foreach ($props as $prop) {
            $this->__dirtyRec[$prop] = $now;
        }
    }

    public function clearDirty($props = null)
    {
        if (isset($props)) {
            $props = is_array($props) ? $props : [$props];
            foreach ($props as $prop) {
                unset($this->__dirtyRec[$prop]);
            }
        } else {
            $this->__dirtyRec = [];
        }
    }

    public function isDirty($prop = null): bool
    {
        if (isset($prop)) {
            return isset($this->__dirtyRec[$prop]);
        }

        return !empty(current($this->__dirtyRec));
    }
}
