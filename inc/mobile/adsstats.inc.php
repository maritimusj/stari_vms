<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\AdStats;
use zovye\domain\Advertising;
use zovye\model\advs_statsModelObj;

$user = Session::getCurrentUser();
$adv = Advertising::get(Request::int('advsid'));

if ($user && $adv) {
    $url = $adv->getExtraData('link');

    /** @var advs_statsModelObj $stats */
    $stats = AdStats::findOne(['openid' => $user->getOpenid(), 'advs_id' => $adv->getId()]);
    if (empty($stats)) {
        AdStats::create([
            'openid' => $user->getOpenid(),
            'advs_id' => $adv->getId(),
            'device_id' => Request::int('deviceid'),
            'account_id' => Request::int('accountid'),
            'ip' => CLIENT_IP,
            'count' => 1,
            'extra' => [
                'user-agent' => $_SERVER['HTTP_USER_AGENT'],
                'url' => $url,
            ],
        ]);
    } else {
        $stats->setCount(intval($stats->getCount()) + 1);
    }

    if ($url) {
        Response::redirect($url);
    }
}
