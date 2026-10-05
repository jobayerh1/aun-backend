<?php
/**
 * Every word on the site lives in this file.
 *
 * Editing copy means editing here — nothing is stored in post content, so a
 * change shows on the front end immediately with no builder and no cache of
 * stale markup to clear (beyond WP Rocket's page cache).
 *
 * Deliberately absent: trade licence and BIN numbers. See DEPLOY.md.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The three brand divisions, in the order they appear on the home page.
 */
function sl_c_divisions() {
	return array(
		array(
			'key'   => 'aun',
			'tag'   => 'Smart projection',
			'img'   => 'aun-band',
			'alt'   => 'The AUN projector range',
			'mark'  => 'aun-mark.webp',
			'name'  => 'AUN Projector Bangladesh',
			'what'  => 'Authorized importer &amp; distributor',
			'body'  => 'Home theatre, portable, classroom and office projectors, plus ALR screens, mounts and genuine spare parts. Sold with local warranty, repaired at our own Dhaka bench, and supported by the free AUN Care app.',
			'chips' => array( sl_customers()['display'] . ' customers', 'BDT 7,590 &ndash; 32,900+', 'Since 2019' ),
			'url'   => 'aun_url',
			'link'  => 'aun-projector.com.bd',
		),
		array(
			'key'   => 'koh',
			'tag'   => 'Women&rsquo;s fashion',
			'img'   => 'koh-band',
			'alt'   => 'Kohthai handbags',
			'mark'  => 'koh-mark.webp',
			'name'  => 'Kohthai Bags',
			'what'  => 'Our own label &middot; imported &amp; curated',
			'body'  => 'A modern women&rsquo;s handbag label &mdash; tote, crossbody, clutch, shoulder and saddle bags chosen for material quality and everyday wear, priced for Bangladeshi buyers rather than for a boutique window.',
			'chips' => array( '6 bag categories', 'Nationwide delivery', '7-day return window' ),
			'url'   => 'koh_url',
			'link'  => 'kohthaibd.com',
		),
		array(
			'key'   => 'breo',
			'tag'   => 'Wellness technology',
			'img'   => 'breo-band',
			'alt'   => 'A Breo massager on a desk',
			'mark'  => '',
			'name'  => 'Breo Bangladesh',
			'what'  => 'Authorized distributor',
			'body'  => 'Breo is a global wellness-device brand. We are its authorized distributor in Bangladesh, bringing neck and shoulder massagers, eye massagers, percussion massage guns and body massagers to the market with official warranty and local service.',
			'chips' => array( 'NEW:Newly launched', '4 models in stock', 'Official warranty' ),
			'url'   => 'breo_url',
			'link'  => 'breo.bd',
		),
	);
}

/**
 * Source → Stock → Sell → Support. A real sequence, which is why it is the
 * only numbered block on the site.
 */
function sl_c_chain() {
	return array(
		array( 'Source', 'We buy directly from the manufacturer as the appointed distributor &mdash; not through a grey channel, so every unit carries a real warranty and a traceable serial.' ),
		array( 'Stock', 'Inventory sits in our own Dhaka warehouse. That is why an order placed today can ship today instead of waiting on the next shipment to clear.' ),
		array( 'Sell', 'Through our own storefronts, our dealer network and direct corporate supply &mdash; with proper invoicing and documentation for procurement teams.' ),
		array( 'Support', 'Warranty handling, diagnostics and genuine parts from our own bench in Dhaka. We do not hand you a manufacturer&rsquo;s hotline in another country.' ),
	);
}

function sl_c_proof() {
	return array(
		array( 'Real photographs', 'Product pages show unedited photos of the actual unit in real Bangladeshi rooms &mdash; never a manufacturer render with a fake projected image dropped in.' ),
		array( 'Published test data', 'We publish unedited nine-point lux-meter brightness tests. You watch the meter yourself rather than trusting a number printed on a box.' ),
		array( 'Honest guidance', 'Every model says plainly which rooms it suits &mdash; including when a cheaper one is enough, and when you genuinely need to spend more.' ),
	);
}

