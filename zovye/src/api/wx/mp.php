<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\api\wx;

use Exception;
use zovye\App;
use zovye\We7;
use zovye\Util;
use zovye\Media;
use zovye\State;
use zovye\Device;
use zovye\DouYin;
use zovye\Schema;
use zovye\Account;
use zovye\request;
use zovye\WxPlatform;
use function zovye\err;
use function zovye\error;
use function zovye\request;
use function zovye\is_error;
use zovye\model\agentModelObj;
use function zovye\toCamelCase;
use zovye\model\accountModelObj;
use zovye\model\device_groupsModelObj;

class mp
{
    /**
     * 公众号详情.
     *
     * @return array
     */
    public static function detail(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_xf');

        $uid = request::trim('uid');
        if ($uid) {
            $account = Account::findOne(['uid' => $uid]);
            $agent_id = $user->getAgentId();

            if (empty($account) || $account->getAgentId() != $agent_id) {
                return error(State::ERROR, '没有权限操作！');
            }

            return self::formatAccountInfo($account, true);
        }

        return error(State::ERROR, '操作失败！');
    }

    public static function formatAccountInfo(accountModelObj $account, $more = false): array
    {
        $data = [
            'uid' => $account->getUid(),
            'banned' => $account->getState() != Account::BANNED,
            'type' => $account->getType(),
            'name' => $account->getName(),
            'title' => $account->getTitle(),
            'descr' => $account->getDescription(),
            'groupname' => $account->getGroupName(),
            'clr' => $account->getClr(),
            'scname' => $account->getScname(),
            'count' => $account->getCount(),
            'total' => $account->getTotal(),
            'img' => Util::toMedia($account->getImg()),
            'url' => $account->getUrl(),
            'orderno' => $account->getOrderNo(),
            'orderlimits' => $account->getOrderLimits(),
        ];

        if ($account->isVideo()) {
            $data['media'] = Util::toMedia($account->getMedia());
            $data['duration'] = $account->getDuration();
        } elseif ($account->isDouyin()) {
            $config = $account->get('config', []);
            $data['url'] = $config['url'];
            $data['openid'] = $config['openid'];
        } else {
            $data['qrcode'] = Util::toMedia($account->getQrcode());
        }

        $user = common::getAgent();

        if ($more) {
            $data['img_signatured'] = sha1(App::uid() . CLIENT_IP . $account->getImg()) . '@' . $account->getImg();
            if ($account->isVideo()) {
                $data['media_signatured'] = sha1(App::uid() . CLIENT_IP . $account->getQrcode()) . '@' . $account->getMedia();
            } else {
                $data['qrcode_signatured'] = sha1(App::uid() . CLIENT_IP . $account->getQrcode()) . '@' . $account->getQrcode();
            }

            $data['assigned'] = [];
            $assign_data = $account->get('assigned', []);
            if ($assign_data['devices']) {
                $agent_id = $user->getAgentId();
                foreach ($assign_data['devices'] as $id) {
                    $device = Device::get($id);
                    if ($device && $device->getAgentId() == $agent_id) {
                        $data['assigned'][] = $device->getImei();
                    }
                }
            }

            $data['groups'] = is_array($assign_data['groups']) ? $assign_data['groups'] : [];

            $data['limits'] = [
                'sex' => 'none',
                'os' => 'none',
            ];

            $limits = $account->get('limits', []);
            if (!empty($limits['male']) && empty($limits['female'])) {
                $data['limits']['sex'] = 'male';
            }

            if (empty($limits['male']) && !empty($limits['female'])) {
                $data['limits']['sex'] = 'female';
            }

            if (!empty($limits['ios']) && empty($limits['android'])) {
                $data['limits']['os'] = 'ios';
            }

            if (empty($limits['ios']) && !empty($limits['android'])) {
                $data['limits']['os'] = 'android';
            }
        }

        return $data;
    }

