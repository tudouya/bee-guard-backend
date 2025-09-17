# Tasks Document

> **Filament 使用规范**：所有 Filament 相关代码（包含命名空间、组件导入、Resource 定义等）必须严格遵循 Filament v4 官方约定；若存在不确定的 API 或命名方式，应通过 Context7 MCP 检索 v4 文档后再实现。

- [x] 1. 建立奖励相关数据表与模型骨架
  - Files: database/migrations/XXXX_create_coupon_templates_table.php, database/migrations/XXXX_create_reward_rules_table.php, database/migrations/XXXX_create_reward_issuances_table.php, app/Models/CouponTemplate.php, app/Models/RewardRule.php, app/Models/RewardIssuance.php
  - Actions: 为券模板、奖励规则、奖励发放建立迁移（含索引/约束）、Eloquent 模型、基础关联与枚举字段，并保持与需求中的字段一致。
  - Purpose: 提供奖励机制的数据持久层基础，满足 Requirement 1~3 的字段与约束需求。
  - _Leverage: community_posts 表的既有关联模式、Laravel 枚举/属性转换能力_
  - _Requirements: Requirement 1, Requirement 2, Requirement 3_
  - _Prompt: Implement the task for spec content-reward-mechanism, first run spec-workflow-guide to get the workflow guide then implement the task: 角色: Laravel 数据建模工程师 | 任务: 创建奖励模块的三张核心数据表及模型，包含所需字段、状态枚举、关系和索引，确保字段命名与需求保持一致 | 限制: 不得修改既有 community_posts 记录结构；迁移命名需符合 Laravel 规范；必须添加必要的唯一约束与外键 | _Leverage: Laravel Schema builder, 已有用户/企业模型 | _Requirements: Requirement 1, Requirement 2, Requirement 3 | Success: 迁移可成功运行，模型定义关系正确，字段/索引满足规格_

- [x] 2. 企业端 Filament 资源：购物券模板提交
  - Files: app/Filament/Enterprise/Resources/CouponTemplateResource.php 及相关表单/列表组件
  - Actions: 为企业面板创建资源，支持创建/编辑/提交购物券模板，自动设置提交者与 `pending_review` 状态。
  - Purpose: 让企业录入包含平台、店铺名称、链接等信息的购物券模板，对应 Requirement 1。
  - _Leverage: 现有 Enterprise panel Filament 配置、表单验证逻辑_
  - _Requirements: Requirement 1_
  - _Prompt: Implement the task for spec content-reward-mechanism, first run spec-workflow-guide to get the workflow guide then implement the task: 角色: Filament 面板开发者 | 任务: 在企业面板实现购物券模板资源，提供表单字段与提交流程，写入提交者并置状态 pending_review | 限制: 不可允许企业直接修改已审批模板；需校验平台/链接格式；遵守企业面板导航结构 | _Leverage: Filament Forms/Table 组件、企业用户授权策略 | _Requirements: Requirement 1 | Success: 企业账户可提交模板并看到待审核状态，字段校验完整_

- [x] 3. 管理端 Filament 资源：购物券模板审核
  - Files: app/Filament/Admin/Resources/CouponTemplateResource.php 及操作类
  - Actions: 为管理员面板提供列表筛选、审核通过/驳回操作，记录审核人、时间、理由。
  - Purpose: 实现管理员审核流程，满足 Requirement 1。
  - _Leverage: 现有 Admin panel 审核模式、通知机制_
  - _Requirements: Requirement 1_
  - _Prompt: Implement the task for spec content-reward-mechanism, first run spec-workflow-guide to get the workflow guide then implement the task: 角色: Filament 审核流程开发者 | 任务: 在管理员面板实现购物券模板资源，支持审批操作并记录审核日志字段 | 限制: 需根据状态限制操作按钮；审批流程必须在事务中写入 reviewer 信息；保持审计记录 | _Leverage: Filament Actions、Audit Logging 工具 | _Requirements: Requirement 1 | Success: 管理员可查看待审模板并通过/驳回，状态切换和记录准确_

- [x] 4. 奖励规则配置资源
  - Files: app/Filament/Admin/Resources/RewardRuleResource.php
  - Actions: 管理端配置奖励规则（指标、阈值、券模板绑定、发放模式等），并确保仅可选择已审批的券模板。
  - Purpose: 支持 Requirement 2 中管理员配置阈值和奖励资产。
  - _Leverage: Filament 表单组件、枚举字段、依赖下拉选择_
  - _Requirements: Requirement 2_
  - _Prompt: Implement the task for spec content-reward-mechanism, first run spec-workflow-guide to get the workflow guide then implement the task: 角色: 配置面板开发者 | 任务: 在管理员面板中实现奖励规则资源，表单含指标/阈值/券模板选择/发放模式等，并校验模板状态 | 限制: 仅允许选择已批准模板；需提供启用/停用切换；保存时校验阈值大于零 | _Leverage: 枚举 cast、关系查询作用域 | _Requirements: Requirement 2 | Success: 管理员可新增并管理奖励规则，数据校验和引用关系正确_

