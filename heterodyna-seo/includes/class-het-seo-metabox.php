<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Editor metabox for per-post SEO fields.
 * - Works in Classic + Gutenberg (as a metabox panel).
 * - Stores to post meta:
 *   het_seo_meta_title, het_seo_meta_description, het_seo_canonical, het_seo_noindex, het_seo_nofollow
 */
class HET_SEO_Metabox {
    private $logger;

    public function __construct(HET_SEO_Logger $logger) {
        $this->logger = $logger;
        add_action('add_meta_boxes', [$this, 'register']);
        add_action('save_post', [$this, 'save'], 10, 2);
    }

    public function register() {
        $post_types = get_post_types(['public' => true], 'names');
        foreach ($post_types as $pt) {
            if ($pt === 'attachment') { continue; }
            add_meta_box(
                'het_seo_metabox',
                'Heterodyna SEO',
                [$this, 'render'],
                $pt,
                'normal',
                'high'
            );
        }
    }

    public function render($post) {
        if (!$post || !($post instanceof WP_Post)) { return; }
        wp_nonce_field('het_seo_metabox_save', 'het_seo_metabox_nonce');

        $title = (string) get_post_meta($post->ID, 'het_seo_meta_title', true);
        $desc  = (string) get_post_meta($post->ID, 'het_seo_meta_description', true);
        $canon = (string) get_post_meta($post->ID, 'het_seo_canonical', true);
        $noindex = (int) get_post_meta($post->ID, 'het_seo_noindex', true);
        $nofollow = (int) get_post_meta($post->ID, 'het_seo_nofollow', true);

        // Fallback preview using patterns if empty (same logic as wp_head output, but simplified for editor preview)
        $opts = get_option(HET_SEO_OPT, []);
        $pt = get_post_type($post);
        $site = get_bloginfo('name');
        $sep  = '—';
        $date = get_the_date('', $post);
        $excerpt = trim(wp_strip_all_tags(get_the_excerpt($post)));
        if ($excerpt === '') {
            $excerpt = trim(wp_strip_all_tags(wp_trim_words($post->post_content ?? '', 30, '')));
        }
        $p_title = (string)($opts['pattern_title_' . $pt] ?? '');
        $p_desc  = (string)($opts['pattern_desc_' . $pt] ?? '');
        if ($p_title === '') { $p_title = (string)($opts['pattern_title_default'] ?? ''); }
        if ($p_desc === '')  { $p_desc  = (string)($opts['pattern_desc_default'] ?? ''); }
        if ($p_title === '') { $p_title = '{title} {sep} {site}'; }
        if ($p_desc === '')  { $p_desc  = '{excerpt}'; }
        $replace = [
            '{title}' => get_the_title($post),
            '{site}' => $site,
            '{sep}' => $sep,
            '{date}' => $date,
            '{excerpt}' => $excerpt,
        ];
        $preview_title = $title !== '' ? $title : trim(preg_replace('/\s+/', ' ', strtr($p_title, $replace)));
        $preview_desc  = $desc  !== '' ? $desc  : trim(preg_replace('/\s+/', ' ', strtr($p_desc, $replace)));

        $permalink = get_permalink($post);

        echo '<div class="het-seo-metabox">';
        echo '  <div class="het-seo-metabox__grid">';
        echo '    <div class="het-seo-metabox__fields">';
        echo '      <p><label><strong>Title</strong></label><br>';
        echo '      <input type="text" class="widefat het-seo-mb-title" name="het_seo_meta_title" value="' . esc_attr($title) . '" placeholder="(puste = wzorzec)"></p>';
        echo '      <p class="description"><span class="het-seo-mb-title-count">0</span> znaków • zalecane ~ 45–60</p>';

        echo '      <p><label><strong>Description</strong></label><br>';
        echo '      <textarea class="widefat het-seo-mb-desc" rows="3" name="het_seo_meta_description" placeholder="(puste = wzorzec)">' . esc_textarea($desc) . '</textarea></p>';
        echo '      <p class="description"><span class="het-seo-mb-desc-count">0</span> znaków • zalecane ~ 120–160</p>';

        echo '      <p><label><strong>Canonical</strong></label><br>';
        echo '      <input type="url" class="widefat" name="het_seo_canonical" value="' . esc_attr($canon) . '" placeholder="https://... (opcjonalnie)"></p>';

        echo '      <p style="margin-top:10px">';
        echo '        <label style="margin-right:14px"><input type="checkbox" name="het_seo_noindex" value="1" ' . checked(1, $noindex, false) . '> noindex</label>';
        echo '        <label><input type="checkbox" name="het_seo_nofollow" value="1" ' . checked(1, $nofollow, false) . '> nofollow</label>';
        echo '      </p>';
        echo '      <p class="description">AI Auto i wzorce nie nadpisują ręcznie ustawionych pól.</p>';
        echo '    </div>';

        echo '    <div class="het-seo-metabox__preview">';
        echo '      <div class="het-seo-snippet">';
        echo '        <div class="het-seo-snippet__url">' . esc_html(preg_replace('#^https?://#', '', (string)$permalink)) . '</div>';
        echo '        <div class="het-seo-snippet__title het-seo-mb-preview-title">' . esc_html($preview_title) . '</div>';
        echo '        <div class="het-seo-snippet__desc het-seo-mb-preview-desc">' . esc_html($preview_desc) . '</div>';
        echo '      </div>';
        echo '    </div>';
        echo '  </div>';

        // Lightweight inline JS to update counters + preview without relying on global admin assets
        echo '<script>(function(){
            function len(s){return (s||"").toString().length;}
            var title=document.querySelector(".het-seo-metabox .het-seo-mb-title");
            var desc=document.querySelector(".het-seo-metabox .het-seo-mb-desc");
            if(!title||!desc){return;}
            var tc=document.querySelector(".het-seo-metabox .het-seo-mb-title-count");
            var dc=document.querySelector(".het-seo-metabox .het-seo-mb-desc-count");
            var pt=document.querySelector(".het-seo-metabox .het-seo-mb-preview-title");
            var pd=document.querySelector(".het-seo-metabox .het-seo-mb-preview-desc");
            function upd(){
                if(tc) tc.textContent=len(title.value);
                if(dc) dc.textContent=len(desc.value);
                if(pt) pt.textContent=title.value.trim()!==""?title.value:pt.textContent;
                if(pd) pd.textContent=desc.value.trim()!==""?desc.value:pd.textContent;
            }
            title.addEventListener("input", upd);
            desc.addEventListener("input", upd);
            upd();
        })();</script>';

        echo '</div>';
    }

    public function save($post_id, $post) {
        if (!isset($_POST['het_seo_metabox_nonce']) || !wp_verify_nonce($_POST['het_seo_metabox_nonce'], 'het_seo_metabox_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return; }
        if (!($post instanceof WP_Post)) { return; }
        if (!current_user_can('edit_post', $post_id)) { return; }

        // Only for public post types
        $pt = get_post_type($post_id);
        $obj = get_post_type_object($pt);
        if (!$obj || empty($obj->public) || $pt === 'attachment') { return; }

        $title = isset($_POST['het_seo_meta_title']) ? sanitize_text_field(wp_unslash($_POST['het_seo_meta_title'])) : '';
        $desc  = isset($_POST['het_seo_meta_description']) ? sanitize_textarea_field(wp_unslash($_POST['het_seo_meta_description'])) : '';
        $canon = isset($_POST['het_seo_canonical']) ? esc_url_raw(wp_unslash($_POST['het_seo_canonical'])) : '';
        $noindex = !empty($_POST['het_seo_noindex']) ? 1 : 0;
        $nofollow = !empty($_POST['het_seo_nofollow']) ? 1 : 0;

        update_post_meta($post_id, 'het_seo_meta_title', $title);
        update_post_meta($post_id, 'het_seo_meta_description', $desc);
        update_post_meta($post_id, 'het_seo_canonical', $canon);
        update_post_meta($post_id, 'het_seo_noindex', $noindex);
        update_post_meta($post_id, 'het_seo_nofollow', $nofollow);
    }
}
