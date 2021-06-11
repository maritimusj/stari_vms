<?php

/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

//公众号状态
use zovye\base\modelObjFinder;
use zovye\model\accountModelObj;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

/**
 * Class Account
 * @package zovye
 */
class Account extends State
{
    const BANNED = 0;

    const NORMAL = 1;

    //视频
    const VIDEO = 10;

    //授权接入公众号
    const AUTH = 98;

    //准粉吧公众号
    const JFB = 99;

    //公锤公众号
    const MOSCALE = 100;

    //云粉吧公众号
    const YUNFENBA = 101;

    //阿旗数据平台
    const AQIINFO = 102;

    //纸巾宝
    const ZJBAO = 103;


    const SUBSCRIPTION_ACCOUNT = 0;
    const SERVICE_ACCOUNT = 2;

    const JFB_NAME = '准粉吧';
    const JFB_HEAD_IMG = MODULE_URL . 'static/img/jfb_pic.png';

    const MOSCALE_NAME = '公锤平台';
    const MOSCALE_HEAD_IMG = MODULE_URL . 'static/img/moscale_pic.jpg';

    const YUNFENBA_NAME = '云粉吧';
    const YUNFENBA_HEAD_IMG = MODULE_URL . 'static/img/yunfenba_pic.png';

    const AQIINFO_NAME = '阿旗平台';
    const AQIINFO_HEAD_IMG = MODULE_URL . 'static/img/aqi_pic.png';

    const ZJBAO_NAME = '纸巾宝';
    const ZJBAO_HEAD_IMG = MODULE_URL . 'static/img/zjbao_pic.png';

    protected static $title = [
        self::BANNED => '已禁用',
        self::NORMAL => '正常',
    ];

    /**
     * @param array $data
     * @return accountModelObj|null
     */
    public static function create(array $data = []): ?accountModelObj
    {
        if (empty($data['uniacid'])) {
            $data['uniacid'] = We7::uniacid();
        }

        return m('account')->create($data);
    }

    /**
     * @param int $id
     * @return ?accountModelObj
     */
    public static function get(int $id): ?accountModelObj
    {
        /** @var accountModelObj[] $cache */
        static $cache = [];
        if ($id) {
            if (isset($cache[$id])) {
                return $cache[$id];
            }
            $res = self::query()->findOne(['id' => $id]);
            if ($res) {
                $cache[$res->getId()] = $res;
                return $res;
            }
        }

        return null;
    }

    /**
     * @param array $condition
     * @return modelObjFinder
     */
    public static function query(array $condition = []): modelObjFinder
    {
        return m('account')->where(We7::uniacid([]))->where($condition);
    }

    public static function format(accountModelObj $entry): array
    {
        //特殊吸粉的img路径中包含addon/{APP_NAME}，不能使用Util::toMedia()转换，否则会出错
        $data = [
            'id' => intval($entry->getId()),
            'uid' => strval($entry->getUid()),
            'state' => intval($entry->getState()),
            'name' => strval($entry->getName()),
            'title' => strval($entry->getTitle()),
            'descr' => html_entity_decode($entry->getDescription()),
            'url' => strval($entry->getUrl()),
            'clr' => strval($entry->getClr()),
            'img' => $entry->isSpecial() ? $entry->getImg() : Util::toMedia($entry->getImg()),
            'scname' => strval($entry->getScname()),
            'total' => intval($entry->getTotal()),
            'count' => intval($entry->getCount()),
            'groupname' => strval($entry->getGroupName()),
            'orderno' => intval($entry->getOrderNo()),
        ];

        if ($entry->isVideo()) {
            $data['media'] = $entry->getQrcode();
            $data['duration'] = $entry->getDuration();
        } else {
            $data['qrcode'] = $entry->getQrcode();
        }

        if ($entry->isAuth()) {
            //授权公众号类型
            $data['service_type'] = $entry->getServiceType();
            //出货时机
            $data['open_timing'] = $entry->settings('config.open.timing');
        }

        return $data;
    }

