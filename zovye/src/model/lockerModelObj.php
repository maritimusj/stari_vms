<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\model;

use zovye\base\modelObj;

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
    protected $createtime;

    public static function getTableName($readOrWrite): string
    {
        return tb('locker');
    }

    public function isExpired(): bool
    {
        return $this->expired_at > 0 && time() > $this->expired_at;
    }

    public function reenter(): bool
    {
        if ($this->request_id === REQUEST_ID && $this->available > 0 && !$this->isExpired()) {
            $condition = [
                'request_id' => REQUEST_ID,
                'available' => $this->available,
            ];
            $this->setAvailable($this->available - 1);
            return $this->saveWhen($condition);
        }
        return false;
    }
}