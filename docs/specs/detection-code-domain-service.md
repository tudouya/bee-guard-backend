# 检测码领域服务（复用与一致性）规划说明

本文档描述当前“检测码生命周期”规则分散的问题、风险与统一的领域服务方案（仅文档，不立即改代码）。

## 背景与现状
- 当前关键规则分散在多处：
  - 用户绑定分配（available → assigned）：`app/Http/Controllers/Api/DetectionCodesController.php`
  - 问卷提交后置已用（assigned → used）：`app/Http/Controllers/Api/SurveysController.php`
  - 后台审核支付凭证后分配自费码：`app/Filament/Admin/Resources/PaymentProofResource.php`
  - 模型侧存在部分不变式处理（时间戳补齐/状态联动），但不负责并发/事务语义：`app/Models/DetectionCode.php`
- 表结构与字段：`database/migrations/2025_09_12_095000_create_detection_codes_table.php`（code 唯一、source_type、prefix、status、assigned_*、used_at 等）。

## 问题与风险
- 规则重复：分配/置已用/并发保护逻辑在多处实现，后续调整容易漏改，产生漂移。
- 并发不一致：不同位置可能采用不同的锁定/条件更新策略，造成偶发冲突与边界差异。
- 错误与审计不统一：错误码/HTTP 状态/审计写入点分散，客户端与排障成本上升。
- 演进困难：未来引入“企业前缀优先”“预留状态（reserved）”“超时回收”等增强能力，修改面大、风控难统一。

## 目标
- 单点承载检测码生命周期规则，控制器/面板仅作为适配层调用。
- 统一并发控制、错误码映射与审计记录，减少重复代码与风格漂移。
- 为后续扩展（企业前缀、预占用、回收、风控）留出演进点。

## 设计原则
- YAGNI：仅收敛当前已存在且必要的动作，不提前实现未确定能力。
- 原子性：所有状态流转在单个事务内完成；失败回滚。
- 并发安全：使用“条件更新”或“行级锁 + 条件校验”统一策略，避免双重消耗（double-spend）。
- 一致对外：错误码/HTTP 状态与响应体遵循 v1 规范（见 `docs/specs/api-response-v1.md`、`docs/specs/error-codes-v1.md`）。

## 领域服务草案
- 类：`App\Services\Detection\DetectionCodeService`
- 职责（最小集）：
  - `assignByFullNumber(string $fullCode, int $userId): DetectionCode`
    - 查找 `prefix+code == $fullCode`；校验状态与归属；当状态为 available 时，原子化分配给 `$userId`（置 `assigned_*`）。
  - `assignOneSelfPaidToUser(int $userId): DetectionCode`
    - 从池中挑选一枚 `source_type=self_paid && status=available`，原子化分配给 `$userId`。无可用码返回 409（`no_available_code` 或用通用 `conflict`）。
  - `markUsed(int $codeId, int $userId): DetectionCode`
    - 校验码存在且归属 `$userId`，当前状态为 `assigned`；原子化置 `used` 并打 `used_at`。
- 错误映射（建议）：
  - 不存在：404 `not_found`
  - 不属于当前用户：403 `forbidden`
  - 状态冲突/并发冲突：409 `operation_conflict`
- 审计：
  - 通过统一方法记录审计（数据库 `audits` 表或结构化日志），动作包括：`assign_by_user`、`assign_by_review`、`mark_used`，附带操作者/订单/凭证等上下文。

## 并发与事务策略（建议）
- 统一使用事务：`DB::transaction(fn() => { ... })`
- 分配（available → assigned）：
  - 方案 1（推荐）：条件更新实现原子化切换：
    - `update detection_codes set status='assigned', assigned_user_id=?, assigned_at=now() where id=? and status='available'`
    - 返回受影响行数=1 视为成功；=0 视为冲突，返回 409。
  - 方案 2：`select ... for update` + 校验 + 更新；需避免长事务与热点行锁。
- 置已用（assigned → used）：同理采用条件更新，或在加锁后校验归属与状态再更新。

## 响应与错误体（对齐 v1）
- 成功：`{ code:0, message:'ok', data:{ ... } }`
- 错误：`{ status, code, message, errors?, requestId, timestamp }`（详见 `docs/specs/api-response-v1.md`）

## 调用点替换清单（不立即改动，仅作为后续实施指南）
- 用户绑定分配：
  - 现：`app/Http/Controllers/Api/DetectionCodesController.php`（方法 `verifyBind` 内部直接执行业务）
  - 替：调用 `DetectionCodeService::assignByFullNumber()`，控制器仅做参数校验与响应封装。
- 问卷提交置已用：
  - 现：`app/Http/Controllers/Api/SurveysController.php`（`store` 尾部将码置 `used`）
  - 替：调用 `DetectionCodeService::markUsed()`，并在服务内统一错误码/幂等处理。
- 人工审核分配自费码：
  - 现：`app/Filament/Admin/Resources/PaymentProofResource.php`（审核通过动作内分配码与置 paid）
  - 替：调用 `DetectionCodeService::assignOneSelfPaidToUser()`，审核动作自身仅处理订单状态与凭证状态流转。

## 落地步骤（建议）
1) 新增服务类与最小单元测试（并发/冲突/归属校验）。
2) 在控制器/面板调用点替换为服务调用（保持响应体与路由不变）。
3) 引入统一审计写入（可先走结构化日志，再择期落库）。

## 回滚与兼容
- 若实施后出现问题，可回退到原控制器内联逻辑；服务类保留、调用恢复即可。
- 服务对外契约保持稳定（方法签名/异常类型/错误码），便于后续复用与扩展。

## 测试建议
- 并发分配：多并发请求同一 `fullCode`，仅一条成功，其余 409。
- 归属校验：非归属用户调用 `markUsed` 返回 403。
- 自费分配耗尽：`assignOneSelfPaidToUser` 在无可用码时返回 409。
- 回归现有接口：`/api/detection-codes/verify-bind`、`/api/surveys`、支付凭证审核流均能闭环。

