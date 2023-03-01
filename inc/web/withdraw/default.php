<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\commission_balanceModelObj;
use zovye\model\userModelObj;

$agent_levels = settings('agent.levels');
$commission_enabled = App::isCommissionEnabled();

$tpl_data = [
    'agent_levels' => $agent_levels,
    'commission_enabled' => $commission_enabled,
];

$tpl_data['mch_pay_enabled'] = !empty(settings('pay.wx.pem'));

$page = max(1, Request::int('page'));
$page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

$query = CommissionBalance::query(['src' => CommissionBalance::WITHDRAW]);
//$query->where('(updatetime IS NULL OR updatetime=0)');

if (Request::has('agentId')) {
    $user_x = User::get(request('agentId'));
    if ($user_x) {
        $tpl_data['user'] = $user_x->profile();
        $query->where(['openid' => $user_x->getOpenid()]);
    }
}

$total = $query->count();

$total_page = ceil($total / $page_size);
if ($page > $total_page) {
    $page = 1;
}

$apps = [];
if ($total > 0) {
    $tpl_data['pager'] = We7::pagination($total, $page, $page_size);

    $query->page($page, $page_size);
    $query->orderBy('id desc');

    /** @var commission_balanceModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $data = [
            'id' => $entry->getId(),
            'xval' => number_format(abs($entry->getXVal()) / 100, 2),
            'remain' => $entry->getExtraData('remain'),
            'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
        ];

        if ($entry->getExtraData('charging', false)) {
            $data['charging'] = true;
        }

        $current = $entry->getExtraData('current');
        if (isset($current)) {
            $data['current'] = number_format($current / 100, 2);
        }

        $remain = $entry->getExtraData('remain');
        if (isset($remain)) {
            $data['remain'] = number_format($remain / 100, 2);
        }

        $fee = $entry->getExtraData('fee');
        if (isset($fee)) {
            $data['fee'] = $fee;
        }

        $memo = $entry->getExtraData('memo', '');
        if (!empty($memo)) {
            $data['memo'] = $memo;
        }

        $state = $entry->getExtraData('state');
        if (empty($state)) {
            $status = '审核中';
        } elseif ($state == 'mchpay') {
            $MCHPayResult = $entry->getExtraData('mchpayResult');
            if ($MCHPayResult['payment_no']) {
                $status = '已支付';
                $data['paymentNO'] = $MCHPayResult['payment_no'];
            } else {
                $status = '未知状态';
                $state = 'mchpay unknown';
                if ($MCHPayResult['batch_id']) {
                    $status = '已提交';
                    $state = 'mchpay committed';
                    $user = User::get($entry->getOpenid(), true);
                    if ($user) {
                        $MCHPayResult = $user->getMCHPayResult($MCHPayResult['batch_id'], $MCHPayResult['out_batch_no']);
                        if ($MCHPayResult['detail_status'] == 'SUCCESS' || $MCHPayResult['detail_status'] == 'FAIL') {
                            $entry->update(['mchpayResult' =>  $MCHPayResult]);
                            $entry->save();
                        }
                    }
                }
                if ($MCHPayResult['detail_status']) {
                    if ($MCHPayResult['detail_status'] == 'SUCCESS') {
                        $status = '已支付';
                        $state = 'mchpay';
                    } elseif ($MCHPayResult['detail_status'] == 'FAIL') {
                        $status = '失败';
                        $state = 'mchpay failed';
                    }
                }
            }
        } elseif ($state == 'confirmed') {
            $status = '已完成';
        } elseif ($state == 'cancelled') {
            $status = '已退回';
        } else {
            $status = '未知';
        }

        $data['state'] = $state;
        $data['state_formatted'] = $status;
        if ($entry->getUpdatetime()) {
            $data['updatetime_formatted'] = date('Y-m-d H:i:s', $entry->getUpdatetime());
        }

        //为什么不是findAgent?因为用户可能已经不是代理了
        /** @var userModelObj $user */
        $user = User::get($entry->getOpenid(), true);
        if ($user) {
            $data['agent'] = [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'avatar' => $user->getAvatar(),
                'mobile' => $user->getMobile(),
                'bank' => $user->settings('agentData.bank', []),
            ];

            $user_qrcode = [];
            if ($user->isKeeper()) {
                $keeper = $user->getKeeper();
                if ($keeper) {
                    $user_qrcode = $keeper->settings('qrcode', []);
                    $data['keeper'] = [
                        'name' => $keeper->getName(),
                        'mobile' => $keeper->getMobile(),
                    ];
                }
            }
            if (isEmptyArray($user_qrcode)) {
                $user_qrcode = $user->settings('qrcode', []);
            }
            if (isset($user_qrcode['wx'])) {
                $user_qrcode['wx'] = Util::toMedia($user_qrcode['wx']);
            }
            if (isset($user_qrcode['ali'])) {
                $user_qrcode['ali'] = Util::toMedia($user_qrcode['ali']);
            }
            $data['agent']['qrcode'] = $user_qrcode;
        }
        $app_user_openid = $entry->getExtraData('openid');
        if ($app_user_openid) {
            $app_user = User::get($app_user_openid, true);
            if ($app_user) {
                $data['name'] = $app_user->getName();
            }
        }
        $apps[] = $data;
    }
}

$tpl_data['apps'] = $apps;

app()->showTemplate('web/withdraw/default', $tpl_data);