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


use app\dao\TenantDao;
use app\dao\TenantPlanDao;
use app\dao\TenantPlanOrderDao;
use app\models\TenantNotice;
use app\models\TenantPlan;
use app\models\TenantPlanOrder;
use crmeb\basic\BaseServices;
use crmeb\exceptions\AdminException;
use crmeb\services\tenant\TenantContext;

/**
 * 租户套餐订购对账service
 * Class TenantPlanOrderServices
 * @package app\services
 */
class TenantPlanOrderServices extends BaseServices
{

    /**
     * 对账导出目录（public下）
     */
    const EXPORT_DIR = 'uploads/export/';

    /**
     * TenantPlanOrderServices constructor.
     * @param TenantPlanOrderDao $dao
     */
    public function __construct(TenantPlanOrderDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 订购/续费套餐：生成对账单并更新租户套餐与到期时间
     * @param array $data tenant_id/plan_id/months/pay_type/remark/admin_id
     * @return array 生成的对账单信息
     */
    public function subscribe(array $data)
    {
        $tenantId = (int)$data['tenant_id'];
        /** @var TenantServices $tenantServices */
        $tenantServices = app()->make(TenantServices::class);
        $tenantServices->mustExists($tenantId);

        /** @var TenantPlanDao $planDao */
        $planDao = app()->make(TenantPlanDao::class);
        $plan = $planDao->get(['id' => (int)$data['plan_id'], 'is_delete' => 0, 'status' => TenantPlan::STATUS_ON]);
        if (!$plan) {
            throw new AdminException('套餐不存在或已停售');
        }

        /** @var TenantDao $tenantDao */
        $tenantDao = app()->make(TenantDao::class);
        $tenant = $tenantDao->get($tenantId);
        $months = max(1, (int)$data['months']);
        $now = time();
        $expireBefore = (int)$tenant->expire_time;
        $samePlan = (int)$tenant->plan_id == (int)$plan->id;
        $expireAfter = self::computeExpireAfter($expireBefore, $months, (string)$plan->price, $samePlan, $now);

        $order = [
            'tenant_id' => $tenantId,
            'order_no' => $this->buildOrderNo($tenantId),
            'plan_id' => (int)$plan->id,
            'plan_name' => $plan->name,
            'plan_snapshot' => json_encode($plan->toArray(), JSON_UNESCAPED_UNICODE),
            'months' => $months,
            'amount' => bcmul((string)$plan->price, (string)$months, 2),
            'pay_type' => (int)($data['pay_type'] ?? TenantPlanOrder::PAY_TYPE_BACKEND),
            'status' => TenantPlanOrder::STATUS_EFFECTIVE,
            'expire_before' => $expireBefore,
            'expire_after' => $expireAfter,
            'admin_id' => (int)($data['admin_id'] ?? 0),
            'remark' => (string)($data['remark'] ?? ''),
            'create_time' => $now,
        ];

        $result = $this->transaction(function () use ($order, $tenantDao, $tenantId, $plan, $expireAfter) {
            $saved = TenantContext::runAs($tenantId, function () use ($order) {
                return $this->dao->save($order);
            });
            if (!$saved) {
                throw new AdminException('生成对账单失败');
            }
            $tenantDao->update($tenantId, [
                'plan_id' => (int)$plan->id,
                'expire_time' => $expireAfter,
                'update_time' => time(),
            ]);
            return $saved;
        });

        /** @var TenantPlanServices $planServices */
        $planServices = app()->make(TenantPlanServices::class);
        $planServices->clearTenantPlanCache($tenantId);

        /** @var TenantNoticeServices $noticeServices */
        $noticeServices = app()->make(TenantNoticeServices::class);
        $expireText = $expireAfter > 0 ? date('Y-m-d', $expireAfter) : '永久';
        $noticeServices->addNotice($tenantId, TenantNotice::TYPE_RENEW, sprintf('已开通「%s」%d个月，有效期至%s', $plan->name, $order['months'], $expireText));

        return $result->toArray();
    }

    /**
     * 计算订购后的到期时间：免费套餐永久；续订同套餐在原到期时间上顺延，换套餐从当前时间起算
     * @param int $expireBefore 订购前到期时间
     * @param int $months 订购月数
     * @param string $price 套餐月价
     * @param bool $samePlan 是否续订当前套餐
     * @param int $now 当前时间
     * @return int
     */
    public static function computeExpireAfter(int $expireBefore, int $months, string $price, bool $samePlan, int $now): int
    {
        if ((float)$price <= 0) {
            return 0;
        }
        $base = ($samePlan && $expireBefore > $now) ? $expireBefore : $now;
        return (int)strtotime('+' . $months . ' month', $base);
    }

    /**
     * 生成对账单号
     * @param int $tenantId
     * @return string
     */
    protected function buildOrderNo(int $tenantId): string
    {
        mt_srand();
        return 'TP' . date('YmdHis') . str_pad((string)$tenantId, 4, '0', STR_PAD_LEFT) . mt_rand(1000, 9999);
    }

    /**
     * 订购记录列表（调用方决定视角：平台全局或当前租户）
     * @param array $where
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getOrderList(array $where)
    {
        [$page, $limit] = $this->getPageValue();
        $list = $this->dao->getOrderList($where, $page, $limit);
        $count = $this->dao->count($where);
        $tenantNames = $this->getTenantNameMap(array_column($list, 'tenant_id'));
        foreach ($list as &$item) {
            $item['tenant_name'] = $tenantNames[$item['tenant_id']] ?? '';
            $item['_create_time'] = date('Y-m-d H:i:s', $item['create_time']);
            $item['_expire_after'] = $item['expire_after'] ? date('Y-m-d', $item['expire_after']) : '永久';
        }
        return compact('list', 'count');
    }

    /**
     * 导出对账CSV，返回可下载的相对路径
     * @param array $where
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function exportOrders(array $where): string
    {
        $list = $this->dao->getOrderList($where);
        $tenantNames = $this->getTenantNameMap(array_column($list, 'tenant_id'));
        $payTypeText = [TenantPlanOrder::PAY_TYPE_BACKEND => '后台开通', TenantPlanOrder::PAY_TYPE_OFFLINE => '线下转账'];
        $statusText = [TenantPlanOrder::STATUS_EFFECTIVE => '已生效', TenantPlanOrder::STATUS_VOID => '已作废'];

        $rows = [['对账单号', '租户ID', '租户名称', '套餐', '订购月数', '金额(元)', '支付方式', '状态', '订购后到期', '备注', '订购时间']];
        foreach ($list as $item) {
            $rows[] = [
                $item['order_no'],
                $item['tenant_id'],
                $tenantNames[$item['tenant_id']] ?? '',
                $item['plan_name'],
                $item['months'],
                $item['amount'],
                $payTypeText[$item['pay_type']] ?? $item['pay_type'],
                $statusText[$item['status']] ?? $item['status'],
                $item['expire_after'] ? date('Y-m-d', $item['expire_after']) : '永久',
                $item['remark'],
                date('Y-m-d H:i:s', $item['create_time']),
            ];
        }
        return $this->writeCsv('plan_orders_', $rows);
    }

    /**
     * 写出CSV文件（带BOM便于Excel识别），返回相对URL路径
     * @param string $prefix
     * @param array $rows
     * @return string
     */
    protected function writeCsv(string $prefix, array $rows): string
    {
        $dir = root_path() . 'public/' . self::EXPORT_DIR;
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new AdminException('创建导出目录失败');
        }
        mt_srand();
        $fileName = $prefix . date('YmdHis') . mt_rand(100, 999) . '.csv';
        $lines = [];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(function ($cell) {
                return '"' . str_replace('"', '""', (string)$cell) . '"';
            }, $row));
        }
        $content = "\xEF\xBB\xBF" . implode("\r\n", $lines);
        if (false === file_put_contents($dir . $fileName, $content)) {
            throw new AdminException('导出文件写入失败');
        }
        return '/' . self::EXPORT_DIR . $fileName;
    }

    /**
     * 租户ID到名称的映射
     * @param array $tenantIds
     * @return array
     */
    protected function getTenantNameMap(array $tenantIds): array
    {
        if (!$tenantIds) {
            return [];
        }
        /** @var TenantDao $tenantDao */
        $tenantDao = app()->make(TenantDao::class);
        return $tenantDao->getColumn([['id', 'IN', array_unique($tenantIds)]], 'name', 'id');
    }
}
