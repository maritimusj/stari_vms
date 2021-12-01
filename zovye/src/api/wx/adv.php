<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wx;

use DateTime;
use Exception;
use zovye\Advertising;
use zovye\Balance;
use zovye\Config;
use zovye\Log;
use zovye\model\advertisingModelObj;
use zovye\Device;
use zovye\model\device_groupsModelObj;
use zovye\request;
use zovye\Media;
use zovye\ReviewResult;
use zovye\State;
use zovye\Util;
use zovye\We7;
use function zovye\err;
use function zovye\error;
use function zovye\request;
use function zovye\is_error;

class adv
{

    /**
     * 保存广告分配.
     *
     * @return array
     */
    public static function assign(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_gg');

        $guid = request::trim('id');
        $adv = Advertising::findOne("SHA1(CONCAT(id,'{$user->getOpenid()}'))='{$guid}'");

        if (empty($adv)) {
            return error(State::ERROR, '找不到这条广告！');
        }

        if ($adv->getAgentId() != $user->getAgentId()) {
            return error(State::ERROR, '没有权限执行这个操作！');
        }

        if (!$adv->isReviewPassed()) {
            return error(State::ERROR, '这个广告还没有通过审核，无法分配！');
        }

        $data = ['devices' => []];
        $devices = request::is_array('devices') ? request::array('devices') : [];

        if ($devices) {
            //检查设备ID是否合法，并保存设备内部ＩＤ
            foreach ($devices as $uid) {
                $device = Device::get($uid, true);
                if ($device && $device->getAgentId() == $user->getAgentId()) {
                    $data['devices'][] = $device->getId();
                }
            }
        }

        $origin_data = $adv->get('assigned', []);
        if ($adv->updateSettings('assigned', $data) && Advertising::update($adv)) {
            if (Advertising::notifyAll($origin_data, $data)) {
                return ['msg' => '保存成功！！'];
            }
            return ['msg' => '保存成功！？'];
        }

        return error(State::ERROR, '操作失败！');
    }

