<?php
namespace app;
use app\tatiye;
class NgoreiSdkTester {
    /**
     * Tambahkan property untuk menyimpan error
     */
    private $error = null;

    /**
     * Tambahkan konstanta untuk method yang didukung
     */
    private const SUPPORTED_METHODS = [
        'select', 'where', 'from', 'orWhere', 'whereBetween', 'whereIn', 
        'whereNotIn', 'whereNull', 'whereNotNull', 'orderBy',
        'limit', 'offset', 'join', 'leftJoin', 'rightJoin',
        'groupBy', 'having', 'insert', 'update', 'delete'
    ];

    /**
     * Mengkonversi SQL query ke Query Builder
     */
    public function convertToBuilder(string $sql): string {
        try {
            // Validasi SQL dasar
            if (empty(trim($sql))) {
                throw new \Exception("SQL query tidak boleh kosong");
            }

            // Jalankan validasi SQL
            $this->validateSql($sql);

            // Deteksi tipe query
            if (preg_match('/^\s*(INSERT|UPDATE|DELETE)/i', $sql, $matches)) {
                $parts = $this->parseModifyQuery($sql);
                return $this->generateModifyBuilderCode($parts);
            } else {
                $parts = $this->parseSqlQuery($sql);
                return $this->generateBuilderCode($parts);
            }

        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            return sprintf("// Error: %s\n// Query: %s", 
                $e->getMessage(),
                $sql
            );
        }
    }

    /**
     * Mendapatkan pesan error terakhir
     */
    public function getLastError(): ?string {
        return $this->error;
    }

