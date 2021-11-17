<?php

namespace zovye;

$tb_name = APP_NAME;

if (!We7::pdo_fieldexists($tb_name . '_account', 'type')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_account` ADD `type` INT NOT NULL DEFAULT '0' AFTER `order_no`, ADD INDEX (`type`);
SQL;
    Migrate::execSQL($sql);

    $query = Account::query();

    foreach($query->findAll() as $entry) {
        $type = intval($entry->settings('config.type', 0));
        $entry->setType($type);
        $entry->setState($entry->getState() != 0 ? Account::NORMAL : Account::BANNED);
        $entry->save();
    }

   updateSettings('accounts.lastupdate', time());
}