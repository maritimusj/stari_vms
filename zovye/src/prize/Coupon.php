<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\prize;

use zovye\Contract\IPrize;
use zovye\State;
use zovye\model\userModelObj;
use zovye\Util;
use zovye\We7;
use function zovye\error;
use function zovye\m;

class Coupon implements IPrize
{
    private $id;

    public function __construct($id = 0)
    {
        $this->id = $id;
    }

    public function isValid(array $params)
    {
        if (empty($params['title'])) {
            return error(State::ERROR, '请指定代金券奖品的名称！');
        }

        if (empty($params['xval'])) {
            return error(State::ERROR, '请指定代金券奖品的金额！');
        }

        return true;
    }

    public function give(userModelObj $user, array $params = [])
    {
        $params = array_merge($this->desc(), $params);

        return Util::transactionDo(
            function () use ($user, $params) {

                $ps = [];
                for ($i = 0; $i < max(1, $params['num']); $i++) {

                    $data = We7::uniacid(
                        [
                            'uid' => sha1(Util::random(10) . microtime()),
                            'title' => $params['title'],
                            'x_val' => is_array($params['xval']) ? rand(
                                $params['xval']['min'],
                                $params['xval']['max']
                            ) : intval($params['xval']),
                            'x_require' => $params['xrequire'],
                            'owner' => $user->getOpenid(),
                            'memo' => "用户充值{$params['xrequire']}元时可以抵扣{$params['xval']}元现金",
                        ]
                    );

                    $coupon = m('coupon')->create($data);
                    if ($coupon) {
                        $p = m('prize')->create(
                            We7::uniacid(
                                [
                                    'openid' => $user->getOpenid(),
                                    'img' => $params['pic'],
                                    'title' => $params['title'],
                                    'desc' => "用户充值{$params['xrequire']}元时可以抵扣{$data['xval']}元现金",
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
        return [
            'title' => '代金券',
            'desc' => '用户充值时抵扣指定金额的现金',
            'img' => '',
        ];
    }
}
