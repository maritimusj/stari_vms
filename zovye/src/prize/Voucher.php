<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\prize;

use zovye\Balance;
use zovye\Contract\IPrize;
use zovye\State;
use zovye\model\userModelObj;
use zovye\Util;
use zovye\We7;
use function zovye\error;
use function zovye\m;
use function zovye\settings;

class Voucher implements IPrize
{
    private $id;

    public function __construct($id = 0)
    {
        $this->id = $id;
    }

    public function isValid(array $params)
    {
        if (empty($params['title'])) {
            return error(State::ERROR, '请指定点券奖品的名称！');
        }
        if (empty($params['balance'])) {
            return error(State::ERROR, '请指定点券奖品赠送的余额数量！');
        }

        return false;
    }

    public function give(userModelObj $user, array $params = [])
    {
        $params = array_merge($this->desc(), $params);
        return Util::transactionDo(
            function () use ($user, $params) {

                $unit_title = settings('user.balance.unit');
                $balance_title = settings('user.balance.title');
                $balance = $user->getBalance();

                $ps = [];

                for ($i = 0; $i < max(1, $params['num']); $i++) {
                    $num = is_array($params['balance']) ? rand(
                        $params['balance']['min'],
                        $params['balance']['max']
                    ) : intval($params['balance']);

                    $v = $balance->change(+$num, Balance::PRIZE, "获得奖品：{$params['title']}");
                    if ($v) {
                        $p = m('prize')->create(
                            We7::uniacid(
                                [
                                    'openid' => $user->getOpenid(),
                                    'img' => $params['pic'],
                                    'title' => $params['title'],
                                    'desc' => "用户获得{$num}{$unit_title}{$balance_title}",
                                ]
                            )
                        );

                        if (empty($p)) {
                            return error(State::FAIL, 'failed');
                        }
                        $ps[] = $p;
                    }
                }

                return $ps;
            }
        );
    }

    public function desc(): array
    {
        $unit_title = settings('user.balance.unit');
        $balance_title = settings('user.balance.title');

        return [
            'title' => "{$balance_title}",
            'desc' => "用户可以获得指定数量的{$unit_title}",
            'img' => '',
        ];
    }
}
