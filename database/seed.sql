START TRANSACTION;

INSERT INTO stores (
    name, domain, category, shopify_plan, growth_signal, growth_percent,
    estimated_monthly_revenue, monthly_traffic, monthly_orders, conversion_rate,
    average_price, product_count, founded_year, headquarters, country,
    employee_range, public_email, public_phone, store_language, currency,
    social_total, instagram_followers, tiktok_followers, facebook_followers,
    logo_letter, logo_class, created_at, updated_at
) VALUES
('Allbirds', 'allbirds.com', 'Footwear', 'Shopify Plus', 'High', 28.40, 8200000, 2400000, 94300, 3.10, 86, 278, 2016, 'San Francisco, CA', 'United States', '350–500', 'help@allbirds.com', '+1 888 963 8944', 'English', 'USD', 1223000, 482000, 211000, 530000, 'A', 'logo-allbirds', NOW() - INTERVAL 30 DAY, NOW() - INTERVAL 2 HOUR),
('Gymshark', 'gymshark.com', 'Apparel', 'Shopify Plus', 'High', 23.10, 12900000, 5100000, 238000, 3.70, 54, 1842, 2012, 'Solihull, England', 'United Kingdom', '900–1,000', 'support@gymshark.com', '+44 121 728 2828', 'English', 'USD / GBP', 14400000, 7100000, 5400000, 1900000, 'G', 'logo-gymshark', NOW() - INTERVAL 4 DAY, NOW() - INTERVAL 4 HOUR),
('Brooklinen', 'brooklinen.com', 'Home & Living', 'Shopify Plus', 'High', 18.60, 4800000, 890000, 40700, 2.80, 118, 412, 2014, 'Brooklyn, NY', 'United States', '150–250', 'hello@brooklinen.com', '+1 646 798 7447', 'English', 'USD', 704000, 414000, 83000, 207000, 'B', 'logo-brooklinen', NOW() - INTERVAL 5 DAY, NOW() - INTERVAL 1 DAY),
('Glossier', 'glossier.com', 'Beauty', 'Shopify Plus', 'High', 15.20, 7100000, 1800000, 229000, 4.20, 31, 186, 2014, 'New York, NY', 'United States', '200–350', 'gteam@glossier.com', '+1 855 929 2179', 'English', 'USD', 4112000, 3100000, 840000, 172000, 'G', 'logo-glossier', NOW() - INTERVAL 60 DAY, NOW() - INTERVAL 5 HOUR),
('Beardbrand', 'beardbrand.com', 'Personal Care', 'Shopify Plus', 'Medium', 9.80, 910000, 340000, 24100, 2.60, 38, 94, 2012, 'Austin, TX', 'United States', '20–50', 'support@beardbrand.com', '+1 844 662 3273', 'English', 'USD', 429000, 190000, 94000, 145000, 'B', 'logo-beardbrand', NOW() - INTERVAL 120 DAY, NOW() - INTERVAL 3 DAY),
('Kylie Cosmetics', 'kyliecosmetics.com', 'Beauty', 'Shopify Plus', 'Medium', 8.30, 3600000, 1200000, 124000, 3.40, 29, 324, 2015, 'Los Angeles, CA', 'United States', '100–200', 'customerservice@kyliecosmetics.com', '+1 833 545 9543', 'English', 'USD', 31200000, 25700000, 3800000, 1700000, 'K', 'logo-kylie', NOW() - INTERVAL 150 DAY, NOW() - INTERVAL 1 DAY)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    category = VALUES(category),
    shopify_plan = VALUES(shopify_plan),
    growth_signal = VALUES(growth_signal),
    growth_percent = VALUES(growth_percent),
    estimated_monthly_revenue = VALUES(estimated_monthly_revenue),
    monthly_traffic = VALUES(monthly_traffic),
    monthly_orders = VALUES(monthly_orders),
    conversion_rate = VALUES(conversion_rate),
    average_price = VALUES(average_price),
    product_count = VALUES(product_count),
    headquarters = VALUES(headquarters),
    country = VALUES(country),
    employee_range = VALUES(employee_range),
    public_email = VALUES(public_email),
    public_phone = VALUES(public_phone),
    social_total = VALUES(social_total),
    instagram_followers = VALUES(instagram_followers),
    tiktok_followers = VALUES(tiktok_followers),
    facebook_followers = VALUES(facebook_followers),
    updated_at = VALUES(updated_at);