    /**
     * 分配公众号.
     *
     * @return array
     */
    public static function assign(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_xf');

        $devices = request::is_array('devices') ? request::array('devices') : [];
        $uid = request::trim('uid');
        if ($uid) {
            $account = Account::findOne(['uid' => $uid]);
            $agent_id = $user->getAgentId();

            if (empty($account) || $account->getAgentId() != $agent_id) {
                return error(State::ERROR, '没有权限操作！');
            }

            $assign_data = [$account];

            if (request('all')) {
                $assign_data[] = $user->isAgent() ? $user : $user->getPartnerAgent();
            } else {
                foreach ($devices as $id) {
                    $device = \zovye\api\wx\device::getDevice($id);
                    if ($device && $device->getAgentId() == $agent_id) {
                        $assign_data[] = $device;
                    }
                }
            }

            if (Account::bind($assign_data, ['overwrite' => true])) {
                return ['msg' => '保存成功！'];
            }
        }

        return error(State::ERROR, '操作失败！');
    }

    /**
     * 上传文件或者视频.
     *
     * @return array
     */
    public static function upload(): array
    {
        if (!common::checkCurrentUserPrivileges('F_xf', true) && !common::checkCurrentUserPrivileges('F_sp', true)) {
            return error(State::ERROR, '没有权限上传文件，请联系管理员！');
        }

        $media = $_FILES['pic'] ?? $_FILES['video'];
        $type = isset($_FILES['pic']) ? Media::IMAGE : Media::VIDEO;

        if ($media) {
            We7::load()->func('file');

            $res = We7::file_upload($media, $type);
            if (is_error($res)) {
                return $res;
            }

            $filename = $res['path'];
            if ($res['success'] && $filename) {
                try {
                    We7::file_remote_upload($filename);
                } catch (Exception $e) {
                    Util::logToFile('doPageMpupload', $e->getMessage());
                    return error(State::ERROR, $e->getMessage());
                }

                $x = sha1(App::uid() . CLIENT_IP . $filename);

                return ['file' => "{$x}@{$filename}", 'fullpath' => Util::toMedia($filename)];
            }
        }

        return error(state::ERROR, '上传失败！');
    }

    /**
     * 公众号列表.
     *
     * @return array
     */
    public static function accounts(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_xf');

        $page = max(1, request::int('page'));
        $page_size = max(1, request::int('pagesize', DEFAULT_PAGESIZE));

        $query = Account::query();
        $query->where(['agent_id' => $user->getAgentId()]);

        if (request::has('keyword')) {
            $keyword = request::trim('keyword');
            $query->whereOr([
                'name LIKE' => "%{$keyword}%",
                'title LIKE' => "%{$keyword}%",
                'descr LIKE' => "%{$keyword}%",
            ]);
        }

        $total = $query->count();

        $result = [
            'total' => $total,
            'page' => $page,
            'pagesize' => $page_size,
            'totalpage' => ceil($total / $page_size),
            'list' => [],
        ];

        if ($total > 0) {
            $query->page($page, $page_size)->orderBy('order_no desc');
            foreach ($query->findAll() as $account) {
                $data = mp::formatAccountInfo($account, true);
                if ($account->isAuth()) {
                    $data['config'] = $account->get('config', []);
                }
                $result['list'][] = $data;
            }
        }

        if (App::isWxPlatformEnabled()) {
            $result['wxplatform'] = [
                'enabled' => App::isWxPlatformEnabled(),
            ];
        }

        return $result;
    }

    /**
     * 禁用公众号.
     *
     * @return array
     */
    public static function ban(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_xf');

        $uid = request::trim('uid');
        if ($uid) {
            $account = Account::findOne(['uid' => $uid]);
            if ($account) {
                if ($account->getAgentId() == $user->getAgentId()) {
                    if ($account->isSpecial() || $account->isAuth()) {
                        return ['msg' => '特殊吸粉或者授权接入的公众号无法禁用！'];
                    }
                    if ($account->isBanned()) {
                        $account->setState(Account::NORMAL);
                    } else {
                        $account->setState(Account::BANNED);
                    }
                    if ($account->save() && Account::updateAccountData()) {
                        return ['msg' => '操作成功！'];
                    }
                }
            }
        }

        return error(State::ERROR, '没有权限操作！');
    }

