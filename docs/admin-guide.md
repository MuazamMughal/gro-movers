# Admin guide

How to run the website day to day: editing content, handling quote requests, and keeping the site healthy in Google. No coding needed.

**Log in:** `https://example.com/admin`, with the email and password you received at setup. You can change the password in **your name (bottom left) → Security**.

> **How changes go live.** Pages are saved as ready-made files so they load fast. When you save something, the affected pages rebuild in the background and show on the site **within about a minute**. Menus, settings and the theme rebuild the whole site, which takes a little longer. If the dashboard shows a red "cron is not running" banner, changes are waiting; tell whoever hosts the site (see `docs/deploy-cpanel.md`, step 8).

---

## 1. Dashboard

The first screen after login shows:

- **Quote requests** today, in the last 7 and 30 days, a 30-day chart and the latest leads.
- **Unread** quote requests and contact messages. The same numbers appear as badges in the sidebar.
- **Static site status:** how many pages are built, any that failed, and the **Rebuild site** button. Use it if something on the site looks out of date.
- **Pages hidden from Google:** should list only `/thank-you`. Anything else here is not appearing in Google search; open it and check its SEO tab.

---

## 2. Quote requests and contact messages

**Quote Requests** lists everything sent through the quote form: name, email, phone, moving from and to, moving date and type of move. Each lead also records the page it was sent from and any campaign (UTM) tags.

- **Open a lead** to see all details. Opening it marks it as read.
- **Status:** move each lead along as you work it: **New → Contacted → Quoted → Booked**, or **Lost**. Filter the list by status to see what still needs a call.
- **Export CSV:** download the current (filtered) list for a spreadsheet or CRM.
- **Email alerts:** every new lead is emailed to the addresses in **Settings → Email → Lead recipients**. The customer gets an automatic confirmation email (you can edit or turn it off there).

**Contact Messages** works the same way for the contact form.

> Tip: tap the phone number in a lead to call straight from your phone.

---

## 3. Pages

**Pages** holds the fixed pages (Home, About Us, Services, Service Areas, Reviews, Blog, Contact Us, Get a Quote, Thank You, Privacy Policy, Terms) and any extra pages you create.

Each page has tabs:

| Tab | What it's for |
|---|---|
| **Details** | Title, web address (slug), published or draft. |
| **Page sections** | The page's content, built from blocks (below). |
| **FAQs** | Questions shown at the bottom of the page (see FAQs). |
| **SEO** | How the page appears in Google (see section 9). |

**Section blocks.** Add, reorder (drag), hide or remove blocks:

Hero · Rich text · Image + text · Services grid · Areas grid · Cards grid · Steps · Pricing factors · Trust bar · Reviews slider · FAQ · Gallery · Map · Quote form · Contact form · Call-to-action banner · Custom HTML

- **Hidden** blocks stay saved but don't show on the site, which is handy for seasonal offers.
- **Custom HTML** is for embeds from trusted sources only.
- **Text tokens:** write `{company}`, `{phone}` or `{city}` in any text and the site fills in the real value. Change the phone number once in Settings and it updates everywhere.

**Changing a web address (slug).** The old address automatically redirects (301) to the new one, so Google rankings and old links keep working. Avoid changing addresses of pages that already rank well unless you need to.

**Privacy Policy and Terms** show a yellow **Note**: the starter text is placeholder copy. Have a lawyer review it, then clear the note.

---

## 4. Services, Service Areas and Area × Service pages

**Services** (Local Moving, Long-Distance, Packing…): each has its own page with Details, Page sections, FAQs and SEO tabs, like Pages. The order in the list is the order on the site and in the menu.

**Service Areas** (cities): each city has a page with its own sections, ZIP codes, nearby cities and the services offered there. Turn a city off to remove its page.

**Area × Service** pages are the combinations, such as "Packing Services in Plano". They are where most local Google traffic comes from.

