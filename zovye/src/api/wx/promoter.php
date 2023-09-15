<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\api\wx;

use zovye\api\wx\keeper as WxKeeper;
use zovye\domain\Keeper;
use zovye\domain\Principal;
use zovye\domain\User;
use zovye\model\userModelObj;
use zovye\Request;
use zovye\util\Helper;
use function zovye\err;

class promoter
{
    public static function getPromoterList(): array
    {
        $agent = common::getAgent();

        $keeper_id = Request::int('id');
        $keeper = Keeper::get($keeper_id);
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

    public static function removePromoter(): array
    {
        $agent = common::getAgent();

        $user = User::get(Request::int('id'));
        if (empty($user)) {
            return err('推广员不存在！');
        }

        $keeper = Keeper::get($user->getSuperiorId());
        if (empty($keeper) || $keeper->getAgentId() != $agent->getId()) {
            return err('没有权限删除！');
        }

        if ($user->removePrincipal(Principal::Promoter)) {
            return ['msg' => '删除成功！'];
        }

        return err('删除失败！');
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

        $keeper = Keeper::get($keeper_id);
        if (empty($keeper) || $keeper->getAgentId() != $agent->getId()) {
            return err('没有权限查看！');
        }

        return Helper::getUserCommissionLogs($user);
    }

    public static function getPromoterConfig(): array
    {
        $agent = common::getAgent();

        $keeper_id = Request::int('id');
        $keeper = Keeper::get($keeper_id);
        if (empty($keeper) || $keeper->getAgentId() != $agent->getId()) {
            return err('找不到这个运营人员！');
        }

        $config = $keeper->settings('promoter.commission', []);
        if ($config['percent']) {
            $config['percent'] = intval($config['percent']) / 100;
        } elseif ($config['fixed']) {
            $config['fixed'] = intval($config['fixed']) / 100;
        }

        return $config;
    }

    public static function updatePromoterConfig(): array
    {
        $agent = common::getAgent();

        $keeper_id = Request::int('id');
        $keeper = Keeper::get($keeper_id);
        if (empty($keeper) || $keeper->getAgentId() != $agent->getId()) {
            return err('找不到这个运营人员！');
        }

        $val = Request::str('val', '', true);

        $config = [];

        if (substr($val, -1) == '%') {
            $val = rtrim($val, '%');
            $percent = max(0, min(10000, intval(round(floatval($val) * 100))));
            $config['percent'] = $percent;
        } else {
            $val = rtrim($val, '*');
            $fixed = max(0, intval(round(floatval($val) * 100)));
            $config['fixed'] = $fixed;
        }

        if (!$keeper->updateSettings('promoter.commission', $config)) {
            return err('保存配置失败！');
        }

        if ($config['percent']) {
            $config['percent'] = intval($config['percent']) / 100;
        } elseif ($config['fixed']) {
            $config['fixed'] = intval($config['fixed']) / 100;
        }

        return $config;
    }

    public static function keeperGetPromoterList(): array
    {
        $keeper = WxKeeper::getKeeper();

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

    public static function keeperGetPromoterLogs(): array
    {
        $keeper = WxKeeper::getKeeper();

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

    public static function keeperRemovePromoter(): array
    {
        $keeper = WxKeeper::getKeeper();

        $user = User::get(Request::int('id'));
        if (empty($user)) {
            return err('推广员不存在！');
        }

        if ($keeper->getId() != $user->getSuperiorId()) {
            return err('没有权限删除！');
        }

        if ($user->removePrincipal(Principal::Promoter)) {
            return ['msg' => '删除成功！'];
        }

        return err('删除失败！');
    }
}