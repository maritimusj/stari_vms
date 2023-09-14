<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\domain;

use zovye\base\ModelObjFinder;
use zovye\business\VIP;
use zovye\model\advertisingModelObj;
use zovye\model\agentModelObj;
use zovye\model\deviceModelObj;
use zovye\model\goodsModelObj;
use zovye\model\keeperModelObj;
use zovye\model\userModelObj;
use zovye\Pay;
use zovye\We7;
use function zovye\err;
use function zovye\m;
use function zovye\settings;

class Agent
{
    const REG_MODE_NORMAL = 0;
    const REG_MODE_AUTO = 1;

    /**
     * @param $id
     * @param bool $is_openid
     * @return agentModelObj|null
     */
    public static function get($id, bool $is_openid = false): ?agentModelObj
    {
        static $cache = [];
        if ($id) {
            if ($cache[$id]) {
                return $cache[$id];
            }
            if ($is_openid) {
                /** @var userModelObj $user */
                $user = self::query()->findOne(['openid' => strval($id)]);
            } else {
                $user = self::query()->findOne(['id' => intval($id)]);
            }
            if ($user) {
                $agent = $user->agent();
                $cache[$user->getId()] = $agent;
                $cache[$user->getOpenid()] = $agent;

                return $agent;
            }
        }

        return null;
    }

    /**
     * @param mixed $condition
     * @return ModelObjFinder
     */
    public static function query($condition = []): ModelObjFinder
    {
        if (is_array($condition) && isset($condition['id'])) {
            return m('agent_vw')->where($condition);
        }

        return m('agent_vw')->where(We7::uniacid([]))->where($condition);
    }

    public static function findOne($cond): ?agentModelObj
    {
        /** @var userModelObj $user */
        $user = self::query($cond)->findOne();
        if ($user) {
            return $user->agent();
        }

        return null;
    }

    public static function getLevels($level = '')
    {
        $key = 'agent.levels';

        return settings($level ? "$key.$level" : $key, []);
    }

    /**
     * 是否开启定位
     * @param agentModelObj $agent
     * @return bool
     */
    public static function isLocationValidateEnabled(agentModelObj $agent): bool
    {
        return !empty($agent->settings('agentData.location.validate.enabled'));
    }

    /**
     * 获取指定代理商，指定设备的支付信息
     * @param $agent agentModelObj
     * @param string $name
     * @return array
     */
    public static function getPayParams(agentModelObj $agent, string $name = ''): array
    {
        $params = $agent->settings('agentData.pay', []);

        return Pay::selectPayParams($params, $name);
    }

    public static function remove(agentModelObj $agent): array
    {
        //移除合伙人
        $agent_data = $agent->get('agentData', []);
        if ($agent_data) {
            $partners = is_array($agent_data['partners']) ? $agent_data['partners'] : [];
            if ($partners) {
                foreach ($partners as $partner_id => $data) {
                    if (!$agent->removePartner($partner_id)) {
                        return err('移除合伙人失败！');
                    }
                }
            }
        }

        if ($agent->setAgent(false) &&
            $agent->setSuperiorId(0) &&
            $agent->remove('agentData') &&
            $agent->remove('keepers')) {

            //删除登录会话数据
            $login_data = $agent->getLoginData();
            if ($login_data) {
                $login_data->destroy();
            }

            //删除相关的运营人员
            $keepers = Keeper::query(['agent_id' => $agent->getId()]);
            /** @var keeperModelObj $keeper */
            foreach ($keepers->findAll() as $keeper) {
                $keeper_user = $keeper->getUser();
                if ($keeper_user) {
                    $keeper_user->removePrincipal(Principal::Keeper);
                }
                $keeper->destroy();
            }

            //清除已绑定的设备，和绑定的运营人员，更新广告数据
            $devices = Device::query(['agent_id' => $agent->getId()]);
            /** @var deviceModelObj $device */
            foreach ($devices->findAll() as $device) {
                Device::unbind($device);
            }

            //删除设备分组
            $query = Group::query(['agent_id' => $agent->getId()]);
            foreach ($query->findAll() as $group) {
                $group->destroy();
            }

            //删除相关设备型号
            $query = DeviceTypes::query(['agent_id' => $agent->getId()]);
            foreach ($query->findAll() as $type) {
                $type->destroy();
            }

            //删除相关商品
            $query = Goods::query(['agent_id' => $agent->getId()]);
            /** @var goodsModelObj $goods */
            foreach ($query->findAll() as $goods) {
                $goods->destroy();
            }

            //删除相关广告
            $query = Advertising::query(['agent_id' => $agent->getId()]);
            /** @var advertisingModelObj $adv */
            foreach ($query->findAll() as $adv) {
                Advertising::setLastUpdate($adv->getType());
                $adv->destroy();
            }

            //删除相关vip用户数据
            VIP::removeAll($agent);

            if ($agent->save()) {
                return ['message' => '成功！'];
            }
        }

        return err('失败！');
    }

    public static function getAllSubordinates(userModelObj $user, array &$result = [], $fetch_obj = false): array
    {
        $query = User::query(['superior_id' => $user->getId()]);

        /** @var userModelObj $entry */
        foreach ($query->findAll() as $entry) {
            if (!in_array($entry->getId(), $result)) {
                $result[] = $fetch_obj ? $entry : $entry->getId();
                self::getAllSubordinates($entry, $result, $fetch_obj);
            }
        }

        return $result;
    }

    public static function getAllPartners(agentModelObj $agent): array
    {
        $partners = [];

        foreach ($agent->settings('agentData.partners', []) as $partner_id => $data) {
            $user = User::get($partner_id);
            if ($user) {
                $partners[] = $user;
            }
        }

        return $partners;
    }
}