    /**
     * 删除公众号.
     *
     * @return array
     */
    public static function delete(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_xf');

        $uid = request::trim('uid');
        $account = Account::findOne(['uid' => $uid]);
        if (empty($account)) {
            return error(State::ERROR, '找不到指定的公众号！');
        }

        if ($account->getAgentId() != $user->getAgentId()) {
            return error(State::ERROR, '没有权限操作这个公众号！');
        }

        $account->destroy();
        if (Account::updateAccountData()) {
            return ['msg' => '删除成功！'];
        }

        return ['msg' => '删除失败！'];
    }

    /**
     * 新建或者编辑公众号.
     *
     * @return array
     */
    public static function save(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_xf');

        $data = [
            'agent_id' => $user->getAgentId(),
            'name' => request::trim('name'),
            'title' => request::trim('title'),
            'descr' => request::str('descr'),
            'group_name' => request::str('groupname'),
            'order_no' => min(999, request::int('orderno', 0)),
            'clr' => request::has('clr') ? request::trim('clr') : 'gray',
            'scname' => request::has('scname') ? request::trim('scname') : Schema::DAY,
            'count' => request::int('count'),
            'total' => request::int('total'),
        ];

        if (empty($data['name'])) {
            return error(State::ERROR, '帐号不能为空！');
        } else {
            $account = Account::findOne(['name' => $data['name']]);
            if ($account) {
                if ($account->getAgentId() != $user->getAgentId()) {
                    return error(State::ERROR, '公众号帐号不能重复！');
                }
            }
        }

        if (request::has('uid')) {
            $account = Account::findOne(['uid' => request::str('uid')]);
            if ($account) {
                if ($account->getAgentId() != $user->getAgentId()) {
                    return error(State::ERROR, '公众号帐号不能重复！');
                }
            }
        }

        if (!Schema::has($data['scname'])) {
            return error(State::ERROR, '领取频率只是每天/每周/每月！');
        }

        if (request::has('qrcode')) {
            $type = Account::NORMAL;
            list($sha1val, $url) = explode('@', request::str('qrcode'), 2);
            if (empty($sha1val) || empty($url) || sha1(App::uid() . CLIENT_IP . $url) != $sha1val) {
                return error(State::ERROR, '请上传正确的二维码文件！');
            }
        } elseif (request::has('media')) {
            $type = Account::VIDEO;
            list($sha1val, $url) = explode('@', request::str('media'), 2);
            if (empty($sha1val) || empty($url) || sha1(App::uid() . CLIENT_IP . $url) != $sha1val) {
                return error(State::ERROR, '请上传正确的视频文件！');
            }
        } elseif (request::has('douyinUrl')) {
            $type = Account::DOUYIN;
        } else {
            return error(State::ERROR, '请指定正确的文件网址！');
        }

        list($sha1val, $img_url) = explode('@', request::str('img'), 2);
        if (empty($sha1val) || empty($img_url) || sha1(App::uid() . CLIENT_IP . $img_url) != $sha1val) {
            return error(State::ERROR, '请上传正确的头像文件！');
        }

        $data['qrcode'] = $url;
        $data['img'] = $img_url;

        $limits = [];
        if (request::str('sex') == 'male') {
            $limits['male'] = 1;
            $limits['female'] = 0;
            $limits['unknown_sex'] = 0;
        } elseif (request::str('sex') == 'female') {
            $limits['male'] = 0;
            $limits['female'] = 1;
            $limits['unknown_sex'] = 0;
        } else {
            $limits['male'] = 1;
            $limits['female'] = 1;
            $limits['unknown_sex'] = 1;
        }

        if (request::str('os') == 'ios') {
            $limits['ios'] = 1;
            $limits['android'] = 0;
        } elseif (request::str('os') == 'android') {
            $limits['ios'] = 0;
            $limits['android'] = 1;
        } else {
            $limits['ios'] = 1;
            $limits['android'] = 1;
        }

        if ($type == Account::DOUYIN) {
            $data['total'] = 1;
        }

        $data['order_limits'] = request::int('orderlimits');

        if ($account) {
            foreach ($data as $key => $val) {
                $key_name = 'get' . ucfirst(toCamelCase($key));
                if ($val != $account->$key_name()) {
                    $set_name = 'set' . ucfirst(toCamelCase($key));
                    $account->$set_name($val);
                }
            }
            if ($account->isAuth()) {
                $account->set('config', [
                    'type' => Account::AUTH,
                    'open' => [
                        'timing' => request::int('OpenTiming'),
                        'msg' => request::str('OpenMsg'),
                    ]
                ]);
            }
        } else {
            $data['uid'] = Account::makeUID(request::trim('name'));
            $data['state'] = $type;
            $data['url'] = Account::createUrl($data['uid'], ['from' => 'account']);
            $account = Account::create($data);
        }

        if (empty($account)) {
            return error(State::ERROR, '操作失败！');
        }

        $account->setExtraData('update', [
            'time' => time(),
            'user' => $user->profile(),
        ]);

        if ($account->save() && $account->set('limits', $limits) && Account::updateAccountData()) {
            if ($account->isVideo()) {
                $account->set('config', [
                    'type' => Account::VIDEO,
                    'video' => [
                        'duration' => request::int('duration', 1),
                    ]
                ]);
            } elseif ($account->isDouyin()) {
                $openid = $account->settings('config.openid', '');
                $account->set('config', [
                    'type' => Account::DOUYIN,
                    'url' => request::trim('douyinUrl'),
                    'openid' => $openid,
                ]);
            }
            return ['msg' => '保存成功！'];
        }

        return error(State::ERROR, '保存数据失败！');
    }

