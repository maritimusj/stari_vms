<?php

namespace zovye;

use zovye\model\goodsModelObj;

$tb_name = APP_NAME;

if (!We7::pdo_tableexists($tb_name . '_delivery')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_delivery` ( 
    `id` INT NOT NULL AUTO_INCREMENT , 
    `user_id` INT NOT NULL , 
    `goods_id` INT NOT NULL , 
    `num` INT NOT NULL DEFAULT '0', 
    `name` VARCHAR(64) NOT NULL , 
    `phone_num` VARCHAR(32) NOT NULL , 
    `address` TEXT NOT NULL , 
    `status` INT NOT NULL , 
    `extra` JSON NOT NULL , 
    `createtime` INT NOT NULL , 
    PRIMARY KEY (`id`), 
    INDEX `user` (`user_id`, `status`),
    INDEX `goods` (`goods_id`, `status`)
    ) ENGINE = InnoDB;
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_fieldexists($tb_name . '_goods', 's1')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_goods` ADD `s1` INT NOT NULL DEFAULT '0' AFTER `deleted`, ADD INDEX (`s1`);
SQL;
    Migrate::execSQL($sql);

    $query = Goods::query();

    /** @var goodsModelObj $goods */
    foreach ($query->findAll() as $goods) {
        $s1 = 0;
        if ($goods->allowPay()) {
            $s1 |= Goods::ALLOW_PAY;
        }
        if ($goods->allowFree()) {
            $s1 |= Goods::ALLOW_FREE;
        }
        if ($goods->getBalance() > 0) {
            $s1 |= Goods::ALLOW_EXCHANGE;
        }
        $goods->setS1($s1);
        $goods->save();
    }
}

