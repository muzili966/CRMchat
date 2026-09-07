<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\models;

use crmeb\basic\BaseModel;
use crmeb\traits\ModelTrait;
use think\Model;

/**
 * 导出任务模型
 * Class ExportTask
 * @package app\models
 */
class ExportTask extends BaseModel
{
    use ModelTrait;

    /**
     * 待处理，等常驻进程领取
     */
    const STATUS_PENDING = 0;

    /**
     * 处理中，已被某个进程领走
     */
    const STATUS_RUNNING = 1;

    const STATUS_SUCCESS = 2;

    const STATUS_FAILED = 3;

    const STATUS_TEXT = [
        self::STATUS_PENDING => '排队中',
        self::STATUS_RUNNING => '生成中',
        self::STATUS_SUCCESS => '已完成',
        self::STATUS_FAILED => '失败',
    ];

    protected $name = 'export_task';

    protected $pk = 'id';

    //时间字段一律存整型时间戳，交给ORM自动转换会当成日期字符串解析而报错
    protected $autoWriteTimestamp = false;

    /**
     * 状态搜索器
     * @param Model $query
     * @param $value
     */
    public function searchStatusAttr($query, $value)
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', (int)$value);
        }
    }

    /**
     * 导出类型搜索器
     * @param Model $query
     * @param $value
     */
    public function searchTypeAttr($query, $value)
    {
        if ($value) {
            $query->where('type', $value);
        }
    }

    /**
     * 发起人搜索器
     * @param Model $query
     * @param $value
     */
    public function searchAdminIdAttr($query, $value)
    {
        if ($value) {
            $query->where('admin_id', (int)$value);
        }
    }
}
