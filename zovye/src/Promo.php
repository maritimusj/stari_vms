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
    public static function getConfig(): array {
        return [
            'sms' => [
                'max' => 3,
                'delay' => 10,
            ],
        ];
    }

    public static function getSMSCode(): string {
        return Util::random(6, true);
    }

    public static function veriyfSMS(userModelObj $user) {
        $config = self::getConfig();

        $today = new DateTime('00:00');

        $total = m('user_logs')->where([
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

    public static function getLastSMSLog(userModelObj $user): ?user_logsModelObj  {

        return m('user_logs')->where([
            'title' => $user->getMobile(),
            'level' => LOG_SMS,
        ])->orderBy('id desc')->findOne();
    }

    public static function createSMSLog(userModelObj $user, $data = []): ?user_logsModelObj {
        return m('user_logs')->create(We7::uniacid([
            'title' => $user->getMobile(),
            'level' => LOG_SMS,
            'data' => serialize($data),
        ]));
    }
}