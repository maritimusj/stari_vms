<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

use zovye\model\userModelObj;
use zovye\model\balanceModelObj;

class Balance extends State
{
    const SYS = 1;
    const ORDER = 2;
    const CHARGE = 3;
    const PRIZE = 4;

    protected static $title = [
        self::SYS => '每月赠送',
        self::ORDER => '余额领货',
        self::CHARGE => '现金充值',
        self::PRIZE => '每日抽奖',
    ];

    private $user;

    public function __construct(userModelObj $user)
    {
        $this->user = $user;
    }

    public function __toString(): string
    {
        return strval($this->total());
    }

    /**
     * 获取当前余额
     * @return int
     */
    public function total(): int
    {
        $this->checkFree();

        if ($this->user) {
            $openid = $this->user->getOpenid();
            $remain = m('balance')->where(We7::uniacid(['openid' => $openid]))->get('sum(x_val)');
            return intval($remain);
        }

        return 0;
    }

    /**
     *  检查每月余额赠送
     */
    protected function checkFree()
    {
        if ($this->user) {
            $openid = $this->user->getOpenid();
            $remain = intval(m('balance')->where(We7::uniacid(['openid' => $openid]))->get('sum(x_val)'));

            if (date('Ym', $this->user->get('lastResetBalance')) != date('Ym')) {

                $free = intval(settings('user.balance.free'));
                if ($remain < $free) {
                    $this->change($free - $remain, Balance::SYS);
                }

                $this->user->set('lastResetBalance', time());
            }
        }
    }

    /**
     * 余额变动操作
     * @param $n
     * @param $src
     * @param string|null $memo
     * @return balanceModelObj|null
     */
    public function change($n, $src, string $memo = null): ?balanceModelObj
    {
        if ($this->user && $n != 0) {
            return m('balance')->create(
                We7::uniacid(
                    [
                        'openid' => $this->user->getOpenid(),
                        'x_val' => intval($n),
                        'src' => $src,
                        'memo' => $memo,
                    ]
                )
            );
        }

        return null;
    }

    /**
     * 返回用户余额变动记录
     */
    public function log(): ?base\modelObjFinder
    {
        if ($this->user) {
            $openid = $this->user->getOpenid();
            return m('balance')->where(We7::uniacid(['openid' => $openid]));
        }

        return null;
    }
}
