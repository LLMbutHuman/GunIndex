-- Autocomplete is an indexed prefix search of active public titles.
ALTER TABLE market_listings ADD KEY listing_title_prefix(status,title);
