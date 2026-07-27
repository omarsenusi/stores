# Campaigns System Implementation Plan

This plan is created based on an analysis of the current project structure, database schema, and background jobs. It outlines the architecture for importing and verifying Salla stores in bulk via Excel files or Google Search queries.

---

## 🚀 Challenges & Proactive Solutions (Challenge the Idea)

To make this plan highly robust, scalable, and efficient, we propose the following architectural solutions:

### 1. Handling Google Anti-Scraping Protection
* **Problem:** Scraping Google search results directly using raw HTTP requests from a server IP is highly susceptible to captchas and HTTP 429 (Rate Limit) blockages.
* **Solution:** We will build a native HTML parser for Google search result pages. In addition, we will design the system with a configuration-fallback mechanism: if `GOOGLE_SERP_API_KEY` or custom Proxy options are defined in the `.env` file, the system will route requests through them for production reliability, falling back to raw HTML parsing in development.

### 2. Smooth UI Polling vs. Heavy Page Reloads
* **Problem:** Reloading the Inertia page (`router.reload`) every 5 seconds re-renders the whole client-side DOM. This causes visible flickering, resets user scroll positions, and disrupts input fields.
* **Solution:** We will expose a lightweight API endpoint (`/campaigns/{id}/stats`) that returns only the essential progress stats in JSON. In the React frontend, we will poll this lightweight route using a standard fetch request (`useHttp` or native fetch) and update progress bars smoothly in the React state.

### 3. URL & Domain Normalization
* **Problem:** Google results can return variations of the same site, e.g., `https://subdomain.salla.sa/`, `http://subdomain.salla.sa/ar`, or custom domains.
* **Solution:** Before checking if a domain already exists in the database, we will normalize the URL to extract the base host/domain (e.g. `subdomain.salla.sa` or `taadates.com`). This ensures we skip scraping duplicate links.

### 4. Campaign Control (Pause / Cancel / Resume)
* **Problem:** Running a large search or verification job (e.g., 5,000 stores) might overwhelm system resources or exceed API limits. The user may want to stop it.
* **Solution:** We will add a "Cancel Campaign" feature. Clicking it sets the campaign status to `cancelled`. As queued background jobs start executing, they will check the campaign's status first; if it is marked as `cancelled`, they will abort immediately, preserving queue resources and Salla API rate limits.

### 5. Detailed Failure Logs
* **Problem:** Simply incrementing the "failure" count does not help users troubleshoot why a store failed (e.g., maintenance mode, invalid ID, connection timeout, etc.).
* **Solution:** We will log all check errors in a dedicated `campaign_store_errors` table. This stores the store ID, website URL, and the detailed error payload/trace, allowing the user to troubleshoot directly from the dashboard.

---

## 📋 The 6-Task Roadmap

### Task 1: Database Models & Migrations
1. Create the `campaigns` table schema:
   - `type`: `excel` or `google`
   - `status`: `pending`, `processing`, `completed`, `failed`, `cancelled`
   - Counters: `total_stores`, `processed_stores`, `success_count`, `failure_count`, `already_exists_count`
   - Google stats: `google_links_found`, `google_links_processed`, `google_pages_scraped`
   - Fields: `name`, `search_query`, `file_path`, `error_message`
2. Create the `campaign_store_errors` table schema:
   - Foreign key: `campaign_id` (nullable)
   - Fields: `store_id` (nullable), `store_url` (nullable), `error_message` (text)
3. Run the database migrations.

---

### Task 2: Update `CheckStoreJob` for Campaign Tracking
1. Modify `CheckStoreJob` to accept an optional `$campaignId` parameter in its constructor.
2. At the start of the job, check if the store ID is already in `scraped_stores` with `is_found = true`.
   - If it exists: Increment `already_exists_count` and `processed_stores` on the Campaign, and skip the API call.
3. If it does not exist, proceed with the API call:
   - **On Success:** Save to `scraped_stores`, increment `success_count` and `processed_stores`.
   - **On Failure:** Save the error, increment `failure_count` and `processed_stores`, and insert a record into `campaign_store_errors`.
4. Trigger a campaign completion check after each store check completes.

---

### Task 3: Develop Excel Import Campaign Job
1. Create `ProcessExcelCampaignJob` to handle Excel files in the background.
2. Use `maatwebsite/excel` to read the uploaded Excel file.
3. Identify the "معرف المتجر" (Store ID) column, count the total stores, and update the campaign status to `processing` with the message: `"X stores detected"`.
4. Queue a separate `CheckStoreJob` for each store ID.

---

### Task 4: Develop Google Search Scraping Job
1. Create `ProcessGoogleCampaignJob` to scrape Google search pages for the query.
2. For each search result link:
   - Extract and normalize the domain.
   - Check if the domain already exists in `scraped_stores`.
     - If it exists: Increment `already_exists_count` and skip.
     - If it does not exist: Send an HTTP request to open the website, and search its HTML source for the pattern `"store":{"id":(\d+)` using regex.
       - If the store ID is found: Queue a `CheckStoreJob` with that ID (and campaign ID) and increment the Google successful extraction count.
       - If not found or the visit fails: Increment the Google failure count.

---

### Task 5: Develop Controller & Routes
1. Create `CampaignController` to handle:
   - Listing campaigns and general stats.
   - Excel file upload and dispatching `ProcessExcelCampaignJob`.
   - Google search query submission and dispatching `ProcessGoogleCampaignJob`.
   - Serving a lightweight JSON endpoint `/campaigns/{id}/stats` to support frontend polling.
   - Cancel campaign endpoint.
2. Register the web and API routes in `routes/web.php`.

---

### Task 6: Build the React Dashboard UI (Inertia + React + Radix)
1. Create the Campaigns page in `resources/js/pages/campaigns/index.tsx`.
2. Design modern modals for:
   - "Excel Upload Campaign"
   - "Google Search Campaign"
3. Render campaign cards with:
   - Smooth progress bars showing percentage completion.
   - Real-time counters (Success, Failed, Existing, Google links processed, etc.).
   - Dynamic status badges.
4. Implement smart polling in React: use `useEffect` and `setInterval` to fetch fresh statistics from the lightweight endpoint every 5 seconds, updating the UI smoothly without reloading the entire Inertia state.
5. Create a failures view tab showing the detailed records from `campaign_store_errors` for active debugging.
