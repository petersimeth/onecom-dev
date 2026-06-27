# Crawler ingestion API v1

Endpoint: `POST /shopsignal/api/ingest.php`

The request body is UTF-8 JSON and must use `Content-Type: application/json`.
Requests larger than the configured byte limit are rejected before parsing.

## Authentication headers

```text
X-ShopSignal-Key: home-scraper
X-ShopSignal-Timestamp: 1800000000
X-ShopSignal-Nonce: unique-random-value-at-least-16-characters
X-ShopSignal-Signature: lowercase-hex-hmac-sha256
```

The signature input is:

```text
shopsignal-ingest-v1\n
{timestamp}\n
{nonce}\n
{sha256-of-the-exact-request-body}
```

The signature is `HMAC-SHA256(signing_input, shared_secret)`. The server checks
the raw body before decoding JSON, rejects timestamps outside the configured
clock window, and stores each nonce so it cannot be replayed.

## Payload

```json
{
  "schema_version": 1,
  "batch_id": "spider-unique-id",
  "source": "shopify-spider-home",
  "sent_at": "2026-06-27T12:00:00Z",
  "stores": [
    {
      "domain": "example.com",
      "url": "https://example.com/",
      "final_url": "https://example.com/",
      "name": "Example Store",
      "description": "Public storefront description",
      "language": "en",
      "currency": "USD",
      "emails": ["hello@example.com"],
      "confidence": 90,
      "detection_signals": ["cdn_shopify"],
      "myshopify_domain": "example.myshopify.com",
      "first_seen_at": "2026-06-01 10:00:00",
      "last_seen_at": "2026-06-27 12:00:00",
      "technologies": [
        {"name": "Klaviyo", "category": "Email marketing", "short_code": "kl"}
      ],
      "products": [
        {"name": "Canvas Hat", "category": "Accessories", "price": 29.5, "currency": "USD", "url": "https://example.com/products/hat"}
      ],
      "signals": [
        {"type": "product", "title": "New product detected", "description": "Canvas Hat appeared in the public storefront catalog.", "occurred_at": "2026-06-27 12:00:00", "occurred_label": "Just detected"}
      ]
    }
  ]
}
```

Stores are upserted by normalized domain. Related records use stable source
keys. A completed `batch_id` with the same body is returned as a duplicate
success; using that ID for different content is rejected. A batch either commits
fully or rolls back fully, and omitted records never cause deletions.
