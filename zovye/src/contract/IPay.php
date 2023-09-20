<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\contract;

use zovye\model\deviceModelObj;
use zovye\model\userModelObj;

interface IPay
{
    /**
     * 获取指定名称
     */
    public function getName(): string;

    /**
     * 设置支付参数
     */
    public function setConfig(array $config = []);

    /**
     * 创建支付订单
     */
    public function createXAppPay(
        string $user_uid,
        string $device_uid,
        string $order_no,
        int $price,
        string $body = ''
    );


    public function createJsPay(string $user_uid, string $device_uid, string $order_no, int $price, string $body = '');


    /**
     * 获取支付Js
     */
    public function getPayJs(deviceModelObj $device, userModelObj $user);

    /**
     * 关闭订单
     */
    public function close(string $order_no);

    /**
     * 请求退款
     * @param string $order_no
     * @param int $total
     * @param bool $is_transaction_id
     * @return mixed
     */
    public function refund(string $order_no, int $total, bool $is_transaction_id = false);

    /**
     * 查询订单
     */
    public function query(string $order_no);

    /**
     * 解析回调数据
     */
    public function decodeData(string $input);

    /**
     * 检验回调数据
     */
    public function checkResult(array $data = []);

    public function getResponse(bool $ok = true);
}