# TbIndexNow

**IndexNow Integration**  
Queue URLs on product add/update/delete and submit them in bulk via cron using the [IndexNow protocol](https://www.indexnow.org/).

---

## Features

- Automatically queue product page URLs on add, update or delete.
- Bulk-submit queued URLs via cron to reduce API calls.
- Multi-shop & multi-language aware (queues per shop and language).
- Records submission history (status code & response).
- HelperForm-based back-office configuration.

---

## Requirements

- PrestaShop / Thirty Bees **1.6.x**  
- PHP **5.6+** with cURL extension  
- Write permissions on your shop root (for `<KEY>.txt`)

---

## Installation

1. Copy the `tbindexnow` folder into your shop’s `/modules/` directory.  
2. In Back Office, go to **Modules** → **Install a module**, find **IndexNow Integration**, and click **Install**.  
3. Ensure the new tables `tbindexnow_queue` and `tbindexnow_history` have been created in your database.

---

## Configuration

1. Generate your API Key at Bing IndexNow ( https://www.bing.com/indexnow ). Generating a Bing key will update other search engines too. Google does not follow IndexNow for the time being but relies on internal crawling methods. 
2. In **ALL SHOPs context** go to **Modules** → **tbindexnow** → **Configure**:
   - Enter your **IndexNow API Key** (8–128 alphanumeric & dashes).
   - Click **Save**.  
3. The module will write a `<YOUR_KEY>.txt` file into your shop root automatically - no manual upload needed.

---

## Usage

- Whenever you add, update or delete a product, its front-office URL(s) (all active shops & languages) are queued.  
- Go to **Modules** → **tbindexnow** → **Submission History** to see the last 50 submissions and pending count.

---

## Cron Setup

You must set up a cron job **for each active shop domain**. The module back-office will list one URL per shop:

0 */6 * * * curl https://your-shop.com/module/tbindexnow/cron?key=YOUR_KEY


Replace:

- `https://your-shop.com` with each shop’s base URL  
- `YOUR_KEY` with the API key you saved  

Cron runs every 6 hours by default - adjust the schedule as needed.

---

## API Endpoint

The front-controller is at:
/modules/tbindexnow/cron

It accepts:
- **GET** `key=YOUR_KEY`  
Returns JSON `{ status, count }` and logs each URL’s HTTP response.

---

## Support & Contribution

1. Fork the repository  
2. Create a feature branch (`git checkout -b feature-name`)  
3. Commit your changes (`git commit -m 'Add feature'`)  
4. Push to the branch (`git push origin feature-name`)  
5. Open a Pull Request  

---

## License

This module is released under the **Academic Free License (AFL 3.0)**.  

## Changelog

-
- 1.4.6 - first public release
 
## Changelog

- introduce automatic history cleaning upon reaching certain number of records
- introduce CMS, category pages support
