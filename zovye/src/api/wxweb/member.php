<?php

namespace zovye\api\wxweb;

use zovye\api\wx\common;
use zovye\App;
use zovye\CommissionBalance;
use zovye\model\orderModelObj;
use zovye\model\team_memberModelObj;
use zovye\model\teamModelObj;
use zovye\Order;
use zovye\request;
use zovye\State;
use zovye\Team;
use zovye\User;
use zovye\Util;
use function zovye\err;
use function zovye\error;
use function zovye\is_error;

class member
{
    public static function getMemberList(): array
    {
        $user = common::getWXAppUser();
        $locker = $user->acquireLocker('team');
        try {
            if (empty($locker)) {
                return err('用户被占用，请重试！');
            }
            $team = Team::getFor($user);
            if (empty($team)) {
                $team = Team::createFor($user, '默认车队');
            }

            if (empty($team)) {
                return err('找不到车队或者创建车队失败！');
            }

            $page = max(1, request::int('page'));
            $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

            $query = Team::findAllMember($team);

            if (request::has('keyword')) {
                $keyword = request::trim('keyword');
                $query->whereOr([
                    'mobile LIKE' => '%'.$keyword.'%',
                    'remark LIKE' => '%'.$keyword.'%',
                ]);
            }

            $total = $query->count();

            $query->page($page, $page_size);

            $query->orderby('id desc');

            $list = [];

            /** @var team_memberModelObj $member */
            foreach ($query->findAll() as $member) {
                $data = $member->profile();
                $user = $member->getAssociatedUser();
                if ($user) {
                    $data['user'] = $user->profile();
                    $data['balance'] = $user->getCommissionBalance()->total();
                }
                $list[] = $data;
            }

            return [
                'total' => $total,
                'list' => $list,
            ];

        } finally {
            if ($locker) {
                $locker->release();
            }
        }
    }

    public static function checkMember(teamModelObj $team, $mobile, $member_id = 0)
    {
        if (empty($mobile) || !preg_match(REGULAR_TEL, $mobile)) {
            return err('手机号码不正确！');
        }

        $owner = $team->owner();
        if ($owner && $owner->getMobile() == $mobile) {
            return err('不能添加自己的手机号码！');
        }

        $condition = $member_id > 0 ? ['id <>' => $member_id] : [];

        $member_exists = Team::findAllMember($team, array_merge(['mobile' => $mobile], $condition))->exists();
        if ($member_exists) {
            return err('手机号码已经是车队成员！');
        }

        $u = User::findOne(['mobile' => $mobile, 'app' => User::WxAPP]);
        if ($u) {
            $member_exists = Team::findAllMember($team, array_merge(['user_id' => $u->getId()], $condition))->exists();
            if ($member_exists) {
                return err('手机号码已经加入车队！');
            }
        }

        return true;
    }

    public static function memberUserInfo(): array
    {
        $user = common::getWXAppUser();

        $mobile = request::str('mobile');

        $team = Team::getOrCreateFor($user);
        if (empty($team)) {
            $team = Team::createFor($user, '默认车队');
        }

        if (empty($team)) {
            return err('找不到车队或者创建车队失败！');
        }

        $result = self::checkMember($team, $mobile, request::int('member'));
        if (is_error($result)) {
            return $result;
        }

        $u = User::findOne(['mobile' => $mobile, 'app' => User::WxAPP]);
        if ($u) {
            return $u->profile();
        }

        return [];
    }

    public static function createMember()
    {
        $user = common::getWXAppUser();

        $team = Team::getOrCreateFor($user);
        if (empty($team)) {
            $team = Team::createFor($user, '默认车队');
        }

        if (empty($team)) {
            return err('找不到车队或者创建车队失败！');
        }

        $mobile = request::trim('mobile');

        $result = self::checkMember($team, $mobile);
        if (is_error($result)) {
            return $result;
        }

        $name = request::str('name');
        $remark = request::str('remark');

        $u = User::findOne(['mobile' => $mobile, 'app' => User::WxAPP]);
        if ($u) {
            /** @var team_memberModelObj $member */
            $member = Team::addMember($team, $u, $name, $remark);
        } else {
            /** @var team_memberModelObj $member */
            $member = Team::addMobile($team, $mobile, $name, $remark);
        }

        if (empty($member)) {
            return err('创建车队成员失败！');
        }

        return $member->profile();
    }

    public static function editMember()
    {
        $user = common::getWXAppUser();

        $id = request::int('id');
        $member = Team::getMember($id);
        if (empty($member)) {
            return err('找不到这个车队队员！');
        }

        $team = $member->team();
        if (empty($team)) {
            return err('没有权限管理这个队员');
        }

        if ($team->getOwnerId() != $user->getId()) {
            return err('没有权限管理这个队员！');
        }

        $mobile = request::trim('mobile');
        if (!empty($mobile)) {
            if ($mobile != $member->getMobile()) {
                $result = self::checkMember($team, $mobile);
                if (is_error($result)) {
                    return $result;
                }
            }
            $member->setMobile($mobile);
        } else {
            $member->setMobile('');
        }

        $name = request::str('name');
        $remark = request::str('remark');

        $member->setName($name);
        $member->setRemark($remark);

        $member->save();

        return $member->profile();
    }

    public static function removeMember()
    {
        $user = common::getWXAppUser();

        $id = request::int('id');
        $member = Team::getMember($id);
        if (empty($member)) {
            return err('找不到这个车队队员！');
        }

        $team = $member->team();
        if (empty($team)) {
            return err('没有权限管理这个队员');
        }

        if ($team->getOwnerId() != $user->getId()) {
            return err('没有权限管理这个队员！');
        }

        $member->destroy();

        return true;
    }

