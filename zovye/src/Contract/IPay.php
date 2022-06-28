<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\Contract;

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
     * @param array $config
     * @return mixed
     */
    public function setConfig(array $config = []);

    /**
     * 创建支付订单
     * @param string $user_uid
     * @param string $device_uid
     * @param string $order_no
     * @param int $price
     * @param string $body
     * @return mixed
     */
    public function createXAppPay(
        string $user_uid,
        string $device_uid,
        string $order_no,
        int $price,
        string $body = ''
    );


    /**
     * @param string $user_uid
     * @param string $device_uid
     * @param string $order_no
     * @param int $price
     * @param string $body
     * @return mixed
     */
    public function createJsPay(string $user_uid, string $device_uid, string $order_no, int $price, string $body = '');


    /**
     * 获取支付Js
     * @param deviceModelObj $device
     * @param userModelObj $user
     * @return mixed
     */
    public function getPayJs(deviceModelObj $device, userModelObj $user);

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
     * @param string $order_no
     * @return mixed
     */
    public function query(string $order_no);

    /**
     * 解析回调数据
     * @param string $input
     * @return mixed
     */
    public function decodeData(string $input);

    /**
     * 检验回调数据
     * @param array $data
     * @return mixed
     */
    public function checkResult(array $data = []);

    /**
     * @param bool $ok
     * @return mixed
     */
    public function getResponse(bool $ok = true);
}