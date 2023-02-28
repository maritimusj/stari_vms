<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$tpl_data['navs'] = Util::getSettingsNavs();

if (App::isIDCardVerifyEnabled()) {
    $res = CtrlServ::getV2('idcard/balance');

    if (!empty($res) && $res['status']) {
        $tpl_data['idcard_balance'] = $res['data']['balance'];
    } else {
        $tpl_data['idcard_balance'] = $res['data']['msg'];
    }
}

$tpl_data['settings'] = settings();

app()->showTemplate('web/settings/user', $tpl_data);