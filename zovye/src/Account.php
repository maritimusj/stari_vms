<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

//公众号状态
use zovye\base\modelObjFinder;
use zovye\model\account_queryModelObj;
use zovye\model\accountModelObj;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

/**
 * Class Account
 * @package zovye
 */
class Account extends State
{
    const NORMAL = 0;

    const BANNED = 1;

    //视频
    const VIDEO = 10;

    //抖音
    const DOUYIN = 20;

    //小程序
    const WXAPP = 30;

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

    //美葩
    const MEIPA = 104;

    //金粉吧
    const KINGFANS = 105;

    //史莱姆
    const SNTO = 106;

    //粉丝宝
    const YFB = 107;

    //企业微信拉新
    //refer: https://www.yuque.com/docs/share/cee4fad0-c591-4086-8fd1-79470ffb6b2b
    const WxWORK = 108;

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

    const MEIPA_NAME = '美葩';
    const MEIPA_HEAD_IMG = MODULE_URL . 'static/img/meipa_pic.png';

    const KINGFANS_NAME = '金粉吧';
    const KINGFANS_HEAD_IMG = MODULE_URL . 'static/img/kingfans_pic.png';

    const SNTO_NAME = '史莱姆';
    const SNTO_HEAD_IMG = MODULE_URL . 'static/img/snto_pic.png';

    const YFB_NAME = '粉丝宝';
    const YFB_HEAD_IMG = MODULE_URL . 'static/img/yfb_pic.png';

    const WxWORK_NAME = '企业微信拉新（阿旗）';
    const WxWORK_HEAD_IMG = MODULE_URL . 'static/img/aqi_pic.png';

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

    public static function findOneFromType($type): ?accountModelObj
    {
        return self::findOne(['type' => $type]);
    }

    public static function findOneFromName($name): ?accountModelObj
    {
        return self::findOne(['name' => $name]);
    }

    public static function findOneFromUID($uid): ?accountModelObj
    {
        return self::findOne(['uid' => $uid]);
    }

    public static function format(accountModelObj $entry): array
    {
        //特殊吸粉的img路径中包含addon/{APP_NAME}，不能使用Util::toMedia()转换，否则会出错
        $data = [
            'id' => $entry->getId(),
            'uid' => $entry->getUid(),
            'type' => $entry->getType(),
            'banned' => $entry->isBanned(),
            'name' => $entry->getName(),
            'title' => $entry->getTitle(),
            'descr' => html_entity_decode($entry->getDescription()),
            'url' => $entry->getUrl(),
            'clr' => $entry->getClr(),
            'img' => $entry->isThirdPartyPlatform() || $entry->isDouyin() ? $entry->getImg() : Util::toMedia($entry->getImg()),
            'scname' => $entry->getScname(),
            'total' => $entry->getTotal(),
            'count' => $entry->getCount(),
            'groupname' => $entry->getGroupName(),
            'orderno' => $entry->getOrderNo(),
        ];

        if ($entry->isVideo()) {
            $data['media'] = $entry->getQrcode();
            $data['duration'] = $entry->getDuration();
        } elseif ($entry->isDouyin()) {
            $data['url'] = DouYin::makeHomePageUrl($entry->getConfig('url'));
            $data['openid'] = $entry->getConfig('openid', '');
        } elseif ($entry->isWxApp()) {
            $data['username'] = $entry->getConfig('username', '');
            $data['path'] = $entry->getConfig('path', '');
            $data['delay'] = $entry->getConfig('delay', 1);
        } else {
            $data['qrcode'] = $entry->getQrcode();
        }

        if ($entry->isAuth()) {
            //授权公众号类型
            $data['service_type'] = $entry->getServiceType();
            //出货时机
            $data['open_timing'] = $entry->settings('config.open.timing');
            $appid = $entry->settings('authdata.authorization_info.authorizer_appid');
            if ($appid) {
                $data['appid'] = $appid;
            }
        }

        return $data;
    }

