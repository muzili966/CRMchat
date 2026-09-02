<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------

namespace app\services\platform;

use app\dao\platform\PlatformLeadDao;
use app\dao\platform\PlatformLeadFollowDao;
use app\dao\system\admin\SystemAdminDao;
use app\models\PlatformLead;
use crmeb\basic\BaseServices;
use crmeb\exceptions\AdminException;
use crmeb\services\tenant\TenantContext;

/**
 * 平台销售线索
 *
 * 官网表单与手工录入的潜在客户在此沉淀，按阶段推进直至开通租户。
 * 每次阶段变更都自动留一条跟进记录，交接时能看清这条线索经历过什么。
 * Class PlatformLeadServices
 * @package app\services\platform
 */
class PlatformLeadServices extends BaseServices
{
    /**
     * @param PlatformLeadDao $dao
     */
    public function __construct(PlatformLeadDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 线索列表
     * @param array $where
     * @return array
     */
    public function getLeadList(array $where): array
    {
        [$page, $limit] = $this->getPageValue();
        $where['is_delete'] = 0;
        $list = $this->dao->getLeadList($where, $page, $limit);
        $count = $this->dao->count($where);
        $owners = $this->ownerMap(array_column($list, 'owner_id'));
        $now = time();
        foreach ($list as &$item) {
            $item = $this->formatLead($item, $owners, $now);
        }
        return ['list' => $list, 'count' => $count, 'stat' => $this->stageStat()];
    }

    /**
     * 各阶段数量概览
     * @return array
     */
    public function stageStat(): array
    {
        $counts = $this->dao->countByStage();
        $stat = [];
        foreach (PlatformLead::STAGES as $stage => $label) {
            $stat[] = ['stage' => $stage, 'label' => $label, 'num' => (int)($counts[$stage] ?? 0)];
        }
        return $stat;
    }

    /**
     * 线索详情，含跟进记录
     * @param int $id
     * @return array
     */
    public function getLeadInfo(int $id): array
    {
        $lead = $this->dao->get($id);
        if (!$lead || $lead->is_delete) {
            throw new AdminException('线索不存在');
        }
        $lead = $lead->toArray();
        $lead = $this->formatLead($lead, $this->ownerMap([$lead['owner_id']]), time());
        /** @var PlatformLeadFollowDao $followDao */
        $followDao = app()->make(PlatformLeadFollowDao::class);
        $follows = $followDao->getByLead($id);
        foreach ($follows as &$follow) {
            $follow['_create_time'] = date('Y-m-d H:i', (int)$follow['create_time']);
            $follow['stage_from_text'] = PlatformLead::STAGES[$follow['stage_from']] ?? '';
            $follow['stage_to_text'] = PlatformLead::STAGES[$follow['stage_to']] ?? '';
        }
        $lead['follows'] = $follows;
        return $lead;
    }

    /**
     * 新建线索
     * @param array $data
     * @param int $adminId 手工录入时的操作人，官网提交传0
     * @return int 线索ID
     */
    public function createLead(array $data, int $adminId = 0): int
    {
        $payload = self::buildPayload($data);
        if ($payload['name'] === '' && $payload['company'] === '') {
            throw new AdminException('请至少填写联系人或公司名称');
        }
        if ($payload['phone'] === '' && $payload['email'] === '') {
            throw new AdminException('请至少留下电话或邮箱，否则无法联系');
        }
        $now = time();
        $payload['stage'] = PlatformLead::STAGE_NEW;
        $payload['owner_id'] = $adminId;
        $payload['create_time'] = $now;
        $payload['update_time'] = $now;
        $lead = $this->dao->save($payload);
        if (!$lead) {
            throw new AdminException('线索创建失败');
        }
        return (int)$lead->id;
    }

    /**
     * 记录一次跟进，可同时推进阶段
     * @param int $id
     * @param array $data content/stage/next_follow_time
     * @param array $adminInfo
     * @return bool
     */
    public function addFollow(int $id, array $data, array $adminInfo): bool
    {
        $lead = $this->dao->get($id);
        if (!$lead || $lead->is_delete) {
            throw new AdminException('线索不存在');
        }
        $content = trim((string)($data['content'] ?? ''));
        $stage = isset($data['stage']) ? (int)$data['stage'] : 0;
        if ($content === '' && !$stage) {
            throw new AdminException('请填写跟进内容或选择要推进的阶段');
        }
        if ($stage && !isset(PlatformLead::STAGES[$stage])) {
            throw new AdminException('阶段不存在');
        }
        $now = time();
        $from = (int)$lead->stage;
        //阶段未变时记0，避免详情里出现「新线索→新线索」这种无意义的流转
        $stageFrom = ($stage && $stage !== $from) ? $from : 0;
        $stageTo = ($stage && $stage !== $from) ? $stage : 0;

        /** @var PlatformLeadFollowDao $followDao */
        $followDao = app()->make(PlatformLeadFollowDao::class);
        $followDao->save([
            'lead_id' => $id,
            'admin_id' => (int)($adminInfo['id'] ?? 0),
            'admin_name' => (string)($adminInfo['real_name'] ?? $adminInfo['account'] ?? ''),
            'content' => mb_substr($content, 0, PlatformLead::MAX_CONTENT),
            'stage_from' => $stageFrom,
            'stage_to' => $stageTo,
            'create_time' => $now,
        ]);

        $update = ['last_follow_time' => $now, 'update_time' => $now];
        if ($stageTo) {
            $update['stage'] = $stageTo;
        }
        //跟进人为空时由本次跟进者接手，避免线索长期无主
        if (!$lead->owner_id && !empty($adminInfo['id'])) {
            $update['owner_id'] = (int)$adminInfo['id'];
        }
        //只在明确给了时间时才写：控制器的postMore带默认值0，
        //按isset判断会让"本次不设下次跟进"把之前约定的时间清零
        if (!empty($data['next_follow_time'])) {
            $update['next_follow_time'] = (int)$data['next_follow_time'];
        }
        return false !== $this->dao->update($id, $update);
    }

    /**
     * 转派跟进人
     * @param int $id
     * @param int $ownerId
     * @return bool
     */
    public function assign(int $id, int $ownerId): bool
    {
        if (!$this->dao->get($id)) {
            throw new AdminException('线索不存在');
        }
        return false !== $this->dao->update($id, ['owner_id' => $ownerId, 'update_time' => time()]);
    }

    /**
     * 关联已开通的租户，形成线索到客户的闭环
     * @param int $id
     * @param int $tenantId
     * @return bool
     */
    public function linkTenant(int $id, int $tenantId): bool
    {
        if (!$this->dao->get($id)) {
            throw new AdminException('线索不存在');
        }
        return false !== $this->dao->update($id, [
            'tenant_id' => $tenantId,
            'stage' => PlatformLead::STAGE_WON,
            'update_time' => time(),
        ]);
    }

    /**
     * @param int $id
     * @return bool
     */
    public function deleteLead(int $id): bool
    {
        return false !== $this->dao->update($id, ['is_delete' => 1, 'update_time' => time()]);
    }

    /**
     * 字段归一化
     * @param array $data
     * @return array
     */
    public static function buildPayload(array $data): array
    {
        $source = (string)($data['source'] ?? PlatformLead::SOURCE_WEBSITE);
        return [
            'name' => self::text($data, 'name', 50),
            'company' => self::text($data, 'company', 100),
            'phone' => self::text($data, 'phone', 30),
            'email' => self::text($data, 'email', 100),
            'scale' => self::text($data, 'scale', 30),
            'intent_plan' => self::text($data, 'intent_plan', 50),
            'content' => self::text($data, 'content', PlatformLead::MAX_CONTENT),
            'source' => isset(PlatformLead::SOURCES[$source]) ? $source : PlatformLead::SOURCE_WEBSITE,
        ];
    }

    /**
     * @param array $data
     * @param string $key
     * @param int $max
     * @return string
     */
    protected static function text(array $data, string $key, int $max): string
    {
        //官网表单来自公网，一律去标签，避免把脚本片段存进库再回显到后台
        return mb_substr(trim(strip_tags((string)($data[$key] ?? ''))), 0, $max);
    }

    /**
     * 补充展示字段
     * @param array $item
     * @param array $owners
     * @param int $now
     * @return array
     */
    protected function formatLead(array $item, array $owners, int $now): array
    {
        $item['stage_text'] = PlatformLead::STAGES[$item['stage']] ?? '';
        $item['source_text'] = PlatformLead::SOURCES[$item['source']] ?? $item['source'];
        $item['owner_name'] = $owners[$item['owner_id']] ?? '';
        $item['_create_time'] = $item['create_time'] ? date('Y-m-d H:i', (int)$item['create_time']) : '';
        $item['_next_follow_time'] = $item['next_follow_time'] ? date('Y-m-d', (int)$item['next_follow_time']) : '';
        //逾期未跟进需要在列表里被一眼看到，终态线索不参与判断
        $item['overdue'] = (int)(
            $item['next_follow_time'] > 0
            && $item['next_follow_time'] < $now
            && !in_array((int)$item['stage'], PlatformLead::CLOSED_STAGES, true)
        );
        return $item;
    }

    /**
     * 跟进人ID到名称
     * @param array $ids
     * @return array
     */
    protected function ownerMap(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        /** @var SystemAdminDao $adminDao */
        $adminDao = app()->make(SystemAdminDao::class);
        //跟进人多为平台账号，查询需逃逸租户Scope
        $rows = TenantContext::withoutTenant(function () use ($adminDao, $ids) {
            return $adminDao->getColumn([['id', 'IN', $ids]], 'real_name,account,id', 'id');
        });
        $map = [];
        foreach ((array)$rows as $id => $row) {
            $map[$id] = $row['real_name'] ?: $row['account'];
        }
        return $map;
    }
}
