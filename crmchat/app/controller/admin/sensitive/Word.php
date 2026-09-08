<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\controller\admin\sensitive;

use app\controller\admin\AuthController;
use app\services\sensitive\SensitiveWordServices;
use crmeb\utils\SensitiveFilter;

/**
 * 敏感词库
 *
 * 平台视角维护合规词（对所有租户强制生效），租户视角维护自己的业务词。
 * Class Word
 * @package app\controller\admin\sensitive
 */
class Word extends AuthController
{
    /**
     * @param SensitiveWordServices $services
     */
    public function __construct(SensitiveWordServices $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    /**
     * 词条列表
     * @return mixed
     */
    public function index()
    {
        $where = $this->request->getMore([
            ['keyword', ''],
            ['category', ''],
            ['status', ''],
            [['page', 'd'], 1],
            [['limit', 'd'], 20],
        ]);
        $data = $this->services->getList($where);
        //当前视角维护的是哪一级词库，前端据此给出不同说明
        $data['is_platform'] = (int)($this->services->scopeTenantId() === 0);
        return $this->success($data);
    }

    /**
     * 新增词条
     * @return mixed
     */
    public function save()
    {
        return $this->success('新增成功', $this->services->create($this->wordData()));
    }

    /**
     * 修改词条
     * @param int $id
     * @return mixed
     */
    public function update($id)
    {
        $this->services->modify((int)$id, $this->wordData());
        return $this->success('修改成功');
    }

    /**
     * 删除词条
     * @param int $id
     * @return mixed
     */
    public function delete($id)
    {
        $this->services->remove((int)$id);
        return $this->success('删除成功');
    }

    /**
     * 批量导入
     * @return mixed
     */
    public function import()
    {
        $data = $this->request->postMore([
            ['words', ''],
            ['category', ''],
            [['action', 'd'], SensitiveFilter::ACTION_REPLACE],
            [['scope', 'd'], SensitiveFilter::SCOPE_ALL],
        ]);
        $result = $this->services->import($data);
        return $this->success("导入成功 {$result['added']} 条，跳过重复 {$result['skipped']} 条", $result);
    }

    /**
     * 词条字段
     * @return array
     */
    protected function wordData(): array
    {
        return $this->request->postMore([
            ['word', ''],
            ['category', ''],
            [['action', 'd'], SensitiveFilter::ACTION_REPLACE],
            [['scope', 'd'], SensitiveFilter::SCOPE_ALL],
            [['status', 'd'], 1],
            ['remark', ''],
        ]);
    }
}
