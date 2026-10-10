<?php
/**
 * Demo case studies. All clients are fictional and labelled as demo content;
 * figures are illustrative examples, not claims about real projects.
 *
 * @package PallthemeCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Case studies.
 *
 * @return array<int,array<string,mixed>>
 */
function pallcore_demo_cases(): array {
	return array(
		array(
			'title'    => 'Enterprise Network Infrastructure Upgrade',
			'slug'     => 'enterprise-network-infrastructure-upgrade',
			'photo'    => 'cs-network',
			'cover'    => 'case-network',
			'industry' => 'Enterprise',
			'excerpt'  => 'A 40-site organisation replaced a congested legacy network with a redundant, monitored architecture — without unplanned downtime.',
			'meta'     => array(
				'client'             => 'BlueWave Logistics (fictional demo client)',
				'location'           => 'Multi-site, regional',
				'duration'           => '14 weeks',
				'challenge'          => 'Legacy switches had reached end of support and uplinks were saturated at peak hours. A flat network meant one faulty device could disrupt every site, and troubleshooting relied on a handful of people who knew the history.',
				'solution'           => 'A three-tier campus design with 25GbE uplinks, redundant cores, SD-WAN between sites and VLAN segmentation by function. Centralised monitoring and configuration backup were added from day one.',
				'implementation'     => 'Sites were migrated in overnight waves using a repeatable runbook and pre-staged equipment. Each cut-over was validated against a test checklist before users returned in the morning.',
				'results'            => array( array( '40%', 'improved network performance' ), array( '99.99%', 'availability target met' ), array( '30%', 'lower operational overhead' ) ),
				'technologies'       => array( 'Enterprise access switching', '25GbE uplinks', 'SD-WAN', 'Network monitoring', 'Configuration backup' ),
				'testimonial'        => 'The upgrade was planned to the hour. Our teams arrived each morning to a faster network and never noticed the change itself.',
				'testimonial_author' => 'Alex Morgan, Technology Director — demo testimonial',
			),
			'services'  => array( 'network-infrastructure', 'managed-it-services' ),
			'solutions' => array( 'enterprise-it' ),
		),
		array(
			'title'    => 'Data Center Modernization',
			'slug'     => 'data-center-modernization',
			'photo'    => 'cs-dcmodern',
			'cover'    => 'case-datacenter',
			'industry' => 'Data Center',
			'excerpt'  => 'Ageing servers and storage were consolidated onto a hyper-converged platform with redundant power and a spine-leaf fabric.',
			'meta'     => array(
				'client'             => 'DataSphere (fictional demo client)',
				'location'           => 'Single facility, 12 racks',
				'duration'           => '5 months',
				'challenge'          => 'Five generations of hardware, inconsistent cabling and a single UPS created both risk and high running costs. Provisioning a new virtual machine took days.',
				'solution'           => 'Consolidation onto a hyper-converged cluster, a 100GbE spine-leaf fabric, dual-feed power with N+1 UPS capacity, hot-aisle containment and environmental monitoring.',
				'implementation'     => 'Racks were pre-built and cabled off-site, workloads migrated in prioritised waves, and legacy hardware decommissioned with secure data erasure.',
				'results'            => array( array( '58%', 'less rack space' ), array( '35%', 'lower power draw' ), array( 'Minutes', 'to provision a VM' ) ),
				'technologies'       => array( 'Hyper-converged infrastructure', 'Spine-leaf fabric', 'N+1 UPS', 'Hot-aisle containment', 'DCIM' ),
				'testimonial'        => 'We gained back half the room and finally have power redundancy we can trust.',
				'testimonial_author' => 'Jordan Ellis, Infrastructure Manager — demo testimonial',
			),
			'services'  => array( 'data-center-solutions', 'server-solutions' ),
			'solutions' => array( 'data-centers-colocation', 'enterprise-it' ),
		),
		array(
			'title'    => 'Secure Cloud Migration',
			'slug'     => 'secure-cloud-migration',
			'photo'    => 'cs-cloud',
			'cover'    => 'case-cloud',
			'industry' => 'Banking',
			'excerpt'  => 'Customer-facing applications moved to a guarded hybrid cloud, shortening release cycles while strengthening controls.',
			'meta'     => array(
				'client'             => 'Primeline Bank (fictional demo client)',
				'location'           => 'Hybrid: on-premises + public cloud',
				'duration'           => '6 months',
				'challenge'          => 'Monolithic applications on ageing hardware slowed every release, and scaling for seasonal peaks meant buying capacity that sat idle most of the year.',
				'solution'           => 'A cloud landing zone with private connectivity, policy-as-code guardrails, containerised services and centralised logging integrated with existing security monitoring.',
				'implementation'     => 'Applications were containerised and migrated service by service. Compliance checks ran automatically in the deployment pipeline.',
				'results'            => array( array( '12×', 'faster releases' ), array( '45%', 'infrastructure savings' ), array( '0', 'critical audit findings' ) ),
				'technologies'       => array( 'Cloud landing zone', 'Kubernetes', 'Terraform', 'CI/CD', 'Secrets management' ),
				'testimonial'        => 'We release in hours instead of weeks, and our controls are stronger than before.',
				'testimonial_author' => 'Sam Rivera, Head of Platform — demo testimonial',
			),
			'services'  => array( 'cloud-computing', 'cybersecurity' ),
			'solutions' => array( 'banking-financial-services' ),
		),
		array(
			'title'    => 'ISP Network Expansion',
			'slug'     => 'isp-network-expansion',
			'photo'    => 'cs-isp',
			'cover'    => 'case-network',
			'industry' => 'ISP',
			'excerpt'  => 'A regional internet provider tripled core capacity and added diverse upstream links to support subscriber growth.',
			'meta'     => array(
				'client'             => 'GlobalNet (fictional demo client)',
				'location'           => 'Four points of presence',
				'duration'           => '20 weeks',
				'challenge'          => 'Evening peaks saturated the core, a single upstream provider was a single point of failure, and subscriber provisioning was manual.',
				'solution'           => 'New edge routers with diverse upstreams and peering, a 100GbE core between points of presence, automated subscriber provisioning and flow-based traffic analytics.',
				'implementation'     => 'Configurations were validated in a lab, then each point of presence migrated during low-traffic windows with rollback plans ready.',
				'results'            => array( array( '3×', 'core capacity' ), array( '2', 'diverse upstream providers' ), array( '-70%', 'provisioning time' ) ),
				'technologies'       => array( 'BGP peering', '100GbE core', 'Subscriber automation', 'Flow analytics' ),
				'testimonial'        => 'Peak-hour complaints dropped away and our NOC finally sees problems first.',
				'testimonial_author' => 'Priya Shah, Network Operations Lead — demo testimonial',
			),
			'services'  => array( 'network-infrastructure', 'managed-it-services' ),
			'solutions' => array( 'isp-network-solutions', 'telecom-infrastructure' ),
		),
		array(
			'title'    => 'Enterprise Cybersecurity Deployment',
			'slug'     => 'enterprise-cybersecurity-deployment',
			'photo'    => 'cs-security',
			'cover'    => 'case-soc',
			'industry' => 'Healthcare',
			'excerpt'  => 'Network segmentation, multi-factor authentication and centralised monitoring for a multi-site healthcare group.',
			'meta'     => array(
				'client'             => 'Helix Health (fictional demo client)',
				'location'           => 'Five sites',
				'duration'           => '10 weeks',
				'challenge'          => 'Medical devices shared networks with office PCs, remote access relied on passwords alone and security alerts went unreviewed outside business hours.',
				'solution'           => 'Device discovery and segmentation, next-generation firewalls between zones, MFA for all remote access and centralised log collection with out-of-hours alert handling.',
				'implementation'     => 'Each device type was profiled with clinical engineering, policies were tested in monitor mode, then enforced site by site.',
				'results'            => array( array( '-90%', 'mean time to respond' ), array( '100%', 'remote access behind MFA' ), array( '24/7', 'alert coverage' ) ),
				'technologies'       => array( 'Next-generation firewalls', 'Network access control', 'MFA', 'SIEM', 'Endpoint protection' ),
				'testimonial'        => 'We now know what is on our network and who is accessing it, at any hour.',
				'testimonial_author' => 'Chris Taylor, IT Security Manager — demo testimonial',
			),
			'services'  => array( 'cybersecurity', 'managed-it-services' ),
			'solutions' => array( 'healthcare-it' ),
		),
		array(
			'title'    => 'Disaster Recovery Implementation',
			'slug'     => 'disaster-recovery-implementation',
			'photo'    => 'cs-dr',
			'cover'    => 'case-datacenter',
			'industry' => 'Banking',
			'excerpt'  => 'Immutable backups and a tested recovery site reduced recovery time for critical systems from days to hours.',
			'meta'     => array(
				'client'             => 'Apex Telecom (fictional demo client)',
				'location'           => 'Primary site + recovery site',
				'duration'           => '12 weeks',
				'challenge'          => 'Backups were taken nightly but never restored in full, and there was no agreed plan for losing the primary data center.',
				'solution'           => 'Recovery objectives per system, immutable backup storage, replication to a secondary site and documented runbooks for each failure scenario.',
				'implementation'     => 'Systems were onboarded by criticality, followed by a full failover test with the operations team and a lessons-learned review.',
				'results'            => array( array( '4 h', 'recovery time for critical systems' ), array( '15 min', 'recovery point objective' ), array( '100%', 'restore tests passed' ) ),
				'technologies'       => array( 'Immutable backup', 'Site replication', 'Runbooks', 'Restore testing' ),
				'testimonial'        => 'The failover test was the first time we truly believed our recovery plan.',
				'testimonial_author' => 'Morgan Lee, Operations Director — demo testimonial',
			),
			'services'  => array( 'backup-disaster-recovery', 'data-center-solutions' ),
			'solutions' => array( 'banking-financial-services', 'data-centers-colocation' ),
		),
	);
}
