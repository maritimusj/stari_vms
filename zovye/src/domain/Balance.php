<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\domain;

use Error;
use Exception;
use zovye\App;
use zovye\base\ModelObjFinder;
use zovye\Config;
use zovye\CtrlServ;
use zovye\Log;
use zovye\model\accountModelObj;
use zovye\model\balanceModelObj;
use zovye\model\userModelObj;
use zovye\util\DBUtil;
use zovye\util\Helper;
use zovye\util\Util;
use zovye\We7;
use function zovye\err;
use function zovye\is_error;
use function zovye\m;

class Balance
{
    const CACHE_EXPIRATION = 60 * 60;

    const ADJUST = 0;
    const ACCOUNT_BONUS = 1; // 关注公众号
    const SIGN_IN_BONUS = 2; // 每日签到
    const VIDEO_BONUS = 3; // 观看视频
    const WX_APP_BONUS = 4; // 关注微信小程序
    const GOODS_EXCHANGE = 5; // 商品兑换
    const REFUND = 6; // 退款
    const REWARD_ADV = 7; // 激励广告
    const API_UPDATE = 8; // 第三方通过api接口修改
    const PROMOTE_BONUS = 9; // 任务奖励
    const TASK_BONUS = 10; // 任务奖励
    const DELIVERY_ORDER = 11; // 商城订单
    const USER_NEW = 12; // 新用户奖励
    const USER_REF = 13; // 推荐新用户奖励

    private $user;

    public function __construct(userModelObj $user)
    {
        $this->user = $user;
    }

    public function __toString(): string
    {
        return strval($this->total());
    }

    public static function query($condition = []): ModelObjFinder
    {
        if (is_array($condition) && isset($condition['id'])) {
            return m('balance')->where($condition);
        }

        return m('balance')->where(We7::uniacid([]))->where($condition);
    }

    public static function findOne($condition = []): ?balanceModelObj
    {
        return self::query($condition)->findOne();
    }

    public static function get($id): ?balanceModelObj
    {
        return self::findOne(['id' => $id]);
    }

    /**
     * 余额变动操作
     * @param int $val
     * @param int $src
     * @param array $extra
     * @param bool $notify
     * @return balanceModelObj
     */
    public function change(int $val, int $src, array $extra = [], bool $notify = true): ?balanceModelObj
    {
        if ($this->user && $val != 0) {
            $result = m('balance')->create(
                We7::uniacid(
                    [
                        'openid' => $this->user->getOpenid(),
                        'src' => $src,
                        'x_val' => $val,
                        'extra' => json_encode($extra),
                    ]
                )
            );
            if ($result && $notify) {
                $this->onChange($result);

                return $result;
            }
        }

        return null;
    }

    public function onChange(balanceModelObj $item)
    {
        $notify_url = Config::balance('app.notify_url');
        if ($notify_url) {
            $profile = $this->user->profile(false);
            $profile['balance'] = $this->total();
            $profile['change'] = $item->getXVal();

            $data = [
                'data' => $profile,
                'serial' => sha1(App::uid(6).$item->getId()),
                'sign' => hash_hmac('sha1', http_build_query($profile), Config::balance('app.key')),
            ];

            $json_str = json_encode($data, JSON_UNESCAPED_UNICODE);

            Log::debug('balance_notify', [
                'notify' => $notify_url,
                'data' => $data,
            ]);

            $result = CtrlServ::httpQueuedCallback(LEVEL_NORMAL, $notify_url, $json_str);
            if (is_error($result)) {
                Log::error('balance_notify', [
                    'notify_url' => $notify_url,
                    'data' => $data,
                    'result' => $result,
                ]);
            }
        }
    }


    /**
     * 获取当前余额
     * @return int
     */
    public function total(): int
    {
        if (App::getUserBalanceByMobileEnabled()) {

            $mobile = $this->user->getMobile();
            if (!empty($mobile)) {

                $total = 0;
                $allUser = User::getAllUserByMobile($mobile);

                if (!empty($allUser)) {
                    foreach ($allUser as $user) {
                        $total += self::getTotal($user);
                    }

                    return $total;
                }
            }
        }

        return self::getTotal($this->user);
    }

