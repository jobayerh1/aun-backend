<?php
/**
 * Site pages created by Tools → Breo BD Setup → Build site pages.
 * Business numbers come from the settings through [breo_info] / [breo_if],
 * so changing a setting updates every policy at once. Pages stay editable
 * in WordPress; a rebuild never overwrites a page you have edited.
 */
defined( 'ABSPATH' ) || exit;

return array(

	'about-breo' => array(
		'title'   => 'About Breo',
		'intro'   => 'Breo has spent more than two decades making relaxation portable. Breo Bangladesh brings it home: genuine devices, a local warranty and real support.',
		'content' => '
<h2>Breo, since 2000</h2>
<p>Breo (Shenzhen Breo Technology Co., Ltd.) was founded in Shenzhen, China, in 2000. It set out to make the relief of a good massage something you could hold in your hand, and became one of the pioneers of portable massagers for the eyes, head, neck and body.</p>
<p>Today Breo is a publicly listed company on the Shanghai Stock Exchange\'s STAR Market. It sells its devices in markets across Asia and beyond, and its products have won international design awards, including the iF Design Award.</p>

<h2>What makes a Breo different</h2>
<ul>
<li><strong>Heat and massage, together.</strong> Most Breo devices pair warmth with massage, because tense muscles let go faster when they are warm.</li>
<li><strong>Inspired by acupressure.</strong> Breo designs draw on traditional techniques like kneading, pressing and working key points, recreated with modern motors and sensors.</li>
<li><strong>Made to be carried.</strong> Cordless, rechargeable and light enough for a bag, so relief is there at the desk, in the car or on a trip.</li>
<li><strong>Designed with care.</strong> Soft-touch materials, quiet motors and clean, modern shapes you are happy to leave on the table.</li>
</ul>

<h2>Breo in Bangladesh</h2>
<p>[breo_info key="company"] is the authorized distributor of Breo in Bangladesh. For you, that means:</p>
<ul>
<li>Every device is genuine and imported through official Breo channels.</li>
<li>Your warranty is honoured here, by us, not by a seller overseas.</li>
<li>You can ask real people for advice before you buy, in Bangla or English.</li>
</ul>
<p>We are starting with four devices chosen for everyday life in Bangladesh: long desk hours, long commutes and long screen time. More are on the way.</p>
',
	),

	'manuals' => array(
		'title'   => 'Manuals & Downloads',
		'intro'   => 'The official Breo user manual for every device we sell in Bangladesh, straight from the factory.',
		'content' => '
<p>Each manual is the original Breo document for that model. Open it on your phone, or download it and keep it with your warranty card.</p>
[breo_manuals]
<h2>Need help instead?</h2>
<p>If something is not working the way the manual describes, don\'t worry: message us on WhatsApp and we will walk you through it. Every device we sell is covered by an official warranty, handled here in Bangladesh.</p>
[breo_contact]
',
	),

	'contact' => array(
		'title'   => 'Contact Us',
		'intro'   => 'Questions about a product, an order or your warranty? We\'re a message away.',
		'content' => '
<p>For the fastest reply, message us on WhatsApp. If you are asking about an existing order or a warranty claim, please keep your order number ready.</p>
[breo_contact]
',
	),

	'faq' => array(
		'title'   => 'Frequently Asked Questions',
		'intro'   => 'Everything about ordering, delivery, our products and warranty in one place.',
		'content' => '
<h2 id="orders">Orders &amp; payment</h2>
<details><summary>How do I place an order?</summary><p>Add a product to your cart and check out, or message us on WhatsApp and we will place the order for you.</p></details>
<details><summary>How can I pay?</summary><p>[breo_info key="payments"].</p></details>
<details><summary>Will you confirm my order?</summary><p>We may call or message you to confirm your order and delivery address before we dispatch it.</p></details>
<details><summary>Can I cancel an order?</summary><p>Yes. Contact us before the order is dispatched and we will cancel it at no cost.</p></details>

<h2 id="delivery">Delivery</h2>
<details><summary>How long does delivery take?</summary><p>Inside Dhaka within [breo_info key="dhaka_days"] working days, and outside Dhaka within [breo_info key="outside_days"] working days. Orders placed after [breo_info key="cutoff"] are processed on the next working day.</p></details>
<details><summary>How much does delivery cost?</summary><p>The delivery charge is shown at checkout before you pay.[breo_if key="dhaka_fee"] Inside Dhaka: ৳[breo_info key="dhaka_fee"].[/breo_if][breo_if key="outside_fee"] Outside Dhaka: ৳[breo_info key="outside_fee"].[/breo_if]</p></details>
<details><summary>Do you deliver everywhere in Bangladesh?</summary><p>Yes, we deliver anywhere in Bangladesh through our courier partners. We don\'t ship outside Bangladesh.</p></details>

<h2 id="products">Products &amp; usage</h2>
<details><summary>Are your products genuine?</summary><p>Yes. We are the authorized Breo distributor in Bangladesh and every device is officially imported.</p></details>
<details><summary>Are Breo massagers medical devices?</summary><p>No. They are designed for relaxation and everyday wellness and don\'t diagnose or treat any disease.</p></details>
<details><summary>Who should not use a massager?</summary><p>Ask your doctor before use if you are pregnant, have a pacemaker or other implant, have had recent surgery or an injury, have a skin condition in the area, or have reduced sensitivity to heat. Never use a massager on broken skin or swelling.</p></details>
<details><summary>How do I charge my device?</summary><p>All our launch devices are rechargeable by USB. Use the cable in the box with a standard 5V phone charger.</p></details>

<h2 id="warranty">Warranty &amp; returns</h2>
<details><summary>How long is the warranty?</summary><p>[breo_if key="warranty_months"]Every device comes with a [breo_info key="warranty"] official warranty against manufacturing defects, handled by us in Bangladesh.[/breo_if] See our <a href="/warranty-policy/">Warranty Policy</a>.</p></details>
<details><summary>My device arrived damaged or faulty. What do I do?</summary><p>Contact us within [breo_info key="return_faulty_days"] days of delivery with a photo or short video of the problem, and we will arrange a replacement or refund after inspection.</p></details>
<details><summary>Can I return a product if I change my mind?</summary><p>Yes, if it is unopened with its seals intact and you contact us within [breo_info key="return_unopened_hours"] hours of delivery. See our <a href="/returns-refunds/">Returns &amp; Refunds</a> policy.</p></details>
',
	),

	'warranty-policy' => array(
		'title'   => 'Warranty Policy',
		'intro'   => 'Every Breo device from Breo Bangladesh is covered by an official warranty, handled here in Bangladesh.',
		'content' => '
<p><em>Last updated: [breo_info key="updated"]</em></p>

<h2>Warranty period</h2>
<p>Breo devices bought from [breo_info key="site"] or an authorized Breo Bangladesh channel are covered by a <strong>[breo_info key="warranty" ] warranty</strong> against manufacturing defects, starting on the date of delivery.</p>

<h2>What is covered</h2>
<p>Faults caused by defects in materials or workmanship under normal use, for example:</p>
<ul>
<li>The device does not power on or charge.</li>
<li>The massage motor, heating function or controls stop working.</li>
<li>The battery fails because of a defect.</li>
</ul>
<p>We will repair the device or, if a repair is not possible, replace it with the same or an equivalent model. A repair or replacement does not start a new warranty period; the remaining period carries over.</p>

<h2>What is not covered</h2>
<ul>
<li>Physical damage such as drops, cracks, dents or crushed parts.</li>
<li>Damage from water or other liquids, fire, or electrical surges.</li>
<li>Misuse, or use that goes against the user manual.</li>
<li>Devices that have been opened, modified or repaired by anyone other than us.</li>
<li>Damage from non-standard chargers or power sources.</li>
<li>Normal wear and tear, such as worn fabric or leather, scuffs, and the gradual loss of battery capacity that all rechargeable batteries experience.</li>
<li>Lost or missing accessories.</li>
</ul>

<h2>How to make a claim</h2>
<ol>
<li>Contact us on WhatsApp or by phone with your <strong>order number</strong> and a short description, photo or video of the problem.</li>
<li>We will try to solve it with you remotely first. Many issues are settings or charging questions.</li>
<li>If the device needs inspection, you can drop it off or send it to us by courier. Courier charges for sending the device to us are paid by the customer.</li>
<li>We will inspect it and tell you whether the fault is covered and how long the repair will take.</li>
</ol>
<p>If the part needed is in stock, repairs are usually done within a few working days. If a part has to be imported from Breo, it can take longer; we will keep you updated.</p>

<h2>Proof of purchase</h2>
<p>Your order number or invoice is your proof of purchase. Please keep it for the whole warranty period.</p>

<h2>Contact</h2>
<p>[breo_info key="company"][breo_if key="phone"] · Phone: [breo_info key="phone"][/breo_if][breo_if key="whatsapp"] · WhatsApp: +[breo_info key="whatsapp"][/breo_if][breo_if key="address"] · Service point: [breo_info key="address"][/breo_if]</p>
',
	),

	'shipping-delivery' => array(
		'title'   => 'Shipping & Delivery',
		'intro'   => 'We deliver anywhere in Bangladesh. Here is how long it takes and what to expect.',
		'content' => '
<p><em>Last updated: [breo_info key="updated"]</em></p>

<h2>Delivery times</h2>
<ul>
<li><strong>Inside Dhaka:</strong> within [breo_info key="dhaka_days"] working days.</li>
<li><strong>Outside Dhaka:</strong> within [breo_info key="outside_days"] working days.</li>
</ul>
<p>Working days are Saturday to Thursday, excluding public holidays. Orders placed after [breo_info key="cutoff"] are processed on the next working day. We may call or message you to confirm your order before dispatch.</p>

<h2>Delivery charges</h2>
<p>The delivery charge depends on your location and is shown at checkout before you pay.</p>
[breo_if key="dhaka_fee"]<ul><li>Inside Dhaka: ৳[breo_info key="dhaka_fee"]</li>[breo_if key="outside_fee"]<li>Outside Dhaka: ৳[breo_info key="outside_fee"]</li>[/breo_if]</ul>[/breo_if]

<h2>Payment</h2>
<p>We accept: [breo_info key="payments"].</p>

<h2>Receiving your parcel</h2>
<ul>
<li>Please check the parcel in front of the delivery person.</li>
<li>If the box is visibly damaged or the item is wrong, don\'t accept it. Contact us straight away.</li>
<li>If you find a problem after opening, contact us within [breo_info key="return_faulty_days"] days. See <a href="/returns-refunds/">Returns &amp; Refunds</a>.</li>
</ul>

<h2>Delays</h2>
<p>Bad weather, strikes, public holidays or courier disruptions can occasionally delay deliveries. If your order is delayed, we will let you know.</p>

<h2>International shipping</h2>
<p>We currently deliver within Bangladesh only.</p>
',
	),

	'returns-refunds' => array(
		'title'   => 'Returns & Refunds',
		'intro'   => 'If something is wrong with your order, we\'ll make it right. Here is how returns and refunds work.',
		'content' => '
<p><em>Last updated: [breo_info key="updated"]</em></p>

<h2>Damaged, faulty or wrong item</h2>
<p>If your device arrives damaged, doesn\'t work, or isn\'t what you ordered, contact us within <strong>[breo_info key="return_faulty_days"] days of delivery</strong> with your order number and a photo or short video of the problem. After inspection, we will replace the item or give you a refund.</p>

<h2>Change of mind</h2>
<p>Massagers touch your skin, so for hygiene reasons we can accept a change-of-mind return only if the product is <strong>unopened, with all seals and stickers intact</strong>, and you contact us within <strong>[breo_info key="return_unopened_hours"] hours of delivery</strong>.</p>

<h2>Conditions</h2>
<ul>
<li>Returns must include the original packaging, all accessories, the manual and any free gifts.</li>
<li>Please contact us for approval <strong>before</strong> sending anything back.</li>
<li>Return shipping costs are paid by the customer.</li>
</ul>

<h2>Not eligible for return</h2>
<ul>
<li>Requests made after the time limits above.</li>
<li>Products that have been used and have no fault.</li>
<li>Products with missing parts, missing packaging or broken seals (for change-of-mind returns).</li>
<li>Damage caused after delivery, such as drops or liquid damage.</li>
</ul>

<h2>Refunds</h2>
<p>Once the returned item passes inspection, we refund you within <strong>[breo_info key="refund_days"] business days</strong>. Refunds go to your original payment method; for Cash on Delivery orders we refund by mobile banking or bank transfer. The original delivery charge is not refundable.</p>

<h2>Cancellations</h2>
<p>You can cancel an order at no cost before it is dispatched. Just contact us.</p>

<h2>Warranty claims</h2>
<p>Faults that appear later are handled under our <a href="/warranty-policy/">Warranty Policy</a>.</p>
',
	),

	'privacy-policy' => array(
		'title'   => 'Privacy Policy',
		'intro'   => 'How we collect, use and protect your personal information.',
		'content' => '
<p><em>Last updated: [breo_info key="updated"]</em></p>
<p>This policy explains how [breo_info key="company"] ("we", "us"), the operator of [breo_info key="site"] and the authorized distributor of Breo in Bangladesh, collects and uses your personal information when you visit our website, place an order or contact us.</p>

<h2>Information we collect</h2>
<ul>
<li><strong>Order and account details:</strong> your name, phone number, email address, delivery address and order history.</li>
<li><strong>Payment information:</strong> online payments are processed by our payment provider. We never see or store your full card number.</li>
<li><strong>Messages:</strong> what you send us on WhatsApp, by phone, email or social media, so we can help you.</li>
<li><strong>Technical data:</strong> IP address, browser and device type, and pages visited, collected automatically for security and to keep the site working.</li>
<li><strong>Cookies:</strong> small files that keep your cart and login working, protect the site and tell us how visitors find us. See "Cookies" below.</li>
</ul>

<h2>How we use it</h2>
<ul>
<li>To process, confirm and deliver your orders, and to handle returns, refunds and warranty claims.</li>
<li>To send order updates by SMS, email, phone or WhatsApp.</li>
<li>To answer your questions and give product advice.</li>
<li>To protect the website and our customers from fraud and abuse.</li>
<li>To improve our website, products and service.</li>
<li>To send offers and news, but only if you agree. You can opt out at any time.</li>
</ul>

<h2>Who we share it with</h2>
<p>We share only what is needed with:</p>
<ul>
<li><strong>Courier partners:</strong> your name, phone number and address, to deliver your order.</li>
<li><strong>Payment providers:</strong> to process online payments securely.</li>
<li><strong>Service providers:</strong> web hosting, security, SMS and email services that help us run the store.</li>
<li><strong>Authorities:</strong> when the law requires it.</li>
</ul>
<p>We never sell your personal information.</p>

<h2>Cookies</h2>
<p>We use cookies that are essential for the shop (your cart, checkout and login), security cookies set by our website firewall, and cookies that record how you arrived at our site (for example, from Facebook or a search engine) so we know which channels work. You can block or delete cookies in your browser settings, but the cart and checkout may not work without them.</p>

<h2>How long we keep it</h2>
<p>We keep order records for as long as needed to provide warranty service and to meet accounting and legal requirements. Account details are kept until you ask us to delete your account.</p>

<h2>Your choices and rights</h2>
<p>You can ask us to show, correct or delete the personal information we hold about you, and you can stop marketing messages at any time. Some records, such as invoices, must be kept by law. To make a request, contact us using the details below.</p>

<h2>Security</h2>
<p>Our website uses an encrypted (HTTPS) connection and a security firewall, and access to customer data is limited to the staff who need it.</p>

<h2>Children</h2>
<p>Our website is not intended for children. Orders must be placed by adults.</p>

<h2>Changes to this policy</h2>
<p>We may update this policy from time to time. The date at the top shows the latest version.</p>

<h2>Contact</h2>
<p>[breo_info key="company"][breo_if key="address"], [breo_info key="address"][/breo_if][breo_if key="email"] · Email: [breo_info key="email"][/breo_if][breo_if key="phone"] · Phone: [breo_info key="phone"][/breo_if]</p>
',
	),

	'terms-conditions' => array(
		'title'   => 'Terms & Conditions',
		'intro'   => 'The terms that apply when you use our website and buy from us.',
		'content' => '
<p><em>Last updated: [breo_info key="updated"]</em></p>
<p>[breo_info key="site"] is operated by [breo_info key="company"], the authorized distributor of Breo in Bangladesh. By using this website or placing an order, you agree to these terms.</p>

<h2>1. Products and information</h2>
<p>We sell genuine Breo products imported through official channels. We describe our products as accurately as we can, using the manufacturer\'s specifications. Photos are for illustration; colours on screen and packaging details may differ slightly from the actual product.</p>

<h2>2. Health and safety</h2>
<p>Breo products are for relaxation and everyday wellness. They are not medical devices and do not diagnose, treat or cure any disease. Always read the user manual. If you are pregnant, have a pacemaker or other implant, a medical condition, or a recent injury or surgery, ask your doctor before use.</p>

<h2>3. Prices and payment</h2>
<p>Prices are in Bangladeshi Taka (BDT) and may change without notice. The price that applies is the one shown when you place your order. Accepted payment methods: [breo_info key="payments"]. If a product is listed at an obviously wrong price because of an error, we may cancel the order and refund any payment.</p>

<h2>4. Orders</h2>
<p>Your order is an offer to buy. It is accepted when we confirm it and dispatch the product. We may refuse or cancel an order, for example if a product is out of stock, the delivery details can\'t be verified, or we suspect fraud. We may limit the quantity per customer. If we cancel a paid order, we refund you in full.</p>

<h2>5. Delivery, returns and warranty</h2>
<p>Delivery, returns and warranty are covered by our <a href="/shipping-delivery/">Shipping &amp; Delivery</a>, <a href="/returns-refunds/">Returns &amp; Refunds</a> and <a href="/warranty-policy/">Warranty Policy</a> pages, which form part of these terms.</p>

<h2>6. Your account</h2>
<p>If you create an account, keep your login details safe. You are responsible for activity on your account. Tell us straight away if you think someone else has used it.</p>

<h2>7. Intellectual property</h2>
<p>Breo, the breo logo and Breo product names are trademarks of Shenzhen Breo Technology Co., Ltd. The content of this website, including text, design and images, may not be copied or reused without permission.</p>

<h2>8. Limitation of liability</h2>
<p>To the extent allowed by law, we are not liable for indirect or consequential losses arising from the use of this website or our products. Nothing in these terms limits your rights under the consumer protection laws of Bangladesh.</p>

<h2>9. Governing law</h2>
<p>These terms are governed by the laws of Bangladesh. Any dispute will be handled by a competent court in Bangladesh.</p>

<h2>10. Changes</h2>
<p>We may update these terms from time to time. The version on this page at the time of your order applies.</p>

<h2>11. Contact</h2>
<p>[breo_info key="company"][breo_if key="address"], [breo_info key="address"][/breo_if][breo_if key="email"] · Email: [breo_info key="email"][/breo_if][breo_if key="phone"] · Phone: [breo_info key="phone"][/breo_if]</p>
',
	),
);
