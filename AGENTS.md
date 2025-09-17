# AGENTS.md — Bee Guard Backend Agent Guide

本文件为在本仓库内工作的智能体（Agent）提供明确的约束、决策与开发流程指导。其范围覆盖本仓库根目录及所有子目录。

— 本文档将随着需求与设计演进而更新。


## 1) 作用域与优先级规则（Agent 必读）
- 作用域：本文件适用于整个仓库目录树。
- 遵循顺序：用户/开发者明确指令 > 更深层级目录的 AGENTS.md > 本文件。
- 若文件树中存在多个 AGENTS.md，越深层的优先生效（仅在其作用域内）。
- 本文件中的编码规范、结构与命名等指导仅适用于本仓库，不外溢至其他项目。
- 修改代码前请通读：问题要在根因处解决，避免引入不相关变更。


## 2) 技术栈与运行方式
- 后端框架：Laravel 12（PHP 8.2）。
- 后台面板：Filament v4（计划两套面板：admin/enterprise）。
- 前端构建：Vite 7 + Tailwind CSS 4。
- 数据库：MySQL 8.x。
- 依赖管理：Composer（PHP），npm（前端）。
- 本地开发：
  - 安装依赖：`composer install && npm install`
  - 环境：复制 `.env.example` 为 `.env`，`php artisan key:generate`
  - 迁移：`php artisan migrate`
  - 一键多进程：`composer dev`（serve/queue/logs/vite）
- 测试：`composer test`（PHPUnit 11，基于 MySQL 的独立测试库）


## 3) 认证与角色（已确认的关键决策）
- 小程序端认证：仅使用微信登录（code2session），不使用 OTP（短信/邮箱）登录。
- 后台登录：账号密码（Filament 默认登录）。
- 角色集合：
  - `super_admin`（超管）：全平台配置/审核/数据权限。
  - `enterprise_admin`（企业）：仅管理所属企业数据与功能。
  - `farmer`（蜂农）：小程序端用户。
- 用户统一表：`users`（统一存储身份信息）。
  - 小程序用户：在 `users` 表中保存 `openid`（唯一、可空）、`unionid?`。
  - 管理员/企业用户：上述字段留空。
- 企业归属：不在 `users` 上存放 `enterprise_id`。改为企业关联用户：
  - `enterprises.user_id` 指向企业“主账号”用户（可为空，创建后再指派）。
  - 关系为一对多：一个用户可以拥有多个企业（`users.id -> enterprises.owner_user_id`）。
  - 蜂农不绑定企业（企业关系通过“检测号来源”与检测记录体现）。

— 补充（与小程序对齐）
- 鉴权与手机号：手机号仅作为业务字段（联系/匹配），不参与登录鉴权；鉴权使用 Sanctum Token。
- 登录与绑定接口：
  - `POST /api/auth/wechat/login`：入参 `{ code }`；返回 `{ token, user }`。依赖微信官方接口进行 `code2session` 校验。
  - `POST /api/auth/wechat/bind-phone`：入参 `{ phone_code }`（微信获取手机号返回码）；返回 `{ phone }`；需携带登录 Token。


## 4) 领域模型（初版草案，供实现参考）
- 用户与组织
  - `users`：通用用户主体（`role`、昵称/头像、可空 `openid` 唯一、`unionid?`、`last_login_at`）。
  - `enterprises`：企业资料、联系人、状态、`owner_user_id`（企业主账号）。
- 检测与订单
  - `detection_codes`：采样码（检测号）。字段建议：`code` 唯一、`source_type=gift|self_paid`、`prefix`（如 `QY|ZF` 可配置）、`status=available|assigned|used|expired`、`assigned_user_id?`、`assigned_at?`、`used_at?`、`enterprise_id?`、索引与唯一约束保证并发安全。
  - `orders`：自费订单（人工，预留微信/支付宝）。`user_id`、`amount`、`status=pending|paid|failed|refunded`、`channel=manual|wxpay|alipay`、`trade_no?`、`paid_at?`、`detection_code_id?`（支付成功后分配）。
  - `detections`：检测主记录。`user_id`、`detection_code_id`、`sample_id?`、`province/city/district`（首期简化）、`submitted_at`、`status=pending|received|processing|completed`、`questionnaire(json)`、`contact_phone`。
  - `questionnaires`：如需拆表：`detection_id`、`answers(json)`、`filled_at`（也可内嵌于 `detections.questionnaire`）。
  - `diseases`：病种字典（SBV、IAPV、BQCV、AFB、EFB、微孢子虫、白垩病等）。
  - `detection_results`：检测结果明细（`detection_id`、`disease_id`、`result=positive|negative`、说明/建议）。
  - `shipping_notifications`：邮寄通知（新增，与小程序“确认邮寄”对齐）。`detection_code_id`、`courier_company`、`tracking_no`、`shipped_at?`、唯一约束（`detection_code_id`+`tracking_no`）。
  - `payment_proofs`：支付凭证（开发/联调替代方案）。`order_id`、`method(text)`、`order_no?`、`amount`、`images(json)`、`remark?`、`status=pending|approved|rejected`、`reviewed_by/at`。
