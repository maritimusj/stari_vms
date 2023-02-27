<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\account_queryModelObj;

$tpl_data = [];

$id = request::int('id');
$acc = Account::get($id);

if (empty($acc)) {
    JSON::fail('找不到这个任务！');
}

$tpl_data['account'] = $acc->profile();

$query = Account::logQuery($acc);

if (request::has('device')) {
    $device_id = request::int('device');
    $device = Device::get($device_id);
    if (empty($device)) {
        Util::itoast('找不到这个设备！', '', 'error');
    }
    $tpl_data['device'] = $device->profile();
    $query->where(['device_id' => $device_id]);
}

if (request::has('user')) {
    $user_id = request::int('user');
    $user = User::get($user_id);
    if (empty($user)) {
        Util::itoast('找不到这个用户！', '', 'error');
    }
    $tpl_data['user'] = $user->profile();
    $query->where(['user_id' => $user_id]);
}

$total = $query->count();
$list = [];

if ($total > 0) {
    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

    $tpl_data['pager'] = We7::pagination($total, $page, $page_size);

    $query->page($page, $page_size);
    $query->orderBy('id DESC');

    /** @var account_queryModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $data = [
            'id' => $entry->getId(),
            'request_id' => $entry->getRequestId(),
            'createtime_formatted' => date('Y-m-d H:i:s', $entry->getCreatetime()),
        ];

        $acc = $entry->getAccount();
        if (!empty($acc)) {
            $data['account'] = $acc->profile();
        }

        $user = $entry->getUser();
        if ($user) {
            $data['user'] = $user->profile();
        }

        $device = $entry->getDevice();
        if ($device) {
            $data['device'] = $device->profile();
        }

        $data['request'] = $entry->getRequest();
        $data['result'] = $entry->getResult();
        $data['cb'] = $entry->getExtraData('cb');
        $last_cb = $entry->getExtraData('last_cb');
        if ($last_cb) {
            $data['last_cb'] = count($last_cb);
            if (empty($data['cb']['order_uid'])) {
                foreach((array)$last_cb as $cb) {
                    if (!empty($cb['order_uid'])) {
                        $data['cb']['order_uid'] = $cb['order_uid'];
                        break;
                    }
                }
            }
            if (empty($data['cb']['serial'])) {
                foreach((array)$last_cb as $cb) {
                    if (!empty($cb['serial'])) {
                        $data['cb']['serial'] = $cb['serial'];
                        break;
                    }
                }
            }
        }
        if ($data['cb']['serial']) {
            $log = BalanceLog::findOne(['s2' => $data['cb']['serial']]);
            if ($log) {
                $data['balance'] = $log->getExtraData('bonus', 0);
            }
        }

        $data['createtime'] = $entry->getCreatetime();

        $list[] = $data;
    }
}

$tpl_data['list'] = $list;

app()->showTemplate('web/account/log', $tpl_data);