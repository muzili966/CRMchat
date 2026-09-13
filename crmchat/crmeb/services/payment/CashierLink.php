<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace crmeb\services\payment;

/**
 * 收银台链接签名
 *
 * 链接、二维码、聊天卡片都指向收银台，付款人不登录就能打开，所以链接本身要带凭证：
 * 签名绑定单号与过期时间，改单号去窥探别人的账单、或把过期时间往后改都验不过。
 * 金额不在签名里——金额从来不经过链接，收银台一律读库。
 */
final class CashierLink
{
    /**
     * 收银台页面路径，与前端路由一致
     */
    const PATH = '/pay/cashier';

    const ALGO = 'sha256';

    /**
     * 链接参数名
     *
     * 签名不能叫 s：ThinkPHP 把 s 当作 pathinfo 变量，带上它整条请求会被路由到别处。
     */
    const QUERY_NO = 'no';

    const QUERY_SIGN = 'sign';

    /**
     * @param string $payNo
     * @param int $expireAt 取自支付单，不取自链接
     * @param string $key
     * @return string url 安全的 base64 签名
     */
    public static function sign(string $payNo, int $expireAt, string $key): string
    {
        if ($key === '') {
            throw new PaymentException('缺少收银台签名密钥');
        }
        $raw = hash_hmac(self::ALGO, $payNo . '|' . $expireAt, $key, true);
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * 比较用 hash_equals，避免按字节提前返回泄露签名
     * @param string $payNo
     * @param int $expireAt
     * @param array $credential signature/key
     * @return bool
     */
    public static function verify(string $payNo, int $expireAt, array $credential): bool
    {
        $signature = (string)($credential['signature'] ?? '');
        return $signature !== '' && hash_equals(self::sign($payNo, $expireAt, (string)($credential['key'] ?? '')), $signature);
    }

    /**
     * 完整收银台地址
     * @param string $origin 对外地址，二维码要能被手机扫开，必须是绝对地址
     * @param string $payNo
     * @param string $signature
     * @return string
     */
    public static function url(string $origin, string $payNo, string $signature): string
    {
        $origin = rtrim($origin, '/');
        if ($origin === '') {
            throw new PaymentException('未配置对外地址 SERVICE_URL，无法生成支付链接');
        }
        return $origin . self::PATH . '?' . http_build_query([self::QUERY_NO => $payNo, self::QUERY_SIGN => $signature]);
    }
}
