-- Sample Categories
INSERT INTO categories (name) VALUES
('Antibiotics'),
('Pain Relief'),
('Vitamins'),
('First Aid'),
('Skincare'),
('Baby Care'),
('Personal Care'),
('Medical Devices');

-- Sample Products
INSERT INTO products (name, description, price, stock, category_id, manufacturer, expiry_date, image_url) VALUES
-- Antibiotics
('Amoxicillin 500mg', 'Broad-spectrum antibiotic for bacterial infections', 150.00, 100, 1, 'Pfizer', '2025-12-31', 'img/products/amoxicillin.jpg'),
('Azithromycin 250mg', 'Macrolide antibiotic for respiratory infections', 200.00, 75, 1, 'GSK', '2025-10-31', 'img/products/azithromycin.jpg'),
('Ciprofloxacin 500mg', 'Fluoroquinolone antibiotic for various infections', 180.00, 50, 1, 'Bayer', '2025-09-30', 'img/products/ciprofloxacin.jpg'),

-- Pain Relief
('Paracetamol 500mg', 'Pain reliever and fever reducer', 50.00, 200, 2, 'GSK', '2025-12-31', 'img/products/paracetamol.jpg'),
('Ibuprofen 400mg', 'Non-steroidal anti-inflammatory drug', 80.00, 150, 2, 'Pfizer', '2025-11-30', 'img/products/ibuprofen.jpg'),
('Aspirin 100mg', 'Pain reliever and blood thinner', 45.00, 180, 2, 'Bayer', '2025-12-31', 'img/products/aspirin.jpg'),

-- Vitamins
('Vitamin C 1000mg', 'Immune system support and antioxidant', 120.00, 100, 3, 'Nature Made', '2025-12-31', 'img/products/vitamin-c.jpg'),
('Vitamin D3 1000IU', 'Bone health and immune support', 150.00, 80, 3, 'Nature Made', '2025-12-31', 'img/products/vitamin-d.jpg'),
('Multivitamin Complex', 'Complete daily vitamin supplement', 200.00, 60, 3, 'Centrum', '2025-12-31', 'img/products/multivitamin.jpg'),

-- First Aid
('Band-Aid Assorted', 'Various sizes of adhesive bandages', 75.00, 100, 4, 'Johnson & Johnson', '2025-12-31', 'img/products/bandaid.jpg'),
('Antiseptic Solution', 'Wound cleaning and disinfection', 120.00, 50, 4, 'Betadine', '2025-12-31', 'img/products/antiseptic.jpg'),
('First Aid Kit', 'Complete emergency medical kit', 500.00, 30, 4, 'Johnson & Johnson', '2025-12-31', 'img/products/firstaid-kit.jpg'),

-- Skincare
('Moisturizing Cream', 'Hydrating facial moisturizer', 250.00, 80, 5, 'Cetaphil', '2025-12-31', 'img/products/moisturizer.jpg'),
('Sunscreen SPF 50', 'Broad spectrum sun protection', 300.00, 60, 5, 'Neutrogena', '2025-12-31', 'img/products/sunscreen.jpg'),
('Acne Treatment', 'Benzoyl peroxide acne medication', 180.00, 90, 5, 'Clean & Clear', '2025-12-31', 'img/products/acne-treatment.jpg'),

-- Baby Care
('Baby Shampoo', 'Gentle cleansing for baby hair', 150.00, 70, 6, 'Johnson & Johnson', '2025-12-31', 'img/products/baby-shampoo.jpg'),
('Diaper Rash Cream', 'Zinc oxide diaper rash treatment', 120.00, 85, 6, 'Desitin', '2025-12-31', 'img/products/diaper-cream.jpg'),
('Baby Lotion', 'Moisturizing baby lotion', 130.00, 65, 6, 'Aveeno', '2025-12-31', 'img/products/baby-lotion.jpg'),

-- Personal Care
('Toothpaste', 'Fluoride toothpaste for cavity protection', 80.00, 120, 7, 'Colgate', '2025-12-31', 'img/products/toothpaste.jpg'),
('Mouthwash', 'Antiseptic mouth rinse', 100.00, 90, 7, 'Listerine', '2025-12-31', 'img/products/mouthwash.jpg'),
('Hand Sanitizer', 'Alcohol-based hand sanitizer', 60.00, 150, 7, 'Purell', '2025-12-31', 'img/products/hand-sanitizer.jpg'),

-- Medical Devices
('Digital Thermometer', 'Fast and accurate temperature reading', 250.00, 40, 8, 'Omron', '2025-12-31', 'img/products/thermometer.jpg'),
('Blood Pressure Monitor', 'Automatic BP monitoring device', 1500.00, 25, 8, 'Omron', '2025-12-31', 'img/products/bp-monitor.jpg'),
('Pulse Oximeter', 'Blood oxygen level monitor', 800.00, 30, 8, 'ChoiceMMed', '2025-12-31', 'img/products/pulse-oximeter.jpg');

-- Sample User Accounts (with hashed passwords)
INSERT INTO accounts (username, email, password, role) VALUES
('admin', 'admin@rhinelab.com', '$2y$10$8K1p/a0dR1xqM8K1p/a0dR1xqM8K1p/a0dR1xqM', 'Admin'),
('john_doe', 'john@example.com', '$2y$10$8K1p/a0dR1xqM8K1p/a0dR1xqM8K1p/a0dR1xqM', 'User'),
('jane_smith', 'jane@example.com', '$2y$10$8K1p/a0dR1xqM8K1p/a0dR1xqM8K1p/a0dR1xqM', 'User'),
('mike_wilson', 'mike@example.com', '$2y$10$8K1p/a0dR1xqM8K1p/a0dR1xqM8K1p/a0dR1xqM', 'User');

-- Sample User Profiles
INSERT INTO user_profiles (account_id, address, phone_number) VALUES
(2, '123 Main St, Manila, Philippines', '+63 912 345 6789'),
(3, '456 Park Ave, Quezon City, Philippines', '+63 923 456 7890'),
(4, '789 Oak St, Makati, Philippines', '+63 934 567 8901');

-- Sample Orders
INSERT INTO orders (account_id, order_date, total_amount, status, shipping_address, phone_number) VALUES
(2, '2024-03-01 10:30:00', 350.00, 'Delivered', '123 Main St, Manila, Philippines', '+63 912 345 6789'),
(3, '2024-03-02 14:15:00', 500.00, 'Processing', '456 Park Ave, Quezon City, Philippines', '+63 923 456 7890'),
(4, '2024-03-03 09:45:00', 750.00, 'Pending', '789 Oak St, Makati, Philippines', '+63 934 567 8901');

-- Sample Order Items
INSERT INTO order_items (order_id, product_id, quantity, price) VALUES
(1, 1, 2, 150.00),
(1, 4, 1, 50.00),
(2, 7, 2, 120.00),
(2, 8, 1, 150.00),
(2, 9, 1, 200.00),
(3, 13, 2, 250.00),
(3, 14, 1, 300.00),
(3, 15, 1, 180.00);

-- Sample Cart Items
INSERT INTO cart (account_id, product_id, quantity) VALUES
(2, 2, 1),
(2, 5, 2),
(3, 10, 1),
(3, 11, 1),
(4, 16, 1),
(4, 17, 2); 