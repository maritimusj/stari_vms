<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace wx;

/**
 * PKCS7Encoder class
 *
 * 提供基于PKCS7算法的加解密接口.
 */
class PKCS7Encoder
{
    const BLOCK_SIZE = 32;

    /**
     * 对需要加密的明文进行填充补位
     * @param $text string 需要进行填充补位操作的明文
     * @return string 补齐明文字符串
     */
    function encode(string $text): string
    {
        $text_length = strlen($text);
        //计算需要填充的位数
        $amount_to_pad = PKCS7Encoder::BLOCK_SIZE - ($text_length % PKCS7Encoder::BLOCK_SIZE);
        if ($amount_to_pad == 0) {
            $amount_to_pad = PKCS7Encoder::BLOCK_SIZE;
        }
        //获得补位所用的字符
        $pad_chr = chr($amount_to_pad);
        $tmp = str_repeat($pad_chr, $amount_to_pad);
        return $text . $tmp;
    }

    /**
     * 对解密后的明文进行补位删除
     * @param string $text 解密后的明文
     * @return string 删除填充补位后的明文
     */
    function decode(string $text): string
    {
        $pad = ord(substr($text, -1));
        if ($pad < 1 || $pad > 32) {
            $pad = 0;
        }
        return substr($text, 0, (strlen($text) - $pad));
    }

}