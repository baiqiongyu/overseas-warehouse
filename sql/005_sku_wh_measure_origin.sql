-- ============================================================
-- 005：SKU 增加「仓库实测」尺寸重量 + 原产地
-- 变更要点：
--   1. 原有 weight/length/width/height 语义 = 客户申报（客户门户维护）
--   2. 新增 wh_ 前缀字段 = 仓库实测（仓库端录入，客户只读，列表展示）
--   3. 新增 origin 原产地，创建时可选（默认 CN），打印面单/标签时直接继承
-- ============================================================

ALTER TABLE `hw_sku`
  MODIFY `weight` DECIMAL(10,3) NOT NULL COMMENT '客户申报重量 kg',
  MODIFY `length` DECIMAL(10,2) NOT NULL COMMENT '客户申报长 cm',
  MODIFY `width`  DECIMAL(10,2) NOT NULL COMMENT '客户申报宽 cm',
  MODIFY `height` DECIMAL(10,2) NOT NULL COMMENT '客户申报高 cm',
  ADD COLUMN `origin` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '原产地代码（如 CN/VN，创建时选）' AFTER `hs_code`,
  ADD COLUMN `wh_weight` DECIMAL(10,3) NULL DEFAULT NULL COMMENT '仓库实测重量 kg' AFTER `height`,
  ADD COLUMN `wh_length` DECIMAL(10,2) NULL DEFAULT NULL COMMENT '仓库实测长 cm' AFTER `wh_weight`,
  ADD COLUMN `wh_width`  DECIMAL(10,2) NULL DEFAULT NULL COMMENT '仓库实测宽 cm' AFTER `wh_length`,
  ADD COLUMN `wh_height` DECIMAL(10,2) NULL DEFAULT NULL COMMENT '仓库实测高 cm' AFTER `wh_width`,
  ADD COLUMN `wh_measure_time` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '实测时间戳' AFTER `wh_height`;
