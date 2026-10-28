-- ============================================================
-- 004：SKU 图片改为单图直传
-- 变更要点：
--   1. 去掉 images JSON（1-8 张 URL），改为 image VARCHAR 单图
--   2. 图片走本地上传接口（/customer/upload/image），存 public/storage
--   3. 存量数据取原数组第一张迁移
-- ============================================================

ALTER TABLE `hw_sku`
  ADD COLUMN `image` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '主图 URL（单图，上传接口返回相对路径或外链）' AFTER `hs_code`;

-- 迁移旧 JSON 数据：取数组第一张
UPDATE `hw_sku`
  SET `image` = JSON_UNQUOTE(JSON_EXTRACT(`images`, '$[0]'))
  WHERE `images` IS NOT NULL AND `images` <> '' AND JSON_VALID(`images`);

ALTER TABLE `hw_sku` DROP COLUMN `images`;
