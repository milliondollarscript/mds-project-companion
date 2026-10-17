<?php
/**
 * Local explanatory catalogue for production-intended MDS extensions.
 *
 * Runtime discovery remains authoritative for installed and active state.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Recommendations;

if (!defined('ABSPATH')) {
    exit;
}
final class Catalogue {
    /**
     * @return array<string,array<string,mixed>>
     */
    public static function all(): array {
        $items = [
            ['mds-attention-cooperative', 'Attention Cooperative', 'Coordinate approved sponsor-inventory partnerships.', 'free', false, ['sponsorship', 'distribution', 'local'], 'advanced'],
            ['mds-bounty-engine', 'Bounty Engine', 'Run moderated public bounty and request boards.', 'free', false, ['bounties', 'funding'], 'moderate'],
            ['mds-contact-form', 'Contact Form', 'Collect simple site enquiries with spam protection.', 'free', false, ['distribution', 'growth'], 'simple'],
            ['mds-google-analytics', 'Analytics', 'Measure site and campaign activity with analytics integrations.', 'free', true, ['analytics', 'growth'], 'moderate'],
            ['mds-imagegrid', 'ImageGrid Rendering', 'Render large grids through the ImageGrid service.', 'free', true, ['distribution', 'growth'], 'moderate'],
            ['mds-launch-wall', 'Launch Wall', 'Publish and monetize moderated launch listings.', 'free', false, ['launches', 'funding', 'distribution'], 'moderate'],
            ['mds-local-money-map', 'Local Money Map', 'Build local sponsor maps, trails, offers, and QR campaigns.', 'free', false, ['local', 'sponsorship', 'funding'], 'moderate'],
            ['mds-revenue-reports', 'Revenue Reports', 'Review local revenue, order status, and grid performance.', 'free', false, ['analytics', 'funding', 'growth'], 'simple'],
            ['mds-seo-basic', 'SEO Basic', 'Add search metadata, social cards, schema, and sitemaps.', 'free', false, ['distribution', 'growth'], 'simple'],
            ['mds-sponsorable-ai-tools', 'Sponsorable AI Tools', 'Publish sponsor-supported calculators, planners, and generators.', 'free', false, ['tools', 'sponsorship', 'funding'], 'advanced'],
            ['mds-sponsorboard', 'SponsorBoard', 'Sell and manage sponsor boards, slots, bookings, and creatives.', 'free', false, ['sponsorship', 'funding'], 'moderate'],
            ['mds-support-passport', 'Proof of Support Passport', 'Issue privacy-first local supporter badges and proof pages.', 'free', false, ['sponsorship', 'growth'], 'moderate'],
            ['mds-woocommerce', 'WooCommerce Checkout', 'Use WooCommerce as an MDS payment provider.', 'free', false, ['funding', 'recurring'], 'moderate'],
            ['mds-advertiser-workspace', 'Advertiser Workspace', 'Give verified advertisers secure placement self-service.', 'paid', false, ['sponsorship', 'growth'], 'advanced'],
            ['mds-campaign-scheduler', 'Campaign Scheduler', 'Schedule, preview, pause, and audit campaigns.', 'paid', false, ['launches', 'distribution'], 'advanced'],
            ['mds-fields', 'Fields', 'Customize placement fields and popup presentation.', 'paid', false, ['distribution', 'growth'], 'moderate'],
            ['mds-missions', 'Missions', 'Create public missions with support, milestones, and recognition.', 'paid', false, ['missions', 'funding', 'sponsorship'], 'advanced'],
            ['mds-revenue-agent', 'Revenue Agent', 'Draft sponsor packages and outreach from approved context.', 'paid', false, ['sponsorship', 'funding'], 'advanced'],
            ['mds-search-visibility', 'Search Visibility', 'Add MDS-specific search audits and redirect intelligence.', 'paid', false, ['analytics', 'distribution', 'growth'], 'advanced'],
            ['mds-subscriptions', 'Subscriptions', 'Offer recurring plans, trials, allowances, and entitlements.', 'paid', false, ['recurring', 'funding'], 'advanced'],
            ['mds-time-capsule', 'Time Capsule', 'Sell moderated future-reveal slots and sponsor moments.', 'paid', false, ['launches', 'sponsorship', 'funding'], 'advanced'],
            ['mds-translation', 'Translation', 'Translate Million Dollar Script core and extension text.', 'paid', false, ['distribution', 'growth'], 'moderate'],
        ];

        $catalogue = [];
        foreach ($items as [$slug, $name, $summary, $commercial, $service, $goals, $effort]) {
            $catalogue[$slug] = [
                'slug' => $slug,
                'name' => $name,
                'summary' => $summary,
                'commercial' => $commercial,
                'requires_service' => $service,
                'goals' => $goals,
                'setup_effort' => $effort,
                'alternatives' => self::alternatives($slug),
                'without_it' => self::without_it($slug),
            ];
        }
        return $catalogue;
    }

    /**
     * @return array<int,string>
     */
    private static function alternatives(string $slug): array {
        $map = [
            'mds-sponsorboard' => ['mds-launch-wall', 'mds-local-money-map'],
            'mds-launch-wall' => ['mds-sponsorboard', 'mds-time-capsule'],
            'mds-google-analytics' => ['Use privacy-respecting server logs or another analytics plugin.'],
            'mds-imagegrid' => ['Use the bundled local grid renderer for supported grid sizes.'],
            'mds-woocommerce' => ['Use another public MDS payment-provider adapter when available.'],
            'mds-revenue-agent' => ['Draft sponsor material manually from approved project context.'],
            'mds-search-visibility' => ['mds-seo-basic'],
            'mds-subscriptions' => ['Offer one-time products through an active payment provider.'],
        ];
        return $map[$slug] ?? [];
    }

    private static function without_it(string $slug): string {
        $map = [
            'mds-imagegrid' => 'Continue with supported local grid rendering.',
            'mds-revenue-agent' => 'Prepare and review sponsor drafts manually.',
            'mds-subscriptions' => 'Sell one-time access or support packages.',
            'mds-translation' => 'Use WordPress translation files and existing localization tools.',
        ];
        return $map[$slug] ?? 'Use Million Dollar Script core features and a manual workflow.';
    }
}
