-- Create a dedicated 'neksomo' Company-portal login, scoped to only the
-- NEKSOMO HYGIENE INDUSTRIES godown (see GodownAccess.php / PermissionCheck.php).
-- Password is plaintext here; CheckLogin.php auto-encrypts it on first
-- successful login (same bootstrap pattern used for the existing
-- admin/finance rows).
-- Applied: 2026-07-13

INSERT INTO admin_log
    (username, password, usertype, state, dash, report, company_profile, users_demo,
     reward_points, demo_free, manage_return, debit_note, stock_request, products,
     add_input_stock, manage_input_stock, add_input_stock_users, manage_input_stock_users,
     ot_channels, location, ss, st, dt, sdt, shop, cus, ms, unassigned, remap,
     partner_location, channel_partner, territory_partner, stock_transfers,
     users_network, payment_entry, manage_payment_entry, consolidated_payment_entry,
     bonus_calculator, manage_bonus_points)
VALUES
    ('9715059715', 'Neksomo@2026', 'neksomo', 0, 0, 0, 0, 0,
     0, 0, 0, 0, 0, 0,
     0, 0, 0, 0,
     0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
     0, 0, 0, 0,
     0, 0, 0, 0,
     0, 0);
