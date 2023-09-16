<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\api\wx;

use DateTime;
use Exception;
use zovye\App;
use zovye\business\GoodsExpireAlert;
use zovye\domain\Device;
use zovye\model\goods_expire_alertModelObj;
use zovye\Request;
use function zovye\err;
use function zovye\is_error;

class alert
{
    public static function update(): array
    {
        if (!App::isGoodsExpireAlertEnabled()) {
            return err('没有启用这个功能！');
        }

        $agent = common::getAgent(true);
        if ($agent) {
            $device = \zovye\api\wx\device::getDevice(Request::str('id'), $agent);
            if (is_error($device)) {
                return $device;
            }
        }

        if (empty($agent)) {
            $keeper = common::getKeeper(true);
            if ($keeper) {
                $device = Device::find(Request::str('id'), ['imei', 'shadow_id']);
                if (empty($device)) {
                    return err('找不到这个设备！');
                }

                if ($device->getAgentId() != $keeper->getAgentId() ||
                    !$device->hasKeeper($keeper, \zovye\domain\Keeper::OP)) {
                    return err('没有权限！');
                }
            }
        }

        if (!isset($device)) {
            return err('找不到这个设备！');
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
        if (!App::isGoodsExpireAlertEnabled()) {
            return 0;
        }

        $agent = common::getAgent(true);
        if ($agent) {
            return GoodsExpireAlert::getAllExpiredForAgent($agent, true);
        }

        $keeper = common::getKeeper(true);
        if ($keeper) {
            return GoodsExpireAlert::getAllExpiredForKeeper($keeper, true);
        }

        return 0;
    }

    public static function list(): array
    {
        if (!App::isGoodsExpireAlertEnabled()) {
            return [];
        }

        $all = [];

        $agent = common::getAgent(true);
        if ($agent) {
            $all = GoodsExpireAlert::getAllExpiredForAgent($agent);
        } else {
            $keeper = common::getKeeper(true);
            if ($keeper) {
                $all = GoodsExpireAlert::getAllExpiredForKeeper($keeper);
            }
        }

        $result = [];

        /** @var goods_expire_alertModelObj $alert */
        foreach ($all as $alert) {

            $device = $alert->getDevice();
            if (empty($device)) {
                continue;
            }

            $goods = $device->getGoodsByLane($alert->getLaneId(), ['useImageProxy' => true, 'fullPath' => true], false);
            if (empty($goods)) {
                continue;
            }

            $expired_at = $alert->getExpiredAt();
            if (empty($expired_at)) {
                continue;
            }

            $result[] = [
                'device' => $device->profile(),
                'goods' => $goods,
                'lane' => $alert->getLaneId(),
                'status' => GoodsExpireAlert::getStatus($alert),
                'pre_days' => $alert->getPreDays(),
                'expired_at' => date('Y-m-d', $alert->getExpiredAt()),
                'valid_if_expired' => $alert->getInvalidIfExpired(),
            ];
        }

        return $result;
    }
}