    /**
     * Parse SQL query menjadi komponen-komponennya
     */
    private function parseSqlQuery(string $sql): array {
        $parts = [
            'table' => '',
            'select' => ['*'],
            'conditions' => [],
            'orConditions' => [],
            'betweenConditions' => [],
            'nullConditions' => [],
            'orderBy' => null,
            'orderDirection' => null,
            'limit' => null,
            'offset' => null,
            'joins' => [],
            'groupBy' => null,
            'aggregates' => [],
            'having' => null,
            'subquery' => null
        ];

        // Parse SELECT columns dengan agregasi - mendukung dengan/tanpa backticks
        if (preg_match('/SELECT\s+(.+?)\s+FROM/i', $sql, $matches)) {
            $selectPart = trim($matches[1]);
            if ($selectPart !== '*') {
                $columns = array_map('trim', explode(',', $selectPart));
                $processedColumns = [];
                
                foreach ($columns as $column) {
                    $column = trim($column);
                    // Hapus backticks jika ada
                    $column = trim($column, '`');
                    
                    if (preg_match('/(COUNT|SUM|AVG|MIN|MAX)\s*\((.*?)\)(?:\s+as\s+(?:`)?(\w+)(?:`)?)?/i', $column, $m)) {
                        $function = strtoupper($m[1]);
                        $argument = trim($m[2], '`');
                        $alias = isset($m[3]) ? $m[3] : null;
                        
                        if ($alias) {
                            $processedColumns[] = "$function($argument) as $alias";
                        } else {
                            $processedColumns[] = "$function($argument)";
                        }
                    } else {
                        $processedColumns[] = $column;
                    }
                }
                $parts['select'] = $processedColumns;
            }
        }

        // Parse table name - mendukung dengan/tanpa backticks
        if (preg_match('/FROM\s+(?:`)?(\w+)(?:`)?\s+(?:AS\s+)?(?:`)?(\w+)(?:`)?/i', $sql, $matches)) {
            $parts['table'] = $matches[1];
            $parts['tableAlias'] = $matches[2];
        } elseif (preg_match('/FROM\s+(?:`)?(\w+)(?:`)?/i', $sql, $matches)) {
            $parts['table'] = $matches[1];
        }

        // Pattern JOIN yang lebih kompleks
        $joinPattern = '/(?:(INNER|LEFT|RIGHT)\s+)?JOIN\s+`?(\w+)`?\s+(?:AS\s+)?`?(\w+)`?\s+ON\s+(.+?)(?=\s+(?:LEFT|RIGHT|INNER)?\s*JOIN|\s+WHERE|\s+GROUP|\s+ORDER|\s+LIMIT|$)/i';
        if (preg_match_all($joinPattern, $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                // Tambahkan validasi kondisi ON yang lebih detail
                $onCondition = trim($match[4]);
                if (preg_match('/^(.+?)(?:\s+AND\s+(.+))?$/i', $onCondition, $onParts)) {
                    $parts['joins'][] = [
                        'type' => $match[1] ?: 'INNER',
                        'table' => $match[2],
                        'alias' => $match[3],
                        'condition' => $onParts[1],
                        'additionalConditions' => isset($onParts[2]) ? $onParts[2] : null
                    ];
                }
            }
        }

        // Tambahkan parsing untuk multiple ORDER BY
        if (preg_match('/ORDER\s+BY\s+(.+?)(?:LIMIT|$)/i', $sql, $matches)) {
            $orderParts = array_map('trim', explode(',', $matches[1]));
            $parts['orderBy'] = [];
            
            foreach ($orderParts as $orderPart) {
                if (preg_match('/`?(\w+)`?\s*(ASC|DESC)?/i', $orderPart, $m)) {
                    $parts['orderBy'][] = [
                        'column' => trim($m[1], '`'),
                        'direction' => isset($m[2]) ? strtoupper($m[2]) : 'ASC'
                    ];
                }
            }
        }

        // Parse WHERE conditions - mendukung dengan/tanpa backticks
        if (preg_match('/WHERE(.*?)(?:ORDER BY|GROUP BY|LIMIT|$)/is', $sql, $matches)) {
            $whereClause = trim($matches[1]);
            $conditions = preg_split('/\s+AND\s+/i', $whereClause);
            
            foreach ($conditions as $condition) {
                $condition = trim($condition);
                // Pattern yang mendukung dengan/tanpa backticks
                $pattern = '/^(?:`)?(\w+)(?:`)?\s*(=|>|<|>=|<=)\s*[\'"]?([^\'"\s]+)[\'"]?$/i';
                
                if (preg_match($pattern, $condition, $m)) {
                    $column = $m[1];  // Tidak perlu trim backticks lagi
                    $operator = $m[2];
                    $value = trim($m[3], "'\"");
                    
                    $parts['conditions'][] = [
                        'condition' => sprintf("%s %s ?", $column, $operator),
                        'value' => $value
                    ];
                }
            }
        }

        // Parse WHERE dengan LIKE dan OR
        if (preg_match('/WHERE\s+(.+?)(?:ORDER BY|GROUP BY|LIMIT|$)/is', $sql, $matches)) {
            $whereClause = trim($matches[1]);
            
            // Split OR conditions
            $orParts = preg_split('/\s+OR\s+/i', $whereClause);
            
            foreach ($orParts as $orPart) {
                // Parse LIKE condition
                if (preg_match('/`?(\w+)`?\s+LIKE\s+\'([^\']+)\'/i', $orPart, $m)) {
                    $column = trim($m[1], '`');
                    $pattern = $m[2];
                    
                    $parts['orConditions'][] = [
                        'condition' => sprintf("`%s` LIKE ?", $column),
                        'value' => $pattern
                    ];
                }
            }
        }

        // Parse LIMIT dan OFFSET
        if (preg_match('/LIMIT\s+(\d+)(?:\s+OFFSET\s+(\d+))?/i', $sql, $matches)) {
            $parts['limit'] = (int)$matches[1];
            if (isset($matches[2])) {
                $parts['offset'] = (int)$matches[2];
            }
        }

        // Parse GROUP BY
        if (preg_match('/GROUP\s+BY\s+(.+?)(?:\s+HAVING|\s+ORDER|\s+LIMIT|$)/i', $sql, $matches)) {
            $parts['groupBy'] = trim(trim($matches[1]), '`');
        }

        // Parse HAVING
        if (preg_match('/HAVING\s+(.+?)(?:\s+ORDER|\s+LIMIT|$)/i', $sql, $matches)) {
            $havingClause = trim($matches[1]);
            
            // Parse kondisi HAVING
            if (preg_match('/(\w+)\s*(>|<|>=|<=|=)\s*\'?(\d+)\'?/i', $havingClause, $m)) {
                $column = $m[1];
                $operator = $m[2];
                $value = $m[3];
                
                $parts['having'] = [
                    'condition' => sprintf("%s %s ?", $column, $operator),
                    'value' => $value
                ];
            }
        }

        // Parse WHERE dengan subquery IN
        if (preg_match('/WHERE\s+`?(\w+)`?\s+IN\s*\(\s*(SELECT\s+.+?)\s*\)/is', $sql, $matches)) {
            $column = trim($matches[1], '`');
            $subquery = trim($matches[2]);
            
            // Parse subquery
            if (preg_match('/SELECT\s+`?(\w+)`?\s+FROM\s+`?(\w+)`?\s+WHERE\s+`?(\w+)`?\s*=\s*\'([^\']+)\'/i', 
                $subquery, $m)) {
                $parts['subquery'] = [
                    'select_column' => trim($m[1], '`'),
                    'from_table' => $m[2],
                    'where_column' => trim($m[3], '`'),
                    'where_value' => $m[4],
                    'main_column' => $column
                ];
            }
        }

        // Parse WHERE dengan BETWEEN
        if (preg_match('/WHERE\s+`?(\w+)`?\s+BETWEEN\s+\'([^\']+)\'\s+AND\s+\'([^\']+)\'/i', $sql, $matches)) {
            $parts['betweenConditions'] = [
                'column' => trim($matches[1], '`'),
                'start' => $matches[2],
                'end' => $matches[3]
            ];
        }

        // Parse WHERE dengan IS NULL
        if (preg_match('/WHERE\s+`?(\w+)`?\s+IS\s+NULL/i', $sql, $matches)) {
            $parts['nullConditions'][] = trim($matches[1], '`');
        }

        return $parts;
    }

