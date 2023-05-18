<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\Account;
use function zovye\tb;

use zovye\base\modelObj;
use zovye\Goods;
use zovye\traits\ExtraDataGettersAndSetters;
use zovye\Util;

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

    public function profile(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'image' => $this->getDetailImg(),
        ];
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

    public function allowBalance(): bool
    {
        return Goods::isAllowBalance($this->s1);
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

    public function setAllowBalance($allowed = true)
    {
        $this->setS1(Goods::setBalanceBitMask($this->s1, $allowed));
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

    public function getGallery($fullpath = false)
    {
        $gallery = $this->getExtraData('gallery', []);
        if ($fullpath) {
            foreach($gallery as &$url) {
                $url = Util::toMedia($url, $fullpath);
            }
        }
        return $gallery;
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

    public function getCVMachineItemCode()
    {
        return $this->getExtraData('CVMachine.code', '');
    }

    public function setCVMachineItemCode($code)
    {
        return $this->setExtraData('CVMachine.code', $code);
    }

    public function getQuota(string $w = ''): array
    {
        if (empty($w)) {
            $res = $this->getExtraData('quota');
            if (empty($res)) {
                $res = [
                    'free' => [
                        'day' => 0,
                        'all' => 0,
                    ],
                    'pay' => [
                        'day' => 0,
                        'all' => 0,
                    ],
                ];
            }

            return $res;
        }

        return $this->getExtraData("quota.{$w}", [
            'day' => 0,
            'all' => 0,
        ]);
    }

    public function setQuota(array $quota = [])
    {
        return $this->setExtraData('quota', $quota);
    }

    public function getBalance(): int
    {
        return intval($this->getExtraData('balance', 0));
    }

    public function getCostPrice(): int
    {
        return intval($this->getExtraData('costPrice', 0));
    }

    public function getType(): string
    {
        return $this->getExtraData('type', '');
    }

    public function setType($type)
    {
        return $this->setExtraData('type', $type);
    }

    public function isFlashEgg(): bool
    {
        return $this->getAccountId() > 0;
    }

    public function getAccountId(): int
    {
        return $this->getExtraData('accountId', 0);
    }

    public function getAccount(): ?accountModelObj
    {
        $account_id = $this->getAccountId();
        return Account::get($account_id);
    }
}