- [x] 5. 奖励发放模型与手动队列管理
  - Files: app/Services/Community/Rewards/RewardIssuer.php, app/Filament/Admin/Resources/RewardIssuanceResource.php
  - Actions: 实现 RewardIssuer 服务（创建发放记录、扣减库存、幂等控制）以及管理员查看/审批待发放奖励的资源。
  - Purpose: 支持 Requirement 3 手动/自动发放流程。
  - _Leverage: 数据表模型、事务操作、Filament actions_
  - _Requirements: Requirement 3_
  - _Prompt: Implement the task for spec content-reward-mechanism, first run spec-workflow-guide to get the workflow guide then implement the task: 角色: 奖励流程后端工程师 | 任务: 编写 RewardIssuer 服务与管理员发放队列资源，实现奖励记录创建、库存校验、手动审批流程 | 限制: 所有数据库写操作需在事务中进行；必须实现幂等防重复；日志记录操作人 | _Leverage: Laravel Transactions、AuditLog 工具 | _Requirements: Requirement 3 | Success: 自动调用时能创建记录并扣减库存，手动队列中管理员可审批并发放_

- [x] 6. 奖励评估服务与任务调度
  - Files: app/Services/Community/Rewards/RewardEvaluator.php, app/Jobs/EvaluatePostRewards.php, 事件监听器
  - Actions: 根据 CommunityPost 的 likes/views/replies 变化触发评估，判断满足阈值即调用 RewardIssuer，含重复判定和多规则支持。
  - Purpose: 自动识别优质内容并生成奖励，覆盖 Requirement 2, Requirement 3。
  - _Leverage: Existing events for likes、队列系统、社区模型关系_
  - _Requirements: Requirement 2, Requirement 3_
  - _Prompt: Implement the task for spec content-reward-mechanism, first run spec-workflow-guide to get the workflow guide then implement the task: 角色: Laravel 队列与业务逻辑工程师 | 任务: 实现奖励评估服务及队列任务，监听互动指标变化并触发奖励发放，支持多条规则、自动/手动模式与日志记录 | 限制: 必须使用队列避免阻塞；评估需复用已存指标，禁止即时聚合查询；需确保并发幂等 | _Leverage: Laravel Queue, CommunityPost 事件, RewardIssuer | _Requirements: Requirement 2, Requirement 3 | Success: 指标更新会触发评估；符合条件的帖子能生成对应奖励记录且无重复发放_

- [x] 7. 农户端奖励 API 与通知
  - Files: app/Http/Controllers/Api/RewardController.php, routes/api.php, 通知类
  - Actions: 实现奖励列表、奖励详情/状态更新接口，以及奖励授予通知（含平台、店铺信息、使用说明）。
  - Purpose: 满足 Requirement 3 与 Requirement 4 的农户可见性与提醒。
  - _Leverage: Sanctum 认证、API 资源封装、现有通知基类_
  - _Requirements: Requirement 3, Requirement 4_
  - _Prompt: Implement the task for spec content-reward-mechanism, first run spec-workflow-guide to get the workflow guide then implement the task: 角色: Laravel API 开发者 | 任务: 编写农户奖励查询接口和通知推送，返回奖励状态、平台信息、链接与到期时间 | 限制: 接口需鉴权；响应遵循统一格式；通知内容需覆盖奖励详情 | _Leverage: ApiResponse trait, Notification system | _Requirements: Requirement 3, Requirement 4 | Success: 农户可通过 API 查看奖励，收到即时通知，数据准确显示平台与使用说明_

- [-] 8. 集成测试：奖励触发全流程
  - Files: tests/Feature/Community/RewardFlowTest.php
  - Actions: 编写测试覆盖企业提交模板→管理员审核→配置规则→帖子达标→自动/手动发放→农户查询的流程。
  - Purpose: 验证核心业务链路，满足所有 Requirements。
  - _Leverage: DatabaseTransactions trait、模型工厂、Queue fake_
  - _Requirements: Requirement 1, Requirement 2, Requirement 3, Requirement 4_
  - _Prompt: Implement the task for spec content-reward-mechanism, first run spec-workflow-guide to get the workflow guide then implement the task: 角色: Laravel Feature 测试工程师 | 任务: 编写覆盖奖励机制完整流程的测试，包括自动与手动发放路径，断言通知与状态 | 限制: 使用内存 SQLite；需 fake 队列/通知以验证调用；保持测试独立可重复 | _Leverage: Laravel testing helpers, factories | _Requirements: Requirement 1, Requirement 2, Requirement 3, Requirement 4 | Success: 测试通过且覆盖主要场景，验证奖励机制的关键路径_

- [x] 9. 文档与环境配置补充
  - Files: docs/content-reward-mechanism.md, .env.example
  - Actions: 为新模块撰写内部文档（含流程图、操作指南）并补充相关环境变量（如奖励评估队列开关等）。
  - Purpose: 便于团队理解和部署奖励机制，支撑所有 Requirements。
  - _Leverage: 现有 docs 模板、队列配置示例_
  - _Requirements: Requirement 1, Requirement 2, Requirement 3, Requirement 4_
  - _Prompt: Implement the task for spec content-reward-mechanism, first run spec-workflow-guide to get the workflow guide then implement the task: 角色: 技术文档维护者 | 任务: 为奖励模块撰写文档与环境配置说明，确保团队知道如何启用与维护 | 限制: 文档需准确描述自动/手动流程；.env 示例保持注释清晰；不得泄漏敏感数据 | _Leverage: 现有文档结构、配置说明 | _Requirements: Requirement 1, Requirement 2, Requirement 3, Requirement 4 | Success: 文档清晰易懂，.env.example 补充必要变量，帮助部署与运营_