    protected static function getTotal(userModelObj $user)
    {
        $query = Balance::query(['openid' => $user->getOpenid()]);

        $last_id = 0;
        $last_total = 0;
        $last_time = 0;

        $cache = $user->get('balance:cache', []);
        if ($cache && isset($cache['id']) && isset($cache['total'])) {

            $last_id = intval($cache['id']);
            $last_total = intval($cache['total']);
            $last_time = intval($cache['time']);

            $query->where(['id >' => $last_id]);
        }

        if (time() - $last_time > self::CACHE_EXPIRATION) {

            list($total, $id) = $query->get(['sum(x_val)', 'max(id)']);

            if (isset($id) && $id > $last_id) {
                $total += $last_total;
                $locker = $user->acquireLocker(User::BALANCE_LOCKER);
                if ($locker) {
                    $user->set('balance:cache', [
                        'id' => $id,
                        'total' => $total,
                        'time' => time(),
                    ]);
                    $locker->unlock();
                }
            } else {
                $total = $last_total;
            }

        } else {
            $total = $query->get('sum(x_val)') + $last_total;
        }

        return $total;
    }

    /**
     * 返回用户积分变动记录
     */
    public function log(): ?ModelObjFinder
    {
        if ($this->user) {
            $openid = $this->user->getOpenid();

            return Balance::query(['openid' => $openid]);
        }

        return null;
    }

