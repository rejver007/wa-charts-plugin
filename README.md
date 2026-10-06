# WebAula Charts

Requires WordPress 6.6+ and PHP 8.1+. Charts as a custom post type, shown with a shortcode or a block. Uses Chart.js (MIT, bundled). Replaces Graphina.

## Use

1. **Charts → Add new**: choose a type, enter the data (or paste from Excel) and the colours, and publish.
2. Copy the shortcode, e.g. `[wa_chart id="123"]`, into any page (Elementor: the Shortcode widget), or use the **Chart** block.
3. Optional shortcode attributes: `height="300"`, `layout="left|right|top"`, `legend="bottom|right|none"`, `class="my-class"`.

## Styling per site

Set any of these on `.wa-chart` in the theme CSS: `--wa-chart-font`, `--wa-chart-heading-size`, `--wa-chart-heading-color`, `--wa-chart-subheading-size`, `--wa-chart-subheading-color`, `--wa-chart-legend-columns`, `--wa-chart-gap`, `--wa-chart-height`.

## Filters

| Filter | Use |
|---|---|
| `wa_charts_default_palette` | Site colour palette (array of hex colours). |
| `wa_charts_library_options` | Extra Chart.js `options`, deep-merged per chart (`$options, $config, $post_id`). |
| `wa_charts_render_html` | Final HTML (`$html, $config, $post_id`). |
| `wa_charts_chart_types` | Remove or relabel chart types. |

## Updates

New versions are published as GitHub Releases of this public repository. Installed sites check for them automatically, so updates appear under **Dashboard → Updates** like any other plugin. No setup is needed.

Optional: on a server that runs many sites, GitHub's anonymous API limit (60 requests per hour per IP address) can be reached. In that case, add a fine-grained, read-only GitHub token to that site's `wp-config.php`:

```php
define( 'WA_CHARTS_GITHUB_TOKEN', '<token>' );
```

Never commit the token.

## Migrating from Graphina

```bash
wp wa-charts import-graphina --dry-run
wp wa-charts import-graphina
wp wa-charts import-graphina --rollback   # if needed
```

Notes:

- Rollback overwrites any Elementor edits made after the import.
- Rollback trashes the charts the import created; that is a permanent delete if `EMPTY_TRASH_DAYS` is 0.
- Always pass `--post=<id>` with a value; a bare `--post` is rejected.
- Run the commands with `--user=<admin-login>` so the charts get an author.

Move the custom CSS listed in the report into the theme, check the pages, then delete Graphina.

## Development

Requires Docker and Node 22 (pinned via Volta in package.json; Node 26 breaks wp-env). WordPress 6.6 or newer.

```bash
npm install
npx wp-env start            # http://localhost:8888
npm run composer -- install
npm run test:php && npm run test:js
npm run lint:php && npm run lint:js
npm run build               # block; commit build/
npm run vendor              # after changing chart.js / datalabels versions; update the constants in wa-charts.php
```

## Releasing

1. Bump `Version:` and `WA_CHARTS_VERSION` in `wa-charts.php`, and `version` in `package.json`.
2. Commit, then `git tag vX.Y.Z && git push --tags`.
3. GitHub Actions runs CI, builds `wa-charts.zip` and publishes the release.
