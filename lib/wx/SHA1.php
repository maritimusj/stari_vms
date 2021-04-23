<?php


namespace wx;

/**
 * SHA1 class
 *
 * 计算公众平台的消息签名接口.
 */
class SHA1
{
    /**
     * 用SHA1算法生成安全签名
     * @param string $token 票据
     * @param string $timestamp 时间戳
     * @param string $nonce 随机字符串
     * @param string $encrypt_msg
     * @return array
     */
    public function getSHA1(string $token, string $timestamp, string $nonce, string $encrypt_msg): array
    {
        $array = array($encrypt_msg, $token, $timestamp, $nonce);

        sort($array, SORT_STRING);

        $str = sha1(implode($array));

        return array(ErrorCode::OK, $str);
    }
}
