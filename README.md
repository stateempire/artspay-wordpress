# artspay-wordpress

Internal WordPress instance used purely as a **test ground for WP plugins** (starting with the
[artspay-woocommerce-plugin](https://github.com/stateempire/artspay-woocommerce-plugin)). It has no
real visitors and holds no real data — treat anything in it as disposable.

Built on [ServerlessWP](https://github.com/mitchmac/serverlesswp): WordPress running in Vercel
serverless functions, with the database as a SQLite file rather than a hosted MySQL server. Full
upstream docs (all deploy targets, both database options, plugin system) live in
[SERVERLESS.md](SERVERLESS.md) — this file only covers what's specific to this instance.

## Why SQLite

This site is single-editor, low/no-traffic, and not running WooCommerce or anything else with
concurrent writes — exactly the case ServerlessWP recommends SQLite for. It needs no database to
provision or pay for 24/7: the deploy connects a private [Vercel Blob](https://vercel.com/docs/vercel-blob)
store and the SQLite file lives in it. Each git branch gets its own database automatically, so
preview deployments never touch the same data as production. MySQL would only be worth it if this
became a multi-editor or ecommerce site.

## Deploying

1. Import the `stateempire/artspay-wordpress` repo in the [Vercel dashboard](https://vercel.com/new).
2. During import, add a **Vercel Blob** store (private access). Vercel wires up `BLOB_STORE_ID`
   automatically — no credentials to copy.
3. Deploy. First load of the live URL shows the WordPress install screen if no database is
   detected yet (see "Which database gets used" in [SERVERLESS.md](SERVERLESS.md#which-database-gets-used)).
4. Complete the WordPress install (site title, admin user/password) — this is what creates the
   SQLite file in the connected Blob store.

No other environment variables are required for the SQLite + Blob path. `S3_KEY_ID` /
`S3_ACCESS_KEY` are only needed if the WP Offload Media Lite plugin is enabled for real media
uploads.

## Local development

```sh
npm install
npx vercel dev
```

Requires a Vercel account linked (`npx vercel link`) so `vercel dev` can read/create a Blob store
for local testing, or point `SQLITE_S3_*` env vars at a scratch bucket instead.

## Adding/testing a plugin

Drop the plugin into `wp/wp-content/plugins/<plugin-name>` and commit it — ServerlessWP serves
whatever's committed, there's no plugin upload step in this deployment model. Redeploy (push to
the branch) to pick it up.
