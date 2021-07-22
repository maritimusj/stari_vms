<?php
namespace zovye\api\wx;

use zovye\App;
use zovye\model\userModelObj;

class misc
{
    public static function getLowRemainDeviceTotal($agent): int
    {
        $remainWarning = App::remainWarningNum($agent);
        return \zovye\Device::query(['agent_id' => $agent->getId(), 'remain <' => $remainWarning])->count();
    }

    public static function deviceStats(): array
    {
        $user = common::getAgent();
        $agent = $user->isAgent() ? $user : $user->getPartnerAgent();

        $total = $user->getDeviceCount();
        $all_devices = $total;
        $low_remain_total = self::getLowRemainDeviceTotal($agent);

        /** @var userModelObj $sub */
        $list = [];
        \zovye\Agent::getAllSubordinates($agent, $list, true);
        foreach ($list as $sub) {
            if ($sub->isAgent()) {
                $sa = $sub->agent();
                if ($sa) {
                    $all_devices += $sa->getDeviceCount();
                    $low_remain_total += self::getLowRemainDeviceTotal($sa);
                }
            }
        }

        return [
            'total' => $total,
            'all' => $all_devices,
            'low_remain' => $low_remain_total,
        ];
    }
}