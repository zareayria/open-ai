## 2026-02-02 - [Product Search Optimization]
**Learning:** Combining frontend debouncing/aborting with backend `no_found_rows` provides a comprehensive optimization for live search features in WordPress plugins. Debouncing reduces request frequency, aborting handles race conditions and prevents unnecessary server processing, and `no_found_rows` speeds up the database query itself.
**Action:** Always check for `WP_Query` calls in AJAX handlers and apply `no_found_rows => true` if pagination is not required. Ensure `jqXHR.abort()` is used on the frontend to manage concurrent requests.
