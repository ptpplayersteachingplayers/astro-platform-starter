<?php
/**
 * Plugin Name: PTP Product Page Designer - Complete Fix
 * Description: Optimized layout with proper sizing, social links, and all sections
 * Version: 5.3
 * Author: PTP Team
 */

if (!defined('ABSPATH')) {
    exit;
}

class PTP_Product_Page_Designer_Complete {

    public function __construct() {
        // Enqueue styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'), 999);
        
        // Remove default price from summary
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
        
        // Add custom elements to product page
        add_action('woocommerce_before_single_product', array($this, 'add_seo_breadcrumb'), 5);
        add_action('woocommerce_single_product_summary', array($this, 'add_facts_bar'), 8);
        add_action('woocommerce_single_product_summary', array($this, 'add_buy_box_price'), 25);
        add_action('woocommerce_single_product_summary', array($this, 'add_coach_strip'), 70);
        
        // Add elements under product images
        add_action('woocommerce_product_thumbnails', array($this, 'add_invite_friend_section'), 20);
        add_action('woocommerce_product_thumbnails', array($this, 'add_social_links'), 22);
        add_action('woocommerce_product_thumbnails', array($this, 'add_trustindex_under_image'), 25);
        
        // Move tabs into summary area
        remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10);
        add_action('woocommerce_single_product_summary', array($this, 'output_product_tabs'), 80);
        
        // Customize tabs
        add_filter('woocommerce_product_tabs', array($this, 'add_custom_product_tabs'), 98);
        
        // Related products
        add_filter('woocommerce_output_related_products_args', array($this, 'customize_related_products'));
        add_action('woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20);
        
        // SEO & Schema
        add_action('wp_head', array($this, 'add_seo_meta_tags'), 1);
        add_action('wp_footer', array($this, 'output_event_schema'));
        add_action('wp_footer', array($this, 'output_invite_friend_script'), 25);
        
        // Debug output (remove after confirming it works)
        add_action('wp_footer', array($this, 'debug_output'));
    }

    /**
     * Debug output to confirm plugin is running
     */
    public function debug_output() {
        if (is_product()) {
            echo '<!-- PTP Product Page Designer v5.3 is ACTIVE -->';
        }
    }

    private function get_current_product() {
        if (!is_product()) {
            return null;
        }
        global $product;
        return ($product instanceof WC_Product) ? $product : null;
    }

    private function format_event_date($raw) {
        if (empty($raw)) {
            return '';
        }
        if (is_numeric($raw)) {
            $timestamp = (int) $raw;
        } else {
            $timestamp = strtotime($raw);
        }
        if (!$timestamp) {
            return is_string($raw) ? $raw : '';
        }
        return wp_date('M j, Y', $timestamp);
    }

    private function format_event_time($raw) {
        if (empty($raw)) {
            return '';
        }
        if (strlen($raw) <= 8 && preg_match('/\d/', $raw)) {
            return $raw;
        }
        $timestamp = is_numeric($raw) ? (int) $raw : strtotime($raw);
        if (!$timestamp) {
            return is_string($raw) ? $raw : '';
        }
        return wp_date('g:i A', $timestamp);
    }

    private function format_iso8601($raw) {
        if (empty($raw)) {
            return null;
        }
        if (function_exists('wp_timezone')) {
            $timezone = wp_timezone();
        } elseif (function_exists('wp_timezone_string')) {
            $timezone = wp_timezone_string();
        } else {
            $timezone = get_option('timezone_string');
        }
        if (empty($timezone)) {
            $timezone = date_default_timezone_get();
        }
        try {
            if ($raw instanceof DateTimeInterface) {
                $dt = new DateTime($raw->format('c'));
            } elseif (is_numeric($raw)) {
                $dt = new DateTime('@' . (int) $raw);
            } else {
                $tz = is_object($timezone) ? $timezone : new DateTimeZone(is_string($timezone) ? (string) $timezone : date_default_timezone_get());
                $dt = new DateTime((string) $raw, $tz);
            }
            if (is_object($timezone)) {
                $dt->setTimezone($timezone);
            } elseif (is_string($timezone) && $timezone) {
                $dt->setTimezone(new DateTimeZone($timezone));
            }
            return $dt->format(DateTime::ATOM);
        } catch (Exception $e) {
            return null;
        }
    }

