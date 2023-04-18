<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\api\wx;

use zovye\Helper;
use zovye\model\userModelObj;
use zovye\Principal;
use zovye\Request;
use zovye\User;
use function zovye\err;

class promoter
{
    public static function getPromoterList(): array
    {
        $agent = common::getAgent();

        $keeper_id = Request::int('keeperid');
        $keeper = \zovye\Keeper::get($keeper_id);
        if (empty($keeper) || $keeper->getAgentId() != $agent->getId()) {
            return err('找不到这个运营人员！');
        }

        $query = Principal::promoter(['superior_id' => $keeper->getId()]);

        $total = $query->count();

        $page = max(1, Request::int('page'));
        $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);
    
        $query->page($page, $page_size);

        $list = [];
        /** @var userModelObj $promoter */
        foreach ($query->findAll() as $promoter) {
            $data = $promoter->profile(false);
            $data['commission_total'] = $promoter->getCommissionBalance()->total();
            $list[] = $data;
        }

        return [
            'list' => $list,
            'total' => $total,
            'totalpage' => ceil($total / $page_size),
        ];
    }

    public static function getPromoterLogs(): array
    {
        $agent = common::getAgent();

        $user = User::get(Request::int('id'));
        if (empty($user)) {
            return err('推广员不存在！');
        }

        if (!$user->isPromoter()) {
            return err('用户不是推广员！');
        }

        $keeper_id = $user->getSuperiorId();

        $keeper = \zovye\Keeper::get($keeper_id);
        if (empty($keeper) || $keeper->getAgentId() != $agent->getId()) {
            return err('没有权限查看！');
        }

        return Helper::getUserCommissionLogs($user);
    }

    public static function getPromoterConfig(): array
    {
        $agent = common::getAgent();

        $keeper_id = Request::int('keeperid');
        $keeper = \zovye\Keeper::get($keeper_id);
        if (empty($keeper) || $keeper->getAgentId() != $agent->getId()) {
            return err('找不到这个运营人员！');
        }

        return $keeper->settings('promoter.commission', []);
    }

    public static function updatePromoterConfig(): array
    {
        $agent = common::getAgent();

        $keeper_id = Request::int('keeperid');
        $keeper = \zovye\Keeper::get($keeper_id);
        if (empty($keeper) || $keeper->getAgentId() != $agent->getId()) {
            return err('找不到这个运营人员！');
        }

        $commission = Request::str('commission', '', true);

        $config = [];

        if (substr($commission, -1) == '%') {
            $commission = rtrim($commission, '%');
            $percent = max(0, min(100, intval($commission)));
            $config['percent'] = $percent;
        } else {
            $commission = rtrim($commission, '*');
            $fixed = max(0, intval($commission));
            $config['fixed'] = $fixed;
        }

        if ($keeper->updateSettings('promoter.commission', $config)) {
            return err('保存配置失败！');
        }

        return $config;
    }

    public static function keeperGetPromoterList(): array
    {
        $keeper = keeper::getKeeper();

        $query = Principal::promoter(['superior_id' => $keeper->getId()]);

        $total = $query->count();

        $page = max(1, Request::int('page'));
        $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);
    
        $query->page($page, $page_size);

        $list = [];
        /** @var userModelObj $promoter */
        foreach ($query->findAll() as $promoter) {
            $data = $promoter->profile(false);
            $data['commission_total'] = $promoter->getCommissionBalance()->total();
            $list[] = $data;
        }

        return [
            'list' => $list,
            'total' => $total,
            'totalpage' => ceil($total / $page_size),
        ];
    }

    public static function KeeperGetPromoterLogs(): array
    {
        $keeper = keeper::getKeeper();

        $user = User::get(Request::int('id'));
        if (empty($user)) {
            return err('推广员不存在！');
        }

        if (!$user->isPromoter()) {
            return err('用户不是推广员！');
        }

        if ($keeper->getId() != $user->getSuperiorId()) {
            return err('没有权限查看！');
        }

        return Helper::getUserCommissionLogs($user);
    }
}