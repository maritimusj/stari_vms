<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wx;

use Exception;
use zovye\App;
use zovye\business\DouYin;
use zovye\business\FlashEgg;
use zovye\domain\Account;
use zovye\domain\Advertising;
use zovye\domain\Device;
use zovye\domain\Goods;
use zovye\Log;
use zovye\model\accountModelObj;
use zovye\model\agentModelObj;
use zovye\model\device_groupsModelObj;
use zovye\model\userModelObj;
use zovye\Request;
use zovye\util\DBUtil;
use zovye\util\QRCodeUtil;
use zovye\util\Util;
use zovye\We7;
use zovye\WxPlatform;
use function zovye\err;
use function zovye\is_error;
use function zovye\toCamelCase;

class mp
{
    /**
     * 公众号详情
     */
    public static function detail(agentModelObj $agent): array
    {
        common::checkCurrentUserPrivileges($agent, 'F_xf');

        $uid = Request::trim('uid');
        if ($uid) {
            $account = Account::findOneFromUID($uid);
            $agent_id = $agent->getId();

            if (empty($account) || $account->getAgentId() != $agent_id) {
                return err('没有权限操作！');
            }

            return self::formatAccountInfo($agent, $account, true);
        }

        return err('操作失败！');
    }

    public static function formatAccountInfo(userModelObj $user, accountModelObj $account, $more = false): array
    {
        $data = [
            'uid' => $account->getUid(),
            'type' => $account->getType(),
            'banned' => $account->isBanned(),
            'name' => $account->getName(),
            'title' => $account->getTitle(),
            'descr' => $account->getDescription(),
            'groupname' => $account->getGroupName(),
            'clr' => $account->getClr(),
            'scname' => $account->getScname(),
            'sccount' => $account->getSccount(),
            'count' => $account->getCount(),
            'total' => $account->getTotal(),
            'img' => Util::toMedia($account->getImg()),
            'url' => $account->getUrl(),
            'orderno' => $account->getOrderNo(),
            'orderlimits' => $account->getOrderLimits(),
        ];
        if ($account->isAuth()) {
            $data['config'] = $account->get('config', []);
        } elseif ($account->isVideo()) {
            $data['media'] = $account->getMedia(true);
            $data['duration'] = $account->getDuration();
        } elseif ($account->isDouyin()) {
            $config = $account->get('config', []);
            $data['url'] = $config['url'];
            $data['openid'] = $config['openid'];
        } elseif ($account->isWxApp()) {
            $data['username'] = $account->getConfig('username', '');
            $data['path'] = $account->getConfig('path', '');
            $data['delay'] = $account->getConfig('delay', 1);
        } elseif ($account->isFlashEgg()) {
            //完整url用于小程序端显示缩略图
            $data['goods'] = $account->getGoodsData();
            //短url用于小程序端编辑时显示
            $data['_goods'] = $account->getGoodsData(false);
            $data['media'] = [
                'type' => $account->getMediaType(),
                //完整url用于小程序端显示缩略图
                'files' => $account->getMedia(true),
                //短url用于小程序端编辑时显示
                '_files' => $account->getMedia(),
                'duration' => $account->getDuration(),
                'area' => $account->getArea(),
            ];
        } else {
            $data['qrcode'] = Util::toMedia($account->getQrcode());
        }

        $data['bonus_type'] = $account->getBonusType();

        if ($more) {
            $data['img_signatured'] = Advertising::sign($account->getImg());
            if ($account->isVideo()) {
                $data['media_signatured'] = Advertising::sign($account->getMedia());
            } else {
                $data['qrcode_signatured'] = Advertising::sign($account->getQrcode());
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
     * 分配公众号
     */
    public static function assign(agentModelObj $agent): array
    {
        common::checkCurrentUserPrivileges($agent, 'F_xf');

        $devices = Request::is_array('devices') ? Request::array('devices') : [];
        $uid = Request::trim('uid');
        if ($uid) {
            $account = Account::findOneFromUID($uid);
            $agent_id = $agent->getId();

            if (empty($account) || $account->getAgentId() != $agent_id) {
                return err('没有权限操作！');
            }

            $assign_data = [$account];

            if (Request::bool('all')) {
                $assign_data[] = $agent;
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

        return err('操作失败！');
    }

    /**
     * 上传文件或者视频
     */
    public static function upload(agentModelObj $agent): array
    {
        if (!common::checkCurrentUserPrivileges($agent, 'F_xf', true) && !common::checkCurrentUserPrivileges($agent, 'F_sp', true)) {
            return err('没有权限上传文件，请联系管理员！');
        }

        $media = $_FILES['pic'] ?? $_FILES['video'];
        $type = isset($_FILES['pic']) ? Advertising::MEDIA_IMAGE : Advertising::MEDIA_VIDEO;

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
                    Log::error('doPageMpUpload', [
                        'file' => $filename,
                        'error' => $e->getMessage(),
                    ]);

                    return err($e->getMessage());
                }

                return ['file' => Advertising::sign($filename), 'fullpath' => Util::toMedia($filename)];
            }
        }

        return err('上传失败！');
    }

    /**
     * 公众号列表
     */
    public static function accounts(agentModelObj $agent): array
    {
        common::checkCurrentUserPrivileges($agent, 'F_xf');

        $page = max(1, Request::int('page'));
        $page_size = max(1, Request::int('pagesize', DEFAULT_PAGE_SIZE));

        $query = Account::query();
        $query->where(['agent_id' => $agent->getId()]);

        if (Request::has('keyword')) {
            $keyword = Request::trim('keyword');
            $query->whereOr([
                'name LIKE' => "%$keyword%",
                'title LIKE' => "%$keyword%",
                'descr LIKE' => "%$keyword%",
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
                $result['list'][] = mp::formatAccountInfo($agent, $account, true);
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
     * 禁用公众号
     */
    public static function ban(agentModelObj $agent): array
    {
        common::checkCurrentUserPrivileges($agent, 'F_xf');

        $uid = Request::trim('uid');
        if ($uid) {
            $account = Account::findOneFromUID($uid);
            if ($account) {
                if ($account->getAgentId() == $agent->getId()) {
                    if ($account->isThirdPartyPlatform() || $account->isAuth()) {
                        return ['msg' => '第三方平台或者授权接入的公众号无法禁用！'];
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

        return err('没有权限操作！');
    }

    /**
     * 删除公众号
     */
    public static function delete(agentModelObj $agent): array
    {
        common::checkCurrentUserPrivileges($agent, 'F_xf');

        return DBUtil::transactionDo(function () use ($agent) {
            $uid = Request::trim('uid');
            $account = Account::findOneFromUID($uid);
            if (empty($account)) {
                return err('找不到指定的公众号！');
            }

            if ($account->getAgentId() != $agent->getId()) {
                return err('没有权限操作这个公众号！');
            }

            if ($account->isFlashEgg()) {
                $goods = $account->getGoods();
                if ($goods) {
                    if (!Goods::safeDelete($goods)) {
                        return err('删除关联商品失败！');
                    }
                }
            }

            $account->destroy();
            if (Account::updateAccountData()) {
                return ['msg' => '删除成功！'];
            }

            return ['msg' => '删除失败！'];
        });
    }

    /**
     * 新建或者编辑公众号
     */
    public static function save(agentModelObj $agent): array
    {
        common::checkCurrentUserPrivileges($agent, 'F_xf');

        return DBUtil::transactionDo(function () use ($agent) {
            $data = [
                'agent_id' => $agent->getId(),
                'title' => Request::trim('title'),
                'descr' => Request::str('descr'),
                'group_name' => Request::str('groupname'),
                'order_no' => min(999, Request::int('orderno')),
                'clr' => Request::has('clr') ? Request::trim('clr') : 'gray',
                'scname' => Request::has('scname') ? Request::trim('scname') : Account::DAY,
                'count' => Request::int('count'),
                'total' => Request::int('total'),
            ];

            if (Request::has('uid')) {
                $account = Account::findOneFromUID(Request::str('uid'));
                if ($account) {
                    if ($account->getAgentId() != $agent->getId()) {
                        return err('公众号账号不能重复！');
                    }
                }
            }

            if (!Account::has($data['scname'])) {
                return err('领取频率只是每天/每周/每月！');
            }

            if (Request::has('qrcode')) {
                $type = Account::NORMAL;
                $url = Advertising::strip(Request::str('qrcode'));
                if ($url === false) {
                    return err('请上传正确的二维码文件！');
                }
            } elseif (Request::has('media')) {
                $type = Account::VIDEO;
                $url = Advertising::strip(Request::str('media'));
                if ($url === false) {
                    return err('请上传正确的视频文件！');
                }
            } elseif (Request::has('douyinUrl')) {
                $type = Account::DOUYIN;
            } elseif (Request::has('username')) {
                $type = Account::WXAPP;
            } elseif (Request::has('mediaType')) {
                $type = Account::FlashEgg;
            } else {
                return err('请指定正确的文件网址！');
            }

            $img_url = Advertising::strip(Request::str('img'));
            if ($img_url === false) {
                return err('请上传正确的头像文件！');
            }

            $data['qrcode'] = $url ?? '';
            $data['img'] = $img_url;

            $limits = [];
            if (Request::str('sex') == 'male') {
                $limits['male'] = 1;
                $limits['female'] = 0;
                $limits['unknown_sex'] = 0;
            } elseif (Request::str('sex') == 'female') {
                $limits['male'] = 0;
                $limits['female'] = 1;
                $limits['unknown_sex'] = 0;
            } else {
                $limits['male'] = 1;
                $limits['female'] = 1;
                $limits['unknown_sex'] = 1;
            }

            if (Request::str('os') == 'ios') {
                $limits['ios'] = 1;
                $limits['android'] = 0;
            } elseif (Request::str('os') == 'android') {
                $limits['ios'] = 0;
                $limits['android'] = 1;
            } else {
                $limits['ios'] = 1;
                $limits['android'] = 1;
            }

            if ($type == Account::DOUYIN) {
                $data['total'] = 1;
            }

            $data['order_limits'] = Request::int('orderlimits');

            if (isset($account)) {
                foreach ($data as $key => $val) {
                    $key_name = 'get'.ucfirst(toCamelCase($key));
                    if ($val != $account->$key_name()) {
                        $set_name = 'set'.ucfirst(toCamelCase($key));
                        $account->$set_name($val);
                    }
                }
            } else {
                if (empty($data['name'])) {
                    //不再要求用户填写唯一的name
                    do {
                        $name = Util::random(16, true);
                    } while (Account::findOneFromName($name));
                    $data['name'] = $name;
                } else {
                    $account = Account::findOneFromName($data['name']);
                    if ($account) {
                        if ($account->getAgentId() != $agent->getId()) {
                            return err('公众号账号不能重复！');
                        }
                    }
                }
                $data['uid'] = Account::makeUID($data['name']);
                $data['type'] = $type;
                $data['url'] = Account::createUrl($data['uid'], ['from' => 'account']);
                $account = Account::create($data);
            }

            if (empty($account)) {
                return err('操作失败！');
            }

            $account->setExtraData('update', [
                'time' => time(),
                'user' => $agent->profile(),
            ]);

            if ($account->save() && $account->set('limits', $limits) && Account::updateAccountData()) {
                if ($account->isAuth()) {
                    $account->updateSettings('config.open', [
                        'timing' => Request::int('OpenTiming'),
                        'msg' => Request::str('OpenMsg'),
                    ]);
                } elseif ($account->isVideo()) {
                    $account->set('config', [
                        'type' => Account::VIDEO,
                        'video' => [
                            'duration' => Request::int('duration', 1),
                        ],
                    ]);
                } elseif ($account->isDouyin()) {
                    $openid = $account->settings('config.openid', '');
                    $account->set('config', [
                        'type' => Account::DOUYIN,
                        'url' => Request::trim('douyinUrl'),
                        'openid' => $openid,
                    ]);
                } elseif ($account->isWxApp()) {
                    $account->set('config', [
                        'type' => Account::WXAPP,
                        'username' => Request::trim('username'),
                        'path' => Request::trim('path'),
                        'delay' => Request::int('delay', 1),
                    ]);
                } elseif ($account->isFlashEgg()) {
                    $res = FlashEgg::createOrUpdate($account, $GLOBALS['_GPC']);
                    if (is_error($res)) {
                        return $res;
                    }
                }

                return ['msg' => '保存成功！'];
            }

            return err('保存数据失败！');
        });
    }

    public static function groupAssign(agentModelObj $agent): array
    {
        common::checkCurrentUserPrivileges($agent, 'F_xf');

        $uid = Request::trim('uid');
        if ($uid) {
            $account = Account::findOneFromUID($uid);
            $agent_id = $agent->getId();

            if (empty($account) || $account->getAgentId() != $agent_id) {
                return err('没有权限操作这个公众号！');
            }

            $assign_data = [$account];

            if (Request::bool('all')) {
                $assign_data[] = $agent;
            } else {

                $groups = Request::is_array('groups') ? Request::array('groups') : [];

                foreach ($groups as $id) {
                    /** @var device_groupsModelObj $one */
                    $one = \zovye\domain\Group::get($id);
                    if ($one) {
                        $query_arr = ['group_id' => $one->getId()];
                        if ($one->getAgentId() != $agent->getId()) {
                            //平台的
                            $query_arr['agent_id'] = $agent->getId();
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

        return err('操作失败！');
    }

    public static function mpAuthUrl(agentModelObj $agent): array
    {
        common::checkCurrentUserPrivileges($agent, 'F_xf');

        $url = WxPlatform::getPreAuthUrl([
            'agent' => $agent->getId(),
        ]);

        if (empty($url)) {
            return err('暂时无法获取授权转跳网址！');
        }

        return ['url' => $url];
    }

    public static function getDouyinAuthQRCode(): array
    {
        $account_uid = Request::trim('uid');
        $url = Util::murl('douyin', [
            'op' => 'get_openid',
            'uid' => $account_uid,
        ]);

        $result = QRCodeUtil::createFile("douyin_$account_uid", DouYin::redirectToAuthorizeUrl($url, true));

        if (is_error($result)) {
            return err('创建二维码文件失败！');
        }

        return [
            'uid' => $account_uid,
            'qrcode_url' => Util::toMedia($result),
        ];
    }
}
