<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\advReviewResult;

//广告审核结果

use zovye\Advertising;
use zovye\CtrlServ;
use zovye\Log;
use zovye\request;
use zovye\ReviewResult;
use zovye\Util;
use zovye\Wx;
use function zovye\request;
use function zovye\settings;

$op = request::op('default');
$log = [
    'id' => request('id'),
];
if ($op == 'adv_review_result' && CtrlServ::checkJobSign(['id' => request('id')])) {
    $adv = Advertising::get(request::int('id'));
    if (empty($adv)) {
        $log['error'] = '广告不存在!';
        Log::fatal('adv_review_result', $log);
    }

    $tpl_id = settings('notice.advReviewResultTplid');
    if ($tpl_id) {
        $log['tpl_id'] = $tpl_id;

        $agent = $adv->getOwner();
        if ($agent) {
            $log['agent'] = $agent->getName();

            foreach (Util::getNotifyOpenIds($agent, 'reviewResult') as $openid) {
                $res = Wx::sendTplNotice(
                    $openid,
                    $tpl_id,
                    [
                        'first' => ['value' => $adv->isReviewPassed() ? '您的广告已经通过审核！' : '您上传的广告已经被拒绝发布！'],
                        'keyword1' => ['value' => $agent->getNickname()],
                        'keyword2' => ['value' => $adv->getTitle()],
                        'keyword3' => ['value' => date('Y-m-d H:i:s', $adv->getCreatetime())],
                        'keyword4' => [
                            'value' => $adv->isReviewPassed() ? ReviewResult::desc(
                                ReviewResult::PASSED
                            ) : ReviewResult::desc(ReviewResult::REJECTED),
                        ],
                        'remark' => ['value' => '有任何问题，请与我们取得联系，谢谢！'],
                    ]
                );
                $log['notice']['openid'] = $res;
            }
        }
    }
}

Log::debug('adv_review_result', $log);