function sl_c_app() {
	return array(
		array( 'Register your warranty', 'by scanning the box &mdash; no receipt to lose.' ),
		array( 'Order genuine spare parts', 'matched to the number printed on the part itself.' ),
		array( 'Book a repair and follow it', 'through every stage, like a courier tracking page.' ),
		array( 'Firmware, manuals and guides', 'filtered to your exact model, not a generic PDF pile.' ),
	);
}

function sl_c_credentials() {
	return array(
		array( 'shield', 'Appointed distributor', 'Authorized in Bangladesh for both AUN and Breo. Genuine units with traceable serials and a warranty that is honoured here, not a parallel import with a foreign returns address.' ),
		array( 'bench', 'Our own service bench', 'A repair workshop in Dhaka with genuine parts held in stock. Diagnostics and warranty work are done in-country, which is why a fault takes days rather than months.' ),
		array( 'doc', 'Paperwork done properly', 'Corporate invoicing, warranty certificates and import documentation prepared the way an audit, a tender or a procurement officer expects to see them.' ),
	);
}

function sl_c_values() {
	return array(
		array( 'Honesty about specs', 'We publish the manufacturer&rsquo;s figures clearly labelled as theirs, and our own measurements separately. We do not quote an inflated number as if we measured it.' ),
		array( 'Repair before replace', 'A unit that can be fixed gets fixed. Genuine parts, on our own bench, with the customer told what is actually wrong.' ),
		array( 'Reachable afterwards', 'A phone number a person answers, a service point with an address, and an app that works whether or not you kept the receipt.' ),
		array( 'Priced for here', 'We price for Bangladeshi buyers and say plainly when a cheaper model in our own range is the better fit for the room.' ),
		array( 'Paperwork done properly', 'Proper invoices, warranty documents and import paperwork &mdash; the things a procurement officer or a dealer needs and rarely gets.' ),
	);
}

function sl_c_timeline() {
	return array(
		array( '2019', 'AUN Projector Bangladesh opens', 'Trading begins on 1 April 2019 as the official AUN importer, with a repair bench in Dhaka from the start rather than a returns address abroad.' ),
		array( '2025', 'Kohthai Bags launches', 'A women&rsquo;s handbag label under the same roof &mdash; imported, curated and priced for everyday wear rather than for a boutique window.' ),
		array( '2026', 'AUN Care app ships', 'A free Android app in Bangla and English: warranty registration by scanning the box, genuine spare-part orders, repair booking with live tracking, and firmware and manuals for the exact model you own.' ),
		array( '2026', 'Breo Bangladesh appointed', 'Smart Living becomes the authorized Breo distributor in Bangladesh, adding neck, eye and body massagers and percussion massage guns to the group.' ),
	);
}

/**
 * Breo ranges, by category rather than by model.
 *
 * Deliberately not model names: a model gets discontinued and the page goes
 * stale. These point at breo.bd's own category pages, which survive a
 * catalogue change.
 */
function sl_c_breo_products() {
	return array(
		array( 'Neck &amp; shoulder', 'Massagers for neck and shoulder tension', 'product-category/neck-shoulder-massagers/' ),
		array( 'Eye massagers', 'For tired eyes and screen fatigue', 'product-category/eye-massagers/' ),
		array( 'Massage guns', 'Percussion devices for muscle recovery', 'product-category/massage-guns/' ),
		array( 'Back &amp; waist', 'Body massagers for back and waist', 'product-category/back-waist-massagers/' ),
	);
}

/* ---------------------------------------------------------------------------
 * Corporate Solutions
 * ------------------------------------------------------------------------ */

function sl_c_corporate_offers() {
	return array(
		array( 'projector', 'Meeting &amp; training rooms', 'Projectors and mounts for conference rooms, training areas and internal communication setups &mdash; specified for the actual room size and ambient light rather than sold from a catalogue page.', 'Site guidance on throw distance, screen size and brightness' ),
		array( 'school', 'Educational institutions', 'Projection for schools, colleges, coaching centres and seminar halls, including multi-room rollouts. We advise on the brightness a lit classroom genuinely needs instead of selling the cheapest unit that fits the budget line.', 'Multi-unit supply &middot; institutional invoicing' ),
		array( 'chart', 'Events &amp; presentations', 'Workshops, exhibitions, training programmes and product launches. Reliable projection output with spare units available, because a failure on the day is not a warranty claim &mdash; it is a ruined event.', 'Standby units available on request' ),
		array( 'gift', 'Corporate gifting', 'Kohthai bags and Breo wellness devices for recognition events, employee rewards, client gifts and promotional campaigns &mdash; a gift that gets used rather than a branded pen.', 'Bulk pricing &middot; Kohthai &amp; Breo' ),
		array( 'wellness', 'Workplace wellness', 'Our newest offering. Breo massagers for staff rest areas, wellness rooms and long-shift environments &mdash; supplied with official warranty and serviced locally, not imported ad hoc for one purchase.', 'New &middot; via our Breo distributorship' ),
	);
}

