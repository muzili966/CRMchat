<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\export;

use app\services\chat\ChatHistoryServices;

/**
 * 历史对话导出器
 * Class ChatHistoryExporter
 * @package app\services\export
 */
class ChatHistoryExporter implements ExporterInterface
{
    /**
     * @var ChatHistoryServices
     */
    protected $services;

    /**
     * @param ChatHistoryServices $services
     */
    public function __construct(ChatHistoryServices $services)
    {
        $this->services = $services;
    }

    public function name(): string
    {
        return '历史对话';
    }

    public function prefix(): string
    {
        return 'chat_all_';
    }

    public function sheetName(): string
    {
        return '对话记录';
    }

    /**
     * @param array $params
     * @return array
     */
    public function rows(array $params): array
    {
        return $this->services->sessionExportRows($params);
    }
}
