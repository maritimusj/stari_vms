<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

use zovye\model\agentModelObj;
use zovye\model\referralModelObj;

class Referral
{
    static function getAgent($code): ?agentModelObj
    {
        /** @var referralModelObj $referral */
        $referral = m('referral')->findOne(['code' => $code]);
        if ($referral) {
            return $referral->getAgent();
        }

        return null;
    }
}