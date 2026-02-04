## 2025-05-14 - [Initial Optimization: Product Search]
**Learning:** Live search features in WordPress often suffer from two main performance bottlenecks: high-frequency AJAX calls from the frontend and inefficient database queries that calculate total row counts even when pagination isn't needed. Combining 'no_found_rows' => true with frontend debouncing significantly reduces both server and database load.
**Action:** Always implement debouncing for search inputs and use 'no_found_rows' for non-paginated WP_Query results.
