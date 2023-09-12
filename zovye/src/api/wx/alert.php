<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\api\wx;

use DateTime;
use Exception;
use zovye\Device;
use zovye\GoodsExpireAlert;
use zovye\model\goods_expire_alertModelObj;
use zovye\Request;
use function zovye\err;
use function zovye\is_error;

class alert
{
    public static function update(): array
    {
        $user = common::getUser();
        if ($user->isAgent() || $user->isPartner()) {
            $agent = common::getAgent();
            $device = \zovye\api\wx\device::getDevice(Request::str('id'), $agent);

            if (is_error($device)) {
                return $device;
            }

        } elseif ($user->isKeeper()) {
            $keeper = keeper::getKeeper();
            $device = Device::find(Request::str('id'), ['imei', 'shadow_id']);
            if (empty($device)) {
                return err('找不到这个设备！');
            }

            if ($device->getAgentId() != $keeper->getAgentId() ||
                !$device->hasKeeper($keeper) ||
                $device->getKeeperKind($keeper) != \zovye\Keeper::OP
            ) {
                return err('没有权限！');
            }
        } else {
            return err('没有权限请求这个接口！');
        }

        $lane_id = Request::int('lane');

        $payload = $device->getPayload();
        if (empty($payload['cargo_lanes']) || empty($payload['cargo_lanes'][$lane_id])) {
            return err('指定货道不存在！');
        }

        $expired_at = Request::str('expired_at');
        $pre_days = Request::int('pre_days');
        $invalid_if_expired = Request::bool('invalid_if_expired');

        /** @var goods_expire_alertModelObj $alert */
        $alert = GoodsExpireAlert::getFor($device, $lane_id);

        if (empty($expired_at)) {
            if ($alert) {
                $alert->destroy();

                return ['msg' => '删除成功！'];
            }
        } else {
            try {
                $ts = (new DateTime($expired_at))->getTimestamp();
                if ($alert) {
                    $alert->setAgentId($device->getAgentId());
                    $alert->setExpiredAt($ts);
                    $alert->setPreDays($pre_days);
                    $alert->setInvalidIfExpired($invalid_if_expired);
                    if (!$alert->save()) {
                        return ['msg' => '保存失败！'];
                    }
                } else {
                    $alert = GoodsExpireAlert::create([
                        'agent_id' => $device->getAgentId(),
                        'device_id' => $device->getId(),
                        'lane_id' => $lane_id,
                        'expired_at' => $ts,
                        'pre_days' => $pre_days,
                        'invalid_if_expired' => $invalid_if_expired,
                    ]);
                    if (empty($alert)) {
                        return err('创建提醒失败！');
                    }
                }
            } catch (Exception $e) {
                return err('保存失败！');
            }
        }

        return ['msg' => '保存成功！'];
    }

    public static function count()
    {
        $user = common::getUser();

        if ($user->isAgent() || $user->isPartner()) {
            $agent = common::getAgent();

            return GoodsExpireAlert::getAllExpiredForAgent($agent, true);

        } elseif ($user->isKeeper()) {
            return GoodsExpireAlert::getAllExpiredForKeeper(keeper::getKeeper(), true);
        }

        return 0;
    }

    public static function list(): array
    {
        $user = common::getUser();

        if ($user->isAgent() || $user->isPartner()) {
            $agent = common::getAgent();
            $all = GoodsExpireAlert::getAllExpiredForAgent($agent);
        } elseif ($user->isKeeper()) {
            $all = GoodsExpireAlert::getAllExpiredForKeeper(keeper::getKeeper());
        } else {
            return err('没有权限请求这个接口！');
        }

        $result = [];

        /** @var goods_expire_alertModelObj $alert */
        foreach ($all as $alert) {
            $expired_at = $alert->getExpiredAt();

            $data = [
                'lane' => $alert->getLaneId(),
                'pre_days' => $alert->getPreDays(),
                'expired_at' => $expired_at ? date('Y-m-d', $expired_at) : '',
                'valid_if_expired' => $alert->getInvalidIfExpired(),
            ];

            $device = $alert->getDevice();
            if (empty($device)) {
                continue;
            }

            $data['goods'] = $device->getGoodsByLane($data['lane']);

            $data['device'] = $device->profile();

            $result[] = $data;
        }

        return $result;
    }
}