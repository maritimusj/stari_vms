<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\accountModelObj;
use zovye\model\balanceModelObj;
use zovye\model\userModelObj;

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

    private $user;

    public function __construct(userModelObj $user)
    {
        $this->user = $user;
    }

    public function __toString(): string
    {
        return strval($this->total());
    }

    public static function query($condition = []): base\modelObjFinder
    {
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
     * @return balanceModelObj
     */
    public function change(int $val, int $src, array $extra = []): ?balanceModelObj
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
            if ($result) {
                $this->on_change($result);
                return $result;
            }
        }

        return null;
    }

    public function on_change(balanceModelObj $item)
    {
        $notify_url = Config::balance('app.notify_url');
        if ($notify_url) {
            $profile = $this->user->profile(true);
            $profile['balance'] = $this->total();
            $profile['change'] = $item->getXVal();
            
            $data = json_encode([
                'data' => $profile,
                'serial' => sha1(App::uid(6) . $item->getId()),
                'sign' => hash_hmac('sha1', http_build_query($profile), Config::balance('app.key')),
            ], JSON_UNESCAPED_UNICODE);

            $result = CtrlServ::httpQueuedCallback(LEVEL_NORMAL, $notify_url, $data);
            if (is_error($result)) {
                Log::error('balance', [
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
        $total = 0;

        if ($this->user) {

            $openid = $this->user->getOpenid();
            $query = Balance::query(['openid' => $openid]);

            $last_id = 0;
            $last_total = 0;
            $last_time = 0;

            $cache = $this->user->get('balance:cache', []);
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
                    $locker = $this->user->acquireLocker(User::BALANCE_LOCKER);
                    if ($locker) {
                        $this->user->set('balance:cache', [
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
        }

        return $total;
    }

    /**
     * 返回用户积分变动记录
     */
    public function log(): ?base\modelObjFinder
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
            $data['xval'] = '+' . $data['xval'];
        }

        if ($entry->getSrc() == Balance::ADJUST) {
            $name = $entry->getExtraData('admin');
            $line = "<dt>管理员</dt><dd class=\"admin\">{$name}</dd>";
            $memo = $entry->getExtraData('memo', '');
            if ($memo) {
                $line .= "<dt>说明</dt><dd class=\"memo\">{$memo}</dd>";
            }
            $data['memo'] = <<<TEXT
<dl class="log dl-horizontal">
<dt>事件</dt><dd class="event">管理员调整</dd>
$line
</dl>
TEXT;

        } elseif ($entry->getSrc() == Balance::ACCOUNT_BONUS) {
            $account_data = $entry->getExtraData('account');
            $account_typeifno = [
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
                ][$account_data['type']] ?? ['公众号', '成功关注公众号'];

            $account_info = "<dt>{$account_typeifno[0]}</dt><dd class=\"user\"><img src=\"{$account_data['img']}\" alt=''/>{$account_data['title']}</dd>";
            $data['memo'] = <<<TEXT
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">{$account_typeifno[1]}</dd>
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
                $line .= "<dt>商品</dt><dd class=\"goods\"><img src=\"$img\" >{$goods['name']} x$num</dd>";
            }
            $device = Device::get($entry->getExtraData('device'));
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
            $reason_data =  $reason ? "<dt>第三方备注</dt><dd>$reason</dd>" : '';
            $data['memo'] = <<<TEXT
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">API接口请求</dd>
<dt>说明</dt>
<dd class="event">第三方API接口请求修改积分</dd>
$reason_data
</dl>
TEXT;
        }

        return $data;
    }

    public static function give(userModelObj $user, accountModelObj $account, $reason = '')
    {
        if (!$user->acquireLocker("balance:give")) {
            return err('无法锁定用户！');
        }

        if ($account->getBonusType() != Account::BALANCE || $account->getBalancePrice() <= 0) {
            return err('没有设置积分奖励！');
        }

        $result = Util::checkBalanceAvailable($user, $account);
        if (is_error($result)) {
            return $result;
        }

        return Util::transactionDo(function () use ($user, $account, $reason) {
            if ($account->getBonusType() != Account::BALANCE || $account->getBalancePrice() == 0) {
                return err('公众号设置不正确！');
            }
            if (!BalanceLog::create([
                'user_id' => $user->getId(),
                'account_id' => $account->getId(),
                'extra' => [
                    'reason' => $reason,
                    'user' => $user->profile(),
                    'account' => $account->profile(),
                    'bonus' => $account->getBalancePrice(),
                ]
            ])) {
                return err('创建领取记录失败！');
            }
            $result = $user->getBalance()->change($account->getBalancePrice(), Balance::ACCOUNT_BONUS, [
                'account' => $account->profile(),
            ]);
            if (!$result) {
                return err('创建用户积分记录失败！');
            }
            return $result;
        });
    }

    public static function isFreeOrder(): bool
    {
        return  Config::balance('order.as', 'free') == 'free';
    }

    public static function isPayOrder(): bool
    {
        return  Config::balance('order.as', 'free') == 'pay';
    }
}