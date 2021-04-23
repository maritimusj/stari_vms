<?php


namespace zovye;

use zovye\model\deviceModelObj;
use zovye\model\orderModelObj;


class VoucherEventHandler
{
    public static function onDeviceOrderCreated(deviceModelObj $device, orderModelObj $order): bool
    {
        if (!App::isGoodsVoucherEnabled()) {
            return true;
        }

        $goods = $order->getGoods();
        if (empty($goods)) {
            return true;
        }

        $vouchers = $goods->getExtraData('vouchers', []);
        if (empty($vouchers)) {
            return true;
        }

        $user = $order->getUser();
        if (empty($user)) {
            return true;
        }

        $res = GoodsVoucher::give($user, $vouchers, function ($voucher) use ($device) {
            $data = $voucher->getExtraData('assigned', []);
            return Util::isAssigned($data, $device);
        });

        $res = [
            'order' => $order->getId(),
            'res' => $res,
        ];

        Util::logToFile('vouchers', $res);

        if ($res) {
            $order->setExtraData('extra.voucher.recv', count($res));
        }

        return true;
    }
}