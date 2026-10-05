-- Extends the View/Edit/Delete/All granular permission model (piloted on
-- manage_input_stock) to the distributor-tier manage modules: Super
-- Stockist (ss), Stockist (st), Distributor (dt), Super Distributor (sdt),
-- Shop (shop), Customer (cus). Each existing column stays the View/base
-- permission; these new columns let a company grant a "users" sub-account
-- the ability to see the list without also being able to edit or delete.
-- Applied: 2026-10-03

ALTER TABLE admin_log
  ADD COLUMN ss_edit    TINYINT(1) NOT NULL DEFAULT 0 AFTER ss,
  ADD COLUMN ss_delete  TINYINT(1) NOT NULL DEFAULT 0 AFTER ss_edit,
  ADD COLUMN st_edit    TINYINT(1) NOT NULL DEFAULT 0 AFTER st,
  ADD COLUMN st_delete  TINYINT(1) NOT NULL DEFAULT 0 AFTER st_edit,
  ADD COLUMN dt_edit    TINYINT(1) NOT NULL DEFAULT 0 AFTER dt,
  ADD COLUMN dt_delete  TINYINT(1) NOT NULL DEFAULT 0 AFTER dt_edit,
  ADD COLUMN sdt_edit   TINYINT(1) NOT NULL DEFAULT 0 AFTER sdt,
  ADD COLUMN sdt_delete TINYINT(1) NOT NULL DEFAULT 0 AFTER sdt_edit,
  ADD COLUMN shop_edit    TINYINT(1) NOT NULL DEFAULT 0 AFTER shop,
  ADD COLUMN shop_delete  TINYINT(1) NOT NULL DEFAULT 0 AFTER shop_edit,
  ADD COLUMN cus_edit    TINYINT(1) NOT NULL DEFAULT 0 AFTER cus,
  ADD COLUMN cus_delete  TINYINT(1) NOT NULL DEFAULT 0 AFTER cus_edit;
