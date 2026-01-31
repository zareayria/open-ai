# Bolt's Journal - Performance Learnings

## 2025-05-14 - Debouncing AJAX Search
**Learning:** In jQuery-based WordPress plugins, AJAX searches are often triggered on `keyup` without debouncing. This can lead to a surge of requests that overwhelm the server and cause a sluggish UI.
**Action:** Always implement a `debounce` helper and use the `input` event for search fields to ensure efficient server communication.
