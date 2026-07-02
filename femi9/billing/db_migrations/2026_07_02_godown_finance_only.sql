-- Restrict specific company profiles (godowns) to the "finance" login only
-- Applied: 2026-07-02

ALTER TABLE company_godown ADD COLUMN finance_only TINYINT(1) NOT NULL DEFAULT 0 AFTER gname;

UPDATE company_godown SET finance_only = 1 WHERE gname IN ('FEMI HEALTH CARE', 'NEKSOMO HYGIENE INDUSTRIES');
