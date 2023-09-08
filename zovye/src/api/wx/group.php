<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wx;

use zovye\Device;
use zovye\Group as ZovyeGroup;
use zovye\model\device_groupsModelObj;
use zovye\Request;
use function zovye\err;

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

        $page = max(1, Request::int('page'));
        $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

        $query = ZovyeGroup::query(ZovyeGroup::NORMAL);

        $keyword = Request::trim('keyword');
        if (!empty($keyword)) {
            $query->where(['title LIKE' => "%$keyword%"]);
        }

        $guid = Request::str('guid');
        if (!empty($guid)) {
            $res = agent::getUserByGUID($guid);
            if (empty($res)) {
                return err('找不到这个用户！');
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
            $query->orderBy('id DESC');

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
        $group_id = Request::int('id');

        /** @var device_groupsModelObj $one */
        $one = ZovyeGroup::findOne([
            'id' => $group_id,
            'agent_id' => $user->getAgentId(),
        ]);

        if (empty($one)) {
            return err('找不到这个分组！');
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
        $title = Request::trim('title');
        $clr = Request::trim('clr');

        if (empty($title)) {
            return err('对不起，请填写分组名称！');
        }

        $agent = agent::getAgent();

        $data = [
            'type_id' => ZovyeGroup::NORMAL,
            'title' => $title,
            'clr' => $clr,
            'agent_id' => $agent->getAgentId(),
            'createtime' => time(),
        ];

        $app = ZovyeGroup::create($data);
        if ($app) {
            return ['msg' => '创建成功'];
        }

        return err('创建失败！');
    }

    /**
     * 更新设备分组.
     *
     * @return array
     */
    public static function update(): array
    {
        $title = Request::trim('title');
        $clr = Request::trim('clr');

        $group_id = Request::int('id');

        if (empty($title)) {
            return err('对不起，请填写分组名称！');
        }

        $user = agent::getAgent();

        /** @var device_groupsModelObj $one */
        $one = ZovyeGroup::findOne([
            'id' => $group_id,
            'agent_id' => $user->getAgentId(),
        ]);

        if (empty($one)) {
            return err('找不到这个分组！');
        }

        $one->setTitle($title);
        $one->setClr($clr);

        if ($one->save()) {
            return ['msg' => '修改成功！'];
        }

        return err('修改失败！');
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

        $group_id = Request::trim('id');

        /** @var device_groupsModelObj $one */
        $one = ZovyeGroup::findOne([
            'id' => $group_id,
            'agent_id' => $user->getAgentId(),
        ]);

        if (empty($one)) {
            return err('找不到这个分组！');
        }

        if ($one->destroy()) {
            return ['msg' => "删除成功！"];
        }

        return err('删除失败！');
    }

    public static function getDeviceGroup($id, $typeid = ZovyeGroup::NORMAL): array
    {
        static $cache = [];

        if (empty($cache[$id])) {
            /** @var device_groupsModelObj $res */
            $res = ZovyeGroup::get($id, $typeid);
            if ($res) {
                $data = [
                    'id' => $res->getId(),
                    'agent_id' => $res->getAgentId(),
                    'title' => $res->getTitle(),
                    'clr' => $res->getClr(),
                ];

                if ($typeid == ZovyeGroup::CHARGING) {
                    $data['description'] = $res->getDescription();
                    $data['address'] = $res->getAddress();
                    $data['loc'] = $res->getLoc();
                }

                $cache[$id] = $data;
            }
        }

        return $cache[$id] ?? ['id' => $id, 'title' => '', 'clr' => ''];
    }
}
