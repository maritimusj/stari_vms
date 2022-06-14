<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$op = request::op('default');
if ($op == 'default') {
        //分组表
        $query = Group::query(Group::CHARGING);

        $page = max(1, request::int('page'));
        $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

        if (request::isset('agent_id')) {
            $agent_id = request::int('agent_id');
            if ($agent_id > 0) {
                $agent = Agent::get($agent_id);
                if (empty($agent)) {
                    Util::itoast('找不到这个代理商！', '', 'error');
                }
                $query->where(['agent_id' => $agent_id]);
            } else {
                $query->where(['agent_id' => 0]);
            }
        }

        $total = $query->count();

        //列表数据
        $query->page($page, $page_size);

        $list = [];
        /** @var device_groupsModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $data = $entry->format();
            $agent = $entry->getAgent();
            if ($agent) {
                $data['agent'] = $agent->profile();
            }
            $list[] = $data;
        }

        $tpl_data['list'] = $list;
        $tpl_data['pager'] = We7::pagination($total, $page, $page_size);
        $tpl_data['agentId'] = $agent_id ?? null;
        $tpl_data['navs'] = $navs;

        app()->showTemplate('web/charging/default', $tpl_data);

} elseif ($op == 'add' || $op == 'edit') {

    $tpl_data = [
        'op' => $op,
        'lbs_key' => settings('user.location.appkey', DEFAULT_LBS_KEY),
    ];

    if ($op == 'edit') {
        $id = request::int('id');
        $group = Group::get($id, Group::CHARGING);
        if (!$group) {
            Util::itoast('找不到这个分组！', Util::url('charging'), 'error');
        }
        $agent = $group->getAgent();
        if ($agent) {
            $tpl_data['agent'] = $agent->profile();
        }

        $tpl_data['id'] = $group->getId();
        $tpl_data['group'] = $group->format();
    }

    app()->showTemplate('web/charging/edit', $tpl_data);

} elseif ($op == 'save') {

    $agent_id = request::int('agent_id');
    if ($agent_id) {
        $agent = Agent::get($agent_id);
        if (!$agent) {
            Util::itoast('找不到这个代理商！', '', 'error');
        }
    }

    $id = request::int('id');
    if ($id) {
        $group = Group::get($id, Group::CHARGING);
        if (empty($group)) {
            Util::itoast('找不到指定的分组！', '', 'error');
        }
        if (isset($agent)) {
            $group->setAgentId($agent->getId());
        }

        $group->setAddress(request::trim('address'));
        $group->setTitle(request::trim('title'));
        $group->setDescription(request::trim('description'));
        $group->setLoc([
            'lng' => request::float('lng'),
            'lat' => request::float('lat'),
        ]);

        if ($group->save()) {
            Util::itoast('保存成功！', Util::url('charging'), 'success');
        }

        Util::itoast('保存失败！', Util::url('charging'), 'error');

    } else {
        $data = [
            'agent_id' => isset($agent) ? $agent->getId() : 0,
            'type_id' => Group::CHARGING,
            'title' => request::trim('title'),
            'extra' => [
                'name' => Util::random(16),
                'description' => request::trim('description'),
                'address' => request::trim('address'),
                'lat' => request::float('lat'),
                'lng' => request::float('lng'),
            ],
        ];

        $group = Group::create($data);
        if ($group) {
            Util::itoast('创建成功！', Util::url('charging'), 'success');
        }

        Util::itoast('创建分组失败！', Util::url('charging'), 'error');
    }

} elseif ($op == 'remove') {

    $id = request::int('id');

    $result = Util::transactionDo(function() use ($id) {
        $group = Group::get($id, Group::CHARGING);
        if (empty($group)) {
            return err('找不到指定的分组！');
        }

        if ($group->destroy()) {
            $result = Device::query(['group_id' => $id])->findAll();

            /** @var deviceModelObj $entry */
            foreach ($result as $entry) {
                $entry->setGroupId(0);
            }
        }
        return true;   
    });

    if (is_error($result)) {
        Util::itoast($result['message'], Util::url('charging'), 'error');
    }

    Util::itoast('已删除！', Util::url('charging'), 'success');
}