<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\event;

use zovye\App;
use zovye\domain\GoodsVoucher;
use zovye\Log;
use zovye\model\deviceModelObj;
use zovye\model\orderModelObj;
use zovye\util\DeviceUtil;

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

            return DeviceUtil::isAssigned($device, $data);
        });

        $res = [
            'order' => $order->getId(),
            'res' => $res,
        ];

        Log::debug('vouchers', $res);

        if ($res) {
            $order->setExtraData('extra.voucher.recv', count($res));
        }

        return true;
    }
}