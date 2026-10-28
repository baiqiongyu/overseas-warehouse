-- ============================================================
-- 014：箱唛码（箱标 / 扫码入仓的唯一识别码）
-- 规则：{订单号}_{箱号}_{序号}
--   IN20261022-0001_A_01 … A_10
-- 1) 箱号（box_no）语义变更：不再「一箱一号」，而是「一类箱的编号」，
--    同一箱号下可以有 N 个实体箱，用 box_seq 区分（A 箱 10 个 → seq 1..10）。
-- 2) 分隔符统一用下划线：
--    订单号内部含「-」（IN20261022-0001），若用「-」拼箱号，扫码反向解析要靠猜；
--    改用「_」后 split('_') 恒为 3 段，从左读即可，且订单号 / 箱号字符集内均不含「_」。
-- 3) 箱号字符集收敛为 [A-Z0-9]，不含分隔符，保证箱唛 100% 可逆解析。
-- 4) 历史数据：一箱一号，回填 box_seq=1、box_code=订单号_箱号_01。
-- ============================================================

ALTER TABLE `hw_inbound_box`
  ADD COLUMN `box_seq`  SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '箱号内序号（第几个实体箱，从 1 开始）' AFTER `box_no`,
  ADD COLUMN `box_code` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '箱唛码 = 订单号_箱号_序号（全局唯一，扫码入口）' AFTER `box_seq`;

-- 历史回填：箱号为空的老数据用 BX+id 兜底，避免 box_code 撞空串
UPDATE `hw_inbound_box` b
  JOIN `hw_inbound_order` o ON o.id = b.inbound_id
   SET b.box_seq  = 1,
       b.box_code = CONCAT(o.order_no, '_', IF(b.box_no = '', CONCAT('BX', LPAD(b.id, 4, '0')), b.box_no), '_01')
 WHERE b.box_code = '';

-- 孤儿箱（inbound 已物理删除）兜底
UPDATE `hw_inbound_box` SET box_seq = 1 WHERE box_seq = 0;
UPDATE `hw_inbound_box` SET box_code = CONCAT('BX', LPAD(id, 6, '0'), '_01') WHERE box_code = '';

-- 箱唛码全局唯一：扫码 → 唯一一箱
ALTER TABLE `hw_inbound_box`
  ADD UNIQUE KEY `uk_box_code` (`box_code`),
  ADD UNIQUE KEY `uk_inb_box_seq` (`inbound_id`, `box_no`, `box_seq`);