    /**
     * 获取广告列表.
     *
     * @return array
     */
    public static function list(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_gg');

        $type = request::int('type') ?: Advertising::SCREEN;

        $query = Advertising::query(['state' => Advertising::NORMAL, 'type' => $type]);

        if (request::has('deviceId')) {
            $device = \zovye\api\wx\device::getDevice(request::int('deviceId'));
            if ($device->getAgentId()) {
                $query->where(['agent_id' => $device->getAgentId()]);
            }
        } else {
            $query->where(['agent_id' => $user->getAgentId()]);
        }

        $total = $query->count();

        $page = max(1, request::int('page'));
        $page_size = max(1, request::int('pagesize', DEFAULT_PAGE_SIZE));

        $result = [
            'page' => $page,
            'pagesize' => $page_size,
            'total' => $total,
            'totalpage' => ceil($total / $page_size),
            'list' => [],
        ];

        if ($total > 0) {
            $query->page($page, $page_size);
            $query->orderBy('id desc');

            /** @var advertisingModelObj $adv */
            foreach ($query->findAll() as $adv) {
                //设备分配情况
                $assign_data = $adv->get('assigned', []);
                $devices = [];
                if (is_array($assign_data['devices'])) {
                    foreach ($assign_data['devices'] as $id) {
                        $device = Device::get($id);
                        if ($device && $device->getAgentId() == $user->getAgentId()) {
                            $devices[] = $device->getImei();
                        }
                    }
                }

                $groups = is_array($assign_data['groups']) ? $assign_data['groups'] : [];

                $reviewResult = $adv->getReviewResult() ?: 0;

                $data = [
                    'id' => sha1($adv->getId() . $user->getOpenid()),
                    'type' => intval($adv->getType()),
                    'state' => intval($adv->getState()),
                    'type_formatted' => Advertising::desc(intval($adv->getType())),
                    'title' => strval($adv->getTitle()),
                    'createtime_formatted' => date('Y-m-d H:i:s', $adv->getCreatetime()),
                    'reviewResult' => $reviewResult,
                    'reviewState' => ReviewResult::desc($reviewResult),
                    'assigned' => $devices,
                    'groups' => $groups,
                ];

                if ($type == Advertising::SCREEN) {

                    $media = $adv->getExtraData('media');
                    if ($media == Media::SRT) {
                        $data['text'] = $adv->getExtraData('text');
                    } else {
                        $data['filename'] = strval($adv->getExtraData('url'));
                        $data['url'] = Util::toMedia($data['filename']);
                        if ($media == Media::IMAGE) {
                            $data['duration'] = $adv->getExtraData('duration', 10);
                        }
                    }
                    $data['media'] = $media;
                    $data['area'] = $adv->getExtraData('area', 0);
                    $data['media_formatted'] = Media::desc($media);
                    $data['type_formatted'] .= "({$data['media']})";

                } elseif (in_array($type, [advertising::WELCOME_PAGE, Advertising::GET_PAGE])) {

                    $images = $adv->getExtraData('images', []);
                    $data['filename'] = $images;
                    $data['images'] = array_map(function ($url) {
                        return Util::toMedia($url);
                    }, $images);

                    $data['link'] = $adv->getExtraData('link');
                    if ($type == Advertising::WELCOME_PAGE) {
                        $data['app_id'] = $adv->getExtraData('app_id');
                        $data['app_path'] = $adv->getExtraData('app_path');
                    }

                } elseif ($type == Advertising::REDIRECT_URL) {

                    $data['url'] = $adv->getExtraData('url', '');
                    $data['delay'] = $adv->getExtraData('delay', 10);
                    $data['when'] = $adv->getExtraData(
                        'when',
                        [
                            'success' => 0,
                            'fail' => 0,
                        ]
                    );

                } elseif ($type == Advertising::PUSH_MSG) {

                    $data['msg_type'] = $adv->getExtraData('msg.type');
                    $data['msg_typename'] = Media::desc($data['msg_type']);
                    $data['delay'] = $adv->getExtraData('delay');
                    $data['msg'] = $adv->getExtraData('msg');

                } elseif ($type == Advertising::LINK) {

                    $data['link'] = strval($adv->getExtraData('url'));
                    $data['app_id'] = $adv->getExtraData('app_id');
                    $data['app_path'] = $adv->getExtraData('app_path');
                    $data['images'] = [];
                    $data['filename'] = [];
                    $image = $adv->getExtraData('image', "");
                    if ($image) {
                        $data['images'][] = Util::toMedia($image);
                        $data['filename'][] = $image;
                    }
                } elseif ($type == Advertising::GOODS) {
                    $data['images'] = [];
                    $data['filename'] = [];

                    $image = $adv->getExtraData('image', '');
                    if (!empty($image)) {
                        $data['images'][] = Util::toMedia($image);
                        $data['filename'][] = $image;
                    }

                    $data['link'] = $adv->getExtraData('url');
                    $data['price'] = $adv->getExtraData('price');
                    $data['discount_price'] = $adv->getExtraData('discount_price');
                    $data['app_id'] = $adv->getExtraData('app_id');
                    $data['app_path'] = $adv->getExtraData('app_path');

                } elseif ($type == Advertising::QRCODE) {

                    $data['text'] = $adv->getExtraData('text');
                    $data['image'] = $adv->getExtraData('image');

                }

                $result['list'][] = $data;
            }
        }

        return $result;
    }

    /**
     * 创建广告
     * @return array|string[]
     */
    public static function createOrUpdate(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_gg');

        $adv = null;

        $guid = request::trim('id');
        if (!empty($guid)) {
            $query = Advertising::query([
                'agent_id' => $user->getAgentId(),
                'state' => Advertising::NORMAL,
            ]);

            $query->where("SHA1(CONCAT(id,'{$user->getOpenid()}'))='{$guid}'");

            $adv = $query->findOne();
            if (empty($adv)) {
                return err('找不到这个广告！');
            }
        }

        return Advertising::createOrUpdate($user, $adv, request::all());
    }

