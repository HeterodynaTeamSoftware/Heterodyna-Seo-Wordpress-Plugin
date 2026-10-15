<?php
if (!defined('ABSPATH')) { exit; }

class HET_SEO_Social {

    public function __construct() {
        add_action('wp_head', [$this, 'output_social_meta'], 5);
    }

    private function opts(): array {
        $opts = get_option(HET_SEO_OPT, []);
        return is_array($opts) ? $opts : [];
    }

    public function output_social_meta() {
        $opts = $this->opts();
        if (empty($opts['social_enabled'])) { return; }

        // Determine context
        $is_singular = is_singular();
        $is_front = is_front_page() || is_home();

        $url = $is_singular ? get_permalink() : home_url('/');
        if (!$url) { $url = home_url('/'); }

        $title = '';
        $desc  = '';
        $image = '';

        if ($is_singular) {
            $id = get_queried_object_id();
            if ($id) {
                $mt = (string) get_post_meta($id, 'het_seo_meta_title', true);
                $md = (string) get_post_meta($id, 'het_seo_meta_description', true);

                $title = $mt !== '' ? $mt : get_the_title($id);
                $desc  = $md !== '' ? $md : '';

                if ($desc === '') {
                    $raw = wp_strip_all_tags((string) get_post_field('post_content', $id));
                    $raw = preg_replace('/\s+/', ' ', trim((string)$raw));
                    $desc = mb_substr($raw, 0, 200);
                }

                if (has_post_thumbnail($id)) {
                    $img = wp_get_attachment_image_src(get_post_thumbnail_id($id), 'full');
                    if (!empty($img[0])) { $image = (string)$img[0]; }
                }
            }
        } else {
            // Home / archive: use document title and site description
            $title = wp_get_document_title();
            $desc = get_bloginfo('description');
        }

        if ($image === '') {
            $image = isset($opts['social_default_image']) ? (string)$opts['social_default_image'] : '';
        }

        $type = $is_singular ? 'article' : 'website';

        $this->tag('meta', ['property'=>'og:type', 'content'=>$type]);
        $this->tag('meta', ['property'=>'og:site_name', 'content'=>get_bloginfo('name')]);
        if ($title !== '') { $this->tag('meta', ['property'=>'og:title', 'content'=>$title]); }
        if ($desc !== '')  { $this->tag('meta', ['property'=>'og:description', 'content'=>$desc]); }
        if ($url !== '')   { $this->tag('meta', ['property'=>'og:url', 'content'=>$url]); }
        if ($image !== '') { $this->tag('meta', ['property'=>'og:image', 'content'=>$image]); }

        $fb_app = isset($opts['social_fb_app_id']) ? trim((string)$opts['social_fb_app_id']) : '';
        if ($fb_app !== '') { $this->tag('meta', ['property'=>'fb:app_id', 'content'=>$fb_app]); }

        // Twitter cards
        $this->tag('meta', ['name'=>'twitter:card', 'content'=> $image !== '' ? 'summary_large_image' : 'summary']);
        if ($title !== '') { $this->tag('meta', ['name'=>'twitter:title', 'content'=>$title]); }
        if ($desc !== '')  { $this->tag('meta', ['name'=>'twitter:description', 'content'=>$desc]); }
        if ($image !== '') { $this->tag('meta', ['name'=>'twitter:image', 'content'=>$image]); }

        $tw_site = isset($opts['social_twitter_site']) ? trim((string)$opts['social_twitter_site']) : '';
        if ($tw_site !== '') { $this->tag('meta', ['name'=>'twitter:site', 'content'=>$tw_site]); }
    }

    private function tag(string $tag, array $attrs) {
        $out = '<' . $tag;
        foreach ($attrs as $k => $v) {
            if ($v === null || $v === '') { continue; }
            $out .= ' ' . esc_attr($k) . '="' . esc_attr((string)$v) . '"';
        }
        $out .= ' />' . "\n";
        echo $out;
    }
}
