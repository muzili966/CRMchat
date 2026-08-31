<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2020 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace app\services;


use app\dao\ApplicationThemeDao;
use app\models\ApplicationTheme;
use crmeb\basic\BaseServices;
use crmeb\exceptions\AdminException;
use crmeb\services\CacheService;
use crmeb\services\tenant\TenantContext;
use think\facade\Log;

/**
 * 客户端装修service
 * Class ApplicationThemeServices
 * @package app\services
 */
class ApplicationThemeServices extends BaseServices
{

    /**
     * 装修配置缓存key前缀与TTL（访客端热路径，配置变更最迟5分钟生效，保存时会主动清除）
     */
    const CACHE_PREFIX = 'app_theme:';
    const CACHE_TTL = 300;

    /**
     * 品牌自定义套餐开关字段
     */
    const FEATURE_BRAND_CUSTOM = 'brand_custom';

    /**
     * 去平台标识（白标）套餐开关字段
     */
    const FEATURE_WHITE_LABEL = 'white_label';

    /**
     * 自定义广告位能力：未开通的租户展示平台默认广告
     */
    const FEATURE_CUSTOM_AD = 'custom_ad';

    /**
     * 装修字段与所需订阅能力的对应关系
     *
     * 未列出的字段（窗口标题、主题色）不设门槛：新租户接进来第一眼就是自家名字与配色，
     * 否则客服窗口顶着别人的品牌，反而降低付费意愿。
     * 前端按同一份关系决定展示哪些表单项，此处是服务端的权威口径。
     */
    /** 受限能力被触碰时的提示语 */
    const FEATURE_MESSAGES = [
        self::FEATURE_BRAND_CUSTOM => '当前套餐不支持自定义LOGO与界面风格，请升级套餐',
        self::FEATURE_CUSTOM_AD => '当前套餐不支持自定义广告位，升级后可投放自有广告',
        self::FEATURE_WHITE_LABEL => '当前套餐不支持隐藏平台标识，请升级套餐',
    ];

    const FIELD_FEATURES = [
        'logo' => self::FEATURE_BRAND_CUSTOM,
        'theme_style' => self::FEATURE_BRAND_CUSTOM,
        'bubble_style' => self::FEATURE_BRAND_CUSTOM,
        'pc_icon' => self::FEATURE_BRAND_CUSTOM,
        'mobile_icon' => self::FEATURE_BRAND_CUSTOM,
        'banners' => self::FEATURE_CUSTOM_AD,
        'custom_html' => self::FEATURE_CUSTOM_AD,
        'show_platform_brand' => self::FEATURE_WHITE_LABEL,
    ];

    /**
     * 平台默认广告的配置项
     */
    const CONFIG_PLATFORM_BANNERS = 'platform_ad_banners';
    const CONFIG_PLATFORM_AD_HTML = 'platform_ad_html';

    /**
     * 主题色格式：#RRGGBB
     */
    const COLOR_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    /**
     * 轮播广告允许的链接协议，拦截javascript:等伪协议注入访客窗口
     */
    const SAFE_LINK_SCHEMES = ['http://', 'https://'];

    /**
     * 仅服务端使用、不下发给访客端的字段
     */
    const INTERNAL_FIELDS = ['id', 'tenant_id', 'create_time', 'update_time'];

    /**
     * 自定义广告HTML中需连内容一起剥离的标签，访客端以v-html渲染，留下即等于任意脚本执行
     */
    const DANGEROUS_TAGS = ['script', 'iframe', 'object', 'embed'];

    /**
     * on*事件属性（onerror/onclick等），三种取值写法：双引号、单引号、裸值
     */
    const EVENT_ATTR_PATTERN = '/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/i';

    /**
     * javascript伪协议；浏览器解析URL时会忽略协议名内的空白与控制字符，故逐字符允许空白以防绕过
     */
    const JS_PROTOCOL_PATTERN = '/j\s*a\s*v\s*a\s*s\s*c\s*r\s*i\s*p\s*t\s*:/i';

