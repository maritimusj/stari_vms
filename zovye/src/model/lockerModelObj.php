<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\modelObj;
use zovye\We7;

use function zovye\tb;

/**
 * Class prizeModelObj
 * @package zovye
 * @method getCreatetime()
 * @method getRemaining()
 * @method getRequestID()
 * @method isAvailable()
 * @method getAvailable()
 * @method setAvailable(int $param)
 */
class lockerModelObj extends modelObj
{
    /** @var int */
    protected $id;

    /** @var string */
    protected $uid;

    /** @var string */
    protected $request_id;

    /** @var int */
    protected $expired_at;

    /** @var int */
    protected $available;

    /** @var int */
    protected $used;

    /** @var int */
    protected $createtime;

    public static function getTableName($readOrWrite): string
    {
        return tb('locker');
    }

    public function isExpired(): bool
    {
        return $this->expired_at > 0 && time() > $this->expired_at;
    }

    public function release(): bool
    {
        if  (--$this->used > 0) {                
            $tbname = We7::tablename(lockerModelObj::getTableName(true));
            $res = We7::pdo_query('UPDATE '. $tbname . ' SET used=used-1 WHERE id=:id', [
                ':id' => $this->id,
            ]);
            if ($res > 0) {                    
                return true;
            }
        } else {
            return $this->destroy();
        }
        return false;
    }

    public function reenter(string $request_id): bool
    {
        if ($this->request_id === $request_id && $this->used < $this->available && !$this->isExpired()) {
            $condition = [
                'request_id' => $request_id,
                'used' => $this->used,
            ];
            $this->setUsed($this->used + 1);
            return $this->saveWhen($condition);
        }
        return false;
    }

    public function unlock(): bool
    {
        return $this->release();
    }
}