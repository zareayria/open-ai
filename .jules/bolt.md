# Bolt's Performance Journal

## 2025-05-15 - Optimizing Product Search
**Learning:** Combining frontend debouncing and backend query optimization provides a massive performance boost for "search-as-you-type" features. Debouncing (300ms) reduces the number of requests by up to 80% during typing. On the backend, `no_found_rows => true` in `WP_Query` significantly speeds up the database response by skipping the total count calculation, which is unnecessary for autocomplete/search suggestions.
**Action:** Always implement both client-side (debounce/abort) and server-side (query params) optimizations for any AJAX-driven search or live-update feature.
