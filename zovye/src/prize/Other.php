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

class Other implements IPrize
{
    private $id;

    public function __construct($id = 0)
    {
        $this->id = $id;
    }

    public function isValid(array $params)
    {
        if (empty($params['link'])) {
            return error(State::ERROR, '请指定领取奖品的网址！');
        }

        return false;
    }

    public function give(userModelObj $user, array $params = [])
    {
        $params = array_merge($this->desc(), $params);
        return Util::transactionDo(
            function () use ($user, $params) {
                $ps = [];
                for ($i = 0; $i < max(1, $params['num']); $i++) {
                    $p = m('prize')->create(
                        We7::uniacid(
                            [
                                'openid' => $user->getOpenid(),
                                'img' => $params['pic'],
                                'link' => $params['link'],
                                'title' => $params['title'],
                                'desc' => '请点击网址获取奖励！',
                            ]
                        )
                    );

                    if (empty($p)) {
                        return error(State::FAIL, 'failed');
                    }
                    $ps[] = $p;
                }

                return $ps;
            }
        );
    }

    public function desc(): array
    {
        return [
            'title' => '其它奖励',
            'desc' => '用户名点击链接后可获取其它奖品',
            'img' => '',
        ];
    }
}
