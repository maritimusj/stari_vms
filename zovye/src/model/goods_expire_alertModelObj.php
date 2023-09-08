<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;
use function zovye\tb;

/**
 * @method getExpireAt()
 * @method setGoodsId(mixed $goods)
 * @method setAgentId($getAgentId)
 * @method setExpiredAt(int $int)
 */
class goods_expire_alertModelObj extends modelObj
{
    public static function getTableName($read_or_write): string
    {
        return tb('goods_expire_alert');
    }

    /** @var int */
    protected $id;

    /** @var int */
    protected $agent_id;

    /** @var int */
    protected $device_id;

    /** @var int */
    protected $lane_id;

    /** @var int */
    protected $goods_id;

    /** @var int */
    protected $expired_at;

    /** @var text */
    protected $extra;

    /** @var int */
    protected $createtime;

    use ExtraDataGettersAndSetters;

    public function setPreAlertDays(int $days): bool
    {
        return (bool)$this->setExtraData('preAlertDays', $days);
    }

    public function getPreAlertDays(): int
    {
        return $this->getExtraData('preAlertDays', 0);
    }

    public function setInvalidIfExpired(bool $enabled): bool
    {
        return (bool)$this->setExtraData('invalidIfExpired', $enabled ? 1 : 0);
    }

    public function invalidIfExpired(): bool
    {
        return $this->getExtraData('invalidIfExpired', false);
    }
}