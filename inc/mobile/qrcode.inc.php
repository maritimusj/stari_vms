<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\qrcode;

defined('IN_IA') or exit('Access Denied');

use DateTime;
use SplPriorityQueue;
use zovye\domain\Advertising;
use zovye\domain\User;
use zovye\model\advertisingModelObj;
use zovye\Response;
use zovye\Session;
use zovye\util\PlaceHolder;
use function zovye\settings;

$params = [
    'create' => true,
    'update' => true,
    'from' => [
        'src' => 'qrcode',
        'ip' => CLIENT_IP,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
    ],
];

$user = Session::getCurrentUser($params);
if (empty($user)) {
    Response::alert('请用微信扫一扫打开，谢谢！', 'error');
}

$profile = $user->profile();
$phone_os = Session::getUserPhoneOS();

$query = Advertising::query(['type' => Advertising::ACTIVE_QRCODE, 'state' => Advertising::NORMAL]);

/**
 * Class priorityQRCodes
 */
class priorityQRCodes extends SplPriorityQueue
{
    public function compare($priority1, $priority2): int
    {
        if ($priority1['score'] == $priority2['score']) {
            return $priority1['priority'] - $priority2['priority'];
        }

        return $priority1['score'] - $priority2['score'];
    }
}

$qr_codes = new priorityQRCodes();

/** @var advertisingModelObj $entry */
foreach ($query->findAll() as $entry) {

    $extra = $entry->getExtra();
    if (empty($extra['url'])) {
        continue;
    }

    $score = 0;

    if ($extra['sex'] != User::UNKNOWN) {
        if ($extra['sex'] == $profile['sex']) {
            $score++;
        } else {
            continue;
        }
    }

    if ($extra['phoneos'] != 'unknown') {
        if ($extra['phoneos'] == $phone_os) {
            $score++;
        } else {
            continue;
        }
    }

    if ($extra['area']) {
        if ($extra['area']['province']) {
            if (strpos($extra['area']['province'], $profile['province']) !== false) {
                $score++;
            } else {
                continue;
            }
        }
        if ($extra['area']['city']) {
            if (strpos($extra['area']['city'], $profile['city']) !== false) {
                $score++;
            } else {
                continue;
            }
        }
    }

    $qr_codes->insert(
        $entry,
        [
            'score' => $score,
            'priority' => $extra['priority'],
        ]
    );
}

$params = [$user, new DateTime()];
$device = $user->getLastActiveDevice();
if ($device) {
    $params[] = $device;
}

if ($qr_codes->count() > 0) {
    $entry = $qr_codes->top();
    $url = $entry->getExtraData('url');
    if ($url) {
        $total = (int)$entry->getExtraData('visited.total');
        $entry->setExtraData('visited.total', $total + 1);

        $user_visited = $user->settings('qrcode.visited', []);
        if (!in_array($user->getId(), $user_visited)) {

            $count = (int)$entry->getExtraData('visited.count');
            $entry->setExtraData('visited.count', $count + 1);

            $user_visited[] = $user->getId();
            $user->updateSettings('qrcode.visited', $user_visited);
        }

        $entry->save();

        Response::redirect(PlaceHolder::replace($url, $params));
    }
}

$default_url = settings('misc.qrcode.default_url');
if ($default_url) {
    Response::redirect(PlaceHolder::replace($default_url, $params));
}

Response::alert('没有设置网址！', 'error');