- **Template:** one shared layout used by every combination. Use the tokens `{service}`, `{city}`, `{state}`, `{state_code}`, `{company}` and `{phone}` in its text and SEO fields, and each page fills in its own names.
- **Edit one page** to give a specific combination its own text. Unique text helps it rank; pages that only use the template are fine to start with.
- **Turn a combination off** if you don't offer that service in that city.

> **Name changes rebuild the whole site.** Renaming a service or city updates every menu, link and combination page, so it takes a few minutes to show everywhere.

---

## 5. Blog

| Screen | What it's for |
|---|---|
| **Posts** | Write and publish articles. |
| **Categories** | Group posts (each has its own page and SEO). |
| **Authors** | Name, photo and short bio shown on posts. |
| **Tags** | Optional extra labels. |

**Writing a post:**

- The editor works like a word processor. Use **Heading 2** for main sections: they build the table of contents at the top of the post automatically. Reading time is calculated for you.
- Insert images from the Media Library; they are resized for phones automatically. Always fill in the image description (alt text).
- **Featured image:** shown on blog cards and when the post is shared on social media.
- **Status:** *Draft* (only you see it), *Published*, or pick a future date and time to **schedule** it. Scheduled posts go live on their own, to the minute.
- Aim for 800+ words that answer a real customer question; link to the matching service page.

---

## 6. Reviews and FAQs

**Reviews:** name, city, star rating, text, source (Google, Yelp, Facebook, Website, Other), date and an optional photo. Link a review to a city or service to show it on that page. **Featured** reviews appear in sliders across the site.

- **Sample reviews:** the starter reviews are placeholders and the list shows a yellow banner until they are gone. **Replace them with real reviews before launch**; publishing invented reviews breaks FTC rules and Google policy.
- **Star rating in Google:** only reviews with source **Website** (collected by you) count towards the star rating shown in search results, and never sample reviews. Google doesn't allow showing Google, Yelp or Facebook reviews as your own rating.

**FAQs** is one shared bank of questions. Write a question once and attach it to any page, service or city on that item's **FAQs** tab. Editing it updates it everywhere. Opening a question shows where it is used.

---

## 7. Media Library

Upload images once and reuse them anywhere.

- **Formats:** JPG, PNG or WebP, up to 8 MB. Logos and favicons may be SVG.
- **Cropping:** pick a shape when you upload or use an image: *Hero banner* (wide), *Card*, *Blog / social share* or *Square*. The site creates fast phone and desktop sizes automatically.
- **Alt text:** describe the image in a few words ("Two movers loading a sofa into a truck in Dallas"). It helps blind visitors and Google Images.
- An image that is in use can't be deleted until you remove it from its pages.

---

## 8. Menus and Move Types

**Menus:** edit the header, the three footer columns and the mobile menu. Drag items to reorder or nest them. An item can link to a page, service, city, post, category or any web address.

- **Auto children:** the Services and Service Areas items can list their sub-pages automatically, so new services and cities appear in the menu without editing it.

**Move Types:** the choices in the quote form's *Type of Move* field (Local Move, Long-Distance Move…). Reorder them or turn them off; the form updates on its own.

---

## 9. SEO

**The SEO tab** (on every page, service, city, post and category):

| Field | Guidance |
|---|---|
| **Meta title** | The blue link in Google. About 50–60 characters, main keyword first, e.g. "Local Movers in Plano, TX". Your site name is added automatically. |
| **Meta description** | The grey text under it. 140–160 characters that make people click; include the city and a call to action. |
| **Social sharing** | Title, description and image for Facebook, LinkedIn and texts. Empty fields use the meta title and description. |
| **Canonical URL** | Leave empty unless an expert tells you otherwise. |
| **Hidden from Google (noindex)** | Only for pages that must not appear in search (like Thank You). The site asks you to confirm. |

The **Google preview** shows the result as you type. A warning appears if another page already uses the same title or description, because duplicates compete with each other.

**SEO Tools** (sidebar):

