-- Product reviews table
CREATE TABLE `product_reviews` (
  `review_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (rating >= 1 AND rating <= 5),
  `review_text` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`review_id`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`),
  FOREIGN KEY (`account_id`) REFERENCES `accounts`(`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; 