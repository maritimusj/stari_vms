<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\job\withdraw;

use zovye\CtrlServ;
use zovye\request;
use zovye\model\userModelObj;
use zovye\User;
use zovye\Util;
use zovye\Wx;
use function zovye\request;
use function zovye\is_error;
use function zovye\settings;

$op = request::op('default');
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
        $apply_user = User::get(request::int('id'));
        if ($apply_user) {

            $notify_data = [
                'first' => ['value' => '有一笔提现待审批，请尽快审核！'],
                'keyword1' => ['value' => $apply_user->getNickname()],
                'keyword2' => ['value' => date('Y-m-d H:i:s')],
                'keyword3' => ['value' => ($data['amount'] / 100) . '元'],
            ];

            $query = User::query();

            if (settings('notice.withdrawAdminUserId')) {
                $query->where(['id' => settings('notice.withdrawAdminUserId')]);
            } else {
                $query->where("LOCATE('admin',passport)>0");
            }

            /** @var userModelObj $user */
            foreach ($query->findAll() as $user) {
                $res = Wx::sendTplNotice($user->getOpenid(), $tpl_id, $notify_data);
                if (!is_error($res)) {
                    $log['result'][$user->getOpenid()] = "[ {$user->getNickname()} ]=> Ok " . PHP_EOL;
                } else {
                    $log['result'][$user->getOpenid()] = $res;
                }
            }

            $log['data'] = $notify_data;
            return Util::logToFile('withdraw', $log);
        }
    }
}

$log['result'] = 'fail';
Util::logToFile('withdraw', $log);