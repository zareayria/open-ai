# Bolt's Performance Journal ⚡

## 2025-05-24 - Un-debounced AJAX search
**Learning:** The WooCommerce product search feature triggers an AJAX request on every `keyup` event once the input length reaches 3 characters. This leads to a high volume of redundant server requests during active typing, increasing server load and potentially causing race conditions where older requests resolve after newer ones.
**Action:** Implement a debounce utility and apply it to the search input, while also adding logic to abort obsolete requests.
