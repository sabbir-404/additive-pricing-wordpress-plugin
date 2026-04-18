# LE Additive Variation Pricing

A WooCommerce plugin that lets you price each attribute value independently. The final price is calculated as:

```
Final Price = (Optional Base Price) + Sum of all selected attribute value prices
```

Enable it per product — existing WooCommerce variation pricing is completely untouched for all other products.

---

## Features

- **Per-product opt-in** — enable additive pricing on individual variable products without affecting the rest of your store.
- **Optional base price** — set a fixed regular and/or sale base price added to every combination.
- **Per-attribute-value pricing** — assign a regular price and an optional sale price to every value of every variation attribute (e.g. Color: Red = $10, Blue = $15; Size: S = $0, XL = $5).
- **Dynamic price display** — the product page updates the displayed price in real time as the customer selects attributes, using the same currency format configured in WooCommerce.
- **Price range on shop listings** — shows a calculated min–max range (with sale markup when applicable) on archive/shop pages.
- **Cart & order accuracy** — the computed price is passed into the cart and saved to the order line item, so it survives product price changes after purchase.
- **Sale price support** — sale prices are optional at both the base level and the per-value level; strikethrough markup is applied automatically.

---

## Requirements

| Requirement | Minimum version |
|---|---|
| WordPress | 6.0 |
| PHP | 7.4 |
| WooCommerce | any current release |

---

## Installation

1. Download or clone this repository.
2. Copy (or upload as a ZIP) the plugin folder to `wp-content/plugins/`.
3. In the WordPress admin go to **Plugins → Installed Plugins** and activate **LE Additive Variation Pricing**.
4. WooCommerce must be installed and active — the plugin will display an admin notice and deactivate gracefully if it is not.

---

## Usage

### 1. Open a Variable Product

Navigate to **Products → Add/Edit Product** and set the product type to **Variable**. Add your attributes and make sure at least one attribute is marked *Used for variations*.

### 2. Configure Additive Pricing

Scroll down to the **💰 LE Additive Variation Pricing** meta box.

| Setting | Description |
|---|---|
| **Enable Additive Pricing** | Checkbox to opt this product in. When unchecked, WooCommerce's default pricing applies. |
| **Base Price — Regular** | A flat amount added to every combination (optional). |
| **Base Price — Sale** | An optional discounted base amount. Leave blank for no base sale. |
| **Attribute value — Regular** | The amount added when this value is selected. |
| **Attribute value — Sale** | An optional discounted amount for this value. |

### 3. Price calculation example

| Component | Regular | Sale |
|---|---|---|
| Base price | $20 | $15 |
| Color: Blue | $10 | — |
| Size: XL | $5 | $4 |
| **Total** | **$35** | **$29** |

### 4. Save the product

Click **Update / Publish**. The product page and shop listing will immediately reflect the new additive pricing.

---

## How It Works (Technical Overview)

```
le-additive-pricing.php      — Plugin bootstrap, constants, WooCommerce dependency check
includes/
  class-admin.php            — Meta box registration, rendering, and saving (nonce-protected)
  class-frontend.php         — Enqueues frontend JS, localises price data, overrides price HTML
  class-cart.php             — Injects computed price into cart item data and order meta
assets/
  admin.css                  — Meta box styles
  admin.js                   — Admin UI interactions
  frontend.js                — Real-time price calculation on the product page
```

### Data storage

All configuration is stored in a single post meta entry (`_le_additive_pricing`) on the product. The structure is:

```json
{
  "enabled": true,
  "base_price": "20.00",
  "base_sale": "15.00",
  "prices": {
    "pa_color": {
      "blue":  { "regular": "10.00", "sale": "" },
      "red":   { "regular": "8.00",  "sale": "6.00" }
    },
    "pa_size": {
      "s":  { "regular": "0.00", "sale": "" },
      "xl": { "regular": "5.00", "sale": "4.00" }
    }
  }
}
```

---

## License

Licensed under the [GNU General Public License v2 or later](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html).

---

## Author

**Leading Edge**  
[https://leadingedge.com.bd](https://leadingedge.com.bd)