    public static function format(balanceModelObj $entry): array
    {
        $data = [
            'id' => $entry->getId(),
            'xval' => $entry->getXVal(),
            'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
        ];

        if ($entry->getXVal() > 0) {
            $data['xval'] = '+'.$data['xval'];
        }

        if ($entry->getSrc() == Balance::ADJUST) {
            $name = $entry->getExtraData('admin');
            $line = "<dt>管理员</dt><dd class=\"admin\">$name</dd>";
            $memo = $entry->getExtraData('memo', '');
            if ($memo) {
                $line .= "<dt>说明</dt><dd class=\"memo\">$memo</dd>";
            }
            $data['memo'] = <<<TEXT
<dl class="log dl-horizontal">
<dt>事件</dt><dd class="event">管理员调整</dd>
$line
</dl>
TEXT;

        } elseif ($entry->getSrc() == Balance::ACCOUNT_BONUS) {
            $account_data = $entry->getExtraData('account');
            $account_typehint = [
                Account::NORMAL => ['公众号', '成功关注公众号'],
                Account::VIDEO => ['视频', '完成观看视频任务'],
                Account::DOUYIN => ['抖音', '完成关注抖音号任务'],
                Account::WXAPP => ['小程序', '完成进入小程序任务'],
                Account::AUTH => ['公众号', '关注公众号'],
                Account::JFB => ['准粉吧', '完成关准粉吧任务'],
                Account::MOSCALE => ['公锤', '完成公锤任务'],
                Account::YUNFENBA => ['云粉吧', '完成云粉吧任务'],
                Account::AQIINFO => ['阿旗', '完成阿旗数据平台任务'],
                Account::ZJBAO => ['纸巾宝', '完成纸巾宝任务'],
                Account::MEIPA => ['美葩', '完成美葩任务'],
                Account::KINGFANS => ['金粉吧', '完成金粉吧任务'],
                Account::SNTO => ['史莱姆', '完成史莱姆任务'],
                Account::YFB => ['粉丝宝', '完成粉丝宝任务'],
                Account::WxWORK => ['阿旗(企业微信)', '完成阿旗(企业微信)任务'],
                Account::YOUFEN => ['友粉', '完成友粉任务'],
                Account::MENGMO => ['涨啊', '完成涨啊任务'],
                Account::YIDAO => ['壹道', '完成壹道任务'],
                Account::WEISURE => ['微保', '完成微保任务'],
                Account::CloudFI => ['中科在线', '完成中科在线任务'],
                Account::QUESTIONNAIRE => ['问卷', '完成问卷调查'],
            ][$account_data['type']] ?? ["未知({$account_data['id']})", '未知事件（任务已删除）'];

            $account_info = "<dt>$account_typehint[0]</dt><dd class=\"user\"><img src=\"{$account_data['img']}\" alt=''/>{$account_data['title']}</dd>";
            $data['memo'] = <<<TEXT
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">$account_typehint[1]</dd>
$account_info
</dl>
TEXT;
        } elseif ($entry->getSrc() == Balance::SIGN_IN_BONUS) {
            $data['memo'] = <<<TEXT
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">每日签到</dd>
</dl>
TEXT;
        } elseif ($entry->getSrc() == Balance::GOODS_EXCHANGE) {
            $line = '';
            $goods = $entry->getExtraData('goods', []);
            $num = $entry->getExtraData('num', 1);
            if ($goods) {
                $img = Util::toMedia($goods['img'], true);
                $line .= "<dt>商品</dt><dd class=\"goods\"><img src=\"$img\"  alt=\"\">{$goods['name']} x$num</dd>";
            }
            $device = $entry->getDevice();
            if ($device) {
                $line .= "<dt>设备</dt><dd class=\"goods\">{$device->getName()}</dd>";
            }
            $data['memo'] = <<<TEXT
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">兑换商品</dd>
$line
</dl>
TEXT;
        } elseif ($entry->getSrc() == Balance::REFUND) {
            $reason = $entry->getExtraData('reason', '');
            $reason_data = "<dt>失败原因</dt><dd>$reason</dd>";
            $data['memo'] = <<<TEXT
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">积分退回</dd>
$reason_data
</dl>
TEXT;
        } elseif ($entry->getSrc() == Balance::REWARD_ADV) {
            $data['memo'] = <<<TEXT
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">广告奖励</dd>
<dt>说明</dt>
<dd class="event">完成观看小程序激励广告，获得奖励</dd>
</dl>
TEXT;
        } elseif ($entry->getSrc() == Balance::API_UPDATE) {
            $reason = $entry->getExtraData('reason', '');
            $reason_data = $reason ? "<dt>第三方备注</dt><dd>$reason</dd>" : '';
            $data['memo'] = <<<TEXT
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">API接口请求</dd>
<dt>说明</dt>
<dd class="event">第三方API接口请求修改积分</dd>
$reason_data
</dl>
TEXT;
        } elseif ($entry->getSrc() == Balance::TASK_BONUS) {
            $account_profile = $entry->getExtraData('account', []);
            $type_title = '任务';
            $text = $account_profile ? "<dt>$type_title</dt><dd><img src=\"{$account_profile['img']}\" alt=\"\">{$account_profile['title']}</dd>" : '';
            $data['memo'] = <<<TEXT
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">任务奖励</dd>
$text
<dt>说明</dt><dd class="event">任务资料通过审核，获得积分</dd>
</dl>
TEXT;
        } elseif ($entry->getSrc() == Balance::PROMOTE_BONUS) {
            $account_profile = $entry->getExtraData('account', []);
            $type_title = Account::getTypeTitle($account_profile['type']);
            $text = $account_profile ? "<dt>$type_title</dt><dd><img src=\"{$account_profile['img']}\" alt=\"\">{$account_profile['title']}</dd>" : '';
            $data['memo'] = <<<TEXT
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">任务奖励</dd>
$text
<dt>说明</dt><dd class="event">用户完成指定任务，系统奖励积分</dd>
</dl>
TEXT;
        } elseif ($entry->getSrc() == Balance::DELIVERY_ORDER) {
            $order_id = $entry->getExtraData('order.id');
            $order = Delivery::get($order_id);
            if ($order) {
                $order_desc = "<dt>订单号</dt><dd>{$order->getOrderNo()}</dd>";

                $num = $order->getNum();
                $goods = $order->getExtraData('goods', []);
                $goods_desc = "<b>{$goods['name']}</b>x$num";
            } else {
                $order_desc = "<dt>订单号</dt><dd>未知订单</dd>";
                $goods_desc = '<未知商品>';
            }

            $data['memo'] = <<<TEXT
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">商城订单</dd>
$order_desc
<dt>说明</dt><dd class="event">用户在商城兑换$goods_desc</dd>
</dl>
TEXT;
        } elseif ($entry->getSrc() == Balance::USER_NEW) {
            $data['memo'] = <<<TEXT
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">新用户首次登录，赠送积分</dd>
</dl>
TEXT;
        } elseif ($entry->getSrc() == Balance::USER_REF) {
            $user_profile = $entry->getExtraData('user', []);
            $type_title = '用户';
            $text = $user_profile ? "<dt>$type_title</dt><dd><img src=\"{$user_profile['headimgurl']}\" alt=\"\">{$user_profile['nickname']}</dd>" : '';
            $data['memo'] = <<<TEXT
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">推荐新用户奖励</dd>
$text
</dl>
TEXT;
        }

        return $data;
    }