SET @allbirds = (SELECT id FROM stores WHERE domain = 'allbirds.com');
SET @gymshark = (SELECT id FROM stores WHERE domain = 'gymshark.com');
SET @brooklinen = (SELECT id FROM stores WHERE domain = 'brooklinen.com');
SET @glossier = (SELECT id FROM stores WHERE domain = 'glossier.com');
SET @beardbrand = (SELECT id FROM stores WHERE domain = 'beardbrand.com');
SET @kylie = (SELECT id FROM stores WHERE domain = 'kyliecosmetics.com');

DELETE FROM store_technologies WHERE store_id IN (@allbirds, @gymshark, @brooklinen, @glossier, @beardbrand, @kylie);
INSERT INTO store_technologies (store_id, technology_name, category, short_code, detected_at, last_seen_at, monthly_cost) VALUES
(@allbirds, 'Klaviyo', 'Email marketing', 'kl', '2021-05-01', CURDATE(), 350),
(@allbirds, 'Yotpo', 'Reviews & loyalty', 'yo', '2020-09-01', CURDATE(), 249),
(@allbirds, 'Gorgias', 'Customer support', 'go', '2022-01-01', CURDATE(), 300),
(@allbirds, 'Nosto', 'Personalization', 'no', '2023-03-01', CURDATE(), 500),
(@gymshark, 'Klaviyo', 'Email marketing', 'kl', '2020-02-01', CURDATE(), 500),
(@gymshark, 'Recharge', 'Subscriptions', 'rc', '2022-06-01', CURDATE(), 499),
(@gymshark, 'Gorgias', 'Customer support', 'go', '2021-10-01', CURDATE(), 300),
(@gymshark, 'Algolia', 'Site search', 'al', '2023-04-01', CURDATE(), 700),
(@brooklinen, 'Klaviyo', 'Email marketing', 'kl', '2019-08-01', CURDATE(), 350),
(@brooklinen, 'Okendo', 'Reviews', 'ok', '2021-11-01', CURDATE(), 299),
(@brooklinen, 'Gorgias', 'Customer support', 'go', '2020-04-01', CURDATE(), 300),
(@brooklinen, 'Rebuy', 'Upsells', 'rb', '2023-07-01', CURDATE(), 199),
(@glossier, 'Klaviyo', 'Email marketing', 'kl', '2020-03-01', CURDATE(), 350),
(@glossier, 'Yotpo', 'Reviews & loyalty', 'yo', '2021-07-01', CURDATE(), 249),
(@glossier, 'Nosto', 'Personalization', 'no', '2022-02-01', CURDATE(), 500),
(@glossier, 'Loop Returns', 'Returns', 'lr', '2022-09-01', CURDATE(), 149),
(@beardbrand, 'Klaviyo', 'Email marketing', 'kl', '2018-06-01', CURDATE(), 150),
(@beardbrand, 'Recharge', 'Subscriptions', 'rc', '2020-01-01', CURDATE(), 199),
(@beardbrand, 'Gorgias', 'Customer support', 'go', '2021-08-01', CURDATE(), 100),
(@beardbrand, 'Stamped', 'Reviews', 'st', '2019-05-01', CURDATE(), 79),
(@kylie, 'Klaviyo', 'Email marketing', 'kl', '2020-11-01', CURDATE(), 350),
(@kylie, 'Yotpo', 'Reviews & loyalty', 'yo', '2021-02-01', CURDATE(), 249),
(@kylie, 'Gorgias', 'Customer support', 'go', '2022-08-01', CURDATE(), 300),
(@kylie, 'Afterpay', 'Payments', 'ap', '2020-06-01', CURDATE(), 0);

