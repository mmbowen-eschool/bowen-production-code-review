# Finance Category 生产分类回填确认报告

**报告日期**: 2026-06-13
**数据来源**: 生产服务器 (43.160.241.126)
**学校**: SCH202615 / Zixuan
**数据库名称**: `eschool_saas_15_zixuan`
**操作类型**: SELECT ONLY — 只读，未执行任何写入

---

## 1. 检查背景

本项目已引入新的 `finance_categories` 财务分类体系，需要在生产环境确认 **fees_class_types** 和 **expenses** 表中 `finance_category_id IS NULL` 的真实情况，并评估是否可以进行安全的数据回填。

本次检查基于 Phase 3.2 生产服务器 Dry Run 的输出结果，对 SCH202615 (Zixuan) 学校数据库进行了完整的只读分析。

## 2. Finance Categories 当前状态

生产环境共有 **19 个** Finance Categories（含 2 个 Inactive 测试分类）。有效分类已覆盖常见的收入和支出类型：

| ID | Name | Type |
|----|------|------|
| 1 | Tuition Fee | Income |
| ... | (其他有效收入分类) | Income |
| 9 | Salary | Expense |
| ... | (其他有效支出分类) | Expense |

**结论**: 现有 Finance Categories 已覆盖所有当前未分类数据的需求，**不需要新增 Finance Category**。

## 3. Fee Items (费用项目) 未分类分析

### 3.1 数据概况

| 指标 | 数值 |
|------|------|
| Total fees_class_types | 18 |
| Already categorized | 3 |
| **Uncategorized (NULL)** | **15** |
| Auto Backfill | 0 |
| Manual Review | 15 |
| Uncategorized Fee Total | 5,290,996 MMK |

### 3.2 为什么 15 条全部被标记为 Manual Review？

Dry Run 脚本中，自动回填的置信度判断规则是：当一个费用项的 `Item_Name` 或关联 `Fee_Name` 为 **Fuzzy Name**（模糊名称，如 "Other"、"其他"、"Misc" 等）时，即使关键词匹配成功，也会从 `auto_backfill` 降级为 `manual_review`。

这 15 条 Fee Items 的详细情况：

- **Item_Name**: 全部为 **"学费"**（中文，即 Tuition Fee）
- **Suggested Category**: 全部为 **Tuition Fee (ID=1)**
- **降级原因**: 关联的 `Fee_Name` 字段为空，脚本对空名称采用了保守策略，判定为需人工确认

### 3.3 结论

**这 15 条的 Item_Name 明确为"学费"，分类目标完全确定**。从业务角度看，它们都应该归类为 **Tuition Fee (ID=1)**。脚本只是出于安全考虑将它们标记为 manual_review，实际上分类结果毫无歧义。

## 4. Expenses (支出) 未分类分析

### 4.1 数据概况

| 指标 | 数值 |
|------|------|
| Total expenses | 4 |
| Already categorized | 3 |
| **Uncategorized (NULL)** | **1** |
| Auto Backfill | 1 |
| Manual Review | 0 |
| Uncategorized Expense Total | 10,000,000 MMK |

### 4.2 未分类支出详情

| 字段 | 值 |
|------|-----|
| Exp_ID | 1 |
| Title | May - 2026 |
| Description | Salary |
| Amount | 10,000,000 MMK |
| Suggested Category | Salary (ID=9) |
| Action | auto_backfill |

**分析**:

- Description 明确为 "Salary"
- 关键词直接匹配 Expense Category "Salary" (ID=9)
- 无模糊性，**可安全归类为 Salary (ID=9)**

## 5. 是否需要新增 Finance Category？

**不需要。** 现有 Finance Categories 已覆盖所有未分类数据的需求：

- Tuition Fee (ID=1) → 覆盖 15 条"学费"
- Salary (ID=9) → 覆盖 1 条 Salary 支出

## 6. 是否建议今天执行真实回填？

