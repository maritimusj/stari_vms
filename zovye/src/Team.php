<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\team_memberModelObj;
use zovye\model\teamModelObj;
use zovye\model\userModelObj;

class Team
{
    public static function getOrCreateFor(userModelObj $user): ?teamModelObj
    {
        $locker = $user->acquireLocker('team');
        try {
            if (empty($locker)) {
                return null;
            }
            $team = self::getFor($user);
            if (empty($team)) {
                $team = self::createFor($user, '默认车队');
            }

            return $team;
        } finally {
            if ($locker) {
                $locker->release();
            }
        }
    }

    public static function createFor(userModelObj $user, string $name): ?teamModelObj
    {
        return m('team')->create(
            We7::uniacid([
                'owner_id' => $user->getId(),
                'name' => $name,
            ])
        );
    }

    public static function getMember(int $id): ?team_memberModelObj
    {
        return m('team_member')->findOne(['id' => $id]);
    }

    public static function addMember(
        teamModelObj $team,
        userModelObj $user,
        string $name = '',
        string $remark = ''
    ): ?team_memberModelObj {
        return m('team_member')->create(
            [
                'team_id' => $team->getId(),
                'user_id' => $user->getId(),
                'mobile' => $user->getMobile(),
                'name' => $name,
                'remark' => $remark,
            ]
        );
    }

    public static function addMobile(
        teamModelObj $team,
        string $mobile,
        string $name = '',
        string $remark = ''
    ): ?team_memberModelObj {
        return m('team_member')->create(
            [
                'team_id' => $team->getId(),
                'mobile' => $mobile,
                'name' => $name,
                'remark' => $remark,
            ]
        );
    }

    public static function queryFor(userModelObj $user, $condition = []): base\modelObjFinder
    {
        return self::query(['owner_id' => $user->getId()])->where($condition);
    }

    public static function getFor(userModelObj $user): ?teamModelObj
    {
        return self::queryFor($user)->findOne();
    }

    public static function getAllMemberFor(userModelObj $user): array
    {
        $query = m('team_member')->query();
        $mobile = $user->getMobile();

        if (empty($mobile)) {
            $query->where(['user_id' => $user->getId()]);
        } else {
            $query->whereOr([
                'mobile' => $mobile,
                'user_id' => $user->getId(),
            ]);
        }
        $member_list = [];
        /** @var team_memberModelObj $member */
        foreach ($query->findAll() as $member) {
            $user_id = $member->getUserId();
            if ($user_id != 0 && $user_id != $user->getId()) {
                continue;
            }
            $member_mobile = $member->getMobile();
            if ($member_mobile != '' && $mobile != '' && $member_mobile != $mobile) {
                continue;
            }
            $member_list[] = $member;
        }

        return $member_list;
    }

    public static function findAllMember(teamModelObj $team, $condition = []): base\modelObjFinder
    {
        return m('team_member')->where(['team_id' => $team->getId()])->where($condition);
    }

    public static function query($condition = []): base\modelObjFinder
    {
        return m('team')->query($condition);
    }

    public static function get($id): ?teamModelObj
    {
        return m('team')->findOne([
            'id' => $id,
        ]);
    }

    public static function findOne($condition = []): ?teamModelObj
    {
        return m('team')->findOne($condition);
    }

}