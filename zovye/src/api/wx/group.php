<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wx;

use zovye\Device;
use zovye\model\device_groupsModelObj;
use zovye\request;
use zovye\State;
use function zovye\error;

class group
{
    /**
     * 设备分组列表.
     *
     * @return array
     */
    public static function list(): array
    {
        $user = agent::getAgent();

        $page = max(1, request::int('page'));
        $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

        $query = \zovye\Group::query();

        $keyword = request::trim('keyword');
        if (!empty($keyword)) {
            $query->where(['title LIKE' => "%$keyword%"]);
        }

        $guid = request::str('guid');
        if (!empty($guid)) {
            $res = agent::getUserByGUID($guid);
            if (empty($res)) {
                return error(State::ERROR, '找不到这个用户！');
            } else {
                $query->where(['agent_id' => $res->getAgentId()]);
            }
        } else {
            //代理商分组
            $query->where(['agent_id' => $user->getAgentId()]);
        }

        $total = $query->count();

        $result = [
            'page' => $page,
            'pagesize' => $page_size,
            'total' => $total,
            'totalpage' => ceil($total / $page_size),
            'list' => [],
        ];

        if ($total > 0) {
            $query->page($page, $page_size);
            $query->orderBy('id desc');

            /** @var device_groupsModelObj $entry */
            foreach ($query->findAll() as $entry) {
                $data = [
                    'id' => $entry->getId(),
                    'title' => $entry->getTitle(),
                    'clr' => $entry->getClr(),
                    'agentId' => $entry->getAgentId(),
                    'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
                ];

                $cond = ['group_id' => $entry->getId()];
                if ($guid) {
                    $cond['agent_id'] = $user->getAgentId();
                }

                $data['count'] = Device::query($cond)->count();

                $result['list'][] = $data;
            }
        }

        return $result;
    }

    /**
     * 设备分组详情.
     *
     * @return array
     */
    public static function detail(): array
    {
        common::checkCurrentUserPrivileges('F_sp');

        $user = agent::getAgent();

        //分组id
        $group_id = request::int('id');

        /** @var device_groupsModelObj $one */
        $one = \zovye\Group::findOne([
            'id' => $group_id,
            'agent_id' => $user->getAgentId(),
        ]);

        if (empty($one)) {
            return error(State::ERROR, '找不到这个分组！');
        }
        return [
            'id' => $one->getId(),
            'title' => $one->getTitle(),
            'clr' => $one->getClr(),
            'createtime' => date('Y-m-d H:i:s', $one->getCreatetime()),
        ];
    }

    /**
     * 保存设备分组.
     *
     * @return array
     */
    public static function create(): array
    {
        $title = request::trim('title');
        $clr = request::trim('clr');

        if (empty($title)) {
            return error(State::FAIL, '对不起，请填写分组名称！');
        }

        $agent = agent::getAgent();

        $data = [
            'title' => $title,
            'clr' => $clr,
            'agent_id' => $agent->getAgentId(),
            'createtime' => time(),
        ];

        $app = \zovye\Group::create($data);
        if ($app) {
            return ['msg' => '创建成功'];
        }

        return error(State::FAIL, '创建失败！');
    }

    /**
     * 更新设备分组.
     *
     * @return array
     */
    public static function update(): array
    {
        $title = request::trim('title');
        $clr = request::trim('clr');

        $group_id = request::int('id');

        if (empty($title)) {
            return error(State::ERROR, '对不起，请填写分组名称！');
        }

        $user = agent::getAgent();

        /** @var device_groupsModelObj $one */
        $one = \zovye\Group::findOne([
            'id' => $group_id,
            'agent_id' => $user->getAgentId(),
        ]);
        if (empty($one)) {
            return error(State::ERROR, '找不到这个分组！');
        }

        $one->setTitle($title);
        $one->setClr($clr);

        if ($one->save()) {
            return ['msg' => '修改成功！'];
        }

        return error(State::ERROR, '修改失败！');
    }

    /**
     * 删除广告.
     *
     * @return array
     */
    public static function delete(): array
    {
        common::checkCurrentUserPrivileges('F_gg');

        $user = agent::getAgent();

        $group_id = request::trim('id');

        /** @var device_groupsModelObj $one */
        $one = \zovye\Group::findOne([
            'id' => $group_id,
            'agent_id' => $user->getAgentId(),
        ]);
        if (empty($one)) {
            return error(State::ERROR, '找不到这个分组！');
        }

        if ($one->destroy()) {
            return ['msg' => "删除成功！"];
        }

        return error(State::ERROR, '删除失败！');
    }

    public static function getDeviceGroup($id): array
    {
        static $cache = [];

        if (empty($cache[$id])) {
            /** @var device_groupsModelObj $res */
            $res = \zovye\Group::get($id);
            if ($res) {
                $cache[$id] = [
                    'id' => $res->getId(),
                    'agent_id' => $res->getAgentId(),
                    'title' => $res->getTitle(),
                    'clr' => $res->getClr(),
                ];
            }
        }

        return $cache[$id] ?? ['id' => $id, 'title' => '', 'clr' => ''];
    }
}
