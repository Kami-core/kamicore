<?php

/**
 * KamiCore
 *
 * SPDX-License-Identifier: Apache-2.0
 *
 * @see https://kamicore.org
 */

/**
 * Lightweight PostgreSQL wrapper built on the pg_* extension.
 */

if(!defined('IN_KAMI')) die();

class DB
{
	public static string $server = 'localhost';
	public static int $port = 5432;
	public static string $user = 'root';
	public static string $password = '';
	public static string $database = '';
	public static string $charset = 'utf8';

        /** @var resource|null */
        public static $conn = null;

        private static int $transactionDepth = 0;

        /** @var string */
        public static string $errdesc = '';
        /** @var int */
        public static int $errno = 0;

        /** @var string */
        public static string $last_query = '';
			public static array $lang_config = [];

        public static function connected(): bool
        {
                return (self::$conn !== null && self::$conn !== false);
        }

        public static function connect(
			string $server = 'localhost',
			string $user = 'root',
			string $password = '',
			string $database = '',
			string $charset = 'utf8',
			int $port = 5432,
			bool $skipError = false
		): bool {
			if (!function_exists('pg_connect')) {
				die('<strong>The PHP PgSQL extension libraries are not installed on this server!</strong>');
			}

			if (!self::$conn) {
				self::$server = $server;
				self::$port = $port;
				self::$user = $user;
				self::$password = $password;
				self::$database = $database;
				self::$charset = $charset;

				$connStr = sprintf(
					"host=%s port=%d dbname=%s user=%s password=%s",
					self::$server,
					self::$port,
					self::$database,
					self::$user,
					self::$password
				);

				self::$conn = pg_connect($connStr);
				if (!self::$conn) {
					if (!$skipError) {
						self::error('Unable to establish a connection to the PostgreSQL server!');
					}
				} else {
					if ($database !== '') {
						@pg_query(self::$conn, "SET NAMES '{$charset}'");
					}
				}
			}

				return self::$conn !== null && self::$conn !== false;
			}

		public static function beginTransaction(): bool
		{
			$sql = self::$transactionDepth === 0
				? 'BEGIN'
				: 'SAVEPOINT kami_tx_' . self::$transactionDepth;

			if (self::query($sql) === false) {
				return false;
			}

			self::$transactionDepth++;
			return true;
		}

		public static function commit(): bool
		{
			if (self::$transactionDepth < 1) {
				return false;
			}

			if (self::$transactionDepth === 1) {
				if (self::query('COMMIT') === false) {
					return false;
				}
				self::$transactionDepth = 0;
				return true;
			}

			$savepoint = self::$transactionDepth - 1;
			if (self::query('RELEASE SAVEPOINT kami_tx_' . $savepoint) === false) {
				return false;
			}

			self::$transactionDepth--;
			return true;
		}

		public static function rollBack(): bool
		{
			if (self::$transactionDepth < 1) {
				return false;
			}

			if (self::$transactionDepth === 1) {
				$result = self::query('ROLLBACK') !== false;
				self::$transactionDepth = 0;
				return $result;
			}

			$savepoint = self::$transactionDepth - 1;
			if (self::query('ROLLBACK TO SAVEPOINT kami_tx_' . $savepoint) === false) {
				return false;
			}
			if (self::query('RELEASE SAVEPOINT kami_tx_' . $savepoint) === false) {
				return false;
			}

			self::$transactionDepth--;
			return true;
		}

	        public static function error(string $msg): void
        {
                self::$errdesc = $msg;
                self::$errno = 1;
                trigger_error($msg, E_USER_WARNING);
        }

