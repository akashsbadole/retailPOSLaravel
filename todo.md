Below is a **clean, step-by-step instruction list** to build a **retail / supermart POS in Laravel**, strictly **phase-wise**, and **clearly marking**:

* ✅ **Already available in `laravel-easy-pos`**
* ➕ **Needs to be created in Laravel**

No theory, no code — **only implementation instructions**.

---

# 🟢 PHASE 0 — BASE SETUP (Laravel Easy POS)

### Instructions

1. Fork `mailmug/laravel-easy-pos`
2. Run migrations and seed demo data
3. Verify:

   * Login works
   * POS screen loads
   * Product add/edit works
   * Sale & invoice works

### Status

* ✅ Laravel project ready
* ✅ POS UI ready
* ✅ Product CRUD exists
* ✅ Sales flow exists

---

# 🟢 PHASE 1 — CORE RETAIL POS (MANDATORY)

> **Goal:** Small shop can sell items daily without issues

### Features & Instructions

1. **Product Management**

   * Use existing Product CRUD
   * Add fields if missing:

     * SKU
     * Barcode
     * Cost price
   * Ensure product search works fast

   **Status:** ✅ Exists → Extend

2. **Inventory Auto-Deduction**

   * On each sale:

     * Reduce product stock quantity
     * Prevent selling if stock = 0

   **Status:** ➕ Create (Laravel event or observer)

3. **POS Checkout**

   * Use existing POS screen
   * Ensure:

     * Quantity increase/decrease
     * Remove item from cart
     * Final total calculation

   **Status:** ✅ Exists

4. **Invoice / Receipt**

   * Confirm:

     * Invoice number auto-generated
     * Printable receipt view
     * Store name & GST shown

   **Status:** ✅ Exists (verify & polish)

5. **Basic Sales Report**

   * Daily sales list
   * Total sales amount

   **Status:** ✅ Exists

---

# 🟡 PHASE 2 — ESSENTIAL RETAIL FEATURES

> **Goal:** Real retail workflow (customers, discounts, tax)

### Features & Instructions

6. **Customer Management**

   * Create customers table
   * Allow:

     * Add customer during checkout
     * Attach customer to sale
   * Store:

     * Name
     * Mobile number

   **Status:** ➕ Create in Laravel

7. **Discount System**

   * Add:

     * Item-level discount
     * Bill-level discount
   * Store discount value in sale tables

   **Status:** ➕ Create

8. **Tax / GST Configuration**

   * Add tax percentage field
   * Calculate tax during checkout
   * Show tax breakup on invoice

   **Status:** ➕ Create

9. **Multiple Payment Types**

   * Add payment method column:

     * Cash
     * Card
     * UPI
   * Allow split payment (optional)

   **Status:** ➕ Create

10. **Low Stock Alert**

    * Add minimum stock column
    * Show alert on dashboard if stock is low

    **Status:** ➕ Create

---

# 🟠 PHASE 3 — RETAIL OPERATIONS SUPPORT

> **Goal:** Better stock accuracy & cashier control

### Features & Instructions

11. **User Roles**

    * Add roles:

      * Admin
      * Manager
      * Cashier
    * Restrict:

      * Discount access
      * Product delete

    **Status:** ➕ Create (Spatie Permission)

12. **Stock Adjustment**

    * Create stock adjustment screen
    * Reasons:

      * Damage
      * Theft
      * Manual correction
    * Maintain stock history table

    **Status:** ➕ Create

13. **Cashier Shift System**

    * Cashier login opens shift
    * Shift close:

      * Total sales
      * Cash collected

    **Status:** ➕ Create

14. **Sales Return / Refund**

    * Allow:

      * Full return
      * Partial return
    * Adjust stock automatically

    **Status:** ➕ Create

---

# 🔵 PHASE 4 — BASIC BUSINESS REPORTS

> **Goal:** Owner understands business performance

### Features & Instructions

15. **Product Sales Report**

    * Top selling products
    * Low selling products

    **Status:** ➕ Create

16. **Cashier Performance Report**

    * Sales by cashier
    * Shift summary

    **Status:** ➕ Create

17. **Profit Report**

    * Use cost price vs selling price
    * Daily / monthly profit

    **Status:** ➕ Create

---

# 🔴 PHASE 5 — OPTIONAL (ONLY AFTER STABLE)

> **Goal:** Scaling & monetization

18. Supplier & Purchase module
19. Barcode scanner support
20. Multi-store support
21. Mobile POS / API

---

# ✅ FINAL SUMMARY TABLE

| Phase   | Purpose           | Laravel Easy POS |
| ------- | ----------------- | ---------------- |
| Phase 1 | Daily selling     | Mostly exists    |
| Phase 2 | Real retail       | Needs extension  |
| Phase 3 | Operations        | New              |
| Phase 4 | Business insights | New              |
| Phase 5 | Scaling           | Optional         |

---

If you want, next I can:

* Break **Phase 1 DB changes**
* Share **exact Laravel tables needed**
* Or give **feature-wise migration plan**

Just tell me **which phase to start first**.
