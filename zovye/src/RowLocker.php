<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

class RowLocker
{
    private $locked = false;

    private $tb_name;
    private $condition;
    private $seg;
    private $lock_guid;

    public function __construct($tb_name, $condition, $seg, $auto_unlock = false, $lock_guid = null)
    {
        if ($tb_name && $condition && $seg) {
            $this->lock($tb_name, $condition, $seg, $auto_unlock, $lock_guid);
        }
    }

    public function lock($tb_name, $condition, $seg, $auto_unlock = false, $lock_guid = null): bool
    {
        if ($this->locked || empty($tb_name) || empty($condition) ||empty($seg) || in_array($seg, ['id', 'uniacid']) || !isset($condition[$seg])) {
            return false;
        }

        if (empty($lock_guid)) {
            $lock_guid = We7::uniacid() . Util::random(6, true);
        }

        $res = We7::pdo_update($tb_name, [$seg => $lock_guid], $condition);
        if ($res) {
            $this->locked = true;

            $this->tb_name = $tb_name;
            $this->condition = $condition;
            $this->seg = $seg;
            $this->lock_guid = $lock_guid;

            if ($auto_unlock) {
                register_shutdown_function(
                    function () {
                        $this->unlock();
                    }
                );
            }

            return true;
        }

        return false;
    }

    public function unlock($new_val = null): bool
    {
        if ($this->locked) {
            $val = isset($new_val) ? $new_val : $this->condition[$this->seg];

            $condition = $this->condition;
            $condition[$this->seg] = $this->lock_guid;

            $res = We7::pdo_update($this->tb_name, [$this->seg => $val], $condition);
            if ($res) {
                $this->locked = false;
                return true;
            }
        }

        return false;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function restore(): bool
    {
        return $this->unlock();
    }
}
