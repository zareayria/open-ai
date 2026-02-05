## 2025-05-24 - Optimized Product Search with WP_Query and Debouncing

**Learning:** In interactive search features using `WP_Query`, performance is often bottlenecked by `SQL_CALC_FOUND_ROWS` (used for pagination) and unnecessary metadata/term loading. On the frontend, frequent AJAX calls without debouncing or abortion lead to server strain and potential race conditions.

**Action:** Always use `'no_found_rows' => true` when pagination isn't needed. Disable meta and term caches if not required. Implement debouncing and use `jqXHR.abort()` on the frontend for search inputs.
