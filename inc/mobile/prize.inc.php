<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$user = Util::getCurrentUser();
if (empty($user) || $user->isBanned()) {
    JSON::fail('找不到用户或者用户无法参与活动！');
}

if (App::isUserCenterEnabled() && App::isUserPrizeEnabled()) {

    $maxTimes = App::maxUserPrizeTimes();
    $prize_data = $user->get('prizeData', []);

    if ($prize_data) {
        if (date('Ymd', $prize_data['lastx']) == date('Ymd') && $prize_data['total'] >= $maxTimes) {
            JSON::fail('今天的机会已经用完了，明天再来！');
        } else {
            $prize_data['total'] = 0;
        }
    }

    $prize_data['total'] = intval($prize_data['total']) + 1;
    $prize_data['lastx'] = time();

    $user->set('prizeData', $prize_data);
    $old = $user->getBalance()->total();

    $prize = Prize::give($user);
    if ($prize && !is_error($prize)) {
        $result = [
            'id' => $prize->getId(),
            'title' => $prize->getTitle(),
            'img' => Util::tomedia($prize->getImg()),
            'link' => $prize->getLink() ?: Util::murl('myPrizes'),
            'desc' => $prize->getDesc(),
            'createtime' => date('Y-m-d H:i:s', $prize->getCreatetime()),
            'msg' => '恭喜您，中奖啦！',
            'change' => $user->getBalance()->total() - $old,
            'balance' => $user->getBalance()->total(),
        ];

        JSON::success($result);
    }

    JSON::fail('没有中奖，下次继续努力！');
}

JSON::fail('暂时没有任何活动！');