    public function enqueue_styles() {
        if (is_product()) {
            wp_enqueue_style(
                'ptp-product-styles',
                plugin_dir_url(__FILE__) . 'ptp-product-styles.css',
                array(),
                '5.3'
            );
        }
    }

    public function add_seo_breadcrumb() {
        $product = $this->get_current_product();
        if (!$product) {
            return;
        }

        $city = $product->get_attribute('city') ?: get_post_meta($product->get_id(), '_ptp_city', true);
        $state = $product->get_attribute('state') ?: get_post_meta($product->get_id(), '_ptp_state', true);
        
        if ($city || $state) {
            ?>
            <div class="ptp-location-breadcrumb">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                <?php echo esc_html($city); ?><?php echo $state ? ', ' . esc_html($state) : ''; ?>
                <span class="ptp-breadcrumb-divider">•</span>
                <span>3-hour clinic</span>
            </div>
            <?php
        }
    }

    public function add_facts_bar() {
        $product = $this->get_current_product();
        if (!$product) {
            return;
        }

        $product_id = $product->get_id();
        $date = $product->get_attribute('date') ?: get_post_meta($product_id, '_ptp_event_start', true);
        $time = $product->get_attribute('time') ?: get_post_meta($product_id, '_ptp_event_start', true);
        $ages = $product->get_attribute('age') ?: get_post_meta($product_id, '_ptp_age_band', true);
        $venue = $product->get_attribute('venue') ?: get_post_meta($product_id, '_ptp_venue_name', true);

        $date_display = $this->format_event_date($date);
        $time_display = $this->format_event_time($time);

        ?>
        <div class="ptp-facts-bar">
            <?php if ($venue) : ?>
                <div class="ptp-fact-item">
                    <strong>Location:</strong> <?php echo esc_html($venue); ?> — venue announced soon
                </div>
            <?php endif; ?>
            <?php if ($date_display && $time_display) : ?>
                <div class="ptp-fact-item">
                    <strong>When:</strong> <?php echo esc_html($date_display); ?> at <?php echo esc_html($time_display); ?>
                </div>
            <?php endif; ?>
            <?php if ($ages) : ?>
                <div class="ptp-fact-item">
                    <strong>Ages:</strong> <?php echo esc_html($ages); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function add_buy_box_price() {
        $product = $this->get_current_product();
        if (!$product) {
            return;
        }

        $price_html = $product->get_price_html();
        $stock_qty = $product->get_stock_quantity();

        ?>
        <div class="ptp-buy-box-price">
            <div class="ptp-price-wrapper">
                <?php echo $price_html; ?>
            </div>
            <?php if ($stock_qty !== null && $stock_qty > 0) : ?>
                <div class="ptp-availability">
                    <strong>Availability:</strong> 
                    <span class="ptp-stock-status<?php echo $stock_qty <= 10 ? ' ptp-low-stock' : ''; ?>">
                        <?php echo esc_html($stock_qty); ?> <?php echo $stock_qty === 1 ? 'spot' : 'spots'; ?> available
                    </span>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function add_coach_strip() {
        ?>
        <section class="ptp-coach-strip" aria-labelledby="ptp-coach-strip-heading">
            <div class="ptp-coach-strip-inner">
                <div class="ptp-coach-avatars" aria-hidden="true">
                    <img src="https://ptpsummercamps.com/wp-content/uploads/2025/09/Screenshot_30-9-2025_195134_.jpeg" alt="Pro Coach" class="ptp-coach-avatar" loading="lazy">
                    <img src="https://ptpsummercamps.com/wp-content/uploads/2025/09/xxpktknsilydnboahitb-e1759276618613.jpg" alt="NCAA Coach" class="ptp-coach-avatar" loading="lazy">
                    <img src="https://ptpsummercamps.com/wp-content/uploads/2025/09/convert-1.webp" alt="Professional Coach" class="ptp-coach-avatar" loading="lazy">
                </div>
                <div class="ptp-coach-info">
                    <h3 id="ptp-coach-strip-heading" class="ptp-coach-title">Led by NCAA &amp; Pro Coaches</h3>
                    <p class="ptp-coach-copy">Small group stations, game-speed reps, and live feedback so every player levels up.</p>
                </div>
            </div>
        </section>
        <?php
    }

    public function add_invite_friend_section() {
        $product = $this->get_current_product();
        if (!$product) {
            echo '<!-- PTP: No product found for invite section -->';
            return;
        }

        $permalink = get_permalink($product->get_id());
        if (!$permalink) {
            echo '<!-- PTP: No permalink found -->';
            return;
        }

        $title = wp_strip_all_tags(get_the_title($product->get_id()));
        $email_subject = rawurlencode(sprintf('Join me at %s', $title));
        $email_body = rawurlencode(sprintf("I found this soccer camp and thought you'd love it!\n\n%s", $permalink));
        $sms_body = rawurlencode(sprintf('Check out this soccer camp: %s', $permalink));

        echo '<!-- PTP: Invite Friends Section START -->';
        ?>
        <section class="ptp-invite-friends" aria-labelledby="ptp-invite-heading" style="display: block !important; visibility: visible !important;">
            <div class="ptp-invite-header">
                <div class="ptp-invite-icon" aria-hidden="true">🤝</div>
                <div class="ptp-invite-text">
                    <h3 id="ptp-invite-heading">Camp is more fun with friends</h3>
                    <p>Share this clinic with teammates in one tap</p>
                </div>
            </div>
            <div class="ptp-invite-actions">
                <button type="button" class="ptp-btn ptp-btn-primary ptp-copy-link" data-link="<?php echo esc_attr($permalink); ?>">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                    Copy link
                </button>
                <a class="ptp-btn ptp-btn-secondary" href="mailto:?subject=<?php echo esc_attr($email_subject); ?>&amp;body=<?php echo esc_attr($email_body); ?>">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    Email
                </a>
                <a class="ptp-btn ptp-btn-secondary" href="sms:?&amp;body=<?php echo esc_attr($sms_body); ?>">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 00-2 2h-5l-5 5v-5z"></path>
                    </svg>
                    Text
                </a>
            </div>
            <p class="ptp-copy-feedback" role="status" aria-live="polite"></p>
        </section>
        <?php
        echo '<!-- PTP: Invite Friends Section END -->';
    }

    public function add_social_links() {
        ?>
        <div class="ptp-social-links">
            <p class="ptp-social-text">Follow PTP Sports:</p>
            <div class="ptp-social-icons">
                <a href="https://www.instagram.com/ptp.training/" target="_blank" rel="noopener noreferrer" aria-label="Follow PTP on Instagram" class="ptp-social-link">
                    <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                    </svg>
                </a>
                <a href="https://www.facebook.com/ptpsummercamps" target="_blank" rel="noopener noreferrer" aria-label="Follow PTP on Facebook" class="ptp-social-link">
                    <svg width="24" height="24" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                </a>
            </div>
        </div>
        <?php
    }

    public function add_trustindex_under_image() {
        if (!is_product() || !shortcode_exists('trustindex')) {
            return;
        }
        ?>
        <div class="ptp-trustindex-under-image">
            <h3 class="ptp-reviews-heading">What Parents Are Saying</h3>
            <?php echo do_shortcode('[trustindex no-registration=google]'); ?>
            <p class="ptp-reviews-footer">250+ families trained last season</p>
        </div>
        <?php
    }

    public function output_product_tabs() {
        woocommerce_output_product_data_tabs();
    }

    public function add_custom_product_tabs($tabs) {
        if (isset($tabs['description'])) {
            $tabs['description']['priority'] = 10;
        }

        $tabs['schedule'] = array(
            'title'    => __('Schedule', 'ptp'),
            'priority' => 20,
            'callback' => array($this, 'schedule_tab_content'),
        );

        $tabs['location'] = array(
            'title'    => __('Location', 'ptp'),
            'priority' => 30,
            'callback' => array($this, 'location_tab_content'),
        );

        $tabs['safety'] = array(
            'title'    => __('Safety', 'ptp'),
            'priority' => 50,
            'callback' => array($this, 'safety_tab_content'),
        );

        if (isset($tabs['reviews'])) {
            $tabs['reviews']['priority'] = 60;
        }

        unset($tabs['additional_information']);

        return $tabs;
    }

    public function location_tab_content() {
        $product = $this->get_current_product();
        if (!$product) {
            return;
        }

        $venue_name = $product->get_attribute('venue') ?: get_post_meta($product->get_id(), '_ptp_venue_name', true);
        $address = $product->get_attribute('address') ?: get_post_meta($product->get_id(), '_ptp_address', true);
        $city = $product->get_attribute('city') ?: get_post_meta($product->get_id(), '_ptp_city', true);
        $state = $product->get_attribute('state') ?: get_post_meta($product->get_id(), '_ptp_state', true);
        $zip = $product->get_attribute('zip') ?: get_post_meta($product->get_id(), '_ptp_zip', true);

        if (empty($venue_name)) {
            $venue_name = 'Indoor Training Facility';
        }

        $parking_info = get_post_meta($product->get_id(), '_ptp_parking_info', true) ?: 'Free parking available on-site';
        $google_maps_url = get_post_meta($product->get_id(), '_ptp_google_maps_url', true);
        $google_maps_embed = get_post_meta($product->get_id(), '_ptp_google_maps_embed', true);

        $full_address = trim(implode(', ', array_filter(array($address, $city, $state))), ', ');
        if ($zip) {
            $full_address = trim($full_address . ' ' . $zip);
        }

        ?>
        <div class="ptp-location-content">
            <h2>Clinic Location Details</h2>
            
            <div class="ptp-location-grid">
                <div class="ptp-location-item">
                    <strong>Venue</strong>
                    <p><?php echo esc_html($venue_name); ?></p>
                </div>

                <?php if (!empty($city)) : ?>
                <div class="ptp-location-item">
                    <strong>City</strong>
                    <p><?php echo esc_html($city); ?><?php echo !empty($state) ? ', ' . esc_html($state) : ''; ?></p>
                </div>
                <?php endif; ?>

                <?php if (!empty($address) || !empty($zip)) : ?>
                <div class="ptp-location-item">
                    <strong>Address</strong>
                    <p><?php echo esc_html($full_address); ?></p>
                    <?php if (!empty($google_maps_url)) : ?>
                        <p><a href="<?php echo esc_url($google_maps_url); ?>" target="_blank" rel="noopener">View on Google Maps →</a></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($parking_info)) : ?>
                <div class="ptp-location-item">
                    <strong>Parking</strong>
                    <p><?php echo esc_html($parking_info); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <div class="ptp-map">
                <?php if (!empty($google_maps_embed)) : ?>
                    <?php echo wp_kses_post($google_maps_embed); ?>
                <?php elseif (!empty($full_address) && strlen($full_address) > 5) : ?>
                    <iframe
                        src="https://www.google.com/maps?q=<?php echo rawurlencode($full_address); ?>&amp;output=embed"
                        width="100%"
                        height="450"
                        style="border:0;"
                        allowfullscreen=""
                        loading="lazy"
                        title="<?php echo esc_attr(sprintf('Map showing location of %s', $venue_name)); ?>">
                    </iframe>
                <?php else : ?>
                    <div class="ptp-map-placeholder">
                        <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <p>Venue details coming soon</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function schedule_tab_content() {
        $product = $this->get_current_product();
        if (!$product) {
            return;
        }

        $schedule_items = get_post_meta($product->get_id(), '_ptp_schedule', true);

        if (empty($schedule_items) || !is_array($schedule_items)) {
            $schedule_items = array(
                array('time' => '4:45 PM', 'activity' => 'Check-in & shirt pickup'),
                array('time' => '5:00 PM', 'activity' => 'Warm-up & introductions'),
                array('time' => '5:15 PM', 'activity' => 'Skills stations (dribbling, passing, finishing)'),
                array('time' => '6:30 PM', 'activity' => 'Small-sided games & 1v1s'),
                array('time' => '7:45 PM', 'activity' => 'Cool down & Q&A with coaches'),
                array('time' => '8:00 PM', 'activity' => 'Clinic concludes'),
            );
        }

        ?>
        <div class="ptp-schedule-content">
            <h2>What to Expect During the Clinic</h2>
            <p class="ptp-schedule-intro">Here's the 3-hour breakdown for this elite winter soccer clinic:</p>

            <div class="ptp-schedule-timeline">
                <?php foreach ($schedule_items as $item) :
                    $time = isset($item['time']) ? $item['time'] : '';
                    $activity = isset($item['activity']) ? $item['activity'] : '';

                    if ($time === '' && $activity === '') {
                        continue;
                    }
                    ?>
                    <div class="ptp-schedule-item">
                        <div class="ptp-schedule-time"><?php echo esc_html($time); ?></div>
                        <div class="ptp-schedule-activity"><?php echo esc_html($activity); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="ptp-schedule-note">
                <strong>Note:</strong> Schedule is subject to change. We'll notify you of any updates via email and text.
            </div>
        </div>
        <?php
    }

    public function safety_tab_content() {
        $product = $this->get_current_product();
        if (!$product) {
            return;
        }

        $custom_reminders = get_post_meta($product->get_id(), '_ptp_safety_reminders', true);

        ?>
        <div class="ptp-safety-content">
            <h2>Safety &amp; What to Bring</h2>
            <p class="ptp-safety-intro">Please review these important reminders before attending your PTP Soccer Clinic.</p>

            <div class="ptp-safety-grid">
                <div class="ptp-safety-item">
                    <div class="ptp-safety-icon">⚽</div>
                    <h3>What to Bring</h3>
                    <ul>
                        <li><strong>Required:</strong> Cleats or flats, shin guards, personal water bottle, your own ball</li>
                        <li><strong>Optional:</strong> Goalkeeper gloves</li>
                        <li><strong>Provided by PTP:</strong> Training balls (extras), cones, goals, pinnies, and a PTP training shirt at check-in</li>
                    </ul>
                </div>

                <div class="ptp-safety-item">
                    <div class="ptp-safety-icon">👕</div>
                    <h3>Attire</h3>
                    <ul>
                        <li>Athletic wear with cleats or flats (indoor-appropriate footwear if the venue specifies)</li>
                        <li>Shin guards are required for all players</li>
                    </ul>
                </div>

                <div class="ptp-safety-item">
                    <div class="ptp-safety-icon">🏥</div>
                    <h3>Medical &amp; Safety</h3>
                    <ul>
                        <li>All Site Leads are CPR/First Aid certified</li>
                        <li>If your child has an EpiPen or inhaler, hand it to the Site Lead at check-in with a clear label (name and instructions)</li>
                        <li>Please list allergies or medical information during registration and confirm at check-in</li>
                    </ul>
                </div>

                <div class="ptp-safety-item">
                    <div class="ptp-safety-icon">☔</div>
                    <h3>Weather Policy</h3>
                    <p>Winter clinics are held indoors. If a rare facility or travel advisory requires changes, we will notify you by email and text with any schedule adjustments or credits.</p>
                </div>

                <div class="ptp-safety-item">
                    <div class="ptp-safety-icon">👨‍👩‍👧</div>
                    <h3>Check-In &amp; Pick-Up</h3>
                    <ul>
                        <li>Arrive 15 minutes early to allow for check-in and shirt pickup</li>
                        <li>Children must be signed in and out by an authorized adult</li>
                        <li>Spectators should remain in designated viewing areas</li>
                    </ul>
                </div>

                <div class="ptp-safety-item">
                    <div class="ptp-safety-icon">📞</div>
                    <h3>Questions?</h3>
                    <p><strong>Phone:</strong> <a href="tel:2154755801">(215) 475-5801</a><br>
                    <strong>Email:</strong> <a href="mailto:luke@ptpsummercamps.com">luke@ptpsummercamps.com</a></p>
                </div>
            </div>

            <?php if (!empty($custom_reminders)) : ?>
                <div class="ptp-custom-safety">
                    <h3>Clinic-Specific Information</h3>
                    <?php echo wpautop(wp_kses_post($custom_reminders)); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function customize_related_products($args) {
        $args['posts_per_page'] = 3;
        return $args;
    }

    public function add_seo_meta_tags() {
        $product = $this->get_current_product();
        if (!$product) {
            return;
        }

        $product_id = $product->get_id();
        $title = get_the_title($product_id);
        $city = $product->get_attribute('city') ?: get_post_meta($product_id, '_ptp_city', true);
        $state = $product->get_attribute('state') ?: get_post_meta($product_id, '_ptp_state', true);
        $date = $this->format_event_date($product->get_attribute('date') ?: get_post_meta($product_id, '_ptp_event_start', true));
        
        $location = trim($city . ($state ? ', ' . $state : ''));
        
        $seo_title = $location ? "Winter Soccer Clinic in {$location} | {$title} | PTP Sports" : "{$title} | Winter Soccer Clinics | PTP Sports";
        $description = "Join PTP's elite winter soccer clinic in {$location}" . ($date ? " on {$date}" : "") . ". Led by NCAA & Pro coaches. Small groups, game-speed reps, 3-hour indoor training. Limited spots available. Register now!";
        
        ?>
        <meta name="description" content="<?php echo esc_attr($description); ?>">
        <meta property="og:title" content="<?php echo esc_attr($seo_title); ?>">
        <meta property="og:description" content="<?php echo esc_attr($description); ?>">
        <meta property="og:type" content="event">
        <?php if ($location) : ?>
        <meta name="geo.placename" content="<?php echo esc_attr($location); ?>">
        <meta name="keywords" content="<?php echo esc_attr("soccer camps {$location}, youth soccer clinic {$city}, indoor soccer training {$state}, winter soccer camp near me, kids soccer programs {$location}"); ?>">
        <?php endif; ?>
        <?php
    }

    public function output_event_schema() {
        $product = $this->get_current_product();
        if (!$product) {
            return;
        }

        $product_id = $product->get_id();
        $start_meta = get_post_meta($product_id, '_ptp_event_start', true);
        $end_meta   = get_post_meta($product_id, '_ptp_event_end', true);
        $start_iso = $this->format_iso8601($start_meta ?: $product->get_attribute('date'));
        $end_iso   = $this->format_iso8601($end_meta ?: $product->get_attribute('end-date'));

        if (!$start_iso) {
            return;
        }

        $venue   = $product->get_attribute('venue') ?: get_post_meta($product_id, '_ptp_venue_name', true);
        $address = $product->get_attribute('address') ?: get_post_meta($product_id, '_ptp_address', true);
        $city    = $product->get_attribute('city') ?: get_post_meta($product_id, '_ptp_city', true);
        $state   = $product->get_attribute('state') ?: get_post_meta($product_id, '_ptp_state', true);
        $zip     = $product->get_attribute('zip') ?: get_post_meta($product_id, '_ptp_zip', true);
        $image_id  = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'full') : '';
        $description = $product->get_short_description();
        if (empty($description)) {
            $description = $product->get_description();
        }
        $price = wc_get_price_to_display($product);
        $currency = get_woocommerce_currency();

        $offers = array(
            '@type'         => 'Offer',
            'url'           => get_permalink($product_id),
            'priceCurrency' => $currency,
            'availability'  => 'https://schema.org/' . ($product->is_in_stock() ? 'InStock' : 'OutOfStock'),
            'validFrom'     => $start_iso,
        );

        if ($price !== '') {
            $offers['price'] = (float) $price;
        }

        $event = array(
            '@context' => 'https://schema.org',
            '@type'    => 'SportsEvent',
            'name'     => wp_strip_all_tags(get_the_title($product_id)),
            'description' => wp_strip_all_tags($description),
            'startDate' => $start_iso,
            'sport' => 'Soccer',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'eventStatus' => 'https://schema.org/EventScheduled',
            'location' => array(
                '@type' => 'Place',
                'name'  => wp_strip_all_tags($venue ?: 'Indoor Training Facility'),
                'address' => array(
                    '@type'           => 'PostalAddress',
                    'streetAddress'   => wp_strip_all_tags($address),
                    'addressLocality' => wp_strip_all_tags($city),
                    'addressRegion'   => wp_strip_all_tags($state),
                    'postalCode'      => wp_strip_all_tags($zip),
                    'addressCountry'  => 'US',
                ),
            ),
            'offers' => $offers,
            'organizer' => array(
                '@type' => 'Organization',
                'name' => 'PTP Sports',
                'url' => home_url(),
            ),
            'performer' => array(
                '@type' => 'Organization',
                'name' => 'NCAA & Professional Coaches',
            ),
        );

        if ($end_iso) {
            $event['endDate'] = $end_iso;
        }
        if ($image_url) {
            $event['image'] = array($image_url);
        }

        $json = wp_json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!$json) {
            return;
        }
        echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
    }

    public function output_invite_friend_script() {
        if (!is_product()) {
            return;
        }

        $copy_success = esc_js('Link copied! Share it with your teammates.');
        $copy_error = esc_js('Copy failed — please try again.');

        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
          var button = document.querySelector('.ptp-copy-link');
          var feedback = document.querySelector('.ptp-copy-feedback');
          
          if (!button || !feedback) return;

          button.addEventListener('click', function() {
            var link = button.getAttribute('data-link');

            function showSuccess() {
              feedback.textContent = '<?php echo $copy_success; ?>';
              feedback.classList.remove('is-error');
              feedback.classList.add('is-visible');
              setTimeout(function() {
                feedback.classList.remove('is-visible');
              }, 3000);
            }

            function showError() {
              feedback.textContent = '<?php echo $copy_error; ?>';
              feedback.classList.add('is-visible', 'is-error');
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
              navigator.clipboard.writeText(link).then(showSuccess).catch(showError);
              return;
            }

            try {
              var temp = document.createElement('textarea');
              temp.value = link;
              temp.setAttribute('readonly', '');
              temp.style.position = 'absolute';
              temp.style.left = '-9999px';
              document.body.appendChild(temp);
              temp.select();
              temp.setSelectionRange(0, temp.value.length);
              var successful = document.execCommand('copy');
              document.body.removeChild(temp);
              if (successful) {
                showSuccess();
              } else {
                showError();
              }
            } catch (err) {
              showError();
            }
          });
        });
        </script>
        <?php
    }
}

