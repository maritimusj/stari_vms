<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Agent;
use zovye\util\DBUtil;

defined('IN_IA') or exit('Access Denied');

$result = DBUtil::transactionDo(function () {
    $id = Request::int('id');

    $agent = Agent::get($id);
    if (empty($agent)) {
        return err('找不到这个代理商！');
    }
    
    $referral = $agent->getReferral();
    if (!$referral) {
        return err('创建代理商推荐码失败！');
    }
    
    $referral->destroy();
    
    $referral = $agent->getReferral();
    if (!$referral) {
        return('创建代理商推荐码失败！');
    }
    
    return [
        'id' => $agent->getId(),
        'referral' => $referral->getCode(),
    ];
});


JSON::result($result);