        /**
         * Executes a query and returns a value based on the PostgreSQL result status:
         * - row-producing queries return a PgSql\Result instance;
         * - data-changing queries with RETURNING return a scalar, row, or row list;
         * - commands without rows return the number of affected rows.
         */
        public static function query(string $sql, array $params = []): mixed
        {
                self::$last_query = $sql;

                $params = array_map(
                        static fn(mixed $value): mixed => is_bool($value)
                                ? ($value ? 't' : 'f')
                                : $value,
                        array_values($params)
                );

                $result = empty($params)
                        ? @pg_query(self::$conn, $sql)
                        : @pg_query_params(self::$conn, $sql, $params);

                if ($result === false) {
                        self::error(pg_last_error(self::$conn));
                        return false;
                }

                $status = pg_result_status($result);

                if ($status === PGSQL_TUPLES_OK) {
                        $commandStatus = strtoupper(
                                (string) pg_result_status($result, PGSQL_STATUS_STRING)
                        );

                        if (!preg_match('/^(INSERT|UPDATE|DELETE|MERGE)\b/', $commandStatus)) {
                                return $result;
                        }

                        $rows = self::fetchAll($result);
                        if (count($rows) === 1 && count($rows[0]) === 1) {
                                return reset($rows[0]);
                        } elseif (count($rows) === 1) {
                                return $rows[0];
                        }
                        return $rows;
                }

                if ($status === PGSQL_COMMAND_OK) {
                        return pg_affected_rows($result);
                }

                return $result;
        }

        public static function getRowCount(mixed $result): int|false
        {
                if (!$result instanceof \PgSql\Result) {
                        return false;
                }

                return pg_num_rows($result);
        }

        public static function fetchRow($result): ?array
        {
                if (!$result instanceof \PgSql\Result) {
                        return null;
                }

                $row = pg_fetch_assoc($result);

                if (!is_array($row)) {
                        return null;
                }

                return self::normalizeBooleanFields(
                        $row,
                        self::getBooleanFieldNames($result)
                );
        }

        public static function fetchAll($result): array
        {
                if (!$result instanceof \PgSql\Result) {
                        return [];
                }

                $rows = [];
                $booleanFields = self::getBooleanFieldNames($result);

                while ($row = pg_fetch_assoc($result)) {
                        if (is_array($row)) {
                                $rows[] = self::normalizeBooleanFields($row, $booleanFields);
                        }
                }
                return $rows;
        }

        public static function escape(string $str): string
        {
                return pg_escape_string(self::$conn, $str);
        }

        private static function getBooleanFieldNames(\PgSql\Result $result): array
        {
                $fields = [];
                $fieldCount = pg_num_fields($result);

                for ($i = 0; $i < $fieldCount; $i++) {
                        if (pg_field_type($result, $i) === 'bool') {
                                $fields[] = pg_field_name($result, $i);
                        }
                }

                return $fields;
        }

        private static function normalizeBooleanFields(
                array $row,
                array $booleanFields
        ): array {
                foreach ($booleanFields as $field) {
                        if (!array_key_exists($field, $row)) {
                                continue;
                        }

                        $row[$field] = self::normalizeBooleanValue($row[$field]);
                }

                return $row;
        }

        private static function normalizeBooleanValue(mixed $value): mixed
        {
                if (is_bool($value) || $value === 't' || $value === 'f') {
                        return self::readBool($value);
                }

                return $value;
        }

        /**
         * Converts a PostgreSQL array literal to a PHP array.
         *
         * Values remain strings, unquoted NULL becomes null, and nested arrays
         * become nested PHP arrays. A custom delimiter can be supplied for
         * PostgreSQL types whose typdelim is not a comma.
         */
        public static function readBool(mixed $value): bool
        {
                if (is_bool($value)) {
                        return $value;
                }

                return in_array(
                        strtolower(trim((string) $value)),
                        ['1', 't', 'true'],
                        true
                );
        }

        public static function prepareIdArray(array $values): string
        {
                $values = array_values(array_unique(array_filter(
                        array_map('intval', $values),
                        static fn(int $value): bool => $value > 0
                )));

                return '{' . implode(',', $values) . '}';
        }

        public static function prepareTextArray(array $values): string
        {
                return '{' . implode(',', array_map(
                        static fn($value): string => '"' . str_replace(
                                ['\\', '"'],
                                ['\\\\', '\\"'],
                                (string) $value
                        ) . '"',
                        $values
                )) . '}';
        }

        public static function prepareBoolArray(array $values): string
        {
                return '{' . implode(',', array_map(
                        static fn($value): string => self::readBool($value) ? 'true' : 'false',
                        $values
                )) . '}';
        }

