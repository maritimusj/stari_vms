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

    public function getGoodsId(): int
    {
        return intval($this->getData('goods'));
    }

    public function getGoods(): array
    {
        return (array)($this->getData('orderData.extra.goods', []));
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