    /**
     * 删除广告.
     *
     * @return array
     */
    public static function delete(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_gg');

        $guid = request::trim('id');

        /** @var advertisingModelObj $adv */
        $adv = Advertising::query(['state' => Advertising::NORMAL])->where(
            "SHA1(CONCAT(id,'{$user->getOpenid()}'))='$guid'"
        )->findAll()->current();

        if (empty($adv)) {
            return error(State::ERROR, '找不到这条广告！');
        }

        if ($adv->getAgentId() != $user->getAgentId()) {
            return error(State::ERROR, '没有权限执行这个操作！');
        }

        $title = $adv->getTitle();
        $assign_data = $adv->settings('assigned', []);

        if (Advertising::update($adv) && $adv->destroy()) {
            Advertising::notifyAll($assign_data, []);
            return ['msg' => "{$title}删除成功！"];
        }

        return error(State::ERROR, "{$title}删除失败！");
    }

    /**
     * 上传广告资源.
     *
     * @return array
     */
    public static function uploadFile(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_gg');

        if (!($user->isAgent() || $user->isPartner())) {
            return error(State::ERROR, '只有代理商能使用该功能！');
        }

        $type = request('type') ?: Media::IMAGE;

         if ($_FILES['file']) {
            We7::load()->func('file');
            $res = We7::file_upload($_FILES['file'], $type);

            if (is_error($res)) {
                return $res;
            }

            $filename = $res['path'];
            if ($res['success'] && $filename) {
                try {
                    We7::file_remote_upload($filename);
                } catch (Exception $e) {
                    Log::error('doPageUploadFile', $e->getMessage());
                    return error(State::ERROR, $e->getMessage());
                }
                return [
                    'filename' => $filename,
                    'url' => Util::toMedia($filename),
                ];
            }
        }

        return error(State::ERROR, '上传失败！');
    }

    public static function groupAssign(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_gg');

        $guid = request::trim('id');
        $adv = Advertising::findOne("SHA1(CONCAT(id,'{$user->getOpenid()}'))='$guid'");

        if (empty($adv)) {
            return error(State::ERROR, '找不到这条广告！');
        }

        if ($adv->getAgentId() != $user->getAgentId()) {
            return error(State::ERROR, '没有权限执行这个操作！');
        }

        if (!$adv->isReviewPassed()) {
            return error(State::ERROR, '这个广告还没有通过审核，无法分配！');
        }

        $groups = request::is_array('groups') ? request::array('groups') : [];

        $group_arr = [];
        $device_arr = [];

        foreach ($groups as $id) {
            /** @var device_groupsModelObj $one */
            $one = \zovye\Group::get($id);
            if ($one) {
                $query_arr = ['group_id' => $one->getId()];
                if ($one->getAgentId() != $user->getAgentId()) {
                    //平台的
                    $query_arr['agent_id'] = $user->getAgentId();
                } else {
                    $group_arr[] = $one->getId();
                }

                $devices = Device::query(We7::uniacid($query_arr))->findAll();
                foreach ($devices as $device) {
                    $device_arr[] = $device->getId();
                }
            }
        }

        $data = [
            'groups' => $group_arr,
            'devices' => $device_arr,
        ];

        $origin_data = $adv->get('assigned', []);
        if ($adv->updateSettings('assigned', $data) && Advertising::update($adv)) {
            if (Advertising::notifyAll($origin_data, $data)) {
                return ['msg' => '保存成功！'];
            }
        }

        return error(State::ERROR, '操作失败！');
    }

    public static function getBonus()
    {
        $user = common::getUser();
        
        $bonus = Config::app('wxapp.advs.reward.bonus', 0);
        if (empty($bonus)) {
            return err(State::ERROR, '暂时没有奖励！');
        }
        
        $limit = Config::app('wxapp.advs.reward.limit', 0);
        if ($limit > 0) {
            $begin = new DateTime();
            $begin->modify('00:00');

            $total = $user->getBalance()->query([
                'src' => Balance::REWARD_ADV,
                'createtime >' => $begin->getTimestamp(),
            ])->count();
            if ($total > $limit) {
                return err(State::ERROR, '对不起，今天的广告奖励额度已用完！');
            }    
        }

        $result = $user->getBalance()->change($bonus, Balance::REWARD_ADV);
        if (empty($result)) {
            return err(State::ERROR, '获取奖励失败！');
        }

        return [
            'balance' => $user->getBalance()->total(),
            'bonus' => $result->getXVal(),
        ];
    }
}