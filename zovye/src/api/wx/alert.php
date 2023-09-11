<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\api\wx;

use DateTime;
use Exception;
use zovye\GoodsExpireAlert;
use zovye\Request;
use function zovye\err;
use function zovye\is_error;

class alert
{
    public static function update(): array
    {
        $agent = common::getAgent();

        $device = device::getDevice(Request::str('id'), $agent);

        if (is_error($device)) {
            return $device;
        }

        $lane_id = Request::int('lane');

        $payload = $device->getPayload();
        if (empty($payload['cargo_lanes']) || empty($payload['cargo_lanes'][$lane_id])) {
            return err('指定货道不存在！');
        }

        $expired_at = Request::str('expiredAt');
        $pre_days = Request::int('preDays');
        $invalid_if_expired = Request::bool('invalidIfExpired');

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
                    $alert->setGoodsId($lane['goods'] ?? 0);
                    $alert->setAgentId($device->getAgentId());
                    $alert->setExpiredAt($ts);
                } else {
                    $alert = GoodsExpireAlert::create([
                        'agent_id' => $device->getAgentId(),
                        'device_id' => $device->getId(),
                        'lane_id' => $lane_id,
                        'goods_id' => $payload['cargo_lanes'][$lane_id]['goods'] ?? 0,
                        'expired_at' => $ts,
                    ]);
                }

                $alert->setPreAlertDays($pre_days);
                $alert->setInvalidIfExpired($invalid_if_expired);

                if (!$alert->save()) {
                    return ['msg' => '保存失败！'];
                }

            } catch (Exception $e) {
                return err('保存失败！');
            }
        }

        return ['msg' => '保存成功！'];
    }
}