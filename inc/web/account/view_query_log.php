<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\Account;
use zovye\domain\BalanceLog;
use zovye\domain\Device;
use zovye\domain\User;
use zovye\model\account_queryModelObj;

$tpl_data = [];

$query = Account::logQuery();

if (Request::has('id')) {
    $id = Request::int('id');
    $account = Account::get($id);
    if (empty($account)) {
        Response::toast('找不到这个任务！', '', 'error');
    }

    $tpl_data['account'] = $account->profile();
    $query = $query->where(['account_id' => $account->getId()]);
}

if (Request::has('device')) {
    $device_id = Request::int('device');
    $device = Device::get($device_id);
    if (empty($device)) {
        Response::toast('找不到这个设备！', '', 'error');
    }

    $tpl_data['device'] = $device->profile();
    $query->where(['device_id' => $device_id]);
}

if (Request::has('user')) {
    $user_id = Request::int('user');
    $user = User::get($user_id);
    if (empty($user)) {
        Response::toast('找不到这个用户！', '', 'error');
    }

    $tpl_data['user'] = $user->profile();
    $query->where(['user_id' => $user_id]);
}

$total = $query->count();
$list = [];

if ($total > 0) {
    $page = max(1, Request::int('page'));
    $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

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
                foreach ((array)$last_cb as $cb) {
                    if (!empty($cb['order_uid'])) {
                        $data['cb']['order_uid'] = $cb['order_uid'];
                        break;
                    }
                }
            }
            if (empty($data['cb']['serial'])) {
                foreach ((array)$last_cb as $cb) {
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

Response::showTemplate('web/account/log', $tpl_data);