<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\Team;
use zovye\domain\User;
use zovye\model\userModelObj;
use zovye\util\Util;

$ids = Request::isset('id') ? [Request::int('id')] : Request::array('ids');

$result = [];

$commission_enabled = App::isCommissionEnabled();
$balance_enabled = App::isBalanceEnabled();
$team_enabled = App::isTeamEnabled();

$query = User::query(['id' => $ids]);

/** @var userModelObj $user */
foreach ($query->findAll() as $user) {
    if ($user) {
        $data = [
            'id' => $user->getId(),
        ];

        if (Util::isSysLoadAverageOk()) {
            $data['free'] = $user->getFreeTotal();
            $data['pay'] = $user->getPayTotal();
        }

        if ($commission_enabled) {
            $total = $user->getCommissionBalance()->total();
            $data['commission_balance'] = $total;
            $data['commission_balance_formatted'] = number_format($total / 100, 2);
        }

        if ($balance_enabled) {
            $data['balance'] = $user->getBalance()->total();
        }

        if ($team_enabled) {
            $team = Team::getFor($user);
            if ($team) {
                $data['team_members'] = Team::findAllMember($team)->count();
            }
            $data['is_member'] = Team::isMember($user);
        }

        $result[] = $data;
    }
}

JSON::success($result);