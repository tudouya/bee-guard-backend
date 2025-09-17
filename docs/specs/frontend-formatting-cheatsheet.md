# 前端展示格式化清单（v1.0）

本清单辅助前端在各项目中一致地展示 API 数据，配合《API Response Specification v1.0》使用。默认时间/金额/空值等格式以面向中文用户的常见习惯为准。

## 通用策略
- 空值兜底：对 `null` 做统一兜底，避免在界面上出现字面量 `null`。
- 不强转：避免将 `null/undefined` 直接字符串化（例如模板插值 `${value}` 前做好空值处理）。
- 纯展示层处理：格式化仅影响显示，不回写到数据模型。

## 空值兜底
- 文本：`value ?? '—'` 或空字符串 `''`（按场景选择）。
- 数值：`value ?? 0`（展示统计类数据时），或 `value == null ? '—' : value.toString()`。
- 日期/时间：`value ?? '—'`（先判断再格式化）。
- 列表：空数组显示 placeholder（如“暂无数据”）。

示例（TypeScript）：
```
const text = (v: string | null | undefined) => v ?? '—';
const int = (v: number | null | undefined) => (v == null ? '—' : String(v));
```

## 日期与时间
- 约定格式：
  - 日期时间：`YYYY-MM-DD HH:MM:SS`
  - 日期：`YYYY-MM-DD`
  - 时区：默认以 Asia/Shanghai 输出；服务端已转换，无需再转。
- 格式化：
  - `dayjs(value).format('YYYY-MM-DD HH:mm:ss')`
  - `dayjs(value).format('YYYY-MM-DD')`
- 兜底：`value ? dayjs(value).format(...) : '—'`

## 金额与数值
- 金额传输为字符串十进制，请勿用浮点参与计算。
- 展示时可转数字并格式化千分位：
```
const money = (v: string | null | undefined, currency = 'CNY') =>
  v == null ? '—' : `${Number(v).toLocaleString('zh-CN', { minimumFractionDigits: 2 })}`;
```
- 比例/百分比：`(num * 100).toFixed(2) + '%'`，注意 `null` 兜底。

## 布尔与状态
- 三态显示：`true/false/null` → `是/否/—`。
```
const yesNo = (b: boolean | null | undefined) => (b == null ? '—' : b ? '是' : '否');
```

## 列表与对象
- 数组：无元素显示“暂无数据”或留白；不要把 `null` 当数组。
- 对象：嵌套字段可能为 `null`，解构前先判空或用可选链。
```
const name = data?.user?.nickname ?? '—';
```

## ID 与大整数
- 以字符串返回，直接展示即可；如需截断显示，保留末尾 6–8 位：
```
const shortId = (id: string | null | undefined) => id ? id.slice(-8) : '—';
```

## 组件/模板示例
- React/JSX：
```
<span>{text(user?.nickname)}</span>
<span>{yesNo(record?.active)}</span>
<span>{record?.submittedAt ? dayjs(record.submittedAt).format('YYYY-MM-DD HH:mm:ss') : '—'}</span>
<span>{money(order?.amount)}</span>
```
- Vue（SFC）：
```
<span>{{ text(user?.nickname) }}</span>
<span>{{ yesNo(record?.active) }}</span>
<span>{{ record?.submittedAt ? dayjs(record.submittedAt).format('YYYY-MM-DD HH:mm:ss') : '—' }}</span>
<span>{{ money(order?.amount) }}</span>
```

## 建议的工具依赖
- 日期：dayjs 或 date-fns（体积小、用法简单）。
- 国际化：i18n 统一文案（“暂无数据/—/是/否”等）。

## 测试与验收
- 单测/快照：对格式化函数做最小单元测试（空值、非法值、正常值）。
- 联调验收：重点检查时间为秒级、金额两位小数、空值统一为 `—` 不出现字面量 `null`。

