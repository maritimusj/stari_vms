<?php

namespace zovye;

use Exception;

$op = request::op('default');

Util::logToFile('SQM', request::raw());

if ($op == 'default') {
    try {
        if (!App::isSQMPayEnabled()) {
            throw new Exception('没有启用该功能！');
        }

        if (!SQM::checkSign(request::raw())) {
            throw new Exception('签名不正确！');
        }

        $user_uid = request::json('user_id', '');
        $user = User::get($user_uid, true);
        if (empty($user) || $user->isBanned()) {
            throw new Exception('用户无法使用该功能！');
        }
        
        $device_id = $user->getLastActiveData('device', 0);
        $device = Device::get($device_id);
        if (empty($device)) {
            throw new Exception('找不到这个设备！');
        }
    
        $goodsData = $device->getGoodsByLane(0);
        if (empty($goodsData)) {
            throw new Exception('找不到商品！');
        }

        //重新获取商品数据，以获取自定义型号的商品数据
        $goodsData = $device->getGoods($goodsData['id']);
        if (empty($goodsData)) {
            throw new Exception('找不到商品！!');
        }
    
        $num = SQM::getGoodsNum();
        if ($goodsData['num'] < $num) {
            throw new Exception('商品数量不足！');
        }
   
        //获取任务佣金
        $money = request::json('money');
        if ($money > 0) {
            $goodsData['price'] = $money;
        }

        //使用后台设置的佣金金额做为支付金额，为代理商分佣
        $bonus = intval(settings('custom.SQMPay.bonus', 0));

        $result = SQM::createOrder($device, $user, $goodsData, $num, [
            'task_record_id' => request::json('task_record_id', ''),
            'timestamp' => request::json('timestamp', time()),
            'price' => $bonus,
            'serial' => request::json('task_record_id'),
        ]);
        
        if (!$result) {
            throw new Exception('创建出货任务失败！');
        }

        exit('Ok');    

    }catch(Exception $e) {
         Util::logToFile('SQM', [
             'error' => $e->getMessage(),
         ]);

         exit($e->getMessage());  
    }
}