- 推荐与产品
  - `products`：企业产品（名称、简介、链接、媒体）。
  - `disease_product`：病种与产品映射（多对多）。
  - `recommendation_rules`：推荐规则（ZF 自费全局推荐、赠送码按企业推荐）。
- 知识与互动
  - `posts`：`type=question|experience`、作者、内容、媒体、`status=pending|approved|rejected`、`disease_id?`、`views`、`likes`。
  - `replies`：问题回复（管理员/企业，可标注“平台建议/企业建议”）。
  - `reward_rules`：优质内容识别门槛/奖励配置（点击/点赞阈值，自动/人工）。
  - `coupons`/`coupon_grants`：代金券定义与发放记录。
- 地区
  - `regions`：省/市/县（首期可用简单字段，后续导入全国行政区库）。
- 审计与审核（建议）
  - 统一 `status` 字段与 `reviewed_by/at`；或独立 `audits` 表记录审核轨迹。


## 5) 业务模块与流程（按需求整理）
### 5.1 前端用户模块（蜂农端，小程序）
- 检测入口（有检测号）
  - 输入检测号与手机号（作为业务字段，不用于登录）→ 校验并绑定 → 问卷 → 提交 → 样品邮寄说明。
  - 检测号一次性使用：提交问卷/生成样品记录后原子化标记 `used`。
- 自费通道（无检测号）
  - 引导支付 → 选择检测项目与价格（可配置） → 微信支付 → 成功后分配自费检测号（示例前缀 `ZF`，编码规则可配置）→ 问卷 → 邮寄说明。
  - 开发阶段替代：上传支付凭证 → 后台人工审核通过后分配 `ZF-` 检测号。
- 检测结果查询
  - 微信登录成功后，展示该用户全部检测记录（自费与赠送）。
  - 列表字段：样品编号、检测号、提交时间、病种与结果、推荐方法、产品推荐。
- 推荐产品
  - 赠送检测号：显示该企业配置的推荐。
  - 自费检测号（ZF）：显示管理员全局推荐。
- 确认邮寄（新增数据上报）
  - 提交字段：`courier_company`、`tracking_no`、`shipped_at?`、`images[]?`、`phone`。
- 手机号与登录：手机号仅为业务字段；登录依赖微信 code2session + Token。
- 疫情查询
  - 省/市/县筛选；阳性数据分布（病种维度）与时间趋势（按月）。
- 防控知识
  - 蜂病百科：简介/症状/传播/诊断/防控方案/推荐产品链接。
  - 技术经验互动：蜂农提问（图文/视频，需审核）、经验分享（需审核），平台/企业可回复。设立激励机制（阈值→优质内容→代金券/勋章等）。
- 安全与验证
  - 检测号一次性、记录绑定到用户（非手机号）且归属明确；内容均需审核后展示。

### 5.2 系统管理员模块（后台管理）
- 检测号池管理：导入/生成/状态流转，关联企业与渠道。
- 检测数据管理：按手机号（业务字段）与用户聚合；录入/导入结果；驱动疫情图。
- 互动内容审核管理：提问/经验/回复的审核、过滤、隐藏/恢复/封禁。
- 推荐配置：疾病-产品推荐库；企业提交内容需初审。
- 权限与模块配置：角色与模块可见性；用户反馈与行为数据。
 - 支付凭证审核：审核 `payment_proofs`，通过后为订单分配 `ZF-` 检测号并置订单 `paid`。
 - 邮寄通知查看：基于 `shipping_notifications` 对单与进度跟踪。

