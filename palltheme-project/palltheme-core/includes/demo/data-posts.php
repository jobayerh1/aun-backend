<?php
/**
 * Demo blog articles (TechNova Systems — fictional company).
 * Bodies are plain HTML; link placeholders are resolved by the importer.
 *
 * @package PallthemeCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Posts.
 *
 * @return array<int,array<string,mixed>>
 */
function pallcore_demo_posts(): array {
	$posts = array();

	$posts[] = array(
		'title' => 'How to Build a Modern Enterprise Network', 'slug' => 'how-to-build-a-modern-enterprise-network',
		'cats' => array( 'Networking' ), 'tags' => array( 'campus network', 'design' ), 'photo' => 'blog-network', 'cover' => 'blog-zero-trust',
		'excerpt' => 'A practical blueprint for campus and branch networks that are fast, segmented and easy to operate.',
		'services' => array( 'network-infrastructure', 'managed-it-services' ), 'products' => array( 'TN-SW-C9300-48', 'TN-SW-POE24', 'TN-AP-WIFI6E' ),
		'body' => <<<'HTML'
<p>A modern enterprise network has three jobs: move traffic reliably, keep different kinds of traffic apart, and tell you quickly when something is wrong. Everything else is detail. This guide walks through the decisions that matter most.</p>
<h2>1. Start with requirements, not hardware</h2>
<p>Inventory your sites, user counts, critical applications and growth plans. Measure current traffic where you can — peak utilisation on uplinks tells you far more than a datasheet. Write down your availability target for each site; a head office and a small branch rarely need the same design.</p>
<h2>2. Choose a simple, repeatable topology</h2>
<ul>
<li><strong>Access layer:</strong> 1GbE (or 2.5GbE for Wi-Fi 6/6E access points) with PoE+ where needed.</li>
<li><strong>Distribution/core:</strong> 10 or 25GbE uplinks, redundant pairs, no single points of failure.</li>
<li><strong>Branches:</strong> a standard template — firewall, switch, access point — deployed the same way everywhere.</li>
</ul>
<p>Collapsed-core designs suit most mid-size campuses. Larger buildings and data centers benefit from spine-leaf. See <a href="{post:understanding-10gbe-vs-25gbe}">10GbE vs 25GbE</a> when choosing uplink speeds.</p>
<h2>3. Segment by purpose</h2>
<p>Put users, servers, voice, cameras, guests and IoT devices in separate VLANs or VRFs, and control traffic between them at a firewall or layer-3 boundary. Segmentation limits the blast radius of a compromised device and makes troubleshooting far easier.</p>
<h2>4. Build in observability from day one</h2>
<ul>
<li>Central syslog and SNMP/streaming telemetry</li>
<li>Flow data (NetFlow/sFlow) on key links</li>
<li>Automated configuration backups</li>
<li>Alerting with clear ownership</li>
</ul>
<h2>5. Document and automate</h2>
<p>Keep diagrams, IP plans and runbooks current. Use templates for switch configuration so every new site is consistent. Small investments here repay themselves at 2 a.m. during an incident.</p>
<p>Need help with a redesign? Our <a href="{svc:network-infrastructure}">network infrastructure</a> team delivers surveys, designs and staged migrations.</p>
HTML,
	);

	$posts[] = array(
		'title' => 'Server Infrastructure Planning Guide', 'slug' => 'server-infrastructure-planning-guide',
		'cats' => array( 'Servers' ), 'tags' => array( 'capacity planning', 'virtualization' ), 'photo' => 'blog-servers', 'cover' => 'cover-server-solutions',
		'excerpt' => 'How to size CPU, memory, storage and networking for the next five years — without overspending.',
		'services' => array( 'server-solutions' ), 'products' => array( 'TN-SRV-R760', 'TN-SRV-VH1U', 'TN-RAM-DDR5' ),
		'body' => <<<'HTML'
<p>Server purchases are long-lived decisions. A host bought today will likely run for five years, so the goal is to buy enough capacity for growth without paying for resources that sit idle.</p>
<h2>Profile the workloads</h2>
<p>Collect at least two weeks of CPU, memory, disk IOPS and network metrics from existing systems. Look at peaks, not averages. Note licensing constraints too — some software is licensed per core, which changes the economics of high-core-count CPUs.</p>
<h2>Size each resource</h2>
<table>
<thead><tr><th>Resource</th><th>Rule of thumb</th></tr></thead>
<tbody>
<tr><td>CPU</td><td>Plan for 60–70% peak utilisation after growth.</td></tr>
<tr><td>Memory</td><td>Usually the first bottleneck in virtualization — leave 25–30% headroom.</td></tr>
<tr><td>Storage</td><td>Size for IOPS and latency first, capacity second. NVMe for active data.</td></tr>
<tr><td>Network</td><td>Dual 25GbE per host is a sensible default for new clusters.</td></tr>
</tbody>
</table>
<h2>Plan for failure</h2>
<p>In a cluster, size for N+1: the remaining hosts must carry the full load if one fails or is in maintenance. Use redundant power supplies, mirrored boot drives and out-of-band management on every server.</p>
<h2>Think about operations</h2>
<ul>
<li>Standardise on one or two server models to simplify spares and firmware.</li>
<li>Track warranty end dates and plan refreshes before they lapse.</li>
<li>Test firmware updates in a lab or on one host first.</li>
</ul>
<p>Our <a href="{svc:server-solutions}">server solutions</a> team sizes, configures and burn-in tests systems before delivery. Choosing drives? Read <a href="{post:choosing-the-right-enterprise-ssd}">choosing the right enterprise SSD</a>.</p>
HTML,
	);

	$posts[] = array(
		'title' => 'Understanding 10GbE vs 25GbE', 'slug' => 'understanding-10gbe-vs-25gbe',
		'cats' => array( 'Networking', 'Technology News' ), 'tags' => array( 'ethernet', 'data center' ), 'photo' => 'blog-25gbe', 'cover' => 'blog-fiber',
		'excerpt' => 'Why 25GbE has become the default for new server connections, and when 10GbE is still the right choice.',
		'services' => array( 'network-infrastructure' ), 'products' => array( 'TN-NIC-25G', 'TN-SW-25G-48', 'TN-SFP-25G-SR' ),
		'body' => <<<'HTML'
<p>For years, 10GbE was the standard server connection. Today, most new data center deployments choose 25GbE. Here is why, and when 10GbE still makes sense.</p>
<h2>The technical difference</h2>
<p>10GbE uses a single 10.3125 Gb/s lane. 25GbE uses a single 25.78 Gb/s lane — the same lane technology used in 100GbE (four lanes of 25). That shared building block is the key: 25GbE servers connect naturally to 100GbE spines.</p>
<table>
<thead><tr><th></th><th>10GbE</th><th>25GbE</th></tr></thead>
<tbody>
<tr><td>Bandwidth per port</td><td>10 Gb/s</td><td>25 Gb/s</td></tr>
<tr><td>Typical optic</td><td>SFP+</td><td>SFP28</td></tr>
<tr><td>Uplink pairing</td><td>40GbE</td><td>100GbE</td></tr>
<tr><td>Short-reach copper</td><td>DAC, 10GBASE-T</td><td>DAC (no common BASE-T)</td></tr>
</tbody>
</table>
<h2>When 25GbE wins</h2>
<ul>
<li>Virtualization hosts with many VMs and live migration traffic</li>
<li>Hyper-converged and software-defined storage</li>
<li>New spine-leaf fabrics with 100GbE uplinks</li>
</ul>
<h2>When 10GbE is enough</h2>
<ul>
<li>Small clusters with modest east-west traffic</li>
<li>Existing 10GBASE-T cabling you want to keep</li>
<li>Edge and branch servers</li>
</ul>
<p>Note that SFP28 ports usually accept SFP+ optics at 10 Gb/s, which eases migration. Read more in <a href="{post:understanding-sfp-and-sfp28}">understanding SFP+ and SFP28</a>.</p>
HTML,
	);

	$posts[] = array(
		'title' => 'Cloud Migration Best Practices', 'slug' => 'cloud-migration-best-practices',
		'cats' => array( 'Cloud' ), 'tags' => array( 'migration', 'finops' ), 'photo' => 'blog-cloud', 'cover' => 'cover-cloud-solutions',
		'excerpt' => 'Ten lessons from real migrations: assess honestly, build a landing zone first and migrate in waves.',
		'services' => array( 'cloud-computing' ), 'products' => array( 'TN-SVC-CLOUD-MIG' ),
		'body' => <<<'HTML'
<p>Most cloud migrations that disappoint share the same causes: unclear goals, no landing zone and big-bang moves. These practices avoid them.</p>
<h2>Before you move anything</h2>
<ol>
<li><strong>Define the goal.</strong> Cost, agility, resilience or a data center exit? Each leads to different choices.</li>
<li><strong>Inventory and classify workloads.</strong> Retire, retain, rehost, re-platform or refactor.</li>
<li><strong>Model costs honestly.</strong> Include egress, backup, licensing and support — not just compute.</li>
</ol>
<h2>Build the foundation</h2>
<ol start="4">
<li><strong>Create a landing zone:</strong> account structure, identity, networking, logging and guardrails.</li>
<li><strong>Automate it</strong> with infrastructure as code so environments are repeatable.</li>
<li><strong>Connect securely</strong> to on-premises with VPN or dedicated links.</li>
</ol>
<h2>Migrate in waves</h2>
<ol start="7">
<li>Start with low-risk workloads to prove the process.</li>
<li>Test performance, backup and failover for each wave.</li>
<li>Decommission the old system promptly to realise savings.</li>
<li>Review costs monthly and right-size continuously.</li>
</ol>
<p>Our <a href="{svc:cloud-computing}">cloud computing</a> team runs assessments and migrations; see the <a href="{case:secure-cloud-migration}">secure cloud migration</a> demo case study for an example.</p>
HTML,
	);

	$posts[] = array(
		'title' => 'How to Secure an Enterprise Network', 'slug' => 'how-to-secure-an-enterprise-network',
		'cats' => array( 'Cybersecurity', 'Networking' ), 'tags' => array( 'segmentation', 'zero trust' ), 'photo' => 'blog-secure', 'cover' => 'blog-zero-trust',
		'excerpt' => 'Layered controls that make the biggest difference: segmentation, identity, patching and monitoring.',
		'services' => array( 'cybersecurity', 'network-infrastructure' ), 'products' => array( 'TN-FW-100F', 'TN-NMS-100' ),
		'body' => <<<'HTML'
<p>No single product secures a network. Security comes from layers that each make an attacker’s job harder — and make their activity easier to spot.</p>
<h2>1. Know what is connected</h2>
<p>Maintain an inventory of devices and their owners. Unknown devices are the most common way in. Network access control can enforce this automatically.</p>
<h2>2. Segment aggressively</h2>
<p>Separate users, servers, management interfaces, guests and IoT. Allow only the traffic that is needed between zones, and put management interfaces on a dedicated, restricted network.</p>
<h2>3. Strengthen identity</h2>
<ul>
<li>Multi-factor authentication for all remote and administrative access</li>
<li>Individual admin accounts — no shared passwords</li>
<li>Least privilege and regular access reviews</li>
</ul>
<h2>4. Patch and harden</h2>
<p>Keep firmware on switches, firewalls and access points current. Disable unused services and default accounts. Prioritise internet-facing systems.</p>
<h2>5. Watch and respond</h2>
<p>Collect logs centrally, alert on anomalies and practise your response. A tested incident plan turns a crisis into a procedure.</p>
<p>Start with our <a href="{post:cybersecurity-fundamentals-for-businesses}">cybersecurity fundamentals</a> or book a <a href="{product:TN-SVC-SEC-ASSESS}">cybersecurity assessment</a>.</p>
HTML,
	);

	$posts[] = array(
		'title' => 'Linux Server Hardening Guide', 'slug' => 'linux-server-hardening-guide',
		'cats' => array( 'Linux', 'Tutorials' ), 'tags' => array( 'linux', 'security', 'ssh' ), 'photo' => 'blog-linux', 'cover' => 'blog-linux',
		'excerpt' => 'The essential steps for a fresh Linux server: SSH keys, firewall, updates, logging and least privilege.',
		'services' => array( 'vps-hosting', 'cybersecurity' ), 'products' => array( 'TN-SVC-LINUX-SUP' ),
		'body' => <<<'HTML'
<p>A freshly installed Linux server is exposed the moment it gets a public IP address. These steps cover the essentials; adapt them to your distribution and policies.</p>
<h2>Accounts and SSH</h2>
<ul>
<li>Create a named admin user and grant <code>sudo</code>; avoid logging in as root.</li>
<li>Use SSH keys and disable password authentication (<code>PasswordAuthentication no</code>).</li>
<li>Disable direct root login (<code>PermitRootLogin no</code>).</li>
<li>Consider limiting SSH to a VPN or known IP ranges.</li>
</ul>
<h2>Firewall</h2>
<p>Allow only required ports. With <code>ufw</code>: <code>ufw default deny incoming</code>, <code>ufw allow 22/tcp</code>, then add application ports and <code>ufw enable</code>.</p>
<h2>Updates</h2>
<p>Enable automatic security updates (for example <code>unattended-upgrades</code> on Debian/Ubuntu) and schedule reboots for kernel patches.</p>
<h2>Logging and monitoring</h2>
<ul>
<li>Forward logs to a central server</li>
<li>Use <code>fail2ban</code> or similar to block repeated failed logins</li>
<li>Monitor disk, memory and service health with alerting</li>
</ul>
<h2>Least privilege</h2>
<p>Run services under dedicated users, remove unused packages and review open ports with <code>ss -tulpn</code>.</p>
<p>Prefer us to handle it? Our <a href="{product:TN-SVC-LINUX-SUP}">Linux server support package</a> includes patching, monitoring and backup checks.</p>
HTML,
	);

	$posts[] = array(
		'title' => 'Choosing the Right Enterprise SSD', 'slug' => 'choosing-the-right-enterprise-ssd',
		'cats' => array( 'Servers' ), 'tags' => array( 'storage', 'nvme' ), 'photo' => '', 'cover' => 'product-nvme',
		'excerpt' => 'NVMe or SAS, read-intensive or mixed-use: what endurance ratings and form factors mean for your servers.',
		'services' => array( 'server-solutions' ), 'products' => array( 'TN-SSD-NVME-U2', 'TN-SSD-3840', 'TN-NVME-2T' ),
		'body' => <<<'HTML'
<p>Enterprise SSDs differ from consumer drives in ways that matter for servers: consistent latency under load, power-loss protection and much higher endurance. Here is how to choose.</p>
<h2>Interface: NVMe, SAS or SATA</h2>
<ul>
<li><strong>NVMe</strong> — lowest latency and highest throughput; the default for new servers.</li>
<li><strong>SAS</strong> — dual-ported and widely supported in existing arrays.</li>
<li><strong>SATA</strong> — cost-effective for boot drives and light workloads.</li>
</ul>
<h2>Endurance (DWPD)</h2>
<p>Drive Writes Per Day expresses how much data you can write daily over the warranty period. Read-intensive drives (~1 DWPD) suit most virtualization and web workloads; mixed-use (~3 DWPD) suits databases and logging.</p>
<h2>Form factor</h2>
<p>U.2 and E1.S/E3.S are hot-swappable server formats. M.2 is common for boot volumes. Check your server’s backplane before ordering.</p>
<h2>Power-loss protection</h2>
<p>Capacitors let the drive flush in-flight writes during a power failure. Essential for databases and any write-back caching.</p>
<h2>Checklist</h2>
<ul>
<li>Confirm server compatibility and backplane type</li>
<li>Estimate daily writes and choose endurance accordingly</li>
<li>Prefer drives with power-loss protection</li>
<li>Keep firmware updated</li>
</ul>
<p>Browse <a href="{pcat:ssd}">enterprise SSDs</a> or read our <a href="{post:server-infrastructure-planning-guide}">server planning guide</a>.</p>
HTML,
	);

	$posts[] = array(
		'title' => 'Understanding SFP+ and SFP28', 'slug' => 'understanding-sfp-and-sfp28',
		'cats' => array( 'Networking', 'Tutorials' ), 'tags' => array( 'optics', 'transceivers' ), 'photo' => 'blog-sfp', 'cover' => 'blog-fiber',
		'excerpt' => 'Transceiver types, reach, fiber and compatibility — explained without the jargon.',
		'services' => array( 'network-infrastructure' ), 'products' => array( 'TN-SFP-10G-SR', 'TN-SFP-25G-SR', 'TN-FIB-OM4' ),
		'body' => <<<'HTML'
<p>SFP+ and SFP28 are the pluggable modules that connect switches, routers and servers to fiber or copper cables. Picking the right one is mostly about speed, distance and fiber type.</p>
<h2>Speeds</h2>
<ul>
<li><strong>SFP+</strong>: 10 Gb/s</li>
<li><strong>SFP28</strong>: 25 Gb/s (most ports also run at 10 Gb/s)</li>
<li><strong>QSFP28</strong>: 100 Gb/s using four 25 Gb/s lanes</li>
</ul>
<h2>Reach and fiber</h2>
<table>
<thead><tr><th>Type</th><th>Fiber</th><th>Typical reach</th></tr></thead>
<tbody>
<tr><td>SR (850 nm)</td><td>Multi-mode OM3/OM4</td><td>70–400 m</td></tr>
<tr><td>LR (1310 nm)</td><td>Single-mode OS2</td><td>10 km</td></tr>
<tr><td>DAC</td><td>Copper cable</td><td>1–5 m in-rack</td></tr>
</tbody>
</table>
<h2>Compatibility</h2>
<p>Many switches check a vendor code on the module. Third-party optics are coded to match; confirm the target platform when ordering.</p>
<h2>Good practice</h2>
<ul>
<li>Keep connectors capped and clean them before insertion</li>
<li>Match fiber type and connector (LC, MPO)</li>
<li>Use DDM readings to monitor optical power</li>
</ul>
<p>Shop <a href="{pcat:transceivers}">transceivers</a> and <a href="{pcat:fiber-optics}">fiber cables</a>.</p>
HTML,
	);

	$posts[] = array(
		'title' => 'Data Center Redundancy Explained', 'slug' => 'data-center-redundancy-explained',
		'cats' => array( 'Data Center' ), 'tags' => array( 'power', 'uptime' ), 'photo' => 'blog-redundancy', 'cover' => 'cover-data-center',
		'excerpt' => 'N, N+1, 2N and 2N+1 — what redundancy levels mean and how to choose for your facility.',
		'services' => array( 'data-center-solutions' ), 'products' => array( 'TN-UPS-3K', 'TN-PDU-16A', 'TN-RACK-42U' ),
		'body' => <<<'HTML'
<p>Redundancy describes how much spare capacity a facility has for power, cooling and connectivity. More redundancy means higher availability — and higher cost.</p>
<h2>The common levels</h2>
<table>
<thead><tr><th>Level</th><th>Meaning</th><th>Example</th></tr></thead>
<tbody>
<tr><td>N</td><td>Exactly enough capacity</td><td>Two UPS units needed, two installed</td></tr>
<tr><td>N+1</td><td>One spare component</td><td>Three UPS units for a two-unit load</td></tr>
<tr><td>2N</td><td>A fully duplicated system</td><td>Independent A and B power paths</td></tr>
<tr><td>2N+1</td><td>Duplicated plus a spare</td><td>Mission-critical facilities</td></tr>
</tbody>
</table>
<h2>Power</h2>
<p>Dual-corded servers connected to separate A and B power feeds, each with its own UPS and PDU, survive the loss of either path. Single-corded equipment needs an automatic transfer switch.</p>
<h2>Cooling</h2>
<p>N+1 cooling units allow maintenance without raising temperatures. Containment keeps hot and cold air separate, improving both efficiency and resilience.</p>
<h2>Connectivity</h2>
<p>Use diverse fiber paths and at least two upstream providers for internet-dependent services.</p>
<h2>Choosing a level</h2>
<p>Start from the cost of downtime. If an hour offline costs more than the extra equipment, invest in higher redundancy. Our <a href="{svc:data-center-solutions}">data center team</a> can model the options.</p>
HTML,
	);

	$posts[] = array(
		'title' => 'Proxmox Virtualization Guide', 'slug' => 'proxmox-virtualization-guide',
		'cats' => array( 'Tutorials', 'Servers' ), 'tags' => array( 'proxmox', 'virtualization' ), 'photo' => 'blog-proxmox', 'cover' => 'blog-kubernetes',
		'excerpt' => 'Planning a Proxmox VE cluster: hardware, networking, storage options and day-two operations.',
		'services' => array( 'server-solutions' ), 'products' => array( 'TN-SRV-VH1U', 'TN-NIC-25G' ),
		'body' => <<<'HTML'
<p>Proxmox VE is an open-source virtualization platform combining KVM virtual machines and LXC containers with a web interface, clustering and integrated backup. It is a popular choice for organisations wanting enterprise features without per-socket licensing.</p>
<h2>Hardware</h2>
<ul>
<li>Three or more identical nodes for a resilient cluster (quorum)</li>
<li>Plenty of RAM — it is usually the limiting resource</li>
<li>Mirrored boot drives and enterprise SSDs for VM storage</li>
</ul>
<h2>Networking</h2>
<p>Separate networks for management, VM traffic, storage and cluster communication. Dual 25GbE with bonding is a solid baseline; keep corosync (cluster) traffic on a low-latency, dedicated link.</p>
<h2>Storage options</h2>
<table>
<thead><tr><th>Option</th><th>Best for</th></tr></thead>
<tbody>
<tr><td>Local ZFS</td><td>Single nodes or small clusters with replication</td></tr>
<tr><td>Ceph</td><td>Hyper-converged clusters of three or more nodes</td></tr>
<tr><td>NFS/iSCSI</td><td>Existing shared storage arrays</td></tr>
</tbody>
</table>
<h2>Day-two operations</h2>
<ul>
<li>Schedule backups with Proxmox Backup Server and test restores</li>
<li>Apply updates one node at a time after migrating VMs</li>
<li>Monitor with your existing tools via the API</li>
</ul>
<p>Our <a href="{product:TN-SRV-VH1U}">virtualization host</a> ships pre-tested for Proxmox VE.</p>
HTML,
	);

	$posts[] = array(
		'title' => 'Docker for IT Infrastructure', 'slug' => 'docker-for-it-infrastructure',
		'cats' => array( 'DevOps' ), 'tags' => array( 'docker', 'containers' ), 'photo' => 'blog-docker', 'cover' => 'blog-kubernetes',
		'excerpt' => 'Where containers help infrastructure teams most — and the habits that keep them secure and maintainable.',
		'services' => array( 'software-development', 'cloud-computing' ), 'products' => array(),
		'body' => <<<'HTML'
<p>Containers package an application with its dependencies so it runs the same way everywhere. For infrastructure teams, Docker is most useful for tooling, internal services and consistent development environments.</p>
<h2>Good first use cases</h2>
<ul>
<li>Monitoring stacks (Prometheus, Grafana) and log collectors</li>
<li>Internal web tools and dashboards</li>
<li>Reproducible build and test environments</li>
</ul>
<h2>Habits that pay off</h2>
<ul>
<li><strong>Pin image versions</strong> instead of using <code>latest</code>.</li>
<li><strong>Use small base images</strong> and rebuild regularly for security patches.</li>
<li><strong>Keep data in volumes</strong>, and back those volumes up.</li>
<li><strong>Never bake secrets into images</strong>; inject them at runtime.</li>
<li><strong>Run as a non-root user</strong> inside the container.</li>
</ul>
<h2>Compose for multi-container apps</h2>
<p>Docker Compose describes services, networks and volumes in one file, making small stacks easy to deploy and version-control.</p>
<h2>When to move to Kubernetes</h2>
<p>When you need scheduling across many hosts, self-healing and rolling updates at scale. See <a href="{post:kubernetes-for-enterprise-environments}">Kubernetes for enterprise environments</a>.</p>
HTML,
	);

	$posts[] = array(
		'title' => 'Kubernetes for Enterprise Environments', 'slug' => 'kubernetes-for-enterprise-environments',
		'cats' => array( 'DevOps', 'Cloud' ), 'tags' => array( 'kubernetes', 'platform engineering' ), 'photo' => 'blog-k8s', 'cover' => 'blog-kubernetes',
		'excerpt' => 'What it takes to run Kubernetes well: platform team, guardrails, observability and upgrades.',
		'services' => array( 'cloud-computing', 'software-development' ), 'products' => array( 'TN-SRV-VH1U' ),
		'body' => <<<'HTML'
<p>Kubernetes is powerful, but it is a platform you operate, not a product you install once. Enterprises succeed with it when they treat it as an internal product with clear owners.</p>
<h2>Managed or self-hosted?</h2>
<p>Managed services (EKS, AKS, GKE) remove control-plane work. Self-hosted clusters on your own hardware give more control and can lower costs at scale, but need more skills.</p>
<h2>Essential guardrails</h2>
<ul>
<li>Namespaces, resource quotas and limits per team</li>
<li>Role-based access control tied to your identity provider</li>
<li>Network policies to restrict pod-to-pod traffic</li>
<li>Admission policies that block privileged or unsigned images</li>
</ul>
<h2>Observability</h2>
<p>Collect metrics, logs and traces from day one. Define service-level objectives so alerts reflect user impact rather than noise.</p>
<h2>Upgrades and lifecycle</h2>
<p>Kubernetes releases frequently. Plan regular upgrades, test them in a staging cluster and keep add-ons current.</p>
<p>Our <a href="{svc:cloud-computing}">cloud team</a> designs and operates container platforms on-premises and in the cloud.</p>
HTML,
	);

	$posts[] = array(
		'title' => 'Backup and Disaster Recovery Planning', 'slug' => 'backup-and-disaster-recovery-planning',
		'cats' => array( 'Data Center', 'Cybersecurity' ), 'tags' => array( 'backup', 'ransomware' ), 'photo' => 'blog-dr', 'cover' => 'blog-backup',
		'excerpt' => 'Set recovery objectives, follow the 3-2-1-1-0 rule and test restores before you need them.',
		'services' => array( 'backup-disaster-recovery' ), 'products' => array( 'TN-NAS-8BAY', 'TN-HDD-18T' ),
		'body' => <<<'HTML'
<p>Backups protect against deleted files; disaster recovery protects against losing an entire site. A good plan covers both, and is tested regularly.</p>
<h2>Define recovery objectives</h2>
<ul>
<li><strong>RPO</strong> (recovery point objective): how much data you can afford to lose.</li>
<li><strong>RTO</strong> (recovery time objective): how long you can afford to be down.</li>
</ul>
<p>Set them per system with business owners — payroll and the public website rarely need the same targets.</p>
<h2>The 3-2-1-1-0 rule</h2>
<ul>
<li><strong>3</strong> copies of your data</li>
<li><strong>2</strong> different storage types</li>
<li><strong>1</strong> copy off-site</li>
<li><strong>1</strong> copy immutable or offline</li>
<li><strong>0</strong> errors in restore tests</li>
</ul>
<h2>Write runbooks</h2>
<p>For each scenario — failed server, ransomware, site loss — document who does what, in what order, with contact details. Store runbooks where you can reach them during an outage.</p>
<h2>Test</h2>
<p>Restore individual files monthly and run a full failover exercise at least once a year. Record results and fix gaps.</p>
<p>See our <a href="{svc:backup-disaster-recovery}">backup & DR service</a> and the <a href="{case:disaster-recovery-implementation}">disaster recovery demo case study</a>.</p>
HTML,
	);

	$posts[] = array(
		'title' => 'Cybersecurity Fundamentals for Businesses', 'slug' => 'cybersecurity-fundamentals-for-businesses',
		'cats' => array( 'Cybersecurity' ), 'tags' => array( 'mfa', 'awareness' ), 'photo' => 'blog-cyber', 'cover' => 'blog-zero-trust',
		'excerpt' => 'Eight controls that stop most attacks, in priority order, for organisations of any size.',
		'services' => array( 'cybersecurity' ), 'products' => array( 'TN-SVC-SEC-ASSESS', 'TN-LIC-EPP50', 'TN-FW-40F' ),
		'body' => <<<'HTML'
<p>Most successful attacks exploit basic gaps: reused passwords, unpatched systems and untested backups. These eight controls, roughly in priority order, address the majority of real-world risk.</p>
<ol>
<li><strong>Multi-factor authentication</strong> on email, remote access and admin accounts.</li>
<li><strong>Patching</strong> operating systems, browsers and internet-facing devices promptly.</li>
<li><strong>Backups</strong> with an immutable or offline copy, tested regularly.</li>
<li><strong>Endpoint protection</strong> on every laptop, desktop and server.</li>
<li><strong>Least privilege</strong>: everyday accounts without admin rights.</li>
<li><strong>Email security</strong>: filtering plus SPF, DKIM and DMARC.</li>
<li><strong>Awareness training</strong> that is short, frequent and practical.</li>
<li><strong>An incident plan</strong> everyone knows how to use.</li>
</ol>
<h2>Measure progress</h2>
<p>Track simple metrics: percentage of accounts with MFA, days to patch critical vulnerabilities, last successful restore test. Improvement over time matters more than perfection.</p>
<h2>Get an outside view</h2>
<p>An independent assessment highlights blind spots and ranks fixes by impact. Our <a href="{product:TN-SVC-SEC-ASSESS}">cybersecurity assessment</a> delivers a prioritised roadmap in about two weeks.</p>
HTML,
	);

	$posts[] = array(
		'title' => 'How to Design a Scalable ISP Network', 'slug' => 'how-to-design-a-scalable-isp-network',
		'cats' => array( 'Networking' ), 'tags' => array( 'isp', 'bgp' ), 'photo' => 'blog-ispnet', 'cover' => 'cover-isp',
		'excerpt' => 'Core, aggregation, access and operations — building an ISP network that grows with subscribers.',
		'services' => array( 'network-infrastructure' ), 'products' => array( 'TN-RT-CCR2116', 'TN-RT-MX204', 'TN-QSFP-100G' ),
		'body' => <<<'HTML'
<p>An ISP network must grow continuously without disrupting subscribers. Designing in clear layers makes that growth predictable.</p>
<h2>Layers</h2>
<ul>
<li><strong>Edge:</strong> BGP routers connecting to upstream transit and peering, ideally with at least two providers.</li>
<li><strong>Core:</strong> high-capacity links between points of presence, often MPLS or segment routing.</li>
<li><strong>Aggregation:</strong> collects access traffic and hosts BNG or subscriber management.</li>
<li><strong>Access:</strong> fiber (GPON/XGS-PON), fixed wireless or Ethernet to customers.</li>
</ul>
<h2>Plan capacity from peaks</h2>
<p>Model evening peak traffic per subscriber and plan upgrades before links reach about 70% utilisation. Track growth monthly.</p>
<h2>Automate provisioning</h2>
<p>Integrate RADIUS, IP address management and billing so new subscribers activate without manual configuration.</p>
<h2>Operate with visibility</h2>
<ul>
<li>Flow analytics to understand traffic and detect DDoS</li>
<li>Monitoring of every link and device with clear alerting</li>
<li>Configuration templates and version control</li>
</ul>
<p>See our <a href="{sol:isp-network-solutions}">ISP solutions</a> and the <a href="{case:isp-network-expansion}">ISP network expansion</a> demo case study.</p>
HTML,
	);

	$posts[] = array(
		'title' => 'Making WordPress Fast for Business Websites', 'slug' => 'making-wordpress-fast-for-business-websites',
		'cats' => array( 'WordPress' ), 'tags' => array( 'performance', 'core web vitals' ), 'photo' => 'blog-wordpress', 'cover' => 'blog-woocommerce',
		'excerpt' => 'Hosting, caching, images and plugins: the changes that actually move Core Web Vitals.',
		'services' => array( 'vps-hosting', 'software-development' ), 'products' => array(),
		'body' => <<<'HTML'
<p>Fast websites convert better and rank better. For WordPress, a handful of changes deliver most of the improvement.</p>
<h2>1. Good hosting</h2>
<p>Modern PHP, NVMe storage and an object cache (Redis) make every uncached request faster.</p>
<h2>2. Page caching</h2>
<p>Serve cached HTML to anonymous visitors with a caching plugin or server-level cache. Exclude carts, checkouts and account pages.</p>
<h2>3. Images</h2>
<ul>
<li>Serve WebP or AVIF in the right sizes</li>
<li>Lazy-load images below the fold</li>
<li>Prioritise the largest image above the fold</li>
</ul>
<h2>4. Fewer, better plugins</h2>
<p>Each plugin can add CSS, JavaScript and database queries. Remove what you do not use and prefer well-maintained plugins.</p>
<h2>5. Measure</h2>
<p>Check Core Web Vitals in Search Console and test key pages after every major change.</p>
<p>Our <a href="{svc:vps-hosting}">managed hosting</a> includes caching and performance tuning.</p>
HTML,
	);

	$posts[] = array(
		'title' => 'Running WooCommerce at Scale', 'slug' => 'running-woocommerce-at-scale',
		'cats' => array( 'WooCommerce' ), 'tags' => array( 'ecommerce', 'performance' ), 'photo' => 'blog-woo', 'cover' => 'blog-woocommerce',
		'excerpt' => 'Practical advice for stores with thousands of products and busy checkout periods.',
		'services' => array( 'vps-hosting', 'software-development' ), 'products' => array(),
		'body' => <<<'HTML'
<p>WooCommerce handles large catalogs well when the platform underneath is configured for it. These are the settings and habits we see make the biggest difference.</p>
<h2>Database and storage</h2>
<ul>
<li>Enable High-Performance Order Storage (HPOS)</li>
<li>Keep product lookup tables up to date</li>
<li>Use an object cache such as Redis</li>
</ul>
<h2>Catalog performance</h2>
<p>Use product attributes for filtering so queries stay indexed, paginate category pages and avoid loading every product variation on listing pages.</p>
<h2>Checkout reliability</h2>
<ul>
<li>Exclude cart, checkout and account pages from page caching</li>
<li>Test payment gateways after updates</li>
<li>Monitor failed orders and checkout errors</li>
</ul>
<h2>Peak-season readiness</h2>
<p>Load-test before major campaigns, scale resources temporarily and freeze non-essential changes during the busiest days.</p>
<p>See <a href="{post:making-wordpress-fast-for-business-websites}">making WordPress fast</a> and our <a href="{sol:retail-ecommerce}">retail & eCommerce solutions</a>.</p>
HTML,
	);

	$posts[] = array(
		'title' => 'AIOps: Predicting Network Outages with Machine Learning', 'slug' => 'aiops-predicting-network-outages',
		'cats' => array( 'AI', 'Technology News' ), 'tags' => array( 'aiops', 'monitoring' ), 'photo' => 'blog-aiops', 'cover' => 'blog-ai-ops',
		'excerpt' => 'How anomaly detection on telemetry can flag failing links and devices before users notice.',
		'services' => array( 'ai-solutions', 'managed-it-services' ), 'products' => array( 'TN-NMS-100' ),
		'body' => <<<'HTML'
<p>Traditional monitoring alerts when a threshold is crossed. AIOps uses machine learning to learn what “normal” looks like and highlight unusual patterns earlier — often before a failure.</p>
<h2>What it can detect</h2>
<ul>
<li>Gradually rising error counters on an optical link</li>
<li>Unusual traffic patterns that may indicate misconfiguration or attack</li>
<li>Devices whose temperature or CPU trends differ from their peers</li>
</ul>
<h2>What it needs</h2>
<p>Good data: consistent telemetry from devices, flow records, logs and a history of incidents. Models are only as good as the data they learn from.</p>
<h2>Start small</h2>
<ol>
<li>Pick one well-understood problem, such as optic degradation.</li>
<li>Collect several weeks of baseline data.</li>
<li>Compare model alerts with what engineers would have flagged.</li>
<li>Tune, then expand to more signals.</li>
</ol>
<h2>Keep humans in the loop</h2>
<p>AIOps should prioritise and explain, not replace engineering judgement. Clear explanations build trust in the alerts.</p>
<p>Learn more about our <a href="{svc:ai-solutions}">AI solutions</a>.</p>
HTML,
	);

	return $posts;
}