    /**
     * Generate kode Query Builder
     */
    private function generateBuilderCode(array $parts): string {
        $code = "\$builder = new NgoreiBuilder('{$parts['table']}');\n";
        $code .= "\$result = \$builder";

        // Add SELECT if not *
        if ($parts['select'] !== ['*']) {
            $columns = array_map(function($col) {
                return "'$col'";
            }, $parts['select']);
            $code .= sprintf("\n    ->select([%s])", implode(', ', $columns));
        }

        // Generate multiple ORDER BY
        if (!empty($parts['orderBy'])) {
            foreach ($parts['orderBy'] as $order) {
                $code .= sprintf("\n    ->orderBy('%s', '%s')", 
                    $order['column'],
                    $order['direction']
                );
            }
        }

        // Update penanganan JOIN dengan kondisi tambahan
        foreach ($parts['joins'] as $join) {
            $method = strtolower($join['type']) . 'Join';
            $joinCode = sprintf("\n    ->%s('%s', '%s')", 
                $method,
                $join['table'],
                $join['condition']
            );
            
            if ($join['additionalConditions']) {
                $joinCode .= sprintf("\n    ->on('%s')", $join['additionalConditions']);
            }
            
            $code .= $joinCode;
        }

        // Add WHERE conditions
        if (!empty($parts['conditions'])) {
            foreach ($parts['conditions'] as $condition) {
                $code .= sprintf("\n    ->where('%s', ['%s'])", 
                    $condition['condition'],
                    $condition['value']
                );
            }
        }

        // Add OR conditions dengan LIKE
        if (!empty($parts['orConditions'])) {
            $conditions = [];
            $values = [];
            
            foreach ($parts['orConditions'] as $condition) {
                $conditions[] = $condition['condition'];
                $values[] = $condition['value'];
            }
            
            $code .= sprintf("\n    ->where('%s', ['%s'])", 
                implode(' OR ', $conditions),
                implode("', '", $values)
            );
        }

        // Add LIMIT
        if ($parts['limit'] !== null) {
            $code .= sprintf("\n    ->limit(%d)", $parts['limit']);
        }

        // Add OFFSET
        if ($parts['offset'] !== null) {
            $code .= sprintf("\n    ->offset(%d)", $parts['offset']);
        }

        // Add GROUP BY
        if ($parts['groupBy'] !== null) {
            $code .= sprintf("\n    ->groupBy('%s')", $parts['groupBy']);
        }

        // Add HAVING
        if ($parts['having'] !== null) {
            $code .= sprintf("\n    ->having('%s', ['%s'])", 
                $parts['having']['condition'],
                $parts['having']['value']
            );
        }

        // Add Subquery
        if ($parts['subquery'] !== null) {
            $subquery = $parts['subquery'];
            $code .= sprintf("\n    ->whereIn('%s', function(\$query) {", 
                $subquery['main_column']
            );
            $code .= sprintf("\n        \$query->select(['%s'])", 
                $subquery['select_column']
            );
            $code .= sprintf("\n            ->from('%s')", 
                $subquery['from_table']
            );
            $code .= sprintf("\n            ->where('%s = ?', ['%s']);", 
                $subquery['where_column'],
                $subquery['where_value']
            );
            $code .= "\n    })";
        }

        // Add BETWEEN condition
        if (!empty($parts['betweenConditions'])) {
            $between = $parts['betweenConditions'];
            $code .= sprintf("\n    ->where('%s BETWEEN ? AND ?', ['%s', '%s'])",
                $between['column'],
                $between['start'],
                $between['end']
            );
        }

        // Add IS NULL conditions
        if (!empty($parts['nullConditions'])) {
            foreach ($parts['nullConditions'] as $column) {
                $code .= sprintf("\n    ->where('%s IS NULL')", $column);
            }
        }

        $code .= "\n    ->execute();";
        return $code;
    }

