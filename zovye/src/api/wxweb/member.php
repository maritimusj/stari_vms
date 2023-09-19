<?php

namespace zovye\api\wxweb;

use zovye\App;
use zovye\domain\CommissionBalance;
use zovye\domain\Order;
use zovye\domain\Team;
use zovye\domain\User;
use zovye\model\orderModelObj;
use zovye\model\team_memberModelObj;
use zovye\model\teamModelObj;
use zovye\model\userModelObj;
use zovye\Request;
use zovye\util\DBUtil;
use function zovye\err;
use function zovye\is_error;

class member
{
    public static function getMemberList(userModelObj $wx_app_user): array
    {
        $locker = $wx_app_user->acquireLocker('team');
        try {
            if (empty($locker)) {
                return err('用户被占用，请重试！');
            }

            $team = Team::getFor($wx_app_user);
            if (empty($team)) {
                $team = Team::createFor($wx_app_user, '默认车队');
            }

            if (empty($team)) {
                return err('找不到车队或者创建车队失败！');
            }

            $page = max(1, Request::int('page'));
            $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

            $query = Team::findAllMember($team);

            if (Request::has('keyword')) {
                $keyword = Request::trim('keyword');
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
                $wx_app_user = $member->getAssociatedUser();
                if ($wx_app_user) {
                    $data['user'] = $wx_app_user->profile();
                    $data['balance'] = $wx_app_user->getCommissionBalance()->total();
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

    public static function memberUserInfo(userModelObj $wx_app_user): array
    {
        $mobile = Request::str('mobile');

        $team = Team::getOrCreateFor($wx_app_user);
        if (empty($team)) {
            $team = Team::createFor($wx_app_user, '默认车队');
        }

        if (empty($team)) {
            return err('找不到车队或者创建车队失败！');
        }

        $result = self::checkMember($team, $mobile, Request::int('member'));
        if (is_error($result)) {
            return $result;
        }

        $u = User::findOne(['mobile' => $mobile, 'app' => User::WxAPP]);
        if ($u) {
            return $u->profile();
        }

        return [];
    }

    public static function createMember(userModelObj $wx_app_user)
    {
        $team = Team::getOrCreateFor($wx_app_user);
        if (empty($team)) {
            $team = Team::createFor($wx_app_user, '默认车队');
        }

        if (empty($team)) {
            return err('找不到车队或者创建车队失败！');
        }

        $mobile = Request::trim('mobile');

        $result = self::checkMember($team, $mobile);
        if (is_error($result)) {
            return $result;
        }

        $name = Request::str('name');
        $remark = Request::str('remark');

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

    public static function editMember(userModelObj $wx_app_user)
    {
        $id = Request::int('id');
        $member = Team::getMember($id);
        if (empty($member)) {
            return err('找不到这个车队队员！');
        }

        $team = $member->team();
        if (empty($team) || $team->getOwnerId() != $wx_app_user->getId()) {
            return err('没有权限管理这个队员');
        }

        $mobile = Request::trim('mobile');
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

        $name = Request::str('name');
        $remark = Request::str('remark');

        $member->setName($name);
        $member->setRemark($remark);

        $member->save();

        return $member->profile();
    }

    public static function removeMember(userModelObj $wx_app_user)
    {
        $id = Request::int('id');
        $member = Team::getMember($id);
        if (empty($member)) {
            return err('找不到这个车队队员！');
        }

        $team = $member->team();
        if (empty($team) || $team->getOwnerId() != $wx_app_user->getId()) {
            return err('没有权限管理这个队员');
        }

        $member->destroy();

        return true;
    }

    public static function transfer(userModelObj $wx_app_user)
    {
        if (!App::isTeamEnabled()) {
            return err('车队功能没有启用！');
        }

        //先锁定用户，防止恶意重复提交
        if (!$wx_app_user->acquireLocker(User::COMMISSION_BALANCE_LOCKER)) {
            return err('锁定用户失败，请重试！');
        }

        return DBUtil::transactionDo(function () use ($wx_app_user) {
            $id = Request::int('id');
            $total = Request::int('total');
            if ($total < 1) {
                return err('转帐金额不正确！');
            }

            if ($total + \zovye\business\Charging::getUnpaidOrderPriceTotal($wx_app_user) > $wx_app_user->getCommissionBalance()->total()) {
                return err('帐户有效余额不足，请充值再操作！');
            }

            $member = Team::getMember($id);
            if (empty($member)) {
                return err('找不到这个车队队员！');
            }

            $team = $member->team();
            if (empty($team) || $team->getOwnerId() != $wx_app_user->getId()) {
                return err('没有权限管理这个队员！');
            }

            $u = $member->getAssociatedUser();
            if (empty($u)) {
                return err('找不到这个成员对应的用户！');
            }

            if ($wx_app_user->getId() == $u->getId()) {
                return err('无法给自己转账！');
            }

            $from = $wx_app_user->getCommissionBalance()->change(
                -$total, CommissionBalance::TRANSFER_OUT,
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

            $to = $u->getCommissionBalance()->change($total, CommissionBalance::TRANSFER_RECEIVED, [
                'from' => [
                    'team' => $team->profile(),
                    'member' => $member->profile(false),
                    'user' => $wx_app_user->profile(false),
                ],
            ]);

            if (empty($to)) {
                return err('转帐失败，请重试！');
            }

            $profile = $u->profile();
            $profile['commission_balance'] = $u->getCommissionBalance()->total();

            return [
                'msg' => '转帐成功！',
                'total' => $total,
                'to' => $profile,
            ];
        });
    }

    public static function orderList(userModelObj $wx_app_user): array
    {
        if (Request::has('id')) {
            $id = Request::int('id');

            $member = Team::getMember($id);
            if (empty($member)) {
                return err('找不到这个车队队员！');
            }

            $team = $member->team();
            if (empty($team) || $team->getOwnerId() != $wx_app_user->getId()) {
                return err('没有权限管理这个队员！');
            }

            $u = $member->getAssociatedUser();
            if (empty($u)) {
                return err('找不到这个成员对应的用户！');
            }

            $member_openid = $u->getOpenid();

        } else {
            $team = Team::getFor($wx_app_user);
            if (empty($team)) {
                return err('找不到用户的车队信息！');
            }

            $member_openid = [];
            $member_query = Team::findAllMember($team);
            /** @var team_memberModelObj $member */
            foreach ($member_query->findAll() as $member) {
                $wx_app_user = $member->getAssociatedUser();
                if (empty($wx_app_user)) {
                    continue;
                }
                $member_openid[] = $wx_app_user->getOpenid();
            }
        }

        $query = Order::query([
            'openid' => $member_openid,
            'result_code' => 0,
            'src' => [Order::CHARGING, Order::CHARGING_UNPAID],
        ]);

        $page = max(1, Request::int('page'));
        $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

        //列表数据
        $query->page($page, $page_size);

        $keywords = Request::trim('keywords');
        if ($keywords) {
            $query->where(['order_id REGEXP' => $keywords]);
        }

        $query->orderby('id desc');

        $list = [];
        /** @var orderModelObj $order */
        foreach ($query->findAll() as $order) {
            $data = Order::format($order, true);
            $wx_app_user = $order->getUser();
            if ($wx_app_user) {
                $member = Team::getMemberFor($team, $wx_app_user);
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
        $serial = Request::str('serial');

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


    public static function chargingList(userModelObj $wx_app_user): array
    {
        $id = Request::int('id');
        $member = Team::getMember($id);
        if (empty($member)) {
            return err('找不到这个车队队员！');
        }

        $team = $member->team();
        if (empty($team) || $team->getOwnerId() != $wx_app_user->getId()) {
            return err('没有权限管理这个队员！');
        }

        $u = $member->getAssociatedUser();
        if (empty($u)) {
            return err('找不到这个成员对应的用户！');
        }

        $query = $u->getCommissionBalance()->log();
        $query->where([
            'src' => [
                CommissionBalance::TRANSFER_RECEIVED,
                CommissionBalance::CHARGING_FEE,
                CommissionBalance::WITHDRAW,
            ],
        ]);

        $page = max(1, Request::int('page'));
        $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);
        $query->page($page, $page_size);

        $query->orderBy('createtime DESC');

        $result = [];
        foreach ($query->findAll() as $log) {
            $result[] = CommissionBalance::format($log);
        }

        return $result;
    }
}