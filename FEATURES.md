# Selected feature coverage — v1.1.0

The 21 selected numbers refer to the feature list discussed with the user. This is an independent implementation in the Nilwears plugin.

| Number | Capability | Implementation / limits |
|---|---|---|
| 1 | Excel-style product table | Inline price, stock, purchase cost and expandable product information; parents and variations remain separate. |
| 2 | Bulk product editing | Selection, current page and all filtered products; immutable preview jobs, history and conflict-aware undo. XLSX/CSV handles batches of product information. |
| 3 | Advanced filters | Text/exact SKU/ID, category, brand, type, publication status, stock status and price range. Parent price filters follow WooCommerce's stored parent price. |
| 4 | Brand filters | Core `product_brand`, or existing `pwb-brand` / `yith_product_brand` taxonomy. Brands must already exist. |
| 5 | Last-modified sorting | Default admin sort; ID and name alternatives. |
| 6 | Quick product creation | Simple or variable; draft/published; custom attribute combinations, up to 100 variations, SKU and retry protection. |
| 9 | Bulk price and stock | Fixed/percentage/set/round price rules and stock previews; shared parent stock and independent variation stock respected. |
| 10 | Automated pricing | Once/daily/weekly cost-based WP-Cron rules; bounded batches and recorded jobs. Traffic or a server cron is needed. |
| 11 | Rounding | Up/down/nearest, configurable step and ending; the original 10% + ending 9000 example remains supported. |
| 12 | Purchase cost | Per-product/per-variation cost metadata, editable in the grid or file; blank means unknown, zero is valid. |
| 13 | Profit and margin reports | Indicative inventory profit/margin and export; historical order-line gross profit based on immutable captured cost, net of refunds. Unknown historical costs remain unknown. |
| 15 | XLSX / CSV | Product and report export, values-only import; bounded size/rows, formula protection, numeric report cells and text SKU cells. |
| 16 | Column mapping / file roundtrip | Map input headers, match ID/SKU, preserve version conflict checks, preview existing edits. New simple/variable rows become drafts; create new variation combinations with the variable-product form. |
| 17 | Image management / optimization | Page/selection bulk actions; main JPG/PNG/WebP optimization with size/quality controls. Original files remain; thumbnail files are copied rather than recompressed. |
| 18 | Real file rename | Rename the active main image and generated size copies, update attachment metadata, retain old URLs. |
| 19 | Bulk ALT | Templates using title, ID or filename; preview and per-image version checks. |
| 20 | Unused-image candidates | Check known post/meta/options references, recheck before reversible archive, restore from archive. External/custom references cannot be ruled out; permanent deletion/disk reclamation is not automatic. |
| 27 | Customer analysis | Purchase count, spend, recency and purchased-product filters, period-based loyalty and exports. Paid/refunded orders, mixed currency excluded and counted separately. |
| 48 | White labeling | Plugin/menu/header title, HTTPS logo and accent; plugin identity and update slug stay stable. |
| 49 | HPOS | Compatibility declaration, WooCommerce CRUD order access and disposable HPOS-enabled integration tests. |
| 50 | Dashboard updates | Private GitHub release updater; server-side repository read token required. Metadata caching, safe redirect handling and digest checks. |

See [README.md](README.md) for use and [CONTRIBUTING.md](CONTRIBUTING.md) for validation evidence and reproducible tests. This release updates the repository and installation package; it does not install itself on the live store.
