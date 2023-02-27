<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$result_msg = function ($msg, $status) {
    if (request::is_ajax()) {
        if ($status) {
            JSON::success($msg);
        } else {
            JSON::fail($msg);
        }
    } else {
        Util::itoast($msg, $status ? 'success' : 'error');
    }
};

$agent = Agent::get(request::int('agentid'));
if (empty($agent)) {
    $result_msg('找不到这个代理商！', false);
}

if (empty($agent->isCommissionEnabled())) {
    $result_msg('代理商没有加入佣金系统！', false);
}

$tpl_data = [
    'op' => request::op(),
    'agent' => $agent,
];

$back_url = $this->createWebUrl('agent', array('id' => $agent->getId(), 'op' => 'agent_commission'));
$tpl_data['back_url'] = $back_url;

$fn = request::trim('fn');
if ($fn == 'adduser' || $fn == 'edituser') {
    $from = request::trim('from');
    if ($from == 'free') {
        $user = User::get(request::int('id'));
        if (empty($user)) {
            $result_msg('找不到这个用户！', false);
        }

        $tpl_data['user'] = $user;

        if ($fn == 'adduser') {
            $tpl_data['order_type'] = [
                'f' => 1,
                'b' => 1,
                'p' => 1,
            ];
            $tpl_data['mode'] = request::str('mode', 'percent');
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
            if ($data['percent']) {
                $tpl_data['mode_type'] = 'percent';
                $tpl_data['val'] = number_format($data['percent'] / 100, 2);
            } else {
                $tpl_data['mode_type'] = 'amount';
                $tpl_data['val'] = number_format($data['amount'] / 100, 2);
            }
        }
        app()->showTemplate('web/agent/free_edit_user', $tpl_data);
    } elseif ($from == 'mixed') {
        if ($fn == 'adduser') {
            $user = User::get(request::int('id'));
        } else {
            $entry = GSP::findOne(['agent_id' => $agent->getId(), 'id' => request::int('id')]);
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

        app()->showTemplate('web/agent/mixed_edit_user', $tpl_data);
    } else {
        Util::resultAlert('不正确的操作！', 'error');
    }
} elseif ($fn == 'saveuser') {

    $user = User::get(request::int('id'));
    if (empty($user)) {
        Util::message('找不到这个用户！', $back_url, 'error');
    }

    $from = request::trim('from', 'free');
    if ($from == 'free') {
        $order_type = [
            'f' => request::bool('freeOrder') ? 1 : 0,
            'b' => request::bool('balanceOrder') ? 1 : 0,
            'p' => request::bool('payOrder') ? 1 : 0,
        ];

        $key_name = "agentData.gsp.users.{$user->getOpenid()}";

        $agent->updateSettings("$key_name.order", $order_type);

        $mode_type = request::trim('mode_type', 'percent');
        if ($mode_type == 'percent') {
            $percent = min(10000, max(0, request::float('val', 0, 2) * 100));
            if ($agent->settings($key_name)) {
                $agent->updateSettings("$key_name.percent", $percent);
            } else {
                $agent->updateSettings(
                    $key_name,
                    [
                        'percent' => $percent,
                        'createtime' => time(),
                    ]
                );
            }
            $agent->updateSettings("$key_name.amount", []);
        } else {
            $amount = request::float('val', 0, 2) * 100;
            if ($agent->settings($key_name)) {
                $agent->updateSettings("$key_name.amount", $amount);
            } else {
                $agent->updateSettings(
                    $key_name,
                    [
                        'amount' => $amount,
                        'createtime' => time(),
                    ]
                );
            }
            $agent->updateSettings("$key_name.percent", []);
        }

        $agent->updateSettings('agentData.gsp.enabled', 1);
        $agent->updateSettings('agentData.gsp.mode', 'free');
    } elseif ($from == 'mixed') {
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
            if (request::isset($v['type'])) {
                if (request::bool($v['type'])) {
                    $entries[$key] = [
                        'val_type' => 'percent',
                        'val' => min(10000, max(0, request::float($v['val'], 0, 2) * 100)),
                    ];
                } else {
                    $entries[$key] = [
                        'val_type' => 'amount',
                        'val' => request::float($v['val'], 0, 2) * 100,
                    ];
                }
            }
        }
        foreach ($entries as $order_type => $entry) {
            $data['val_type'] = $entry['val_type'];
            $data['val'] = $entry['val'];
            $data['order_types'] = $order_type;
            GSP::update(['agent_id' => $agent->getId(), 'uid' => $user->getOpenid(), 'order_types' => $order_type],
                $data);
        }
        $agent->updateSettings('agentData.gsp.enabled', 1);
        $agent->updateSettings('agentData.gsp.mode', 'mixed');
    }

    Util::message('保存成功！', $back_url, 'success');
} elseif ($fn == 'removeuser') {
    $from = request::trim("from");
    if ($from == 'free') {
        $user = User::get(request::int('id'));
        if (empty($user)) {
            $result_msg('找不到这个用户', 'error');
        }
        if ($agent->settings("agentData.gsp.users.{$user->getOpenid()}")) {
            if ($agent->removeSettings('agentData.gsp.users', $user->getOpenid())) {
                $result_msg('删除成功！', true);
            }
        }
    } elseif ($from == 'mixed') {
        $entry = GSP::findOne(['agent_id' => $agent->getId(), 'id' => request::int('id')]);
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

    $tpl_data['level'] = request::trim('level');

    $content = app()->fetchTemplate('web/agent/gsp_add_role', $tpl_data);
    JSON::success([
        'title' => '设置角色',
        'content' => $content,
    ]);
} elseif ($fn == 'get_role') {
    $level = request::str('level');
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
    $level = request::str('level');
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
        if (request::isset($v['type'])) {
            if (request::bool($v['type'])) {
                $entries[$key] = [
                    'val_type' => 'percent',
                    'val' => min(10000, max(0, request::float($v['val'], 0, 2) * 100)),
                ];
            } else {
                $entries[$key] = [
                    'val_type' => 'amount',
                    'val' => request::float($v['val'], 0, 2) * 100,
                ];
            }
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
    $user = User::get(request::trim('openid'), true);
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