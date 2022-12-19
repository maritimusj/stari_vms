<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

class SIM
{
    private static $status_title = [
        "00" => '正常使用',
        "10" => '测试期',
        "02" => '停机',
        "03" => '预销号',
        "04" => '销号',
        "11" => '沉默期',
        "12" => '停机保号',
        "99" => '未知',
    ];

    public static function get($iccid) {
        $res = CtrlServ::getV2("iccid/$iccid");
        if ( is_error($res)) {
            return $res;
        }

        if (!$res['status']) {
            return err('请求失败！');
        }

        $data = $res['data'] ?? [];
        if (empty($data)) {
            return err('请求失败，返回数据为空！');
        }

        $data['status'] = self::$status_title[$data['account_status']] ?? '未知';

        return $data;
    }

}