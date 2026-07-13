-- Backfills pieces_per_pack for the 13 products that existed at the time
-- this feature shipped. Matched by productName (not numeric id) since
-- product ids can differ between environments.
-- 7 of these were parsed directly from an explicit "(N PCS)" in the title;
-- the other 6 (410mm/320mm/280mm/Combo/Trial Pack 320mm/Trial Pack 280mm)
-- were provided directly by the user, since their titles don't state a count.
-- Applied: 2026-07-13

UPDATE products SET pieces_per_pack = 5  WHERE productName = '410mm XXL  - Femi9 Premium Sanitary Napkin';
UPDATE products SET pieces_per_pack = 10 WHERE productName = '320mm XL - Femi9 Premium Sanitary Napkin';
UPDATE products SET pieces_per_pack = 12 WHERE productName = '280mm L - Femi9 Premium Sanitary Napkin';
UPDATE products SET pieces_per_pack = 30 WHERE productName = '180mm (30 PCS) - Femi9 Premium Sanitary Napkin';
UPDATE products SET pieces_per_pack = 6  WHERE productName = 'Combo pack - Femi9 Sanitary Napkins';
UPDATE products SET pieces_per_pack = 3  WHERE productName = 'Trial Pack 320mm - Femi9 Sanitary Napkins';
UPDATE products SET pieces_per_pack = 4  WHERE productName = 'Trial Pack  280mm - Femi9 Sanitary Napkins';
UPDATE products SET pieces_per_pack = 9  WHERE productName = '330mm XL (9 PCS) - Femi9 Premium Sanitary Napkin';
UPDATE products SET pieces_per_pack = 9  WHERE productName = '290mm L (9  PCS) - Femi9 Premium Sanitary Napkin';
UPDATE products SET pieces_per_pack = 6  WHERE productName = '330mm XL (6 PCS) - Femi9 Premium Sanitary Napkin';
UPDATE products SET pieces_per_pack = 3  WHERE productName = '330mm XL (3 PCS) - Femi9 Premium Sanitary Napkin';
UPDATE products SET pieces_per_pack = 6  WHERE productName = '290mm L (6 PCS) - Femi9 Premium Sanitary Napkin';
UPDATE products SET pieces_per_pack = 3  WHERE productName = '290mm L (3 PCS) - Femi9 Premium Sanitary Napkin';
