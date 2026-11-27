ALTER TABLE market_campaigns MODIFY COLUMN placement ENUM('legacy','home','search','country') NOT NULL DEFAULT 'legacy';
ALTER TABLE market_banner_rates MODIFY COLUMN placement ENUM('home','search','country') NOT NULL;
INSERT INTO market_banner_rates (placement,term_days,gross_cents,updated_by)
SELECT 'country', r.term_days, r.gross_cents, r.updated_by
FROM market_banner_rates r
WHERE r.placement IN ('home','search') AND NOT EXISTS (
    SELECT 1 FROM market_banner_rates s WHERE s.term_days=r.term_days
        AND (s.placement='country' OR (s.placement IN ('home','search') AND
        (s.gross_cents<r.gross_cents OR (s.gross_cents=r.gross_cents AND s.placement='home' AND r.placement='search'))))
);

INSERT INTO market_business_inventory (country_code,scope,category_id,slot_no)
SELECT c.code,'banner_country',0,slots.number FROM market_countries c
CROSS JOIN (SELECT 1 AS number UNION ALL SELECT 2 UNION ALL SELECT 3) slots
WHERE NOT EXISTS (SELECT 1 FROM market_business_inventory i
    WHERE i.country_code=c.code AND i.scope='banner_country' AND i.category_id=0 AND i.slot_no=slots.number);

ALTER TABLE market_premium_bookings ADD INDEX banner_booking_country (country_code,scope,status,ends_at,reserved_until);

CREATE TABLE IF NOT EXISTS market_banner_capacity_cases (
    booking_id BIGINT UNSIGNED PRIMARY KEY,
    opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    resolved_by INT NULL,
    resolution VARCHAR(1000) NOT NULL DEFAULT '',
    FOREIGN KEY (booking_id) REFERENCES market_premium_bookings(id) ON DELETE RESTRICT,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Capture old claims for manual review before deadlines can pass. No booking, invoice or creative is rewritten.
INSERT INTO market_banner_capacity_cases (booking_id)
WITH claims AS (
    SELECT b.id AS booking_id,b.country_code,
        CASE WHEN b.status='active' THEN b.starts_at ELSE UTC_TIMESTAMP() END AS starts_at,
        CASE WHEN b.status='active' THEN b.ends_at ELSE DATE_ADD(b.reserved_until, INTERVAL 52 DAY) END AS ends_at
    FROM market_premium_bookings b JOIN market_campaigns c ON c.id=b.campaign_id
    WHERE b.scope IN ('banner_home','banner_search','banner_monthly')
        AND ((b.status='active' AND b.ends_at>UTC_TIMESTAMP())
            OR (b.status IN ('reserved','awaiting_review','awaiting_start') AND b.reserved_until>UTC_TIMESTAMP()))
    UNION ALL
    SELECT NULL,c.country_code,c.scheduled_from,c.scheduled_until FROM market_campaigns c
    WHERE c.placement IN ('home','search') AND c.status='approved'
          AND (c.operator_owned=1 OR c.agreed_gross_cents=0) AND c.scheduled_until>UTC_TIMESTAMP()
), boundaries AS (
    SELECT DISTINCT country_code,GREATEST(starts_at,UTC_TIMESTAMP()) AS starts_at FROM claims
), overbooked AS (
    SELECT boundary.country_code,boundary.starts_at FROM boundaries boundary
    JOIN claims overlapping ON overlapping.country_code=boundary.country_code
        AND overlapping.starts_at<=boundary.starts_at AND overlapping.ends_at>boundary.starts_at
    GROUP BY boundary.country_code,boundary.starts_at HAVING COUNT(*)>3
)
SELECT DISTINCT c.booking_id FROM claims c JOIN overbooked o ON o.country_code=c.country_code
    AND c.starts_at<=o.starts_at AND c.ends_at>o.starts_at
WHERE c.booking_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM market_banner_capacity_cases x WHERE x.booking_id=c.booking_id);
