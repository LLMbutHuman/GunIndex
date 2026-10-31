-- Correct only exact, known initial seed mojibake; preserve edited category names.
UPDATE market_categories SET name='Optik & Ferngläser' WHERE slug='optik' AND HEX(name)='4F7074696B2026204665726E676CC383C2A4736572';
UPDATE market_categories SET name='Bücher & Medien' WHERE slug='buecher-medien' AND HEX(name)='42C383C2BC636865722026204D656469656E';
UPDATE market_categories SET name='Musik & Kreativität' WHERE slug='musik' AND HEX(name)='4D7573696B2026204B7265617469766974C383C2A474';
UPDATE market_categories SET name='Sammlerzubehör' WHERE slug='sammlerzubehoer' AND HEX(name)='53616D6D6C65727A75626568C383C2B672';
