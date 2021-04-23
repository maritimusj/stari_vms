<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

use zovye\model\advs_statsModelObj;

defined('IN_IA') or exit('Access Denied');

$user = Util::getCurrentUser();
$adv = Advertising::get(request::int('advsid'));

if ($user && $adv) {
    $url = $adv->getExtraData('link');

    /** @var advs_statsModelObj $stats */
    $stats = m('advs_stats')->findOne(We7::uniacid(['openid' => $user->getOpenid(), 'advs_id' => $adv->getId()]));
    if (empty($stats)) {
        $data = We7::uniacid(
            [
                'openid' => $user->getOpenid(),
                'advs_id' => $adv->getId(),
                'device_id' => request::int('deviceid'),
                'account_id' => request::int('accountid'),
                'ip' => CLIENT_IP,
                'count' => 1,
                'extra' => serialize(
                    [
                        'user-agent' => $_SERVER['HTTP_USER_AGENT'],
                        'url' => $url,
                    ]
                ),
            ]
        );

        m('advs_stats')->create($data);
    } else {
        $stats->setCount(intval($stats->getCount()) + 1);
    }

    if ($url) {
        header("location:{$url}");
    }
}
