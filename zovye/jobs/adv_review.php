<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\advReview;

defined('IN_IA') or exit('Access Denied');

//广告审核

use zovye\Advertising;
use zovye\App;
use zovye\CtrlServ;
use zovye\Job;
use zovye\Log;
use zovye\Media;
use zovye\model\userModelObj;
use zovye\Request;
use zovye\User;
use zovye\Util;
use zovye\Wx;
use function zovye\is_error;
use function zovye\request;
use function zovye\settings;

$op = Request::op('default');

if ($op == 'adv_review' && CtrlServ::checkJobSign(['id' => request('id')])) {
    $id = Request::int('id');
    $adv = Advertising::get($id);
    if (empty($adv)) {
        Log::fatal('adv_review', [
            'error' => "adv[$id] not found!",
        ]);
    }

    $tpl_id = settings('notice.advReviewTplid');
    if ($tpl_id) {
        $agent = $adv->getOwner();
        if (empty($agent)) {
            Log::fatal('adv_review', [
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
            'remark' => ['value' => "代理商：$agentName"],
        ];

        if (settings('notice.reviewAdminUserId')) {
            /** @var userModelObj $user */
            $user = User::findOne(['id' => settings('notice.reviewAdminUserId')]);
            if ($user) {
                $url = Util::murl(
                    'util',
                    [
                        'op' => 'adv_review',
                        'id' => $adv->getId(),
                        'sign' => sha1(App::uid().$user->getOpenid().$adv->getId()),
                    ]
                );
                $res = Wx::sendTplNotice($user->getOpenid(), $tpl_id, $notify_data, $url);
                if (is_error($res)) {
                    $res = [
                        'user' => $user->profile(),
                        'err' => $res['message'],
                    ];
                }
            } else {
                $res = '找不到指定用户！';
            }
        } else {
            $res = '没有指定用户！';
        }

        Log::debug('adv_review', ['result' => $res]);
        Job::exit();
    }
}

Log::debug('adv_review', ['result' => 'failed, advReviewTplid is empty!']);
