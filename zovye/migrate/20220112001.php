<?php

namespace zovye;

use zovye\model\goodsModelObj;

$tb_name = APP_NAME;

if (!We7::pdo_tableexists($tb_name.'_delivery')) {
    $sql = <<<SQL
CREATE TABLE `ims_zovye_vms_delivery` ( 
    `id` INT NOT NULL AUTO_INCREMENT , 
    `uniacid` INT NOT NULL , 
    `order_no` VARCHAR(64) NOT NULL , 
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
    UNIQUE (`order_no`), 
    INDEX `user` (`uniacid`, `user_id`, `status`),
    INDEX `goods` (`uniacid`, `goods_id`, `status`),
    INDEX `createtime` (`uniacid`, `createtime`, `status`),
    INDEX `status` (`uniacid`, `status`)
    ) ENGINE = InnoDB;
SQL;
    Migrate::execSQL($sql);
}

if (!We7::pdo_fieldexists($tb_name.'_goods', 's1')) {
    $sql = <<<SQL
ALTER TABLE `ims_zovye_vms_goods` ADD `s1` INT NOT NULL DEFAULT '0' AFTER `deleted`, ADD INDEX (`s1`);
SQL;
    Migrate::execSQL($sql);

    $query = Goods::query();

    /** @var goodsModelObj $goods */
    foreach ($query->findAll() as $goods) {
        $s1 = 0;
        if ($goods->getExtraData('allowPay')) {
            $s1 = Goods::setPayBitMask($s1);
        }
        if ($goods->getExtraData('allowFree')) {
            $s1 = Goods::setFreeBitMask($s1);
        }
        if ($goods->getBalance() > 0) {
            $s1 = Goods::setExchangeBitMask($s1);
        }
        $goods->setS1($s1);
        $goods->save();
    }
}