    /**
     * @param userModelObj $user
     * @param accountModelObj $account
     * @param string $serial 做为唯一记录UID，存在时返回错误
     * @param string $reason
     * @return array|balanceModelObj|bool
     */
    public static function give(userModelObj $user, accountModelObj $account, string $serial = '', string $reason = '')
    {
        if (!$user->acquireLocker(User::BALANCE_GIVE_LOCKER)) {
            return err('无法锁定用户！');
        }

        if ($account->getBonusType() != Account::BALANCE) {
            return err('没有设置积分奖励！');
        }

        return DBUtil::transactionDo(function () use ($user, $account, $serial, $reason) {
            if ($serial) {
                if (BalanceLog::query()->exists(['s2' => $serial])) {
                    return err('记录已经存在！');
                }
            }

            $bonus = $account->getBalancePrice();

            if (!$account->isTask()) {
                $result = Helper::checkBalanceAvailable($user, $account);
                if (is_error($result)) {
                    return $result;
                }
                $data = [
                    'user_id' => $user->getId(),
                    'account_id' => $account->getId(),
                    'extra' => [
                        'reason' => $reason,
                        'user' => $user->profile(),
                        'account' => $account->profile(),
                        'bonus' => $bonus,
                    ],
                ];
                if ($serial) {
                    $data['s2'] = $serial;
                }
                if (!BalanceLog::create($data)) {
                    return err('创建领取记录失败！');
                }
            }

            if ($bonus > 0) {
                $result = $user->getBalance()->change(
                    $account->getBalancePrice(),
                    $account->isTask() ? Balance::TASK_BONUS : Balance::ACCOUNT_BONUS,
                    [
                        'account' => $account->profile(),
                    ]
                );
                if (!$result) {
                    return err('创建用户积分记录失败！');
                }

                return $result;
            }

            return true;
        });
    }

    public static function dailySignIn(userModelObj $user)
    {
        $bonus = Config::balance('sign.bonus', []);
        if (empty($bonus) || !$bonus['enabled']) {
            return err('这个功能没有启用！');
        }

        if (!$user->acquireLocker(User::DAILY_SIGN_IN_LOCKER)) {
            return err('请稍后再试！');
        }

        if ($user->isSigned()) {
            return err('已经签到了！');
        }

        $min = intval($bonus['min']);
        $max = intval($bonus['max']);

        if ($min >= $max) {
            $val = $min;
        } else {
            try {
                $val = random_int($min, $max);
            } catch (Exception|Error $e) {
            }
        }

        if (empty($val)) {
            return err('真遗憾，没有获得积分！');
        }

        $res = $user->signIn($val);
        if (empty($res)) {
            return err('签到失败！');
        }

        return $val;
    }

    public static function isFreeOrder(): bool
    {
        static $result = null;
        if (is_null($result)) {
            $result = Config::balance('order.as', 'free') == 'free';
        }

        return $result;
    }

    public static function isPayOrder(): bool
    {
        static $result = null;
        if (is_null($result)) {
            $result = Config::balance('order.as', 'free') == 'pay';
        }

        return $result;
    }

    public static function onUserCreated(userModelObj $user, userModelObj $ref_user = null)
    {
        $bonus = Config::balance('user', []);
        if (empty($bonus)) {
            return true;
        }

        if ($bonus['new'] > 0) {
            $result = $user->getBalance()->change((int)$bonus['new'], Balance::USER_NEW);
            if (!$result) {
                return err('创建用户积分记录失败！');
            }
        }
        if ($bonus['ref'] > 0 && $ref_user != null) {
            $result = $ref_user->getBalance()->change((int)$bonus['ref'], Balance::USER_REF, [
                'user' => $user->profile(false),
            ]);
            if (!$result) {
                return err('创建用户积分记录失败！');
            }
        }

        return true;
    }
}