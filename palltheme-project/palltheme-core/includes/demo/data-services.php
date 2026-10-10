<?php
/**
 * Demo services and solutions (TechNova Systems — fictional company).
 *
 * Link placeholders resolved by the importer after all content exists:
 *   {svc:slug} {sol:slug} {case:slug} {page:slug} {cat:slug} {pcat:slug}
 *
 * @package PallthemeCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Paragraph/heading/list helpers for block markup.
 */
function pallcore_demo_p( string ...$paras ): string {
	return implode( '', array_map( static fn( $t ) => "<!-- wp:paragraph -->\n<p>{$t}</p>\n<!-- /wp:paragraph -->\n", $paras ) );
}

/**
 * Heading block.
 */
function pallcore_demo_h( string $text, int $level = 2 ): string {
	$attr = 2 === $level ? '' : ' {"level":' . $level . '}';
	return "<!-- wp:heading{$attr} -->\n<h{$level} class=\"wp-block-heading\">{$text}</h{$level}>\n<!-- /wp:heading -->\n";
}

/**
 * List block.
 *
 * @param string[] $items Items (HTML allowed).
 */
function pallcore_demo_ul( array $items ): string {
	return "<!-- wp:list -->\n<ul class=\"wp-block-list\"><li>" . implode( '</li><li>', $items ) . "</li></ul>\n<!-- /wp:list -->\n";
}

/**
 * Services.
 *
 * @return array<int,array<string,mixed>>
 */
