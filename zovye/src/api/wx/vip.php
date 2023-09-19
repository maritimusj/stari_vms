<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wx;

use zovye\domain\User;
use zovye\model\agentModelObj;
use zovye\model\vipModelObj;
use zovye\Request;
use zovye\util\Helper;
use function zovye\err;

class vip
{
    public static function userInfo(agentModelObj $agent): array
    {
        $mobile = Request::trim('mobile');
        if (!preg_match(REGULAR_TEL, $mobile)) {
            return err('手机号码格式不正确！');
        }

        if (\zovye\business\VIP::existsByMobile($agent, $mobile)) {
            return err('该手机号码的用户已经是VIP用户！');
        }

        $user = User::findOne(['mobile' => $mobile, 'app' => User::WxAPP]);
        if ($user) {
            if (\zovye\business\VIP::exists($agent, $user)) {
                return err('该用户已经是VIP用户！');
            }

            return $user->profile();
        }

        return [];
    }

    public static function create(agentModelObj $agent): array
    {
        if (Request::has('user')) {
            $user = User::get(Request::int('user'), false, User::WxAPP);
            if (empty($user)) {
                return err('找不到这个用户！');
            }
        } elseif (Request::has('mobile')) {
            $mobile = Request::str('mobile');
            if (!preg_match(REGULAR_TEL, $mobile)) {
                return err('手机号码格式不正确！');
            }
            $user = User::findOne(['mobile' => $mobile, 'app' => User::WxAPP]);
        }

        $name = Request::str('name');

        if (isset($user)) {
            if ($user->isBanned()) {
                return err('这个用户已被禁用！');
            }
            $locker = $user->acquireLocker('VIP::create');
            if (!$locker) {
                return err('锁定用户失败，请重试！');
            }
            if (\zovye\business\VIP::exists($agent, $user) || \zovye\business\VIP::existsByMobile($agent, $user->getMobile())) {
                return err('这个用户已经是VIP用户！');
            }

            if (\zovye\business\VIP::addUser($agent, $user, $name)) {
                return ['msg' => '创建成功！'];
            }
        }

        if (isset($mobile)) {
            if (\zovye\business\VIP::addMobile($agent, $name, $mobile)) {
                return ['msg' => '手机号码添加成功！'];
            }
        }

        return err('创建失败！');
    }

    public static function remove(agentModelObj $agent): array
    {
        $vip = \zovye\business\VIP::get(Request::int('id'));
        if (empty($vip)) {
            return err('找不到指定的vip用户！');
        }

        if ($vip->getAgentId() != $agent->getId()) {
            return err('没有权限！');
        }

        $vip->destroy();

        return ['msg' => '删除成功！'];
    }

    public static function getList(agentModelObj $agent): array
    {
        $query = \zovye\business\VIP::query(['agent_id' => $agent->getId()]);

        $result = [];
        /** @var vipModelObj $vip */
        foreach ($query->findAll() as $vip) {
            $data = [
                'id' => $vip->getId(),
                'name' => $vip->getName(),
                'mobile' => $vip->getMobile(),
                'device' => [],
                'createtime_formatted' => date('Y-m-d H:i:s', $vip->getCreatetime()),
            ];

            $user = $vip->getUser();
            if ($user) {
                $data['user'] = $user->profile();
            }

            $ids = $vip->getDeviceIds();
            foreach ($ids as $id) {
                $device = \zovye\domain\Device::get($id);
                if ($device) {
                    $profile = [
                        'id' => $device->getImei(),
                        'name' => $device->getName(),
                    ];
                    $profile['enabled'] = $device->getAgentId() == $agent->getId();
                    $data['device'][] = $profile;
                }
            }

            $result[] = $data;
        }

        return $result;
    }

    public static function updateDeviceIds(agentModelObj $agent): array
    {
        $vip = \zovye\business\VIP::get(Request::int('vip'));
        if ($vip->getAgentId() != $agent->getId()) {
            return err('没有权限管理这个VIP用户！');
        }

        //ids 中包括的id是设备imei
        $imei_list = Request::array('ids');
        $ids = [];

        foreach ($imei_list as $imei) {
            $device = \zovye\domain\Device::get(strval($imei), true);
            if (empty($device)) {
                return err('找不到这个设备！');
            }
            if ($device->getAgentId() != $agent->getId()) {
                return err('没有权限管理这个设备！');
            }
            $ids[] = $device->getId();
        }

        $vip->setDeviceIds($ids);

        if (!$vip->save()) {
            return err('保存数据失败！');
        }

        return ['msg' => '设置成功！'];
    }

    // 设备续费（有效期）
    public static function payForDeviceRenewal(agentModelObj $agent): array
    {
        $device = \zovye\domain\Device::get(Request::str('id'), true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        if ($device->getAgentId() != $agent->getId()) {
            return err('没有权限管理这个设备！');
        }

        //获取代理商手机对应的小程序用户
        $user = User::findOne(['mobile' => $agent->getMobile(), 'app' => User::WxAPP]);
        if (empty($user)) {
            return err('找不到对应的小程序用户，无法创建支付！');
        }

        return Helper::createForDeviceRenewal($user, $device, Request::int('years', 1));
    }
}