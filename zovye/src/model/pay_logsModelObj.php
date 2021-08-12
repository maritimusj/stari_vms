<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\model;

use function zovye\tb;
use zovye\BaseLogsModelObj;

class pay_logsModelObj extends BaseLogsModelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('user_logs');
    }

    public function getOrderNO(): string
    {
        return strval($this->getData('orderData.orderNO'));
    }

    public function getDeviceId(): int
    {
        return intval($this->getData('device'));
    }

    public function getUserOpenid(): string
    {
        return strval($this->getData('user'));
    }

    public function isPackage(): bool
    {
        return $this->getPackageId() > 0;
    }

    public function isGoods(): bool
    {
        return $this->getGoodsId() > 0;
    }

    public function getPackageId(): int
    {
        return intval($this->getData('package'));
    }

    public function getPackage(): array
    {
        return (array)($this->getData('orderData.extra.package', []));
    }

    public function getGoodsId(): int
    {
        return intval($this->getData('goods'));
    }

    public function getGoods(): array
    {
        return (array)($this->getData('orderData.extra.goods', []));
    }

    public function getGoodsList(): array
    {
        $result = [];
        if ($this->isGoods()) {
            $goods = $this->getGoods();
            $goods['goods_id'] = $goods['id'];
            //设置商品数量
            //单个商品时，goods['num']数量为创建订单时商品的库存数量
            $goods['num'] = $this->getTotal();
            $result[] = $goods;
        } elseif ($this->isPackage()) {
            $package = $this->getPackage();
            if ($package && $package['list']) {
                $result = $package['list'];
            }
        }
        return $result;
    }

    public function getPayName(): string
    {
        return strval($this->getData('pay.name'));
    }

    public function getTotal(): int
    {
        $total = intval($this->getData('total'));
        return empty($total) ? 1 : $total;
    }

    public function getPrice(): int
    {
        $price = intval($this->getData('price'));
        return empty($price) ? intval($this->getData('orderData.price')) : $price;
    }

    public function getDiscount(): int
    {
        return intval($this->getData('discount'));
    }

    public function getPayResult()
    {
        return $this->getData('payResult');
    }

    public function getQueryResult()
    {
        return $this->getData('queryResult');
    }

    public function isCancelled(): bool
    {
        return !empty($this->getData('cancelled'));
    }

    public function isTimeout(): bool
    {
        return !empty($this->getData('timeout'));
    }

    public function isRefund(): bool
    {
        return !empty($this->getData('refund'));
    }

    public function isPaied(): bool
    {
        return !empty($this->getPayResult()) || !empty($this->getQueryResult());
    }
}
