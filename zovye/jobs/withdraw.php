<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\withdraw;

use zovye\CtrlServ;
use zovye\Job;
use zovye\Log;
use zovye\model\userModelObj;
use zovye\Principal;
use zovye\Request;
use zovye\User;
use zovye\Wx;
use function zovye\is_error;
use function zovye\request;
use function zovye\settings;

$op = Request::op('default');
$data = [
    'id' => request('id'),
    'amount' => request('amount'),
];

$log = [
    'data' => $data,
];

if ($op == 'withdraw' && CtrlServ::checkJobSign($data)) {
    $tpl_id = settings('notice.withdraw_tplid');
    if ($tpl_id) {
        $apply_user = User::get(Request::int('id'));
        if ($apply_user) {

            $notify_data = [
                'first' => ['value' => '有一笔提现待审批，请尽快审核！'],
                'keyword1' => ['value' => $apply_user->getNickname()],
                'keyword2' => ['value' => date('Y-m-d H:i:s')],
                'keyword3' => ['value' => ($data['amount'] / 100).'元'],
            ];
            $admin_id = intval(settings('notice.withdrawAdminUserId'));
            if ($admin_id) {
                $query = User::query(['id' => $admin_id]);
            } else {
                $query = Principal::admin();
            }

            /** @var userModelObj $user */
            foreach ($query->findAll() as $user) {
                $res = Wx::sendTplNotice($user->getOpenid(), $tpl_id, $notify_data);
                if (!is_error($res)) {
                    $log['result'][$user->getOpenid()] = "[ {$user->getNickname()} ]=> Ok ".PHP_EOL;
                } else {
                    $log['result'][$user->getOpenid()] = $res;
                }
            }

            $log['data'] = $notify_data;
            Log::debug('withdraw', $log);
            Job::exit();
        }
    }
}

$log['result'] = 'fail';
Log::debug('withdraw', $log);