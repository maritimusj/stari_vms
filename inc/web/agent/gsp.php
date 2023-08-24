<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$result_msg = function ($msg, $status) {
    if (Request::is_ajax()) {
        if ($status) {
            JSON::success($msg);
        } else {
            JSON::fail($msg);
        }
    } else {
        Response::toast($msg, '', $status ? 'success' : 'error');
    }
};

$agent = Agent::get(Request::int('agentid'));
if (empty($agent)) {
    $result_msg('找不到这个代理商！', false);
}

if (empty($agent->isCommissionEnabled())) {
    $result_msg('代理商没有加入佣金系统！', false);
}

$tpl_data = [
    'op' => Request::op(),
    'agent' => $agent,
];

$back_url = Util::url('agent', array('id' => $agent->getId(), 'op' => 'agent_commission'));
$tpl_data['back_url'] = $back_url;

$fn = Request::trim('fn');
if ($fn == 'adduser' || $fn == 'edituser') {

    $from = Request::trim('from');
    if ($from == GSP::FREE) {
        $user = User::get(Request::int('id'));
        if (empty($user)) {
            $result_msg('找不到这个用户！', false);
        }

        $tpl_data['fn'] = $fn;
        $tpl_data['user'] = $user;

        if ($fn == 'adduser') {
            $tpl_data['order_type'] = [
                'f' => 1,
                'b' => 1,
                'p' => 1,
            ];
            $tpl_data['mode'] = Request::str('mode', GSP::PERCENT);
            if ($agent->settings("agentData.gsp.users.{$user->getOpenid()}")) {
                $result_msg('用户已经是代理商的佣金分享用户！', false);
            }
        } elseif ($fn == 'edituser') {
            $data = $agent->settings("agentData.gsp.users.{$user->getOpenid()}", []);
            $tpl_data['order_type'] = is_array($data['order']) ? $data['order'] : [
                'f' => 1,
                'b' => 1,
                'p' => 1,
            ];

            if ($data[GSP::PERCENT]) {
                $mode_type = GSP::PERCENT;
            } elseif ($data[GSP::PERCENT_PER_GOODS]) {
                $mode_type = GSP::PERCENT_PER_GOODS;
            } elseif ($data[GSP::AMOUNT]) {
                $mode_type = GSP::AMOUNT;
            } elseif ($data[GSP::AMOUNT_PER_GOODS]) {
                $mode_type = GSP::AMOUNT_PER_GOODS;
            }

            $tpl_data['mode_type'] = $mode_type;
            $tpl_data['val'] = number_format($data[$mode_type] / 100, 2);
        }

        $title = $fn == 'adduser' ? '增加佣金分享用户' : '编辑佣金分享用户';
        Response::templateJSON('web/agent/free_edit_user', $title, $tpl_data);

    } elseif ($from == GSP::MIXED) {

        if ($fn == 'adduser') {
            $user = User::get(Request::int('id'));
        } else {
            $entry = GSP::findOne([
                'agent_id' => $agent->getId(),
                'id' => Request::int('id'),
            ]);
            if (empty($entry)) {
                $result_msg('找不到这个设置！', false);
            }

            if ($entry->isRole()) {
                $result_msg('不能编辑', false);
            }

            $user = User::get($entry->getUid(), true);
        }

        if (empty($user)) {
            $result_msg('找不到这个用户！', false);
        }

        $tpl_data['user'] = $user;

        $title = $fn == 'adduser' ? '增加佣金分享用户' : '编辑佣金分享用户';
        Response::templateJSON('web/agent/mixed_edit_user', $title, $tpl_data);
    } else {
        Response::alert('不正确的操作！', 'error');
    }

} elseif ($fn == 'saveuser') {

    $user = User::get(Request::int('id'));
    if (empty($user)) {
        Response::message('找不到这个用户！', $back_url, 'error');
    }

    $from = Request::trim('from', GSP::FREE);
    if ($from == GSP::FREE) {
        $gsp_data = [
            'order' => [
                'f' => Request::bool('freeOrder') ? 1 : 0,
                'b' => Request::bool('balanceOrder') ? 1 : 0,
                'p' => Request::bool('payOrder') ? 1 : 0,
            ],
            'createtime' => $agent->settings("agentData.gsp.users.{$user->getOpenid()}.createtime", time()),
        ];

        $mode_type = Request::trim('mode_type', GSP::PERCENT);
        if ($mode_type == GSP::PERCENT || $mode_type == GSP::PERCENT_PER_GOODS) {
            $gsp_data[$mode_type] = min(10000, max(0, Request::float('val', 0, 2) * 100));
        } else {
            $gsp_data[$mode_type] = max(0, Request::float('val', 0, 2) * 100);
        }

        $agent->updateSettings("agentData.gsp.users.{$user->getOpenid()}", $gsp_data);
        $agent->updateSettings('agentData.gsp.enabled', 1);
        $agent->updateSettings('agentData.gsp.mode', GSP::FREE);

    } elseif ($from == GSP::MIXED) {
        $data = [
            'agent_id' => $agent->getId(),
            'uid' => $user->getOpenid(),
        ];

        $entries = [];
        foreach ([
                     'f' => ['type' => 'freeOrderType', 'val' => 'freeOrderVal'],
                     'b' => ['type' => 'balanceOrderType', 'val' => 'balanceOrderVal'],
                     'p' => ['type' => 'payOrderType', 'val' => 'payOrderVal'],
                 ] as $key => $v) {
            if (Request::has($v['type'])) {
                $type = Request::str($v['type']);
                if (in_array($type, [GSP::PERCENT, GSP::PERCENT_PER_GOODS])) {
                    $val = min(10000, max(0, Request::float($v['val'], 0, 2) * 100));
                } else {
                    $val = max(0, Request::float($v['val'], 0, 2) * 100);
                }
                $entries[$key] = [
                    'val_type' => $type,
                    'val' => $val,
                ];
            }
        }

        foreach ($entries as $order_type => $entry) {

            $data['val_type'] = $entry['val_type'];
            $data['val'] = $entry['val'];
            $data['order_types'] = $order_type;

            GSP::update([
                'agent_id' => $agent->getId(),
                'uid' => $user->getOpenid(),
                'order_types' => $order_type,
            ], $data);
        }

        $agent->updateSettings('agentData.gsp.enabled', 1);
        $agent->updateSettings('agentData.gsp.mode', GSP::MIXED);
    }

    $result_msg('保存成功！', 'success');

} elseif ($fn == 'removeuser') {

    $from = Request::trim("from");
    if ($from == GSP::FREE) {
        $user = User::get(Request::int('id'));
        if (empty($user)) {
            $result_msg('找不到这个用户', 'error');
        }

        if ($agent->settings("agentData.gsp.users.{$user->getOpenid()}")) {
            if ($agent->removeSettings('agentData.gsp.users', $user->getOpenid())) {
                $result_msg('删除成功！', true);
            }
        }

    } elseif ($from == GSP::MIXED) {

        $entry = GSP::findOne(['agent_id' => $agent->getId(), 'id' => Request::int('id')]);
        if (empty($entry)) {
            $result_msg('找不到这个设置', 'error');
        }

        if (!$entry->isRole()) {
            $user = User::get($entry->getUid(), true);
            if (empty($user)) {
                $result_msg('找不到这个用户', 'error');
            }

            $query = GSP::query(['agent_id' => $agent->getId(), 'uid' => $user->getOpenid()]);
            foreach ($query->findAll() as $entry) {
                $entry->destroy();
            }
        }

        $result_msg('删除成功！', true);
    }

    $result_msg('删除失败！', false);

} elseif ($fn == 'add_role') {

    $tpl_data['agentId'] = $agent->getId();

    $tpl_data['level'] = Request::trim('level');

    Response::templateJSON('web/agent/gsp_add_role', '设置角色', $tpl_data);

} elseif ($fn == 'get_role') {

    $level = Request::str('level');
    if (!in_array($level, [GSP::LEVEL1, GSP::LEVEL2, GSP::LEVEL3])) {
        JSON::fail('角色不正确！');
    }

    $result = [];
    $f = GSP::findOne(['agent_id' => $agent->getId(), 'uid' => $level, 'order_types' => 'f']);
    if ($f) {
        $result['f'] = [
            'val_type' => $f->getValType(),
            'val' => number_format($f->getVal() / 100, 2),
        ];
    }

    $p = GSP::findOne(['agent_id' => $agent->getId(), 'uid' => $level, 'order_types' => 'p']);
    if ($p) {
        $result['p'] = [
            'val_type' => $p->getValType(),
            'val' => number_format($p->getVal() / 100, 2),
        ];
    }
    JSON::success($result);

} elseif ($fn == 'save_role') {

    $level = Request::str('level');
    if (!in_array($level, [GSP::LEVEL1, GSP::LEVEL2, GSP::LEVEL3])) {
        JSON::fail('角色不正确！');
    }
    $data = [
        'agent_id' => $agent->getId(),
        'uid' => $level,
    ];
    $entries = [];
    foreach ([
                 'f' => ['type' => 'freeOrderType', 'val' => 'freeOrderVal'],
                 'b' => ['type' => 'balanceOrderType', 'val' => 'balanceOrderVal'],
                 'p' => ['type' => 'payOrderType', 'val' => 'payOrderVal'],
             ] as $key => $v) {

        if (Request::has($v['type'])) {
            $type = Request::str($v['type']);
            if (in_array($type, [GSP::PERCENT, GSP::PERCENT_PER_GOODS])) {
                $val = min(10000, max(0, Request::float($v['val'], 0, 2) * 100));
            } else {
                $val = max(0, Request::float($v['val'], 0, 2) * 100);
            }
            $entries[$key] = [
                'val_type' => $type,
                'val' => $val,
            ];
        }
    }

    foreach ($entries as $order_type => $entry) {
        $data['val_type'] = $entry['val_type'];
        $data['val'] = $entry['val'];
        $data['order_types'] = $order_type;
        GSP::update(['agent_id' => $agent->getId(), 'uid' => $level, 'order_types' => $order_type], $data);
    }

    $agent->updateSettings('agentData.gsp.enabled', 1);
    $agent->updateSettings('agentData.gsp.mode', 'mixed');

    JSON::success('成功！');

} elseif ($fn == 'get_data') {

    $user = User::get(Request::trim('openid'), true);
    if (empty($user)) {
        JSON::fail('找不到这个用户！');
    }
    $result = [];
    $f = GSP::findOne(['agent_id' => $agent->getId(), 'uid' => $user->getOpenid(), 'order_types' => 'f']);
    if ($f) {
        $result['f'] = [
            'val_type' => $f->getValType(),
            'val' => number_format($f->getVal() / 100, 2),
        ];
    }
    $p = GSP::findOne(['agent_id' => $agent->getId(), 'uid' => $user->getOpenid(), 'order_types' => 'p']);
    if ($p) {
        $result['p'] = [
            'val_type' => $p->getValType(),
            'val' => number_format($p->getVal() / 100, 2),
        ];
    }

    JSON::success($result);
}