function sl_c_corporate_why() {
	return array(
		array( 'Authorized import', 'Appointed distributor for AUN and Breo in Bangladesh &mdash; genuine units with traceable serials, not parallel imports.' ),
		array( 'Documentation', 'Corporate invoicing, warranty certificates and import paperwork, prepared the way an audit expects to see them.' ),
		array( 'Stock depth', 'Inventory held in Dhaka, so a multi-unit order does not wait on the next shipment clearing customs.' ),
		array( 'Service in-country', 'Our own bench in Dhaka with genuine parts. Warranty claims are handled here, not routed abroad.' ),
		array( 'Specification help', 'We size brightness, resolution and throw distance for your actual room &mdash; and say when a cheaper model will do.' ),
		array( 'One point of contact', 'A named person who answers, through the order and afterwards. Not a shared inbox.' ),
	);
}

function sl_c_corporate_faq() {
	return array(
		array(
			'Do you supply projectors in bulk to schools and offices?',
			'Yes. Bulk and multi-room supply is a core part of what we do &mdash; schools, colleges, coaching centres, corporate offices and training facilities. Tell us how many rooms and roughly how large they are, and we will specify per room rather than quoting one model for everything.',
		),
		array(
			'Can you issue a corporate invoice?',
			'Yes. Corporate invoicing and warranty documentation are standard on institutional orders. If your finance team needs a VAT challan (Mushak 6.3) or specific tender paperwork, say so with the enquiry and we will confirm exactly what we can issue before you raise the purchase order.',
		),
		array(
			'Which projector suits a classroom with the lights on?',
			'A brighter one than most suppliers will sell you. A lit classroom needs far more real brightness than a darkened living room, and the headline number on a box is usually not measured the way you would assume. Send us the room dimensions, the window situation and the screen size, and we will tell you the honest minimum &mdash; including when a cheaper model in our own range is genuinely enough.',
		),
		array(
			'Do you handle corporate gifting?',
			'Yes, through two of our divisions: Kohthai bags and Breo wellness devices. Both work well for recognition events, employee rewards, client gifts and promotional campaigns. Bulk pricing applies, and we can advise on what suits a mixed recipient list.',
		),
		array(
			'What happens if a unit fails during the warranty period?',
			'It comes to our own service bench in Dhaka. Diagnostics and genuine parts are handled here rather than shipped abroad, which is why a fault is usually measured in days rather than months. For institutional customers running critical rooms, ask about standby units when you order.',
		),
	);
}

/* ---------------------------------------------------------------------------
 * Dealer & Partnership
 * ------------------------------------------------------------------------ */

function sl_c_dealer_programmes() {
	return array(
		array( 'projector', 'AUN projector dealer programme', 'Authorized resale of AUN projectors, screens and accessories nationwide. Suited to retail electronics shops, IT suppliers, AV contractors and regional distributors.', 'Dealer pricing &middot; warranty handled by us' ),
		array( 'wellness', 'Breo wellness retail partnership', 'Our newest programme. Stock Breo massagers and massage guns in pharmacies, wellness retailers, lifestyle stores, gyms and physiotherapy practices, backed by the official distributorship.', 'New &middot; territories still open' ),
		array( 'box', 'Kohthai retail &amp; boutique', 'Wholesale and consignment for fashion stores, online sellers, gift shops and lifestyle boutiques &mdash; with seasonal range access and restocking support.', 'Wholesale &middot; online resale' ),
		array( 'building', 'Bulk &amp; institutional orders', 'Procurement support for NGOs, training centres, public facilities, commercial projects and government-linked programmes, with tender-ready documentation.', 'Tender documentation available' ),
	);
}

