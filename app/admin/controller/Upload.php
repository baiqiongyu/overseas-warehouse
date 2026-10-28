<?php
declare(strict_types=1);

namespace app\admin\controller;

use app\common\exception\BizException;
use think\facade\Filesystem;

/**
 * 文件上传（内部后台，需登录）
 * 与货主门户 Upload 逻辑一致（public/storage 磁盘，返回相对 URL）
 */
class Upload extends Base
{
    private const EXT      = ['jpg', 'jpeg', 'png', 'webp'];
    private const MAX_SIZE = 5242880;

    /**
     * 上传图片
     * POST /admin/upload/image  (multipart: file)
     */
    public function image()
    {
        $file = $this->request->file('file');
        if (!$file || !$file->isValid()) {
            throw new BizException('请选择要上传的图片');
        }
        if ($file->getSize() > self::MAX_SIZE) {
            throw new BizException('图片不能超过 5MB');
        }
        $ext = strtolower($file->getOriginalExtension());
        if (!in_array($ext, self::EXT, true)) {
            throw new BizException('仅支持 jpg / png / webp 格式');
        }
        // 双重保险：真实内容必须是图片（防改后缀伪装）
        if (getimagesize($file->getRealPath()) === false) {
            throw new BizException('文件内容不是有效图片');
        }

        $path = Filesystem::disk('public')->putFile('sku', $file);
        if (!$path) {
            throw new BizException('图片保存失败，请重试');
        }

        return $this->ok([
            'url'  => '/storage/' . str_replace('\\', '/', $path),
            'size' => $file->getSize(),
        ], '上传成功');
    }
}
