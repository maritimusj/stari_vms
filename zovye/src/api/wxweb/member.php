<?php

namespace zovye\api\wxweb;

use zovye\api\wx\common;
use zovye\CommissionBalance;
use zovye\model\team_memberModelObj;
use zovye\model\teamModelObj;
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
        $user = common::getUser();
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
            $total = $query->count();

            $query->page($page, $page_size);

            $list = [];

            /** @var team_memberModelObj $member */
            foreach ($query->findAll() as $member) {
                $list[] = $member->profile();
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

    public static function checkMember(teamModelObj $team, $mobile)
    {
        if (empty($mobile) || !preg_match(REGULAR_TEL, $mobile)) {
            return err('手机号码不正确！');
        }

        $member_exists = Team::findAllMember($team, [
            'mobile' => $mobile,
        ])->exists();

        if ($member_exists) {
            return err('手机号码已经是车队成员！');
        }

        $u = User::findOne(['mobile' => $mobile]);
        if ($u) {
            $member_exists = Team::findAllMember($team, [
                'user_id' => $u->getId(),
            ])->exists();

            if ($member_exists) {
                return err('手机号码已经加入车队！');
            }
        }

        return true;
    }

    public static function memberUserInfo(): array
    {
        $user = common::getUser();

        $mobile = request::str('mobile');

        $team = Team::getOrCreateFor($user);
        if (empty($team)) {
            $team = Team::createFor($user, '默认车队');
        }

        if (empty($team)) {
            return err('找不到车队或者创建车队失败！');
        }

        $result = self::checkMember($team, $mobile);
        if (is_error($result)) {
            return $result;
        }

        $u = User::findOne(['mobile' => $mobile]);
        if ($u) {
            return $u->profile();
        }

        return [];
    }

    public static function createMember()
    {
        $user = common::getUser();

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

        $u = User::findOne(['mobile' => $mobile]);
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
        $user = common::getUser();

        $id = request::int('id');
        $member = Team::getMember($id);
        if (empty($member)) {
            return err('找不到这个车队队员！');
        }

        $team = $member->team();
        if (empty($team)) {
            return err('没有权限管理这个成员');
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
        $user = common::getUser();

        $id = request::int('id');
        $member = Team::getMember($id);
        if (empty($member)) {
            return err('找不到这个车队队员！');
        }

        $team = $member->team();
        if (empty($team)) {
            return err('没有权限管理这个成员');
        }

        if ($team->getOwnerId() != $user->getId()) {
            return err('没有权限管理这个队员！');
        }

        $member->destroy();

        return true;
    }

    public static function transfer()
    {
        $user = common::getUser();

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

            if ($total > $user->getCommissionBalanceCard()->total()) {
                return err('帐户余额不足！');
            }

            $member = Team::getMember($id);
            if (empty($member)) {
                return err('找不到这个车队队员！');
            }

            $team = $member->team();
            if (empty($team)) {
                return err('没有权限管理这个成员');
            }

            if ($team->getOwnerId() != $user->getId()) {
                return err('没有权限管理这个队员！');
            }

            $u = $member->user();
            if (empty($u)) {
                $mobile = $member->getMobile();
                if (!empty($mobile)) {
                    $u = User::findOne(['mobile' => $mobile]);
                }
            }

            if (empty($u)) {
                return err('找不到这个成员对应的用户！');
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
                    'member' => $member->profile(),
                    'user' => $user->profile(false),
                ],
            ]);

            if (empty($to)) {
                return err('转帐失败，请重试！');
            }

            return [
                'message' => '转帐成功！',
                'total' => $total,
                'to' => $u->profile(),
            ];
        });
    }

    public static function chargingList(): array
    {
        $user = common::getUser();

        $id = request::int('id');

        $member = Team::getMember($id);
        if (empty($member)) {
            return err('找不到这个车队队员！');
        }

        $team = $member->team();
        if (empty($team)) {
            return err('没有权限管理这个成员');
        }

        if ($team->getOwnerId() != $user->getId()) {
            return err('没有权限管理这个队员！');
        }

        $u = $member->user();
        if (empty($u)) {
            $mobile = $member->getMobile();
            if (!empty($mobile)) {
                $u = User::findOne(['mobile' => $mobile]);
            }
        }

        if (empty($u)) {
            return err('找不到这个成员对应的用户！');
        }

        $query = $u->getCommissionBalance()->log();
        $query->where([
            'src' => [
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