<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\domain;

use DateTimeImmutable;
use Exception;
use zovye\App;
use zovye\base\ModelFactory;
use zovye\model\deviceModelObj;
use zovye\model\goods_expire_alertModelObj;
use zovye\model\keeperModelObj;
use zovye\model\userModelObj;
use zovye\We7;
use function zovye\m;

class GoodsExpireAlert extends Base
{
    public static function model(): ModelFactory
    {
        return m('goods_expire_alert');
    }

    public static function getFor(
        deviceModelObj $device,
        int $index,
        bool $agent_restrict = false
    ): ?goods_expire_alertModelObj {

        $condition = [
            'device_id' => $device->getId(),
            'lane_id' => $index,
        ];

        if ($agent_restrict) {
            $agent = $device->getAgent();
            if ($agent) {
                $condition['agent_id'] = $agent->getId();
            }
        }

        return self::findOne($condition);
    }

    public static function getAllExpiredForAgent(userModelObj $user, $fetch_total = false)
    {
        $query = self::query(['agent_id' => $user->getId()]);
        $query->where('expired_at>0 AND expired_at-pre_days*86400<='.time());

        if ($fetch_total) {
            return $query->count();
        }

        $query->orderBy('expired_at ASC');

        return $query->findAll();
    }

    public static function getStatus(goods_expire_alertModelObj $alert): string
    {
        $pre_days = max(0, $alert->getPreDays());

        try {
            $datetime = new DateTimeImmutable("@{$alert->getExpiredAt()}");
            $now = new DateTimeImmutable();
            if ($now >= $datetime) {
                return 'expired';
            } else {
                $datetime = $datetime->modify("-{$pre_days}days");
                if ($now >= $datetime) {
                    return 'alert';
                }
            }
        } catch (Exception $e) {
            return 'error';
        }

        return 'normal';
    }

    public static function getAllExpiredForKeeper($user, $fetch_total = false)
    {
        if ($user instanceof userModelObj) {
            $keeper = $user->getKeeper();
        } elseif ($user instanceof keeperModelObj) {
            $keeper = $user;
        } else {
            return $fetch_total ? 0 : [];
        }

        $alert_tb = We7::tb(self::model()->getTableName());
        $keeper_tb = We7::tb(Keeper::model()->getTableName());
        $keeper_device_tb = We7::tb(m('keeper_devices')->getTableName());

        $ts = TIMESTAMP;

        $sql = <<<SQL
FROM $alert_tb a 
INNER JOIN $keeper_tb k ON a.agent_id=k.agent_id 
INNER JOIN $keeper_device_tb d ON k.id=d.keeper_id AND d.device_id=a.device_id 
WHERE k.id={$keeper->getId()} AND d.kind=1 AND a.expired_at>0 AND a.expired_at-a.pre_days*86400<=$ts
SQL;
        if ($fetch_total) {
            $res = We7::pdo_fetch('SELECT COUNT(*) AS total '.$sql);

            return intval($res['total'] ?? 0);
        }

        $res = We7::pdo_fetchAll('SELECT a.id '.$sql.' ORDER BY a.expired_at ASC');
        if (empty($res)) {
            return [];
        }

        $ids = [];
        foreach ($res as $item) {
            $ids[] = $item['id'];
        }

        return self::query(['id' => $ids])->findAll();
    }

    public static function isAvailable(deviceModelObj $device, int $lane_id): bool
    {
        if (!App::isGoodsExpireAlertEnabled()) {
            return true;
        }

        $alert = self::getFor($device, $lane_id, true);
        if (empty($alert) || !$alert->getInvalidIfExpired() || empty($alert->getExpiredAt())) {
            return true;
        }

        return self::getStatus($alert) != 'expired';
    }
}