    public static function groupAssign(): array
    {
        $user = agent::getAgent();

        common::checkCurrentUserPrivileges('F_xf');

        $uid = request::trim('uid');
        if ($uid) {
            $account = Account::findOne(['uid' => $uid]);
            $agent_id = $user->getAgentId();

            if (empty($account) || $account->getAgentId() != $agent_id) {
                return error(State::ERROR, '没有权限操作这个公众号！');
            }

            $assign_data = [$account];

            if (request::bool('all')) {
                $assign_data[] = $user;
            } else {

                $groups = request::is_array('groups') ? request::array('groups') : [];

                foreach ($groups as $id) {
                    /** @var device_groupsModelObj $one */
                    $one = \zovye\Group::get($id);
                    if ($one) {
                        $query_arr = ['group_id' => $one->getId()];
                        if ($one->getAgentId() != $user->getAgentId()) {
                            //平台的
                            $query_arr['agent_id'] = $user->getAgentId();
                        } else {
                            $assign_data[] = $one;
                        }

                        $devices = Device::query(We7::uniacid($query_arr))->findAll();
                        foreach ($devices as $device) {
                            $assign_data[] = $device;
                        }
                    }
                }
            }

            if (Account::bind($assign_data, ['overwrite' => true])) {
                return ['msg' => '保存成功！'];
            }
        }

        return error(State::ERROR, '操作失败！');
    }

    public static function mpAuthUrl(): array
    {
        /** @var agentModelObj $user */
        $user = agent::getAgent();

        common::checkCurrentUserPrivileges('F_xf');

        $url = WxPlatform::getPreAuthUrl([
            'agent' => $user->getId(),
        ]);

        if (empty($url)) {
            return err('暂时无法获取授权转跳网址！');
        }

        return ['url' => $url];
    }

    public static function getDouyinAuthQRCode(): array
    {
        $account_uid = request::trim('uid');
        $url = Util::murl('douyin', [
            'op' => 'get_openid',
            'uid' => $account_uid,
        ]);
     
        $result = Util::createQrcodeFile("douyin_$account_uid", DouYin::redirectToAuthorizeUrl($url, true));
    
        if (is_error($result)) {
            return err('创建二维码文件失败！');
        }
    
        return [
            'uid' => $account_uid,
            'qrcode_url' => Util::toMedia($result),
        ];
    }
}