function pallcore_demo_services(): array {
	$p  = 'pallcore_demo_p';
	$h  = 'pallcore_demo_h';
	$ul = 'pallcore_demo_ul';

	return array(
		array(
			'title'    => 'Network Infrastructure',
			'slug'     => 'network-infrastructure',
			'cat'      => 'Infrastructure',
			'icon'     => 'network',
			'photo'    => 'svc-network',
			'cover'    => 'cover-network-infrastructure',
			'excerpt'  => 'Design, deployment and lifecycle management of high-performance campus, data center and WAN networks.',
			'body'     => $p( 'A network should be the part of your business nobody has to think about. We design switching, routing and wireless infrastructure that is fast, segmented and observable — then document it so your team can run it with confidence.', 'Every engagement starts with a site survey and traffic baseline. From there we produce a vendor-neutral design covering topology, addressing, VLAN and VRF strategy, redundancy and capacity headroom for the next three to five years.' )
				. $h( 'What we deliver' )
				. $ul( array( 'Campus LAN with 1/10/25GbE access, distribution and core layers', 'Data center spine-leaf fabrics with 25/100GbE', 'SD-WAN and MPLS integration for multi-site organisations', 'Enterprise Wi-Fi 6/6E design with predictive and on-site surveys', 'Network monitoring, configuration backup and change control' ) )
				. $p( 'Planning a speed upgrade? Read our guide to <a href="{post:understanding-10gbe-vs-25gbe}">10GbE vs 25GbE</a> or browse <a href="{pcat:switches}">enterprise switches</a> in our store.' ),
			'features' => array( 'Site survey and traffic baseline', 'Vendor-neutral high-level and low-level design', 'Staged, change-controlled cut-overs', 'Redundant core and uplinks (no single points of failure)', 'Monitoring with alerting and capacity reporting', 'As-built diagrams and runbooks' ),
			'benefits' => array( array( 'Predictable performance', 'Capacity planned from real traffic data, not guesswork.' ), array( 'Faster troubleshooting', 'Segmentation, monitoring and documentation cut time to resolve.' ), array( 'Room to grow', 'Designs leave headroom for new sites, users and applications.' ) ),
			'tech'     => array( 'Cisco', 'Juniper', 'MikroTik', 'Aruba', 'OSPF / BGP', 'VXLAN EVPN', 'SD-WAN' ),
			'faq'      => array( array( 'Can you work with our existing switches?', 'Yes. We assess what you have, reuse what is still supported and plan replacements only where they add value.' ), array( 'Do upgrades cause downtime?', 'Cut-overs are scheduled in maintenance windows with tested rollback plans. Most users notice nothing.' ), array( 'Do you provide ongoing management?', 'Yes — pair this service with Managed IT for 24/7 monitoring, patching and configuration management.' ) ),
			'products' => array( 'TN-SW-C9300-48', 'TN-SW-25G-48', 'TN-SW-10G-24', 'TN-SFP-10G-SR' ),
		),
		array(
			'title'    => 'Server Solutions',
			'slug'     => 'server-solutions',
			'cat'      => 'Infrastructure',
			'icon'     => 'server',
			'photo'    => 'svc-server',
			'cover'    => 'cover-server-solutions',
			'excerpt'  => 'Right-sized rack, tower and hyper-converged servers — specified, configured, burn-in tested and supported.',
			'body'     => $p( 'Over-specified servers waste budget; under-specified servers waste everyone else’s time. We size compute, memory and storage from your actual workloads, then deliver systems that arrive configured, tested and ready to rack.', 'We support physical, virtualised and hyper-converged deployments and integrate with your existing backup, monitoring and identity platforms.' )
				. $h( 'From specification to production' )
				. $ul( array( 'Workload profiling and capacity modelling', 'Hardware selection across leading vendors', 'RAID, BIOS, firmware and hypervisor configuration', '48-hour burn-in testing before dispatch', 'Rack installation, cabling and labelling', 'Lifecycle refresh planning' ) )
				. $p( 'See our <a href="{post:server-infrastructure-planning-guide}">server planning guide</a> or explore <a href="{pcat:servers}">servers in stock</a>.' ),
			'features' => array( 'Workload-based sizing', 'Pre-configured RAID, BIOS and firmware', 'Hypervisor installation (Proxmox, VMware, Hyper-V)', 'Burn-in testing and test report', 'Out-of-band management setup', 'Warranty and on-site support options' ),
			'benefits' => array( array( 'Lower total cost', 'Pay for the capacity you need, with a clear upgrade path.' ), array( 'Faster go-live', 'Servers arrive ready to rack — no first-day surprises.' ), array( 'Fewer failures', 'Burn-in testing catches early-life hardware faults.' ) ),
			'tech'     => array( 'Dell PowerEdge', 'HPE ProLiant', 'Lenovo ThinkSystem', 'Proxmox VE', 'VMware', 'Hyper-V' ),
			'faq'      => array( array( 'Which vendors do you work with?', 'We are vendor-neutral and recommend based on workload, support needs and budget.' ), array( 'Can you migrate our existing virtual machines?', 'Yes. We plan and execute VM migrations with minimal downtime.' ), array( 'Do you offer extended warranty?', 'Yes, including next-business-day and four-hour on-site options where available.' ) ),
			'products' => array( 'TN-SRV-R760', 'TN-SRV-DL380', 'TN-SRV-VH1U', 'TN-RAM-DDR5' ),
		),
		array(
			'title'    => 'Cloud Computing',
			'slug'     => 'cloud-computing',
			'cat'      => 'Infrastructure',
			'icon'     => 'cloud',
			'photo'    => 'svc-cloud',
			'cover'    => 'cover-cloud-solutions',
			'excerpt'  => 'Migrate, modernise and operate workloads across public, private and hybrid cloud with clear cost control.',
			'body'     => $p( 'Cloud is a tool, not a destination. We help you decide which workloads belong in AWS, Azure, Google Cloud or your own private cloud — and build landing zones that are secure, observable and affordable from day one.' )
				. $h( 'Our cloud services' )
				. $ul( array( 'Cloud readiness assessment and business case', 'Landing zone design: identity, networking, logging, guardrails', 'Lift-and-shift and re-platform migrations', 'Kubernetes and container platforms', 'FinOps: tagging, budgets and right-sizing', 'Hybrid connectivity: VPN and dedicated interconnects' ) )
				. $p( 'Start with our <a href="{post:cloud-migration-best-practices}">cloud migration best practices</a>, or read how a fictional demo bank moved to hybrid cloud in our <a href="{case:secure-cloud-migration}">case study</a>.' ),
			'features' => array( 'Readiness assessment and TCO model', 'Secure landing zones with policy-as-code', 'Wave-based migration planning', 'Containers and Kubernetes', 'Cost monitoring and optimisation', 'Hybrid networking' ),
			'benefits' => array( array( 'Cost visibility', 'Budgets, tagging and reports show exactly where spend goes.' ), array( 'Security by default', 'Guardrails prevent common misconfigurations before they happen.' ), array( 'Faster delivery', 'Automated environments shorten release cycles.' ) ),
			'tech'     => array( 'AWS', 'Microsoft Azure', 'Google Cloud', 'Terraform', 'Kubernetes', 'Docker' ),
			'faq'      => array( array( 'Will moving to the cloud save money?', 'Sometimes. We build an honest cost model first; some workloads are cheaper on-premises.' ), array( 'Do you support multi-cloud?', 'Yes, with consistent identity, networking and monitoring across providers.' ), array( 'How long does a migration take?', 'Small estates take weeks; larger ones run in waves over several months.' ) ),
			'products' => array( 'TN-SVC-CLOUD-MIG', 'TN-SRV-VH1U' ),
		),
		array(
			'title'    => 'Cybersecurity',
			'slug'     => 'cybersecurity',
			'cat'      => 'Security & Operations',
			'icon'     => 'shield',
			'photo'    => 'svc-security',
			'cover'    => 'cover-cybersecurity',
			'excerpt'  => 'Risk assessments, next-generation firewalls, zero-trust access and security monitoring that fit how you work.',
			'body'     => $p( 'Security is a set of habits as much as a set of products. We combine practical controls — segmentation, multi-factor authentication, patching and monitoring — with the right technology for your risk profile and budget.' )
				. $h( 'How we help' )
				. $ul( array( 'Security assessment and prioritised roadmap', 'Next-generation firewall design and deployment', 'Zero-trust network access and MFA rollout', 'Endpoint protection and vulnerability management', 'Security monitoring and incident response support', 'Policies, awareness training and tabletop exercises' ) )
				. $p( 'New to the topic? Our <a href="{post:cybersecurity-fundamentals-for-businesses}">cybersecurity fundamentals</a> article is a good starting point. You can also book a <a href="{product:TN-SVC-SEC-ASSESS}">cybersecurity assessment</a> online.' ),
			'features' => array( 'Risk-based security assessment', 'Firewall and segmentation design', 'MFA and zero-trust access', 'Vulnerability scanning and patch reporting', 'Log collection and alerting', 'Incident response playbooks' ),
			'benefits' => array( array( 'Reduced attack surface', 'Segmentation and least privilege limit what an attacker can reach.' ), array( 'Earlier detection', 'Centralised logging surfaces suspicious activity sooner.' ), array( 'Clear priorities', 'A roadmap ranks actions by risk reduction per dollar.' ) ),
			'tech'     => array( 'Fortinet', 'Palo Alto Networks', 'Microsoft Defender', 'Wazuh', 'CrowdStrike', 'Zero Trust' ),
			'faq'      => array( array( 'Do you guarantee we will never be breached?', 'No one can honestly guarantee that. We reduce risk measurably and help you respond quickly if something happens.' ), array( 'Can you help with compliance?', 'We map controls to the frameworks you follow and provide evidence for audits.' ), array( 'Is a firewall enough?', 'No — it is one layer. Identity, patching, backups and monitoring matter just as much.' ) ),
			'products' => array( 'TN-FW-100F', 'TN-FW-40F', 'TN-SVC-SEC-ASSESS', 'TN-LIC-EPP50' ),
		),
		array(
			'title'    => 'Managed IT Services',
			'slug'     => 'managed-it-services',
			'cat'      => 'Security & Operations',
			'icon'     => 'settings',
			'photo'    => 'svc-managed',
			'cover'    => 'cover-managed-it',
			'excerpt'  => 'Proactive monitoring, patching, helpdesk and strategic IT management for a predictable monthly fee.',
			'body'     => $p( 'Managed IT gives you a full operations team without hiring one. We monitor your servers, network and cloud services around the clock, apply updates safely, answer your users’ tickets and meet with you every quarter to plan what comes next.' )
				. $h( 'Included in every plan' )
				. $ul( array( '24/7 monitoring and alerting', 'Patch and firmware management with maintenance windows', 'Backup monitoring and restore testing', 'Helpdesk for your staff by phone, email and chat', 'Asset inventory and licence tracking', 'Quarterly service and roadmap reviews' ) )
				. $p( 'Compare plans on our <a href="{page:pricing}">pricing page</a> or <a href="{page:contact}">talk to an expert</a>.' ),
			'features' => array( '24/7 monitoring and alerting', 'Patch management', 'Helpdesk with defined SLAs', 'Backup verification', 'Asset and licence management', 'Quarterly reviews' ),
			'benefits' => array( array( 'Predictable cost', 'A fixed monthly fee replaces surprise repair bills.' ), array( 'Fewer outages', 'Problems are fixed before users notice them.' ), array( 'Strategic guidance', 'Regular reviews align IT with business plans.' ) ),
			'tech'     => array( 'RMM', 'Zabbix', 'Grafana', 'Microsoft 365', 'ITIL' ),
			'faq'      => array( array( 'What response times do you offer?', 'Response targets depend on plan and priority — see the pricing page for details.' ), array( 'Can you work alongside our IT staff?', 'Yes. Co-managed IT is common; we handle monitoring and after-hours cover while your team focuses on projects.' ), array( 'Is there a minimum contract?', 'Plans are typically annual; we discuss terms during onboarding.' ) ),
			'products' => array( 'TN-SVC-MANAGED-NET', 'TN-SVC-LINUX-SUP' ),
		),
		array(
			'title'    => 'Data Center Solutions',
			'slug'     => 'data-center-solutions',
			'cat'      => 'Infrastructure',
			'icon'     => 'rack',
			'photo'    => 'svc-datacenter',
			'cover'    => 'cover-data-center',
			'excerpt'  => 'Rack, power, cooling, structured cabling and monitoring for efficient, resilient server rooms and data centers.',
			'body'     => $p( 'Whether you are tidying a single server room or building a multi-rack facility, the fundamentals are the same: clean power, controlled airflow, organised cabling and good visibility. We design and build all four.' )
				. $h( 'Scope' )
				. $ul( array( 'Rack layout, hot/cold aisle and containment', 'Power design: UPS sizing, PDUs and redundancy', 'Structured copper and fiber cabling', 'Environmental monitoring: temperature, humidity, leaks', 'Remote hands and migration services' ) )
				. $p( 'Read <a href="{post:data-center-redundancy-explained}">data center redundancy explained</a> for the difference between N+1 and 2N designs.' ),
			'features' => array( 'Rack and floor layout', 'Power and cooling design', 'Structured cabling', 'Environmental monitoring', 'Labelling and documentation', 'Migration planning' ),
			'benefits' => array( array( 'Higher resilience', 'Redundant power and cooling protect critical workloads.' ), array( 'Lower energy use', 'Containment and airflow management reduce cooling costs.' ), array( 'Easier operations', 'Clean cabling and labels speed up every change.' ) ),
			'tech'     => array( 'APC / Schneider Electric', 'Eaton', 'Vertiv', 'OM4 / OS2 fiber', 'DCIM' ),
			'faq'      => array( array( 'Do you work on live facilities?', 'Yes, with strict method statements and change control.' ), array( 'Can you help us move to a colocation facility?', 'Yes — we plan, pack, move and re-commission equipment.' ), array( 'What is a good PUE?', 'It depends on climate and design; we measure and improve it step by step.' ) ),
			'products' => array( 'TN-RACK-42U', 'TN-UPS-3K', 'TN-PDU-16A', 'TN-FIB-OM4' ),
		),
		array(
			'title'    => 'Software Development',
			'slug'     => 'software-development',
			'cat'      => 'Software & AI',
			'icon'     => 'code',
			'photo'    => 'svc-software',
			'cover'    => 'cover-software-development',
			'excerpt'  => 'Custom portals, APIs, integrations and internal tools built with modern, maintainable engineering practices.',
			'body'     => $p( 'Off-the-shelf software rarely fits perfectly. We build the pieces in between — customer portals, internal dashboards, integrations between systems and APIs for partners — with automated testing and documentation as standard.' )
				. $h( 'How we build' )
				. $ul( array( 'Discovery workshops and clickable prototypes', 'Two-week sprints with demos', 'Automated tests and CI/CD pipelines', 'Security reviews and dependency scanning', 'Handover documentation and support options' ) ),
			'features' => array( 'Web portals and dashboards', 'REST and GraphQL APIs', 'System integrations', 'WordPress and WooCommerce development', 'CI/CD and automated testing', 'Maintenance and support' ),
			'benefits' => array( array( 'Software that fits', 'Built around your processes, not the other way round.' ), array( 'Lower long-term cost', 'Clean code and tests make changes cheaper.' ), array( 'Visible progress', 'Sprint demos keep everyone aligned.' ) ),
			'tech'     => array( 'PHP', 'Python', 'TypeScript', 'React', 'WordPress', 'WooCommerce', 'PostgreSQL' ),
			'faq'      => array( array( 'Who owns the code?', 'You do. Source code and documentation are handed over in full.' ), array( 'Do you build WooCommerce stores?', 'Yes — including integrations with ERPs, payment gateways and logistics providers.' ), array( 'Can you take over an existing project?', 'Yes, after a short code and architecture review.' ) ),
			'products' => array(),
		),
		array(
			'title'    => 'IT Consultancy',
			'slug'     => 'it-consultancy',
			'cat'      => 'Software & AI',
			'icon'     => 'consult',
			'photo'    => 'svc-consultancy',
			'cover'    => 'cover-it-consultancy',
			'excerpt'  => 'Independent advice on strategy, architecture, vendor selection and technology roadmaps.',
			'body'     => $p( 'Sometimes you need an experienced, independent view before committing budget. Our consultants review your current estate, interview stakeholders and deliver a practical roadmap with costs, risks and priorities.' )
				. $h( 'Typical engagements' )
				. $ul( array( 'IT strategy and three-year roadmaps', 'Architecture reviews and health checks', 'Vendor selection and tender support', 'Due diligence for mergers and acquisitions', 'Interim technical leadership' ) ),
			'features' => array( 'Current-state assessment', 'Stakeholder interviews', 'Options analysis with costs', 'Prioritised roadmap', 'Board-ready summary', 'Optional delivery support' ),
			'benefits' => array( array( 'Better decisions', 'Clear options with costs and risks.' ), array( 'Independent view', 'Recommendations are not tied to a product quota.' ), array( 'Faster alignment', 'A shared roadmap ends circular debates.' ) ),
			'tech'     => array( 'TOGAF', 'ITIL', 'Cloud adoption frameworks', 'Risk assessment' ),
			'faq'      => array( array( 'How long does an assessment take?', 'Typically two to six weeks depending on scope.' ), array( 'Will you implement the recommendations?', 'If you wish — but the roadmap is yours to execute with any partner.' ), array( 'Do you sign NDAs?', 'Yes, always before any detailed discussion.' ) ),
			'products' => array(),
		),
		array(
			'title'    => 'Backup & Disaster Recovery',
			'slug'     => 'backup-disaster-recovery',
			'cat'      => 'Security & Operations',
			'icon'     => 'backup',
			'photo'    => 'svc-backup',
			'cover'    => 'cover-backup-recovery',
			'excerpt'  => 'Immutable backups, off-site replication and tested recovery plans with agreed recovery objectives.',
			'body'     => $p( 'Backups only matter on the day you need to restore. We design backup and disaster recovery around clear recovery point and recovery time objectives, keep immutable copies out of reach of ransomware, and test restores on a schedule.' )
				. $h( 'What is included' )
				. $ul( array( 'RPO/RTO workshop per system', '3-2-1-1-0 backup architecture', 'Immutable and off-site copies', 'Disaster recovery runbooks', 'Scheduled restore tests with reports' ) )
				. $p( 'Learn more in <a href="{post:backup-and-disaster-recovery-planning}">backup and disaster recovery planning</a>.' ),
			'features' => array( 'Recovery objectives per system', 'Immutable backup storage', 'Off-site and cloud replication', 'Ransomware-resilient design', 'DR runbooks', 'Restore testing' ),
			'benefits' => array( array( 'Confidence', 'Tested restores prove your data can come back.' ), array( 'Ransomware resilience', 'Immutable copies cannot be encrypted or deleted by attackers.' ), array( 'Shorter outages', 'Runbooks turn a crisis into a checklist.' ) ),
			'tech'     => array( 'Veeam', 'Proxmox Backup Server', 'Synology', 'S3 Object Lock', 'ZFS' ),
			'faq'      => array( array( 'How often should we test restores?', 'At least quarterly for critical systems, and after major changes.' ), array( 'What is immutable backup?', 'Backup data that cannot be modified or deleted until its retention period ends.' ), array( 'Can you back up Microsoft 365?', 'Yes, along with on-premises and cloud workloads.' ) ),
			'products' => array( 'TN-NAS-8BAY', 'TN-HDD-18T', 'TN-UPS-3K' ),
		),
		array(
			'title'    => 'VPS & Hosting',
			'slug'     => 'vps-hosting',
			'cat'      => 'Infrastructure',
			'icon'     => 'hosting',
			'photo'    => 'svc-hosting',
			'cover'    => 'cover-vps-hosting',
			'excerpt'  => 'High-availability VPS, dedicated servers and managed WordPress/WooCommerce hosting on NVMe storage.',
			'body'     => $p( 'Our hosting platform runs on redundant NVMe-backed clusters with daily backups and proactive monitoring. Choose self-managed VPS for full control or managed hosting where we handle updates, security and performance tuning.' )
				. $h( 'Hosting options' )
				. $ul( array( 'Self-managed Linux VPS with root access', 'Managed VPS with patching and monitoring', 'Dedicated servers', 'Managed WordPress and WooCommerce hosting', 'Private cloud clusters for larger workloads' ) )
				. $p( 'Running a store? See <a href="{post:running-woocommerce-at-scale}">WooCommerce at scale</a>.' ),
			'features' => array( 'NVMe storage', 'Daily backups', 'DDoS-mitigated network', 'Free TLS certificates', 'Staging environments', 'Optional managed updates' ),
			'benefits' => array( array( 'Speed', 'NVMe storage and tuned stacks keep sites responsive.' ), array( 'Peace of mind', 'Backups and monitoring are included.' ), array( 'Room to scale', 'Upgrade resources without migrating.' ) ),
			'tech'     => array( 'Linux', 'Proxmox', 'Nginx', 'PHP-FPM', 'Redis', 'MariaDB' ),
			'faq'      => array( array( 'Do you include backups?', 'Yes — daily backups with configurable retention.' ), array( 'Can I get root access?', 'Yes on self-managed VPS plans.' ), array( 'Do you migrate existing sites?', 'Yes, free migration is included on managed plans.' ) ),
			'products' => array( 'TN-SVC-LINUX-SUP' ),
		),
		array(
			'title'    => 'AI Solutions',
			'slug'     => 'ai-solutions',
			'cat'      => 'Software & AI',
			'icon'     => 'ai',
			'photo'    => 'svc-ai',
			'cover'    => 'cover-ai-solutions',
			'excerpt'  => 'Practical AI: GPU infrastructure, private language models, document automation and analytics with measurable value.',
			'body'     => $p( 'AI projects succeed when they start from a business problem, not a model. We help you identify use cases with clear value, prepare data, choose between hosted and private models and build the GPU or cloud infrastructure to run them securely.' )
				. $h( 'Capabilities' )
				. $ul( array( 'Use-case discovery and ROI estimation', 'Private LLM deployment on your infrastructure', 'Retrieval-augmented search over your documents', 'GPU server sizing and deployment', 'MLOps pipelines and monitoring', 'Responsible AI guidelines' ) )
				. $p( 'See how machine learning can predict network issues in <a href="{post:aiops-predicting-network-outages}">our AIOps article</a>.' ),
			'features' => array( 'Use-case discovery', 'Private and hosted model options', 'Document and knowledge search', 'GPU infrastructure', 'MLOps', 'Governance guidelines' ),
			'benefits' => array( array( 'Real productivity gains', 'Focus on tasks where AI measurably saves time.' ), array( 'Data stays private', 'Run models on infrastructure you control.' ), array( 'Responsible adoption', 'Clear policies for accuracy, privacy and oversight.' ) ),
			'tech'     => array( 'NVIDIA GPUs', 'PyTorch', 'Open-weight LLMs', 'Vector databases', 'Kubernetes' ),
			'faq'      => array( array( 'Do we need our own GPUs?', 'Not always. Hosted APIs suit many use cases; private models suit sensitive data.' ), array( 'How do you measure success?', 'With agreed metrics such as time saved, accuracy and adoption.' ), array( 'Is our data used to train public models?', 'Not in our deployments — we design for data privacy.' ) ),
			'products' => array( 'TN-GPU-L40S', 'TN-SRV-R760' ),
		),
		array(
			'title'    => 'IoT Solutions',
			'slug'     => 'iot-solutions',
			'cat'      => 'Software & AI',
			'icon'     => 'iot',
			'photo'    => 'svc-iot',
			'cover'    => 'cover-iot-solutions',
			'excerpt'  => 'Secure sensor networks, edge gateways and dashboards that turn connected devices into useful data.',
			'body'     => $p( 'From temperature sensors in a server room to vibration monitoring on a production line, IoT projects need reliable connectivity, secure devices and dashboards people actually use. We deliver all three.' )
				. $h( 'Typical projects' )
				. $ul( array( 'Environmental monitoring for facilities', 'Asset tracking and utilisation', 'Predictive maintenance for equipment', 'Smart building integrations', 'Edge computing for low-latency processing' ) ),
			'features' => array( 'Device selection and pilots', 'Secure onboarding and segmentation', 'Edge gateways', 'Dashboards and alerts', 'Integration with business systems', 'Device lifecycle management' ),
			'benefits' => array( array( 'Better visibility', 'Real-time data replaces manual checks.' ), array( 'Less downtime', 'Early warnings enable planned maintenance.' ), array( 'Secure by design', 'Devices are isolated from critical networks.' ) ),
			'tech'     => array( 'MQTT', 'LoRaWAN', 'Node-RED', 'InfluxDB', 'Grafana' ),
			'faq'      => array( array( 'Are IoT devices a security risk?', 'They can be. We segment them, manage credentials and update firmware.' ), array( 'Can we start small?', 'Yes — most projects begin with a pilot of a few devices.' ), array( 'Do you integrate with our ERP?', 'Yes, via APIs or middleware.' ) ),
			'products' => array( 'TN-AP-WIFI6E', 'TN-SW-POE24' ),
		),
	);
}

