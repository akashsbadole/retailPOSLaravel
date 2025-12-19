<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add only essential indexes that we know will work
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasIndex('orders', 'orders_customer_id_status_index')) {
                $table->index(['customer_id', 'status']);
            }
            if (!Schema::hasIndex('orders', 'orders_cashier_shift_id_status_index')) {
                $table->index(['cashier_shift_id', 'status']);
            }
            if (!Schema::hasIndex('orders', 'orders_status_created_at_index')) {
                $table->index(['status', 'created_at']);
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasIndex('order_items', 'order_items_order_id_product_id_index')) {
                $table->index(['order_id', 'product_id']);
            }
            if (!Schema::hasIndex('order_items', 'order_items_product_id_quantity_index')) {
                $table->index(['product_id', 'quantity']);
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasIndex('products', 'products_quantity_minimum_stock_index')) {
                $table->index(['quantity', 'minimum_stock']);
            }
            if (!Schema::hasIndex('products', 'products_status_quantity_index')) {
                $table->index(['status', 'quantity']);
            }
            if (!Schema::hasIndex('products', 'products_barcode_index')) {
                $table->index(['barcode']);
            }
            if (!Schema::hasIndex('products', 'products_sku_index')) {
                $table->index(['sku']);
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasIndex('payments', 'payments_order_id_payment_method_index')) {
                $table->index(['order_id', 'payment_method']);
            }
            if (!Schema::hasIndex('payments', 'payments_cashier_shift_id_payment_method_index')) {
                $table->index(['cashier_shift_id', 'payment_method']);
            }
            if (!Schema::hasIndex('payments', 'payments_user_id_created_at_index')) {
                $table->index(['user_id', 'created_at']);
            }
        });

        Schema::table('sales_returns', function (Blueprint $table) {
            if (!Schema::hasIndex('sales_returns', 'sales_returns_order_id_status_index')) {
                $table->index(['order_id', 'status']);
            }
            if (!Schema::hasIndex('sales_returns', 'sales_returns_user_id_status_index')) {
                $table->index(['user_id', 'status']);
            }
            if (!Schema::hasIndex('sales_returns', 'sales_returns_status_return_date_index')) {
                $table->index(['status', 'return_date']);
            }
        });

        Schema::table('sales_return_items', function (Blueprint $table) {
            if (!Schema::hasIndex('sales_return_items', 'sales_return_items_sales_return_id_order_item_id_index')) {
                $table->index(['sales_return_id', 'order_item_id']);
            }
            if (!Schema::hasIndex('sales_return_items', 'sales_return_items_order_item_id_quantity_index')) {
                $table->index(['order_item_id', 'quantity']);
            }
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            if (!Schema::hasIndex('stock_adjustments', 'stock_adjustments_product_id_adjustment_type_index')) {
                $table->index(['product_id', 'adjustment_type']);
            }
            if (!Schema::hasIndex('stock_adjustments', 'stock_adjustments_user_id_approved_index')) {
                $table->index(['user_id', 'approved']);
            }
            if (!Schema::hasIndex('stock_adjustments', 'stock_adjustments_approved_created_at_index')) {
                $table->index(['approved', 'created_at']);
            }
        });

        Schema::table('cashier_shifts', function (Blueprint $table) {
            if (!Schema::hasIndex('cashier_shifts', 'cashier_shifts_user_id_status_index')) {
                $table->index(['user_id', 'status']);
            }
            if (!Schema::hasIndex('cashier_shifts', 'cashier_shifts_status_start_time_index')) {
                $table->index(['status', 'start_time']);
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasIndex('customers', 'customers_first_name_last_name_index')) {
                $table->index(['first_name', 'last_name']);
            }
            if (!Schema::hasIndex('customers', 'customers_email_index')) {
                $table->index(['email']);
            }
            if (!Schema::hasIndex('customers', 'customers_phone_index')) {
                $table->index(['phone']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'status']);
            $table->dropIndex(['cashier_shift_id', 'status']);
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['created_at', 'total_price']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['order_id', 'product_id']);
            $table->dropIndex(['product_id', 'quantity']);
            $table->dropIndex(['order_id', 'created_at']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['quantity', 'minimum_stock']);
            $table->dropIndex(['status', 'quantity']);
            $table->dropIndex(['barcode']);
            $table->dropIndex(['sku']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['order_id', 'payment_method']);
            $table->dropIndex(['cashier_shift_id', 'payment_method']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['payment_method', 'created_at']);
        });

        Schema::table('sales_returns', function (Blueprint $table) {
            $table->dropIndex(['order_id', 'status']);
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['status', 'return_date']);
            $table->dropIndex(['approved_by', 'status']);
        });

        Schema::table('sales_return_items', function (Blueprint $table) {
            $table->dropIndex('sales_return_items_sales_return_id_order_item_id_index');
            $table->dropIndex('sales_return_items_order_item_id_quantity_index');
            $table->dropIndex('sales_return_items_restocked_index');
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'adjustment_type']);
            $table->dropIndex(['user_id', 'approved']);
            $table->dropIndex(['approved', 'created_at']);
            $table->dropIndex(['adjustment_type', 'reason']);
        });

        Schema::table('cashier_shifts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['status', 'start_time']);
            $table->dropIndex(['opened_by', 'status']);
            $table->dropIndex(['closed_by', 'status']);
        });

        Schema::table('cart', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'product_id']);
            $table->dropIndex(['user_id', 'created_at']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['first_name', 'last_name']);
            $table->dropIndex(['email']);
            $table->dropIndex(['phone']);
        });
    }
};