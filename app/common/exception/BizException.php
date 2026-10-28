<?php
declare(strict_types=1);

namespace app\common\exception;

/**
 * 业务异常：由 ExceptionHandle 统一渲染为 {code:1, msg} JSON
 */
class BizException extends \RuntimeException
{
}
