## 2025-05-24 - [Optimizing Search-as-you-type in WordPress]
**Learning:** For features like live search, implementing both frontend debouncing and backend query optimization (`no_found_rows => true`) provides a compounded performance benefit.
- Debouncing reduces the number of server hits.
- `jqXHR.abort()` prevents race conditions and redundant processing.
- `no_found_rows` skips expensive row counting in MySQL when pagination isn't needed.

**Action:** Always check if `WP_Query` needs pagination. If not, set `'no_found_rows' => true`. Always debounce high-frequency input events that trigger network requests.
