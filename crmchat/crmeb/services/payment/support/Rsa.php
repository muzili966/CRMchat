<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\services\payment\support;

use crmeb\services\payment\PaymentException;

/**
 * SHA256withRSA 签名与验签
 *
 * 支付宝 RSA2 与微信 v3 用的是同一种算法，差别只在待签字符串怎么拼，由各渠道负责。
 * 密钥接受三种写法：PEM 原文、PEM 文件路径、支付宝后台复制出来的去头尾 base64。
 */
final class Rsa
{
    /**
     * 能识别为密钥文件的扩展名
     */
    const KEY_FILE_PATTERN = '/\.(pem|crt|key)$/i';

    /**
     * 私钥的两种封装：PKCS#8 与 PKCS#1，去头尾的 base64 看不出是哪种，依次尝试
     */
    const PRIVATE_KEY_TYPES = ['PRIVATE KEY', 'RSA PRIVATE KEY'];

    /**
     * @param string $data 待签字符串
     * @param string $privateKey
     * @return string base64 签名
     */
    public static function sign(string $data, string $privateKey): string
    {
        if (!openssl_sign($data, $signature, self::loadPrivate($privateKey), OPENSSL_ALGO_SHA256)) {
            throw new PaymentException('签名失败：' . openssl_error_string());
        }
        return base64_encode($signature);
    }

    /**
     * 验签
     *
     * 公钥本身解析不了属于配置错误，抛异常而不是返回 false：否则运维配错公钥时，
     * 日志里看到的只会是「验签失败」，会误判成有人伪造回调。
     * @param string $data
     * @param string $signature base64 签名
     * @param string $publicKey 公钥或证书
     * @return bool
     */
    public static function verify(string $data, string $signature, string $publicKey): bool
    {
        $key = openssl_pkey_get_public(self::toPem($publicKey, 'PUBLIC KEY'));
        if ($key === false) {
            throw new PaymentException('平台公钥无法解析，请检查配置');
        }
        $raw = base64_decode($signature, true);
        if ($raw === false || $raw === '') {
            return false;
        }
        return openssl_verify($data, $raw, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * @param string $privateKey
     * @return resource|\OpenSSLAsymmetricKey
     */
    private static function loadPrivate(string $privateKey)
    {
        foreach (self::PRIVATE_KEY_TYPES as $type) {
            $key = openssl_pkey_get_private(self::toPem($privateKey, $type));
            if ($key !== false) {
                return $key;
            }
        }
        throw new PaymentException('商户私钥无法解析，请检查配置');
    }

    /**
     * 规范成 PEM
     * @param string $key
     * @param string $type 去头尾 base64 时要补的类型
     * @return string
     */
    private static function toPem(string $key, string $type): string
    {
        $key = trim($key);
        if (preg_match(self::KEY_FILE_PATTERN, $key) && is_file($key)) {
            $key = trim((string)file_get_contents($key));
        }
        if (strpos($key, '-----BEGIN') !== false) {
            return $key;
        }
        $body = chunk_split((string)preg_replace('/\s+/', '', $key), 64, "\n");
        return "-----BEGIN {$type}-----\n{$body}-----END {$type}-----";
    }
}
