-- ============================================================
-- 007：SKU 资料确认（缓冲态）
-- 变更要点：
--   1. 新增 is_confirmed：0=待确认（客户可编辑）1=已确认（锁定关键字段）
--   2. 锁定字段范围：sku_code / name_cn / name_en / weight / length /
--      width / height / declare_value / hs_code / attrs
--      （color、图片、中英文描述不锁定，可随时修改）
--   3. sku_code 创建后永久不可改（前后端均已控制），条码随编码生成亦不变
--   4. confirm_time 记录最近一次确认时间；解锁后重新确认会刷新
-- ============================================================

ALTER TABLE `hw_sku`
  ADD COLUMN `is_confirmed` TINYINT NOT NULL DEFAULT 0
    COMMENT '资料确认：0 待确认（可编辑）1 已确认（锁定关键字段）' AFTER `status`,
  ADD COLUMN `confirm_time` INT UNSIGNED NOT NULL DEFAULT 0
    COMMENT '最近一次确认时间' AFTER `is_confirmed`,
  ADD KEY `idx_customer_confirm` (`customer_id`, `is_confirmed`);