        public static function convertArr(string $pgArray, string $delimiter = ','): array
        {
                if (
                        strlen($delimiter) !== 1
                        || str_contains('{}"\\', $delimiter)
                        || ctype_space($delimiter)
                ) {
                        throw new \InvalidArgumentException(
                                'The PostgreSQL array delimiter must be one non-whitespace character.'
                        );
                }

                $pgArray = trim($pgArray);

                // PostgreSQL may prefix an array literal with explicit dimensions,
                // for example: [0:2]={one,two,three}.
                if (preg_match('/^(?:\[[+-]?\d+:[+-]?\d+\])+=/', $pgArray, $matches)) {
                        $pgArray = substr($pgArray, strlen($matches[0]));
                }

                $offset = 0;
                $result = self::parsePgArray($pgArray, $offset, $delimiter);
                self::skipPgArrayWhitespace($pgArray, $offset);

                if ($offset !== strlen($pgArray)) {
                        throw new \UnexpectedValueException(
                                "Invalid PostgreSQL array literal at offset {$offset}."
                        );
                }

                return $result;
        }

        private static function parsePgArray(
                string $pgArray,
                int &$offset,
                string $delimiter
        ): array {
                self::skipPgArrayWhitespace($pgArray, $offset);

                if (($pgArray[$offset] ?? null) !== '{') {
                        throw new \UnexpectedValueException(
                                "Expected '{' in PostgreSQL array literal at offset {$offset}."
                        );
                }

                $offset++;
                $result = [];
                self::skipPgArrayWhitespace($pgArray, $offset);

                if (($pgArray[$offset] ?? null) === '}') {
                        $offset++;
                        return $result;
                }

                while (true) {
                        self::skipPgArrayWhitespace($pgArray, $offset);
                        $char = $pgArray[$offset] ?? null;

                        if ($char === null || $char === '}' || $char === $delimiter) {
                                throw new \UnexpectedValueException(
                                        "Missing PostgreSQL array value at offset {$offset}."
                                );
                        }

                        if ($char === '{') {
                                $result[] = self::parsePgArray($pgArray, $offset, $delimiter);
                        } elseif ($char === '"') {
                                $result[] = self::parseQuotedPgArrayValue($pgArray, $offset);
                        } else {
                                $result[] = self::parseUnquotedPgArrayValue(
                                        $pgArray,
                                        $offset,
                                        $delimiter
                                );
                        }

                        self::skipPgArrayWhitespace($pgArray, $offset);
                        $char = $pgArray[$offset] ?? null;

                        if ($char === $delimiter) {
                                $offset++;
                                continue;
                        }

                        if ($char === '}') {
                                $offset++;
                                return $result;
                        }

                        throw new \UnexpectedValueException(
                                "Invalid PostgreSQL array literal at offset {$offset}."
                        );
                }
        }

        private static function parseQuotedPgArrayValue(
                string $pgArray,
                int &$offset
        ): string {
                $offset++;
                $value = '';
                $length = strlen($pgArray);

                while ($offset < $length) {
                        $char = $pgArray[$offset++];

                        if ($char === '\\') {
                                if ($offset >= $length) {
                                        break;
                                }

                                $value .= $pgArray[$offset++];
                                continue;
                        }

                        if ($char === '"') {
                                return $value;
                        }

                        $value .= $char;
                }

                throw new \UnexpectedValueException(
                        'Unterminated quoted value in PostgreSQL array literal.'
                );
        }

