<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

//公众号状态

use zovye\account\AQIInfoAccount;
use zovye\account\CloudFIAccount;
use zovye\account\JfbAccount;
use zovye\account\KingFansAccount;
use zovye\account\MeiPaAccount;
use zovye\account\MengMoAccount;
use zovye\account\MoscaleAccount;
use zovye\account\SNTOAccount;
use zovye\account\WeiSureAccount;
use zovye\account\WxWorkAccount;
use zovye\account\YfbAccount;
use zovye\account\YiDaoAccount;
use zovye\account\YouFenAccount;
use zovye\account\YunfenbaAccount;
use zovye\account\ZhiJinBaoAccount;
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
    const COMMISSION = 'commission';

    const BALANCE = 'balance';

    const NORMAL = 0;

    const BANNED = 1;

    const PSEUDO = 2;      //虚拟

    //视频
    const VIDEO = 10;

    //抖音
    const DOUYIN = 20;

    //小程序
    const WXAPP = 30;

    //问卷
    const QUESTIONNAIRE = 40;

    //授权接入公众号
    const AUTH = 98;

    //准粉吧公众号
    //https://www.showdoc.com.cn/1660977313040831/7833225636342042
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

    // 企业微信拉新
    // refer: https://www.yuque.com/docs/share/cee4fad0-c591-4086-8fd1-79470ffb6b2b
    const WxWORK = 108;

    // 友粉
    // https://www.showdoc.com.cn/p/96e1339954947631fdeb196c36436f66
    // https://www.showdoc.com.cn/p/a2585efa11f4240fdb9b906f40e7313c
    // https://www.showdoc.com.cn/p/f5e459aaab1b8fdd4b1ee30ae7a2cebd
    const YOUFEN = 109;

    const TASK = 110;

    const MENGMO = 111;

    const YIDAO = 112;

    const WEISURE = 113;

    const CloudFI = 114;

    const FlashEgg = 115;

    const SUBSCRIPTION_ACCOUNT = 0;
    const SERVICE_ACCOUNT = 2;

    const JFB_NAME = '准粉吧';
    const JFB_HEAD_IMG = MODULE_URL.'static/img/jfb_pic.png';

    const MOSCALE_NAME = '公锤平台';
    const MOSCALE_HEAD_IMG = MODULE_URL.'static/img/moscale_pic.jpg';

    const YUNFENBA_NAME = '云粉吧';
    const YUNFENBA_HEAD_IMG = MODULE_URL.'static/img/yunfenba_pic.png';

    const AQIINFO_NAME = '阿旗平台';
    const AQIINFO_HEAD_IMG = MODULE_URL.'static/img/aqi_pic.png';

    const ZJBAO_NAME = '纸巾宝';
    const ZJBAO_HEAD_IMG = MODULE_URL.'static/img/zjbao_pic.png';

    const MEIPA_NAME = '美葩';
    const MEIPA_HEAD_IMG = MODULE_URL.'static/img/meipa_pic.png';

    const KINGFANS_NAME = '金粉吧';
    const KINGFANS_HEAD_IMG = MODULE_URL.'static/img/kingfans_pic.png';

    const SNTO_NAME = '史莱姆';
    const SNTO_HEAD_IMG = MODULE_URL.'static/img/snto_pic.png';

    const YFB_NAME = '粉丝宝';
    const YFB_HEAD_IMG = MODULE_URL.'static/img/yfb_pic.png';

    const WxWORK_NAME = '企业微信拉新（阿旗）';
    const WxWORK_HEAD_IMG = MODULE_URL.'static/img/aqi_pic.png';

    const YOUFEN_NAME = '友粉';
    const YOUFEN_HEAD_IMG = MODULE_URL.'static/img/youfen.png';

    const TASK_NAME = '自定义任务';
    const TASK_HEAD_IMG = MODULE_URL.'static/img/task.svg';

    const MENGMO_NAME = '涨啊';
    const MENGMO_HEAD_IMG = MODULE_URL.'static/img/mengmo.jpg';

    const YIDAO_NAME = '壹道';
    const YIDAO_HEAD_IMG = MODULE_URL.'static/img/yidao.png';

    const WEISURE_NAME = '微保';
    const WEISURE_HEAD_IMG = MODULE_URL.'static/img/weisure.png';

    const CloudFI_NAME = '中科在线';
    const CloudFI_HEAD_IMG = MODULE_URL.'static/img/cloudfi.png';

    const PSEUDO_NAME = '虚拟公众号';
    const PSEUDO_HEAD_IMG = MODULE_URL.'static/img/pseudo.svg';

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

    public static function getPseudoAccount(): ?accountModelObj
    {
        $uid = sha1(App::uid());
        $account = self::findOneFromUID($uid);
        if ($account) {
            return $account;
        }

        $data = [
            'agent_id' => 0,
            'type' => Account::PSEUDO,
            'state' => Account::NORMAL,
            'uid' => $uid,
            'name' => Util::random(16),
            'title' => self::PSEUDO_NAME,
            'descr' => '系统内部使用的虚拟公众号',
            'img' => self::PSEUDO_HEAD_IMG,
            'qrcode' => '',
            'clr' => Util::randColor(),
            'scname' => Schema::DAY,
            'count' => 0,
            'total' => 0,
            'group_name' => '',
            'url' => Account::createUrl($uid, ['from' => 'account']),
        ];

        return self::create($data);
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

    public static function format(accountModelObj $account): array
    {
        //特殊吸粉的img路径中包含addon/{APP_NAME}，不能使用Util::toMedia()转换，否则会出错
        $data = [
            'id' => $account->getId(),
            'uid' => $account->getUid(),
            'type' => $account->getType(),
            'banned' => $account->isBanned(),
            'name' => $account->getName(),
            'title' => $account->getTitle(),
            'descr' => html_entity_decode($account->getDescription()),
            'url' => $account->getUrl(),
            'clr' => $account->getClr(),
            'img' => $account->isThirdPartyPlatform() || $account->isDouyin() ? $account->getImg() : Util::toMedia(
                $account->getImg()
            ),
            'scname' => $account->getScname(),
            'total' => $account->getTotal(),
            'count' => $account->getCount(),
            'groupname' => $account->getGroupName(),
            'orderno' => $account->getOrderNo(),
            'thirdparty_platform' => $account->isThirdPartyPlatform() ? 1 : 0,
        ];

        if ($account->isVideo()) {
            $data['media'] = $account->getQrcode();
            $data['duration'] = $account->getDuration();
        } elseif ($account->isDouyin()) {
            $data['url'] = DouYin::makeHomePageUrl($account->getConfig('url'));
            $data['openid'] = $account->getConfig('openid', '');
        } elseif ($account->isWxApp()) {
            $data['username'] = $account->getConfig('username', '');
            $data['path'] = $account->getConfig('path', '');
            $data['delay'] = $account->getConfig('delay', 1);
        } elseif ($account->isFlashEgg()) {
            if ($account->getMediaType() == 'video') {
                $data['video'] = $account->getMedia();
            } else {
                $data['images'] = $account->getMedia();
            }
            $data['duration'] = $account->getDuration();
            $data['area'] = $account->getArea();
            $data['goods'] = $account->getGoodsData();
        } else {
            $data['qrcode'] = $account->getQrcode();
        }

        if ($account->isAuth()) {
            //授权公众号类型
            $data['service_type'] = $account->getServiceType();
            //出货时机
            $data['open_timing'] = $account->settings('config.open.timing');
            $appid = $account->settings('authdata.authorization_info.authorizer_appid');
            if ($appid) {
                $data['appid'] = $appid;
            }
        }

        if (App::isBalanceEnabled() && $account->getBonusType() == Account::BALANCE) {
            $data['balance'] = $account->getBalancePrice();
        } else {
            $data['commission'] = $account->getCommissionPrice();
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
            Account::YOUFEN => App::isYouFenEnabled(),
            Account::MENGMO => App::isMengMoEnabled(),
            Account::YIDAO => App::isYiDaoEnabled(),
            Account::WEISURE => App::isWeiSureEnabled(),
            Account::CloudFI => App::isCloudFIEnabled(),
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

        $include_balance = empty($params['include']) || in_array(Account::BALANCE, $params['include']);
        $include_commission = empty($params['include']) || in_array(Account::COMMISSION, $params['include']);

        $join = function ($cond, $getter_fn) use ($device, $user, &$list, $include_balance, $include_commission) {
            $acc = Account::findOne($cond);
            if (empty($acc) || $acc->isBanned()) {
                return false;
            }
            if (!$include_balance && $acc->getBonusType() == Account::BALANCE) {
                return false;
            }
            if (!$include_commission && $acc->getBonusType() == Account::COMMISSION) {
                return false;
            }
            $index = sprintf("%03d", $acc->getOrderNo());
            if ($list[$index]) {
                $index .= $acc->getId();
            }
            $list[$index] = function () use ($getter_fn, $acc, $device, $user) {
                if ($acc->getBonusType() == Account::BALANCE) {
                    $res = Util::checkBalanceAvailable($user, $acc);
                } else {
                    //检查用户是否允许
                    $res = Util::checkAvailable($user, $acc, $device);
                }
                if (is_error($res)) {
                    return $res;
                }

                return $getter_fn($acc);
            };

            return true;
        };

        //处理分组
        $groups = [];

        $include = $params['type'] ?? [
            Account::NORMAL,
            Account::VIDEO,
            Account::AUTH,
            Account::WXAPP,
            Account::QUESTIONNAIRE,
        ];

        $third_party_platform_includes = $params['s_type'] ?? [
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
            Account::YOUFEN,
            Account::MENGMO,
            Account::YIDAO,
            Account::WEISURE,
            Account::CloudFI,
        ];

        $include = is_array($include) ? $include : [$include];
        $third_party_platform_includes = is_array(
            $third_party_platform_includes
        ) ? $third_party_platform_includes : [$third_party_platform_includes];

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
            $join(['id' => $entry['id']], function (accountModelObj $acc) {
                return [$acc->format()];
            });
        }

        $exclude = is_array($params['exclude']) ? $params['exclude'] : [];
        $third_party_platform = [
            //准粉吧
            Account::JFB => [
                function () use ($third_party_platform_includes, $exclude) {
                    return App::isJfbEnabled()
                        && in_array(Account::JFB, $third_party_platform_includes)
                        && !in_array(JfbAccount::getUid(), $exclude);
                },
                function () use ($device, $user) {
                    return JfbAccount::fetch($device, $user);
                },
            ],
            //公锤
            Account::MOSCALE => [
                function () use ($third_party_platform_includes, $exclude) {
                    return App::isMoscaleEnabled()
                        && in_array(Account::MOSCALE, $third_party_platform_includes)
                        && !in_array(MoscaleAccount::getUid(), $exclude);
                },
                function () use ($device, $user) {
                    return MoscaleAccount::fetch($device, $user);
                },
            ],
            //云粉
            Account::YUNFENBA => [
                function () use ($third_party_platform_includes, $exclude) {
                    return App::isYunfenbaEnabled()
                        && in_array(Account::YUNFENBA, $third_party_platform_includes)
                        && !in_array(YunfenbaAccount::getUid(), $exclude);
                },
                function () use ($device, $user) {
                    return YunfenbaAccount::fetch($device, $user);
                },
            ],
            //阿旗
            Account::AQIINFO => [
                function () use ($third_party_platform_includes, $exclude) {
                    return App::isAQiinfoEnabled()
                        && in_array(Account::AQIINFO, $third_party_platform_includes)
                        && !in_array(AQIInfoAccount::getUid(), $exclude);
                },
                function () use ($device, $user) {
                    return AQIInfoAccount::fetch($device, $user);
                },
            ],

            //纸巾宝
            Account::ZJBAO => [
                function () use ($third_party_platform_includes, $exclude) {
                    return App::isZJBaoEnabled()
                        && in_array(Account::ZJBAO, $third_party_platform_includes)
                        && !in_array(ZhiJinBaoAccount::getUid(), $exclude);
                },
                function () use ($device, $user) {
                    return ZhiJinBaoAccount::fetch($device, $user);
                },
            ],

            //美葩
            Account::MEIPA => [
                function () use ($third_party_platform_includes, $exclude) {
                    return App::isMeiPaEnabled()
                        && in_array(Account::MEIPA, $third_party_platform_includes)
                        && !in_array(MeiPaAccount::getUid(), $exclude);
                },
                function () use ($device, $user) {
                    return MeiPaAccount::fetch($device, $user);
                },
            ],

            //金粉吧
            Account::KINGFANS => [
                function () use ($third_party_platform_includes, $exclude) {
                    return App::isKingFansEnabled()
                        && in_array(Account::KINGFANS, $third_party_platform_includes)
                        && !in_array(KingFansAccount::getUid(), $exclude);
                },
                function () use ($device, $user) {
                    return KingFansAccount::fetch($device, $user);
                },
            ],

            //史莱姆
            Account::SNTO => [
                function () use ($third_party_platform_includes, $exclude) {
                    return App::isSNTOEnabled()
                        && in_array(Account::SNTO, $third_party_platform_includes)
                        && !in_array(SNTOAccount::getUid(), $exclude);
                },
                function () use ($device, $user) {
                    return SNTOAccount::fetch($device, $user);
                },
            ],

            //粉丝宝
            Account::YFB => [
                function () use ($third_party_platform_includes, $exclude) {
                    return App::isYFBEnabled()
                        && in_array(Account::YFB, $third_party_platform_includes)
                        && !in_array(YfbAccount::getUid(), $exclude);
                },
                function () use ($device, $user) {
                    return YfbAccount::fetch($device, $user);
                },
            ],

            //企业微信拉新（阿旗）
            Account::WxWORK => [
                function () use ($third_party_platform_includes, $exclude) {
                    return App::isWxWorkEnabled()
                        && in_array(Account::WxWORK, $third_party_platform_includes)
                        && !in_array(WxWorkAccount::getUid(), $exclude);
                },
                function () use ($device, $user) {
                    return WxWorkAccount::fetch($device, $user);
                },
            ],

            //友粉
            Account::YOUFEN => [
                function () use ($third_party_platform_includes, $exclude) {
                    return App::isYouFenEnabled()
                        && in_array(Account::YOUFEN, $third_party_platform_includes)
                        && !in_array(YouFenAccount::getUid(), $exclude);
                },
                function () use ($device, $user) {
                    return YouFenAccount::fetch($device, $user);
                },
            ],

            //涨啊
            Account::MENGMO => [
                function () use ($third_party_platform_includes, $exclude) {
                    return App::isMengMoEnabled()
                        && in_array(Account::MENGMO, $third_party_platform_includes)
                        && !in_array(MengMoAccount::getUid(), $exclude);
                },
                function () use ($device, $user) {
                    return MengMoAccount::fetch($device, $user);
                },
            ],

            //壹道
            Account::YIDAO => [
                function () use ($third_party_platform_includes, $exclude) {
                    return App::isYiDaoEnabled()
                        && in_array(Account::YIDAO, $third_party_platform_includes)
                        && !in_array(YiDaoAccount::getUid(), $exclude);
                },
                function () use ($device, $user) {
                    return YiDaoAccount::fetch($device, $user);
                },
            ],

            //微保
            Account::WEISURE => [
                function () use ($third_party_platform_includes, $exclude) {
                    return App::isWeiSureEnabled()
                        && in_array(Account::WEISURE, $third_party_platform_includes)
                        && !in_array(WeiSureAccount::getUid(), $exclude);
                },
                function () use ($device, $user) {
                    return WeiSureAccount::fetch($device, $user);
                },
            ],

            //中科
            Account::CloudFI => [
                function () use ($third_party_platform_includes, $exclude) {
                    return App::isCloudFIEnabled()
                        && in_array(Account::CloudFI, $third_party_platform_includes)
                        && !in_array(CloudFIAccount::getUid(), $exclude);
                },
                function () use ($device, $user) {
                    return CloudFIAccount::fetch($device, $user);
                },
            ],
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
        $shuffle_accounts = function ($result) use ($params) {
            if ($params['shuffle'] !== false) {
                if (count($result) > 1) {
                    $first = current($result);
                    $last = end($result);

                    if ($first['orderno'] == $last['orderno']) {
                        $keys = array_keys($result);
                        shuffle($keys);

                        $arr = [];
                        foreach ($keys as $key) {
                            $arr[] = $result[$key];
                        }

                        return $arr;
                    }
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
            foreach ($res as $account) {
                if (self::isReady($account)) {
                    $result[] = $account;
                }
            }
            if ($max > 0 && count($result) >= $max) {
                $result = array_slice($result, 0, $max, true);

                return $shuffle_accounts($result);
            }
        }

        return $shuffle_accounts($result);
    }

    /**
     * @param $cond
     * @return accountModelObj|null
     */
    public static function findOne($cond): ?accountModelObj
    {
        if (count($cond) == 1 && $cond['id']) {
            return self::get($cond['id']);
        }
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
        return sha1(We7::uniacid().$name);
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
        return updateSettings('accounts.last_update', ''.microtime(true));
    }

    /**
     * 判断是否与$dst已经有关联
     * @param array $assign_data 分配数据
     * @param mixed $dst 要检查的对象
     * @return bool
     */
    public static function isRelated(array $assign_data, $dst): bool
    {
        if (empty($assign_data) || empty($dst)) {
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
                    $devices = Device::query(['agent_id' => $obj->getAgentId(), 'id' => $assign_data['devices']]
                    )->count();
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

    public static function createThirdPartyPlatform(int $type, string $name, string $img, string $url): ?accountModelObj
    {
        $uid = self::makeThirdPartyPlatformUID($type, $name);
        $account = self::findOne(['uid' => $uid]);
        if ($account) {
            $account->setType($type);
            $account->setName($name);
            $account->setTitle($name);
            $account->setImg($img);
            $account->setUrl($url);
            $account->save();

            return $account;
        }

        $result = self::create([
            'uid' => $uid,
            'type' => $type,
            'scname' => Schema::DAY,
            'name' => $name,
            'title' => $name,
            'url' => $url,
            'img' => $img,
            'clr' => Util::randColor(),
        ]);

        if ($result) {
            $result->settings('config.type', $type);
        }

        return $result;
    }

    public static function makeThirdPartyPlatformUID(int $type, string $name): string
    {
        return self::makeUID("$type:$name");
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

        return self::createThirdPartyPlatform(
            Account::YUNFENBA,
            Account::YUNFENBA_NAME,
            Account::YUNFENBA_HEAD_IMG,
            $url
        );
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

        return self::createThirdPartyPlatform(
            Account::KINGFANS,
            Account::KINGFANS_NAME,
            Account::KINGFANS_HEAD_IMG,
            $url
        );
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

    public static function createYouFenAccount(): ?accountModelObj
    {
        $url = Util::murl('youfen');

        return self::createThirdPartyPlatform(Account::YOUFEN, Account::YOUFEN_NAME, Account::YOUFEN_HEAD_IMG, $url);
    }

    public static function createMengMoAccount(): ?accountModelObj
    {
        $url = Util::murl('mengmo');

        return self::createThirdPartyPlatform(Account::MENGMO, Account::MENGMO_NAME, Account::MENGMO_HEAD_IMG, $url);
    }

    public static function createYiDaoAccount(): ?accountModelObj
    {
        $url = Util::murl('yidao');

        return self::createThirdPartyPlatform(Account::YIDAO, Account::YIDAO_NAME, Account::YIDAO_HEAD_IMG, $url);
    }

    public static function createWeiSureAccount(): ?accountModelObj
    {
        $url = Util::murl('weisure');

        return self::createThirdPartyPlatform(Account::WEISURE, Account::WEISURE_NAME, Account::WEISURE_HEAD_IMG, $url);
    }

    public static function createCloudFIAccount(): ?accountModelObj
    {
        $url = Util::murl('cloudfi');

        return self::createThirdPartyPlatform(Account::CloudFI, Account::CloudFI_NAME, Account::CloudFI_HEAD_IMG, $url);
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

        $create_time = getArray($auth_data, 'createtime', 0);
        $expired = getArray($auth_data, 'authorization_info.expires_in', 0);

        if (time() - $create_time > $expired) {

            $app_id = getArray($auth_data, 'authorization_info.authorizer_appid', '');
            $refreshToken = getArray($auth_data, 'authorization_info.authorizer_refresh_token', '');

            $result = WxPlatform::refreshAuthorizerAccessToken($app_id, $refreshToken);
            if (is_error($result)) {
                return $result;
            }

            if ($result) {
                if ($result['authorizer_access_token']) {
                    setArray(
                        $auth_data,
                        'authorization_info.authorizer_access_token',
                        $result['authorizer_access_token']
                    );
                }

                if ($result['authorizer_refresh_token']) {
                    setArray(
                        $auth_data,
                        'authorization_info.authorizer_refresh_token',
                        $result['authorizer_refresh_token']
                    );
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

        return WxPlatform::getAuthQRCode(
            $res,
            $sceneStr,
            $temporary ? WxPlatform::TEMP_QRCODE : WxPlatform::PERM_QRCODE
        );
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
                $qrcode_url = Util::toMedia(QRCodeUtil::downloadQRCode($qrcode_url));
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

    protected static function isReady(array $account): bool
    {
        switch ($account['type']) {
            case Account::TASK:
            case Account::FlashEgg:
                //不需要检查
                return true;
            case Account::VIDEO:
                if (empty($account['media'])) {
                    return false;
                }
                break;
            case Account::DOUYIN:
                if (empty($account['url'])) {
                    return false;
                }
                break;
            case Account::WXAPP:
                if (empty($account['username'])) {
                    return false;
                }
                break;
            case Account::AUTH:
                if (App::isUseAccountQRCode()) {
                    $obj = Account::get($account['id']);
                    if (empty($obj) || $obj->useAccountQRCode()) {
                        return false;
                    }
                }
                break;
            case Account::QUESTIONNAIRE:
                $obj = Account::get($account['id']);
                if (empty($obj)) {
                    return false;
                }
                $questions = $obj->getQuestions();
                if (isEmptyArray($questions)) {
                    return false;
                }
                break;
            default:
                if ($account['redirect_url']) {
                    return true;
                }
                if (empty($account['qrcode'])) {
                    return false;
                }
        }

        return true;
    }

    public static function getAvailableList(deviceModelObj $device, userModelObj $user, array $params = []): array
    {
        if (Session::isSnapshot()) {
            return [];
        }

        //获取本地可用公众号列表
        $accounts = Account::match($device, $user, array_merge(['max' => settings('misc.maxAccounts', 0)], $params));
        if (!empty($accounts)) {
            foreach ($accounts as &$account) {
                if ($account['type'] == Account::AUTH) {
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
                Log::error('wxplatform', [
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
            if (App::isUseAccountQRCode()) {
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
    public static function createQueryLog(
        accountModelObj $account,
        userModelObj $user,
        deviceModelObj $device,
        $request,
        $result,
        $createtime = null
    ): ?account_queryModelObj {
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

    public static function logQuery(accountModelObj $account = null, $condition = []): modelObjFinder
    {
        $query = m('account_query')->where($condition);
        if ($account) {
            $query->where(['account_id' => $account->getId()]);
        }

        return $query;
    }

    /**
     * @param accountModelObj $account
     * @param userModelObj $user
     * @param deviceModelObj|null $device
     * @return account_queryModelObj|null
     */
    public static function getLastQueryLog(
        accountModelObj $account,
        userModelObj $user,
        deviceModelObj $device = null
    ): ?account_queryModelObj {
        $condition = [
            'user_id' => $user->getId(),
        ];

        if ($device) {
            $condition['device_id'] = $device->getId();
        }

        $query = self::logQuery($account, $condition);
        $query->orderBy('id DESC');

        return $query->findOne();
    }

    public static function updateQueryLogCBData(
        accountModelObj $acc,
        userModelObj $user,
        deviceModelObj $device = null,
        $data = []
    ) {
        if (App::isAccountLogEnabled()) {
            $log = Account::getLastQueryLog($acc, $user, $device);
            if ($log) {
                $last = $log->getExtraData('cb');
                if ($last) {
                    $arr = $log->getExtraData('last_cb', []);
                    $arr[] = $last;
                    $log->setExtraData('last_cb', $arr);
                }
                $log->setExtraData('cb', array_merge(['time' => time()], $data));
                $log->save();
            }
        }
    }

    public static function createThirdPartyPlatformBalance(
        accountModelObj $acc,
        userModelObj $user,
        $serial = '',
        $params = []
    ) {
        $result = Balance::give($user, $acc, $serial);

        Account::updateQueryLogCBData($acc, $user, null, [
            'serial' => $serial,
            'data' => $params,
            'result' => $result,
        ]);

        return $result;
    }

    public static function createThirdPartyPlatformOrder(
        accountModelObj $acc,
        userModelObj $user,
        deviceModelObj $device,
        $order_uid = '',
        $cb_params = []
    ) {
        self::updateQueryLogCBData($acc, $user, $device, [
            'order_uid' => $order_uid,
            'data' => $cb_params,
        ]);

        Job::createThirdPartyPlatformOrder([
            'device' => $device->getId(),
            'user' => $user->getId(),
            'account' => $acc->getId(),
            'orderUID' => $order_uid,
            'goods' => $user->getLastActiveData('goods', 0),
        ]);
    }

    public static function getTypeTitle($type): string
    {
        static $titles = [
            self::NORMAL => '公众号',
            self::VIDEO => '视频',
            self::DOUYIN => '抖音',
            self::WXAPP => '小程序',
            self::QUESTIONNAIRE => '问卷',
            self::AUTH => '公众号',
            self::JFB => '准粉吧',
            self::MOSCALE => '公锤',
            self::YUNFENBA => '云粉吧',
            self::AQIINFO => '阿旗',
            self::ZJBAO => '纸巾宝',
            self::MEIPA => '美葩',
            self::KINGFANS => '金粉吧',
            self::SNTO => '史莱姆',
            self::YFB => '粉丝宝',
            self::WxWORK => '阿旗（企业微信）',
            self::YOUFEN => '友粉',
            self::MENGMO => '涨啊',
            self::YIDAO => '壹道',
            self::WEISURE => '微保',
            self::CloudFI => '中科在线',
        ];

        return $titles[$type] ?? '未知';
    }

    public static function replaceCode($desc, $placeholder, $code, bool $must_replace = true)
    {
        if (strpos($desc, '{'.$placeholder.'}') !== false) {
            $desc = PlaceHolder::replace($desc, [
                $placeholder => $code ? "<span data-key=\"$code\">$code</span>" : '',
            ]);
        } else {
            if ($code && $must_replace) {
                $desc = "回复<span data-key=\"$code\">$code</span>免费领取！";
            }
        }

        return $desc;
    }
}
