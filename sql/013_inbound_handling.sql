-- ============================================================
-- 013：到仓形态收敛（集装箱 / 托盘 / 散箱）
-- 1) 停用「运输方式」transport_type：货主真正能准确回答的是「货到仓时是什么形态」，
--    而运输方式（海运/空运/快递）与到仓形态并不一一对应，两个字段并存会互相打架。
-- 2) 集装箱只保留 20 尺 / 40 尺两种规格（真实客户能确认的粒度）。
-- 3) 托盘到仓需要申报托盘数量（计费与月台排班的直接依据）。
-- 4) 预计到仓时间改为必填（仓库排班的唯一输入）。
-- ============================================================

ALTER TABLE `hw_inbound_order`
  ADD COLUMN `pallet_qty` INT NOT NULL DEFAULT 0 COMMENT '托盘数量（到仓形态=托盘时必填）' AFTER `container_type`;

ALTER TABLE `hw_inbound_order`
  MODIFY COLUMN `transport_type` TINYINT NOT NULL DEFAULT 0 COMMENT '已停用（由 handling_type 取代），历史值仅用于展示',
  MODIFY COLUMN `handling_type`  TINYINT NOT NULL DEFAULT 3 COMMENT '到仓形态：1 集装箱 2 托盘 3 散箱',
  MODIFY COLUMN `container_type` TINYINT NOT NULL DEFAULT 0 COMMENT '集装箱规格：1 20尺(20GP) 2 40尺(40HQ)；3/4/5 为历史值',
  MODIFY COLUMN `expect_time`    DATE NULL COMMENT '预计到仓时间（必填）';