    public static function transfer()
    {
        $user = common::getWXAppUser();

        if (!App::isTeamEnabled()) {
            return err('车队功能没有启用！');
        }

        //先锁定用户，防止恶意重复提交
        if (!$user->acquireLocker(User::COMMISSION_BALANCE_LOCKER)) {
            return error(State::ERROR, '锁定用户失败，请重试！');
        }

        return Util::transactionDo(function () use ($user) {
            $id = request::int('id');
            $total = request::int('total');
            if ($total < 1) {
                return err('转帐金额不正确！');
            }

            if ($total > $user->getCommissionBalance()->total()) {
                return err('帐户余额不足！');
            }

            $member = Team::getMember($id);
            if (empty($member)) {
                return err('找不到这个车队队员！');
            }

            $team = $member->team();
            if (empty($team)) {
                return err('没有权限管理这个队员');
            }

            if ($team->getOwnerId() != $user->getId()) {
                return err('没有权限管理这个队员！');
            }

            $u = $member->getAssociatedUser();
            if (empty($u)) {
                return err('找不到这个成员对应的用户！');
            }

            if ($user->getId() == $u->getId()) {
                return err('无法给自己转账！');
            }

            $from = $user->getCommissionBalance()->change(
                -$total, CommissionBalance::TRANSFER_FROM,
                [
                    'to' => [
                        'team' => $team->profile(),
                        'member' => $member->profile(),
                        'user' => $u->profile(false),
                    ],
                ]
            );

            if (empty($from)) {
                return err('转帐失败，请重试！');
            }

            $to = $u->getCommissionBalance()->change($total, CommissionBalance::TRANSFER_TO, [
                'from' => [
                    'team' => $team->profile(),
                    'member' => $member->profile(false),
                    'user' => $user->profile(false),
                ],
            ]);

            if (empty($to)) {
                return err('转帐失败，请重试！');
            }

            $profile = $u->profile();
            $profile['commission_balance'] = $u->getCommissionBalance()->total();

            return [
                'message' => '转帐成功！',
                'total' => $total,
                'to' => $profile,
            ];
        });
    }

    public static function orderList(): array
    {
        $user = common::getWXAppUser();

        if (request::has('id')) {
            $id = request::int('id');

            $member = Team::getMember($id);
            if (empty($member)) {
                return err('找不到这个车队队员！');
            }

            $team = $member->team();
            if (empty($team)) {
                return err('没有权限管理这个队员');
            }

            if ($team->getOwnerId() != $user->getId()) {
                return err('没有权限管理这个队员！');
            }

            $u = $member->getAssociatedUser();
            if (empty($u)) {
                return err('找不到这个成员对应的用户！');
            }

            $member_openid = $u->getOpenid();

        } else {
            $team = Team::getFor($user);
            if (empty($team)) {
                return err('找不到用户的车队信息！');
            }

            $member_openid = [];
            $member_query = Team::findAllMember($team);
            /** @var team_memberModelObj $member */
            foreach ($member_query->findAll() as $member) {
                $user = $member->getAssociatedUser();
                if (empty($user)) {
                    continue;
                }
                $member_openid[] = $user->getOpenid();
            }
        }

        $query = Order::query([
            'openid' => $member_openid,
            'result_code' => 0,
            'src' => [Order::CHARGING, Order::CHARGING_UNPAID],
        ]);

        $page = max(1, request::int('page'));
        $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

        //列表数据
        $query->page($page, $page_size);

        $keywords = request::trim('keywords');
        if ($keywords) {
            $query->where(['order_id REGEXP' => $keywords]);
        }

        $query->orderby('id desc');

        $list = [];
        /** @var orderModelObj $order */
        foreach ($query->findAll() as $order) {
            $data = Order::format($order, true);
            $user = $order->getUser();
            if ($user) {
                $member = Team::getMemberFor($team, $user);
                if ($member) {
                    $data['member'] = $member->profile(false);
                }
            }
            $list[] = $data;
        }

        return $list;
    }

    public static function orderDetail(): array
    {
        $serial = request::str('serial');

        $order = Order::get($serial, true);
        if (empty($order)) {
            return err('找不到这个订单！');
        }

        if (!$order->isChargingResultOk()) {
            $data = $order->getChargingResult();

            return err('订单没有完成，故障：'.$data['re']);
        }

        if (!$order->isChargingFinished()) {
            return err('订单没有完成！');
        }

        return Order::format($order, true);
    }


    public static function chargingList(): array
    {
        $user = common::getWXAppUser();

        $id = request::int('id');

        $member = Team::getMember($id);
        if (empty($member)) {
            return err('找不到这个车队队员！');
        }

        $team = $member->team();
        if (empty($team)) {
            return err('没有权限管理这个队员');
        }

        if ($team->getOwnerId() != $user->getId()) {
            return err('没有权限管理这个队员！');
        }

        $u = $member->getAssociatedUser();
        if (empty($u)) {
            return err('找不到这个成员对应的用户！');
        }

        $query = $u->getCommissionBalance()->log();
        $query->where([
            'src' => [
                CommissionBalance::TRANSFER_TO,
                CommissionBalance::CHARGING,
                CommissionBalance::WITHDRAW,
            ],
        ]);

        $page = max(1, request::int('page'));
        $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);
        $query->page($page, $page_size);

        $query->orderBy('createtime DESC');

        $result = [];
        foreach ($query->findAll() as $log) {
            $result[] = CommissionBalance::format($log);
        }

        return $result;
    }
}