/**
 * Solutions (by industry).
 *
 * @return array<int,array<string,mixed>>
 */
function pallcore_demo_solutions(): array {
	return array(
		array(
			'title' => 'ISP Network Solutions', 'slug' => 'isp-network-solutions', 'industry' => 'ISP', 'icon' => 'telecom', 'photo' => 'sol-isp', 'cover' => 'cover-isp',
			'excerpt' => 'Carrier-grade routing, BNG, fiber access and NOC tooling for internet service providers of every size.',
			'problem' => 'Subscriber growth outpaces core capacity, BGP policies become hard to manage, and outages are discovered by customers before the NOC sees them.',
			'approach' => 'We design a scalable core and aggregation layer with redundant upstream connectivity, automate subscriber provisioning and give the NOC real-time visibility.',
			'architecture' => 'Redundant BGP edge routers with diverse upstreams, an MPLS or segment-routing core, BNG for subscriber management, GPON/XGS-PON access and a monitoring stack with flow analytics.',
			'implementation' => 'Lab validation of configurations, staged migration by POP, automated config templates and a 30-day hypercare period after each cut-over.',
			'features' => array( 'BGP edge and peering design', 'BNG and subscriber management', 'Fiber access (GPON/XGS-PON)', 'Flow analytics and DDoS detection', 'NOC dashboards and alerting' ),
			'tech' => array( 'Juniper', 'MikroTik', 'Huawei', 'BGP', 'MPLS', 'NetFlow' ),
			'services' => array( 'network-infrastructure', 'managed-it-services', 'data-center-solutions' ),
			'products' => array( 'TN-RT-CCR2116', 'TN-RT-MX204', 'TN-SFP-25G-SR', 'TN-QSFP-100G' ),
			'cases' => array( 'isp-network-expansion' ),
		),
		array(
			'title' => 'Telecom Infrastructure', 'slug' => 'telecom-infrastructure', 'industry' => 'Telecom', 'icon' => 'telecom', 'photo' => 'sol-telecom', 'cover' => 'cover-isp',
			'excerpt' => 'Transport, backhaul and data center infrastructure for telecom operators and tower companies.',
			'problem' => 'Mobile data growth stresses backhaul links, while legacy transport equipment is expensive to maintain and hard to automate.',
			'approach' => 'We modernise transport with high-capacity packet networks, consolidate edge sites and introduce automation for provisioning and assurance.',
			'architecture' => '25/100GbE aggregation rings, timing-aware switching at cell sites, edge data centers for network functions and centralised telemetry.',
			'implementation' => 'Site surveys, phased ring migrations, acceptance testing per site and knowledge transfer to field teams.',
			'features' => array( 'Backhaul capacity planning', 'Edge data center design', 'Network automation', 'Telemetry and assurance', 'Field team enablement' ),
			'tech' => array( 'Huawei', 'Juniper', 'Cisco', '25/100GbE', 'Segment Routing' ),
			'services' => array( 'network-infrastructure', 'data-center-solutions', 'it-consultancy' ),
			'products' => array( 'TN-SW-25G-48', 'TN-QSFP-100G', 'TN-FIB-MPO12' ),
			'cases' => array( 'isp-network-expansion' ),
		),
		array(
			'title' => 'Enterprise IT', 'slug' => 'enterprise-it', 'industry' => 'Enterprise', 'icon' => 'building', 'photo' => 'sol-enterprise', 'cover' => 'cover-enterprise',
			'excerpt' => 'Secure, well-run infrastructure for multi-site organisations — from campus networks to hybrid cloud.',
			'problem' => 'Years of incremental change leave enterprises with inconsistent networks, aging servers and shadow IT that nobody fully understands.',
			'approach' => 'We standardise the foundation — network, identity, compute and backup — and introduce managed operations so internal teams can focus on projects.',
			'architecture' => 'Segmented campus networks, SD-WAN between sites, a hybrid compute platform, centralised identity with MFA and immutable backups.',
			'implementation' => 'Assessment, prioritised roadmap, quick wins in the first 90 days, then site-by-site standardisation.',
			'features' => array( 'Standardised network and security', 'Hybrid cloud platform', 'Identity and MFA', 'Managed operations', 'Roadmap and governance' ),
			'tech' => array( 'Cisco', 'Fortinet', 'Microsoft 365', 'Azure', 'VMware', 'Proxmox' ),
			'services' => array( 'managed-it-services', 'network-infrastructure', 'cloud-computing' ),
			'products' => array( 'TN-SW-C9300-48', 'TN-FW-100F', 'TN-SRV-DL380' ),
			'cases' => array( 'enterprise-network-infrastructure-upgrade', 'data-center-modernization' ),
		),
		array(
			'title' => 'Education & Campus', 'slug' => 'education-campus', 'industry' => 'Education', 'icon' => 'education', 'photo' => 'sol-education', 'cover' => 'cover-education',
			'excerpt' => 'High-density Wi-Fi, secure student and staff access and reliable learning platforms for schools and universities.',
			'problem' => 'Thousands of devices connect at once in lecture halls, budgets are tight and IT teams are small.',
			'approach' => 'We design for density, separate student, staff and IoT traffic, and automate onboarding so the network scales without extra headcount.',
			'architecture' => 'Wi-Fi 6/6E with high-density design, PoE access switching, role-based access, content filtering and cloud-managed operations.',
			'implementation' => 'Predictive and on-site surveys, installation during term breaks and staff training before students return.',
			'features' => array( 'High-density wireless', 'Role-based access', 'Device onboarding', 'Content filtering', 'Cloud-managed operations' ),
			'tech' => array( 'Aruba', 'Ubiquiti', 'Cisco Meraki', 'RADIUS', '802.1X' ),
			'services' => array( 'network-infrastructure', 'cybersecurity', 'managed-it-services' ),
			'products' => array( 'TN-AP-WIFI6E', 'TN-SW-POE24', 'TN-FW-40F' ),
			'cases' => array(),
		),
		array(
			'title' => 'Healthcare IT', 'slug' => 'healthcare-it', 'industry' => 'Healthcare', 'icon' => 'health', 'photo' => 'sol-healthcare', 'cover' => 'cover-healthcare',
			'excerpt' => 'Secure clinical networks, imaging storage and always-on systems for hospitals and clinics.',
			'problem' => 'Clinical devices share networks with office PCs, imaging data grows quickly and downtime directly affects patient care.',
			'approach' => 'We segment clinical systems, protect patient data and design redundant infrastructure for the applications clinicians rely on.',
			'architecture' => 'Clinical network segmentation, redundant core and power, tiered storage for imaging, monitored backups and secure remote access.',
			'implementation' => 'Close coordination with clinical engineering, out-of-hours changes and documented validation for each system.',
			'features' => array( 'Clinical device segmentation', 'Imaging storage tiers', 'High availability', 'Secure remote access', 'Security monitoring' ),
			'tech' => array( 'Fortinet', 'NAC', 'VMware', 'Veeam', 'HL7 / DICOM integration' ),
			'services' => array( 'cybersecurity', 'backup-disaster-recovery', 'server-solutions' ),
			'products' => array( 'TN-FW-100F', 'TN-NAS-8BAY', 'TN-SSD-NVME-U2' ),
			'cases' => array( 'enterprise-cybersecurity-deployment' ),
		),
		array(
			'title' => 'Banking & Financial Services', 'slug' => 'banking-financial-services', 'industry' => 'Banking', 'icon' => 'bank', 'photo' => 'sol-banking', 'cover' => 'cover-banking',
			'excerpt' => 'Resilient, audited infrastructure with strong segmentation, encryption and monitoring.',
			'problem' => 'Financial institutions must deliver digital services quickly while meeting strict regulatory, availability and audit requirements.',
			'approach' => 'We build compliant platforms with policy-as-code, encryption everywhere, strong identity controls and full audit trails.',
			'architecture' => 'Segmented networks, hybrid cloud landing zones with guardrails, hardware security modules, centralised logging and active-active data centers.',
			'implementation' => 'Controls mapped to your regulatory framework from day one, with evidence collected automatically for audits.',
			'features' => array( 'Compliance-mapped controls', 'Encryption and key management', 'Privileged access management', 'Centralised logging', 'Active-active resilience' ),
			'tech' => array( 'Azure', 'AWS', 'Terraform', 'HashiCorp Vault', 'SIEM' ),
			'services' => array( 'cloud-computing', 'cybersecurity', 'backup-disaster-recovery' ),
			'products' => array( 'TN-FW-100F', 'TN-SRV-R760' ),
			'cases' => array( 'secure-cloud-migration', 'disaster-recovery-implementation' ),
		),
		array(
			'title' => 'Government & Public Sector', 'slug' => 'government-public-sector', 'industry' => 'Government', 'icon' => 'government', 'photo' => 'sol-government', 'cover' => 'cover-government',
			'excerpt' => 'Secure, sovereign infrastructure and citizen-service platforms built to policy.',
			'problem' => 'Public bodies must modernise services for citizens while protecting sensitive data and following procurement and security policies.',
			'approach' => 'We design sovereign and hybrid platforms, harden systems against current threats and document everything for oversight bodies.',
			'architecture' => 'On-premises or sovereign cloud hosting, zero-trust access, encrypted inter-agency links and centrally monitored security controls.',
			'implementation' => 'Policy-aligned design reviews, security testing before go-live and structured handover to internal teams.',
			'features' => array( 'Sovereign hosting options', 'Zero-trust access', 'Encrypted interconnects', 'Security testing', 'Thorough documentation' ),
			'tech' => array( 'Linux', 'Proxmox', 'Kubernetes', 'Fortinet', 'Open standards' ),
			'services' => array( 'cybersecurity', 'data-center-solutions', 'it-consultancy' ),
			'products' => array( 'TN-SRV-VH1U', 'TN-FW-100F' ),
			'cases' => array(),
		),
		array(
			'title' => 'Retail & eCommerce', 'slug' => 'retail-ecommerce', 'industry' => 'Retail', 'icon' => 'retail', 'photo' => 'sol-retail', 'cover' => 'cover-retail',
			'excerpt' => 'Reliable store networks, secure payments and fast online stores that scale with demand.',
			'problem' => 'Stores need dependable connectivity for payments, while online channels must stay fast during peak campaigns.',
			'approach' => 'We standardise store networks, isolate payment systems and optimise eCommerce platforms for performance and scale.',
			'architecture' => 'SD-WAN with LTE backup in stores, segmented POS networks, managed Wi-Fi, CDN-fronted online stores and scalable hosting.',
			'implementation' => 'A template store rollout, pilot sites, then waves of installations scheduled around trading hours.',
			'features' => array( 'Store network templates', 'Payment system isolation', 'Guest Wi-Fi', 'eCommerce performance tuning', 'Peak-season readiness' ),
			'tech' => array( 'WooCommerce', 'Cloudflare', 'Fortinet SD-WAN', 'Redis', 'Ubiquiti' ),
			'services' => array( 'vps-hosting', 'software-development', 'network-infrastructure' ),
			'products' => array( 'TN-FW-40F', 'TN-AP-WIFI6E', 'TN-SW-POE24' ),
			'cases' => array(),
		),
		array(
			'title' => 'Manufacturing & Industry 4.0', 'slug' => 'manufacturing-industry-4-0', 'industry' => 'Manufacturing', 'icon' => 'factory', 'photo' => 'sol-manufacturing', 'cover' => 'cover-manufacturing',
			'excerpt' => 'OT/IT convergence, industrial IoT and edge computing for smart factories.',
			'problem' => 'Production systems were never designed for connectivity, yet data from the factory floor is now essential for efficiency.',
			'approach' => 'We connect OT safely through segmented networks, collect machine data at the edge and deliver dashboards for operations teams.',
			'architecture' => 'Industrial DMZ between IT and OT, ruggedised switching, edge servers for data processing and secure remote access for vendors.',
			'implementation' => 'Line-by-line rollout coordinated with production schedules and validated with operations staff.',
			'features' => array( 'OT network segmentation', 'Edge computing', 'Machine data collection', 'Predictive maintenance', 'Secure vendor access' ),
			'tech' => array( 'MQTT', 'OPC UA', 'Fortinet', 'Grafana', 'Docker' ),
			'services' => array( 'iot-solutions', 'cybersecurity', 'network-infrastructure' ),
			'products' => array( 'TN-SW-POE24', 'TN-FW-40F', 'TN-SRV-VH1U' ),
			'cases' => array(),
		),
		array(
			'title' => 'Data Centers & Colocation', 'slug' => 'data-centers-colocation', 'industry' => 'Data Center', 'icon' => 'rack', 'photo' => 'sol-datacenter', 'cover' => 'cover-data-center',
			'excerpt' => 'Design, build and modernisation of data halls, from power and cooling to spine-leaf fabrics.',
			'problem' => 'Operators need more power density and faster provisioning while improving efficiency and uptime.',
			'approach' => 'We design modular capacity, modern fabrics and monitoring that let operators add customers quickly and run efficiently.',
			'architecture' => 'Modular power (2N or N+1), hot-aisle containment, 100GbE spine-leaf fabrics, structured fiber and DCIM monitoring.',
			'implementation' => 'Prefabricated racks and cabling staged off-site, phased commissioning and integrated testing.',
			'features' => array( 'Power and cooling design', 'Spine-leaf fabrics', 'Structured fiber', 'DCIM and monitoring', 'Commissioning' ),
			'tech' => array( 'Juniper QFX', 'Arista', 'Schneider Electric', 'OM4 / OS2', 'DCIM' ),
			'services' => array( 'data-center-solutions', 'network-infrastructure', 'server-solutions' ),
			'products' => array( 'TN-RACK-42U', 'TN-SW-25G-48', 'TN-PDU-16A', 'TN-FIB-MPO12' ),
			'cases' => array( 'data-center-modernization' ),
		),
	);
}