    /**
     * 获取用户可用的公众号列表
     * @param deviceModelObj $device
     * @param userModelObj $user
     * @param array $params
     * @return array
     * $params['max' => 1] 最多返回几个公众号
     */
    public static function match(deviceModelObj $device, userModelObj  $user, array $params = []): array {
        $list = [];
        $join = function($cond, $getter_fn) use($device, $user, &$list) {
            $acc = Account::findOne($cond);
            if ($acc) {
                $index = sprintf("%03d", $acc->getOrderNo());
                if ($list[$index]) {
                    $index .= $acc->getId();
                }
                $list[$index] = function () use($getter_fn, $acc, $device, $user) {
                    //检查用户是否允许
                    $res = Util::isAvailable($user, $acc, $device);
                    if (is_error($res)) {
                        return $res;
                    }

                    return $getter_fn($acc);
                };
            }
        };

        $groups = [];
        
        $accounts = $device->getAccounts();
        foreach ($accounts as $uid => $entry) {
            $group_name = $entry['groupname'];
            if ($group_name && array_key_exists($group_name, $groups)) {
                unset($accounts[$uid]);
                continue;
            }

            if ($group_name) {
                $groups[$group_name] = 'exists';
            }

            $join(['id' => $entry['id']], function ($acc) {
                return [$acc->format()];
            });
        }

        $exclude = is_array($params['exclude']) ? $params['exclude'] : [];

        //准粉吧
        if (App::isJfbEnabled() && !in_array(JfbAccount::getUid(), $exclude)) {
            $join(['state' => Account::JFB], function () use ($device, $user) {
                return JfbAccount::fetch($device, $user);
            });
        }

        //公锤
        if (App::isMoscaleEnabled() && !in_array(MoscaleAccount::getUid(), $exclude)) {
            $join(['state' => Account::MOSCALE], function () use ($device, $user) {
                return MoscaleAccount::fetch($device, $user);
            });
        }

        //云粉
        if (App::isYunfenbaEnabled() && !in_array(YunfenbaAccount::getUid(), $exclude)) {
            $join(['state' => Account::YUNFENBA], function () use ($device, $user) {
                return YunfenbaAccount::fetch($device, $user);
            });
        }

        //阿旗
        if (App::isAQiinfoEnabled() && !in_array(AQiinfoAccount::getUid(), $exclude)) {
            $join(['state' => Account::AQIINFO], function () use ($device, $user) {
                return AQiinfoAccount::fetch($device, $user);
            });
        }

        //纸巾宝
        if (App::isZJBaoEnabled() && !in_array(ZhiJinBaoAccount::getUid(), $exclude)) {
            $join(['state' => Account::ZJBAO], function () use ($device, $user) {
                return ZhiJinBaoAccount::fetch($device, $user);
            });
        }

        if (empty($list)) {
            return [];
        }

        uksort($list, function ($a, $b) {
            $res = strcmp($a, $b);
            if ($res < 0) {
                return 1;
            } elseif ($res > 0) {
                return -1;
            }
            return 0;
        });

        $result = [];

        //如果所有公众号的排序值一样，则打乱排序
        $shuffle_accounts = function ($result) {
            if (count($result) > 1) {
                $first = current($result);
                $last = end($result);

                if ($first['orderno'] == $last['orderno']) {
                    $keys = array_keys($result);
                    shuffle($keys);

                    $arr = [];
                    foreach ($keys as $key) {
                        $arr[$key] = $result[$key];
                    }
                    return $arr;
                }
            }
            return $result;
        };

        $max = intval($params['max']);
        foreach ($list as $getter) {
            $res = $getter();
            if (empty($res) || is_error($res)) {
                continue;
            }
            $result = array_merge($result, $res);
            if ($max > 0 && count($result) >= $max) {
                $result = array_slice($result, 0, $max, true);
                return $shuffle_accounts($result);
            }
        }

        return $result;
    }

