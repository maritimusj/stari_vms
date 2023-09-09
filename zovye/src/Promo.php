<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use DateTime;
use zovye\model\user_logsModelObj;
use zovye\model\userModelObj;

class Promo
{
    public static function getConfig(): array
    {
        return [
            'sms' => [
                'max' => 3,
                'delay' => 30,
                'expired' => 5 * 60,
            ],
            'goods' => [
                'max' => 9,
            ],
            'user' => [
                'limit' => [
                    'day' => 0,
                ],
            ],
        ];
    }

    public static function getSMSCode(): string
    {
        return Util::random(6, true);
    }

    public static function verifySMS(userModelObj $user)
    {
        $config = self::getConfig();

        $daily_limit = intval($config['user']['limit']['day']);
        if ($daily_limit > 0 && Stats::getDayTotal($user)['total'] > $daily_limit) {
            return err('you have reached the daily limit.');
        }

        $today = new DateTime('00:00');

        $total = UserLogs::query([
            'title' => $user->getMobile(),
            'level' => LOG_SMS,
            'createtime >' => $today->getTimestamp(),
        ])->count();

        if ($total > $config['sms']['max']) {
            return err('you have reached the daily sending limit.');
        }

        $log = self::getLastSMSLog($user);
        if ($log && time() - $log->getCreatetime() < $config['sms']['delay']) {
            return err('you have to wait before you can re-send.');
        }

        return true;
    }

    public static function getLastSMSLog(userModelObj $user): ?user_logsModelObj
    {
        return UserLogs::query([
            'title' => $user->getMobile(),
            'level' => LOG_SMS,
        ])->orderBy('id desc')->findOne();
    }

    public static function createSMSLog(userModelObj $user, $data = []): ?user_logsModelObj
    {
        return UserLogs::create([
            'title' => $user->getMobile(),
            'level' => LOG_SMS,
            'data' => serialize($data),
        ]);
    }
}