<?php
/**
 * Author: Drunk (drunkce.com; idrunk.net)
 * Date: 2019/10/6 20:23
 */

namespace dce\db\proxy;

use Closure;
use dce\db\Query;
use dce\db\query\builder\StatementInterface;
use Iterator;

abstract class DbProxy {
    public static int $max_statement_log = 32;

    private static int $logCounter = 0;

    private static array $statementLogs = [];

    /**
     * @param StatementInterface|string $statement
     * @param array|null $params
     */
    protected function logStatement(StatementInterface|string $statement, array|null $params = null): void {
        if (null !== $statement) {
            self::$logCounter ++;
            self::$statementLogs[] = [$statement, $params];
            if (self::$logCounter > self::$max_statement_log * 2) {
                self::$logCounter = self::$max_statement_log;
                self::$statementLogs = array_slice(self::$statementLogs, - self::$max_statement_log);
            }
        }
    }

    public static function getStatements(): array {
        return self::$statementLogs;
    }

    public static function getLastStatement(): array {
        $lastKey = array_key_last(self::$statementLogs);
        return [self::$statementLogs[$lastKey][0] ?? null, self::$statementLogs[$lastKey][1]];
    }

    /**
     * 筛选取出全部数据
     * @param StatementInterface $statement
     * @param string|null $indexColumn
     * @param string|null $extractColumn
     * @return array
     */
    abstract public function queryAll(StatementInterface $statement, string|null $indexColumn = null, string|null $extractColumn = null): array;

    /**
     * 迭代式取筛选数据
     * @param StatementInterface $statement
     * @param Closure|null $decorator
     * @return Iterator
     */
    abstract public function queryEach(StatementInterface $statement, Closure|null $decorator = null): Iterator;

    /**
     * 取第一条筛选结果
     * @param StatementInterface $statement
     * @return array|false
     */
    abstract public function queryOne(StatementInterface $statement): array|false;

    /**
     * 取第一条筛选结果的第一个字段标量值
     * @param StatementInterface $statement
     * @return string|int|float|false|null
     */
    abstract public function queryColumn(StatementInterface $statement): string|int|float|null|false;

    /**
     * 取执行SQL所改变的记录数
     * @param StatementInterface $statement
     * @return int
     */
    abstract public function queryGetAffectedCount(StatementInterface $statement): int;

    /**
     * 取插入语句的最后插入的记录ID
     * @param StatementInterface $statement
     * @return int|string
     */
    abstract public function queryGetInsertId(StatementInterface $statement): int|string;

    /**
     * 字符串式PDO查询取查询结果
     * @param string $statement
     * @param array $params
     * @param array $fetchArgs
     * @return array
     */
    abstract public function query(string $statement, array $params, array $fetchArgs): array;

    /**
     * 字符串式PDO查询取插入的ID或改变的记录数
     * @param string $statement
     * @param array $params
     * @return int|string
     */
    abstract public function execute(string $statement, array $params): int|string;

    /**
     * 开启一个事务
     * @param Query $query
     * @return Transaction
     */
    abstract public function begin(Query $query): Transaction;
}