    /**
     * @param $cond
     * @return accountModelObj|null
     */
    public static function findOne($cond): ?accountModelObj
    {
        $cond = boolval($cond['id']) || boolval($cond['uid']) ? $cond : We7::uniacid($cond);
        $query = self::query($cond);
        return $query->findOne();
    }

    /**
     * @param string $name
     * @return string
     */
    public static function makeUID(string $name): string
    {
        return sha1(We7::uniacid() . $name);
    }

    /**
     * 清除所有代理商关联
     * @param accountModelObj $account
     * @return bool
     */
    public static function removeAllAgents(accountModelObj $account): bool
    {
        if ($account) {
            $assign_data = $account->settings('assigned', []);
            $assign_data['agents'] = [];
            $assign_data = isEmptyArray($assign_data) ? [] : $assign_data;
            if ($account->updateSettings('assigned', $assign_data)) {
                return self::updateAccountData();
            }
        }

        return false;
    }

    /**
     * 更新公众号数据的最后更新时间
     * @return bool
     */
    public static function updateAccountData(): bool
    {
        return updateSettings('accounts.lastupdate', '' . microtime(true));
    }

    /**
     * 判断是否与$dst已经有关联
     * @param array $assign_data 分配数据
     * @param mixed $dst 要检查的对象
     * @return bool
     */
    public static function isRelated(array $assign_data, $dst): bool
    {
        if (empty($assign_data) || empty($dst) || !is_array($assign_data)) {
            return false;
        }

        if (empty($assign_data['agents'])) {
            $assign_data['agents'] = [];
        }

        if (empty($assign_data['devices'])) {
            $assign_data['devices'] = [];
        }

        if (empty($assign_data['tags'])) {
            $assign_data['tags'] = [];
        }

        $objs = is_array($dst) ? $dst : [$dst];

        foreach ($objs as $obj) {
            if (is_a($obj, User::objClassname())) {
                if (in_array($obj->getId(), $assign_data['agents'])) {
                    continue;
                } else {
                    $devices = Device::query(['agent_id' => $obj->getAgentId(), 'id' => $assign_data['devices']])->count();
                    if (empty($devices)) {
                        return false;
                    }
                }
            } elseif (is_a($obj, Device::objClassname())) {
                if (!in_array($obj->getId(), $assign_data['devices'])) {
                    return false;
                }
            } elseif (is_a($obj, m('tags')->objClassname())) {
                if (!in_array($obj->getId(), $assign_data['tags'])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * 绑定或者取消绑定多个对象，（必须有一个公众号和一个或多个其它对象：设备，代理商，标签）
     * @param array<mixed> $objs
     * @param array $params
     * @return bool
     */
    public static function bind(array $objs, array $params = []): bool
    {
        if (!is_array($objs)) {
            return false;
        }

        $accounts = [
            'classname' => m('account')->objClassname(),
            'list' => [],
        ];

        $dst = [
            'agents' => [
                'classname' => User::objClassname(),
                'list' => [],
            ],
            'devices' => [
                'classname' => Device::objClassname(),
                'list' => [],
            ],
            'tags' => [
                'classname' => m('tags')->objClassname(),
                'list' => [],
            ],
            'groups' => [
                'classname' => m('device_groups')->objClassname(),
                'list' => [],
            ],
        ];

        foreach ($objs as $obj) {
            if ($obj instanceof $accounts['classname']) {
                $accounts['list'][] = $obj;
            } else {
                foreach ($dst as $index => &$x) {
                    if ($obj instanceof $x['classname']) {
                        $x['list'][] = intval($obj->getId());
                        break;
                    }
                }
            }
        }

        if (empty($accounts)) {
            return false;
        }

        /** @var accountModelObj $account */
        foreach ($accounts['list'] as $account) {

            $assign_data = $account->get('assigned', []);

            if (empty($assign_data['agents'])) {
                $assign_data['agents'] = [];
            }
            if (empty($assign_data['devices'])) {
                $assign_data['devices'] = [];
            }
            if (empty($assign_data['tags'])) {
                $assign_data['tags'] = [];
            }
            if (empty($assign_data['groups'])) {
                $assign_data['groups'] = [];
            }

            if (!empty($params['revert'])) {
                //取消绑定
                $assign_data['agents'] = array_diff($assign_data['agents'], $dst['agents']['list']);
                $assign_data['devices'] = array_diff($assign_data['devices'], $dst['devices']['list']);
                $assign_data['tags'] = array_diff($assign_data['tags'], $dst['tags']['list']);
                $assign_data['groups'] = array_diff($assign_data['groups'], $dst['groups']['list']);
            } else {
                //绑定操作
                if (!empty($params['overwrite'])) {
                    //覆盖模式
                    $assign_data = ['agents' => [], 'devices' => [], 'tags' => [], 'groups' => []];
                }

                $assign_data['agents'] = array_unique(array_merge($assign_data['agents'], $dst['agents']['list']));
                $assign_data['devices'] = array_unique(array_merge($assign_data['devices'], $dst['devices']['list']));
                $assign_data['tags'] = array_unique(array_merge($assign_data['tags'], $dst['tags']['list']));
                $assign_data['groups'] = array_unique(array_merge($assign_data['groups'], $dst['groups']['list']));
            }

            $account->set('assigned', $assign_data);
        }

        return self::updateAccountData();
    }

    public static function createUrl(string $uid, array $params = []): string
    {
        return Util::shortMobileUrl('entry', array_merge(['account' => $uid], $params));
    }

    public static function createSpecialAccount(int $aid, string $name, string $img, string $url): ?accountModelObj
    {
        $uid = self::makeSpecialAccountUID($aid, $name);
        $account = self::findOne(['uid' => $uid]);
        if ($account) {
            if ($account->getState() != $aid) {
                return null;
            }

            $account->setName($name);
            $account->setTitle($name);
            $account->setImg($img);
            $account->setUrl($url);

            $account->save();

            return $account;
        }

        $result = self::create([
            'uid' => $uid,
            'state' => $aid,
            'scname' => Schema::DAY,
            'name' => $name,
            'url' => $url,
            'img' => $img,
            'clr' => Util::randColor(),
        ]);

        if ($result) {
            $result->settings('config.type', $aid);
        }

        return $result;
    }

    public static function makeSpecialAccountUID($aid, $name): string
    {
        return self::makeUID("{$aid}:{$name}");
    }

    public static function createJFBAccount(): ?accountModelObj
    {
        $url = Util::murl('jfb');
        return self::createSpecialAccount(Account::JFB, Account::JFB_NAME, Account::JFB_HEAD_IMG, $url);
    }

    public static function createMoscaleAccount(): ?accountModelObj
    {
        $url = Util::murl('moscale');
        return self::createSpecialAccount(Account::MOSCALE, Account::MOSCALE_NAME, Account::MOSCALE_HEAD_IMG, $url);
    }

    public static function createYunFenBaAccount(): ?accountModelObj
    {
        $url = Util::murl('yunfenba');
        return self::createSpecialAccount(Account::YUNFENBA, Account::YUNFENBA_NAME, Account::YUNFENBA_HEAD_IMG, $url);
    }

    public static function createAQiinfoAccount(): ?accountModelObj
    {
        $url = Util::murl('aqiinfo');
        return self::createSpecialAccount(Account::AQIINFO, Account::AQIINFO_NAME, Account::AQIINFO_HEAD_IMG, $url);
    }

    public static function createZJBaoAccount(): ?accountModelObj
    {
        $url = Util::murl('zjbao');
        return self::createSpecialAccount(Account::ZJBAO, Account::ZJBAO_NAME, Account::ZJBAO_HEAD_IMG, $url);
    }

    public static function getAuthorizerQrcodeById(int $id, string $sceneStr, $temporary = true): array
    {
        $account = self::get($id);
        if (empty($account)) {
            return err('找不到这个公众号！');
        }
        return self::getAuthorizerQrcode($account, $sceneStr, $temporary);
    }

    public static function getAuthorizerQrcode(accountModelObj $account, string $sceneStr, $temporary = true): array
    {
        $auth_data = $account->get('authdata', []);
        if (empty($auth_data)) {
            return err('没有认证数据！');
        }

        $createtime = getArray($auth_data, 'createtime', 0);
        $expired = getArray($auth_data, 'authorization_info.expires_in', 0);

        if (time() - $createtime > $expired - 600) {

            $app_id = getArray($auth_data, 'authorization_info.authorizer_appid', '');
            $refreshToken = getArray($auth_data, 'authorization_info.authorizer_refresh_token', '');

            $result = WxPlatform::refreshAuthorizerAccessToken($app_id, $refreshToken);
            if (is_error($result)) {
                return $result;
            }

            setArray($auth_data, 'authorization_info.authorizer_access_token', $result['authorizer_access_token']);
            setArray($auth_data, 'authorization_info.authorizer_refresh_token', $result['authorizer_refresh_token']);
            setArray($auth_data, 'authorization_info.expires_in', $result['expires_in']);
            setArray($auth_data, 'createtime', time());

            $account->set('authdata', $auth_data);
        }

        $access_token = getArray($auth_data, 'authorization_info.authorizer_access_token', '');

        return WxPlatform::getAuthQRCode($access_token, $sceneStr, $temporary ? WxPlatform::TEMP_QRCODE : WxPlatform::PERM_QRCODE);
    }

    public static function createOrUpdateFromWxPlatform(int $agent_id, string $app_id, array $auth_result = [])
    {
        $profile = WxPlatform::getAuthProfile($app_id);
        //Util::logToFile('wxplatform', $profile);
        if (is_error($profile)) {
            return $profile;
        }

        $auth_data = WxPlatform::getAuthData($auth_result['AuthorizationCode']);
        //Util::logToFile('wxplatform', $auth_data);
        if (is_error($auth_data)) {
            return $auth_data;
        }

        if ($auth_data['errcode'] != 0) {
            return error(intval($auth_data['errcode']), strval($auth_data['errmsg']));
        }

        $uid = Account::makeUID($app_id);
        $name = getArray($profile, 'authorizer_info.user_name');

        $account = Account::findOne(['uid' => $uid]);

        if (empty($account)) {
            $qrcode_url = getArray($profile, 'authorizer_info.qrcode_url', '');
            if ($qrcode_url) {
                $qrcode_url = Util::toMedia(Util::downloadQRCode($qrcode_url));
            }
            $data = [
                'agent_id' => $agent_id,
                'state' => Account::AUTH,
                'uid' => $uid,
                'name' => $name,
                'title' => getArray($profile, 'authorizer_info.nick_name', '未知'),
                'descr' => getArray($profile, 'authorizer_info.nick_name', ''),
                'img' => getArray($profile, 'authorizer_info.head_img', ''),
                'qrcode' => $qrcode_url,
                'clr' => Util::randColor(),
                'scname' => Schema::DAY,
                'count' => 1,
                'total' => 1,
                'balance_deduct_num' => 0,
                'group_name' => '',
                'url' => Account::createUrl($uid, ['from' => 'account']),
            ];

            $account = Account::create($data);
            if (empty($account)) {
                return err('创建公众号失败！');
            }

            $account->set('config', [
                'open' => [
                    'timing' => 0,
                    'msg' => '谢谢关注，请点击{url}领取商品！',
                ],
            ]);
        } else {
            if (empty($account->getTitle())) {
                $account->setTitle(getArray($profile, 'authorizer_info.nick_name', '未知'));
            }

            if (empty($account->getImg())) {
                $account->setImg(getArray($profile, 'authorizer_info.head_img', ''));
            }

            $account->setName($name);
            $account->setState(Account::AUTH);

            $account->save();
        }

        $auth_data['createtime'] = time();

        $account->set('authdata', $auth_data);
        $account->set('profile', $profile);

        return $account;
    }

    public static function disableWxPlatformAccount(string $app_id): array
    {
        $uid = Account::makeUID($app_id);
        $account = Account::findOne(['uid' => $uid]);
        if ($account) {
            $account->setState(Account::BANNED);
            if ($account->save()) {
                return ['message' => '成功'];
            } else {
                return ['message' => '保存失败'];
            }
        }

        return ['message' => '找不到公众号！'];
    }


    public static function getAvailableList(deviceModelObj $device, userModelObj $user, array $params = []): array
    {
        //获取本地可用公众号列表
        $accounts = Account::match($device, $user, array_merge($params, ['admin', 'max' => settings('misc.maxAccounts', 0)]));
        if (!empty($accounts)) {
            foreach ($accounts as $index => &$account) {
                if (App::useAccountQRCode()) {
                    $obj = Account::get($account['id']);
                    if (empty($obj) || $obj->useAccountQRCode()) {
                        unset($accounts[$index]);
                        continue;
                    }
                }
                if (isset($account['service_type']) && $account['service_type'] == Account::SERVICE_ACCOUNT) {
                    //如果是授权服务号，需要使用场景二维码替换原二维码
                    self::updateAuthAccountQRCode($account, [App::uid(6), $user->getId(), $device->getId()]);
                    if (isset($account['qrcode'])) {
                        if ($account['qrcode']) {
                            $account['qrcode'] = Util::toMedia($account['qrcode']);
                        } else {
                            $account['qrcode'] = './resource/images/nopic.jpg';
                        }
                    }
                }
                if (isset($account['media'])) {
                    if ($account['media']) {
                        $account['media'] = Util::toMedia($account['media']);
                    } else {
                        $account['media'] = './resource/images/nopic.jpg';
                    }
                }
            }
        }
        return $accounts;
    }

    /**
     * 把授权公众号二维码替换成带参数的二维码
     * @param array $account_data
     * @param mixed $params
     * @param bool $temporary
     * @return array|string
     */
    public static function updateAuthAccountQRCode(array &$account_data, $params, $temporary = true)
    {
        if ($account_data['state'] == Account::AUTH) {
            $str = is_array($params) ? implode(':', $params) : strval($params);
            $result = Account::getAuthorizerQrcodeById($account_data['id'], $str, $temporary);
            if (is_error($result)) {
                Util::logToFile('wxplatform', [
                    'fn' => 'updateAuthAccountQRCode',
                    'error' => $result,
                ]);
                return $result;
            } else {
                $account_data['qrcode'] = $result['url'];
                return strval($result['url']);
            }
        }

        return err('不是授权接入的公众号！');
    }

    /**
     * 根据上次 uid 获取下个公众号
     * @param deviceModelObj $device
     * @param userModelObj $user
     * @return array
     */
    public static function getUserNext(deviceModelObj $device, userModelObj  $user): array
    {
        $account = self::getNext($device, $user->settings('accounts.last.uid', ''));
        if ($account) {
            $uid = $account['uid'];

            self::updateAuthAccountQRCode($account, [App::uid(6), $user->getId(), $device->getId()]);
            if ($account) {
                return $account;
            }

            $user->updateSettings('accounts.last', [
                'uid' => $uid,
                'time' => time(),
            ]);
        }
        return [];
    }

    /**
     * 根据上次 uid 获取下个公众号
     * @param deviceModelObj $device
     * @param $last_uid
     * @return array
     */
    public static function getNext(deviceModelObj $device, $last_uid): array
    {
        $accounts = $device->getAccounts();
        if (empty($accounts)) {
            return [];
        }

        //按order_no从大到小,对公众号排序
        usort($accounts, function ($a, $b) {
            return intval($b['order_no']) - intval($a['order_no']);
        });

        $first = (array)$accounts[0];

        if (empty($last_uid)) {
            return $first;
        }

        foreach ($accounts as $index => $account) {
            if (App::useAccountQRCode()) {
                $obj = Account::get($account['id']);
                if (empty($obj) || $obj->useAccountQRCode()) {
                    continue;
                }
            }
            if ($account['uid'] == $last_uid) {
                $account = (array)$accounts[$index + 1];
                if ($account) {
                    return $account;
                }
                return $first;
            }
        }

        return $first;
    }
}
