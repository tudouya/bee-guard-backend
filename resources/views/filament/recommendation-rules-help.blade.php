<div class="prose max-w-none">
  <h3>推荐规则说明</h3>
  <p>用于在小程序“检测结果详情”中向蜂农展示推荐产品。排序遵循“企业优先、平台补足、赞助置顶、时间窗生效”的原则。</p>

  <h4>关键字段</h4>
  <ul>
    <li><strong>Scope</strong>（作用域）：Enterprise（企业规则）或 Global（全局规则）。</li>
    <li><strong>Applies To</strong>（适用）：self_paid / gift / any。</li>
    <li><strong>Tier</strong>（层级）：跨作用域排序，数值越小越靠前（默认：Enterprise=10，Global=20）。</li>
    <li><strong>Priority</strong>（优先级）：同层内排序，数值越小越靠前。</li>
    <li><strong>Sponsored</strong>（赞助）：标识推广位，通常配合更小的 Tier 提升曝光。</li>
    <li><strong>Active / Starts / Ends</strong>：仅在启用且处于时间窗内的规则才会生效。</li>
  </ul>

  <h4>排序规则</h4>
  <ol>
    <li>企业码（gift 且绑定企业）：企业规则 + 全局规则合并，按 <code>Tier</code> → <code>Priority</code> 排序；若不足，再用疾病-产品映射补全（优先本企业产品，再任意企业）。</li>
    <li>自费码（self_paid）：全局规则按 <code>Tier</code> → <code>Priority</code> 排序；若不足，再用映射补全。</li>
  </ol>
  <p>通用过滤：仅推荐 <code>products.status=active</code> 的产品；同一产品去重；仅取阳性相关疾病的规则。</p>

  <h4>快速示例</h4>
  <ul>
    <li>企业优先 + 平台补足：新建企业规则（Tier=10），再建全局规则（Tier=20）。</li>
    <li>赞助置顶：将赞助规则的 Tier 设置为更小（如 8），即可排在企业规则之前。</li>
    <li>限时活动：设置 Starts/Ends，时间窗内自动生效，过期自动失效。</li>
    <li>Gift/自费专属：通过 Applies To 精准限定。</li>
  </ul>

  <h4>注意事项</h4>
  <ul>
    <li>企业作用域仅可选择该企业的产品。</li>
    <li>若企业无命中规则，且无本企业映射，平台可通过“全局规则”推广其他企业产品。</li>
    <li>小程序详情页默认展示前两条推荐，优先级配置将直接影响曝光位。</li>
  </ul>
</div>