    /**
     * Menampilkan hasil konversi dengan penanganan error
     */
    public function showConversion(string $sql): string {
        $builderCode = $this->convertToBuilder($sql);
        
        if ($this->error) {
            return sprintf(
                "<div class='error-box'>
                    <strong>Error:</strong><br>
                    %s
                    <br><br>
                    <strong>SQL yang diberikan:</strong><br>
                    <code>%s</code>
                    <br><br>
                    <strong>Method yang didukung:</strong><br>
                    <code>%s</code>
                </div>
                <style>
                    .error-box {
                        background: #fee;
                        border-left: 4px solid #e74c3c;
                        padding: 15px;
                        margin: 10px 0;
                        border-radius: 4px;
                    }
                    .error-box strong {
                        color: #c0392b;
                    }
                </style>",
                htmlspecialchars($this->error),
                htmlspecialchars($sql),
                implode(', ', self::SUPPORTED_METHODS)
            );
        }

        return "<pre><code>$builderCode</code></pre>";
    }

    // Tambahkan method baru untuk parse query INSERT, UPDATE, DELETE
    private function parseModifyQuery(string $sql): array {
        $parts = [
            'type' => '',
            'table' => '',
            'columns' => [],
            'values' => [],
            'conditions' => [],
        ];

        // Parse INSERT dengan validasi tambahan
        if (preg_match('/INSERT\s+INTO\s+`?(\w+)`?\s*\(([^)]+)\)\s*VALUES\s*\(([^)]+)\)/i', $sql, $matches)) {
            $parts['type'] = 'INSERT';
            $parts['table'] = trim($matches[1], '`');
            
            // Validasi kolom dan nilai
            $columns = array_map('trim', explode(',', $matches[2]));
            $values = array_map('trim', explode(',', $matches[3]));
            
            if (count($columns) !== count($values)) {
                throw new \RuntimeException('Jumlah kolom dan nilai tidak sama');
            }
            
            $parts['columns'] = array_map(function($col) {
                return trim($col, '` ');
            }, $columns);
            
            $parts['values'] = array_map(function($val) {
                return trim($val, '\'" ');
            }, $values);
        }
        
        // Parse UPDATE dengan validasi tambahan
        elseif (preg_match('/UPDATE\s+`?(\w+)`?\s+SET\s+(.+?)(?:\s+WHERE\s+(.+))?$/i', $sql, $matches)) {
            $parts['type'] = 'UPDATE';
            $parts['table'] = trim($matches[1], '`');
            
            // Validasi SET assignments
            $sets = array_map('trim', explode(',', $matches[2]));
            foreach ($sets as $set) {
                if (preg_match('/`?(\w+)`?\s*=\s*[\'"]?([^\'"\s]+)[\'"]?/i', $set, $m)) {
                    $parts['columns'][] = trim($m[1], '`');
                    $parts['values'][] = $m[2];
                }
            }
            
            // Parse WHERE dengan validasi
            if (isset($matches[3])) {
                $this->parseWhereConditions($matches[3], $parts);
            }
        }
        
        // Parse DELETE dengan validasi tambahan
        elseif (preg_match('/DELETE\s+FROM\s+`?(\w+)`?(?:\s+WHERE\s+(.+))?$/i', $sql, $matches)) {
            $parts['type'] = 'DELETE';
            $parts['table'] = trim($matches[1], '`');
            
            if (isset($matches[2])) {
                $this->parseWhereConditions($matches[2], $parts);
            }
        } else {
            throw new \RuntimeException('Query tidak valid atau tidak didukung');
        }

        return $parts;
    }

