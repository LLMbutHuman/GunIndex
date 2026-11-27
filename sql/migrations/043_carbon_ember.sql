CREATE TABLE IF NOT EXISTS market_editorial_reviews (
    topic_code VARCHAR(32) NOT NULL,
    country_code CHAR(2) NOT NULL,
    status_code VARCHAR(24) NOT NULL,
    legal_reference VARCHAR(255) NOT NULL,
    scope_note TEXT NOT NULL,
    reviewed_by INT NOT NULL,
    reviewed_at DATETIME NOT NULL,
    revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (topic_code, country_code),
    CONSTRAINT market_editorial_country_fk FOREIGN KEY (country_code) REFERENCES market_countries(code) ON DELETE RESTRICT,
    CONSTRAINT market_editorial_reviewer_fk FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT market_editorial_review_status CHECK (status_code IN ('permit','exempt','age','pending','unregulated','prohibited'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- These are ordinary equipment categories. Editorial topics have no marketplace category IDs.
INSERT INTO market_categories (name, slug, sort_order)
SELECT 'Jagd & Revier', 'jagd-revier', 15
WHERE NOT EXISTS (SELECT 1 FROM market_categories WHERE slug='jagd-revier');

INSERT INTO market_categories (parent_id, name, slug, sort_order)
SELECT parent.id, child.name, child.slug, child.sort_order
FROM market_categories parent
JOIN (
    SELECT 'jagdbekleidung' AS slug, 'Jagdbekleidung' AS name, 10 AS sort_order
    UNION ALL
    SELECT 'revierausstattung', 'Revierausstattung', 20
    UNION ALL
    SELECT 'jagdtaschen', 'Jagdtaschen', 30
    UNION ALL
    SELECT 'jagdrucksaecke', 'Jagdrucksäcke', 40
    UNION ALL
    SELECT 'wildbeobachtung', 'Wildbeobachtung', 50
    UNION ALL
    SELECT 'jagdliteratur', 'Jagdliteratur', 60
) child
WHERE parent.slug='jagd-revier' AND parent.parent_id IS NULL
  AND NOT EXISTS (SELECT 1 FROM market_categories existing WHERE existing.slug=child.slug);

INSERT INTO market_categories (name, slug, sort_order)
SELECT 'Schießsport', 'schiesssport', 16
WHERE NOT EXISTS (SELECT 1 FROM market_categories WHERE slug='schiesssport');

INSERT INTO market_categories (parent_id, name, slug, sort_order)
SELECT parent.id, child.name, child.slug, child.sort_order
FROM market_categories parent
JOIN (
    SELECT 'schiessstandausruestung' AS slug, 'Schießstandausrüstung' AS name, 10 AS sort_order
    UNION ALL
    SELECT 'zielscheiben', 'Zielscheiben', 20
    UNION ALL
    SELECT 'trainingszubehoer', 'Trainingszubehör', 30
    UNION ALL
    SELECT 'gehoerschutz', 'Gehörschutz', 40
    UNION ALL
    SELECT 'schutzbrillen', 'Schutzbrillen', 50
    UNION ALL
    SELECT 'sportbekleidung', 'Sportbekleidung', 60
) child
WHERE parent.slug='schiesssport' AND parent.parent_id IS NULL
  AND NOT EXISTS (SELECT 1 FROM market_categories existing WHERE existing.slug=child.slug);

INSERT INTO market_categories (parent_id, name, slug, sort_order)
SELECT parent.id, child.name, child.slug, child.sort_order
FROM market_categories parent
JOIN (
    SELECT 'fernglaeser' AS slug, 'Ferngläser' AS name, 10 AS sort_order
    UNION ALL
    SELECT 'spektive', 'Spektive', 20
    UNION ALL
    SELECT 'entfernungsmessgeraete', 'Entfernungsmessgeräte', 30
    UNION ALL
    SELECT 'beobachtungszubehoer', 'Beobachtungszubehör', 40
    UNION ALL
    SELECT 'optikpflege', 'Optikpflege', 50
) child
WHERE parent.slug='optik' AND parent.parent_id IS NULL
  AND NOT EXISTS (SELECT 1 FROM market_categories existing WHERE existing.slug=child.slug);

INSERT INTO market_categories (parent_id, name, slug, sort_order)
SELECT parent.id, child.name, child.slug, child.sort_order
FROM market_categories parent
JOIN (
    SELECT 'rucksaecke' AS slug, 'Rucksäcke' AS name, 10 AS sort_order
    UNION ALL
    SELECT 'campingausruestung', 'Campingausrüstung', 20
    UNION ALL
    SELECT 'outdoorbekleidung', 'Outdoorbekleidung', 30
    UNION ALL
    SELECT 'beleuchtung', 'Beleuchtung', 40
    UNION ALL
    SELECT 'sicherheitsausruestung', 'Sicherheitsausrüstung', 50
) child
WHERE parent.slug='outdoor-camping' AND parent.parent_id IS NULL
  AND NOT EXISTS (SELECT 1 FROM market_categories existing WHERE existing.slug=child.slug);

INSERT INTO market_categories (name, slug, sort_order)
SELECT 'Pflege & Aufbewahrung', 'pflege-aufbewahrung', 65
WHERE NOT EXISTS (SELECT 1 FROM market_categories WHERE slug='pflege-aufbewahrung');

INSERT INTO market_categories (parent_id, name, slug, sort_order)
SELECT parent.id, child.name, child.slug, child.sort_order
FROM market_categories parent
JOIN (
    SELECT 'reinigungszubehoer' AS slug, 'Reinigungszubehör' AS name, 10 AS sort_order
    UNION ALL
    SELECT 'pflegeprodukte', 'Pflegeprodukte', 20
    UNION ALL
    SELECT 'sicherheitsbehaeltnisse', 'Sicherheitsbehältnisse', 30
    UNION ALL
    SELECT 'transporttaschen', 'Transporttaschen', 40
    UNION ALL
    SELECT 'aufbewahrungszubehoer', 'Aufbewahrungszubehör', 50
) child
WHERE parent.slug='pflege-aufbewahrung' AND parent.parent_id IS NULL
  AND NOT EXISTS (SELECT 1 FROM market_categories existing WHERE existing.slug=child.slug);
