<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

class IDCardUtil
{
    public static function getGender($card_number): string
    {
        // 如果身份证号码是 15 位，则将其转换为 18 位
        if (strlen($card_number) == 15) {
            $card_number = substr($card_number, 0, 6).'19'.substr($card_number, 6, 9);
        }
        // 获取身份证号码中的第 17 位数字
        $gender_code = substr($card_number, 16, 1);
        // 判断性别代码的奇偶性
        if ($gender_code % 2 == 0) {
            // 偶数为女性
            return '女';
        } else {
            // 奇数为男性
            return '男';
        }
    }

    public static function validate($card_number)
    {
        // 验证身份证号码长度
        if (strlen($card_number) != 15 && strlen($card_number) != 18) {
            return err('身份证号码长度不正确！');
        }
        // 验证身份证号码格式
        if (!preg_match('/^\d{15}$|^\d{17}[\dXx]$/', $card_number)) {
            return err('身份证号码只能是数字或字母X！');
        }
        // 验证身份证号码中的校验码
        if (strlen($card_number) == 18) {
            $card_number = strtoupper($card_number);
            $sum = 0;
            for ($i = 0; $i < 17; $i++) {
                $sum += ($card_number[$i] - '0') * pow(2, 17 - $i);
            }
            $mod = $sum % 11;
            $check_code = '10X98765432';
            if ($card_number[17] != $check_code[$mod]) {
                return err('身份证号码不正确！');
            }
        }

        return true;
    }
}