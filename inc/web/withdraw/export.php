<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\commission_balanceModelObj;

set_time_limit(60);

$query = CommissionBalance::query(['src' => CommissionBalance::WITHDRAW]);
$query->where('(updatetime IS NULL OR updatetime=0)');

$query->orderBy('id DESC');

$list = [];

/** @var commission_balanceModelObj $entry */
foreach ($query->findAll() as $entry) {
    $state = $entry->getExtraData('state');
    if (empty($state)) {
        $user = User::get($entry->getOpenid(), true);
        if ($user) {
            $bank = $user->settings('agentData.bank', []);
            $data = [
                'id' => $entry->getId(),
                'name' => $user->getName(),
                'mobile' => "[{$user->getMobile()}]",
                'xval' => number_format(abs($entry->getXVal()) / 100, 2, '.', ''),
                'bank' => $bank['bank'],
                'branch' => $bank['branch'],
                'realname' => $bank['realname'],
                'account' => "[{$bank['account']}]",
                'address' => $bank['address']['province'].$bank['address']['city'],
                'memo' => '',
                'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
            ];

            if ($user->isKeeper()) {
                $keeper = $user->getKeeper();
                if ($keeper) {
                    $data['memo'] = $keeper->getName();
                }
            }

            $list[] = $data;
        }
    }
}

Util::exportCSV('withdraw', ['#', '代理商', '手机', '金额(元)', '开户行', '开户支行', '姓名', '卡号', '开户行地址', '备注',  '创建时间'], $list);
