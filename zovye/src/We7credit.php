<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

use zovye\model\userModelObj;

class We7credit extends State
{
    const DEFAULT_CREDIT_TYPE = 'credit1';
    private $user;
    private $credit_type;

    public function __construct(userModelObj $user)
    {
        $this->user = $user;
        $this->credit_type = settings('we7credit.type', self::DEFAULT_CREDIT_TYPE);
    }

    public function __toString(): string
    {
        return strval($this->total());
    }

    //获取当前积分

    /**
     * @return float
     */
    public function total()
    {
        if ($this->user) {
            $openid = $this->user->getOpenid();

            We7::load()->model('mc');

            $uid = We7::mc_openid2uid($openid);
            $res = We7::mc_credit_fetch($uid, [$this->credit_type]);

            return floatval($res[$this->credit_type]);
        }

        return 0;
    }

    /**
     * @param int $n
     * @return bool
     */
    public function change(int $n): bool
    {
        if ($this->user && $n != 0) {
            $openid = $this->user->getOpenid();

            We7::load()->model('mc');

            $uid = We7::mc_openid2uid($openid);
            $act = $n > 0 ? '赠送' : '扣除';
            $abs_n = abs($n);

            return We7::mc_credit_update(
                $uid,
                $this->credit_type,
                $n,
                [
                    0,
                    "用户免费领取商品，{$act}{$abs_n}积分",
                    APP_NAME,
                ]
            );
        }

        return false;
    }
}
