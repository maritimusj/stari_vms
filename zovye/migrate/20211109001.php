<?php

namespace zovye;

$tb_name = APP_NAME;

if (!We7::pdo_fieldexists($tb_name . '_account', 'type')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_account` ADD `type` INT NOT NULL DEFAULT '0' AFTER `order_no`, ADD INDEX (`type`);
SQL;
    Migrate::execSQL($sql);

    $query = Account::query();

    foreach ($query->findAll() as $entry) {
        $type = intval($entry->settings('config.type', 0));
        $entry->setType($type);
        $entry->setState($entry->getState() != 0 ? Account::NORMAL : Account::BANNED);
        $entry->save();
    }

    $spec = [
        Account::JFB => [
            Util::murl('jfb'),
            Account::JFB_NAME,
        ],
        Account::MOSCALE => [
            Util::murl('moscale'),
            Account::    MOSCALE_NAME,
        ],
        Account::YUNFENBA => [
            Util::murl('yunfenba'),
            Account::YUNFENBA_NAME,
        ],
        Account::AQIINFO => [
            Util::murl('aqiinfo'),
            Account::    AQIINFO_NAME,
        ],
        Account::ZJBAO => [
            Util::murl('zjbao'),
            Account::  ZJBAO_NAME,
        ],
        Account::MEIPA => [
            Util::murl('meipa'),
            Account::  MEIPA_NAME,
        ],
        Account::KINGFANS => [
            Util::murl('kingfans'),
            Account::KINGFANS_NAME,
        ],
        Account::SNTO => [
            Util::murl('snto'),
            Account:: SNTO_NAME,
        ],
        Account::YFB => [
            Util::murl('yfb'),
            Account::YFB_NAME,
        ],
        Account::WxWORK => [
            Util::murl('wxwork'),
            Account::WxWORK_NAME,
        ],
        Account::YOUFEN => [
            Util::murl('youfen'),
            Account::YOUFEN_NAME,
        ],
    ];

    foreach ($spec as $type => $item) {
        $acc = Account::findOneFromUID(Account::makeThirdPartyPlatformUID($type, $item[1]));
        if ($acc) {
            $acc->setType($type);
            $acc->setUrl($item[0]);
            $acc->save();
        }
    }

    updateSettings('accounts.lastupdate', time());
}