// Initialize the plugin
new PTP_Product_Page_Designer_Complete();

// Meta boxes for admin
add_action('add_meta_boxes', 'ptp_add_meta_boxes');
function ptp_add_meta_boxes() {
    add_meta_box('ptp_location_meta', 'PTP Location Details', 'ptp_location_meta_box', 'product', 'normal');
    add_meta_box('ptp_schedule_meta', 'PTP Event Schedule', 'ptp_schedule_meta_box', 'product', 'normal');
    add_meta_box('ptp_safety_meta', 'PTP Safety Reminders', 'ptp_safety_meta_box', 'product', 'normal');
}

function ptp_location_meta_box($post) {
    wp_nonce_field('ptp_meta', 'ptp_meta_nonce');
    $fields = array('venue_name', 'address', 'city', 'state', 'zip', 'parking_info', 'google_maps_url');
    foreach ($fields as $field) {
        $value = get_post_meta($post->ID, '_ptp_' . $field, true);
        echo '<p><label><strong>' . esc_html(ucwords(str_replace('_', ' ', $field))) . ':</strong></label><br>';
        $type = $field === 'parking_info' ? 'textarea' : 'input';
        if ($type === 'textarea') {
            echo '<textarea name="ptp_' . esc_attr($field) . '" style="width:100%;height:80px;">' . esc_textarea($value) . '</textarea>';
        } else {
            echo '<input type="text" name="ptp_' . esc_attr($field) . '" value="' . esc_attr($value) . '" style="width:100%;">';
        }
        echo '</p>';
    }
    $embed = get_post_meta($post->ID, '_ptp_google_maps_embed', true);
    echo '<p><label><strong>Google Maps Embed:</strong></label><br>';
    echo '<textarea name="ptp_google_maps_embed" style="width:100%;height:100px;">' . esc_textarea($embed) . '</textarea></p>';
}