        private static function parseUnquotedPgArrayValue(
                string $pgArray,
                int &$offset,
                string $delimiter
        ): ?string {
                $value = '';
                $pendingWhitespace = '';
                $length = strlen($pgArray);

                while ($offset < $length) {
                        $char = $pgArray[$offset];

                        if ($char === $delimiter || $char === '}') {
                                break;
                        }

                        if ($char === '{' || $char === '"') {
                                throw new \UnexpectedValueException(
                                        "Invalid PostgreSQL array value at offset {$offset}."
                                );
                        }

                        if (ctype_space($char)) {
                                $pendingWhitespace .= $char;
                                $offset++;
                                continue;
                        }

                        if ($char === '\\') {
                                $value .= $pendingWhitespace;
                                $pendingWhitespace = '';
                                $offset++;

                                if ($offset >= $length) {
                                        throw new \UnexpectedValueException(
                                                'Unterminated escape sequence in PostgreSQL array literal.'
                                        );
                                }

                                $value .= $pgArray[$offset++];
                                continue;
                        }

                        $value .= $pendingWhitespace . $char;
                        $pendingWhitespace = '';
                        $offset++;
                }

                if ($value === '') {
                        throw new \UnexpectedValueException(
                                "Empty unquoted PostgreSQL array value at offset {$offset}."
                        );
                }

                return strcasecmp($value, 'NULL') === 0 ? null : $value;
        }

        private static function skipPgArrayWhitespace(
                string $pgArray,
                int &$offset
        ): void {
                $length = strlen($pgArray);

                while ($offset < $length && ctype_space($pgArray[$offset])) {
                        $offset++;
                }
        }

        /**
         * Return the first result row.
         */
        public static function getRow(string $sql, array $params = [])
        {

				$result = self::query($sql, $params);

                return ($result) ? self::fetchRow($result) : false;
        }

        public static function getArr(string $sql, array $params = []): array
        {
                $arr = [];
                $result = self::query($sql, $params);

                if (!$result instanceof \PgSql\Result) {
                        return $arr;
                }

                $isBoolean = pg_num_fields($result) > 0
                        && pg_field_type($result, 0) === 'bool';

                while ($row = pg_fetch_row($result)) {
                        $value = $row[0];

                        $arr[] = $isBoolean
                                ? self::normalizeBooleanValue($value)
                                : $value;
                }
                return $arr;
        }

        /**
         * Return the first column from the first result row.
         */
        public static function getOne(string $sql, array $params = []): mixed
        {
                $row = self::getRow($sql, $params);
                return $row ? reset($row) : null;
        }

			public static function insert(string $table, array $data, ?string $returning = null): mixed {
			$fields = array_keys($data);
			$placeholders = [];
			$values = [];

			$i = 1;
				foreach ($fields as $field) {
					$placeholders[] = '$' . $i++;
					$values[] = $data[$field];
				}

			$fieldList = implode(', ', $fields);
			$placeholderList = implode(', ', $placeholders);

			$sql = "INSERT INTO {$table} ({$fieldList}) VALUES ({$placeholderList})";

			if ($returning) {
				$sql .= " RETURNING {$returning}";
			}

			return self::query($sql, $values);
		}

        public static function bulk_insert(string $table, array $rows, ?string $returning = null): mixed {
			if (empty($rows)) {
				return false;
			}

			// Use the field order from the first row.
			$fields = array_keys(reset($rows));
			$fieldList = implode(', ', $fields);

			$placeholders = [];
			$values = [];
			$paramIndex = 1;

			foreach ($rows as $row) {
				$rowPlaceholders = [];

					foreach ($fields as $field) {
						$rowPlaceholders[] = '$' . $paramIndex++;
						$values[] = $row[$field];
					}

				$placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
			}

			$sql = "INSERT INTO {$table} ({$fieldList}) VALUES " . implode(', ', $placeholders);

			if ($returning) {
				$sql .= " RETURNING {$returning}";
			}

			return self::query($sql, $values);
		}

        public static function update($table, $data, $where, $params = [])
        {
                $fields = array_keys($data);
                $set = [];
                $values = array_values($params);
                $offset = count($values);

                foreach ($fields as $i => $field) {
                        $set[] = "{$field} = $" . ($offset + $i + 1);
                        $values[] = $data[$field];
                }

                $sql = "UPDATE {$table} SET " . implode(', ', $set) . " WHERE {$where}";

                $result = self::query($sql, $values);
                return $result;
        }

        public static function delete($table, $where, $params = [])
        {
                $sql = "DELETE FROM {$table} WHERE {$where}";
                $result = self::query($sql, $params);
                return $result;
        }

}
