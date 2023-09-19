<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wx;

use zovye\api\common;
use zovye\domain\Advertising;
use zovye\domain\Device;
use zovye\model\advertisingModelObj;
use zovye\model\agentModelObj;
use zovye\model\device_groupsModelObj;
use zovye\Request;
use zovye\util\Helper;
use zovye\util\Util;
use function zovye\err;
use function zovye\is_error;

class ad
{
    /**
     * 保存广告分配
     */
    public static function assign(agentModelObj $agent): array
    {
        common::checkPrivileges($agent, 'F_gg');

        $guid = Request::trim('id');
        $ad = Advertising::findOne("SHA1(CONCAT(id,'{$agent->getOpenid()}'))='$guid'");

        if (empty($ad)) {
            return err('找不到这条广告！');
        }

        if ($ad->getAgentId() != $agent->getId()) {
            return err('没有权限执行这个操作！');
        }

        if (!$ad->isReviewPassed()) {
            return err('这个广告还没有通过审核，无法分配！');
        }

        $data = ['devices' => []];
        $devices = Request::is_array('devices') ? Request::array('devices') : [];

        if ($devices) {
            //检查设备ID是否合法，并保存设备内部ＩＤ
            foreach ($devices as $uid) {
                $device = Device::get($uid, true);
                if ($device && $device->getAgentId() == $agent->getId()) {
                    $data['devices'][] = $device->getId();
                }
            }
        }

        $origin_data = $ad->get('assigned', []);
        if ($ad->updateSettings('assigned', $data) && Advertising::update($ad)) {
            if (Advertising::notifyAll($origin_data, $data)) {
                return ['msg' => '保存成功！！'];
            }

            return ['msg' => '保存成功！？'];
        }

        return err('操作失败！');
    }

    /**
     * 获取广告列表
     */
    public static function list(agentModelObj $agent): array
    {
        common::checkPrivileges($agent, 'F_gg');

        $type = Request::int('type') ?: Advertising::SCREEN;

        $query = Advertising::query(['state' => Advertising::NORMAL, 'type' => $type]);

        if (Request::has('deviceId')) {
            $device = \zovye\api\wx\device::getDevice(Request::int('deviceId'));
            if ($device->getAgentId()) {
                $query->where(['agent_id' => $device->getAgentId()]);
            }
        } else {
            $query->where(['agent_id' => $agent->getId()]);
        }

        $total = $query->count();

        $page = max(1, Request::int('page'));
        $page_size = max(1, Request::int('pagesize', DEFAULT_PAGE_SIZE));

        $result = [
            'page' => $page,
            'pagesize' => $page_size,
            'total' => $total,
            'totalpage' => ceil($total / $page_size),
            'list' => [],
        ];

        if ($total > 0) {
            $query->page($page, $page_size);
            $query->orderBy('id DESC');

            /** @var advertisingModelObj $ad */
            foreach ($query->findAll() as $ad) {
                //设备分配情况
                $assign_data = $ad->get('assigned', []);
                $devices = [];
                if (is_array($assign_data['devices'])) {
                    foreach ($assign_data['devices'] as $id) {
                        $device = Device::get($id);
                        if ($device && $device->getAgentId() == $agent->getId()) {
                            $devices[] = $device->getImei();
                        }
                    }
                }

                $groups = is_array($assign_data['groups']) ? $assign_data['groups'] : [];

                $reviewResult = $ad->getReviewResult() ?: 0;

                $data = [
                    'id' => sha1($ad->getId().$agent->getOpenid()),
                    'type' => intval($ad->getType()),
                    'state' => intval($ad->getState()),
                    'type_formatted' => Advertising::desc(intval($ad->getType())),
                    'title' => strval($ad->getTitle()),
                    'createtime_formatted' => date('Y-m-d H:i:s', $ad->getCreatetime()),
                    'reviewResult' => $reviewResult,
                    'reviewState' => Advertising::getReviewResultTitle($reviewResult),
                    'assigned' => $devices,
                    'groups' => $groups,
                ];

                if ($type == Advertising::SCREEN) {

                    $media = $ad->getExtraData('media');
                    if ($media == Advertising::MEDIA_SRT) {
                        $data['text'] = $ad->getExtraData('text');
                    } else {
                        $data['filename'] = $ad->getExtraData('url', '');
                        $data['url'] = Util::toMedia($data['filename']);
                        if ($media == Advertising::MEDIA_IMAGE) {
                            $data['duration'] = $ad->getExtraData('duration', 10);
                        }
                    }
                    $data['media'] = $media;
                    $data['area'] = $ad->getExtraData('area', 0);
                    $data['media_formatted'] = Advertising::desc($media);
                    $data['type_formatted'] .= "({$data['media']})";

                } elseif (in_array($type, [advertising::WELCOME_PAGE, Advertising::GET_PAGE])) {

                    $images = $ad->getExtraData('images', []);
                    $data['filename'] = $images;
                    $data['images'] = array_map(function ($url) {
                        return Util::toMedia($url);
                    }, $images);

                    $data['link'] = $ad->getExtraData('link');
                    if ($type == Advertising::WELCOME_PAGE) {
                        $data['app_id'] = $ad->getExtraData('app_id');
                        $data['app_path'] = $ad->getExtraData('app_path');
                    }

                } elseif ($type == Advertising::REDIRECT_URL) {

                    $data['url'] = $ad->getExtraData('url', '');
                    $data['delay'] = $ad->getExtraData('delay', 10);
                    $data['when'] = $ad->getExtraData(
                        'when',
                        [
                            'success' => 0,
                            'fail' => 0,
                        ]
                    );

                } elseif ($type == Advertising::PUSH_MSG) {

                    $data['msg_type'] = $ad->getExtraData('msg.type');
                    $data['msg_typename'] = Advertising::desc($data['msg_type']);
                    $data['delay'] = $ad->getExtraData('delay');
                    $data['msg'] = $ad->getExtraData('msg');

                } elseif ($type == Advertising::LINK) {

                    $data['link'] = $ad->getExtraData('url', '');
                    $data['app_id'] = $ad->getExtraData('app_id');
                    $data['app_path'] = $ad->getExtraData('app_path');
                    $data['images'] = [];
                    $data['filename'] = [];
                    $image = $ad->getExtraData('image', "");
                    if ($image) {
                        $data['images'][] = Util::toMedia($image);
                        $data['filename'][] = $image;
                    }
                } elseif ($type == Advertising::GOODS) {
                    $data['images'] = [];
                    $data['filename'] = [];

                    $image = $ad->getExtraData('image', '');
                    if (!empty($image)) {
                        $data['images'][] = Util::toMedia($image);
                        $data['filename'][] = $image;
                    }

                    $data['link'] = $ad->getExtraData('url');
                    $data['price'] = $ad->getExtraData('price');
                    $data['discount_price'] = $ad->getExtraData('discount_price');
                    $data['app_id'] = $ad->getExtraData('app_id');
                    $data['app_path'] = $ad->getExtraData('app_path');

                } elseif ($type == Advertising::QRCODE) {

                    $data['text'] = $ad->getExtraData('text');
                    $data['image'] = $ad->getExtraData('image');

                }

                $result['list'][] = $data;
            }
        }

        return $result;
    }