**不建议。** 经进一步分析确认，当前 Uncategorized 数据主要来自历史测试数据——创建这些测试 Fee Items 时，系统尚未引入完整的 Finance Category / Report Category 功能，因此 `finance_category_id` 为 NULL。详见下方第 9 节最终决策。

## 7. 未来执行真实回填的安全原则（仅供参考，本次不执行）

> 以下原则仅在**未来确有需要时**参考，当前 Phase 3.4 已决定不执行真实回填。

### 7.1 执行前

- **必须备份** `fees_class_types` 和 `expenses` 表
- 确认备份文件可恢复
- 再次运行 Dry Run 确认数据未发生变化

### 7.2 执行中

- **只更新** `finance_category_id IS NULL` 的记录
- **不覆盖** 已有分类（`finance_category_id IS NOT NULL` 的记录）
- **不修改** 以下字段：`amount` / `status` / `student_id` / `fees_id` / `date`
- **不修改** 以下表：`compulsory_fees` / `optional_fees` / `fees_paids`

### 7.3 执行后

必须验证以下模块报表数据正常：

| 验证模块 | 检查内容 |
|----------|----------|
| Finance Report | 收入/支出按分类汇总正确 |
| Dashboard | 财务概览图表数据正确 |
| Outstanding Fees | 未缴费用列表数据正确 |
| Student Ledger | 学生账单明细数据正确 |

## 8. 下一步建议

由于当前 Uncategorized 数据主要来自历史测试数据，且不影响 Total Income、Net Income、Outstanding、Student Ledger 或 Receipt，本阶段决定**不执行真实回填**。

后续正式数据录入时，应在 **Fee Setup** / **Expense 创建或编辑页面** 中选择正确的 Finance Category，以避免新数据继续进入 Uncategorized。

| 步骤 | 负责人 | 内容 |
|------|--------|------|
| 1 | 管理员 | 在 Fee Setup / Expense 页面中，创建或编辑时选择正确的 Finance Category |
| 2 | 开发人员 | 如需可考虑在 Fee Setup / Expense 表单中将 Finance Category 设为必填项 |
| 3 | — | 后续如有新学校上线，Finance Category 应由管理员在数据录入时一并设置，不再需要批量回填脚本 |

## 9. 最终决策：不执行真实回填

### 决策依据

经过 Phase 3.2 生产 Dry Run 分析，SCH202615 / Zixuan 当前 Uncategorized 数据（15 条 Fee Items + 1 条 Expense）主要来自 **历史测试数据**。创建这些测试 Fee Items 时，系统尚未引入完整的 Finance Category / Report Category 功能，因此 `finance_category_id` 为 NULL。

### 重要发现

这些历史测试数据的 `finance_category_id = NULL` **不影响**以下核心财务模块：

- **Total Income / Net Income** — 金额计算不依赖 `finance_category_id`
- **Outstanding Fees** — 未缴费用按 `fees_id` / `student_id` 计算
- **Student Ledger** — 学生账单按费用项目关联
- **Receipt** — 收款不依赖 `finance_category_id`

### 最终决定

| 决定 | 说明 |
|------|------|
| **不执行 UPDATE 回填** | 不对历史测试数据执行 `finance_category_id` 回填 |
| **不修改数据库** | 保留当前数据状态不变 |
| **不执行 fix_finance_categories.php** | 不运行任何回填脚本 |
| **不执行任何真实回填脚本** | Phase 3.4 暂停 |

### 后续正式数据管理原则

后续**正式新增** Fee Items / Expenses 时，由管理员在页面中直接选择正确的 Finance Category / Report Category：

- **Fee Setup 页面**: 创建/编辑 Fee Item 时选择 Finance Category
- **Expense 创建/编辑页面**: 创建/编辑 Expense 时选择 Finance Category

这样可以确保**新数据从源头就有正确的分类**，不再产生 Uncategorized 数据。

---

**报告结束**

*此报告基于生产服务器 SELECT ONLY 查询结果生成，未对数据库执行任何写入操作。*
