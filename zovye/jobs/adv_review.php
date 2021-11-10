<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\advReview;

//广告审核

use zovye\Advertising;
use zovye\CtrlServ;
use zovye\request;
use zovye\Media;
use zovye\User;
use zovye\Util;
use zovye\Wx;
use function zovye\request;
use function zovye\is_error;
use function zovye\settings;

$op = request::op('default');

if ($op == 'adv_review' && CtrlServ::checkJobSign(['id' => request('id')])) {
    $id = request::int('id');
    $adv = Advertising::get($id);
    if (empty($adv)) {
        return Util::logToFile('adv_review', [
            'error' => "adv[{$id}] not found!",
        ]);
    }

    $tpl_id = settings('notice.advReviewTplid');
    if ($tpl_id) {
        $agent = $adv->getOwner();
        if (empty($agent)) {
            return Util::logToFile('adv_review', [
                'error' => 'adv\'s agent is empty!',
            ]);
        }

        $agent_data = $agent->getAgentData();
        $type = Media::desc($adv->getExtraData('media'));
        $agentName = $agent_data['name'] ?: $agent->getNickname();
        $notify_data = [
            'first' => ['value' => '代理商广告需要审核！'],
            'keyword1' => ['value' => "{$type}广告"],
            'keyword2' => ['value' => '<无>'],
            'keyword3' => ['value' => date('Y-m-d H:i:s', $adv->getCreatetime())],
            'remark' => ['value' => "代理商：{$agentName}"],
        ];

        if (settings('notice.reviewAdminUserId')) {
            $user = User::findOne(['id' => settings('notice.reviewAdminUserId')]);
            if ($user) {
                $url = Util::murl('util', ['op' => 'adv_review', 'id' => $adv->getId(), 'sign' => sha1(\zovye\App::uid() . $user->getOpenid() . $adv->getId())]);
                if (!is_error(Wx::sendTplNotice($user->getOpenid(), $tpl_id, $notify_data, $url))) {
                    $res[] = "[ {$user->getNickname()} ]=> Ok" . PHP_EOL;
                } else {
                    $res[] = "[ {$user->getNickname()} ]=> fail" . PHP_EOL;
                }
            } else {
                $res = '找不到指定用户！';
            }
        } else {
            $res = '没有指定用户！';
        }

        return Util::logToFile('adv_review', ['result' => $res]);
    }
}

Util::logToFile('adv_review', ['result' => 'failed, advReviewTplid is empty!']);
