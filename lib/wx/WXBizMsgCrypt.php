<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace wx;

/**
 * 1.第三方回复加密消息给公众平台；
 * 2.第三方收到公众平台发送的消息，验证消息的安全性，并对消息进行解密。
 */
class WXBizMsgCrypt
{
    private $token;
    private $encoding_aes_key;
    private $app_id;

    /**
     * 构造函数
     * @param $token string 公众平台上，开发者设置的token
     * @param $encoding_aes_key string 公众平台上，开发者设置的EncodingAESKey
     * @param $app_id string 公众平台的appId
     */
    public function __construct(string $app_id, string $token, string $encoding_aes_key)
    {
        $this->app_id = $app_id;
        $this->token = $token;
        $this->encoding_aes_key = $encoding_aes_key;
    }

    /**
     * 将公众平台回复用户的消息加密打包.
     * <ol>
     *    <li>对要发送的消息进行AES-CBC加密</li>
     *    <li>生成安全签名</li>
     *    <li>将消息密文和安全签名打包成xml格式</li>
     * </ol>
     *
     * @param $reply_msg string 公众平台待回复用户的消息，xml格式的字符串
     * @param $timestamp string 时间戳，可以自己生成，也可以用URL参数的timestamp
     * @param $nonce string 随机串，可以自己生成，也可以用URL参数的nonce
     * @return array array[0]错误码，成功0，失败返回对应的错误码,array[1]加密后的可以直接回复用户的密文，包括msg_signature, timestamp, nonce, encrypt的xml格式的字符串
     */
    public function encryptMsg(string $reply_msg, string $timestamp, string $nonce): array
    {
        //加密
        list($ret, $encrypt) = (new Prpcrypt($this->encoding_aes_key))->encrypt($this->app_id, $reply_msg);
        if ($ret != ErrorCode::OK) {
            return [$ret, ''];
        }

        if ($timestamp == null) {
            $timestamp = time();
        }

        //生成安全签名
        list($ret, $signature) = (new SHA1())->getSHA1($this->token, $timestamp, $nonce, $encrypt);
        if ($ret != ErrorCode::OK) {
            return [$ret, ''];
        }

        //生成发送的xml
        $encryptMsg = (new XMLParse())->generate($encrypt, $signature, $timestamp, $nonce);

        return [ErrorCode::OK, $encryptMsg];
    }


    /**
     * 检验消息的真实性，并且获取解密后的明文.
     * <ol>
     *    <li>利用收到的密文生成安全签名，进行签名验证</li>
     *    <li>若验证通过，则提取xml中的加密消息</li>
     *    <li>对消息进行解密</li>
     * </ol>
     *
     * @param $msg_signature string 签名串，对应URL参数的msg_signature
     * @param $timestamp string 时间戳 对应URL参数的timestamp
     * @param $nonce string 随机串，对应URL参数的nonce
     * @param $post_data string 密文，对应POST请求的数据
     * @return array array[0]错误码，成功0，失败返回对应的错误码,array[1]解密后的原文
     */
    public function decryptMsg(string $msg_signature, string $timestamp, string $nonce, string $post_data): array
    {
        if (strlen($this->encoding_aes_key) != 43) {
            return [ErrorCode::IllegalAesKey, ''];
        }

        //提取密文
        list($ret, $encrypt,) = (new XMLParse())->extract($post_data);

        if ($ret != ErrorCode::OK) {
            return [$ret, ''];
        }

        if (empty($timestamp)) {
            $timestamp = time();
        }

        //验证安全签名
        list($ret, $signature) = (new SHA1())->getSHA1($this->token, $timestamp, $nonce, $encrypt);

        if ($ret != ErrorCode::OK) {
            return [$ret, ''];
        }

        if ($signature != $msg_signature) {
            return [ErrorCode::ValidateSignatureError, ''];
        }

        list($ret, $decrypted) = (new Prpcrypt($this->encoding_aes_key))->decrypt($this->app_id, $encrypt);

        if ($ret != ErrorCode::OK) {
            return [$ret, ''];
        }

        return [ErrorCode::OK, $decrypted];
    }

}