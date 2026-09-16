-- Synthetic demo data — NOT real business/customer data.
-- Gives a Territory Partner login and a small product catalog so the app
-- isn't completely empty for a developer trying it out.

USE `billing0femi9_billingapp`;

-- Demo Territory Partner login
-- Mobile: 9000000001   Password: Demo@123
INSERT INTO `territory_partners`
    (`tp_id`, `name`, `company_name`, `mobile`, `email`, `branch_city`, `branch_district`,
     `branch_state`, `branch_country`, `is_active`, `password`, `created_by`, `updated_by`,
     `must_change_password`, `stock_initialized`)
VALUES
    ('TP-DEMO-001', 'Demo Territory Partner', 'Demo Distribution Co', '9000000001',
     'demo.tp@example.com', 'Chennai', 'Chennai', 'Tamil Nadu', 'India', 1,
     '$2y$10$7vaFxL4XHCpY4/3Qod4UYOM0skzJzArX238gXoeFg7FT8.uwPFxZy',
     'seed', 'seed', 0, 1);

-- Sample product catalog (fake names/prices)
INSERT INTO `products`
    (`temp_id`, `productName`, `pieces_per_pack`, `packs_per_carton`, `unit_type`, `category`,
     `mrp`, `supersstock_price`, `stockist_price`, `distributor_price`, `super_distributor_price`,
     `outlet_price`, `gst`, `gst_type`, `hsn`, `rwpoints`)
VALUES
    ('PROD-DEMO-001', 'Demo Sanitary Napkin Pack (10s)', 10, 12, 'pack', 'napkin',
     120, 90, 95, 100, 98, 110, 12, 'exclusive', '9619', 1),
    ('PROD-DEMO-002', 'Demo Baby Diaper Pack (20s)', 20, 8, 'pack', 'diaper',
     450, 350, 365, 380, 372, 420, 12, 'exclusive', '9619', 2),
    ('PROD-DEMO-003', 'Demo Sanitary Napkin Pack (20s)', 20, 12, 'pack', 'napkin',
     220, 165, 172, 180, 176, 200, 12, 'exclusive', '9619', 1.5);
