# artspay-wordpress

Internal WordPress instance used purely as a **test ground for WP plugins**, currently the
[ArtsPay WooCommerce plugin](https://github.com/ArtsPay/artspay-woocommerce-plugin). It has no
real visitors and holds no real data, so treat anything in it as disposable.

Built on [ServerlessWP](https://github.com/mitchmac/serverlesswp): WordPress running in Vercel
serverless functions, with the database as a SQLite file rather than a hosted MySQL server. Full
upstream docs (all deploy targets, both database options, plugin system) live in
[SERVERLESS.md](SERVERLESS.md). This file only covers what's specific to this instance.

## What's installed

| Component | Version |
|---|---|
| WordPress | 7.0.2 |
| PHP (bundled by `serverlesswp` 0.7.2) | 8.3.36 |
| WooCommerce | 11.1.0 |
| ArtsPay WooCommerce gateway (`artspay-woocommerce`) | 3.1.1 |
| SQLite Database Integration | 3.0.0-rc.7 |
| TiDB Compatibility | 1.0.2 |
| WP Offload Media Lite | 3.3.1 |

Everything above is committed to the repo. Update this table whenever you commit a new
version.

## Access

All deployments, production included, have been behind **Vercel Deployment Protection** while
the bundled PHP was behind on security patches. `serverlesswp` 0.7.2 bundles PHP 8.3.36, which is
current. Once it is deployed and a sandbox checkout works on a preview, the protection can come
off.

The PHP version comes from the `serverlesswp` package, not from Vercel, so it only changes when
the package does. Check it after any bump:

```sh
strings node_modules/serverlesswp/php-files/php | grep -m1 X-Powered-By
```

Note that `^0.x` ranges never move to a new minor version, so new `serverlesswp` releases have to
be bumped by hand.

## Why SQLite

The site has one tester at a time and no real traffic, so WooCommerce's writes never compete.
That keeps it within the case ServerlessWP recommends SQLite for, with no database to provision
or pay for around the clock. The deploy connects a private
[Vercel Blob](https://vercel.com/docs/vercel-blob) store and the SQLite file lives in it. Each git
branch gets its own database automatically, so preview deployments never touch the same data as
production.

Switch to MySQL if several people need to test at once, or if test runs start failing on
database locks.

## Deploying

1. Import the `stateempire/artspay-wordpress` repo in the [Vercel dashboard](https://vercel.com/new).
2. During import, add a **Vercel Blob** store (private access). Vercel wires up `BLOB_STORE_ID`
   automatically, so there are no credentials to copy.
3. Deploy. The first load of the live URL shows the WordPress install screen if no database is
   detected yet (see "Which database gets used" in [SERVERLESS.md](SERVERLESS.md#which-database-gets-used)).
4. Complete the WordPress install (site title, admin user and password). This creates the SQLite
   file in the connected Blob store.
5. Activate WooCommerce, then the ArtsPay gateway, and enter Fat Zebra **sandbox** credentials in
   WooCommerce > Settings > Payments. Never use live credentials here.

No other environment variables are required for the SQLite and Blob path. `S3_KEY_ID` and
`S3_ACCESS_KEY` are only needed if WP Offload Media Lite is enabled for real media uploads.

## Local development

```sh
npm install
npx vercel dev
```

Requires a linked Vercel account (`npx vercel link`) so `vercel dev` can read or create a Blob
store for local testing. Alternatively, point the `SQLITE_S3_*` env vars at a scratch bucket.

## Updating WordPress or a plugin

There is no plugin upload or auto-update step in this deployment model: ServerlessWP serves
whatever is committed.

- **ArtsPay gateway:** download the release ZIP from
  [GitHub Releases](https://github.com/ArtsPay/artspay-woocommerce-plugin/releases), replace
  `wp/wp-content/plugins/artspay-woocommerce` with its contents, and commit.
- **Other plugins:** replace the folder under `wp/wp-content/plugins/<plugin-name>` and commit.
- **WordPress core:** replace the core files under `wp/` (leave `wp-content` and `wp-config.php`
  alone) and commit.

Push to the branch to redeploy, then update the version table above.