function ptp_schedule_meta_box($post) {
    wp_nonce_field('ptp_meta', 'ptp_meta_nonce');
    $schedule = get_post_meta($post->ID, '_ptp_schedule', true);
    if (empty($schedule) || !is_array($schedule)) {
        $schedule = array(array('time' => '', 'activity' => ''));
    }
    echo '<div id="ptp-schedule-items">';
    foreach ($schedule as $i => $item) {
        $time = isset($item['time']) ? $item['time'] : '';
        $activity = isset($item['activity']) ? $item['activity'] : '';
        echo '<div style="margin-bottom:10px;">';
        echo '<input type="text" name="ptp_schedule[' . intval($i) . '][time]" value="' . esc_attr($time) . '" placeholder="9:00 AM" style="width:20%;" />';
        echo '<input type="text" name="ptp_schedule[' . intval($i) . '][activity]" value="' . esc_attr($activity) . '" placeholder="Activity" style="width:75%;margin-left:4px;" />';
        echo '</div>';
    }
    echo '</div><button type="button" id="ptp-add-schedule" class="button">Add Item</button>';
    $count = count($schedule);
    $template = '<div style="margin-bottom:10px;"><input type="text" name="ptp_schedule[__index__][time]" placeholder="9:00 AM" style="width:20%;" /> <input type="text" name="ptp_schedule[__index__][activity]" placeholder="Activity" style="width:75%;margin-left:4px;" /></div>';
    $encoded_template = wp_json_encode($template);
    if ($encoded_template) {
        echo '<script>jQuery(function($){var i=' . (int) $count . ';var tpl=' . $encoded_template . ';$("#ptp-add-schedule").on("click",function(){var html=tpl.replace(/__index__/g,i);$("#ptp-schedule-items").append(html);i++;});});</script>';
    }
}

