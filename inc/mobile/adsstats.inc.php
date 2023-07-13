<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\advs_statsModelObj;

$user = Session::getCurrentUser();
$adv = Advertising::get(Request::int('advsid'));

if ($user && $adv) {
    $url = $adv->getExtraData('link');

    /** @var advs_statsModelObj $stats */
    $stats = m('advs_stats')->findOne(We7::uniacid(['openid' => $user->getOpenid(), 'advs_id' => $adv->getId()]));
    if (empty($stats)) {
        $data = We7::uniacid(
            [
                'openid' => $user->getOpenid(),
                'advs_id' => $adv->getId(),
                'device_id' => Request::int('deviceid'),
                'account_id' => Request::int('accountid'),
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
        header("location:$url");
    }
}
