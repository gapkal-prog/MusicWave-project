# Catalog filters and sorting

The `music-wave/catalog-filters` block is used by the release archive template. It submits a standard GET request to the `mw_release` archive and uses the registered WordPress taxonomy query variables: `mw_artist`, `mw_genre`, `mw_mood`, and `mw_release_type`.

The optional `mw_sort` query argument is strictly allow-listed. Valid values are `latest`, `oldest`, `title_asc`, and `title_desc`; invalid or absent values safely fall back to `latest`. Sorting is applied only to the public primary `mw_release` archive, using native WordPress `date` or `title` ordering. It never exposes, sorts by, or queries private access metadata.

The companion `music-wave/catalog-results` block shows the archive result count and removable active-filter links. It preserves only known catalog query values when constructing a link, so tracking parameters or arbitrary query variables are not propagated.

The filter block reads at most 50 non-empty terms per taxonomy and does not run custom post queries. WordPress therefore owns taxonomy parsing, pagination, canonical URLs, and cache compatibility. Filters deliberately use term slugs rather than IDs or arbitrary meta values.
