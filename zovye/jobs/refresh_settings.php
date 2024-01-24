<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\refresh_settings;

defined('IN_IA') or exit('Access Denied');

use zovye\CtrlServ;
use zovye\domain\Account;
use zovye\domain\Device;
use zovye\JobException;
use zovye\Log;
use zovye\model\accountModelObj;
use zovye\model\deviceModelObj;
use zovye\util\Util;
use function zovye\_W;
use function zovye\is_error;

if (!CtrlServ::checkJobSign()) {
    throw new JobException('签名不正确!');
}

$query = Account::query(['type' => [Account::NORMAL, Account::AUTH, Account::VIDEO, Account::QUESTIONNAIRE]]);

/** @var accountModelObj $acc */
foreach ($query->findAll() as $acc) {
    $qrcodesData = $acc->get('qrcodesData', []);
    if ($qrcodesData) {
        foreach ($qrcodesData as &$qrcode_data) {
            $qrcode_data['url'] = Account::createUrl($acc->getUid(), $qrcode_data['xid']);
        }
        $acc->set('qrcodesData', $qrcodesData);
    }

    $acc->setUrl(Account::createUrl($acc->getUid(), ['from' => 'account']));
    $acc->save();
}

$third_party_platform = [
    ['name' => Account::JFB_NAME, 'type' => Account::JFB, 'url' => Util::murl('jfb')],
    ['name' => Account::MOSCALE_NAME, 'type' => Account::MOSCALE, 'url' => Util::murl('moscale')],
    ['name' => Account::YUNFENBA_NAME, 'type' => Account::YUNFENBA, 'url' => Util::murl('yunfenba')],
    ['name' => Account::AQIINFO_NAME, 'type' => Account::AQIINFO, 'url' => Util::murl('aqiinfo')],
    ['name' => Account::ZJBAO_NAME, 'type' => Account::ZJBAO, 'url' => Util::murl('zjbao')],
    ['name' => Account::MEIPA_NAME, 'type' => Account::MEIPA, 'url' => Util::murl('meipa')],
    ['name' => Account::KINGFANS_NAME, 'type' => Account::KINGFANS, 'url' => Util::murl('kingfans')],
    ['name' => Account::SNTO_NAME, 'type' => Account::SNTO, 'url' => Util::murl('snto')],
    ['name' => Account::YFB_NAME, 'type' => Account::YFB, 'url' => Util::murl('yfb'),],
    ['name' => Account::WxWORK_NAME, 'type' => Account::WxWORK, 'url' => Util::murl('wxwork')],
    ['name' => Account::YOUFEN_NAME, 'type' => Account::YOUFEN, 'url' => Util::murl('youfen')],
    ['name' => Account::YIDAO_NAME, 'type' => Account::YIDAO, 'url' => Util::murl('yidao')],
    ['name' => Account::WEISURE_NAME, 'type' => Account::WEISURE, 'url' => Util::murl('weisure')],
    ['name' => Account::MENGMO_NAME, 'type' => Account::MENGMO, 'url' => Util::murl('mengmo')],
    ['name' => Account::CloudFI_NAME, 'type' => Account::CloudFI, 'url' => Util::murl('cloudfi')],
];

foreach ($third_party_platform as $item) {
    $acc = Account::findOneFromUID(Account::makeThirdPartyPlatformUID($item['type'], $item['name']));
    if ($acc) {
        $acc->setType($item['type']);
        $acc->setUrl($item['url']);
        $acc->save();
    }
}

$query = Device::query();
/** @var deviceModelObj $device */
foreach ($query->findAll() as $device) {
    $res = $device->updateQRCode(true);
    if (is_error($res)) {
        Log::error('refresh_settings', [
            'device' => $device->getImei(),
            'error' => $res,
        ]);
    }
}

Log::debug('refresh_settings', [
    'domain' => _W('siteroot'),
    'time' => date('Y-m-d H:i:s'),
]);