- **SEO report:** every public page with missing, too-long or duplicate titles, descriptions and headings. Aim for 0 issues before launch and check it monthly.
- **Redirects:** send an old address to a new one (301), for example links from a previous website. Redirects for changed slugs are created for you.
- **robots.txt & sitemap:** the sitemap updates itself. Leave robots.txt as it is unless you know why to change it; the editor blocks settings that would hide the whole site from Google.

---

## 10. Settings

| Tab | What's in it |
|---|---|
| **General** | Company and legal name, phone, email, address and map location, business hours, USDOT and MC numbers, founded year, service area line, footer text, social profiles. These appear across the site and in Google's business data, so keep them exact. |
| **Branding** | Logo for light and dark backgrounds, favicon. |
| **Theme** | Site colors: Primary (backgrounds), Secondary (text and dark sections) and Tertiary (buttons and accents). The preview shows a readability (contrast) check for each color pair. If your accent is too light for white text, buttons automatically use a darker shade of it. |
| **Email** | Lead recipients, sending server (SMTP), sender name and address, the customer auto-reply text, and **Send test email**. |
| **Integrations** | Google Places key (address suggestions in the quote form), Google Analytics 4 or Tag Manager, Search Console verification, Cloudflare Turnstile keys (spam check on the forms), Facebook Pixel, extra head and body scripts. |
| **Forms** | Consent text under the quote form, the button label and the success message. |
| **SEO** | Site name added to titles, default description and share image, the main domain (with or without www). |
| **Static Cache** | Turn the page cache off while troubleshooting (every page then renders live and the site is a little slower). Turn it back on afterwards. |

**Analytics events.** GA4 records these events, so you can see which pages bring in work:

- `generate_lead`: a quote request was sent.
- `contact_submit`: a contact message was sent.
- `click_to_call`: someone tapped a phone number.
- `quote_cta_click`: someone clicked a "Get a Free Quote" button.

Mark `generate_lead` as a **key event** in GA4.

---

## 11. Users and your account

- **Users:** add or remove admin accounts. Everyone with an account can change everything, so only add people you trust. Remove accounts when someone leaves.
- **Your account** (your name, bottom left): change your name, email and password, and switch the admin between light and dark mode.
- **Forgot your password?** Use the link on the login page; a reset email is sent to you.

---

## 12. Monthly checklist

- [ ] Every lead has a status; nothing stays *New* for more than a day.
- [ ] **Dashboard:** no red cron banner, no failed pages, and only `/thank-you` under "Pages hidden from Google".
- [ ] **SEO report:** 0 issues.
- [ ] One or two new blog posts answering customer questions.
- [ ] New real reviews added and featured.
- [ ] Company info (hours, phone, holiday closures) still correct.
- [ ] **Search Console:** no new errors under Pages; look at which searches bring visitors.
- [ ] A backup downloaded and kept off the server (`docs/deploy-cpanel.md`, section 12).

---

## 13. Common questions

| Question | Answer |
|---|---|
| I saved, but the site still shows the old version. | Wait a minute and refresh (on a phone, close and reopen the tab). Still old after 5 minutes? Click **Rebuild site** on the dashboard, and check for the red cron banner. |
| A customer says the form asks them to "verify you are human". | That's the Cloudflare spam check (**Settings → Integrations**). Most people never see it; one tick is enough. If someone can't get past it, they can call instead. |
| Quote emails stopped arriving. | **Settings → Email → Send test email**. If the test fails, the email password or server changed. Leads are always saved in **Quote Requests**, even when email fails. |
| The address suggestions in the quote form stopped working. | The Google Places key expired, ran out of quota or doesn't allow your domain. Check it in Google Cloud. The form still works without suggestions. |
| A page disappeared from Google. | Open its SEO tab: is *Hidden from Google* ticked? Is the page published? Then use **URL Inspection** in Search Console. |
| I deleted a page by mistake. | It can't be undone in the admin. Ask your host to restore last night's backup, or recreate the page and add a redirect from its old address. |
