<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\model;

use function zovye\tb;

use zovye\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;

/**
 * @method getAgentId()
 * @method setAgentId($agentId)
 * @method getName()
 * @method setName($goodsName)
 * @method getImg()
 * @method setImg($goodsImg)
 * @method getSync()
 * @method setSync($syncAll)
 * @method getPrice()
 * @method setPrice(int $price)
 * @method getBalance()
 * @method setBalance($goodsBalance)
 * @method getCreatetime()
 * @method setDeleted(int $int)
 */
class goodsModelObj extends modelObj
{
    /** @var int */
    protected $id;
    protected $uniacid;
    /** @var int */
    protected $agent_id;
    /** @var string */
    protected $name;
    /** @var string */
    protected $img;
    /** @var int */
    protected $price;
    /** @var bool */
    protected $sync;
    /** @var bool */
    protected $deleted;

    protected $extra;
    /** @var int */
    protected $createtime;

    use ExtraDataGettersAndSetters;

    public static function getTableName($readOrWrite): string
    {
        return tb('goods');
    }

    public function delete()
    {
        $this->setDeleted(1);
    }

    public function allowFree(): bool
    {
        return $this->getExtraData('allowFree') ? true : false;
    }

    public function setAllowFree($allowed = true)
    {
        $this->setExtraData('allowFree', $allowed ? 1 : 0);
    }

    public function setAllowPay($allowed = true)
    {
        $this->setExtraData('allowPay', $allowed ? 1 : 0);
    }

    public function getUnitTitle()
    {
        return $this->getExtraData('unitTitle', '');
    }

    public function setUnitTitle($title)
    {
        return $this->setExtraData('unitTitle', $title);
    }

    public function allowPay(): bool
    {
        return $this->getExtraData('allowPay') ? true : false;
    }

    public function getDetailImg()
    {
        return $this->getExtraData('detailImg');
    }

    public function setDetailImg($url)
    {
        return $this->setExtraData('detailImg', $url);
    }
    
    public function getAppendage(): array
    {
        return $this->getExtraData('appendage') ?: [];
    }

    public function setAppendage(array $data = [])
    {
        return $this->setExtraData('appendage', $data);
    }

    public function getCostPrice(): int
    {
        return intval($this->getExtraData('costPrice', 0));
    }
}