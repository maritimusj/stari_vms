<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\job\refresh_settings;

use zovye\Account;
use zovye\CtrlServ;
use zovye\Device;
use zovye\Log;
use zovye\model\accountModelObj;
use zovye\model\deviceModelObj;
use zovye\request;
use zovye\Util;
use function zovye\_W;
use function zovye\is_error;

$op = request::op('default');

if ($op == 'refresh_settings' && CtrlServ::checkJobSign()) {

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
        ['name' => Account::MENGMO_NAME, 'type' => Account::MENGMO, 'url' => Util::murl('mengmo')],
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
        $res = $device->updateQrcode(true);
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
}