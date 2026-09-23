<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            // Для MySQL изменяем ENUM
            DB::statement("ALTER TABLE orders MODIFY COLUMN order_core ENUM('core', 'non_core', 'other') NOT NULL DEFAULT 'core'");
        } elseif ($driver === 'sqlite') {
            // Для SQLite нужно пересоздать таблицу с новым CHECK constraint
            // В SQLite нельзя изменить CHECK constraint напрямую
            // Отключаем проверку внешних ключей на время операции
            DB::statement("PRAGMA foreign_keys = OFF");
            
            // Удаляем временную таблицу, если она существует (от предыдущей неудачной попытки)
            DB::statement("DROP TABLE IF EXISTS orders_new");
            
            DB::statement("
                CREATE TABLE orders_new (
                    order_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    datetime_order DATETIME NOT NULL,
                    address_id INTEGER NOT NULL,
                    order_status TEXT NOT NULL DEFAULT 'pending' CHECK(order_status IN ('pending', 'callback', 'not_processed', 'rejected', 'unassigned', 'on_way', 'in_progress', 'in_progress_sd', 'review', 'waiting_parts', 'waiting_payment', 'completed', 'cancelled_cc', 'cancelled_city')),
                    order_type TEXT NOT NULL DEFAULT 'new' CHECK(order_type IN ('new', 'repeat', 'warranty')),
                    order_core TEXT NOT NULL DEFAULT 'core' CHECK(order_core IN ('core', 'non_core', 'other')),
                    amount_paid INTEGER NOT NULL DEFAULT 0,
                    amount_comp INTEGER NOT NULL DEFAULT 0,
                    master_id INTEGER,
                    source_id INTEGER,
                    order_adds TEXT,
                    shift_adds TEXT,
                    city_adds TEXT,
                    order_created_by INTEGER NOT NULL,
                    order_created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    order_closed_by INTEGER,
                    order_closed_at TIMESTAMP,
                    FOREIGN KEY (address_id) REFERENCES addresses(address_id),
                    FOREIGN KEY (master_id) REFERENCES users(user_id),
                    FOREIGN KEY (source_id) REFERENCES sources(source_id),
                    FOREIGN KEY (order_created_by) REFERENCES users(user_id),
                    FOREIGN KEY (order_closed_by) REFERENCES users(user_id)
                )
            ");
            
            // Копируем данные из старой таблицы (если есть)
            // Явно указываем поля, чтобы избежать проблем при несовпадении структуры
            DB::statement("
                INSERT INTO orders_new (
                    order_id, datetime_order, address_id, order_status, order_type, order_core,
                    amount_paid, amount_comp, master_id, source_id, order_adds, shift_adds, city_adds,
                    order_created_by, order_created_at, order_closed_by, order_closed_at
                )
                SELECT 
                    order_id, datetime_order, address_id, order_status, order_type, order_core,
                    amount_paid, amount_comp, master_id, source_id, order_adds, shift_adds, city_adds,
                    order_created_by, order_created_at, order_closed_by, order_closed_at
                FROM orders
            ");
            
            // Удаляем старую таблицу
            DB::statement("DROP TABLE orders");
            
            // Переименовываем новую таблицу
            DB::statement("ALTER TABLE orders_new RENAME TO orders");
            
            // Создаём индексы
            DB::statement("CREATE INDEX orders_datetime_order_index ON orders(datetime_order)");
            DB::statement("CREATE INDEX orders_order_status_index ON orders(order_status)");
            
            // Включаем обратно проверку внешних ключей
            DB::statement("PRAGMA foreign_keys = ON");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'mysql') {
            // Для MySQL возвращаем ENUM к исходному состоянию
            DB::statement("ALTER TABLE orders MODIFY COLUMN order_core ENUM('core', 'non_core') NOT NULL DEFAULT 'core'");
        } elseif ($driver === 'sqlite') {
            // Для SQLite пересоздаём таблицу без 'other'
            // Отключаем проверку внешних ключей на время операции
            DB::statement("PRAGMA foreign_keys = OFF");
            
            // Удаляем временную таблицу, если она существует (от предыдущей неудачной попытки)
            DB::statement("DROP TABLE IF EXISTS orders_old");
            
            DB::statement("
                CREATE TABLE orders_old (
                    order_id INTEGER PRIMARY KEY AUTOINCREMENT,
                    datetime_order DATETIME NOT NULL,
                    address_id INTEGER NOT NULL,
                    order_status TEXT NOT NULL DEFAULT 'pending' CHECK(order_status IN ('pending', 'callback', 'not_processed', 'rejected', 'unassigned', 'on_way', 'in_progress', 'in_progress_sd', 'review', 'waiting_parts', 'waiting_payment', 'completed', 'cancelled_cc', 'cancelled_city')),
                    order_type TEXT NOT NULL DEFAULT 'new' CHECK(order_type IN ('new', 'repeat', 'warranty')),
                    order_core TEXT NOT NULL DEFAULT 'core' CHECK(order_core IN ('core', 'non_core')),
                    amount_paid INTEGER NOT NULL DEFAULT 0,
                    amount_comp INTEGER NOT NULL DEFAULT 0,
                    master_id INTEGER,
                    source_id INTEGER,
                    order_adds TEXT,
                    shift_adds TEXT,
                    city_adds TEXT,
                    order_created_by INTEGER NOT NULL,
                    order_created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    order_closed_by INTEGER,
                    order_closed_at TIMESTAMP,
                    FOREIGN KEY (address_id) REFERENCES addresses(address_id),
                    FOREIGN KEY (master_id) REFERENCES users(user_id),
                    FOREIGN KEY (source_id) REFERENCES sources(source_id),
                    FOREIGN KEY (order_created_by) REFERENCES users(user_id),
                    FOREIGN KEY (order_closed_by) REFERENCES users(user_id)
                )
            ");
            
            // Копируем данные, исключая записи с 'other'
            // Явно указываем поля, чтобы избежать проблем при несовпадении структуры
            DB::statement("
                INSERT INTO orders_old (
                    order_id, datetime_order, address_id, order_status, order_type, order_core,
                    amount_paid, amount_comp, master_id, source_id, order_adds, shift_adds, city_adds,
                    order_created_by, order_created_at, order_closed_by, order_closed_at
                )
                SELECT 
                    order_id, datetime_order, address_id, order_status, order_type, order_core,
                    amount_paid, amount_comp, master_id, source_id, order_adds, shift_adds, city_adds,
                    order_created_by, order_created_at, order_closed_by, order_closed_at
                FROM orders 
                WHERE order_core != 'other'
            ");
            
            // Удаляем старую таблицу
            DB::statement("DROP TABLE orders");
            
            // Переименовываем новую таблицу
            DB::statement("ALTER TABLE orders_old RENAME TO orders");
            
            // Создаём индексы
            DB::statement("CREATE INDEX orders_datetime_order_index ON orders(datetime_order)");
            DB::statement("CREATE INDEX orders_order_status_index ON orders(order_status)");
            
            // Включаем обратно проверку внешних ключей
            DB::statement("PRAGMA foreign_keys = ON");
        }
    }
};