    // Helper method untuk parse WHERE conditions
    private function parseWhereConditions(string $where, array &$parts): void {
        $conditions = preg_split('/\s+AND\s+/i', $where);
        foreach ($conditions as $condition) {
            if (preg_match('/`?(\w+)`?\s*(=|>|<|>=|<=)\s*[\'"]?([^\'"\s]+)[\'"]?/i', $condition, $m)) {
                $parts['conditions'][] = [
                    'column' => trim($m[1], '`'),
                    'operator' => $m[2],
                    'value' => trim($m[3], '\'"')
                ];
            }
        }
    }

    // Method baru untuk generate kode builder untuk query modifikasi
    private function generateModifyBuilderCode(array $parts): string {
        $code = "\$builder = new NgoreiBuilder('{$parts['table']}');\n";
        
        switch ($parts['type']) {
            case 'INSERT':
                // Ubah format array untuk insert sesuai NgoreiBuilder
                $insertData = [];
                foreach (array_combine($parts['columns'], $parts['values']) as $col => $val) {
                    $insertData[$col] = $val;
                }
                
                $code .= "\$result = \$builder->insert(" . var_export($insertData, true) . ")";
                break;

            case 'UPDATE':
                // Ubah format array untuk update sesuai NgoreiBuilder
                $updateData = [];
                foreach (array_combine($parts['columns'], $parts['values']) as $col => $val) {
                    $updateData[$col] = $val;
                }
                
                $code .= "\$result = \$builder";
                
                // Tambahkan WHERE conditions sebelum UPDATE
                foreach ($parts['conditions'] as $condition) {
                    $code .= sprintf("\n    ->where('%s %s ?', ['%s'])",
                        $condition['column'],
                        $condition['operator'],
                        $condition['value']
                    );
                }
                
                $code .= "\n    ->update(" . var_export($updateData, true) . ")";
                break;

            case 'DELETE':
                $code .= "\$result = \$builder";
                
                // Tambahkan WHERE conditions sebelum DELETE
                foreach ($parts['conditions'] as $condition) {
                    $code .= sprintf("\n    ->where('%s %s ?', ['%s'])",
                        $condition['column'],
                        $condition['operator'],
                        $condition['value']
                    );
                }
                
                $code .= "\n    ->delete()";
                break;
        }

        return $code . ";";
    }

    // Tambahkan method validasi SQL
    private function validateSql(string $sql): void {
        // Validasi sintaks dasar
        if (!preg_match('/^(SELECT|INSERT|UPDATE|DELETE)/i', $sql)) {
            throw new \Exception("Query harus dimulai dengan SELECT, INSERT, UPDATE, atau DELETE");
        }

        // Validasi tabel
        if (!preg_match('/\s+FROM\s+`?\w+`?|\s+INTO\s+`?\w+`?|\s+UPDATE\s+`?\w+`?/i', $sql)) {
            throw new \Exception("Query harus memiliki nama tabel yang valid");
        }

        // Validasi kolom
        if (preg_match('/SELECT\s+(.+?)\s+FROM/i', $sql, $matches)) {
            $columns = explode(',', $matches[1]);
            foreach ($columns as $column) {
                if (!preg_match('/^[\s\w\*`\(\)\.]+$/', trim($column))) {
                    throw new \Exception("Format kolom tidak valid: " . trim($column));
                }
            }
        }

        // Validasi WHERE clause
        if (preg_match('/\s+WHERE\s+(.+?)(?:ORDER BY|GROUP BY|LIMIT|$)/is', $sql, $matches)) {
            $whereClause = $matches[1];
            if (!preg_match('/^[\s\w`=><\'"\(\)\s]+$/', $whereClause)) {
                throw new \Exception("Format WHERE clause tidak valid");
            }
        }
    }
}