function ptp_safety_meta_box($post) {
    wp_nonce_field('ptp_meta', 'ptp_meta_nonce');
    $reminders = get_post_meta($post->ID, '_ptp_safety_reminders', true);
    echo '<p><label><strong>Additional Safety Reminders:</strong></label><br>';
    echo '<textarea name="ptp_safety_reminders" style="width:100%;height:150px;">' . esc_textarea($reminders) . '</textarea></p>';
}

add_action('save_post', 'ptp_save_meta_boxes');
function ptp_save_meta_boxes($post_id) {
    if (!isset($_POST['ptp_meta_nonce']) || !wp_verify_nonce($_POST['ptp_meta_nonce'], 'ptp_meta')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (isset($_POST['post_type']) && 'product' !== $_POST['post_type']) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $text_fields = array('venue_name', 'address', 'city', 'state', 'zip', 'parking_info');
    foreach ($text_fields as $field) {
        if (isset($_POST['ptp_' . $field])) {
            $value = $field === 'parking_info' ? sanitize_textarea_field(wp_unslash($_POST['ptp_' . $field])) : sanitize_text_field(wp_unslash($_POST['ptp_' . $field]));
            update_post_meta($post_id, '_ptp_' . $field, $value);
        }
    }

    if (isset($_POST['ptp_google_maps_url'])) {
        update_post_meta($post_id, '_ptp_google_maps_url', esc_url_raw(wp_unslash($_POST['ptp_google_maps_url'])));
    }
    if (isset($_POST['ptp_google_maps_embed'])) {
        update_post_meta($post_id, '_ptp_google_maps_embed', wp_kses_post(wp_unslash($_POST['ptp_google_maps_embed'])));
    }
    if (isset($_POST['ptp_safety_reminders'])) {
        update_post_meta($post_id, '_ptp_safety_reminders', wp_kses_post(wp_unslash($_POST['ptp_safety_reminders'])));
    }

    if (isset($_POST['ptp_schedule']) && is_array($_POST['ptp_schedule'])) {
        $schedule = array();
        foreach ($_POST['ptp_schedule'] as $item) {
            $time = isset($item['time']) ? sanitize_text_field(wp_unslash($item['time'])) : '';
            $activity = isset($item['activity']) ? sanitize_text_field(wp_unslash($item['activity'])) : '';
            if ($time === '' && $activity === '') {
                continue;
            }
            $schedule[] = array('time' => $time, 'activity' => $activity);
        }
        update_post_meta($post_id, '_ptp_schedule', $schedule);
    }
}
