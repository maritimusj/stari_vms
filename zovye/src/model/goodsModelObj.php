<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use function zovye\tb;

use zovye\base\modelObj;
use zovye\Goods;
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
 * @method getCreatetime()
 * @method setDeleted(int $int)
 * @method isDeleted()
 * @method setS1(int $v)
 * @method getS1()
 */
class goodsModelObj extends modelObj
{
    /** @var int */
    protected $id;
    /** @var int */
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

    /** @var int */
    protected $s1;

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
        return Goods::isAllowFree($this->s1);
    }

    public function allowPay(): bool
    {
        return Goods::isAllowPay($this->s1);
    }

    public function allowExchange(): bool
    {
        return Goods::isAllowExchange($this->s1);
    }

    public function allowDelivery(): bool
    {
        return Goods::isAllowDelivery($this->s1);
    }    

    public function setAllowFree($allowed = true)
    {
        $this->setS1(Goods::setFreeBitMask($this->s1, $allowed));
    }

    public function setAllowPay($allowed = true)
    {
        $this->setS1(Goods::setPayBitMask($this->s1, $allowed));
    }

    public function setAllowExchange($allowed = true)
    {
        $this->setS1(Goods::setExchangeBitMask($this->s1, $allowed));
    }
    
    public function setAllowDelivery($allowed = true)
    {
        $this->setS1(Goods::setDeliveryBitMask($this->s1, $allowed));
    }
    
    public function getUnitTitle()
    {
        return $this->getExtraData('unitTitle', '');
    }

    public function setUnitTitle($title)
    {
        return $this->setExtraData('unitTitle', $title);
    }

    public function getDetailImg()
    {
        return $this->getExtraData('detailImg');
    }

    public function setDetailImg($url)
    {
        return $this->setExtraData('detailImg', $url);
    }

    public function getGallery()
    {
        return $this->getExtraData('gallery', []);
    }

    public function setGallery($images = [])
    {
        return $this->setExtraData('gallery', $images);
    }   
    
    public function getAppendage(): array
    {
        return $this->getExtraData('appendage') ?: [];
    }

    public function setAppendage(array $data = [])
    {
        return $this->setExtraData('appendage', $data);
    }

    public function getBalance(): int
    {
        return intval($this->getExtraData('balance', 0));
    }

    public function getCostPrice(): int
    {
        return intval($this->getExtraData('costPrice', 0));
    }
}