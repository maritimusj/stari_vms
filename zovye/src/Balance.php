<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\accountModelObj;
use zovye\model\balanceModelObj;
use zovye\model\commission_balanceModelObj;
use zovye\model\userModelObj;

class Balance
{
    const CACHE_EXPIRATION = 60 * 60;

    const ADJUST = 0;
    const ACCOUNT_BONUS = 1; // 关注公众号
    const SIGN_IN_BONUS = 2; // 每日签到
    const VIDEO_BONUS = 3; // 观看视频
    const WX_APP_BONUS = 4; // 关注微信小程序


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

    public static function findOne($condition = []): ?commission_balanceModelObj
    {
        return self::query($condition)->findOne();
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
            return m('balance')->create(
                We7::uniacid(
                    [
                        'openid' => $this->user->getOpenid(),
                        'src' => $src,
                        'x_val' => $val,
                        'extra' => json_encode($extra),
                    ]
                )
            );
        }

        return null;
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

    public static function format(balanceModelObj $entry)
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
            $admin_info = "<dt>管理员</dt><dd class=\"admin\">$name</dd>";
            $memo = $entry->getExtraData('memo');
            if ($memo) {
                $memo_info = '<dt>说明</dt>
                <dd class="memo">$memo</dd>';
            } else {
                $memo_info = '';
            }
            $data['memo'] = <<<REFUND
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">管理员调整</dd>
$admin_info
$memo_info
</dl>
REFUND;

        } elseif ($entry->getSrc() == Balance::ACCOUNT_BONUS) {
            $account_data = $entry->getExtraData('account');
            $account_info = "<dt>公众号</dt><dd class=\"user\"><img src=\"{$account_data['img']}\" alt=''/>{$account_data['title']}</dd>";
            $data['memo'] = <<<REFUND
<dl class="log dl-horizontal">
<dt>事件</dt>
<dd class="event">关注公众号</dd>
$account_info
</dl>
REFUND;
        }

        return $data;
    }

    public static function give(userModelObj $user, accountModelObj $account, $reason = '')
    {
        if ($user->acquireLocker("balance:give")) {
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
}