    /**
     * 创建广告
     */
    public static function createOrUpdate(agentModelObj $agent): array
    {
        common::checkPrivileges($agent, 'F_gg');

        $ad = null;

        $guid = Request::trim('id');
        if (!empty($guid)) {
            $query = Advertising::query([
                'agent_id' => $agent->getId(),
                'state' => Advertising::NORMAL,
            ]);

            $query->where("SHA1(CONCAT(id,'{$agent->getOpenid()}'))='$guid'");

            $ad = $query->findOne();
            if (empty($ad)) {
                return err('找不到这个广告！');
            }
        }

        return Advertising::createOrUpdate($agent, $ad, Request::all());
    }

    /**
     * 删除广告
     */
    public static function delete(agentModelObj $agent): array
    {
        common::checkPrivileges($agent, 'F_gg');

        $guid = Request::trim('id');

        /** @var advertisingModelObj $ad */
        $ad = Advertising::query(['state' => Advertising::NORMAL])
            ->where("SHA1(CONCAT(id,'{$agent->getOpenid()}'))='$guid'")
            ->findOne();

        if (empty($ad)) {
            return err('找不到这条广告！');
        }

        if ($ad->getAgentId() != $agent->getId()) {
            return err('没有权限执行这个操作！');
        }

        $title = $ad->getTitle();
        $assign_data = $ad->settings('assigned', []);

        if (Advertising::update($ad) && $ad->destroy()) {
            Advertising::notifyAll($assign_data);

            return ['msg' => "{$title}删除成功！"];
        }

        return err("{$title}删除失败！");
    }

    /**
     * 上传广告资源
     */
    public static function uploadFile(agentModelObj $agent): array
    {
        common::checkPrivileges($agent, 'F_gg');

        $res = Helper::upload('file', Request::str('type') ?: Advertising::MEDIA_IMAGE);
        if (is_error($res)) {
            return $res;
        }

        return [
            'filename' => $res,
            'url' => Util::toMedia($res),
        ];
    }

    public static function groupAssign(agentModelObj $agent): array
    {
        common::checkPrivileges($agent, 'F_gg');

        $guid = Request::trim('id');
        $ad = Advertising::findOne("SHA1(CONCAT(id,'{$agent->getOpenid()}'))='$guid'");

        if (empty($ad)) {
            return err('找不到这条广告！');
        }

        if ($ad->getAgentId() != $agent->getId()) {
            return err('没有权限执行这个操作！');
        }

        if (!$ad->isReviewPassed()) {
            return err('这个广告还没有通过审核，无法分配！');
        }

        $groups = Request::is_array('groups') ? Request::array('groups') : [];

        $group_arr = [];
        $device_arr = [];

        foreach ($groups as $id) {
            /** @var device_groupsModelObj $one */
            $one = \zovye\domain\Group::get($id);
            if ($one) {
                $query_arr = ['group_id' => $one->getId()];
                if ($one->getAgentId() != $agent->getId()) {
                    //平台的
                    $query_arr['agent_id'] = $agent->getId();
                } else {
                    $group_arr[] = $one->getId();
                }

                $devices = Device::query($query_arr)->findAll();
                foreach ($devices as $device) {
                    $device_arr[] = $device->getId();
                }
            }
        }

        $data = [
            'groups' => $group_arr,
            'devices' => $device_arr,
        ];

        $origin_data = $ad->get('assigned', []);
        if ($ad->updateSettings('assigned', $data) && Advertising::update($ad)) {
            if (Advertising::notifyAll($origin_data, $data)) {
                return ['msg' => '保存成功！'];
            }
        }

        return err('操作失败！');
    }
}