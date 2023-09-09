<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\model\base\modelObj;
use zovye\PackageGoods;
use zovye\Util;
use function zovye\tb;

/**
 * @method getTitle()
 * @method getPrice()
 * @method setDeviceId($id)
 */
class packageModelObj extends modelObj
{
    public static function getTableName($read_or_write): string
    {
        return tb('package');
    }

    /** @var int */
    protected $id;

    /** @var int */
    protected $device_id;

    /** @var string */
    protected $title;

    /** @var int */
    protected $price;

    /** @var int */
    protected $createtime;

    public function format($detail = false, $format_price = true): array
    {
        $result = [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'price' => $format_price ? number_format($this->getPrice() / 100, 2) : $this->getPrice(),
            'is_package' => true,
            'createtime' => date('Y-m-d H:i:s', $this->getCreatetime()),
        ];
        if ($detail) {
            $result['list'] = [];
            /** @var package_goodsModelObj $entry */
            foreach (PackageGoods::queryFor($this)->findAll() as $entry) {
                $data = [
                    'id' => $entry->getId(),
                    'price' => $format_price ? number_format($entry->getPrice() / 100, 2) : $entry->getPrice(),
                    'num' => $entry->getNum(),
                ];
                $goods = $entry->getGoods();
                if ($goods) {
                    $data['goods_id'] = $goods->getId();
                    $data['name'] = $goods->getName();
                    $data['image'] = Util::toMedia($goods->getImg(), true);
                    $data['unit_title'] = $goods->getUnitTitle();
                    if ($goods->isDeleted()) {
                        $data['deleted'] = true;
                    }
                }
                $result['list'][] = $data;
            }
        }

        return $result;
    }

}