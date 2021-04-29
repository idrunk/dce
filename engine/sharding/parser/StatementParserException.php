<?php
/**
 * Author: Drunk
 * Date: 2019/10/31 17:14
 */

namespace dce\sharding\parser;

use dce\base\Exception;
use dce\i18n\Language;

// 1500-1599
class StatementParserException extends Exception {
    // 运行时异常
    #[Language(['操作符 %s 无效'])]
    public const INVALID_OPERATOR = 1500;

    #[Language(['语句 %s 出现在无效位置'])]
    public const INVALID_STATEMENT_PLACE = 1501;

    #[Language(['未定义别名'])]
    public const UNDEFINED_ALIAS = 1502;

    #[Language(['语句异常, 无法解析'])]
    public const INVALID_STATEMENT = 1503;

    #[Language(['字符串 %s 未闭合'])]
    public const STRING_UNCLOSE = 1504;

    #[Language(['非法字段名 %s'])]
    public const INVALID_COLUMN = 1505;

    #[Language(['方法 %s 调用未正常闭合'])]
    public const FUNCTION_UNCLOSE = 1506;

    #[Language(['负号后跟的不是有效数字'])]
    public const INVALID_NUMBER_AFTER_MINUS = 1507;

    #[Language(['%s 附近出现错误, CASE语句未正常关闭'])]
    public const INVALID_STATEMENT_CASE_UNCLOSE = 1508;

    #[Language(['符号 %s 异常, CASE未正常关闭'])]
    public const INVALID_OPERATOR_CASE_UNCLOSE = 1509;

    #[Language(['缺少THEN'])]
    public const THEN_MISSING = 1510;
}
