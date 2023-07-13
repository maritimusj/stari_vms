<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

header("Access-Control-Allow-Origin: *");

$op = Request::op('default');

if ($op == 'default') {

    $app_id = Request::trim('id');

    $device = Device::getFromAppId($app_id);
    if ($device) {
        //设备已完成注册
        header('location:'.Util::murl('device', ['id' => $device->getImei()]));
        exit();
    }

    app()->showTemplate('app', [
        'js_code' => Session::fetchJSSDK(),
        'op' => $op,
        'appId' => $app_id,
        'device' => $device,
    ]);

} elseif ($op == 'bind') {

    $device_id = Request::trim('id');
    $app_id = Request::trim('appId');

    $result = DBUtil::transactionDo(
        function () use ($device_id, $app_id) {
            if ($device_id && $app_id) {
                $device = Device::getFromAppId($app_id);
                if ($device) {
                    return err('appID已经被注册！');
                }

                $device = Device::find($device_id, ['id', 'imei']);
                if (empty($device)) {
                    $res = CtrlServ::query("device/$device_id", []);
                    if (!is_error($res) && $res['id']) {
                        //创建设备
                        $data = [
                            'name' => $res['title'],
                            'capacity' => DEFAULT_DEVICE_CAPACITY,
                            'remain' => DEFAULT_DEVICE_CAPACITY,
                            'reset' => 0,
                            'imei' => $res['mcbUID'],
                            'app_id' => $res['appUID'] ?: $app_id,
                        ];

                        $device = Device::create($data);
                        if (empty($device)) {
                            return err('无法创建新设备！');
                        }
                    } else {
                        return err('没有找到设备信息，无法创建设备！');
                    }
                }

                if ($device) {
                    if (empty($device->getAppId())) {
                        $device->setAppId($app_id);
                        if (!$device->save()) {
                            return err('无法保存设置，注册失败！');
                        }
                    }

                    $res = CtrlServ::query("device/{$device->getImei()}", []);
                    if (!is_error($res) && empty($res['appUID'])) {
                        $data = http_build_query(['appUID' => $app_id]);
                        $res = CtrlServ::query("device/{$device->getImei()}/bind", [], $data, '', 'PUT');
                        if (is_error($res)) {
                            return err('控制中心无法绑定appID，注册失败！');
                        }
                    }
                    $device->resetShadowId();
                    $device->createQrcodeFile();
                    $device->appNotify('update');

                    return true;

                }
            }

            return err('参数错误，注册失败。deviceID:[ {$device_id} ], appID:[ {$app_id} ]！');
        }
    );

    if (!is_error($result)) {
        exit(json_encode(['status' => true, 'msg' => '注册成功！']));
    } else {
        exit(json_encode(['status' => false, 'msg' => $result['message']]));
    }

} elseif ($op == 'data_vw') {

    $type = Request::str('type');
    //title
    if ($type == 'title') {
        $query = m('data_vw');
        $title = $query->where(['k' => 'title'])->findOne();
        $value = '';
        if ($title) {
            $value = $title->getV();
        }
        exit(json_encode([['value' => $value]]));
    }

    //
    if ($type == 'total_sale') {
        $k_init = 'total_sale_init';
        $k_freq = 'total_sale_freq';
        $k_s1 = 'total_sale_section1';
        $k_s2 = 'total_sale_section2';
        exit(json_encode([['value' => handleFreq($k_init, $k_freq, $k_s1, $k_s2)]]));
    }

    if ($type == 'today_sale') {
        $k_init = 'today_sale_init';
        $k_freq = 'today_sale_freq';
        $k_s1 = 'today_sale_section1';
        $k_s2 = 'today_sale_section2';
        exit(json_encode([['value' => handleFreq($k_init, $k_freq, $k_s1, $k_s2)]]));
    }
    if ($type == 'total_order') {
        $k_init = 'total_order_init';
        $k_freq = 'total_order_freq';
        $k_s1 = 'total_order_section1';
        $k_s2 = 'total_order_section2';
        exit(json_encode([['value' => handleFreq($k_init, $k_freq, $k_s1, $k_s2)]]));
    }
    if ($type == 'today_order') {
        $k_init = 'today_order_init';
        $k_freq = 'today_order_freq';
        $k_s1 = 'today_order_section1';
        $k_s2 = 'today_order_section2';
        exit(json_encode([['value' => handleFreq($k_init, $k_freq, $k_s1, $k_s2)]]));
    }
    if ($type == 'user') {
        exit(json_encode(handlePCT('user_man', 'user_woman', '男性', '女性')));
    }
    if ($type == 'income') {
        exit(json_encode(handlePCT('income_wx', 'income_ali', '微信', '支付宝')));
    }
    //商品
    if ($type == 'goods') {
        $query = m('data_vw');
        $arr = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
        $arr_assoc = [
            1 => '第一名',
            2 => '第二名',
            3 => '第三名',
            4 => '第四名',
            5 => '第五名',
            6 => '第六名',
            7 => '第七名',
            8 => '第八名',
            9 => '第九名',
            10 => '第十名',
        ];

        $in_str = array_map(function ($v) {
            return 'g'.$v;
        }, $arr);

        $res = $query->where(['k' => $in_str])->findAll();
        $dt = time();
        $res_arr = [];

        foreach ($res as $item) {
            $value = $item->getV();
            $value_arr = explode(';', $value);
            $init_dt = $item->getCreatetime();
            $dt_section = $dt - $init_dt;
            $freq = ceil($dt_section / $value_arr[2]);
            if ($freq > 1) {
                $_r = rand(1, $arr[3]);
                $_dt = $init_dt + $freq * $value_arr[2];
                $value_arr[1] = $value_arr[1] + $_r * $freq;

                //
                $item->setV(implode(';', $value_arr));
                $item->setCreatetime($_dt);
                $item->save();
            }
            $res_arr[] = ['value1' => $value_arr[0], 'value2' => $value_arr[1]];
        }
        $c_arr = array_column($res_arr, 'value2');
        array_multisort($c_arr, SORT_DESC, $res_arr);
        foreach ($res_arr as $k => $val) {
            $i = $k + 1;
            $res_arr[$k]['key1'] = $arr_assoc[$i];
        }
        exit(json_encode($res_arr));
    }

    $arr = [
        1,
        2,
        3,
        4,
        5,
        6,
        7,
        8,
        9,
        10,
        11,
        12,
        13,
        14,
        15,
        16,
        17,
        18,
        19,
        20,
        21,
        22,
        23,
        24,
        25,
        26,
        27,
        28,
        29,
        30,
        31,
    ];
    $arr_assoc = Util::getProvinceList();

    //省份
    if ($type == 'provinces') {
        $query = m('data_vw');

        $res = $query->where(['k' => array_keys($arr_assoc)])->findAll();

        $dt = time();

        $res_arr = [];

        foreach ($res as $item) {
            $value = $item->getV();

            $value_arr = explode(';', $value);
            $init_dt = $item->getCreatetime();
            $dt_section = $dt - $init_dt;
            $freq = ceil($dt_section / $value_arr[1]);

            if ($freq > 1) {

                $_r = rand(1, $arr[2]);
                $_dt = $init_dt + $freq * $value_arr[1];
                $value_arr[0] = $value_arr[0] + $_r * $freq;

                $item->setV(implode(';', $value_arr));
                $item->setCreatetime($_dt);
                $item->save();
            }

            $res_arr[] = ['key1' => $arr_assoc[$item->getK()], 'value1' => $value_arr[0]];
        }

        exit(json_encode($res_arr));
    }

    if ($type == 'device_total') {

        $query = m('data_vw');
        $res = $query->where(['k' => array_keys($arr_assoc)])->findAll();

        $total_amount = 0;

        foreach ($res as $item) {
            $value = $item->getV();
            $value_arr = explode(';', $value);
            $total_amount += $value_arr[0];
        }

        exit(json_encode([['value' => $total_amount]]));
    }

    if ($type == 'device_map') {
        $query = m('data_vw');
        $res = $query->where(['k' => array_keys($arr_assoc)])->findAll();

        $res_arr = [];

        foreach ($res as $item) {
            $value = $item->getV();
            $value_arr = explode(';', $value);
            $res_arr[] = ['x' => $arr_assoc[$item->getK()], 'y' => $value_arr[0], 's' => 'thermal'];
        }

        exit(json_encode($res_arr));
    }
}