    public static function getAllEnabledThirdPartyPlatform(): array
    {
        $arr = [
            Account::JFB => App::isJfbEnabled(),
            Account::MOSCALE => App::isMoscaleEnabled(),
            Account::YUNFENBA => App::isYunfenbaEnabled(),
            Account::AQIINFO => App::isAQiinfoEnabled(),
            Account::ZJBAO => App::isZJBaoEnabled(),
            Account::MEIPA => App::isMeiPaEnabled(),
            Account::KINGFANS => App::isKingFansEnabled(),
            Account::SNTO => App::isSNTOEnabled(),
            Account::YFB => App::isSNTOEnabled(),
            Account::WxWORK => App::isWxWorkEnabled(),

        ];
        $result = [];
        foreach ($arr as $name => $enabled) {
            if ($enabled) {
                $result[] = $name;
            }
        }
        return $result;
    }

    /**
     * 获取用户可用的公众号列表
     * @param deviceModelObj $device
     * @param userModelObj $user
     * @param array $params
     * @return array
     * $params['max' => 1] 最多返回几个公众号
     */
    public static function match(deviceModelObj $device, userModelObj $user, array $params = []): array
    {
        $list = [];
        $join = function ($cond, $getter_fn) use ($device, $user, &$list) {
            $acc = Account::findOne($cond);
            if ($acc) {
                $index = sprintf("%03d", $acc->getOrderNo());
                if ($list[$index]) {
                    $index .= $acc->getId();
                }
                $list[$index] = function () use ($getter_fn, $acc, $device, $user) {
                    //检查用户是否允许
                    $res = Util::isAvailable($user, $acc, $device);
                    if (is_error($res)) {
                        return $res;
                    }

                    return $getter_fn($acc);
                };
            }
        };

        //处理分组
        $groups = [];

        $include = $params['type'] ?? [
                Account::NORMAL,
                Account::VIDEO,
                Account::AUTH,
                Account::WXAPP,
            ];

        $third_party_platform_includes = $params['type'] ?? [
                Account::JFB,
                Account::MOSCALE,
                Account::YUNFENBA,
                Account::AQIINFO,
                Account::ZJBAO,
                Account::MEIPA,
                Account::KINGFANS,
                Account::SNTO,
                Account::YFB,
                Account::WxWORK,
            ];

        $include = is_array($include) ? $include : [$include];
        $third_party_platform_includes = is_array($third_party_platform_includes) ? $third_party_platform_includes : [$third_party_platform_includes];

        $accounts = $device->getAccounts($include);
        foreach ($accounts as $uid => $entry) {
            $group_name = $entry['groupname'];
            if (empty($group_name)) {
                continue;
            }
            if (!isset($groups[$group_name])) {
                $groups[$group_name] = [
                    'uid' => $uid,
                    'orderno' => $entry['orderno'],
                ];
            } elseif ($entry['orderno'] > $groups[$group_name]['orderno']) {
                $last_uid = $groups[$group_name]['uid'];

                unset($accounts[$last_uid]);

                $groups[$group_name] = [
                    'uid' => $uid,
                    'orderno' => $entry['orderno'],
                ];
            } else {
                unset($accounts[$uid]);
            }
        }

        foreach ($accounts as $entry) {
            $join(['id' => $entry['id']], function ($acc) {
                return [$acc->format()];
            });
        }

        $exclude = is_array($params['exclude']) ? $params['exclude'] : [];
        $third_party_platform = [
            //准粉吧
            Account::JFB => [
                function () use ($third_party_platform_includes, $exclude) {
                    if ($third_party_platform_includes && !in_array(Account::JFB, $third_party_platform_includes)) {
                        return false;
                    }
                    return App::isJfbEnabled() && !in_array(JfbAccount::getUid(), $exclude);
                },
                function () use ($device, $user) {
                    return JfbAccount::fetch($device, $user);
                },
            ],
            //公锤
            Account::MOSCALE => [
                function () use ($third_party_platform_includes, $exclude) {
                    if ($third_party_platform_includes && !in_array(Account::MOSCALE, $third_party_platform_includes)) {
                        return false;
                    }
                    return App::isMoscaleEnabled() && !in_array(MoscaleAccount::getUid(), $exclude);
                },
                function () use ($device, $user) {
                    return MoscaleAccount::fetch($device, $user);
                },
            ],
            //云粉
            Account::YUNFENBA => [
                function () use ($third_party_platform_includes, $exclude) {
                    if ($third_party_platform_includes && !in_array(Account::YUNFENBA, $third_party_platform_includes)) {
                        return false;
                    }
                    return App::isYunfenbaEnabled() && !in_array(YunfenbaAccount::getUid(), $exclude);
                },
                function () use ($device, $user) {
                    return YunfenbaAccount::fetch($device, $user);
                },
            ],
            //阿旗
            Account::AQIINFO => [
                function () use ($third_party_platform_includes, $exclude) {
                    if ($third_party_platform_includes && !in_array(Account::AQIINFO, $third_party_platform_includes)) {
                        return false;
                    }
                    return App::isAQiinfoEnabled() && !in_array(AQIInfoAccount::getUid(), $exclude);
                },
                function () use ($device, $user) {
                    return AQIInfoAccount::fetch($device, $user);
                },
            ],

            //纸巾宝
            Account::ZJBAO => [
                function () use ($third_party_platform_includes, $exclude) {
                    if ($third_party_platform_includes && !in_array(Account::ZJBAO, $third_party_platform_includes)) {
                        return false;
                    }
                    return App::isZJBaoEnabled() && !in_array(ZhiJinBaoAccount::getUid(), $exclude);
                },
                function () use ($device, $user) {
                    return ZhiJinBaoAccount::fetch($device, $user);
                },
            ],

            //美葩
            Account::MEIPA => [
                function () use ($third_party_platform_includes, $exclude) {
                    if ($third_party_platform_includes && !in_array(Account::MEIPA, $third_party_platform_includes)) {
                        return false;
                    }
                    return App::isMeiPaEnabled() && !in_array(MeiPaAccount::getUid(), $exclude);
                },
                function () use ($device, $user) {
                    return MeiPaAccount::fetch($device, $user);
                },
            ],

            //金粉吧
            Account::KINGFANS => [
                function () use ($third_party_platform_includes, $exclude) {
                    if ($third_party_platform_includes && !in_array(Account::KINGFANS, $third_party_platform_includes)) {
                        return false;
                    }
                    return App::isKingFansEnabled() && !in_array(KingFansAccount::getUid(), $exclude);
                },
                function () use ($device, $user) {
                    return KingFansAccount::fetch($device, $user);
                },
            ],

            //史莱姆
            Account::SNTO => [
                function () use ($third_party_platform_includes, $exclude) {
                    if ($third_party_platform_includes && !in_array(Account::SNTO, $third_party_platform_includes)) {
                        return false;
                    }
                    return App::isSNTOEnabled() && !in_array(SNTOAccount::getUid(), $exclude);
                },
                function () use ($device, $user) {
                    return SNTOAccount::fetch($device, $user);
                },
            ],

            //粉丝宝
            Account::YFB => [
                function () use ($third_party_platform_includes, $exclude) {
                    if ($third_party_platform_includes && !in_array(Account::YFB, $third_party_platform_includes)) {
                        return false;
                    }
                    return App::isYFBEnabled() && !in_array(YfbAccount::getUid(), $exclude);
                },
                function () use ($device, $user) {
                    return YfbAccount::fetch($device, $user);
                },
            ],

            //企业微信拉新（阿旗）
            Account::WxWORK => [
                function () use ($third_party_platform_includes, $exclude) {
                    if ($third_party_platform_includes && !in_array(Account::WxWORK, $third_party_platform_includes)) {
                        return false;
                    }
                    return App::isWxWorkEnabled() && !in_array(WxWorkAccount::getUid(), $exclude);
                },
                function () use ($device, $user) {
                    return WxWorkAccount::fetch($device, $user);
                },
            ]
        ];

        foreach ($third_party_platform as $uid => $entry) {
            if ($entry[0]()) {
                $join(['type' => $uid], $entry[1]);
            }
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
        $cond = $cond['id'] || $cond['uid'] ? $cond : We7::uniacid($cond);
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
        $assign_data = $account->settings('assigned', []);
        $assign_data['agents'] = [];
        $assign_data = isEmptyArray($assign_data) ? [] : $assign_data;
        if ($account->updateSettings('assigned', $assign_data)) {
            return self::updateAccountData();
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
     * @param array $objs
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
                foreach ($dst as &$x) {
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

    public static function createThirdPartyPlatform(int $aid, string $name, string $img, string $url): ?accountModelObj
    {
        $uid = self::makeThirdPartyPlatformUID($aid, $name);
        $account = self::findOne(['uid' => $uid]);
        if ($account) {
            if ($account->getType() != $aid) {
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
            'type' => $aid,
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

    public static function makeThirdPartyPlatformUID($aid, $name): string
    {
        return self::makeUID("$aid:$name");
    }

    public static function createJFBAccount(): ?accountModelObj
    {
        $url = Util::murl('jfb');
        return self::createThirdPartyPlatform(Account::JFB, Account::JFB_NAME, Account::JFB_HEAD_IMG, $url);
    }

    public static function createMoscaleAccount(): ?accountModelObj
    {
        $url = Util::murl('moscale');
        return self::createThirdPartyPlatform(Account::MOSCALE, Account::MOSCALE_NAME, Account::MOSCALE_HEAD_IMG, $url);
    }

    public static function createYunFenBaAccount(): ?accountModelObj
    {
        $url = Util::murl('yunfenba');
        return self::createThirdPartyPlatform(Account::YUNFENBA, Account::YUNFENBA_NAME, Account::YUNFENBA_HEAD_IMG, $url);
    }

    public static function createAQiinfoAccount(): ?accountModelObj
    {
        $url = Util::murl('aqiinfo');
        return self::createThirdPartyPlatform(Account::AQIINFO, Account::AQIINFO_NAME, Account::AQIINFO_HEAD_IMG, $url);
    }

    public static function createZJBaoAccount(): ?accountModelObj
    {
        $url = Util::murl('zjbao');
        return self::createThirdPartyPlatform(Account::ZJBAO, Account::ZJBAO_NAME, Account::ZJBAO_HEAD_IMG, $url);
    }

    public static function createMeiPaAccount(): ?accountModelObj
    {
        $url = Util::murl('meipa');
        return self::createThirdPartyPlatform(Account::MEIPA, Account::MEIPA_NAME, Account::MEIPA_HEAD_IMG, $url);
    }

    public static function createKingFansAccount(): ?accountModelObj
    {
        $url = Util::murl('kingfans');
        return self::createThirdPartyPlatform(Account::KINGFANS, Account::KINGFANS_NAME, Account::KINGFANS_HEAD_IMG, $url);
    }

    public static function createSNTOAccount(): ?accountModelObj
    {
        $url = Util::murl('snto');
        return self::createThirdPartyPlatform(Account::SNTO, Account::SNTO_NAME, Account::SNTO_HEAD_IMG, $url);
    }

    public static function createYFBAccount(): ?accountModelObj
    {
        $url = Util::murl('yfb');
        return self::createThirdPartyPlatform(Account::YFB, Account::YFB_NAME, Account::YFB_HEAD_IMG, $url);
    }

    public static function createWxWorkAccount(): ?accountModelObj
    {
        $url = Util::murl('wxwork');
        return self::createThirdPartyPlatform(Account::WxWORK, Account::WxWORK_NAME, Account::WxWORK_HEAD_IMG, $url);
    }

    public static function getAuthorizerQrcodeById(int $id, string $sceneStr, $temporary = true): array
    {
        $account = self::get($id);
        if (empty($account)) {
            return err('找不到这个公众号！');
        }
        return self::getAuthorizerQrcode($account, $sceneStr, $temporary);
    }

    public static function getAuthorizerAccessToken(accountModelObj $account)
    {
        $auth_data = $account->get('authdata', []);
        if (empty($auth_data)) {
            return err('没有认证数据！');
        }

        $createtime = getArray($auth_data, 'createtime', 0);
        $expired = getArray($auth_data, 'authorization_info.expires_in', 0);

        if (time() - $createtime > $expired) {

            $app_id = getArray($auth_data, 'authorization_info.authorizer_appid', '');
            $refreshToken = getArray($auth_data, 'authorization_info.authorizer_refresh_token', '');

            $result = WxPlatform::refreshAuthorizerAccessToken($app_id, $refreshToken);
            if (is_error($result)) {
                return $result;
            }

            if ($result) {
                if ($result['authorizer_access_token']) {
                    setArray($auth_data, 'authorization_info.authorizer_access_token', $result['authorizer_access_token']);
                }

                if ($result['authorizer_refresh_token']) {
                    setArray($auth_data, 'authorization_info.authorizer_refresh_token', $result['authorizer_refresh_token']);
                }

                setArray($auth_data, 'authorization_info.expires_in', $result['expires_in']);
                setArray($auth_data, 'createtime', time());

                $account->set('authdata', $auth_data);
            }
        }

        return strval(getArray($auth_data, 'authorization_info.authorizer_access_token', ''));
    }

    public static function getAuthorizerQrcode(accountModelObj $account, string $sceneStr, $temporary = true): array
    {
        $res = self::getAuthorizerAccessToken($account);
        if (is_error($res)) {
            return $res;
        }

        return WxPlatform::getAuthQRCode($res, $sceneStr, $temporary ? WxPlatform::TEMP_QRCODE : WxPlatform::PERM_QRCODE);
    }

    public static function createOrUpdateFromWxPlatform(int $agent_id, string $app_id, array $auth_result = [])
    {
        $profile = WxPlatform::getAuthProfile($app_id);
        if (is_error($profile)) {
            return $profile;
        }

        $auth_data = WxPlatform::getAuthData($auth_result['AuthorizationCode']);
        if (is_error($auth_data)) {
            return $auth_data;
        }

        $uid = Account::makeUID($app_id);
        $name = getArray($profile, 'authorizer_info.user_name');

        $account = Account::findOneFromUID($uid);

        if (empty($account)) {
            $qrcode_url = getArray($profile, 'authorizer_info.qrcode_url', '');
            if ($qrcode_url) {
                $qrcode_url = Util::toMedia(Util::downloadQRCode($qrcode_url));
            }
            $data = [
                'agent_id' => $agent_id,
                'type' => Account::AUTH,
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
            $account->setType(Account::AUTH);

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
        $account = Account::findOneFromUID($uid);
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
                if ($account['type'] == Account::WXAPP && empty($account['username'])) {
                    unset($accounts[$index]);
                    continue;
                }
                if ($account['type'] == Account::AUTH) {
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
                    }
                }
                if (isset($account['qrcode'])) {
                    if ($account['qrcode']) {
                        $account['qrcode'] = Util::toMedia($account['qrcode']);
                    } else {
                        $account['qrcode'] = './resource/images/nopic.jpg';
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
        //防止json_encode成对象造成前端代码出错
        return array_values($accounts);
    }

    /**
     * 把授权公众号二维码替换成带参数的二维码
     * @param array $account_data
     * @param mixed $params
     * @param bool $temporary
     * @return array|string
     */
    public static function updateAuthAccountQRCode(array &$account_data, $params, bool $temporary = true)
    {
        if ($account_data['type'] == Account::AUTH) {
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
    public static function getUserNext(deviceModelObj $device, userModelObj $user): array
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

    /**
     * @param accountModelObj $account
     * @param userModelObj $user
     * @param deviceModelObj $device
     * @param $request
     * @param $result
     * @param null $createtime
     * @return account_queryModelObj|null
     */
    public static function createQueryLog(accountModelObj $account, userModelObj $user, deviceModelObj $device, $request, $result, $createtime = null): ?account_queryModelObj
    {
        $data = [
            'request_id' => REQUEST_ID,
            'account_id' => $account->getId(),
            'user_id' => $user->getId(),
            'device_id' => $device->getId(),
            'request' => json_encode($request),
            'result' => json_encode($result),
            'createtime' => $createtime ?? time(),
        ];

        return m('account_query')->create($data);
    }

    public static function logQuery(accountModelObj $account, $condition = []): modelObjFinder
    {
        return m('account_query')
            ->where(['account_id' => $account->getId()])
            ->where($condition);
    }

    public static function getLastQueryLog(accountModelObj $account, userModelObj $user, deviceModelObj $device)
    {
        $query = self::logQuery($account, [
            'device_id' => $device->getId(),
            'user_id' => $user->getId()
        ]);
        $query->orderBy('id DESC');
        return $query->findOne();
    }

    public static function createThirdPartyPlatformOrder(accountModelObj $acc, userModelObj $user, deviceModelObj $device, $order_uid = '', $cb_params = [])
    {
        if (App::isAccountLogEnabled()) {
            $log = Account::getLastQueryLog($acc, $user, $device);
            if ($log) {
                $log->setExtraData('cb', [
                    'time' => time(),
                    'order_uid' => $order_uid,
                    'data' => $cb_params,
                ]);
                $log->save();
            }
        }

        Job::createThirdPartyPlatformOrder([
            'device' => $device->getId(),
            'user' => $user->getId(),
            'account' => $acc->getId(),
            'orderUID' => $order_uid,
        ]);
    }
}