    /**
     * ApplicationThemeServices constructor.
     * @param ApplicationThemeDao $dao
     */
    public function __construct(ApplicationThemeDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取应用装修配置（带缓存），未配置返回平台默认外观
     * @param string $appid
     * @return array
     */
    public function getTheme(string $appid): array
    {
        if ($appid === '') {
            return self::defaultTheme();
        }
        $theme = $this->loadTheme($appid);
        return $theme ? self::formatTheme($theme) : self::defaultTheme();
    }

    /**
     * 访客端装修配置：按套餐降级并剔除内部字段
     * @param string $appid
     * @return array
     */
    /**
     * 用平台默认广告替换租户自定义广告
     *
     * 免费/低档套餐的客服窗口由平台统一投放，既是能力分层也是平台自身的曝光位
     * @param array $theme
     * @return array
     */
    protected static function applyPlatformAd(array $theme): array
    {
        //不用sys_config：它会遍历数组元素做路径替换，而广告是对象数组会触发类型错误
        $theme['banners'] = self::normalizeBanners(self::platformConfig(self::CONFIG_PLATFORM_BANNERS));
        $theme['custom_html'] = self::sanitizeHtml((string)self::platformConfig(self::CONFIG_PLATFORM_AD_HTML));
        return $theme;
    }

    /**
     * 读平台层配置原始值
     * @param string $name
     * @return mixed
     */
    protected static function platformConfig(string $name)
    {
        try {
            return TenantContext::withoutTenant(function () use ($name) {
                return app()->make(\app\services\system\config\SystemConfigServices::class)
                    ->getConfigValue($name);
            });
        } catch (\Throwable $e) {
            Log::error('读取平台广告配置失败：' . $e->getMessage());
            return '';
        }
    }

    public function getPublicTheme(string $appid): array
    {
        $theme = $this->getTheme($appid);
        $tenantId = (int)($theme['tenant_id'] ?? 0);
        /** @var TenantPlanServices $planServices */
        $planServices = app()->make(TenantPlanServices::class);
        //未购买品牌自定义的租户，访客端一律呈现平台默认外观
        if (!$planServices->hasFeature($tenantId, self::FEATURE_BRAND_CUSTOM)) {
            $theme = self::defaultTheme();
        }
        if (!$planServices->hasFeature($tenantId, self::FEATURE_WHITE_LABEL)) {
            $theme['show_platform_brand'] = ApplicationTheme::BRAND_SHOW;
        }
        //广告位单独判定：未开通自定义广告的租户回落平台默认广告，使免费版也成为平台的曝光位
        if (!$planServices->hasFeature($tenantId, self::FEATURE_CUSTOM_AD)) {
            $theme = self::applyPlatformAd($theme);
        }
        //应用未单独配置的项回落租户全局设置
        $theme = self::inheritGlobal($theme);
        return array_diff_key($theme, array_flip(self::INTERNAL_FIELDS));
    }

    /**
     * 保存应用装修配置（不存在则新建）
     * @param string $appid
     * @param array $data
     * @return bool
     */
    public function saveTheme(string $appid, array $data)
    {
        $tenantId = $this->tenantIdByAppid($appid);
        if (!$tenantId) {
            throw new AdminException('应用不存在');
        }
        $payload = self::buildPayload($data);
        $this->validatePayload($payload, $data);
        $result = TenantContext::runAs($tenantId, function () use ($appid, $tenantId, $payload) {
            /** @var TenantPlanServices $planServices */
            $planServices = app()->make(TenantPlanServices::class);
            $can = function ($feature) use ($planServices, $tenantId) {
                return $planServices->hasFeature($tenantId, $feature);
            };
            $stored = $this->storedTheme($appid);
            //仅当确实想改动受限字段时才报错；前端把这些项置为禁用后会原样回传，不应误报
            if ($blocked = self::blockedFeature($payload, $stored, $can)) {
                throw new AdminException(self::FEATURE_MESSAGES[$blocked]);
            }
            //兜底：即便提交值与已存值一致，也按已存值写入，避免默认值抹掉配置
            $payload = self::applyEntitlement($payload, $stored, $can);
            return $this->persist($appid, $tenantId, $payload);
        });
        $this->clearCache($appid);
        return $result;
    }

    /**
     * 清除装修配置缓存
     * @param string $appid
     * @return void
     */
    public function clearCache(string $appid): void
    {
        try {
            CacheService::redisHandler()->delete(self::CACHE_PREFIX . $appid);
        } catch (\Throwable $e) {
            Log::error('清除装修配置缓存失败：' . $e->getMessage());
        }
    }

    /**
     * 平台默认装修（纯函数）
     * @return array
     */
    public static function defaultTheme(): array
    {
        return [
            'title' => '',
            'logo' => '',
            'theme_color' => ApplicationTheme::DEFAULT_THEME_COLOR,
            'theme_style' => ApplicationTheme::DEFAULT_THEME_STYLE,
            'bubble_style' => ApplicationTheme::DEFAULT_BUBBLE_STYLE,
            'pc_icon' => '',
            'mobile_icon' => '',
            'banners' => [],
            'custom_html' => '',
            'show_platform_brand' => ApplicationTheme::BRAND_SHOW,
            //空值表示继承「客服端配置」里的租户全局设置，避免多应用租户重复配置
            'tourist_avatar' => [],
            'service_feedback' => '',
        ];
    }

    /**
     * 补齐继承自租户全局设置的项
     *
     * 采用与"平台默认+租户覆盖"一致的两层模型：应用未单独配置时回落到全局，
     * 单应用租户只需在「客服端配置」配一次，多应用租户可按应用差异化
     * @param array $theme
     * @return array
     */
    public static function inheritGlobal(array $theme): array
    {
        if (empty($theme['tourist_avatar'])) {
            $avatar = sys_config('tourist_avatar', []);
            $theme['tourist_avatar'] = is_array($avatar) ? $avatar : [];
        }
        if (trim((string)$theme['service_feedback']) === '') {
            $theme['service_feedback'] = (string)sys_config('service_feedback', '');
        }
        return $theme;
    }

    /**
     * 补全缺省字段并归一化（纯函数）
     * @param array $theme
     * @return array
     */
    public static function formatTheme(array $theme): array
    {
        //读取侧同样清洗：入库前的存量数据与绕过服务层的直写都可能带脚本
        return array_merge(self::defaultTheme(), $theme, [
            'theme_color' => self::sanitizeColor((string)($theme['theme_color'] ?? '')),
            'theme_style' => self::sanitizeThemeStyle(self::stringValue($theme, 'theme_style')),
            'bubble_style' => self::sanitizeBubbleStyle(self::stringValue($theme, 'bubble_style')),
            'banners' => self::normalizeBanners($theme['banners'] ?? []),
            'custom_html' => self::sanitizeHtml(self::stringValue($theme, 'custom_html')),
            'show_platform_brand' => self::normalizeBrand($theme['show_platform_brand'] ?? ApplicationTheme::BRAND_SHOW),
            'tourist_avatar' => self::normalizeAvatars($theme['tourist_avatar'] ?? []),
            'service_feedback' => self::stringValue($theme, 'service_feedback'),
        ]);
    }

    /**
     * 归一化轮播广告为[['image'=>..,'link'=>..,'sort'=>int]]（纯函数）
     * 脏数据直接丢弃、按sort升序，并按上限截断以防存量超量数据撑爆访客窗口
     * @param mixed $banners json字符串或数组
     * @return array
     */
    public static function normalizeBanners($banners): array
    {
        $items = array_map([self::class, 'normalizeBannerItem'], self::decodeRows($banners));
        $sorted = self::sortBanners(array_values(array_filter($items)));
        return array_slice($sorted, 0, ApplicationTheme::MAX_BANNERS);
    }

    /**
     * 校验并规范化主题色（纯函数），非法值回退默认色
     * @param string $color
     * @return string
     */
    public static function sanitizeColor(string $color): string
    {
        $value = trim($color);
        return self::isValidColor($value) ? strtolower($value) : ApplicationTheme::DEFAULT_THEME_COLOR;
    }

    /**
     * 校验并规范化主题风格（纯函数），非法值回退默认风格
     * @param string $style
     * @return string
     */
    public static function sanitizeThemeStyle(string $style): string
    {
        $value = trim($style);
        return in_array($value, ApplicationTheme::THEME_STYLES, true)
            ? $value
            : ApplicationTheme::DEFAULT_THEME_STYLE;
    }

    /**
     * 校验并规范化气泡风格（纯函数），非法值回退默认风格
     * @param string $style
     * @return string
     */
    public static function sanitizeBubbleStyle(string $style): string
    {
        $value = trim($style);
        return in_array($value, ApplicationTheme::BUBBLE_STYLES, true)
            ? $value
            : ApplicationTheme::DEFAULT_BUBBLE_STYLE;
    }

    /**
     * 是否合法的#RRGGBB色值（纯函数）
     * @param string $color
     * @return bool
     */
    public static function isValidColor(string $color): bool
    {
        return (bool)preg_match(self::COLOR_PATTERN, trim($color));
    }

    /**
     * 链接是否可安全跳转（纯函数），空链接视为不可跳转
     * @param string $link
     * @return bool
     */
    public static function isSafeLink(string $link): bool
    {
        foreach (self::SAFE_LINK_SCHEMES as $scheme) {
            if (stripos($link, $scheme) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * 组装入库字段（纯函数）
     * @param array $data
     * @return array
     */
    /**
     * 受限字段中第一个被实际改动的能力，无则返回空串
     * @param array $payload
     * @param array $stored
     * @param callable $can
     * @return string
     */
    public static function blockedFeature(array $payload, array $stored, callable $can): string
    {
        $allowed = [];
        foreach (self::FIELD_FEATURES as $field => $feature) {
            $allowed[$feature] = $allowed[$feature] ?? (bool)$can($feature);
            if ($allowed[$feature] || !array_key_exists($field, $payload)) {
                continue;
            }
            $current = array_key_exists($field, $stored) ? $stored[$field] : self::defaultStoredValue($field);
            //宽松比较：库里的数字型字段取出来是字符串
            if ((string)$payload[$field] !== (string)$current) {
                return $feature;
            }
        }
        return '';
    }

    /**
     * 已存主题的原始行，不做格式化，用于门禁回填
     * @param string $appid
     * @return array
     */
    protected function storedTheme(string $appid): array
    {
        $row = $this->dao->get(['appid' => $appid]);
        return $row ? $row->toArray() : [];
    }

    /**
     * 按订阅能力过滤待写入字段：无权限的字段沿用已存值，避免被表单默认值清空
     * @param array $payload buildPayload的结果
     * @param array $current 当前已存主题
     * @param callable $can 能力判定 fn(string $feature): bool
     * @return array
     */
    public static function applyEntitlement(array $payload, array $current, callable $can): array
    {
        $allowed = [];
        foreach (self::FIELD_FEATURES as $field => $feature) {
            $allowed[$feature] = $allowed[$feature] ?? (bool)$can($feature);
            if ($allowed[$feature] || !array_key_exists($field, $payload)) {
                continue;
            }
            //字段在库里以原始形态存放，取不到时回落到该字段的默认值
            $payload[$field] = array_key_exists($field, $current)
                ? $current[$field]
                : self::defaultStoredValue($field);
        }
        return $payload;
    }

    /**
     * 字段在数据表中的默认存值
     * @param string $field
     * @return mixed
     */
    protected static function defaultStoredValue(string $field)
    {
        if ($field === 'banners') {
            return json_encode([], JSON_UNESCAPED_UNICODE);
        }
        if ($field === 'show_platform_brand') {
            return ApplicationTheme::BRAND_SHOW;
        }
        //枚举字段的默认值不是空串，漏掉会让新应用的首次保存被误判成"想改受限字段"
        if ($field === 'theme_style') {
            return ApplicationTheme::DEFAULT_THEME_STYLE;
        }
        if ($field === 'bubble_style') {
            return ApplicationTheme::DEFAULT_BUBBLE_STYLE;
        }
        return '';
    }

    public static function buildPayload(array $data): array
    {
        return [
            'title' => self::stringValue($data, 'title'),
            'logo' => self::stringValue($data, 'logo'),
            'theme_color' => self::sanitizeColor(self::stringValue($data, 'theme_color')),
            'theme_style' => self::sanitizeThemeStyle(self::stringValue($data, 'theme_style')),
            'bubble_style' => self::sanitizeBubbleStyle(self::stringValue($data, 'bubble_style')),
            'pc_icon' => self::stringValue($data, 'pc_icon'),
            'mobile_icon' => self::stringValue($data, 'mobile_icon'),
            'banners' => json_encode(self::normalizeBanners($data['banners'] ?? []), JSON_UNESCAPED_UNICODE),
            'custom_html' => self::sanitizeHtml(self::stringValue($data, 'custom_html')),
            'show_platform_brand' => self::normalizeBrand($data['show_platform_brand'] ?? ApplicationTheme::BRAND_SHOW),
            //留空即继承租户全局设置，不写死当前全局值，避免全局改动后应用不跟随
            'tourist_avatar' => json_encode(self::normalizeAvatars($data['tourist_avatar'] ?? []), JSON_UNESCAPED_UNICODE),
            'service_feedback' => self::stringValue($data, 'service_feedback'),
        ];
    }

    /**
     * 规整游客头像列表
     * @param mixed $avatars
     * @return array
     */
    public static function normalizeAvatars($avatars): array
    {
        if (is_string($avatars)) {
            $avatars = json_decode($avatars, true);
        }
        if (!is_array($avatars)) {
            return [];
        }
        $list = [];
        foreach ($avatars as $item) {
            $url = is_array($item) ? (string)($item['url'] ?? '') : (string)$item;
            $url = trim($url);
            if ($url !== '') {
                $list[] = $url;
            }
        }
        return array_values(array_unique($list));
    }

    /**
     * 读取配置：appid寻址跨租户，需逃逸执行
     * @param string $appid
     * @return array
     */
    protected function loadTheme(string $appid): array
    {
        try {
            return CacheService::redisHandler()->remember(self::CACHE_PREFIX . $appid, function () use ($appid) {
                return TenantContext::withoutTenant(function () use ($appid) {
                    $theme = $this->dao->getByAppid($appid);
                    return $theme ? $theme->toArray() : [];
                });
            }, self::CACHE_TTL) ?: [];
        } catch (\Throwable $e) {
            Log::error('读取装修配置失败：' . $e->getMessage());
            return [];
        }
    }

    /**
     * 落库：按appid唯一键决定更新还是插入
     * @param string $appid
     * @param int $tenantId
     * @param array $payload
     * @return bool
     */
    protected function persist(string $appid, int $tenantId, array $payload)
    {
        $payload['update_time'] = time();
        $id = (int)$this->dao->value(['appid' => $appid], 'id');
        if ($id) {
            return false !== $this->dao->update(['id' => $id, 'appid' => $appid], $payload);
        }
        $payload['appid'] = $appid;
        $payload['tenant_id'] = $tenantId;
        $payload['create_time'] = time();
        return (bool)$this->dao->save($payload);
    }

    /**
     * 应用所属租户ID，应用不存在返回0
     * @param string $appid
     * @return int
     */
    protected function tenantIdByAppid(string $appid): int
    {
        /** @var TenantServices $tenantServices */
        $tenantServices = app()->make(TenantServices::class);
        return $tenantServices->tenantIdByAppid($appid);
    }

    /**
     * 校验各字段：色值以原值判定，避免sanitize后的回退掩盖用户的错误输入
     * @param array $payload
     * @param array $data
     * @return void
     */
    protected function validatePayload(array $payload, array $data): void
    {
        if (mb_strlen($payload['title']) > ApplicationTheme::MAX_TITLE) {
            throw new AdminException('窗口标题最多' . ApplicationTheme::MAX_TITLE . '个字');
        }
        $color = self::stringValue($data, 'theme_color');
        if ($color !== '' && !self::isValidColor($color)) {
            throw new AdminException('主题色格式不正确，请使用#RRGGBB');
        }
        //以原值判长，避免清洗掉的脚本为超长内容让出配额
        if (mb_strlen(self::stringValue($data, 'custom_html')) > ApplicationTheme::MAX_CUSTOM_HTML) {
            throw new AdminException('自定义广告内容最多' . ApplicationTheme::MAX_CUSTOM_HTML . '个字符');
        }
        $this->validateBanners($data['banners'] ?? []);
    }

    /**
     * 校验轮播广告条数与结构：归一化后条数变少说明存在缺图片的脏数据
     * @param mixed $banners
     * @return void
     */
    protected function validateBanners($banners): void
    {
        $rows = self::decodeRows($banners);
        if (count($rows) > ApplicationTheme::MAX_BANNERS) {
            throw new AdminException('轮播广告最多' . ApplicationTheme::MAX_BANNERS . '张');
        }
        if (count(self::normalizeBanners($banners)) !== count($rows)) {
            throw new AdminException('轮播广告格式不正确，每张需选择图片');
        }
        $this->validateBannerLinks($rows);
    }

    /**
     * 校验轮播广告链接协议
     * @param array $rows
     * @return void
     */
    protected function validateBannerLinks(array $rows): void
    {
        foreach ($rows as $row) {
            $link = self::stringValue($row, 'link');
            if ($link !== '' && !self::isSafeLink($link)) {
                throw new AdminException('轮播广告链接必须以http://或https://开头');
            }
        }
    }

    /**
     * 清洗自定义广告HTML（纯函数）：剥离危险标签、on*事件属性与javascript伪协议
     * 只做减法不做白名单，保证租户已有的排版标签不被误伤；
     * preg_replace回溯超限时返回null，此处统一转空串——宁可广告位不展示，也不能把未清洗内容下发给访客
     * @param string $html
     * @return string
     */
    protected static function sanitizeHtml(string $html): string
    {
        $value = trim($html);
        if ($value === '') {
            return '';
        }
        $stripped = array_reduce(self::DANGEROUS_TAGS, [self::class, 'stripTag'], $value);
        $withoutEvent = preg_replace(self::EVENT_ATTR_PATTERN, '', $stripped);
        return (string)preg_replace(self::JS_PROTOCOL_PATTERN, '', (string)$withoutEvent);
    }

    /**
     * 移除指定标签及其内容（纯函数），未闭合或孤立的标签也一并清掉
     * @param string $html
     * @param string $tag
     * @return string
     */
    protected static function stripTag(string $html, string $tag): string
    {
        $paired = preg_replace('/<' . $tag . '\b[^>]*>.*?<\/\s*' . $tag . '\s*>/is', '', $html);
        return (string)preg_replace('/<\/?\s*' . $tag . '\b[^>]*>/i', '', (string)$paired);
    }

    /**
     * json字符串或数组统一为行数组（纯函数）
     * @param mixed $rows
     * @return array
     */
    protected static function decodeRows($rows): array
    {
        $decoded = is_string($rows) ? json_decode($rows, true) : $rows;
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * 归一化单条轮播广告，缺图片返回null
     * @param mixed $row
     * @return array|null
     */
    protected static function normalizeBannerItem($row)
    {
        if (!is_array($row)) {
            return null;
        }
        $image = self::stringValue($row, 'image');
        if ($image === '') {
            return null;
        }
        $link = self::stringValue($row, 'link');
        //存量或异常写入的伪协议链接在读取侧同样清空，保证访客端拿到的链接始终可安全跳转
        return [
            'image' => $image,
            'link' => self::isSafeLink($link) ? $link : '',
            'sort' => self::intValue($row, 'sort'),
        ];
    }

    /**
     * 按sort升序（纯函数），PHP7.4的usort不稳定，附带原序号保证同序值维持提交顺序
     * @param array $items
     * @return array
     */
    protected static function sortBanners(array $items): array
    {
        $indexed = array_map(function ($index, $item) {
            return [$index, $item];
        }, array_keys($items), $items);
        usort($indexed, function ($a, $b) {
            return [$a[1]['sort'], $a[0]] <=> [$b[1]['sort'], $b[0]];
        });
        return array_column($indexed, 1);
    }

    /**
     * 取标量字段的字符串值（纯函数），非标量视为空
     * @param array $row
     * @param string $key
     * @return string
     */
    protected static function stringValue(array $row, string $key): string
    {
        return isset($row[$key]) && is_scalar($row[$key]) ? trim((string)$row[$key]) : '';
    }

    /**
     * 取数字字段的整数值（纯函数），非数字视为0
     * @param array $row
     * @param string $key
     * @return int
     */
    protected static function intValue(array $row, string $key): int
    {
        return isset($row[$key]) && is_numeric($row[$key]) ? (int)$row[$key] : 0;
    }

    /**
     * 归一化平台标识开关（纯函数）
     * @param mixed $value
     * @return int
     */
    protected static function normalizeBrand($value): int
    {
        return (int)$value === ApplicationTheme::BRAND_HIDE ? ApplicationTheme::BRAND_HIDE : ApplicationTheme::BRAND_SHOW;
    }
}
