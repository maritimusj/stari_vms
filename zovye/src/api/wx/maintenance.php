<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wx;

use DateTime;
use zovye\Device;
use zovye\model\device_recordModelObj;
use zovye\model\deviceModelObj;
use zovye\model\settings_userModelObj;
use zovye\request;
use zovye\Keeper;
use zovye\model\keeperModelObj;
use zovye\State;
use zovye\User;
use zovye\model\userModelObj;
use zovye\We7;
use function zovye\error;
use function zovye\m;

class maintenance
{
    public static function createMaintainRecord($user, $user_id = 0): array
    {
        $device_id = request::int('device_id');
        $user_id = $user_id ?: $user->getId();
        $cate = request::str('cate');

        $agent_id = $user->getAgentId();

        $d_id = 0;
        if ($device_id) {
            $dev_res = Device::get($device_id, true);
            $d_id = $dev_res->getId();
        }

        $data = [
            'agent_id' => $agent_id,
            'device_id' => $d_id,
            'user_id' => $user_id,
            'cate' => $cate,
            'createtime' => time(),
        ];

        if (m('device_record')->create($data)) {
            return ['msg' => '维护记录保存成功！'];
        } else {
            return error(State::ERROR, '维护记录保存失败！');
        }
    }

    public static function record(): array
    {
        $user = common::getAgent();

        return maintenance::createMaintainRecord($user);
    }

    public static function keeperMaintainRecord(): array
    {
        $user = \zovye\api\wx\keeper::getKeeper();
        $real_user = $user->getUser();
        $user_id = $real_user->getId();

        return maintenance::createMaintainRecord($user, $user_id);
    }

    public static function MRList(): array
    {
        $user = common::getAgent();

        $agent = $user->isAgent() ? $user : $user->getPartnerAgent();

        return maintenance::getMRList($agent);
    }

    public static function keeperMRList(): array
    {
        $keeper = \zovye\api\wx\keeper::getKeeper();

        $agent = $keeper->getAgent();

        return maintenance::getMRList($agent);
    }

    public static function getMRList($agent): array
    {
        $device = request::trim('device');
        $keeper = request::int('keeper');
        $cate = request::int('cate');

        $page = max(1, request::int('page'));
        $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

        if (request::has('start')) {
            $s_date = DateTime::createFromFormat('Y-m-d H:i:s', request::str('start').' 00:00:00');
        } else {
            $s_date = new DateTime('first day of this month 00:00:00');
        }

        if (request::has('end')) {
            $e_date = DateTime::createFromFormat('Y-m-d H:i:s', request::str('end').' 00:00:00');
            $e_date->modify('next day');
        } else {
            $e_date = new DateTime('first day of next month 00:00:00');
        }

        $condition = [
            'agent_id' => $agent->getId(),
            'createtime >=' => $s_date->getTimestamp(),
            'createtime <' => $e_date->getTimestamp(),
        ];

        if ($keeper != 0) {
            $condition['user_id'] = $keeper;
        }
        if ($cate != 0) {
            $condition['cate'] = $cate;
        }

        $query = m('device_record')->query($condition);

        $device_ids = [];
        if ($device != '') {
            $device_res = Device::query()->whereOr([
                'name LIKE' => "%$device%",
                'imei LIKE' => "%$device%",
            ])->findAll([], true);
            foreach ($device_res as $item) {
                $device_ids[] = $item->getId();
            }

            if (empty($device_ids)) {
                $query->where('id = -1');
            } else {
                $device_ids = array_unique($device_ids);
                $query->where(['device_id' => $device_ids]);
            }
        } else {
            $query->where('device_id != 0');
        }

        $total = $query->count();

        /** @var device_recordModelObj $res */
        $res = $query->page($page, $page_size)->orderBy('createtime DESC')->findAll();

        /** @var keeperModelObj $keep_res */
        $keep_res = Keeper::query(['agent_id' => $agent->getId()])->findAll();
        $keep_assoc = [];
        foreach ($keep_res as $item) {
            $user = $item->getUser();
            if ($user) {
                $keep_assoc[$user->getId()] = $item->getName();
            }
        }

        $s_query = m('settings_user');
        $s_arr = [];

        $s_query = $s_query->query(We7::uniacid([]));
        $s_res = $s_query->where(['name LIKE' => '%partnerData'])->findAll();
        $_reg = '/.+:(.+):.+/';
        /** @var settings_userModelObj $val */
        foreach ($s_res as $val) {
            $s_data = unserialize($val->getData());
            $s_agent = $s_data['agent'] ?? '';
            if ($s_agent == $agent->getId()) {
                $str = $val->getName();
                preg_match($_reg, $str, $mat);
                if (isset($mat[1])) {
                    $s_arr[] = $mat[1];
                }
            }
        }
        $user_res = User::query()->where(['id' => $s_arr])->findAll();
        /** @var userModelObj $item */
        foreach ($user_res as $item) {
            $keep_assoc[$item->getId()] = $item->getNickname();
        }
        $keep_assoc[$agent->getId()] = $agent->getName();

        $rec_type = [
            '1' => '开门记录',
            '2' => '消毒记录',
            '3' => '换电池记录',
        ];
        $device_ids = [];
        $data = [];

        /** @var device_recordModelObj $item */
        foreach ($res as $item) {
            $data[] = [
                'id' => $item->getId(),
                'deviceId' => $item->getDeviceId(),
                'cate' => $rec_type[$item->getCate()],
                'keeper' => isset($keep_assoc[$item->getUserId()]) ? $keep_assoc[$item->getUserId()] : '',
                'agent' => $agent->getName(),
                'createtime' => date('Y-m-d H:i:s', $item->getCreatetime()),
            ];
            $device_ids[] = $item->getDeviceId();
        }

        $device_assoc = [];
        if (!empty($device_ids)) {
            $device_ids = array_unique($device_ids);
            $device_res = Device::query()->where(['id' => $device_ids])->findAll();
            /** @var deviceModelObj $item */
            foreach ($device_res as $item) {
                $device_assoc[$item->getId()] = [
                    'name' => $item->getName(),
                    'imei' => $item->getImei(),
                ];
            }
        }

        return [
            'data' => $data,
            'device_assoc' => $device_assoc,
            'device' => $device,
            'keeper' => $keeper,
            'cate' => $cate,
            'start' => $s_date,
            'end' => $e_date,
            'page' => $page,
            'total' => $total,
        ];
    }
}
