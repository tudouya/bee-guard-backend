# 推荐规则人工测试操作说明（Admin 面板）

本说明面向测试人员，指导如何仅通过 Admin 面板完成“推荐规则 → 小程序展示”的人工端到端测试，覆盖企业优先、平台补足、赞助置顶、时间窗生效等场景。

---

## 一、前提与账号
- 以 `super_admin` 身份登录 Admin 面板 `/admin`。
- 准备一个测试用蜂农账号（普通用户，建议带手机号），用于绑定检测码与查看小程序结果。
- 小程序端已指向本环境的 API，并能使用该蜂农账号登录。

## 二、基础数据准备（Admin 面板）
1) 疾病字典
- 菜单：Diseases
- 确认存在待测试疾病（例如：SBV 囊状幼虫病）。若无请新建，`code=SBV`、`name=囊状幼虫病`。

2) 企业与产品
- 菜单：Enterprises
  - 新建“企业A”（建议 `code_prefix=QYA`）与“企业B”（建议 `code_prefix=QYB`）。
- 菜单：Products
  - 为企业A新建产品“企业A产品”，`status=active`（可不填 URL，便于测试 internal 推荐）。
  - 为企业B新建产品“企业B产品”，`status=active` 且填写 `url=https://example.com/b`（用于 external 推荐）。

3) （可选）疾病-产品映射（兜底）
- 在 Products 或 Diseases 的详情页（若提供关系管理器）中，为 SBV 建立与上述产品的映射并设置 `priority`（A 优先于 B）。
  - 作用：当规则不足时，API 会用映射补足推荐（先本企业产品，再任意企业）。

## 三、配置推荐规则（Recommendation Rules）
1) 企业优先（默认策略）
- 新建规则：
  - Scope=Enterprise；Enterprise=企业A
  - Applies To=gift（赠送码）
  - Disease=SBV；Product=企业A产品
  - Priority=0；Tier=10（默认）；Active=ON
  - 时间窗：默认空（即立即生效），或设置为覆盖当前时间

2) 平台补足（全局规则）
- 新建规则：
  - Scope=Global
  - Applies To=gift
  - Disease=SBV；Product=企业B产品
  - Priority=0；Tier=20（默认）；Active=ON

3) 赞助置顶（可选）
- 在“平台补足”规则上开启 Sponsored（仅标识），并将 `Tier` 调小（例如 8）。
  - 效果：该全局推广可跨层级优先，排在企业规则之前；用于测试“付费推广”曝光位。

提示：列表页右上角有“规则说明”按钮，包含字段解释与排序逻辑（Tier 越小越靠前；同层按 Priority）。

## 四、造检测数据（Admin 面板）
1) 发放企业检测码并分配给蜂农
- 菜单：Detection Codes
  - 点击“创建”，设置：
    - Source Type=gift；Enterprise=企业A
    - Status=assigned（已分配）；Assigned User=测试蜂农用户；Assigned At=当前时间
  - 保存后记录“prefix+code”（即检测号）

2) 生成检测记录并设置阳性病原
- 菜单：Detections
  - 点击“创建”，选择上一步的 `Detection Code`（表单通常会自动带出 `User`）
  - 设置：
    - Status=completed；Reported At=当前时间（或合适时间）
    - 结果：将 `SBV` 对应强度设为 `weak`/`medium`/`strong`（阳性）
  - 保存。此时该检测具备展示推荐的条件（来源、企业、阳性病原齐备）。

## 五、小程序端查看与验收
1) 登录小程序为同一测试蜂农账号。
2) 打开“检测结果详情”页，查看“推荐”区域（默认展示前 2 条）。
3) 期望结果：
- 无“赞助置顶”时：
  - 第 1 条为“企业推荐”（企业A产品，可能为 internal 推荐）
  - 第 2 条为“平台推荐”（企业B产品，external 推荐，有链接）
- 开启“赞助置顶”（Tier=8）后：
  - 第 1 条为“平台推荐”（企业B产品）
  - 第 2 条为“企业推荐”（企业A产品）

说明：
- 仅 `Active=ON` 且处于时间窗内的规则有效；产品需 `status=active`。
- 规则优先级：按 `Tier` 升序（越小越靠前），同层按 `Priority` 升序。
- 若规则不足，将按疾病-产品映射补足（先本企业产品，再任意企业）。

## 六、边界与负面用例（建议覆盖）
- 关闭企业规则（Active=OFF）：应由全局规则顶上；如仍不足再落到映射。
- 调整时间窗，使规则过期：应不再生效。
- 修改 Applies To 为 self_paid，再用 gift 测试：应不命中该规则。
- 将某产品下架（status=inactive）：对应推荐应消失/被后续候选顶替。
- 企业无产品：依赖全局规则；若设置“赞助置顶”可验证跨企业推广曝光。

## 七、常见排查
- 推荐为空：检查是否存在阳性病原；规则是否 Active 且在时间窗内；产品是否上架；Applies To 是否匹配来源；是否被更高优先级覆盖。
- 赞助不靠前：确认已将该规则 Tier 调小（如 8），并 Active=ON 且在时间窗内。
- 顺序异常：检查 Tier 与 Priority；确认没有重复/冲突规则。

---

如需进一步辅助（例如新增示例数据、导入模板或一键清理），请联系开发同学评估是否在 Admin 面板补充“测试工具”入口。
