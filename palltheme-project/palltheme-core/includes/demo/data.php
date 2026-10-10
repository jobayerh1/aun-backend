<?php
/**
 * Demo dataset for "TechNova Systems" — a fictional company.
 * Every item is tagged on import so it can be removed in one click.
 * People, clients, testimonials and case-study figures are fictional and
 * labelled as demo content; nothing implies a real customer, partnership,
 * certification or reseller status.
 *
 * @package PallthemeCore
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/data-services.php';
require_once __DIR__ . '/data-cases.php';
require_once __DIR__ . '/data-products.php';
require_once __DIR__ . '/data-posts.php';

/**
 * Demo dataset.
 *
 * @return array<string,mixed>
 */
function pallcore_demo_data(): array {
	return array(
		'settings'      => array(
			'company_name'       => 'TechNova Systems',
			'phone'              => '+1 (555) 010-2040',
			'email'              => 'hello@technova.example',
			'address'            => "Level 12, Innovation Tower\n100 Silicon Avenue\nTech City 10001",
			'hours'              => "Mon – Fri | 9:00 – 18:00\nSaturday | 10:00 – 14:00\nEmergency line (managed customers) | 24/7",
			'hours_short'        => 'Mon – Fri 9:00 – 18:00 · 24/7 emergency line',
			'map_query'          => 'Times Square, New York',
			'whatsapp'           => '+15550102040',
			'telegram'           => 'technova_demo',
			'messenger'          => 'technova.demo',
			'careers_email'      => 'careers@technova.example',
			'social_facebook'    => 'https://facebook.com/',
			'social_linkedin'    => 'https://linkedin.com/',
			'social_youtube'     => 'https://youtube.com/',
			'social_x'           => 'https://x.com/',
			'chat_whatsapp'      => '1',
			'chat_telegram'      => '1',
			'chat_phone'         => '1',
			'hero_eyebrow'       => 'Enterprise IT · Cloud · Security',
			'hero_title'         => 'Powering the Future with *Intelligent Technology*',
			'hero_text'          => 'Enterprise-grade infrastructure, cloud, networking, cybersecurity and digital solutions designed to keep modern businesses connected, secure and ready for what comes next.',
			'hero_btn1_text'     => 'Explore Solutions',
			'hero_btn2_text'     => 'Get a Quote',
			'hero_cards'         => "99.99% | Uptime target | speed\n24/7 | Monitoring | headset\n15+ | Years of experience | check",
			'stats'              => "500+ | Projects Delivered\n99.99% | Infrastructure Uptime\n24/7 | Technical Support\n15+ | Years of Experience",
			'search_suggestions' => 'PowerEdge, 25GbE switch, Firewall, NVMe SSD, Managed IT, Cloud migration',
			'intro_pall_service'      => 'Twelve services spanning infrastructure, security, cloud, software and AI — delivered by engineers, documented properly and supported for the long term.',
			'intro_pall_solution'     => 'Reference architectures and delivery experience for the industries we serve, from ISPs and telecom to healthcare and government.',
			'intro_pall_case_study'   => 'A selection of demo projects showing how we approach infrastructure, security and cloud challenges. Clients and figures are fictional examples.',
			'intro_pall_team'         => 'The people behind TechNova Systems (demo team members).',
		),
		'service_cats'  => array( 'Infrastructure', 'Security & Operations', 'Software & AI' ),
		'industries'    => array( 'ISP', 'Telecom', 'Enterprise', 'Education', 'Healthcare', 'Banking', 'Government', 'Retail', 'Manufacturing', 'Data Center' ),
		'team'          => array(
			array( 'Amelia Carter', 'CEO & Managing Director', 'amelia', 'Leadership', 'Amelia sets the company’s direction and works closely with customers on long-term technology strategy. Before co-founding TechNova she led infrastructure programmes for multi-site organisations.', array( array( 'Strategy', '95' ), array( 'Customer success', '92' ) ) ),
			array( 'Rafiq Hasan', 'Chief Technology Officer', 'rafiq', 'Leadership', 'Rafiq leads architecture and engineering standards. He has designed carrier and enterprise networks for more than fifteen years and still enjoys a good packet capture.', array( array( 'Network architecture', '96' ), array( 'Cloud platforms', '88' ) ) ),
			array( 'Kenji Watanabe', 'Network Architect', 'kenji', 'Engineering', 'Kenji designs campus, data center and ISP networks, with a focus on routing, automation and making complex designs easy to operate.', array( array( 'BGP / MPLS', '95' ), array( 'Automation', '86' ) ) ),
			array( 'Daniel Okafor', 'Cloud Engineer', 'daniel', 'Engineering', 'Daniel builds landing zones and container platforms on AWS and Azure, and helps teams migrate workloads without surprises.', array( array( 'AWS / Azure', '92' ), array( 'Terraform', '90' ) ) ),
			array( 'Sofia Martins', 'Cybersecurity Engineer', 'sofia', 'Engineering', 'Sofia runs security assessments and firewall projects and helps customers turn findings into practical, prioritised improvements.', array( array( 'Threat detection', '93' ), array( 'Firewalls', '91' ) ) ),
			array( 'Maya Lindqvist', 'DevOps Engineer', 'maya', 'Engineering', 'Maya automates infrastructure and delivery pipelines, and makes observability a default rather than an afterthought.', array( array( 'CI/CD', '92' ), array( 'Kubernetes', '87' ) ) ),
			array( 'Omar Siddiqui', 'System Administrator', 'omar', 'Operations', 'Omar keeps customer servers patched, backed up and monitored, and is the calm voice on the phone when something goes wrong.', array( array( 'Linux', '94' ), array( 'Backup & recovery', '90' ) ) ),
			array( 'Nadia Rahman', 'Technical Support Lead', 'nadia', 'Operations', 'Nadia leads the support desk, sets response standards and makes sure every ticket ends with a clear explanation, not just a fix.', array( array( 'Service management', '93' ), array( 'Troubleshooting', '91' ) ) ),
		),
		'testimonials'  => array(
			array( 'Alex Morgan', 'Technology Director', 'Demo Company — BlueWave Logistics', 'The network upgrade was planned to the hour. Our teams arrived each morning to a faster network and never noticed the change itself.' ),
			array( 'Jordan Ellis', 'Infrastructure Manager', 'Demo Company — DataSphere', 'We consolidated five generations of hardware into a fraction of the space, and finally have power redundancy we trust.' ),
			array( 'Sam Rivera', 'Head of Platform', 'Demo Company — Primeline Bank', 'Release cycles went from weeks to hours, and our controls are stronger than they were on-premises.' ),
			array( 'Priya Shah', 'Network Operations Lead', 'Demo Company — GlobalNet', 'Peak-hour complaints dropped away. Our NOC now sees problems before our subscribers do.' ),
			array( 'Chris Taylor', 'IT Security Manager', 'Demo Company — Helix Health', 'We finally know what is on our network and who is accessing it — at any hour.' ),
			array( 'Morgan Lee', 'Operations Director', 'Demo Company — Apex Telecom', 'The failover test was the first time we genuinely believed in our recovery plan.' ),
			array( 'Taylor Brooks', 'IT Manager', 'Demo Company — EduCore', 'Campus Wi-Fi handles our busiest lecture halls without complaints. Clear communication throughout.' ),
			array( 'Jamie Patel', 'eCommerce Lead', 'Demo Company — Nova Retail', 'Our store is noticeably faster and the support team answers in minutes, not hours.' ),
		),
		'clients'       => array(
			array( 'GlobalNet', 'ISP', 'wave' ), array( 'Apex Telecom', 'Telecom', 'tri' ), array( 'Nova Retail', 'Retail', 'bars' ), array( 'BlueWave Logistics', 'Enterprise', 'wave' ),
			array( 'Primeline Bank', 'Banking', 'plus' ), array( 'EduCore', 'Education', 'hex' ), array( 'DataSphere', 'Data Center', 'ring' ), array( 'Helix Health', 'Healthcare', 'plus' ),
			array( 'Orbitel', 'Telecom', 'ring' ), array( 'Stratus Energy', 'Manufacturing', 'bars' ),
		),
		'technologies'  => array(
			array( 'Cisco', 'Ci', 'Networking' ), array( 'Huawei', 'Hw', 'Networking' ), array( 'Juniper', 'Jn', 'Networking' ), array( 'MikroTik', 'Mt', 'Networking' ),
			array( 'Fortinet', 'Ft', 'Security' ), array( 'VMware', 'Vm', 'Virtualization' ), array( 'Proxmox', 'Px', 'Virtualization' ), array( 'Microsoft', 'Ms', 'Cloud & OS' ),
			array( 'Linux', 'Lx', 'Cloud & OS' ), array( 'AWS', 'AWS', 'Cloud & OS' ), array( 'Azure', 'Az', 'Cloud & OS' ), array( 'Google Cloud', 'GC', 'Cloud & OS' ),
			array( 'Docker', 'Dk', 'DevOps' ), array( 'Kubernetes', 'K8s', 'DevOps' ), array( 'WordPress', 'WP', 'Web' ), array( 'WooCommerce', 'Woo', 'Web' ),
		),
		'pricing'       => array(
			array( 'Essential', 'Monitoring and helpdesk for small teams.', '$499', '/month', array( 'Up to 25 users', 'Business-hours helpdesk', 'Patch management', 'Monthly health report', 'Email and phone support' ), false, '' ),
			array( 'Business', 'Fully managed IT for growing companies.', '$1,490', '/month', array( 'Up to 100 users', '24/7 monitoring and alerting', 'Managed firewall and backups', 'Quarterly strategy review', '4-hour response target', 'Dedicated account manager' ), true, 'Most popular' ),
			array( 'Enterprise', 'Custom service levels and on-site engineers.', 'Custom', '', array( 'Unlimited users', '24/7 security monitoring', 'On-site engineering days', '1-hour response target', 'Compliance reporting', 'Named technical lead' ), false, '' ),
		),
		'faqs'          => array(
			array( 'General', 'Which regions do you serve?', 'We support customers remotely worldwide and provide on-site engineering in our core regions. Contact us to confirm coverage for your locations.' ),
			array( 'General', 'Are you vendor-neutral?', 'Yes. We work with many manufacturers and recommend based on your requirements and budget, not on sales targets.' ),
			array( 'General', 'How do projects start?', 'With a free discovery call, followed by an assessment and a written proposal with scope, timeline and fixed or estimated costs.' ),
			array( 'General', 'Is the content on this website real?', 'This is a demo website. Company details, clients, testimonials and figures are fictional examples created to showcase the Palltheme theme.' ),
			array( 'Services', 'What does a managed IT contract include?', 'Monitoring, patching, backup checks, helpdesk and regular reviews. Each plan has defined response targets — see the pricing page.' ),
			array( 'Services', 'How fast do you respond to incidents?', 'Response targets depend on plan and priority, from four business hours to one hour around the clock for critical incidents on enterprise plans.' ),
			array( 'Services', 'Do upgrades require downtime?', 'Most changes are made in agreed maintenance windows with tested rollback plans, so users notice little or nothing.' ),
			array( 'Services', 'Can you work with our in-house IT team?', 'Yes. Co-managed arrangements are common: we cover monitoring and after-hours support while your team focuses on projects.' ),
			array( 'Services', 'Do you help with cloud migration?', 'Yes — from readiness assessment and cost modelling to landing zones, migration waves and post-migration optimisation.' ),
			array( 'Hosting', 'What is the difference between VPS and managed hosting?', 'A VPS gives you root access and full control. Managed hosting adds updates, security hardening, monitoring and performance tuning by our team.' ),
			array( 'Hosting', 'Are backups included with hosting?', 'Yes. Hosting plans include daily backups with configurable retention, and restores on request.' ),
			array( 'Hosting', 'Do you migrate existing websites?', 'Yes, migration is included on managed hosting plans and scheduled to minimise downtime.' ),
			array( 'Security', 'Can you guarantee we will never be breached?', 'No honest provider can. We reduce risk measurably, detect issues sooner and help you respond quickly if something happens.' ),
			array( 'Security', 'How often should we test backups?', 'At least quarterly for critical systems, plus after any major change. Untested backups cannot be relied on.' ),
			array( 'Store', 'Do products include a warranty?', 'Hardware ships with the manufacturer warranty shown on each product page. Extended warranty and on-site support options are available.' ),
			array( 'Store', 'Can you configure servers before shipping?', 'Yes. We can set up RAID, BIOS, firmware and hypervisors, then burn-in test each server before dispatch.' ),
			array( 'Store', 'How long does shipping take?', 'In-stock items usually ship within one to two business days. Delivery times depend on destination and are shown at checkout.' ),
			array( 'Store', 'What is your returns policy?', 'Unopened items can be returned within 14 days. Faulty items are repaired, replaced or refunded — see the refund policy for details.' ),
			array( 'Store', 'Do you offer project or volume pricing?', 'Yes. Request a quote with your bill of materials and we will reply within one business day.' ),
			array( 'Support', 'How do I open a support ticket?', 'Email support, use the contact form, call during business hours, or message us on WhatsApp or Telegram. Managed customers also have an emergency line.' ),
			array( 'Support', 'Do you offer remote support?', 'Yes. After you open a ticket, an engineer can start a secure remote session with your permission.' ),
			array( 'Support', 'Do you offer maintenance contracts for hardware?', 'Yes — preventive maintenance, firmware management and extended warranty can be combined into one agreement.' ),
		),
		'jobs'          => array(
			array( 'Senior Network Engineer', 'Engineering', 'Tech City / Hybrid', 'Full-time', 'Design and deploy campus, data center and ISP networks. Strong routing and switching experience required.' ),
			array( 'Cloud DevOps Engineer', 'Engineering', 'Remote', 'Full-time', 'Build landing zones, CI/CD pipelines and Kubernetes platforms on AWS and Azure. Terraform experience required.' ),
			array( 'Security Analyst', 'Security', 'Tech City', 'Full-time', 'Investigate alerts, support incident response and help customers improve their security posture.' ),
			array( 'Systems Administrator (Linux)', 'Operations', 'Tech City / Hybrid', 'Full-time', 'Maintain customer Linux servers: patching, monitoring, backups and troubleshooting.' ),
			array( 'Technical Support Specialist', 'Operations', 'Tech City', 'Full-time', 'Be the first point of contact for customers, resolve issues and escalate with clear documentation.' ),
		),
		'blog_cats'     => array( 'Networking', 'Cloud', 'Cybersecurity', 'Servers', 'Linux', 'AI', 'WordPress', 'WooCommerce', 'DevOps', 'Data Center', 'Tutorials', 'Technology News' ),
		'product_cats'  => array(
			'Servers'     => array(),
			'Networking'  => array( 'Switches', 'Routers', 'Firewalls', 'Network Cards', 'Transceivers', 'Fiber Optics' ),
			'Storage'     => array( 'SSD', 'HDD' ),
			'Components'  => array( 'RAM', 'CPUs', 'GPUs' ),
			'Racks'       => array(),
			'UPS'         => array(),
			'Accessories' => array(),
			'Software'    => array( 'Licenses' ),
			'Support'     => array(),
		),
		'attributes'    => array( 'Processor', 'RAM', 'Storage', 'Interface', 'Port Speed', 'Form Factor', 'Capacity', 'Warranty', 'Length' ),
		'pages'         => array(
			'about'          => array( 'About Us', 'TechNova Systems is a modern technology solutions provider delivering infrastructure, networking, cloud, cybersecurity, server, software and managed IT solutions for businesses of all sizes.' ),
			'pricing'        => array( 'Pricing', 'Managed IT plans with clear response targets and no surprises.' ),
			'faq'            => array( 'FAQ', 'Answers to the questions we hear most often about services, hosting, the store and support.' ),
			'contact'        => array( 'Contact', 'Tell us about your project. An engineer will reply within one business day.' ),
			'support'        => array( 'Support', 'Technical support, documentation and emergency help for TechNova customers.' ),
			'careers'        => array( 'Careers', 'Build the infrastructure the world runs on, with a team that values craft and clear communication.' ),
			'blog'           => array( 'Blog', 'Guides, explainers and news from our engineering team.' ),
			'privacy-policy' => array( 'Privacy Policy', '' ),
			'terms'          => array( 'Terms & Conditions', '' ),
			'cookie-policy'  => array( 'Cookie Policy', '' ),
			'refund-policy'  => array( 'Refund Policy', '' ),
			'media-credits'  => array( 'Media Credits', 'Sources and licences for third-party images and videos used on this website.' ),
		),
	);
}