### 5.3 企业用户模块（后台管理）
- 企业注册与登录：账号密码/或由管理员分配并审核。
- 客户检测结果查看：基于使用该企业检测号的蜂农数据；支持时间/地区/病种/结果维度筛选与统计。
- 数据可视化：趋势图、热力图、分布饼图（支持筛选）。
- 产品推荐管理：按病种配置产品与内容；检测结果页向蜂农展示。
- 品牌与内容：回复蜂农提问（标注企业建议）、投稿知识内容（需管理员初审）。
- 代金券：赞助激励配置与发放统计。


## 6) 后台面板（Filament）与访问控制
- 计划提供两个 Panel：
  - `/admin`：超管专用，仅 `super_admin` 可访问。
  - `/enterprise`：企业面板，仅 `enterprise_admin` 可访问。
- 资源建议：用户、企业、检测号、订单、检测记录、问卷、病种、检测结果、产品、病种-产品映射、推荐规则、内容（提问/经验/回复）审核、奖励规则、代金券、发放记录、地区/字典、仪表盘统计。

— 面板职责补充
- Admin：检测号池、订单与支付凭证审核、邮寄通知、检测记录/结果管理、推荐规则、内容审核、地区/字典、统计。
- Enterprise：本企业检测数据浏览（来源企业码）、产品与推荐管理、内容投稿/回复、邮寄与进度查看。


## 7) API 原则与约定
- 认证：小程序端使用 Sanctum Token；登录接口仅接受 `code` 并服务端与微信交互校验。
- 响应：JSON；分页统一（`page`/`per_page`），统一错误码与错误体结构（保留 `code`、`message`、`errors`）。
- 校验：使用 Form Request；对状态流转（如检测号使用）采用事务与乐观约束（唯一索引）防止并发重复使用。
- 资源命名：REST 优先，必要时用动词子路径表述动作（如 `/wechat/notify`）。
- 推荐：按“检测号来源企业或自费”动态计算，尽量通过查询组合或缓存实现。
- 日志与审计：关键动作写入审计日志（创建/审核/发放/状态变更）。
  - 统一参考《审计与可观测性规划》（docs/specs/audit-observability.md），对检测码分配/使用、订单支付、邮寄提交等动作记审计。

— 响应规范（统一标准）
- 统一遵循《API 响应规范 v1.0》：`docs/specs/api-response-v1.md`
- 错误码遵循《标准错误码清单 v1.0》：`docs/specs/error-codes-v1.md`
- 前端展示参考《前端展示格式化清单 v1.0》：`docs/specs/frontend-formatting-cheatsheet.md`
 - 资源形状说明：`docs/specs/api-shape.md`

— 首期接口清单（与小程序联调）
- 认证与资料：
  - `POST /api/auth/wechat/login` → `{ token, user }`（依赖微信官方接口）
  - `POST /api/auth/wechat/bind-phone` → `{ phone }`
- 检测流程：
  - `POST /api/detection-codes/verify-bind` → 入参 `{ detection_number, phone }`，原子化置 `assigned` 并绑定用户，返回 `detection_code_id`
  - `POST /api/surveys` → `{ detection_code_id, ...问卷字段 }`；成功后置码为 `used`
  - `POST /api/shipping-notifications` → `{ detection_number, courier_company, tracking_no, shipped_at? }`
- 自费下单与人工审核：
  - `POST /api/orders`（创建） → 返回占位信息
  - `POST /api/orders/{id}/payment-proof` → 上传凭证，后台审核通过后分配检测号并置订单 `paid`
- 检测结果：
  - `GET /api/detections`（分页） → 列表包含：`id`、`detectionId`、`sampleId?`、`submitTime`、`status`/`statusText`、`recommendation{ productId?, productName, brief, url?, source, targetType? }`
  - `GET /api/detections/{id}` → 明细含病种结果与推荐
- 疫情数据：
  - `GET /api/epidemic/distribution?province=&city=&district=&month=YYYY-MM` → `{ list:[{ diseaseCode,diseaseName,positive,samples,rate }], totalPositive, totalSamples, updatedAt }`
  - `GET /api/epidemic/trend?province=&city=&district=&diseaseCode=&toMonth=YYYY-MM` → `{ points:[{ month,positive,samples,rate }], diseaseCode, updatedAt }`


## 8) 编码规范与变更约束
- 语言与标准：PHP 8.2，PSR-12，遵循 Laravel 惯例（Eloquent 命名、迁移命名、Seeder/Factory 使用）。
- 变更范围：仅实现与当前任务直接相关的代码；避免“顺手修复”不相关问题（可在说明中指出）。
- 迁移与数据：
  - 新增字段/表使用迁移；必要时添加唯一索引与检查约束以固化业务规则（如检测号唯一、手机号唯一、openid 唯一）。
  - 涉及状态机的流程（检测号使用）务必在事务内完成并保证原子性。
