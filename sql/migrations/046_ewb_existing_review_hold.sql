-- Preserve listing records. A missing or later changed category label cannot clear an existing review hold.
WITH RECURSIVE ewb_categories (id) AS (
    SELECT id FROM market_categories WHERE ewb_required<>0
    UNION DISTINCT
    SELECT child.id FROM market_categories child JOIN ewb_categories parent ON parent.id=child.parent_id
)
UPDATE market_listings listing JOIN ewb_categories category ON category.id=listing.category_id
SET listing.ewb_required=1 WHERE listing.ewb_required=0;

UPDATE market_listings SET status='review' WHERE ewb_required<>0 AND status='active';