function sl_c_dealer_commitments() {
	return array(
		array( 'Official import', 'Appointed distributor for AUN and Breo. You are reselling genuine, warrantied stock.' ),
		array( 'Dealer pricing', 'Tiered pricing with margin that survives a competitive market, reviewed as volume grows.' ),
		array( 'Consistent stock', 'Inventory held in Dhaka so you can promise a delivery date and keep it.' ),
		array( 'We take the service', 'Warranty and repair come back to our bench, not your counter. That is the whole point.' ),
		array( 'Marketing support', 'Product photography, specification sheets and campaign assets for selected partners.' ),
		array( 'Exclusive access', 'Reseller access to models and ranges we do not list publicly at retail.' ),
		array( 'Proper paperwork', 'Professional invoicing and documentation &mdash; the unglamorous thing that makes a partnership last.' ),
	);
}

function sl_c_dealer_faq() {
	return array(
		array(
			'How do I become an AUN projector dealer in Bangladesh?',
			'Send us your business category, your location and roughly what volume you expect to move. We come back with the pricing tier that fits and what stock we can commit to. There is no application fee, and we would rather start you small and grow than load you with inventory you cannot sell.',
		),
		array(
			'Is the Breo distributorship open in my district?',
			'Very likely &mdash; Breo is our newest division and most territories are still open. It suits pharmacies, wellness retailers, lifestyle stores, gyms and physiotherapy practices as well as general electronics shops. Tell us your district and we will tell you honestly what is already taken.',
		),
		array(
			'What is the minimum order to start?',
			'It depends on the brand and the tier, and we set it after we understand your market rather than before. We would rather agree a realistic first order than quote a number that makes the partnership fail in month two.',
		),
		array(
			'Who handles warranty claims &mdash; me or you?',
			'We do. A warranty claim comes back to our own bench, not to your counter. This is deliberate and it is the main reason dealers stay with us: you sell the unit, we carry the service burden.',
		),
		array(
			'Do you supply dealers outside Dhaka?',
			'Yes, nationwide. Stock is held in Dhaka and shipped by courier, so a regional dealer can promise a delivery date and keep it. Service still routes back to our own bench, which for most faults is faster than any local alternative.',
		),
	);
}

/* ---------------------------------------------------------------------------
 * SEO copy — see smartliving-seo-plan.md for how these were chosen.
 * Overridable per page from Settings → Smart Living Site.
 * ------------------------------------------------------------------------ */

function sl_c_seo() {
	return array(
		'home' => array(
			'title' => 'Smart Living Bangladesh | Authorized AUN & Breo Distributor',
			'desc'  => 'Dhaka import and distribution company since 2019. Authorized distributor of AUN projectors and Breo massagers, and owner of Kohthai Bags.',
		),
		'about' => array(
			'title' => 'About Smart Living Bangladesh | Import & Distribution',
			'desc'  => 'Trading since 1 April 2019 from Dhaka. Three divisions, our own service bench, and ' . sl_customers()['display'] . ' customers served through AUN Projector Bangladesh.',
		),
		'brands' => array(
			'title' => 'Our Brands | AUN Projector, Kohthai Bags & Breo Bangladesh',
			'desc'  => 'Smart Living Bangladesh is the authorized AUN projector and Breo distributor in Bangladesh, and owns the Kohthai Bags label.',
		),
		'corporate' => array(
			'title' => 'Corporate Projector Supplier in Bangladesh | Smart Living',
			'desc'  => 'Bulk projector supply for schools, offices and events across Bangladesh, plus corporate gifting. Corporate invoicing and in-country service.',
		),
		'dealers' => array(
			'title' => 'Projector & Massager Dealership in Bangladesh | Smart Living',
			'desc'  => 'Dealer programmes for AUN projectors, Breo massagers and Kohthai Bags. Dealer pricing, stock held in Dhaka, and warranty handled by us.',
		),
		'contact' => array(
			'title' => 'Contact Smart Living Bangladesh | Dhaka',
			'desc'  => '108 Golartek, Mazar Road, Mirpur, Dhaka 1216. Phone +880 9638-078888. Saturday to Thursday, 10:00 to 18:00.',
		),
	);
}