- 面板资源：使用 Filament Resource 与 Relation Manager；遵循面板的导航结构与授权策略。
- 安全：输入严格校验；上传文件校验类型与大小；敏感数据加密保存。
- 配置：所有外部依赖（微信、支付、存储等）通过 .env 配置；微信登录需配置官方凭据。

— 配置建议（可放 .env）
- `DETCODE_PREFIX_SELF=ZF`、`DETCODE_PREFIX_ENTERPRISE=QY`、`DETCODE_RULE=self:date-seq`（示例）；
- 微信小程序：`WECHAT_MINI_APP_ID`、`WECHAT_MINI_APP_SECRET`（用于调用官方接口）。

— 并发与一致性
- `detection_codes.code` 唯一索引；`assigned`/`used` 流转置于同一事务，必要时使用“状态 + 唯一约束”防止重复使用。
- 订单支付成功到检测号分配在一个事务或幂等处理内完成；人工审核流程需幂等（同一订单仅能成功一次）。


## 9) 测试与验证
- 测试优先靠近改动：为关键领域服务与控制器添加 Feature/Unit 测试。
- 使用 MySQL 独立测试库运行测试（`phpunit.xml` 已配置或通过环境变量覆盖）；需预先创建测试数据库（默认 `bee_guard_test`）。
- 不存在既有测试时，新增紧贴改动的最小必要测试；不要为不相关模块补测。


## 10) 本地开发
- 微信登录依赖微信官方接口（需配置 `WECHAT_MINI_APP_ID/SECRET`）。在无法接入微信接口的场景，可采用以下替代方式进行联调：
  - 在数据库创建测试用户并手动发放 Sanctum Token（仅用于本地开发）。
  - 使用已有 Token 进行业务流程联调（问卷、邮寄、订单与凭证等）。
- （如未来接入支付网关）可提供本地伪造通知端点串通订单→分配检测号→问卷流程。
 - 开发替代方案：启用 `payment_proofs`，小程序上传支付截图与金额，后台审核通过后视同支付成功并分配 `ZF-` 检测号。
 - 疫情接口可在开发模式下返回基于地区与月份的“种子化”稳定假数据（便于前端演示与回归）。


## 11) 未决事项（待产品确认）
- 自费检测号前缀是否固定为 `ZF`，编码格式是否为 `ZFYYYYMMDDNNN`？
- 行政区使用方案：是否先用简单省市县字段，后续导入全国行政区标准库？
- 推荐算法是否需要 A/B 或权重配置，还是静态映射为主？
- 是否需要短信能力（非登录场景，如通知备用）？


## 12) 快速清单（Agent 执行时自查）
- 是否遵循了本文件与更深层 AGENTS.md 的要求？
- 是否使用迁移而非直接改库？是否添加必要索引/约束？
- 是否避免引入与任务无关的变更？
- 是否提供了最小必要测试或说明验证步骤？
- 是否将外部集成做成可配置？
 - 首批接口是否覆盖：登录、绑定手机号、检测号验证绑定、问卷提交、邮寄上报、订单与（回调/凭证）分配、结果查询、疫情分布/趋势？
 - `detection_codes` 原子化状态流与并发保护是否落实（事务+唯一约束/乐观锁）？


## 13) 变更记录
- 2025-09-12：首版建立，整合产品需求与开发约束，明确“微信登录、三角色、两面板”的核心决策；沉淀数据模型与流程骨架。
- 2025-09-12：补充与小程序对齐的接口与数据模型细化：新增登录/绑定手机号接口约定；完善检测号验证绑定、自费支付（人工凭证审核）流程；补充 `shipping_notifications`、`payment_proofs` 表；明确结果返回结构与疫情接口形态；增加前缀/规则配置与并发一致性要求。
 - 2025-09-17：精简微信登录相关描述，统一为官方接口 + 必需配置项，清理无关实现细节以避免歧义。
- 资源形状说明（重要）：当前交互以“检测码”为锚点
- 问卷/邮寄接口以检测码驱动（`detection_code_id` 或 `detection_number`），原因是在问卷阶段实验室尚未创建 `detections` 主记录，无法提供稳定 `detection.id`。检测结果展示以 `GET /api/detections`/`{id}` 进行。