function handlePCT($x, $y, $xx, $yy): array
{
    $query = m('data_vw');

    $res = $query->where(['k' => [$x, $y]])->findAll();

    $arr1 = ['x' => $xx, 'y' => 0];
    $arr2 = ['x' => $yy, 'y' => 0];

    $total_amount = 0;
    foreach ($res as $item) {
        if ($item->getK() == $x) {
            $arr1['y'] = $item->getV();
        }
        if ($item->getK() == $y) {
            $arr2['y'] = $item->getV();
        }
        $total_amount += $item->getV();
    }

    if ($total_amount < 100) {
        $o_amount = 100 - $total_amount;

        return [$arr1, $arr2, ['x' => '其他', 'y' => $o_amount]];
    } else {
        return [$arr1, $arr2];
    }
}

function handleFreq($k_init, $k_freq, $k_s1, $k_s2): int
{
    $query = m('data_vw');
    $res = $query->where(['k' => [$k_init, $k_freq, $k_s1, $k_s2]])->findAll();
    $dt = time();
    $arr = [];
    $init_data = null;
    $init_dt = null;
    foreach ($res as $item) {
        if ($item->getK() == $k_init) {
            $init_dt = $item->getCreatetime();
            $init_data = $item;
        }
        $arr[$item->getK()] = $item->getV();
    }
    $value = 0;
    if ($init_dt) {
        $dt_section = $dt - $init_dt;
        $freq = ceil($dt_section / $arr[$k_freq]);
        if ($freq > 1) {
            $_r = rand($arr[$k_s1], $arr[$k_s2]);
            $_dt = $init_dt + $freq * $arr[$k_freq];
            $value = $arr[$k_init] + $_r * $freq;
            $init_data->setV($value);
            $init_data->setCreatetime($_dt);
            $init_data->save();
        } else {
            $value = $arr[$k_init];
        }
    }

    return intval($value);
}