DELETE FROM products WHERE store_id IN (@allbirds, @gymshark, @brooklinen, @glossier, @beardbrand, @kylie);
INSERT INTO products (store_id, name, category, price, currency_symbol, is_top_product, first_seen_at, last_seen_at) VALUES
(@allbirds, 'Tree Runner', 'Footwear', 98, '$', TRUE, '2021-01-01', CURDATE()),
(@allbirds, 'Wool Runner', 'Footwear', 110, '$', TRUE, '2020-01-01', CURDATE()),
(@allbirds, 'Tree Dasher 2', 'Footwear', 135, '$', TRUE, '2022-01-01', CURDATE()),
(@gymshark, 'Arrival 5” Shorts', 'Menswear', 30, '$', TRUE, '2022-01-01', CURDATE()),
(@gymshark, 'Vital Seamless Leggings', 'Womenswear', 60, '$', TRUE, '2021-01-01', CURDATE()),
(@gymshark, 'Crest Hoodie', 'Menswear', 50, '$', TRUE, '2021-01-01', CURDATE()),
(@brooklinen, 'Luxe Core Sheet Set', 'Bedding', 189, '$', TRUE, '2020-01-01', CURDATE()),
(@brooklinen, 'Super-Plush Bath Towels', 'Bath', 89, '$', TRUE, '2021-01-01', CURDATE()),
(@brooklinen, 'Down Comforter', 'Bedding', 299, '$', TRUE, '2020-01-01', CURDATE()),
(@glossier, 'Boy Brow', 'Makeup', 18, '$', TRUE, '2020-01-01', CURDATE()),
(@glossier, 'Glossier You', 'Fragrance', 78, '$', TRUE, '2021-01-01', CURDATE()),
(@glossier, 'Cloud Paint', 'Makeup', 22, '$', TRUE, '2020-01-01', CURDATE()),
(@beardbrand, 'Utility Beard Oil', 'Grooming', 36, '$', TRUE, '2020-01-01', CURDATE()),
(@beardbrand, 'Sea Salt Spray', 'Hair', 22, '$', TRUE, '2020-01-01', CURDATE()),
(@beardbrand, 'Utility Balm', 'Grooming', 42, '$', TRUE, '2020-01-01', CURDATE()),
(@kylie, 'Lip Kit', 'Makeup', 35, '$', TRUE, '2020-01-01', CURDATE()),
(@kylie, 'Kylash Mascara', 'Makeup', 24, '$', TRUE, '2023-01-01', CURDATE()),
(@kylie, 'Cosmic Eau de Parfum', 'Fragrance', 48, '$', TRUE, '2024-01-01', CURDATE());

DELETE FROM store_signals WHERE store_id IN (@allbirds, @gymshark, @brooklinen, @glossier, @beardbrand, @kylie);
INSERT INTO store_signals (store_id, signal_type, title, description, occurred_at, occurred_label) VALUES
(@allbirds, 'traffic', 'Traffic spike', 'Organic traffic increased 18% week over week.', NOW() - INTERVAL 2 DAY, '2 days ago'),
(@allbirds, 'technology', 'New app detected', 'Nosto personalization was added to the storefront.', NOW() - INTERVAL 8 DAY, '8 days ago'),
(@allbirds, 'catalog', 'Catalog growth', '34 new products were published.', NOW() - INTERVAL 21 DAY, '21 days ago'),
(@gymshark, 'advertising', 'Paid media growth', 'Estimated ad spend rose 24% in the last 30 days.', NOW() - INTERVAL 1 DAY, 'Yesterday'),
(@gymshark, 'international', 'International expansion', 'New localized storefront detected for South Korea.', NOW() - INTERVAL 6 DAY, '6 days ago'),
(@gymshark, 'hiring', 'Hiring signal', '12 new ecommerce roles were posted.', NOW() - INTERVAL 14 DAY, '14 days ago'),
(@brooklinen, 'product', 'Best-seller velocity', 'Top bedding products gained 16% in review velocity.', NOW() - INTERVAL 3 DAY, '3 days ago'),
(@brooklinen, 'technology', 'New technology', 'Rebuy was added for post-purchase offers.', NOW() - INTERVAL 11 DAY, '11 days ago'),
(@brooklinen, 'promotion', 'Promotion detected', 'A sitewide seasonal campaign went live.', NOW() - INTERVAL 26 DAY, '26 days ago'),
(@glossier, 'product', 'Product launch', 'A new Cloud Paint shade collection was published.', NOW(), 'Today'),
(@glossier, 'traffic', 'Traffic acceleration', 'Direct traffic increased 22% month over month.', NOW() - INTERVAL 5 DAY, '5 days ago'),
(@glossier, 'technology', 'Technology change', 'Checkout personalization scripts were updated.', NOW() - INTERVAL 18 DAY, '18 days ago'),
(@beardbrand, 'subscription', 'Subscription growth', 'Subscription messaging became more prominent onsite.', NOW() - INTERVAL 4 DAY, '4 days ago'),
(@beardbrand, 'catalog', 'Catalog update', 'Six product bundles were refreshed.', NOW() - INTERVAL 15 DAY, '15 days ago'),
(@beardbrand, 'email', 'Email activity', 'Campaign frequency increased by 13%.', NOW() - INTERVAL 29 DAY, '29 days ago'),
(@kylie, 'social', 'Social momentum', 'TikTok mentions increased 31% this week.', NOW() - INTERVAL 1 DAY, 'Yesterday'),
(@kylie, 'product', 'Product launch', 'A limited lip collection was added.', NOW() - INTERVAL 9 DAY, '9 days ago'),
(@kylie, 'promotion', 'Promotional change', 'Free shipping threshold decreased.', NOW() - INTERVAL 23 DAY, '23 days ago');

COMMIT;
