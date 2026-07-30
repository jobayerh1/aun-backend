# AUN Projector — Customer Support FAQ Corpus (Master)

**Purpose:** Single source of truth for all repetitive customer questions. This one file feeds → (a) the AI WhatsApp bot, (b) an optional public Help/FAQ page, (c) agent quick-replies, (d) post-purchase SMS text. Edit here once; everything downstream updates.

**Status:** v1.2 (2026-06-24). ⚠️ **Canonical live data now lives in `aun-help-center/aun-help-center.php`** (`aun_hc_data()` + per-model `aun_hc_videos()`); this doc is the human-readable reference. v1.2 corrections applied in the plugin: KB-001 keystone/**trapezoid** wording; KB-002 **electronic focus** (remote focus button, no dial on any model); KB-005 **no pre-installed browser** on any model incl. Android TV (install from app store, e.g. TV Bro); KB-006 **sideload differs** — certified = File Manager from Play Store, non-certified = remote Input→USB; KB-009 non-certified **Netflix runs in HD** (not SD). Per-model tutorial videos wired for U002, U001 Pro, A005, A004 Pro, A32 Pro (**A45 Pro page has no tutorials yet**).

---

## Field legend (schema for every entry)

| Field | What it's for |
|---|---|
| **ID** | Stable reference (video mapping, QR links, "bot missed Q#" logging) |
| **Category** | Setup / Casting / App-Software / Hardware / Buying / Post-sale |
| **Applies to** | Which models — answers differ by model |
| **Customer asks** | Real trigger phrasings in EN / Banglish / Bengali — what the bot matches against |
| **Root cause** | The real reason (owner's knowledge — makes answers accurate) |
| **Answer (EN)** | Canonical answer the AI is grounded on |
| **Answer (BN)** | Vetted, ready-to-send Bengali reply |
| **Link** | Exact tutorial video / manual / page |
| **Escalate?** | No / Conditional / Yes — when the bot hands off to a human |
| **Notes** | Internal routing hints (model branches, ask-first, upsell) |

---

## Model reference (routing facts — the bot uses this to pick the right answer)

| Capability | Certified Android TV — **A45 Pro, U001 Pro** | Uncertified Android — **all other models** |
|---|---|---|
| Google Play Store / Chrome / Google services | ✅ Yes | ❌ No → third-party store + APK sideload |
| Native Google Cast (Pixel etc.) | ✅ Yes | ❌ No → Miracast / wired / dongle |
| Netflix | ✅ Normal (HD) | ⚠️ Sideload, SD only, mouse-mode nav |
| In-app navigation | Standard remote | Often needs **mouse mode** |

**Dust protection:** U002 = fully **dustproof** · **A32 series = NO dust filter** (more dust-prone) · all other models = have a dust filter.

> The bot should know the customer's model (from the order, or by asking). Use this table to pick the branch — e.g. never send "sideload Netflix" steps to an A45 Pro / U001 Pro owner.

---

### KB-001 · Keystone correction (crooked image)
- **Category:** Setup · **Applies to:** All models
- **Customer asks:** EN "how do I straighten the picture / image is tilted" · Banglish "picture thik korbo kivabe", "image baka hoye ache", "display soja hoy na" · BN "ছবি বাঁকা থাকে, সোজা হয় না", "স্ক্রিন বাঁকা দেখাচ্ছে"
- **Root cause:** Projector placed at an angle / not square and level to the wall.
- **Answer (EN):** The picture looks crooked because the projector isn't square to the wall. Two fixes: (1) place it directly in front of the wall, level and pointing straight; (2) go to **Settings → Keystone Correction** and adjust until the edges are straight. Video: https://www.youtube.com/watch?v=lVeh4ruP84E
- **Answer (BN):** ছবিটা বাঁকা দেখাচ্ছে কারণ প্রজেক্টরটি দেয়ালের সাথে সোজাসুজি নেই। দুইভাবে ঠিক করতে পারেন— ১) প্রজেক্টরটি দেয়ালের ঠিক সামনে, সমান উচ্চতায় ও সোজা করে বসান। ২) **Settings → Keystone Correction**-এ গিয়ে ছবিটি ম্যানুয়ালি সোজা করুন। ভিডিও: https://www.youtube.com/watch?v=lVeh4ruP84E
- **Link:** https://www.youtube.com/watch?v=lVeh4ruP84E
- **Escalate?** No · **Notes:** Most common question. Good post-purchase SMS item. "return korbo" phrasing → flag human.

---

### KB-002 · Focus / blurry image
- **Category:** Setup · **Applies to:** All models
- **Customer asks:** "image blurry", "jhapsa dekhacche", "ফোকাস হয় না", "ছবি ঝাপসা"
- **Root cause:** Focus not adjusted. Manual-focus models use a dial; auto-focus models (U002, ToF laser) refocus automatically.
- **Answer (EN):** A blurry image just needs focusing. On manual-focus models, turn the focus dial until text is sharp. On auto-focus models (like the U002) it focuses itself — if not, nudge the projector or trigger refocus in Settings. Video: https://www.youtube.com/watch?v=lP8PoR_G4Lg
- **Answer (BN):** ছবি ঝাপসা হলে শুধু ফোকাস ঠিক করতে হবে। ম্যানুয়াল ফোকাস মডেলে প্রজেক্টরের ফোকাস ডায়াল ঘুরিয়ে ছবিটি স্পষ্ট করুন; অটো-ফোকাস মডেলে (যেমন U002) এটি নিজে নিজে হয়—না হলে প্রজেক্টরটি একটু নাড়িয়ে দিন বা **Settings** থেকে রিফোকাস দিন। ভিডিও: https://www.youtube.com/watch?v=lP8PoR_G4Lg
- **Link:** https://www.youtube.com/watch?v=lP8PoR_G4Lg
- **Escalate?** No

---

### KB-003 · Make the image larger or smaller
- **Category:** Setup · **Applies to:** All models
- **Customer asks:** EN "how to make screen bigger / image too small / too big" · Banglish "screen boro korbo kivabe", "chobi boro/choto korbo" · BN "ছবি বড় করব কীভাবে", "স্ক্রিন ছোট/বড় করতে চাই"
- **Root cause:** Image size depends on **distance** (throw distance). The Zoom function only makes the image *smaller* — it cannot enlarge it. Customers expect zoom to enlarge; it doesn't.
- **Answer (EN):** Screen size depends on how far the projector is from the wall: **bigger** → move it further back; **smaller** → move it closer, or use **Zoom (zoom-out)**. Note: Zoom can only shrink the image, not enlarge it — to enlarge, increase the distance. To find the exact distance for your room/size, use our **Screen Size Calculator**: ‹URL: Screen Size Calculator tab›
- **Answer (BN):** স্ক্রিনের ছবি বড়/ছোট হওয়া নির্ভর করে প্রজেক্টর থেকে দেয়ালের দূরত্বের উপর— • **বড় করতে চাইলে:** প্রজেক্টরটি দেয়াল থেকে আরও পিছনে সরান। • **ছোট করতে চাইলে:** দেয়ালের কাছে আনুন, অথবা **Zoom** ব্যবহার করুন। মনে রাখবেন, Zoom দিয়ে শুধু ছবি ছোট করা যায়, বড় করা যায় না—বড় করতে দূরত্ব বাড়াতে হবে। সঠিক দূরত্ব বের করতে আমাদের **Screen Size Calculator** ব্যবহার করুন: ‹URL: Screen Size Calculator tab›
- **Link:** ‹URL: Screen Size Calculator tab — need standalone URL›
- **Escalate?** No · **Notes:** Cross-links the on-site calculator — turns support into a self-serve tool visit.

---

### KB-004 · Can't find a preferred app in the store
- **Category:** App-Software · **Applies to:** Uncertified models (all except A45 Pro, U001 Pro)
- **Customer asks:** "app khuje pacchi na", "store e app nai", "অ্যাপ খুঁজে পাচ্ছি না"
- **Root cause:** Ships with a **third-party app store** (not Google-certified Play Store); some apps aren't listed.
- **Answer (EN):** This model uses a third-party app store, so a few apps may not appear there. You can still install them from their APK file — tell us which app and we'll guide you step by step.
- **Answer (BN):** এই মডেলে থার্ড-পার্টি অ্যাপ স্টোর থাকে, তাই কিছু অ্যাপ সেখানে নাও পাওয়া যেতে পারে। তবে চিন্তা নেই—অ্যাপটির APK ফাইল দিয়ে ইনস্টল করা যায়। কোন অ্যাপটি দরকার বলুন, আমরা ধাপে ধাপে দেখিয়ে দিচ্ছি।
- **Link:** ‹no video yet — record "installing apps / sideload"›
- **Escalate?** Conditional · **Notes:** Certified models (A45 Pro, U001 Pro) → not applicable, they have Play Store. Links to KB-006.

---

### KB-005 · No Google Play Store / can't install Chrome
- **Category:** App-Software · **Applies to:** Uncertified models (all except A45 Pro, U001 Pro)
- **Customer asks:** "play store nai", "chrome install hocche na", "গুগল প্লে স্টোর নেই"
- **Root cause:** Device is **not Google-certified** (no Google Mobile Services), so Play Store, Chrome and some Google apps aren't available out of the box.
- **Answer (EN):** This model isn't Google-Play-certified, so the Play Store and Chrome aren't pre-installed. Use the built-in browser for the web, and the bundled app store / APK sideload for apps. We can walk you through it.
- **Answer (BN):** এই মডেলটি Google Play সার্টিফায়েড নয়, তাই এতে Play Store ও Chrome আগে থেকে দেওয়া থাকে না। ব্রাউজ করার জন্য বিল্ট-ইন ব্রাউজার ব্যবহার করুন, আর অ্যাপের জন্য দেওয়া অ্যাপ স্টোর বা APK ইনস্টল ব্যবহার করুন। প্রয়োজনে আমরা দেখিয়ে দিচ্ছি।
- **Link:** ‹no video yet — record "installing apps / sideload"›
- **Escalate?** Conditional · **Notes:** Certified models = full Play Store (honest upsell at point of sale). Set this expectation *before* purchase.

---

### KB-006 · How to install an app that isn't in the store (sideload)
- **Category:** App-Software · **Applies to:** Uncertified models (all except A45 Pro, U001 Pro)
- **Customer asks:** "apk install korbo kivabe", "baire theke app install", "এপিকে ইনস্টল"
- **Root cause:** App must be sideloaded via APK (file manager / USB drive / browser download).
- **Answer (EN):** Download the app's APK file, open it with the built-in **File Manager**, and install. Allow "install from unknown sources" if prompted. We can do it together over a call if you like.
- **Answer (BN):** যে অ্যাপটি স্টোরে নেই, সেটির **APK** ফাইল ডাউনলোড করুন → বিল্ট-ইন **File Manager** দিয়ে খুলে ইনস্টল করুন। "Unknown sources" অনুমতি চাইলে **Allow** করুন। চাইলে ফোনে কথা বলে আমরা একসাথে করে দিচ্ছি।
- **Link:** ‹no video yet — record "sideload APK"›
- **Escalate?** No

---

### KB-007 · Remote control not working properly
- **Category:** Hardware · **Applies to:** All models
- **Customer asks:** "remote kaj korche na", "remote te problem", "রিমোট কাজ করছে না"
- **Root cause:** Most often the IR remote is **pointed at the wall/screen instead of the projector's IR sensor**. Also: weak/wrong batteries; Bluetooth remotes must be **paired** first.
- **Answer (EN):** Point the remote at the **projector itself** (its IR sensor), not at the wall/screen — that fixes it most of the time. Check the batteries are fresh and correctly placed. If it's a Bluetooth remote, it must be paired first (we'll guide you).
- **Answer (BN):** রিমোটটি দেয়াল/স্ক্রিনের দিকে না তাক করে **প্রজেক্টরের দিকে** (এর IR সেন্সরের দিকে) তাক করুন—বেশিরভাগ সময় এতেই কাজ করে। ব্যাটারি ঠিকভাবে লাগানো ও চার্জ আছে কিনা দেখুন। ব্লুটুথ রিমোট হলে আগে পেয়ার করে নিতে হবে (আমরা দেখিয়ে দিচ্ছি)। তারপরও কাজ না করলে আমাদের জানান।
- **Link:** ‹manual → remote section›
- **Escalate?** Conditional — if still dead after battery + aiming check → faulty remote → human.
- **Notes:** Very common "silly" knock — perfect instant bot reply.

---

### KB-008 · "This app doesn't work"
- **Category:** App-Software · **Applies to:** Uncertified models (all except A45 Pro, U001 Pro)
- **Customer asks:** "app kaj kore na", "এই অ্যাপ চলে না"
- **Root cause:** App may be incompatible with non-certified Android, need sideload, or not be built for a TV screen. Need to know *which* app.
- **Answer (EN):** Which app is it? Some apps install differently on this model, or aren't built for TV screens. Tell us the app name and we'll give you the exact fix.
- **Answer (BN):** কোন অ্যাপটির কথা বলছেন? এই মডেলে কিছু অ্যাপ একটু ভিন্নভাবে ইনস্টল করতে হয়, অথবা কিছু অ্যাপ টিভি স্ক্রিনের জন্য তৈরি নয়। অ্যাপটির নাম বলুন, আমরা সঠিক সমাধান দিচ্ছি।
- **Link:** —
- **Escalate?** Conditional — bot asks which app, then routes to KB-005/006/009 or a human.
- **Notes:** "Clarify-first" intent, not a canned answer.

---

### KB-009 · Netflix doesn't work
- **Category:** App-Software · **Applies to:** Uncertified models (all except A45 Pro, U001 Pro)
- **Customer asks:** "netflix cholche na", "netflix kaj kore na", "নেটফ্লিক্স চলছে না"
- **Root cause:** (a) a **shared/unofficial account** gets blocked by Netflix; (b) on non-certified Android, Netflix is **sideloaded** and often plays **SD only**; (c) navigation needs the **remote / mouse mode**, not touch.
- **Answer (EN):** A few things to check: use an **official, personal Netflix login** (shared/unofficial accounts often get blocked). On this model Netflix is installed via sideload and may play in standard definition, and you navigate it with the remote's mouse mode. We'll guide you through setup.
- **Answer (BN):** কয়েকটি বিষয় চেক করুন: (১) নিজের **অফিশিয়াল Netflix অ্যাকাউন্ট** ব্যবহার করুন—শেয়ার করা/আনঅফিশিয়াল অ্যাকাউন্ট প্রায়ই ব্লক হয়ে যায়। (২) এই মডেলে Netflix সাইডলোড করে ইনস্টল করতে হয় এবং সাধারণত SD কোয়ালিটিতে চলে। (৩) টাচ নয়, **রিমোটের মাউস মোড** দিয়ে চালাতে হবে। সেটআপে সাহায্য লাগলে আমরা আছি।
- **Link:** ‹no video yet — record "Netflix setup"›
- **Escalate?** Conditional
- **Notes:** **Be tactful — never accuse of piracy**, just say shared/unofficial accounts get blocked. Certified models (A45 Pro, U001 Pro) = proper HD Netflix (upsell angle).

---

### KB-010 · Remote doesn't work inside Netflix / apps
- **Category:** App-Software · **Applies to:** Uncertified models (all except A45 Pro, U001 Pro)
- **Customer asks:** "netflix e remote kaj kore na", "app er moddhe remote chole na"
- **Root cause:** App not built for this OS's navigation; standard keys don't move the cursor. Use the remote's **mouse / air-mouse mode**.
- **Answer (EN):** Inside apps like Netflix, switch your remote to **mouse mode** (the pointer button) and use it like a cursor to tap items.
- **Answer (BN):** Netflix-এর মতো অ্যাপের ভেতরে রিমোটটি **মাউস মোডে** নিন (পয়েন্টার বাটন) এবং কার্সারের মতো নাড়িয়ে অপশনে ট্যাপ করুন।
- **Link:** ‹no video yet — record "remote mouse mode"›
- **Escalate?** No · **Notes:** Pairs with KB-009.

---

### KB-011 · Can't mirror / cast a Google Pixel
- **Category:** Casting · **Applies to:** Uncertified models (all except A45 Pro, U001 Pro)
- **Customer asks:** "pixel cast hocche na", "pixel mirror korte parchi na"
- **Root cause:** Pixel uses **Google Cast (Chromecast)**, which needs a Chromecast-certified receiver (certified Android TV). Uncertified projectors lack it.
- **Answer (EN):** Google Pixel uses Chromecast, which this model doesn't have built in. Instead use **Miracast** (if your Pixel supports it), a **wired USB-C-to-HDMI** cable, or add a **Chromecast dongle** to the HDMI port. We'll recommend the easiest option for you.
- **Answer (BN):** Google Pixel কাস্টের জন্য Chromecast ব্যবহার করে, যা এই মডেলে বিল্ট-ইন নেই। এর বদলে ব্যবহার করুন: **Miracast** (যদি আপনার Pixel সাপোর্ট করে), অথবা **USB-C-to-HDMI** ক্যাবল দিয়ে তারের মাধ্যমে, অথবা HDMI পোর্টে একটি **Chromecast ডংগল** লাগিয়ে। কোনটি সহজ হবে আমরা বলে দিচ্ছি।
- **Link:** https://www.youtube.com/watch?v=KJwFhuis4GA (Android mirroring — related)
- **Escalate?** Conditional · **Notes:** Wired/dongle = reliable fallback. Certified models = native Cast (upsell).

---

### KB-012 · Can't mirror an iPhone
- **Category:** Casting · **Applies to:** All models
- **Customer asks:** "iphone mirror hocche na", "iphone cast korbo kivabe", "আইফোন মিরর হয় না"
- **Root cause:** iOS uses **AirPlay**. Need the projector's screen-mirroring/AirPlay feature, both devices on the **same Wi-Fi**; or a wired Lightning/USB-C-to-HDMI adapter.
- **Answer (EN):** On iPhone open **Screen Mirroring** (Control Center) and select the projector — both must be on the **same Wi-Fi**. If your model needs its mirroring app open first, start that. Reliable wired option: a Lightning/USB-C-to-HDMI adapter.
- **Answer (BN):** আইফোনে **Control Center → Screen Mirroring**-এ গিয়ে প্রজেক্টরটি সিলেক্ট করুন—খেয়াল রাখবেন দুটো ডিভাইস যেন **একই Wi-Fi**-তে থাকে। মডেলভেদে আগে মিররিং অ্যাপটি চালু করতে হতে পারে। নিশ্চিত উপায়: একটি Lightning/USB-C-to-HDMI অ্যাডাপ্টার।
- **Link:** ‹no iOS-mirroring video on U002 page — record/provide one›
- **Escalate?** Conditional

---

### KB-013 · Can't cast an Android phone
- **Category:** Casting · **Applies to:** All models
- **Customer asks:** "phone cast hocche na", "screen mirror hoy na", "মোবাইল কাস্ট হয় না"
- **Root cause:** The **phone** must support Miracast / Smart View / Wireless Display — not all do. Both devices on same Wi-Fi; or wired.
- **Answer (EN):** First check your phone has **Smart View / Cast / Wireless Display** (in quick settings). If yes, put both on the same Wi-Fi and mirror. If your phone lacks it, use a USB-C-to-HDMI cable. Tell us your phone model and we'll confirm. Video: https://www.youtube.com/watch?v=KJwFhuis4GA
- **Answer (BN):** প্রথমে দেখুন আপনার ফোনে **Smart View / Cast / Wireless Display** অপশন আছে কিনা (কুইক সেটিংসে)। থাকলে দুটো ডিভাইস একই Wi-Fi-তে রেখে মিরর করুন। না থাকলে USB-C-to-HDMI ক্যাবল ব্যবহার করুন। আপনার ফোনের মডেল বলুন, আমরা নিশ্চিত করে দিচ্ছি। ভিডিও: https://www.youtube.com/watch?v=KJwFhuis4GA
- **Link:** https://www.youtube.com/watch?v=KJwFhuis4GA
- **Escalate?** Conditional — ask phone model first.
- **Notes:** Phone model determines whether mirroring is even possible.

---

### KB-014 · Projector heats up a lot
- **Category:** Hardware · **Applies to:** All models
- **Customer asks:** "projector onek gorom hoy", "garam hoye jay", "প্রজেক্টর গরম হয়ে যায়"
- **Root cause:** **Normal.** LED/lamp projectors produce heat; internal cooling fans manage it. Only a concern if it shuts off or smells burnt.
- **Answer (EN):** That's completely normal — projectors run warm and have internal cooling fans to handle it. Just keep the side/back vents unblocked. (If it ever shuts off by itself or smells burnt, stop and contact us.)
- **Answer (BN):** এটা সম্পূর্ণ স্বাভাবিক—প্রজেক্টর কিছুটা গরম হয় এবং ভেতরে কুলিং ফ্যান থাকে যা তাপ নিয়ন্ত্রণ করে। শুধু পাশের/পেছনের ভেন্টগুলো খোলা রাখুন। (যদি কখনও নিজে নিজে বন্ধ হয়ে যায় বা পোড়া গন্ধ আসে, তাহলে বন্ধ করে আমাদের জানান।)
- **Link:** https://www.youtube.com/watch?v=rs9IzcLf0RM (maintenance — keeping vents clean)
- **Escalate?** Conditional — only if auto-shutdown / burning smell.
- **Notes:** Pure reassurance. First-time-buyer FAQ. Great post-purchase SMS item.

---

### KB-015 · "Dead pixel" / spot on the screen
- **Category:** Hardware · **Applies to:** Product-dependent (U002 = dustproof · A32 series = no filter · others = dust filter)
- **Customer asks:** EN "there's a dead pixel / black-white spot on screen / dot on the image" · Banglish "screen e dag", "ekta dead pixel ache", "chobite dag dekha jay" · BN "স্ক্রিনে দাগ", "একটা ডেড পিক্সেল আছে", "ছবিতে দাগ দেখা যায়"
- **Root cause:** Almost always **dust** on the lens/optical path — **not** a dead pixel. Normal for projectors. Severity depends on the model's dust protection.
- **Answer (EN):** Good news — this is usually **dust**, not a dead pixel. A little dust shows as a spot, and it's very normal for projectors. The **U002** is fully dustproof so it doesn't get this; most models have a dust filter; the **A32 series** has no filter so it needs a bit more regular cleaning. Cleaning steps: https://www.youtube.com/watch?v=rs9IzcLf0RM — if the spot is permanent or growing after cleaning, let us know and we'll check it.
- **Answer (BN):** চিন্তার কিছু নেই—সাধারণত এটি ডেড পিক্সেল নয়, বরং লেন্সে সামান্য ধুলা পড়ার কারণে স্ক্রিনে দাগ দেখা যায়। প্রজেক্টরের জন্য এটি খুবই স্বাভাবিক। আমাদের **U002** মডেল সম্পূর্ণ **ডাস্টপ্রুফ**; বেশিরভাগ মডেলে ধুলা ফিল্টার আছে; **A32 সিরিজে** ফিল্টার নেই, তাই একটু বেশি নিয়মিত পরিষ্কার রাখতে হয়। পরিষ্কারের নিয়ম: https://www.youtube.com/watch?v=rs9IzcLf0RM — দাগ যদি পরিষ্কারের পরও স্থায়ী হয় বা বাড়তে থাকে, আমাদের জানাবেন, আমরা চেক করে দেব।
- **Link:** https://www.youtube.com/watch?v=rs9IzcLf0RM
- **Escalate?** Conditional — genuinely permanent/growing after cleaning → human (possible warranty).
- **Notes:** Tactful reassurance + subtle U002 upsell. True dead pixel (rare) = warranty.

---

### KB-016 · Factory reset (projector slow / stuck)
- **Category:** Hardware · **Applies to:** All models
- **Customer asks:** "projector slow", "hang kore", "reset korbo kivabe", "রিসেট দেব কীভাবে"
- **Root cause:** Wants to restore to default / clear a glitch.
- **Answer (EN):** Go to **Settings → System → Factory Reset**. Note this erases your installed apps and settings, so it's a fresh start. Video: https://www.youtube.com/watch?v=nO70wP9bmDU
- **Answer (BN):** **Settings → System → Factory Reset**-এ যান। মনে রাখবেন, এতে আপনার ইনস্টল করা অ্যাপ ও সেটিংস মুছে যাবে—এটি একদম নতুন করে শুরু করে দেয়। ভিডিও: https://www.youtube.com/watch?v=nO70wP9bmDU
- **Link:** https://www.youtube.com/watch?v=nO70wP9bmDU
- **Escalate?** No

---

### KB-017 · Connect via HDMI (laptop / TV box / set-top box)
- **Category:** Setup · **Applies to:** All models
- **Customer asks:** "hdmi te connect korbo", "laptop lagabo kivabe", "dish/setup box connect", "এইচডিএমআই কানেক্ট"
- **Root cause:** Needs to select the HDMI input source after plugging in.
- **Answer (EN):** Plug the device into the projector's **HDMI** port, then open the **Source / Input** menu and select **HDMI**. Video: https://www.youtube.com/watch?v=ig0QKrWZ3zM
- **Answer (BN):** ডিভাইসটি (ল্যাপটপ/টিভি বক্স/সেটটপ বক্স) প্রজেক্টরের **HDMI** পোর্টে লাগান, তারপর **Source / Input** মেনু থেকে **HDMI** সিলেক্ট করুন। ভিডিও: https://www.youtube.com/watch?v=ig0QKrWZ3zM
- **Link:** https://www.youtube.com/watch?v=ig0QKrWZ3zM
- **Escalate?** No

---

### KB-018 · Play movies from a USB / pen drive
- **Category:** Setup · **Applies to:** All models
- **Customer asks:** "pendrive theke movie chalabo", "usb chalabo kivabe", "flash drive", "পেনড্রাইভ থেকে মুভি"
- **Root cause:** Needs to open the media player / file manager after inserting the drive.
- **Answer (EN):** Insert the pen drive into the **USB** port, open the **File Manager / Media Player**, and select your video. Video: https://www.youtube.com/watch?v=4rute8VQxvo
- **Answer (BN):** পেনড্রাইভটি **USB** পোর্টে লাগান, **File Manager / Media Player** খুলে আপনার ভিডিও/মুভি সিলেক্ট করুন। ভিডিও: https://www.youtube.com/watch?v=4rute8VQxvo
- **Link:** https://www.youtube.com/watch?v=4rute8VQxvo
- **Escalate?** No

---

## Content gaps — tutorial videos to record (no video exists yet)
These intents currently have **no video** to link. Filming short clips would close the loop:
1. Installing apps / sideloading an APK (KB-004, KB-005, KB-006)
2. Netflix setup on uncertified models (KB-009)
3. Using the remote's mouse mode in apps (KB-010)
4. **iOS / iPhone screen mirroring** (KB-012) — only an Android-mirroring video exists
5. Remote control basics / IR aiming (KB-007)

Also available but not yet an entry: **Change Language** — https://www.youtube.com/watch?v=fHMe1qsGmp4

## Backlog / to add later
- Audio / Bluetooth speaker pairing
- Wi-Fi won't connect
- Picture has wrong colors / tint
- Auto-focus / auto-keystone not working (supported models)
- Warranty terms & how to claim
- Screen recommendation (wall vs. screen)
- _(owner to add more)_
