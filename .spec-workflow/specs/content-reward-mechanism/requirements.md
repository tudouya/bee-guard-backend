# Requirements Document

## Introduction

The content reward mechanism motivates farmers to produce high-quality experience posts by granting incentives when engagement milestones are met. It builds on the existing community metrics pipeline—`community_posts` (views, likes, replies_count) and `community_post_likes`—so thresholds can be evaluated without adding new counters, while formalizing reward payloads and fulfillment workflows for consistent incentive delivery.

## Alignment with Product Vision

Rewarding outstanding user-generated content strengthens the community pillar of Bee Guard by promoting knowledge sharing, increasing app stickiness, and enabling enterprise sponsors to highlight their products within relevant, high-trust contexts.

## Requirements

### Requirement 1

**User Story:** As an enterprise administrator, I want to登记购物券模板并提交给平台审核, so that my sponsored rewards can be准确展示到蜂农的个人中心。

#### Acceptance Criteria

1. WHEN an enterprise user creates or updates a coupon template THEN the system SHALL require platform identifiers (e.g., 京东/淘宝), store name, store URL, coupon face value, usage description, validity period, and quantity policy.
2. WHEN a template is submitted THEN the system SHALL mark it `pending_review` and notify administrators for approval.
3. IF an administrator rejects a template THEN the system SHALL record the rejection reason and return the template to the enterprise for revision.
4. WHEN a template is approved THEN the system SHALL expose it to reward rule configuration while preserving an audit trail of the enterprise submitter and administrator reviewer.

### Requirement 2

**User Story:** As a system administrator, I want to配置奖励规则并绑定已审核通过的购物券模板, so that qualifying帖子可以按照既定阈值获得对应奖励。

#### Acceptance Criteria

1. WHEN an administrator creates or updates a reward rule THEN the system SHALL support selecting the metric source from existing `community_posts` fields (views, likes, replies_count) and defining the threshold value (e.g., likes ≥ 100).
2. WHEN saving a reward rule THEN the system SHALL require binding to one or more approved reward assets (coupon template, badge,讲师团资格等) and capture fulfillment mode (automatic or manual).
3. IF an administrator defines a coupon reward THEN the system SHALL capture voucher metadata inherited from the template (platform, store name, store link, validity, quantity) to ensure downstream display consistency.
4. WHEN a reward rule is saved THEN the system SHALL validate that referenced enterprises, coupon templates, and reward assets exist and are active to prevent broken configurations.
5. WHEN fulfillment mode is set to manual THEN the system SHALL route qualifying content into a待发放列表供管理员审核。

### Requirement 3

**User Story:** As the platform, I want to evaluate content engagement against reward rules so that qualifying posts are flagged and rewards are allocated according to the configured fulfillment mode.

#### Acceptance Criteria

1. WHEN a piece of content meets or exceeds a configured threshold AND the rule is automatic THEN the system SHALL create a reward issuance record, decrement available coupon quantity (if limited), and notify the farmer without human intervention.
2. WHEN a piece of content meets or exceeds a configured threshold AND the rule is manual THEN the system SHALL flag the content for review and block reward issuance until an administrator approves it; upon approval, the issuance record SHALL be generated with the administrator as issuer.
3. IF multiple reward rules apply to the same content THEN the system SHALL evaluate each rule independently and attach all qualifying rewards without duplication.
4. WHEN rewards are issued THEN the system SHALL persist an audit trail that records the content, rule, issuer (system or administrator), coupon template version, and timestamp.
5. WHEN evaluating thresholds THEN the system SHALL reuse the persisted engagement metrics and like records rather than calculating ad-hoc aggregates to ensure consistency with the moderation pipeline.

### Requirement 4

**User Story:** As a farmer who owns qualified content, I want to查看并使用我获得的奖励, so that I understand the benefits provided by the platform and sponsors。

#### Acceptance Criteria

1. WHEN a reward is granted THEN the system SHALL notify the content author with reward details including reward type, sponsoring enterprise, platform (京东/淘宝等), store link, usage instructions, and expiry.
2. WHEN a farmer views their个人中心的“我的奖励”页面 THEN the system SHALL list rewards grouped by status（待审核、可使用、已使用、已过期）并展示关联帖子。
3. IF a reward has an expiration date THEN the system SHALL display the expiry and mark rewards as inactive after the date passes.
4. WHEN a reward requires manual approval THEN the farmer-facing view SHALL reflect its pending status until administrators complete the review.
5. WHEN a coupon reward includes a store link THEN tapping the link SHALL跳转至对应平台的店铺或优惠券详情。

## Non-Functional Requirements

### Code Architecture and Modularity
- **Single Responsibility Principle**: Each file should encapsulate a focused concern such as template management, rule configuration, evaluation service, or notification dispatch.
- **Modular Design**: Separate enterprise coupon submission, administrative review, eligibility evaluation, reward issuance, and notification delivery into distinct services or actions.
- **Dependency Management**: Reuse existing content engagement tracking and voucher subsystems without duplicating logic.
- **Clear Interfaces**: Define contracts for reward issuance to allow future reward types（如勋章、讲师团资格）to plug in.

### Performance
- Evaluation jobs SHALL complete within acceptable background processing windows (e.g., queue execution) and avoid scanning the entire content dataset by using indexed metrics and incremental processing.

### Security
- Authorization SHALL restrict coupon template submission to enterprise users, reward rule management to administrators, and prevent farmers from self-awarding rewards.

### Reliability
- Reward issuance SHALL be idempotent so reprocessing or retrying jobs does not create duplicate rewards.
- Threshold evaluation SHALL rely on the existing moderation-updated metrics so that approvals and counter updates stay in sync.
- Coupon stock management SHALL prevent issuing beyond available quantities.

### Usability
- Enterprise和管理员表单 SHALL provide descriptive labels and validations so users understand required fields and constraints when配置模板与规则。
- Farmer-facing reward pages SHALL present清晰的使用步骤与平台信息以提升兑换体验。
