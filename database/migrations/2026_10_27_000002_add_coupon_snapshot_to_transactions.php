<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    private const FOREIGN_KEY_NAME = 'transactions_coupon_id_foreign';

    public function up(): void
    {
        if (! Schema::hasTable('transactions') || ! Schema::hasTable('coupons')) {
            throw new RuntimeException('transactions and coupons tables must both exist before adding coupon snapshots.');
        }

        $driver = DB::connection()->getDriverName();
        $this->assertInnoDbParentsWhenMySql($driver);
        $couponIdWasMissing = ! Schema::hasColumn('transactions', 'coupon_id');

        // Validate every pre-existing piece before issuing more DDL. A malformed
        // partial state must stop without adding unrelated missing pieces.
        if (! $couponIdWasMissing) {
            $this->assertCouponSnapshotColumn('coupon_id');
        }
        if (Schema::hasColumn('transactions', 'coupon_code')) {
            $this->assertCouponSnapshotColumn('coupon_code');
        }
        $this->assertExistingForeignKeyCompatibility($driver, $couponIdWasMissing);

        if ($couponIdWasMissing) {
            if ($driver === 'sqlite') {
                // SQLite cannot ALTER TABLE to add a foreign-key constraint
                // separately. Add it with the column for isolated test schemas.
                DB::statement('ALTER TABLE "transactions" ADD COLUMN "coupon_id" INTEGER NULL REFERENCES "coupons" ("id") ON DELETE SET NULL');
            } elseif ($driver === 'mysql') {
                Schema::table('transactions', function (Blueprint $table) {
                    $table->foreignId('coupon_id')->nullable()->after('discount_total');
                });
            } elseif ($driver === 'pgsql') {
                Schema::table('transactions', function (Blueprint $table) {
                    $table->bigInteger('coupon_id')->nullable();
                });
            } else {
                throw new RuntimeException('Coupon snapshot migration supports MySQL, SQLite, and PostgreSQL; received '.$driver.'.');
            }
        }

        if (! Schema::hasColumn('transactions', 'coupon_code')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('coupon_code', 40)->nullable()->after('coupon_id');
            });
        }

        $this->assertCouponSnapshotColumn('coupon_id');
        $this->assertCouponSnapshotColumn('coupon_code');
        $this->ensureCorrectCouponForeignKey($driver, $couponIdWasMissing);
    }

    public function down(): void
    {
        if (! Schema::hasTable('transactions')) {
            return;
        }

        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            $foreignKeys = $this->mysqlCouponForeignKeys();
            if (count($foreignKeys) > 1 || ($foreignKeys && ! $this->isCorrectMySqlForeignKey($foreignKeys[0]))) {
                throw new RuntimeException('Refusing to drop an unexpected foreign key on transactions.coupon_id.');
            }
            if ($foreignKeys) {
                Schema::table('transactions', function (Blueprint $table) use ($foreignKeys) {
                    $table->dropForeign($foreignKeys[0]->CONSTRAINT_NAME);
                });
            }
        } elseif ($driver === 'sqlite') {
            $foreignKeys = $this->sqliteCouponForeignKeys();
            if (count($foreignKeys) > 1 || ($foreignKeys && ! $this->isCorrectSqliteForeignKey($foreignKeys[0]))) {
                throw new RuntimeException('Refusing to drop an unexpected foreign key on transactions.coupon_id.');
            }
        } elseif ($driver === 'pgsql') {
            $foreignKeys = $this->pgsqlCouponForeignKeys();
            if (count($foreignKeys) > 1 || ($foreignKeys && ! $this->isCorrectPgsqlForeignKey($foreignKeys[0]))) {
                throw new RuntimeException('Refusing to drop an unexpected foreign key on transactions.coupon_id.');
            }
            if ($foreignKeys) {
                Schema::table('transactions', function (Blueprint $table) use ($foreignKeys) {
                    $table->dropForeign($foreignKeys[0]->constraint_name);
                });
            }
        }

        $columns = array_values(array_filter(
            ['coupon_id', 'coupon_code'],
            fn ($column) => Schema::hasColumn('transactions', $column)
        ));
        if ($columns) {
            // Rollback removes only the two columns owned by this migration;
            // it never alters transaction totals or other transaction fields.
            Schema::table('transactions', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }

    private function assertInnoDbParentsWhenMySql(string $driver): void
    {
        if ($driver !== 'mysql') {
            return;
        }

        $tables = DB::select(
            'SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (?, ?)',
            ['transactions', 'coupons']
        );
        foreach ($tables as $table) {
            if (strtoupper((string) $table->ENGINE) !== 'INNODB') {
                throw new RuntimeException('Both transactions and coupons must use InnoDB for coupon foreign-key and checkout transaction safety.');
            }
        }
        if (count($tables) !== 2) {
            throw new RuntimeException('Could not verify both InnoDB tables for coupon snapshots.');
        }

        $couponId = DB::selectOne(
            'SELECT COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['coupons', 'id']
        );
        if (! $couponId || strtolower((string) $couponId->COLUMN_TYPE) !== 'bigint unsigned' || $couponId->IS_NULLABLE !== 'NO') {
            throw new RuntimeException('coupons.id must be a non-null bigint unsigned before transactions.coupon_id can reference it.');
        }
    }

    private function assertCouponSnapshotColumn(string $column): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            $metadata = DB::selectOne(
                'SELECT COLUMN_TYPE, DATA_TYPE, IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                ['transactions', $column]
            );
            $valid = $column === 'coupon_id'
                ? $metadata && strtolower((string) $metadata->COLUMN_TYPE) === 'bigint unsigned' && $metadata->IS_NULLABLE === 'YES'
                : $metadata && strtolower((string) $metadata->DATA_TYPE) === 'varchar' && (int) $metadata->CHARACTER_MAXIMUM_LENGTH === 40 && $metadata->IS_NULLABLE === 'YES';
        } elseif ($driver === 'pgsql') {
            $metadata = DB::selectOne(
                'SELECT data_type, udt_name, is_nullable, character_maximum_length FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?',
                ['transactions', $column]
            );
            $valid = $column === 'coupon_id'
                ? $metadata && strtolower((string) $metadata->data_type) === 'bigint' && $metadata->is_nullable === 'YES'
                : $metadata
                    && in_array(strtolower((string) $metadata->data_type), ['character varying', 'varchar'], true)
                    && strtolower((string) $metadata->udt_name) === 'varchar'
                    && (int) $metadata->character_maximum_length === 40
                    && $metadata->is_nullable === 'YES';
        } else {
            $metadata = collect(DB::select("PRAGMA table_info('transactions')"))->first(function ($item) use ($column) {
                return strtolower((string) $item->name) === $column;
            });
            $type = strtolower((string) optional($metadata)->type);
            $valid = $metadata
                && (int) $metadata->notnull === 0
                && ($column === 'coupon_id' ? in_array($type, ['integer', 'bigint'], true) : in_array($type, ['varchar', 'varchar(40)'], true));
        }

        if (! $valid) {
            throw new RuntimeException('Existing transactions.'.$column.' is missing or does not match the nullable snapshot column definition.');
        }
    }

    private function ensureCorrectCouponForeignKey(string $driver, bool $couponIdWasMissing): void
    {
        if ($driver === 'mysql') {
            $foreignKeys = $this->mysqlCouponForeignKeys();
            if (count($foreignKeys) > 1 || ($foreignKeys && ! $this->isCorrectMySqlForeignKey($foreignKeys[0]))) {
                throw new RuntimeException('transactions.coupon_id already has an incompatible foreign key; no schema was removed or replaced.');
            }
            if ($foreignKeys) {
                return;
            }

            $nameCollision = DB::selectOne(
                'SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ?',
                [self::FOREIGN_KEY_NAME]
            );
            if ($nameCollision) {
                throw new RuntimeException('The expected coupon foreign-key name is already used by another constraint.');
            }

            Schema::table('transactions', function (Blueprint $table) {
                $table->foreign('coupon_id', self::FOREIGN_KEY_NAME)->references('id')->on('coupons')->nullOnDelete();
            });

            $foreignKeys = $this->mysqlCouponForeignKeys();
            if (count($foreignKeys) !== 1 || ! $this->isCorrectMySqlForeignKey($foreignKeys[0])) {
                throw new RuntimeException('The coupon foreign key could not be verified after creation.');
            }

            return;
        }

        if ($driver === 'sqlite') {
            $foreignKeys = $this->sqliteCouponForeignKeys();
            if (count($foreignKeys) === 1 && $this->isCorrectSqliteForeignKey($foreignKeys[0])) {
                return;
            }
            if (! $foreignKeys && ! $couponIdWasMissing) {
                throw new RuntimeException('SQLite cannot add a foreign key to an existing column. Rebuild only the isolated SQLite test schema before retrying.');
            }
            throw new RuntimeException('transactions.coupon_id has no verified SQLite foreign key to coupons.id with ON DELETE SET NULL.');
        }

        if ($driver === 'pgsql') {
            $foreignKeys = $this->pgsqlCouponForeignKeys();
            if (count($foreignKeys) > 1 || ($foreignKeys && ! $this->isCorrectPgsqlForeignKey($foreignKeys[0]))) {
                throw new RuntimeException('transactions.coupon_id already has an incompatible PostgreSQL foreign key; no schema was removed or replaced.');
            }
            if ($foreignKeys) {
                return;
            }

            $nameCollision = $this->pgsqlCouponConstraintNameCollision();
            if ($nameCollision) {
                throw new RuntimeException('The expected coupon foreign-key name is already used by another constraint.');
            }

            Schema::table('transactions', function (Blueprint $table) {
                $table->foreign('coupon_id', self::FOREIGN_KEY_NAME)->references('id')->on('coupons')->nullOnDelete();
            });

            $foreignKeys = $this->pgsqlCouponForeignKeys();
            if (count($foreignKeys) !== 1 || ! $this->isCorrectPgsqlForeignKey($foreignKeys[0])) {
                throw new RuntimeException('The PostgreSQL coupon foreign key could not be verified after creation.');
            }

            return;
        }

        throw new RuntimeException('Cannot verify the coupon foreign key on database driver '.$driver.'.');
    }

    private function assertExistingForeignKeyCompatibility(string $driver, bool $couponIdWasMissing): void
    {
        if ($driver === 'mysql') {
            $foreignKeys = $this->mysqlCouponForeignKeys();
            if (count($foreignKeys) > 1 || ($foreignKeys && ! $this->isCorrectMySqlForeignKey($foreignKeys[0]))) {
                throw new RuntimeException('transactions.coupon_id already has an incompatible foreign key; no additional columns were added.');
            }
            if (! $foreignKeys) {
                $nameCollision = DB::selectOne(
                    'SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ?',
                    [self::FOREIGN_KEY_NAME]
                );
                if ($nameCollision) {
                    throw new RuntimeException('The expected coupon foreign-key name is already used by another constraint.');
                }
            }

            return;
        }

        if ($driver === 'sqlite') {
            $foreignKeys = $this->sqliteCouponForeignKeys();
            if ($foreignKeys && (count($foreignKeys) !== 1 || ! $this->isCorrectSqliteForeignKey($foreignKeys[0]))) {
                throw new RuntimeException('transactions.coupon_id already has an incompatible SQLite foreign key.');
            }
            if (! $couponIdWasMissing && ! $foreignKeys) {
                throw new RuntimeException('SQLite cannot attach a foreign key to an existing coupon_id column without rebuilding the test table.');
            }

            return;
        }

        if ($driver === 'pgsql') {
            $foreignKeys = $this->pgsqlCouponForeignKeys();
            if ($foreignKeys && (count($foreignKeys) !== 1 || ! $this->isCorrectPgsqlForeignKey($foreignKeys[0]))) {
                throw new RuntimeException('transactions.coupon_id already has an incompatible PostgreSQL foreign key; no additional columns were added.');
            }
            if (! $foreignKeys && $this->pgsqlCouponConstraintNameCollision()) {
                throw new RuntimeException('The expected coupon foreign-key name is already used by another constraint.');
            }

            return;
        }

        throw new RuntimeException('Cannot verify the coupon foreign key on database driver '.$driver.'.');
    }

    private function mysqlCouponForeignKeys(): array
    {
        return DB::select(
            'SELECT k.CONSTRAINT_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE, (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE k2 WHERE k2.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND k2.TABLE_NAME = k.TABLE_NAME AND k2.CONSTRAINT_NAME = k.CONSTRAINT_NAME) AS COLUMN_COUNT FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME WHERE k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = ? AND k.COLUMN_NAME = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL',
            ['transactions', 'coupon_id']
        );
    }

    private function isCorrectMySqlForeignKey($foreignKey): bool
    {
        return strtolower((string) $foreignKey->REFERENCED_TABLE_NAME) === 'coupons'
            && strtolower((string) $foreignKey->REFERENCED_COLUMN_NAME) === 'id'
            && (int) $foreignKey->COLUMN_COUNT === 1
            && strtoupper((string) $foreignKey->DELETE_RULE) === 'SET NULL';
    }

    private function pgsqlCouponForeignKeys(): array
    {
        return DB::select(
            "SELECT con.conname AS constraint_name, ref_ns.nspname AS referenced_schema, ref_table.relname AS referenced_table, ref_column.attname AS referenced_column, array_length(con.conkey, 1) AS column_count, con.confdeltype AS delete_action FROM pg_constraint con JOIN pg_class local_table ON local_table.oid = con.conrelid JOIN pg_namespace local_ns ON local_ns.oid = local_table.relnamespace JOIN LATERAL unnest(con.conkey) WITH ORDINALITY AS local_key(attnum, ordinality) ON true JOIN pg_attribute local_column ON local_column.attrelid = local_table.oid AND local_column.attnum = local_key.attnum JOIN LATERAL unnest(con.confkey) WITH ORDINALITY AS ref_key(attnum, ordinality) ON ref_key.ordinality = local_key.ordinality JOIN pg_class ref_table ON ref_table.oid = con.confrelid JOIN pg_namespace ref_ns ON ref_ns.oid = ref_table.relnamespace JOIN pg_attribute ref_column ON ref_column.attrelid = ref_table.oid AND ref_column.attnum = ref_key.attnum WHERE con.contype = 'f' AND local_ns.nspname = current_schema() AND local_table.relname = ? AND local_column.attname = ?",
            ['transactions', 'coupon_id']
        );
    }

    private function isCorrectPgsqlForeignKey($foreignKey): bool
    {
        $currentSchema = DB::selectOne('SELECT current_schema() AS schema_name');

        return $currentSchema
            && strtolower((string) $foreignKey->referenced_schema) === strtolower((string) $currentSchema->schema_name)
            && strtolower((string) $foreignKey->referenced_table) === 'coupons'
            && strtolower((string) $foreignKey->referenced_column) === 'id'
            && (int) $foreignKey->column_count === 1
            && (string) $foreignKey->delete_action === 'n';
    }

    private function pgsqlCouponConstraintNameCollision()
    {
        return DB::selectOne(
            "SELECT con.conname FROM pg_constraint con JOIN pg_class local_table ON local_table.oid = con.conrelid JOIN pg_namespace local_ns ON local_ns.oid = local_table.relnamespace WHERE local_ns.nspname = current_schema() AND local_table.relname = ? AND con.conname = ?",
            ['transactions', self::FOREIGN_KEY_NAME]
        );
    }

    private function sqliteCouponForeignKeys(): array
    {
        return array_values(array_filter(DB::select("PRAGMA foreign_key_list('transactions')"), function ($foreignKey) {
            return strtolower((string) $foreignKey->from) === 'coupon_id';
        }));
    }

    private function isCorrectSqliteForeignKey($foreignKey): bool
    {
        $sameConstraintRows = array_filter(DB::select("PRAGMA foreign_key_list('transactions')"), function ($row) use ($foreignKey) {
            return (int) $row->id === (int) $foreignKey->id;
        });

        return strtolower((string) $foreignKey->table) === 'coupons'
            && strtolower((string) $foreignKey->to) === 'id'
            && count($sameConstraintRows) === 1
            && strtoupper((string) $foreignKey->on_delete) === 'SET NULL';
    }
};
