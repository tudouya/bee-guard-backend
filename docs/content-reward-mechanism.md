# 内容奖励机制说明

## 模块概览
- **购物券模板（企业面板）**：企业用户在 `/enterprise` 面板提交购物券模板，填写平台、店铺名称/链接、有效期、面值等信息，提交后状态为 `pending_review`，待管理员审批。
- **奖励规则（管理员面板）**：管理员在 `/admin` 面板配置奖励规则，选择指标（点赞/浏览/回复）、阈值、发放模式（自动/手动），并绑定已审核通过的购物券模板。
- **奖励发放队列（管理员面板）**：查看自动或手动生成的奖励记录；手动模式下管理员可“通过发放”或“驳回”，系统会写入审计日志并向蜂农推送通知。
- **蜂农端 API**：蜂农通过 `/api/rewards` 系列接口查看奖励列表/详情、确认奖励、标记已使用，个人中心可据此展示奖励状态。

## 数据流
1. **企业提交模板** → `coupon_templates` 状态 `pending_review` → 管理员审核通过后变为 `approved`，供奖励规则引用。
2. **管理员配置奖励规则** → `reward_rules` 记录指标/阈值/模式。
3. **蜂农内容触发**：帖子浏览/点赞/回复数满足某条奖励规则 → `RewardEvaluator` 调用 `RewardIssuer` 生成 `reward_issuances` 记录。
   - 自动模式：直接生成 `issued`，立即通知蜂农。
   - 手动模式：生成 `pending_review`，管理员在队列列表中审批后才发放。
4. **蜂农查看/操作**：通过 `/api/rewards` 查询奖励、确认、标记已使用，同时可以在数据库通知中看到奖励提醒。

## API 一览
- `GET /api/rewards?status=`：分页列出蜂农奖励（可按状态筛选，状态值：pending｜usable｜used｜expired）。
- `GET /api/rewards/{id}`：奖励详情。
- `POST /api/rewards/{id}/acknowledge`：记录蜂农已知晓奖励。
- `POST /api/rewards/{id}/mark-used`：已发放奖励标记“已使用”。
- `GET /api/rewards/summary`：返回待审核、可使用、已使用、已过期的奖励数量，用于概览与标签角标。

## 队列与通知
- 奖励评估、通知均依赖队列，默认使用 `database` 连接。
- 需运行 `php artisan queue:work` 或使用 Horizon，确保 `EvaluatePostRewardsJob` 和通知分发及时执行。
- 奖励发放通知使用 Laravel 数据库通知（`notifications` 表），小程序可通过轮询或订阅机制消费。

## 运维要点
- 确保 `notifications` 表已迁移：`php artisan migrate --path=database/migrations/2025_09_16_090000_create_notifications_table.php`。
- 若需暂时关闭奖励评估，可在 `.env` 将 `REWARD_EVALUATION_ENABLED=false`，系统只会继续发放已有待审核奖励。
- 定期检查 `reward_issuances` 的 `audit_log` 字段以追踪状态变化，支持问题溯源。

## 调试建议
- 开发环境可通过工厂快速生成规则/模板：参见 `database/factories/CouponTemplateFactory.php`、`RewardRuleFactory.php`。
- 使用 `Notification::fake()`、`Queue::fake()` 辅助编写 Feature 测试，可参考 `tests/Feature/Api/RewardsTest.php`。
