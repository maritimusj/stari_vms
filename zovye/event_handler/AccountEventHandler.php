<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Exception;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\model\accountModelObj;
use zovye\model\balanceModelObj;
use zovye\model\orderModelObj;

class AccountEventHandler
{
    /**
     * 事件：device.beforeLock
     * @param deviceModelObj $device
     * @param userModelObj $user
     * @param accountModelObj|null $account
     * @param orderModelObj|null $order
     */
    public static function onDeviceBeforeLock(deviceModelObj $device, userModelObj $user, accountModelObj $account = null, orderModelObj $order = null)
    {
        if ($account && empty($order)) {
            //检查用户是否允许
            $res = Util::checkAvailable($user, $account, $device);
            if (is_error($res)) {
                ZovyeException::throwWith($res['message'], -1, $device);
            }
        }
    }

    public static function onDeviceOrderCreated(userModelObj $user, accountModelObj $account = null): bool
    {
        if (!App::isBalanceEnabled() || empty($account) || $account->getBonusType() == Account::BALANCE) {
            return true;
        }

        $config = Config::balance('account.promote_bonus', []);
        if (empty($config['min']) && empty($config['max'])) {
            return true;
        }

        $matched = ($config['third_platform'] && $account->isThirdPartyPlatform()) ||
            ($config['account'] && ($account->isNormal() || $account->isAuth())) ||
            ($config['video'] && $account->isVideo()) ||
            ($config['wxapp'] && $account->isWxApp());

        if (!$matched) {
            return true;
        }

        $v = random_int($config['min'], $config['max']);
        if (empty($v)) {
            return true;
        }

        $result = $user->getBalance()->change($v, Balance::PROMOTE_BONUS, [
            'account' => $account->profile(),
        ]);

        if (empty($result)) {
            Log::error('create_order_balance', [
                'error' => 'failed to create promote bonus',
                'user' => $user->profile(false),
                'account' => $account->profile(),
                'bonus_num' => $v,
            ]);
        }
        return true;
    }
}
